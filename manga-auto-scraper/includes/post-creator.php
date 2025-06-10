<?php

function mas_post_exists($title) {
    $query = new WP_Query([
        'title' => $title,
        'post_type' => 'post',
        'post_status' => ['publish', 'pending', 'draft'],
        'posts_per_page' => 1,
    ]);
    return $query->have_posts();
}

function mas_create_wp_post($manga) {
    if (empty($manga['title'])) {
        error_log("[Manga Scraper] Post creation aborted: Empty title");
        return false;
    }

    // Check for existing manga (using correct post type)
    global $wpdb;
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'manga' LIMIT 1",
        $manga['title']
    ));
    
    if ($existing) {
        error_log("[Manga Scraper] Duplicate manga exists: {$manga['title']} (ID {$existing})");
        return false;
    }

    $post_data = [
        'post_title'   => $manga['title'],
        'post_content' => $manga['description'] ?? 'No description available',
        'post_status'  => 'publish',
        'post_type'    => 'manga' // ✅ Changed from 'post' to 'manga'
    ];

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        error_log("[Manga Scraper] Manga creation failed: " . $post_id->get_error_message());
        return false;
    }

    // ✅ Add REQUIRED MangaReader meta fields
    update_post_meta($post_id, '_wp_manga_alternative', '');
    update_post_meta($post_id, '_wp_manga_type', 'manga');
    update_post_meta($post_id, '_wp_manga_status', 'on-going');
    update_post_meta($post_id, '_wp_manga_views', 0);
    update_post_meta($post_id, '_wp_manga_rating', 0);

    error_log("[Manga Scraper] Manga created successfully - ID: {$post_id}");
    return $post_id;
}



