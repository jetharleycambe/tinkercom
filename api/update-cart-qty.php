<?php
include 'db.php';  
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET["id"]) && isset($_GET["action"])) {
    $cart_item_id = intval($_GET["id"]);
    $action       = $_GET["action"]; // "increase" or "decrease"
    $user_id      = $_SESSION["customer_id"];

    // Security check — yung item ba ay sa user na naka-login?
    $check_sql    = "SELECT cart_items.* FROM cart_items
                     JOIN carts ON cart_items.cart_id = carts.cart_id
                     WHERE cart_items.cart_item_id = $cart_item_id
                     AND carts.user_id = $user_id";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $item = mysqli_fetch_assoc($check_result);

        if ($action === "increase") {
    // Check current stock before increasing
    $product_id_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT p.stock FROM products p
         JOIN cart_items ci ON ci.product_id = p.product_id
         WHERE ci.cart_item_id = $cart_item_id"
    ));
    
    if ($product_id_row && $item['quantity'] < $product_id_row['stock']) {
        mysqli_query($conn, "UPDATE cart_items SET quantity = quantity + 1
                             WHERE cart_item_id = $cart_item_id");
    }
    // else: already at max stock, do nothing
} elseif ($action === "decrease") {
            if ($item["quantity"] > 1) {
                // Bawasan lang kung hindi pa 1
                mysqli_query($conn, "UPDATE cart_items SET quantity = quantity - 1
                                     WHERE cart_item_id = $cart_item_id");
            } else {
                // Kung 1 na at binawasan pa — i-delete na
                mysqli_query($conn, "DELETE FROM cart_items
                                     WHERE cart_item_id = $cart_item_id");
            }
        }
    }
}

header("Location: cart.php");
exit;
?>