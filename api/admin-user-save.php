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

if ($action === "delete") {
    $user_id = intval($_POST["user_id"]);

    // Hindi pwedeng i-delete ang sariling account
    if ($user_id === intval($_SESSION["customer_id"])) {
        header("Location: admin-users.php?success=error");
        exit;
    }

    mysqli_query($conn, "DELETE FROM user_roles WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM wishlist WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM cart_items WHERE cart_id IN (SELECT cart_id FROM carts WHERE user_id = $user_id)");
    mysqli_query($conn, "DELETE FROM carts WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Deleted User', "Deleted user ID: $user_id");

    header("Location: admin-users.php?success=deleted");
    exit;
}

header("Location: admin-users.php");
exit;
?>