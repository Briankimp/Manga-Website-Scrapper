<?php
/**
 * Plugin Name: Manga Auto Scraper
 * Description: Scrapes manga from go-manga.com and uploads chapters/images via FTP.
 * Version: 1.0
 * Author: Your Name
 */

// Schedule weekly scraper
register_activation_hook(__FILE__, 'mas_schedule_scraper');
function mas_schedule_scraper() {
    if (!wp_next_scheduled('mas_run_scraper')) {
        wp_schedule_event(time(), 'weekly', 'mas_run_scraper');
    }
    // Run the scraper immediately after activation
    mas_run_main_scraper();
    
}

register_deactivation_hook(__FILE__, 'mas_clear_scraper');
function mas_clear_scraper() {
    wp_clear_scheduled_hook('mas_run_scraper');
}

add_action('mas_run_scraper', 'mas_run_main_scraper');

require_once plugin_dir_path(__FILE__) . 'includes/ftp-upload.php';


function mas_run_main_scraper() {
    error_log("[Manga Scraper] Starting scraper at " . date('Y-m-d H:i:s'));
    
    require_once plugin_dir_path(__FILE__) . 'scraper/go-manga.php';
    require_once plugin_dir_path(__FILE__) . 'includes/post-creator.php';
    require_once plugin_dir_path(__FILE__) . 'includes/ftp-upload.php';

    // 1. Scrape manga data
    $manga = mas_scrape_go_manga();
    if (!$manga || empty($manga['chapters'])) {
        error_log("[Manga Scraper] Scraping failed or no chapters found");
        return false;
    }

    error_log("[Manga Scraper] Successfully scraped: " . $manga['title'] . 
             " with " . count($manga['chapters']) . " chapters");

    // 2. Create WordPress post
    $post_id = mas_create_wp_post($manga);
    if (!$post_id) {
        error_log("[Manga Scraper] Post creation failed");
        return false;
    }

    error_log("[Manga Scraper] Created post ID: $post_id");

    // 3. Process chapters (newest first)
    $uploaded_chapters = 0;
    $manga_slug = sanitize_title($manga['title']);

    foreach ($manga['chapters'] as $chapter) {
        if (empty($chapter['images'])) {
            error_log("[Manga Scraper] No images found for Chapter {$chapter['number']}");
            continue;
        }

        // Download images temporarily
        $temp_images = [];
        foreach ($chapter['images'] as $img_url) {
            $temp_path = mas_download_image($img_url);
            if ($temp_path) {
                $temp_images[] = $temp_path;
            }
        }

        if (empty($temp_images)) {
            error_log("[Manga Scraper] Failed to download images for Chapter {$chapter['number']}");
            continue;
        }

        // Upload via FTP
        $upload_result = mas_upload_chapter(
            $manga_slug,
            $chapter['number'],
            $temp_images
        );

        // Cleanup temp files
        foreach ($temp_images as $temp_path) {
            if (file_exists($temp_path)) {
                unlink($temp_path);
            }
        }

        if ($upload_result) {
            $uploaded_chapters++;
            error_log("[Manga Scraper] Successfully uploaded Chapter {$chapter['number']}");
            
            // Create chapter post in WordPress
            mas_create_chapter_post(
                $post_id,
                $chapter['number'],
                $chapter['title'],
                count($temp_images)
            );
        } else {
            error_log("[Manga Scraper] FTP upload failed for Chapter {$chapter['number']}");
        }

        // Rate limiting
        sleep(2);
    }

    error_log("[Manga Scraper] Completed. Uploaded $uploaded_chapters chapters.");
    return true;
}

// Helper function to download images
function mas_download_image($url) {
    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        error_log("[Image Download] Failed: " . $url . " - " . $tmp->get_error_message());
        return false;
    }
    
    // Validate it's an image
    $mime = mime_content_type($tmp);
    if (strpos($mime, 'image/') !== 0) {
        unlink($tmp);
        error_log("[Image Download] Not an image: " . $url);
        return false;
    }
    
    return $tmp;
}

// Function to create chapter posts
function mas_create_chapter_post($manga_id, $chapter_num, $chapter_title, $image_count) {
    $chapter_data = [
        'post_title'   => "Chapter $chapter_num - $chapter_title",
        'post_content' => "[manga_images count=$image_count]",
        'post_status'  => 'publish',
        'post_type'    => 'chapter',
        'post_parent'  => $manga_id
    ];
    
    $chapter_id = wp_insert_post($chapter_data);
    
    if ($chapter_id) {
        update_post_meta($chapter_id, '_chapter_number', $chapter_num);
        update_post_meta($chapter_id, '_images_count', $image_count);
        return $chapter_id;
    }
    
    return false;
}

