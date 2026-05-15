<?php
$host = "tinkercom-db-tinkercom-1.h.aivencloud.com";
$username = "avnadmin";
$password = getenv('DB_PASSWORD');
$database = "defaultdb";
$port = 28987;

$conn = mysqli_init();
$ca_path = __DIR__ . '/ca.pem';
mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);
mysqli_real_connect($conn, $host, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

class DBSessionHandler implements SessionHandlerInterface {
    public function open($path, $name) { return true; }
    public function close() { return true; }
    public function read($id) {
        global $conn;
        $id = mysqli_real_escape_string($conn, $id);
        $result = mysqli_query($conn, "SELECT data FROM php_sessions WHERE session_id='$id' AND expires > NOW()");
        if ($row = mysqli_fetch_assoc($result)) return $row['data'];
        return '';
    }
    public function write($id, $data) {
        global $conn;
        $id = mysqli_real_escape_string($conn, $id);
        $data = mysqli_real_escape_string($conn, $data);
        mysqli_query($conn, "REPLACE INTO php_sessions (session_id, data, expires) VALUES ('$id', '$data', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        return true;
    }
    public function destroy($id) {
        global $conn;
        $id = mysqli_real_escape_string($conn, $id);
        mysqli_query($conn, "DELETE FROM php_sessions WHERE session_id='$id'");
        return true;
    }
    public function gc($max) {
        global $conn;
        mysqli_query($conn, "DELETE FROM php_sessions WHERE expires < NOW()");
        return true;
    }
}

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);