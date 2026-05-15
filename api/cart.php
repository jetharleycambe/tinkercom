<?php
include 'db.php';  
session_start();
if (!isset($_SESSION["customer_name"])) {
    header("Location: login.php");
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <title>My Cart | Tinkercom</title>
</head>

<body class="background">
    <?php include 'header.php'; ?>

    <?php
    // Fetch cart items from DB kung naka-login
    $cart_items = [];

    if (isset($_SESSION["customer_id"])) {
        $user_id = $_SESSION["customer_id"];

        // Hanapin yung cart ng user
        $cart_sql = "SELECT cart_id FROM carts WHERE user_id = $user_id";
        $cart_result = mysqli_query($conn, $cart_sql);
        // Check stock status
    
        if (mysqli_num_rows($cart_result) > 0) {
            $cart_row = mysqli_fetch_assoc($cart_result);
            $cart_id = $cart_row["cart_id"];

            // Kunin lahat ng items sa cart
            $items_sql = "SELECT cart_items.*, products.product_name, products.price, 
                                products.image, products.brand, products.status
                         FROM cart_items
                         JOIN products ON cart_items.product_id = products.product_id
                         WHERE cart_items.cart_id = $cart_id";
            $items_result = mysqli_query($conn, $items_sql);

            while ($item = mysqli_fetch_assoc($items_result)) {
                $cart_items[] = $item;
            }
        }
    }
    ?>

    <?php if (isset($_GET["empty"])): ?>
        <div class="alert alert-error" style="margin: 0 0 16px;">
            Your cart is empty. Add items before checking out.
        </div>
    <?php endif; ?>

    <section class="cart-page">
        <h1 class="page-title">Shopping Cart</h1>

        <?php if (isset($_GET["empty"])): ?>
            <div class="alert alert-error">
                Please select at least one item before checking out.
            </div>
        <?php endif; ?>

        <div class="cart-layout">

            <div class="cart-items-container">

                <div class="cart-header">
                    <span class="ch-product">Product</span>
                    <span class="ch-price">Price</span>
                    <span class="ch-quantity">Quantity</span>
                    <span class="ch-total">Total</span>
                    <span style="flex:1; text-align:center;">Select</span>
                </div>

                <?php if (!empty($cart_items)): ?>
                    <?php foreach ($cart_items as $item): ?>
                        <?php $subtotal = $item["price"] * $item["quantity"];
                        $isOut = ($item["status"] === "Out of Stock");
                        ?>

                        <div class="cart-card <?php echo $isOut ? 'out-of-stock' : ''; ?>"
                            data-item-id="<?php echo $item['cart_item_id']; ?>" data-price="<?php echo $item['price']; ?>"
                            data-qty="<?php echo $item['quantity']; ?>" data-stock="<?php echo $item['status']; ?>">

                            <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['product_name']; ?>"
                                class="cart-thumb" />

                            <div class="cart-card-info">
                                <p class="product-name"><?php echo strtoupper($item['product_name']); ?></p>
                                <p class="brand">Brand: <span class="blue-text"><?php echo $item['brand']; ?></span></p>
                                
                            </div>

                            <p class="cart-price">₱<?php echo number_format($item['price'], 2); ?></p>

                            <div class="quantity-controls">
                                <a href="update-cart-qty.php?id=<?php echo $item['cart_item_id']; ?>&action=decrease">
                                    <button type="button">-</button>
                                </a>
                                <span class="qty"><?php echo $item['quantity']; ?></span>
                                <a href="update-cart-qty.php?id=<?php echo $item['cart_item_id']; ?>&action=increase">
                                    <button type="button">+</button>
                                </a>
                            </div>

                            <p class="item-total">₱<?php echo number_format($subtotal, 2); ?></p>

                            <!-- Select button -->
                            <div>
                                <a href="delete-cart-item.php?id=<?php echo $item['cart_item_id']; ?>" class="cart-delete-btn"
                                    onclick="return confirm('Remove this item?')">
                                    Remove
                                </a>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>
                    <p class="cart-empty">Your cart is empty.</p>
                <?php endif; ?>

            </div>

            <aside class="order-summary">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span id="summary-count">0 items selected</span>
                </div>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotal">₱0.00</span>
                </div>
                <div class="summary-row total-row">
                    <span>Cart Total</span>
                    <span id="cart-total">₱0.00</span>
                </div>
                <button class="checkout-btn" onclick="proceedToCheckout()">
                    Proceed to Checkout
                </button>
            </aside>

        </div>
    </section>
    <?php include 'login-modal.php'; ?>
    <script src="javascript.js"></script>
</body>

</html>