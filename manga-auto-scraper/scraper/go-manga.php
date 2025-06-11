<?php

function mas_scrape_go_manga() {
    $manga_url = 'https://www.go-manga.com/im-not-kind-talent/';
    
    // 1. Fetch main manga page with better headers
    $response = wp_remote_get($manga_url, [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ],
        'timeout' => 45,
        'sslverify' => false,
        'redirection' => 5
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

    // 3. Extract chapter links
    $chapters = [];
    $chapter_links = $xpath->query("//div[contains(@class, 'eplister')]//li//a");
    
    foreach ($chapter_links as $link) {
        $chapter_url = $link->getAttribute('href');
        $chapter_title = trim($link->nodeValue);
        
        preg_match('/-(\d+)\/$/', $chapter_url, $matches);
        $chapter_num = isset($matches[1]) ? (float)$matches[1] : 0;
        
        if (!$chapter_num || isset($chapters[$chapter_num])) continue;
        
        $chapters[$chapter_num] = [
            'number' => $chapter_num,
            'title' => $chapter_title,
            'url' => $chapter_url,
            'images' => []
        ];
    }

    krsort($chapters);
    $chapters = array_values($chapters);

    // 4. Fetch images from each chapter
    $count = 0;
    foreach ($chapters as &$chapter) {
        if ($count++ >= 3) break;
        error_log("[Scraper] Processing Chapter {$chapter['number']}: {$chapter['url']}");
        
        $chap_response = wp_remote_get($chapter['url'], [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Referer' => $manga_url,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
            ],
            'timeout' => 60,
            'sslverify' => false,
            'redirection' => 5
        ]);
        
        if (is_wp_error($chap_response)) {
            error_log("[Scraper] Failed to fetch chapter: " . $chap_response->get_error_message());
            continue;
        }
        
        $chap_html = wp_remote_retrieve_body($chap_response);
        file_put_contents(
            plugin_dir_path(__FILE__) . "debug-chapter-{$chapter['number']}.html", 
            $chap_html
        );
        
        // Try multiple methods to extract images
        $images = [];
        
        // Method 1: DOMDocument + XPath
        $chap_dom = new DOMDocument();
        @$chap_dom->loadHTML(mb_convert_encoding($chap_html, 'HTML-ENTITIES', 'UTF-8'));
        $chap_xpath = new DOMXPath($chap_dom);
        
        $img_nodes = $chap_xpath->query("//div[@id='readerarea']//img[@src] | //div[@id='readerarea']//img[@data-src]");
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if (!empty($src)) {
                $src = str_replace(' ', '%20', trim($src));
                $images[] = $src;
            }
        }
        
        // Method 2: Regex fallback if no images found
        if (empty($images)) {
            preg_match_all('/<img[^>]+(src|data-src)=["\']([^"\']+)["\']/i', $chap_html, $matches);
            if (!empty($matches[2])) {
                $images = array_map(function($src) {
                    return str_replace(' ', '%20', trim($src));
                }, $matches[2]);
            }
        }
        
        $chapter['images'] = array_unique(array_filter($images));
        error_log("[Scraper] Found " . count($chapter['images']) . " images for Chapter {$chapter['number']}");
        
        sleep(3); // Respect server rate limits
    }

    return [
        'title' => $title,
        'description' => $description,
        'status' => 'ongoing',
        'chapters' => $chapters,
        'scrape_time' => current_time('mysql')
    ];
}