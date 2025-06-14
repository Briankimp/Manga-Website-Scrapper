<?php
/**
 * Plugin Name: Manga Auto Scraper
 * Description: Scrapes manga from go-manga.com and uploads chapters/images via FTP.
 * Version: 1.0
 * Author: brianmunene.vercel.app
 */

register_activation_hook(__FILE__, 'mas_schedule_scraper');
function mas_schedule_scraper() {
    if (!wp_next_scheduled('mas_run_scraper')) {
        wp_schedule_event(time(), 'weekly', 'mas_run_scraper');
    }
    mas_run_main_scraper();
}

register_deactivation_hook(__FILE__, 'mas_clear_scraper');
function mas_clear_scraper() {
    wp_clear_scheduled_hook('mas_run_scraper');
}

add_action('mas_run_scraper', 'mas_run_main_scraper');

function mas_run_main_scraper() {
    error_log("[Scraper] Starting at " . date('Y-m-d H:i:s'));
    
    require_once plugin_dir_path(__FILE__) . 'scraper/go-manga.php';
    require_once plugin_dir_path(__FILE__) . 'includes/post-creator.php';
    require_once plugin_dir_path(__FILE__) . 'includes/ftp-upload.php';

    $manga = mas_scrape_go_manga();
    if (!$manga || empty($manga['chapters'])) {
        error_log("[Scraper] Failed: No manga data");
        return false;
    }

    $post_id = mas_create_wp_post($manga);
    if (!$post_id) {
        error_log("[Scraper] Failed: Post creation");
        return false;
    }
    
    if (!empty($manga['cover_url'])) {
        mas_set_manga_cover($post_id, $manga['cover_url']);
    }

    // PROCESS ALL CHAPTERS (REMOVED TEST LIMIT)
    foreach ($manga['chapters'] as $chapter) {
        if (empty($chapter['images'])) {
            error_log("[Scraper] Skipped chapter {$chapter['number']}: No images");
            continue;
        }

        $image_infos = [];
        foreach ($chapter['images'] as $img_url) {
            $temp_path = mas_download_image($img_url);
            if ($temp_path) $image_infos[] = [$temp_path, $img_url];
        }
        
        if (empty($image_infos)) {
            error_log("[Scraper] Skipped chapter {$chapter['number']}: Download failed");
            continue;
        }
        
        $chapter_date = date('dmY');
        $uploaded_urls = mas_upload_chapter($chapter_date, $image_infos);
        
        // Cleanup regardless of upload status
        foreach ($image_infos as $img_info) {
            if (file_exists($img_info[0])) unlink($img_info[0]);
        }
        
        if (is_array($uploaded_urls)) {
            $chapter_id = mas_create_chapter_post(
                $post_id,
                $chapter['number'],
                $chapter['title'],
                $uploaded_urls
            );
            
            if ($chapter_id) {
                error_log("[Scraper] Created chapter {$chapter['number']}: ID {$chapter_id}");
            }
        }

        sleep(2);
    }
    return true;
}

function mas_create_chapter_post($manga_id, $number, $title, $image_urls) {
    $chapter_title = $title ?: "Chapter {$number}";
    
    $content = '';
    foreach ($image_urls as $url) {
        $content .= '<img src="' . esc_url($url) . '" class="manga-page">';
    }
    
    $chapter_id = wp_insert_post([
        'post_parent'  => $manga_id,
        'post_title'   => $chapter_title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'chapter',
        'meta_input'   => [
            '_chapter_number' => $number
        ]
    ]);
    
    return is_wp_error($chapter_id) ? false : $chapter_id;
}

function mas_download_image($url) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $tmp = download_url($url);
    
    if (is_wp_error($tmp)) {
        error_log("[Download] Failed: {$url}");
        return false;
    }
    
    $mime = mime_content_type($tmp);
    if (strpos($mime, 'image/') !== 0) {
        unlink($tmp);
        error_log("[Download] Invalid MIME: {$url} ({$mime})");
        return false;
    }
    
    return $tmp;
}

function mas_set_manga_cover($post_id, $cover_url) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    
    $tmp = download_url($cover_url);
    if (is_wp_error($tmp)) return false;
    
    $file_array = [
        'name'     => basename($cover_url),
        'tmp_name' => $tmp,
    ];
    $attachment_id = media_handle_sideload($file_array, $post_id);
    if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
        error_log("[Scraper] Set cover for post {$post_id}");
    }
    return $attachment_id;
}