<?php

function mas_upload_to_ftp($local_file_path, $remote_file_name) {
    // FTP credentials (you can later load from WordPress options/settings page)
    $ftp_server = "127.0.0.1"; // Your provided FTP host
    $ftp_user = "brian";
    $ftp_pass = "passwordbrian123";
    $remote_file = 'test.jpg';
    $remote_dir = '/'; // uploads to C:\FTP_UPLOADS
    $upload = ftp_put($ftp_conn, $remote_dir . $remote_file_name, $local_file_path, FTP_BINARY);


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
