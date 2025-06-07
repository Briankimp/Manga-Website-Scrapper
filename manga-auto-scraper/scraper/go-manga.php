<?php

function mas_scrape_go_manga() {
    $url = 'https://www.go-manga.com/im-not-kind-talent/';

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return [];
    }

    $html = wp_remote_retrieve_body($response);

    // Use regex or DOM parsing - very simple example:
    preg_match('/<h1 class="entry-title">(.*?)<\/h1>/', $html, $titleMatch);
    preg_match('/<div class="summary__content">(.*?)<\/div>/s', $html, $descMatch);

    return [
        'title' => wp_strip_all_tags($titleMatch[1] ?? 'Unknown Title'),
        'description' => wp_strip_all_tags($descMatch[1] ?? ''),
    ];
}
