<?php
include 'db.php';  
session_start();

// Read filters from URL
$min_price = isset($_GET["min"]) ? intval($_GET["min"]) : 0;
$max_price = isset($_GET["max"]) ? intval($_GET["max"]) : 999999;
$brand_filter = isset($_GET["brand"]) ? trim(mysqli_real_escape_string($conn, $_GET["brand"])) : "";
$sort = isset($_GET["sort"]) ? $_GET["sort"] : "name_asc";

// Build sort clause
$sort_clause = "products.product_name ASC";
if ($sort === "price_asc")  $sort_clause = "products.price ASC";
if ($sort === "price_desc") $sort_clause = "products.price DESC";
if ($sort === "name_desc")  $sort_clause = "products.product_name DESC";

// Build brand filter
$brand_sql = $brand_filter ? "AND products.brand = '$brand_filter'" : "";

// Build price filter
$price_sql = "AND products.price BETWEEN $min_price AND $max_price";

$sql = "SELECT products.*, categories.category_name
        FROM products
        JOIN categories ON products.category_id = categories.category_id
        WHERE categories.category_name = 'Printers'
        AND products.is_visible = 1
        $brand_sql
        $price_sql
        ORDER BY $sort_clause";
$result = mysqli_query($conn, $sql);

// Get distinct brands for this category
$brands_result = mysqli_query($conn,
    "SELECT DISTINCT brand FROM products
     JOIN categories ON products.category_id = categories.category_id
     WHERE categories.category_name = 'Printers'
     AND is_visible = 1
     ORDER BY brand ASC"
);
?>
 
<!DOCTYPE html>
<html lang="en">
 
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($cat_name); ?> | Tinkercom</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
 
<body class="background">
 
    <header class="home-header">
        <div class="home-logo">
            <a href="index.php"><img src="assets/tinkercom-logoe.png" alt="" /></a>
            <h4>Tinkercom</h4>
        </div>
        <form class="search-bar" action="search.php" method="GET">
            <input type="search" name="q" placeholder="Search products..." />
            <button type="submit"><img src="assets/search.png" alt="" /></button>
        </form>
        <div class="header-icons">
            <a href="wishlist.php"><img src="assets/love.png" alt="" /></a>
            <a href="cart.php"><img src="assets/shopping-cart.png" alt="" /></a>
            <?php if (isset($_SESSION["customer_name"])): ?>
                <div class="user-dropdown">
                    <img src="assets/user.png" alt="" class="user-icon" />
                    <div class="dropdown-menu">
                        <p>Hi, <?php echo $_SESSION["customer_name"]; ?></p>
                        <a href="my-account.php">My Account</a>
                        <a href="my-account.php?tab=orders">My Orders</a>
                        <a href="my-account.php?tab=appointments">My Appointments</a>
                        <a href="wishlist.php">My Wishlist</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php"><img src="assets/user.png" alt="" /></a>
            <?php endif; ?>
        </div>
    </header>
 
    <nav class="navigation">
        <ul>
            <a href="index.php">HOME</a>
            <a href="printers.php">PRINTERS</a>
            <a href="accessories.php">ACCESSORIES</a>
            <a href="peripherals.php">PERIPHERALS</a>
            <a href="services.php">SERVICES</a>
        </ul>
    </nav>
 
    <section class="category-product">
        <h1><?php echo strtoupper(htmlspecialchars($cat_name)); ?></h1>
 
        <div class="category-row">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($product = mysqli_fetch_assoc($result)): ?>
 
                    <?php
                    $is_wishlisted = false;
                    if (isset($_SESSION["customer_id"])) {
                        $wl_sql    = "SELECT * FROM wishlist
                                      WHERE user_id = "  . $_SESSION["customer_id"] . "
                                      AND product_id = " . $product['product_id'];
                        $wl_result = mysqli_query($conn, $wl_sql);
                        $is_wishlisted = mysqli_num_rows($wl_result) > 0;
                    }
                    ?>
 
                    <div class="category-container">
                        <div class="category-img">
                            <a href="product-details.php?id=<?php echo $product['product_id']; ?>">
                                <img src="<?php echo $product['image']; ?>"
                                     alt="<?php echo $product['product_name']; ?>" />
                            </a>
                        </div>
 
                        <div class="category-info">
                            <h2><?php echo $product['product_name']; ?></h2>
                            <h3>₱<?php echo number_format($product['price'], 2); ?></h3>
                        </div>
 
                        <div class="category-nav">
                            <a class="view"
                               href="product-details.php?id=<?php echo $product['product_id']; ?>">VIEW</a>
                            <a class="fav"
                               href="add-to-wishlist.php?id=<?php echo $product['product_id']; ?>">
                                <img src="<?php echo $is_wishlisted ? 'assets/love-filled.png' : 'assets/love.png'; ?>"
                                     alt="wishlist"
                                     class="wishlist-icon" />
                            </a>
                        </div>  
                    </div>
 
                <?php endwhile; ?>
            <?php else: ?>
                <div class="search-empty">
                    <p class="search-empty-title">
                        No products available in this category yet.
                    </p>
                    <a href="index.php" class="search-back-btn">Back to Home</a>
                </div>
            <?php endif; ?>
        </div>
 
    </section>
 
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
                <a href="#">Tinkercom Computer Parts and Accessories Shop</a>
                <a href="#">0961-346-9709</a>
            </div>
            <div class="footer-location">
                <h3>LOCATE US</h3>
                <a href="#">Blk 6 Lot 4 Alegra Heights<br />1 Brgy. San Vicente Santa<br />Maria, City of Santa Maria,<br />3022 Bulacan</a>
            </div>
            <div class="home-logo">
                <img src="assets/tinkercom-logoe.png" alt="" />
                <h3>Tinkercom</h3>
            </div>
        </div>
    </footer>
 
    <div id="atc-toast"></div>
    <script src="javascript.js"></script>
</body>
 
</html>