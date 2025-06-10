<?php

function mas_scrape_go_manga() {
    $url = 'https://www.go-manga.com/im-not-kind-talent/';
    $response = wp_remote_get($url, [
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log("[Scraper] HTTP Error: " . $response->get_error_message());
        return false;
    }

    $html = wp_remote_retrieve_body($response);
    file_put_contents(plugin_dir_path(__FILE__) . 'debug-latest.html', $html);

    // Extract title
    preg_match('/<h1 class="entry-title"[^>]*>(.*?)<\/h1>/', $html, $titleMatch);
    $title = isset($titleMatch[1]) ? wp_strip_all_tags($titleMatch[1]) : 'Unknown Title';

    // Extract description - NEW METHOD
    $description = '';
    if (preg_match('/<div class="entry-content[^>]+itemprop="description"[^>]*>(.*?)<\/div>/s', $html, $descMatch)) {
        // Remove all HTML tags but keep line breaks
        $description = wp_strip_all_tags($descMatch[1], true);
        
        // Clean up leftover HTML entities and whitespace
        $description = html_entity_decode($description);
        $description = preg_replace('/\s+/', ' ', trim($description));
        
        // Remove the "เรื่องย่อ" prefix if present
        $description = preg_replace('/^เรื่องย่อ\s+/', '', $description);
    }

    error_log("[Scraper] Extracted Title: " . $title);
    error_log("[Scraper] Extracted Description Length: " . strlen($description));

    return [
        'title' => $title,
        'description' => $description ?: 'No description available'
    ];
}
