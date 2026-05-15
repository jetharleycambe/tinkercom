<?php

// 1. Your Aiven Connection Details
$host = "tinkercom-db-tinkercom-1.h.aivencloud.com"; 
$username = "avnadmin";       
$password = getenv('DB_PASSWORD'); 
$database = "defaultdb"; 
$port = 28987;

// 2. Initialize MySQLi
$conn = mysqli_init();

// 3. Point to the SSL certificate you downloaded
$ca_path = realpath(__DIR__ . '/../ca.pem');

mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);

// 4. Establish the connection
$real_connect = mysqli_real_connect(
    $conn, 
    $host, 
    $username, 
    $password, 
    $database, 
    $port, 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (!$real_connect) {
    die("Database connection failed: " . mysqli_connect_error());
}


?>