<?php

// Final production version: uploads to /server/manga/{date}/ with correct filename logic
function mas_upload_chapter($chapter_date, $image_paths) {
    $ftp_server = "138.201.55.106";
    $ftp_user = "mangapost";
    $ftp_pass = "GyLFsBpan5tiehMc";
    
    $conn = ftp_connect($ftp_server);
    if (!$conn) {
        error_log("FTP connection failed to server: {$ftp_server}");
        return false;
    }

    if (!ftp_login($conn, $ftp_user, $ftp_pass)) {
        error_log("FTP login failed for user: {$ftp_user}");
        ftp_close($conn);
        return false;
    }
    ftp_pasv($conn, true);

    // Create date-based manga directory (e.g., 11062025)
    $manga_dir = "/manga/{$chapter_date}/";
    if (@ftp_mkdir($conn, $manga_dir)) {
        error_log("[FTP] Created or confirmed directory: $manga_dir");
    } else {
        error_log("[FTP] Failed to create directory or already exists: $manga_dir");
    }

    // Log the image paths for debugging
    error_log("Uploading images: " . print_r($image_paths, true));

    // Upload each image
    foreach ($image_paths as $i => $img_info) {
        // $img_info should be [local_path, original_url] for best uniqueness
        $local_path = $img_info[0];
        $original_url = $img_info[1];
        if (!file_exists($local_path)) {
            error_log("Local file missing, skipping upload: {$local_path}");
            continue;
        }
        // Hash the full original image URL for filename uniqueness
        $hash = substr(md5($original_url), 0, 10);
        $page_num = str_pad($i+1, 2, '0', STR_PAD_LEFT);
        $remote_file = $manga_dir . "{$hash}_{$page_num}.jpg";
        if (ftp_put($conn, $remote_file, $local_path, FTP_BINARY)) {
            unlink($local_path);
            error_log("Uploaded: {$remote_file}");
        } else {
            error_log("Failed to upload: {$local_path} to {$remote_file}");
        }
    }

    ftp_close($conn);
    return true;
}