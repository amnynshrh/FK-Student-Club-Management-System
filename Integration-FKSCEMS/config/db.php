<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'fk_scems_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed. Please try again later."
    ]);

    exit;
}

mysqli_set_charset($conn, DB_CHARSET);
