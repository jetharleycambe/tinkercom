<?php
session_start();
include 'db.php';
include 'log_action.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

$action = $_POST["action"] ?? '';

if ($action === "add") {
    $name = mysqli_real_escape_string($conn, trim($_POST["category_name"]));
    mysqli_query($conn, "INSERT INTO categories (category_name) VALUES ('$name')");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Added Category', "Added: $name");
    header("Location: admin-categories.php?success=added");
    exit;
}

if ($action === "edit") {
    $id   = intval($_POST["category_id"]);
    $name = mysqli_real_escape_string($conn, trim($_POST["category_name"]));
    mysqli_query($conn, "UPDATE categories SET category_name = '$name' WHERE category_id = $id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Edited Category', "Updated category ID: $id to $name");
    header("Location: admin-categories.php?success=edited");
    exit;
}

if ($action === "delete") {
    $id = intval($_POST["category_id"]);

    // Hindi pwedeng i-delete kung may products pa
    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE category_id = $id"));
    if ($check["count"] > 0) {
        header("Location: admin-categories.php?success=error");
        exit;
    }

    mysqli_query($conn, "DELETE FROM categories WHERE category_id = $id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Deleted Category', "Deleted category ID: $id");
    header("Location: admin-categories.php?success=deleted");
    exit;
}

header("Location: admin-categories.php");
exit;
?>