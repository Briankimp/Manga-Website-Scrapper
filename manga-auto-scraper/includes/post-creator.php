<?php

function mas_create_wp_post($manga) {
    if (empty($manga['title'])) return;

    $post_data = [
        'post_title'    => $manga['title'],
        'post_content'  => $manga['description'],
        'post_status'   => 'publish',
        'post_type'     => 'post', // if MangaReader uses a custom post type, update this
    ];

    // Prevent duplicate posting
    if (get_page_by_title($manga['title'], OBJECT, 'post')) return;

    wp_insert_post($post_data);
}
