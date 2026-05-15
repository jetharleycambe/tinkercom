<?php
include 'db.php';
session_start();

// Delete from database
$id = session_id();
$id = mysqli_real_escape_string($conn, $id);
mysqli_query($conn, "DELETE FROM php_sessions WHERE session_id='$id'");

// Destroy session fully
session_unset();
session_destroy();

header('Location: /index.php');
exit;