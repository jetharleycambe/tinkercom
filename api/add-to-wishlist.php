<?php
session_start();
include 'db.php';

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET["id"])) {
    $product_id = intval($_GET["id"]);
    $user_id    = $_SESSION["customer_id"];

    // Check kung nandoon na sa wishlist
    $check_sql    = "SELECT * FROM wishlist 
                     WHERE user_id = $user_id 
                     AND product_id = $product_id";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        // Nandoon na — i-remove (toggle off)
        mysqli_query($conn, "DELETE FROM wishlist 
                             WHERE user_id = $user_id 
                             AND product_id = $product_id");
        $action = "removed";
    } else {
        // Wala pa — i-add (toggle on)
        mysqli_query($conn, "INSERT INTO wishlist (user_id, product_id) 
                             VALUES ($user_id, $product_id)");
        $action = "added";
    }
}

// I-redirect pabalik — alisin muna yung existing wishlisted param sa URL
$redirect = isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "index.php";

// Alisin yung lahat ng existing wishlisted or added params
$redirect = preg_replace('/([?&])wishlisted=[^&]*(&|$)/', '$1', $redirect);
$redirect = preg_replace('/([?&])added=[^&]*(&|$)/', '$1', $redirect);
$redirect = rtrim($redirect, '?&');

if (strpos($redirect, "?") !== false) {
    header("Location: " . $redirect . "&wishlisted=" . $action);
} else {
    header("Location: " . $redirect . "?wishlisted=" . $action);
}
exit;
?>