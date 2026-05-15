<?php
include 'db.php';  
session_start();

// --- Featured / Top Sellers (is_featured = 1) ---
$featured_sql = "SELECT products.*, categories.category_name
                 FROM products
                 JOIN categories ON products.category_id = categories.category_id
                 WHERE products.is_featured = 1
                   AND products.is_visible  = 1
                 LIMIT 8";
$featured_result = mysqli_query($conn, $featured_sql);

// --- Shop by Category: fetch all PARENT categories with product count ---
$parent_cats_sql = "SELECT c.category_id, c.category_name,
                           COUNT(p.product_id) AS product_count
                    FROM categories c
                    LEFT JOIN categories sub ON sub.parent_category_id = c.category_id
                    LEFT JOIN products p ON p.category_id = sub.category_id
                                        AND p.is_visible = 1
                    WHERE c.parent_category_id IS NULL
                      AND c.is_visible = 1
                    GROUP BY c.category_id, c.category_name
                    ORDER BY c.display_order ASC";
$parent_cats = mysqli_query($conn, $parent_cats_sql);

// Map parent category names to hero images (update paths if yours differ)
$cat_images = [
    'Printers' => 'assets/products/printers/printer.png',
    'Peripherals' => 'assets/products/keyboard/redragon-k552.png',
    'Accessories' => 'assets/products/inks/inks.png',
    'Computer Parts' => 'assets/products/mouse/hp-mouse.png',
];

// Map parent category names to browse links
$cat_links = [
    'Printers' => 'printers.php',
    'Peripherals' => 'peripherals.php',
    'Accessories' => 'accessories.php',
    'Computer Parts' => 'computer-parts.php',
];

// --- Sub-categories for "Browse by Type" quick-links ---
$sub_cats_sql = "SELECT c.category_id, c.category_name, c.parent_category_id,
                        p.category_name AS parent_name
                 FROM categories c
                 JOIN categories p ON c.parent_category_id = p.category_id
                 WHERE c.parent_category_id IS NOT NULL
                   AND c.is_visible = 1
                 ORDER BY p.display_order, c.display_order";
$sub_cats = mysqli_query($conn, $sub_cats_sql);

// Group sub-cats by parent
$grouped_subs = [];
while ($sc = mysqli_fetch_assoc($sub_cats)) {
    $grouped_subs[$sc['parent_name']][] = $sc;
}

// Sub-category page links map
function sub_link($parent, $sub_id)
{
    $map = [
        'Printers' => "printers.php?sub=$sub_id",
        'Peripherals' => "peripherals.php?sub=$sub_id",
        'Accessories' => "accessories.php?sub=$sub_id",
        'Computer Parts' => "accessories.php?sub=$sub_id",
    ];
    return $map[$parent] ?? "search.php?cat=$sub_id";
}

$active_nav = "home";

// --- Site Reviews for homepage ---
$site_reviews_sql = "SELECT sr.rating, sr.comment, sr.created_at,
                            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                            u.username
                     FROM site_reviews sr
                     JOIN users u ON sr.user_id = u.user_id
                     WHERE sr.comment IS NOT NULL AND sr.comment != ''
                     ORDER BY sr.created_at DESC
                     LIMIT 8";
$site_reviews_result = mysqli_query($conn, $site_reviews_sql);
$site_reviews_data = [];
while ($sr = mysqli_fetch_assoc($site_reviews_result)) {
    $site_reviews_data[] = $sr;
}

// Calculate overall site rating
$site_avg_sql = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM site_reviews")
);
$site_avg = $site_avg_sql['avg_rating'] ? round($site_avg_sql['avg_rating'], 1) : 0;
$site_total = intval($site_avg_sql['total']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tinkercom — Computer Parts, Accessories &amp; Printers</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
</head>

<body class="background">

    <?php include 'header.php'; ?>


    <div class="content">
        <div class="home-picture">
            <img src="assets/content-v2.jfif" alt="">
        </div>
        <div class="content-info">
            <div class="hero-text">
                <h1>Computer Parts, <br>Accessories &amp; Printers</h1>
                <p>From essential components to the latest tech equipment — reliable products and expert repair services
                    all in one place.</p>
                <div class="hero-actions">
                    <a href="printers.php" class="hero-btn-primary">Shop Printers</a>
                    <a href="services.php" class="hero-btn-secondary">Book a Service</a>
                </div>
            </div>
        </div>
    </div>


    <section class="departments">
        <div class="section-header">
            <h2>Shop by Department</h2>
            <p>Browse our complete range of computer products and services</p>
        </div>

        <div class="department-grid">
            <?php
            // Reset pointer
            mysqli_data_seek($parent_cats, 0);
            while ($cat = mysqli_fetch_assoc($parent_cats)):
                $img = $cat_images[$cat['category_name']] ?? 'assets/tinkercom-logoe.png';
                $link = $cat_links[$cat['category_name']] ?? 'index.php';
                $count = intval($cat['product_count']);
                ?>
                <a href="<?php echo $link; ?>" class="department-card">
                    <div class="dept-img">
                        <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($cat['category_name']); ?>">
                    </div>
                    <div class="dept-info">
                        <h3><?php echo htmlspecialchars($cat['category_name']); ?></h3>
                        <span><?php echo $count; ?> product<?php echo $count !== 1 ? 's' : ''; ?></span>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </section>


    <section class="top-sellers-v2">
        <div class="section-header">
            <h2>Top Sellers</h2>
            <p>Our most popular products this month</p>
        </div>

        <div class="top-sellers-grid">
            <?php
            mysqli_data_seek($featured_result, 0);
            while ($product = mysqli_fetch_assoc($featured_result)):
                $is_wishlisted = false;
                if (isset($_SESSION['customer_id'])) {
                    $wl_sql = "SELECT 1 FROM wishlist WHERE user_id = " . intval($_SESSION['customer_id']) . " AND product_id = " . intval($product['product_id']);
                    $wl_result = mysqli_query($conn, $wl_sql);
                    $is_wishlisted = mysqli_num_rows($wl_result) > 0;
                }
                $is_oos = $product['stock'] <= 0 || $product['status'] === 'Out of Stock';
                $discount_percent = !empty($product['discount_percent']) ? intval($product['discount_percent']) : 0;
                $disc_price = $discount_percent > 0 ? $product['price'] * (1 - $discount_percent / 100) : null;
                ?>
                <div class="product-card">

                    <!-- ── Badge priority: OOS > Sale > Top Seller ── -->
                    <?php if ($is_oos): ?>
                        <span class="product-badge badge-oos">Out of Stock</span>
                    <?php elseif ($discount_percent > 0): ?>
                        <span class="product-badge badge-sale">-<?php echo $discount_percent; ?>% OFF</span>
                    <?php elseif ($product['is_featured']): ?>
                        <span class="product-badge badge-hot">⭐ Top Seller</span>
                    <?php endif; ?>

                    <div class="product-card-img">
                        <a href="product-details.php?id=<?php echo $product['product_id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        </a>
                    </div>

                    <div class="product-card-body">
                        <span class="product-card-cat"><?php echo htmlspecialchars($product['category_name']); ?></span>
                        <h3 class="product-card-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <p class="product-card-brand"><?php echo htmlspecialchars($product['brand']); ?></p>

                        <?php if ($disc_price !== null): ?>
                            <!-- Discounted: show sale price + strikethrough original -->
                            <p class="product-card-price">
                                <span class="price-sale">₱<?php echo number_format($disc_price, 2); ?></span>
                                <span class="price-original">₱<?php echo number_format($product['price'], 2); ?></span>
                            </p>
                        <?php else: ?>
                            <p class="product-card-price">₱<?php echo number_format($product['price'], 2); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="product-card-actions">
                        <a class="btn-view" href="product-details.php?id=<?php echo $product['product_id']; ?>">View</a>
                        <a class="btn-wishlist"
                            href="<?php echo isset($_SESSION['customer_id']) ? 'add-to-wishlist.php?id=' . $product['product_id'] : '#'; ?>"
                            <?php if (!isset($_SESSION['customer_id'])): ?>onclick="openLoginModal()" <?php endif; ?>>
                            <img src="<?php echo $is_wishlisted ? 'assets/heart-filled.png' : 'assets/love.png'; ?>"
                                alt="wishlist">
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="why-us">
        <div class="section-header light">
            <h2>Why Choose Tinkercom?</h2>
            <p>Trusted by customers in Santa Maria, Bulacan and nearby cities</p>
        </div>
        <div class="why-us-grid">
            <div class="why-card">
                <div class="why-icon"></div>
                <h4>Wide Product Range</h4>
                <p>From inks and keyboards to full printer systems — everything your home or office needs.</p>
            </div>
            <div class="why-card">
                <div class="why-icon"></div>
                <h4>Professional Services</h4>
                <p>Deep cleaning, re-thermal, driver updates, and preventive maintenance by skilled technicians.</p>
            </div>
            <div class="why-card">
                <div class="why-icon"></div>
                <h4>Competitive Pricing</h4>
                <p>Fair prices on all products with regular promos and discounts for our loyal customers.</p>
            </div>
            <div class="why-card">
                <div class="why-icon"></div>
                <h4>Local &amp; Trusted</h4>
                <p>Based in Santa Maria, Bulacan — serving your community with honest and reliable service.</p>
            </div>
        </div>
    </section>


    <section class="reviews">
        <div class="section-header">
            <h2>What Customers Say</h2>
            <p>Real feedback from real buyers</p>
        </div>

        <?php if ($site_total > 0): ?>
            <!-- Overall rating summary -->
            <div class="site-rating-summary">
                <div class="site-rating-score"><?php echo number_format($site_avg, 1); ?></div>
                <div class="site-rating-right">
                    <div class="site-rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?php echo $i <= round($site_avg) ? 'srs-star active' : 'srs-star'; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <p class="site-rating-count">Based on <?php echo $site_total; ?> customer
                        review<?php echo $site_total !== 1 ? 's' : ''; ?></p>
                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <a href="my-account.php?tab=rate-us" class="site-rate-cta">Leave Your Review</a>
                    <?php else: ?>
                        <a href="#" class="site-rate-cta" onclick="openLoginModal()">Sign in to Rate Us</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="site-rating-empty">
                <p>No reviews yet — be the first!</p>
                <a href="<?php echo isset($_SESSION['customer_id']) ? 'my-account.php?tab=rate-us' : 'login.php'; ?>"
                    class="site-rate-cta"> Rate Tinkercom</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($site_reviews_data)): ?>
            <div class="reviews-grid">
                <?php foreach ($site_reviews_data as $sr):
                    $display = trim($sr['full_name']) !== '' ? htmlspecialchars(trim($sr['full_name'])) : htmlspecialchars($sr['username']);
                    $initial = strtoupper(substr($display, 0, 1));
                    ?>
                    <div class="review-card">
                        <div class="review-card-header">
                            <div class="review-card-avatar"><?php echo $initial; ?></div>
                            <div>
                                <p class="review-author" style="margin:0; font-weight:700;"><?php echo $display; ?></p>
                                <p class="review-card-date"><?php echo date("M j, Y", strtotime($sr['created_at'])); ?></p>
                            </div>
                        </div>
                        <p class="review-stars">
                            <?php echo str_repeat('★', $sr['rating']) . str_repeat('☆', 5 - $sr['rating']); ?>
                        </p>
                        <p class="review-text">"<?php echo htmlspecialchars($sr['comment']); ?>"</p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Fallback hardcoded reviews shown while no user reviews exist -->

        <?php endif; ?>
    </section>

    <?php include 'login-modal.php'; ?>
    <?php include 'footer.php'; ?>

    <div id="atc-toast"></div>
    <script src="javascript.js"></script>

</body>

</html>