<?php

function mas_upload_chapter($chapter_date, $image_paths) {
    $ftp_server = "138.201.55.106";
    $ftp_user = "mangapost";
    $ftp_pass = "GyLFsBpan5tiehMc";
    $image_base_url = 'https://mangadomain.com/manga/';
    
    $conn = ftp_connect($ftp_server);
    if (!$conn) {
        error_log("[FTP] Connection failed: {$ftp_server}");
        return false;
    }

    if (!ftp_login($conn, $ftp_user, $ftp_pass)) {
        error_log("[FTP] Login failed: {$ftp_user}");
        ftp_close($conn);
        return false;
    }
    ftp_pasv($conn, true);

    $manga_dir = "/manga/{$chapter_date}/";
    if (@ftp_mkdir($conn, $manga_dir)) {
        error_log("[FTP] Created directory: $manga_dir");
    }

    $uploaded_urls = [];
    
    foreach ($image_paths as $i => $img_info) {
        $local_path = $img_info[0];
        $original_url = $img_info[1];
        
        if (!file_exists($local_path)) {
            error_log("[FTP] File missing: {$local_path}");
            continue;
        }
        
        $hash = substr(md5($original_url), 0, 10);
        $page_num = str_pad($i+1, 2, '0', STR_PAD_LEFT);
        $filename = "{$hash}_{$page_num}.jpg";
        $remote_file = $manga_dir . $filename;
        
        if (ftp_put($conn, $remote_file, $local_path, FTP_BINARY)) {
            $uploaded_urls[] = $image_base_url . $chapter_date . '/' . $filename;
            unlink($local_path);
            error_log("[FTP] Uploaded: {$filename}"); // ADDED LOGGING
        } else {
            error_log("[FTP] Failed: {$filename}"); // ADDED LOGGING
        }
    }

    ftp_close($conn);
    return $uploaded_urls;
}