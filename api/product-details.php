<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

$sql = "SELECT products.*, categories.category_name,
               pc.category_name AS parent_cat_name
        FROM products
        JOIN categories ON products.category_id = categories.category_id
        LEFT JOIN categories pc ON categories.parent_category_id = pc.category_id
        WHERE products.product_id = $id
          AND products.is_visible = 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 0) {
    header("Location: index.php");
    exit;
}

$product = mysqli_fetch_assoc($result);

$is_out_of_stock  = $product['stock'] <= 0 || $product['status'] === 'Out of Stock';
$discount_percent = !empty($product['discount_percent']) ? intval($product['discount_percent']) : 0;
$disc_price       = $discount_percent > 0 ? $product['price'] * (1 - $discount_percent / 100) : null;

// Wishlist check
$is_wishlisted = false;
if (isset($_SESSION['customer_id'])) {
    $wl = mysqli_query($conn, "SELECT 1 FROM wishlist WHERE user_id=" . intval($_SESSION['customer_id']) . " AND product_id=$id");
    $is_wishlisted = mysqli_num_rows($wl) > 0;
}

// Reviews
$reviews_sql = "SELECT pr.rating, pr.comment, pr.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                       u.username
                FROM product_reviews pr
                JOIN users u ON pr.user_id = u.user_id
                WHERE pr.product_id = $id
                ORDER BY pr.created_at DESC";
$reviews_result = mysqli_query($conn, $reviews_sql);
$reviews = [];
while ($r = mysqli_fetch_assoc($reviews_result)) {
    $reviews[] = $r;
}

$avg_rating   = 0;
$review_count = count($reviews);
if ($review_count > 0) {
    $avg_rating = array_sum(array_column($reviews, 'rating')) / $review_count;
}

// Related products (same category, exclude current)
$related_sql = "SELECT p.*, c.category_name
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.category_id = {$product['category_id']}
                  AND p.product_id != $id
                  AND p.is_visible = 1
                LIMIT 4";
$related_result = mysqli_query($conn, $related_sql);
$related_products = [];
while ($rp = mysqli_fetch_assoc($related_result)) {
    $related_products[] = $rp;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap">
    <title><?php echo htmlspecialchars($product['product_name']); ?> | Tinkercom</title>
</head>
<body class="background">
    <?php include 'header.php'; ?>

    <?php if (isset($_GET['out_of_stock'])): ?>
        <div class="alert alert-error" style="margin: 10px 20px;">Sorry, this product is out of stock.</div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="pd-breadcrumb">
        <a href="index.php">Home</a>
        <?php if (!empty($product['parent_cat_name'])): ?>
            <span>›</span>
            <a href="#"><?php echo htmlspecialchars($product['parent_cat_name']); ?></a>
        <?php endif; ?>
        <span>›</span>
        <a href="#"><?php echo htmlspecialchars($product['category_name']); ?></a>
        <span>›</span>
        <?php echo htmlspecialchars($product['product_name']); ?>
    </div>

    <section class="product-detail">
        <div class="details-container">

            <!-- LEFT: Image -->
            <div class="pd-image-panel">
                <div class="pd-main-image-wrap">
                    <?php if ($discount_percent > 0): ?>
                        <div class="pd-img-badge">
                            <span class="pd-discount-badge">-<?php echo $discount_percent; ?>% OFF</span>
                        </div>
                    <?php endif; ?>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                         id="pd-main-img">
                </div>
            </div>

            <!-- RIGHT: Info -->
            <div class="pd-info-panel">

                <div class="pd-category-tag"><?php echo htmlspecialchars($product['category_name']); ?></div>
                <h1 class="pd-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>

                <!-- Stars -->
                <div class="pd-stars-row">
                    <div class="pd-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="pd-star <?php echo $i <= round($avg_rating) ? '' : 'empty'; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <?php if ($review_count > 0): ?>
                        <button class="pd-review-link" onclick="openTab(event,'reviews'); document.getElementById('reviews').scrollIntoView({behavior:'smooth'});">
                            <?php echo number_format($avg_rating, 1); ?> (<?php echo $review_count; ?> review<?php echo $review_count !== 1 ? 's' : ''; ?>)
                        </button>
                    <?php else: ?>
                        <button class="pd-no-reviews-cta" onclick="openTab(event,'reviews'); document.getElementById('reviews').scrollIntoView({behavior:'smooth'});">
                            Be the first to review
                        </button>
                    <?php endif; ?>
                </div>

                <div class="pd-brand">Brand: <strong><?php echo htmlspecialchars($product['brand']); ?></strong></div>

                <div class="pd-divider"></div>

                <!-- Price -->
                <div class="pd-price-block">
                    <span class="pd-price-main">
                        ₱<?php echo number_format($disc_price ?? $product['price'], 2); ?>
                    </span>
                    <?php if ($disc_price !== null): ?>
                        <span class="pd-price-original">₱<?php echo number_format($product['price'], 2); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($disc_price !== null): ?>
                    <div class="pd-savings-note">
                        You save ₱<?php echo number_format($product['price'] - $disc_price, 2); ?>!
                    </div>
                <?php endif; ?>

                <!-- Status -->
                <div class="pd-status-row">
                    <div class="pd-status-dot <?php echo $is_out_of_stock ? 'oos' : ''; ?>"></div>
                    <span class="pd-status-label <?php echo $is_out_of_stock ? 'oos' : ''; ?>">
                        <?php echo $is_out_of_stock ? 'Out of Stock' : 'In Stock'; ?>
                    </span>
                    <?php if (!$is_out_of_stock && $product['stock'] <= 5): ?>
                        <span class="pd-stock-note">⚠ Only <?php echo $product['stock']; ?> left!</span>
                    <?php endif; ?>
                </div>

                <!-- Meta chips: warranty, category -->
                <div class="pd-meta-chips">
                    <?php if (!empty($product['warranty'])): ?>
                        <span class="pd-chip"><span class="pd-chip-icon"></span> <?php echo htmlspecialchars($product['warranty']); ?> Warranty</span>
                    <?php endif; ?>
                    <?php if (!empty($product['weight_kg']) && $product['weight_kg'] > 0): ?>
                        <span class="pd-chip"><span class="pd-chip-icon">📦</span> <?php echo $product['weight_kg']; ?> kg</span>
                    <?php endif; ?>
                    <span class="pd-chip"><span class="pd-chip-icon">✅</span> Genuine Product</span>
                </div>

                <?php if ($is_out_of_stock): ?>
                    <div class="pd-oos-msg">⚠ This product is currently out of stock. Check back soon!</div>
                <?php endif; ?>

                <div class="pd-divider"></div>

                <!-- Qty + Actions grouped together -->
                <div class="pd-qty-actions">
                    <span class="pd-qty-label">Qty:</span>
                    <div class="pd-qty-controls">
                        <button type="button" onclick="changeQty(-1)">−</button>
                        <span id="qty">1</span>
                        <button type="button" onclick="changeQty(1)">+</button>
                    </div>
                </div>

                <div class="pd-action-row">
                    <?php if ($is_out_of_stock): ?>
                        <button class="pd-btn-buy" disabled>Buy Now</button>
                        <form action="add-to-cart.php" method="POST" style="flex:1;display:flex;" id="atc-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <input type="hidden" name="quantity" id="atc-qty" value="1">
                            <button type="submit" class="pd-btn-cart" style="width:100%;">Add to Cart</button>
                        </form>
                    <?php elseif (!isset($_SESSION['customer_id'])): ?>
                        <button type="button" class="pd-btn-buy" onclick="openLoginModal()">Buy Now</button>
                        <button type="button" class="pd-btn-cart" onclick="openLoginModal()">Add to Cart</button>
                    <?php else: ?>
                        <form action="checkout.php" method="GET" style="flex:1;display:flex;">
                            <input type="hidden" name="buynow" value="1">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <input type="hidden" name="qty" id="buynow-qty" value="1">
                            <button type="submit" class="pd-btn-buy" style="width:100%;">Buy Now</button>
                        </form>
                        <form action="add-to-cart.php" method="POST" style="flex:1;display:flex;" id="atc-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <input type="hidden" name="quantity" id="atc-qty" value="1">
                            <button type="submit" class="pd-btn-cart" style="width:100%;">Add to Cart</button>
                        </form>
                    <?php endif; ?>

                    <!-- Wishlist button beside the actions -->
                    <a class="pd-btn-wishlist"
                       href="<?php echo isset($_SESSION['customer_id']) ? 'add-to-wishlist.php?id=' . $product['product_id'] : '#'; ?>"
                       <?php if (!isset($_SESSION['customer_id'])): ?>onclick="openLoginModal()"<?php endif; ?>>
                        <img src="<?php echo $is_wishlisted ? 'assets/heart-filled.png' : 'assets/love.png'; ?>" alt="Wishlist">
                    </a>
                </div>

            </div>
        </div>
    </section>

    <!-- Description / Reviews tabs -->
    <section class="product-description">
        <div class="pd-header">
            <button class="pd-header-btn active" onclick="openTab(event, 'description')">Product Description</button>
            <button class="pd-header-btn" onclick="openTab(event, 'reviews')">
                Reviews
                <?php if ($review_count > 0): ?>
                    <span style="background:#0049af;color:#fff;border-radius:12px;padding:1px 7px;font-size:12px;margin-left:4px;">
                        <?php echo $review_count; ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>

        <div class="pd-container">
            <!-- Description Tab -->
            <div class="tab-panel" id="description" style="display:block;">
                <div class="product-info">
                    <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    <div class="features">
                        <h4>Key Features</h4>
                        <?php if (!empty($product['features'])): ?>
                            <?php foreach (explode("|", $product['features']) as $line):
                                $parts = explode(":", $line, 2); ?>
                                <?php if (count($parts) === 2): ?>
                                    <p><strong><?php echo trim(htmlspecialchars($parts[0])); ?>:</strong> <?php echo trim(htmlspecialchars($parts[1])); ?></p>
                                <?php else: ?>
                                    <p><?php echo trim(htmlspecialchars($line)); ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No features listed.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Reviews Tab -->
            <div class="tab-panel" id="reviews" style="display:none;">
                <div class="pd-review">
                    <h2 class="review-title">Customer Reviews</h2>
                    <?php if ($review_count > 0): ?>
                        <div class="review-summary-bar">
                            <div class="review-summary-score">
                                <span class="review-big-score"><?php echo number_format($avg_rating, 1); ?></span>
                                <div class="review-summary-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="<?php echo $i <= round($avg_rating) ? 'rs-star active' : 'rs-star'; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="review-summary-count"><?php echo $review_count; ?> review<?php echo $review_count !== 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="review-breakdown">
                                <?php for ($i = 5; $i >= 1; $i--):
                                    $count_i = count(array_filter($reviews, fn($r) => $r['rating'] == $i));
                                    $pct = $review_count > 0 ? ($count_i / $review_count * 100) : 0; ?>
                                    <div class="review-bar-row">
                                        <span class="review-bar-label"><?php echo $i; ?> ★</span>
                                        <div class="review-bar-track">
                                            <div class="review-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                                        </div>
                                        <span class="review-bar-count"><?php echo $count_i; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="review-list">
                            <?php foreach ($reviews as $rv):
                                $display_name = trim($rv['full_name']) !== '' ? htmlspecialchars(trim($rv['full_name'])) : htmlspecialchars($rv['username']);
                                $initials = strtoupper(substr($display_name, 0, 1)); ?>
                                <div class="review-item">
                                    <div class="review-item-header">
                                        <div class="review-avatar"><?php echo $initials; ?></div>
                                        <div class="review-item-meta">
                                            <span class="review-item-author"><?php echo $display_name; ?></span>
                                            <span class="review-item-date"><?php echo date("F j, Y", strtotime($rv['created_at'])); ?></span>
                                        </div>
                                        <div class="review-item-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="<?php echo $i <= $rv['rating'] ? 'rs-star active' : 'rs-star'; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($rv['comment'])): ?>
                                        <p class="review-item-comment"><?php echo htmlspecialchars($rv['comment']); ?></p>
                                    <?php else: ?>
                                        <p class="review-item-comment" style="color:#aaa;font-style:italic;">No comment left.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#aaa;font-size:14px;margin-top:12px;">
                            No reviews yet. Be the first to review this product after purchasing!
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
    <section class="pd-related">
        <h2>You May Also Like</h2>
        <div class="pd-related-grid">
            <?php foreach ($related_products as $rp):
                $rp_oos      = $rp['stock'] <= 0 || $rp['status'] === 'Out of Stock';
                $rp_disc_pct = !empty($rp['discount_percent']) ? intval($rp['discount_percent']) : 0;
                $rp_disc     = $rp_disc_pct > 0 ? $rp['price'] * (1 - $rp_disc_pct / 100) : null;
            ?>
            <div class="product-card">
                <?php if ($rp_oos): ?>
                    <span class="product-badge badge-oos">Out of Stock</span>
                <?php elseif ($rp_disc_pct > 0): ?>
                    <span class="product-badge badge-sale">-<?php echo $rp_disc_pct; ?>% OFF</span>
                <?php elseif ($rp['is_featured']): ?>
                    <span class="product-badge badge-hot">⭐ Top Seller</span>
                <?php endif; ?>
                <div class="product-card-img">
                    <a href="product-details.php?id=<?php echo $rp['product_id']; ?>">
                        <img src="<?php echo htmlspecialchars($rp['image']); ?>" alt="<?php echo htmlspecialchars($rp['product_name']); ?>">
                    </a>
                </div>
                <div class="product-card-body">
                    <span class="product-card-cat"><?php echo htmlspecialchars($rp['category_name']); ?></span>
                    <h3 class="product-card-name"><?php echo htmlspecialchars($rp['product_name']); ?></h3>
                    <p class="product-card-brand"><?php echo htmlspecialchars($rp['brand']); ?></p>
                    <?php if ($rp_disc !== null): ?>
                        <p class="product-card-price">
                            <span class="price-sale">₱<?php echo number_format($rp_disc, 2); ?></span>
                            <span class="price-original">₱<?php echo number_format($rp['price'], 2); ?></span>
                        </p>
                    <?php else: ?>
                        <p class="product-card-price">₱<?php echo number_format($rp['price'], 2); ?></p>
                    <?php endif; ?>
                </div>
                <div class="product-card-actions">
                    <a class="btn-view" href="product-details.php?id=<?php echo $rp['product_id']; ?>">View</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'login-modal.php'; ?>
    <script src="javascript.js"></script>
    <div id="atc-toast">Added to cart!</div>

    <script>
        // Sync qty to both forms
        function changeQty(delta) {
            const el = document.getElementById('qty');
            let val = parseInt(el.textContent) + delta;
            if (val < 1) val = 1;
            el.textContent = val;
            const atcQty    = document.getElementById('atc-qty');
            const buynowQty = document.getElementById('buynow-qty');
            if (atcQty)    atcQty.value    = val;
            if (buynowQty) buynowQty.value = val;
        }

        // Tab switching
        function openTab(event, tabId) {
            document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
            document.querySelectorAll('.pd-header-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            if (event && event.currentTarget) event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>