<?php

function mas_scrape_go_manga() {
    $manga_url = 'https://www.go-manga.com/im-not-kind-talent/';
    
    // 1. Fetch main manga page
    $response = wp_remote_get($manga_url, [
        'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        'timeout' => 45,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        error_log("[Scraper] HTTP Error: " . $response->get_error_message());
        return false;
    }

    $html = wp_remote_retrieve_body($response);
    file_put_contents(plugin_dir_path(__FILE__) . 'debug-main.html', $html);

    // 2. Extract basic manga info
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    $title_node = $xpath->query("//h1[contains(@class, 'entry-title')]")->item(0);
    $title = trim($title_node ? $title_node->nodeValue : 'Unknown Title');

    $description = trim($xpath->query("//div[contains(@class, 'entry-content')]")->item(0)->nodeValue ?? '');
    $description = preg_replace('/^เรื่องย่อ\s+/', '', $description);

    // 3. NEW: Extract chapter links with better selector
    $chapters = [];
    $chapter_links = $xpath->query("//div[contains(@class, 'eplister')]//li//a");
    
    foreach ($chapter_links as $link) {
        $chapter_url = $link->getAttribute('href');
        $chapter_title = trim($link->nodeValue);
        
        // Extract chapter number from URL (like -68 at the end)
        preg_match('/-(\d+)\/$/', $chapter_url, $matches);
        $chapter_num = isset($matches[1]) ? (float)$matches[1] : 0;
        
        // Skip duplicates and invalid chapters
        if (!$chapter_num || isset($chapters[$chapter_num])) continue;
        
        $chapters[$chapter_num] = [
            'number' => $chapter_num,
            'title' => $chapter_title,
            'url' => $chapter_url,
            'images' => []
        ];
    }

    // 4. Process chapters in reverse order (newest first)
    krsort($chapters);
    $chapters = array_values($chapters);

    // 5. Fetch images for each chapter (limit to 3 chapters for testing)
    foreach (array_slice($chapters, 0, 3) as &$chapter) {
        error_log("[Scraper] Processing Chapter {$chapter['number']}: {$chapter['url']}");
        
        $chap_response = wp_remote_get($chapter['url'], [
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
            'timeout' => 60,
            'sslverify' => false
        ]);
        
        if (is_wp_error($chap_response)) {
            error_log("[Scraper] Failed to fetch chapter: " . $chap_response->get_error_message());
            continue;
        }
        
        $chap_html = wp_remote_retrieve_body($chap_response);
        $chap_dom = new DOMDocument();
        @$chap_dom->loadHTML($chap_html);
        $chap_xpath = new DOMXPath($chap_dom);
        
        // NEW: Better image selector
        $images = [];
        $img_nodes = $chap_xpath->query("//div[@id='readerarea']//img");
        
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src');
            if (!empty($src)) {
                // Clean URL if needed
                $src = str_replace(' ', '%20', trim($src));
                $images[] = $src;
            }
        }
        
        $chapter['images'] = $images;
        error_log("[Scraper] Found " . count($images) . " images for Chapter {$chapter['number']}");
        
        // Save chapter HTML for debugging
        file_put_contents(
            plugin_dir_path(__FILE__) . "debug-chapter-{$chapter['number']}.html", 
            $chap_html
        );
        
        // Delay to avoid rate-limiting
        sleep(3);
    }

    return [
        'title' => $title,
        'description' => $description,
        'status' => 'ongoing',
        'chapters' => $chapters,
        'scrape_time' => current_time('mysql')
    ];
}