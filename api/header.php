<?php
/**
 * PART 2 — SHARED HEADER COMPONENT
 * FILE: header.php  (save in your root project folder)
 *
 * HOW TO USE:
 *   Replace the entire <header>...</header> block in EVERY .php page with:
 *   <?php include 'header.php'; ?>
 *
 * BEFORE including this file, set $active_nav to highlight the correct nav link:
 *   $active_nav = "home";        // on index.php
 *   $active_nav = "printers";    // on printers.php
 *   $active_nav = "accessories"; // on accessories.php
 *   $active_nav = "peripherals"; // on peripherals.php
 *   $active_nav = "services";    // on services.php
 *
 * NOTE: session_start() and include 'db.php' must already be called
 *       BEFORE including this file.
 */

// Safety — if $active_nav not set, default to nothing
if (!isset($active_nav)) $active_nav = "";

// Get cart item count for badge (only if logged in)
$cart_count = 0;
if (isset($_SESSION['customer_id']) && isset($conn)) {
    $cart_sql = "SELECT SUM(ci.quantity) as total
                 FROM cart_items ci
                 JOIN carts c ON ci.cart_id = c.cart_id
                 WHERE c.user_id = " . intval($_SESSION['customer_id']);
    $cart_res = mysqli_query($conn, $cart_sql);
    if ($cart_res) {
        $cart_row  = mysqli_fetch_assoc($cart_res);
        $cart_count = $cart_row['total'] ? intval($cart_row['total']) : 0;
    }
}
?>

<!-- ============================================================
     HEADER
     ============================================================ -->
<header class="home-header">

    <!-- Logo -->
    <div class="home-logo">
        <a href="index.php"><img src="assets/tinkercom-logoe.png" alt="Tinkercom Logo"></a>
        <h4>Tinkercom</h4>
    </div>

    <!-- Search Bar -->
    <form class="search-bar" action="search.php" method="GET" autocomplete="off">
        <div class="search-wrapper">
            <input type="search" name="q" id="searchInput"
                   value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                   placeholder="Search products, brands..." />
            <div class="search-suggestions" id="searchSuggestions"></div>
        </div>
        <button type="submit"><img src="assets/search.png" alt="Search" /></button>
    </form>

    <!-- Header Icons: Wishlist, Cart, User -->
    <div class="header-icons">

        <!-- Wishlist -->
        <a href="<?php echo isset($_SESSION['customer_id']) ? 'wishlist.php' : '#'; ?>"
           <?php if (!isset($_SESSION['customer_id'])): ?>onclick="openLoginModal()"<?php endif; ?>
           title="My Wishlist">
            <img src="assets/love.png" alt="Wishlist">
        </a>

        <!-- Cart (with badge) -->
        <a href="<?php echo isset($_SESSION['customer_id']) ? 'cart.php' : '#'; ?>"
           <?php if (!isset($_SESSION['customer_id'])): ?>onclick="openLoginModal()"<?php endif; ?>
           title="My Cart" class="cart-icon-wrap">
            <img src="assets/shopping-cart.png" alt="Cart">
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?php echo $cart_count > 99 ? '99+' : $cart_count; ?></span>
            <?php endif; ?>
        </a>

        <!-- User Menu -->
        <?php if (isset($_SESSION['customer_name'])): ?>
            <div class="user-dropdown">
                <img src="assets/user.png" alt="My Account" class="user-icon" />
                <div class="dropdown-menu">
                    <p class="dropdown-greeting">Hi, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>!</p>
                    <a href="my-account.php">My Account</a>
                    <a href="my-account.php?tab=orders">My Orders</a>
                    <a href="my-account.php?tab=appointments">My Bookings</a>
                    <a href="wishlist.php">My Wishlist</a>
                    <hr class="dropdown-divider">
                    <a href="logout.php" class="dropdown-logout">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="#" onclick="openLoginModal()" title="Login">
                <img src="assets/user.png" alt="Login">
            </a>
        <?php endif; ?>

    </div>
</header>

<!-- ============================================================
     NAVIGATION
     ============================================================ -->
<nav class="navigation">
    <ul>
        <a href="index.php"
           class="<?php echo $active_nav === 'home' ? 'active-nav' : ''; ?>">HOME</a>

        <!-- PRINTERS — single nav item (goes to printers.php) -->
        <div class="nav-dropdown">
            <a href="printers.php"
           class="<?php echo $active_nav === 'printers' ? 'active-nav' : ''; ?>">PRINTERS ▾</a>
            <div class="nav-dropdown-menu">
                <a href="printers.php?sub=10">Inkjet Printers</a>
                <a href="printers.php?sub=11">All-in-one Printers</a>
                <a href="printers.php?sub=12">Laser Printers</a>
            </div>
        </div>

        <!-- ACCESSORIES — has dropdown showing sub-categories -->
        <div class="nav-dropdown">
            <a href="accessories.php"
               class="<?php echo $active_nav === 'accessories' ? 'active-nav' : ''; ?>">
               ACCESSORIES ▾
            </a>
            <div class="nav-dropdown-menu">
                <a href="accessories.php?sub=30">Inks &amp; Toners</a>
                <a href="accessories.php?sub=31">Cables &amp; Adapters</a>
                <a href="accessories.php?sub=32">Storage</a>
                <a href="accessories.php?sub=33">Laptop Bags</a>
                <a href="accessories.php?sub=34">Mouse Pads</a>
            </div>
        </div>

        <!-- PERIPHERALS — has dropdown showing sub-categories -->
        <div class="nav-dropdown">
            <a href="peripherals.php"
               class="<?php echo $active_nav === 'peripherals' ? 'active-nav' : ''; ?>">
               PERIPHERALS ▾
            </a>
            <div class="nav-dropdown-menu">
                <a href="peripherals.php?sub=20">Keyboards</a>
                <a href="peripherals.php?sub=21">Mouse</a>
                <a href="peripherals.php?sub=22">Headset</a>
                <a href="peripherals.php?sub=23">Speakers</a>
                <a href="peripherals.php?sub=24">Webcam</a>
            </div>
        </div>

        <!-- COMPUTER PARTS — has dropdown showing sub-categories -->
        <div class="nav-dropdown">
            <a href="computer-parts.php"
               class="<?php echo $active_nav === 'computer-parts' ? 'active-nav' : ''; ?>">
               COMPUTER PARTS ▾
            </a>
            <div class="nav-dropdown-menu">
                <a href="computer-parts.php?sub=40">RAM</a>
                <a href="computer-parts.php?sub=41">SSD &amp; HDD</a>
                <a href="computer-parts.php?sub=42">Motherboard</a>
                <a href="computer-parts.php?sub=43">Processor</a>
                <a href="computer-parts.php?sub=44">Power Supply</a>
                <a href="computer-parts.php?sub=45">GPU</a>
            </div>
        </div>

        <a href="services.php"
           class="<?php echo $active_nav === 'services' ? 'active-nav' : ''; ?>">SERVICES</a>
    </ul>
</nav>