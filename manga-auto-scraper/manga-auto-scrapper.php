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

$local_file = plugin_dir_path(__FILE__) . 'sample.jpg';  // This is a test image you'll place in the plugin folder
$remote_name = 'test-upload.jpg';  // This is the name it will have on the FTP server

mas_upload_to_ftp($local_file, $remote_name);


function mas_run_main_scraper() {
    require_once plugin_dir_path(__FILE__) . 'scraper/go-manga.php';
    require_once plugin_dir_path(__FILE__) . 'includes/post-creator.php';

    $manga = mas_scrape_go_manga();
    mas_create_wp_post($manga);
}
