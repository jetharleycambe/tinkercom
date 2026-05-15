<?php
include 'db.php';  
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET["id"])) {
    $cart_item_id = intval($_GET["id"]);
    $user_id      = $_SESSION["customer_id"];

    // Security check — siguraduhing yung cart item na ito ay sa user na naka-login
    $sql = "DELETE FROM cart_items 
            WHERE cart_item_id = $cart_item_id
            AND cart_id IN (SELECT cart_id FROM carts WHERE user_id = $user_id)";

    mysqli_query($conn, $sql);
}

header("Location: cart.php");
exit;
?>