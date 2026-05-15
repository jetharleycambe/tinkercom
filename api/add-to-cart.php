<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = intval($_POST["product_id"]);
    $quantity   = isset($_POST["quantity"]) ? intval($_POST["quantity"]) : 1;

// Stock check
$stock_check = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT stock, status FROM products WHERE product_id = $product_id")
);

if (!$stock_check || $stock_check['stock'] <= 0 || $stock_check['status'] === 'Out of Stock') {
    $redirect = isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "index.php";
    header("Location: " . $redirect . "?out_of_stock=1");
    exit;
}

// Huwag mag-exceed sa available stock
if ($quantity > $stock_check['stock']) {
    $quantity = $stock_check['stock'];
}


    $quantity = isset($_POST["quantity"]) ? intval($_POST["quantity"]) : 1;

    // Kailangan naka-login ang user para makapag-add to cart
    if (!isset($_SESSION["customer_id"])) {
        header("Location: login.php");
        exit;
    }

    $user_id = $_SESSION["customer_id"];

    // Step 1: Hanapin kung may cart na ang user
    $cart_sql = "SELECT cart_id FROM carts WHERE user_id = $user_id";
    $cart_result = mysqli_query($conn, $cart_sql);

    if (mysqli_num_rows($cart_result) === 0) {
        // Wala pang cart — gumawa ng bago
        mysqli_query($conn, "INSERT INTO carts (user_id) VALUES ($user_id)");
        $cart_id = mysqli_insert_id($conn);
    } else {
        $cart_row = mysqli_fetch_assoc($cart_result);
        $cart_id = $cart_row["cart_id"];
    }

    // Step 2: Check kung nandoon na yung product sa cart
    $item_sql = "SELECT * FROM cart_items WHERE cart_id = $cart_id AND product_id = $product_id";
    $item_result = mysqli_query($conn, $item_sql);

    if (mysqli_num_rows($item_result) > 0) {
        // Nandoon na — dagdagan lang yung quantity
        // Get current qty in cart + check max stock
$current_in_cart = mysqli_fetch_assoc(mysqli_query(
    $conn, "SELECT ci.quantity, p.stock 
            FROM cart_items ci
            JOIN products p ON p.product_id = ci.product_id
            WHERE ci.cart_id = $cart_id AND ci.product_id = $product_id"
));
$new_qty = $current_in_cart['quantity'] + $quantity;
if ($new_qty > $current_in_cart['stock']) {
    $new_qty = $current_in_cart['stock'];
}
mysqli_query($conn, "UPDATE cart_items SET quantity = $new_qty 
                     WHERE cart_id = $cart_id AND product_id = $product_id");
    } else {
        // Bago — i-insert
        mysqli_query($conn, "INSERT INTO cart_items (cart_id, product_id, quantity) 
                             VALUES ($cart_id, $product_id, $quantity)");
    }
    include_once 'log_action.php';
    log_action($conn, 'USER', $user_id, $_SESSION['customer_name'], 'Added to Cart', "Product ID: $product_id, Qty: $quantity");
}

// I-redirect pabalik
$redirect = isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "index.php";

if (strpos($redirect, "?") !== false) {
    header("Location: " . $redirect . "&added=1");
} else {
    header("Location: " . $redirect . "?added=1");
}
exit;
?>