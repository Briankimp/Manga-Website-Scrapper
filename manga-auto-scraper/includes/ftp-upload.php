
<?php

function mas_upload_chapter($manga_slug, $chapter_num, $image_paths) {
    $ftp_server = "176.97.124.39";
    $ftp_user = "manga_neko1";
    $ftp_pass = "GCD3fZ6H2w4bNZdn";
    
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

    // Create manga directory if needed
    $manga_dir = "/server/manga/{$manga_slug}/";
    @ftp_mkdir($conn, $manga_dir);

    // Create chapter directory
    $chapter_dir = $manga_dir . "chapter-{$chapter_num}/";
    @ftp_mkdir($conn, $chapter_dir);

    // Upload each image
    foreach ($image_paths as $i => $local_path) {
        $remote_file = $chapter_dir . ($i+1) . '.jpg';
        if (!ftp_put($conn, $remote_file, $local_path, FTP_BINARY)) {
            error_log("Failed to upload: {$local_path}");
        }
    }

    ftp_close($conn);
    return true;
}