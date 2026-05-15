<?php
session_start();
include 'db.php';

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["customer_id"];

// Fetch wishlist items
$sql    = "SELECT wishlist.*, products.product_name, products.price, 
                  products.image, products.brand, products.status,
                  products.product_id
           FROM wishlist
           JOIN products ON wishlist.product_id = products.product_id
           WHERE wishlist.user_id = $user_id";
$result = mysqli_query($conn, $sql);

$wishlist_items = [];
while ($item = mysqli_fetch_assoc($result)) {
    $wishlist_items[] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <title>My Wishlist | Tinkercom</title>
</head>

<body class="background">
    <?php include 'header.php'; ?>

    <section class="wishlist-page">
        <h1 class="page-title">My Wishlist</h1>

        <div class="wishlist-container">
            <?php if (!empty($wishlist_items)): ?>
                <table class="wishlist-table">
                    <thead>
                        <tr>
                            <th id="product-tr">Product</th>
                            <th>Price</th>
                            <th>Stock Status</th>
                            <th>Add to cart</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wishlist_items as $item): ?>
                            <tr class="wishlist-row">
                                <td class="wishlist-cell">
                                    <img src="<?php echo $item['image']; ?>" 
                                         alt="<?php echo $item['product_name']; ?>" 
                                         class="wishlist-thumb">
                                    <div>
                                        <p class="product-name"><?php echo strtoupper($item['product_name']); ?></p>
                                        <p class="brand">Brand: <span class="blue-text"><?php echo $item['brand']; ?></span></p>
                                    </div>
                                </td>
                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <span class="<?php echo $item['status'] === 'In Stock' ? 'in-stock' : 'out-stock'; ?>">
                                        <?php echo $item['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['status'] === 'In Stock'): ?>
                                        <form action="add-to-cart.php" method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="atc-btn">Add to Cart</button>
                                        </form>
                                        <?php else: ?>
                                            <span class="unavailable">Unavailable</span>
                                            <?php endif; ?>
                                </td>
                                <td>
                                    <a href="add-to-wishlist.php?id=<?php echo $item['product_id']; ?>" 
                                       class="wishlist-remove">✕</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="cart-empty">Your wishlist is empty.</p>
            <?php endif; ?>
        </div>
    </section>
     <div id="atc-toast"></div>     
     <?php include 'login-modal.php'; ?>      
    <script src="javascript.js"></script>
</body>
<footer>
    <div class="footer-container">
        <div class="footer-links">
            <h3>LINKS</h3>
            <a href="my-account.php">My Account</a>
            <a href="my-account.php?tab=orders">My Order</a>
            <a href="cart.php">My Cart</a>
            <a href="wishlist.php">My Wishlist</a>
        </div>

        <div class="footer-contact">
            <h3>CONTACT US</h3>
            <a href="https://www.facebook.com/profile.php?id=61573685231394" target="_blank">Tinkercom Computer Parts
                and Accessories Shop</a>
            <a href="">0961-346-9709</a>
        </div>

        <div class="footer-location">
            <h3>LOCATE US</h3>
            <a href="https://maps.app.goo.gl/tyMutf7m7Y1wmgdE9" target="_blank">Blk 6 Lot 4 Alegra Heights <br> 1 Brgy.
                San Vicente Santa <br> Maria, City of Santa Maria, <br>
                3022 Bulacan</a>
        </div>

        <div class="home-logo">
            <img src="assets/tinkercom-logoe.png" alt="">
            <h3>Tinkercom</h3>
        </div>

    </div>
</footer>
</html>