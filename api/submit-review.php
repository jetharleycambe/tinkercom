<?php
include 'db.php';  
session_start();

// Must be logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my-account.php?tab=orders');
    exit;
}

$user_id    = intval($_SESSION['customer_id']);
$product_id = intval($_POST['product_id'] ?? 0);
$order_id   = intval($_POST['order_id']   ?? 0);
$rating     = intval($_POST['rating']     ?? 0);
$comment    = trim(mysqli_real_escape_string($conn, $_POST['comment'] ?? ''));

// Basic validation
if ($product_id === 0 || $order_id === 0 || $rating < 1 || $rating > 5) {
    header('Location: my-account.php?tab=orders&review_error=invalid');
    exit;
}

// Verify: this order belongs to this user AND is DELIVERED or COMPLETED
$order_check = mysqli_fetch_assoc(
    mysqli_query($conn,
        "SELECT status FROM orders
         WHERE order_id = $order_id AND user_id = $user_id"
    )
);

if (!$order_check || !in_array($order_check['status'], ['DELIVERED', 'COMPLETED'])) {
    header('Location: my-account.php?tab=orders&review_error=notallowed');
    exit;
}

// Verify: this product was actually in that order
$item_check = mysqli_fetch_assoc(
    mysqli_query($conn,
        "SELECT 1 FROM order_items
         WHERE order_id = $order_id AND product_id = $product_id"
    )
);

if (!$item_check) {
    header('Location: my-account.php?tab=orders&review_error=notallowed');
    exit;
}

// Check if already reviewed
$existing = mysqli_fetch_assoc(
    mysqli_query($conn,
        "SELECT review_id FROM product_reviews
         WHERE user_id = $user_id AND product_id = $product_id AND order_id = $order_id"
    )
);

if ($existing) {
    header('Location: my-account.php?tab=orders&review_error=already');
    exit;
}

// Insert review
mysqli_query($conn,
    "INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment)
     VALUES ($product_id, $user_id, $order_id, $rating, '$comment')"
);

header('Location: my-account.php?tab=orders&ostatus=DELIVERED&review_success=1');
exit;