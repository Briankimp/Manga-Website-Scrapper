<?php

function mas_upload_to_ftp($local_file_path, $remote_file_name) {
    // FTP credentials (you can later load from WordPress options/settings page)
    $ftp_server = "138.201.55.106"; // Your provided FTP host
    $ftp_user = "mangapost";
    $ftp_pass = "GyLFsBpan5tiehMc";
    $remote_dir = '/'; // uploads to C:\FTP_UPLOADS

    // Check if file exists before attempting upload
    if (!file_exists($local_file_path)) {
        error_log("FTP upload failed: File does not exist - $local_file_path");
        return false;
    }

    // Establish connection
    $ftp_conn = ftp_connect($ftp_server);

    if (!$ftp_conn) {
        error_log("FTP connection failed.");
        return false;
    }

    // Login
    $login = ftp_login($ftp_conn, $ftp_user, $ftp_pass);
    if (!$login) {
        error_log("FTP login failed.");
        ftp_close($ftp_conn);
        return false;
    }

    ftp_pasv($ftp_conn, true); // Enable passive mode, often required

    // Upload file
    $upload = ftp_put($ftp_conn, $remote_dir . $remote_file_name, $local_file_path, FTP_BINARY);
    if (!$upload) {
        error_log("FTP upload failed: $local_file_path");
    }

    ftp_close($ftp_conn);
    return $upload;
}

$local_file = plugin_dir_path(__FILE__) . 'sample.jpg';  // This is a test image you'll place in the plugin folder
$remote_name = 'test-upload.jpg';  // This is the name it will have on the FTP server

// Only attempt upload if file exists (prevents fatal error on plugin load)
if (file_exists($local_file)) {
    mas_upload_to_ftp($local_file, $remote_name);
} else {
    error_log("Sample file for FTP upload not found: $local_file");
}
