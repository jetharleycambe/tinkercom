<?php
/**
 * REFACTORED search.php
 * FILE: search.php — REPLACE your existing search.php entirely
 *
 * Same layout as printers.php / accessories.php / peripherals.php:
 *  - Left sidebar: category filter, brand filter, price range
 *  - Main area:    sort bar, active filter tags, product-card grid
 */
include 'db.php';  
session_start();

// ── Read URL params ──────────────────────────────────────────
$q            = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$brand_filter = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$cat_filter   = isset($_GET['cat'])   ? intval($_GET['cat']) : 0;
$min_price    = isset($_GET['min']) && $_GET['min'] !== '' ? max(0, floatval($_GET['min'])) : 0;
$max_price    = isset($_GET['max']) && $_GET['max'] !== '' ? max(0, floatval($_GET['max'])) : 999999;
$sort         = isset($_GET['sort'])  ? $_GET['sort']        : 'name_asc';

// Redirect to homepage if empty query
if ($q === '') {
    header('Location: index.php');
    exit;
}

// ── Sort clause ──────────────────────────────────────────────
$sort_map = [
    'name_asc'   => 'p.product_name ASC',
    'name_desc'  => 'p.product_name DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
];
$sort_clause = $sort_map[$sort] ?? 'p.product_name ASC';

// ── Escape search term ───────────────────────────────────────
$q_escaped   = mysqli_real_escape_string($conn, $q);
$search_like = "%$q_escaped%";

// ── Build WHERE filters ──────────────────────────────────────
$brand_sql = $brand_filter
    ? "AND TRIM(UPPER(p.brand)) = '" . strtoupper(trim(mysqli_real_escape_string($conn, $brand_filter))) . "'"
    : '';
$cat_sql   = $cat_filter ? "AND p.category_id = $cat_filter" : '';
$price_sql = "AND p.price BETWEEN $min_price AND $max_price";

// ── Main product query ───────────────────────────────────────
$sql = "SELECT p.*, c.category_name,
               pc.category_name AS parent_cat_name
        FROM products p
        JOIN categories c  ON p.category_id          = c.category_id
        LEFT JOIN categories pc ON c.parent_category_id = pc.category_id
        WHERE p.is_visible = 1
          AND (
              p.product_name LIKE '$search_like' OR
              p.brand        LIKE '$search_like' OR
              c.category_name LIKE '$search_like'
          )
          $brand_sql
          $cat_sql
          $price_sql
        ORDER BY $sort_clause";

$result = mysqli_query($conn, $sql);
$total  = mysqli_num_rows($result);

// ── Sidebar data: categories matching this search ────────────
$cats_sql = mysqli_query($conn,
    "SELECT DISTINCT c.category_id, c.category_name,
            pc.category_name AS parent_name
     FROM products p
     JOIN categories c  ON p.category_id = c.category_id
     LEFT JOIN categories pc ON c.parent_category_id = pc.category_id
     WHERE p.is_visible = 1
       AND (
           p.product_name  LIKE '$search_like' OR
           p.brand         LIKE '$search_like' OR
           c.category_name LIKE '$search_like'
       )
     ORDER BY c.category_name ASC"
);
$categories = [];
while ($cat = mysqli_fetch_assoc($cats_sql)) {
    $categories[] = $cat;
}

// ── Sidebar data: brands matching this search ─────────────────
$brands_sql = mysqli_query($conn,
    "SELECT DISTINCT TRIM(p.brand) AS brand
     FROM products p
     JOIN categories c ON p.category_id = c.category_id
     WHERE p.is_visible = 1
       AND (
           p.product_name  LIKE '$search_like' OR
           p.brand         LIKE '$search_like' OR
           c.category_name LIKE '$search_like'
       )
     ORDER BY brand ASC"
);
$brands = [];
while ($b = mysqli_fetch_assoc($brands_sql)) {
    if ($b['brand']) $brands[] = $b['brand'];
}

// ── Helper: preserve all current filters in a URL ────────────
function search_url($overrides = []) {
    global $q, $brand_filter, $cat_filter, $min_price, $max_price, $sort;
    $params = [
        'q'     => $q,
        'sort'  => $sort,
    ];
    if ($brand_filter)       $params['brand'] = $brand_filter;
    if ($cat_filter)         $params['cat']   = $cat_filter;
    if ($min_price > 0)      $params['min']   = $min_price;
    if ($max_price < 999999) $params['max']   = $max_price;
    // Apply overrides
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return 'search.php?' . http_build_query($params);
}

$active_nav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search: <?php echo htmlspecialchars($q); ?> | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
</head>
<body class="background">

<?php include 'header.php'; ?>

<div class="listing-page">

    <!-- ── LEFT SIDEBAR ───────────────────────────────────── -->
    <aside class="listing-sidebar">

        <!-- Category filter -->
        <?php if (!empty($categories)): ?>
        <div class="sidebar-section">
            <h3 class="sidebar-title">Category</h3>
            <ul class="sidebar-links">
                <li>
                    <a href="<?php echo search_url(['cat' => null]); ?>"
                       class="<?php echo $cat_filter === 0 ? 'sidebar-active' : ''; ?>">
                        All Categories
                    </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                <li>
                    <a href="<?php echo search_url(['cat' => $cat['category_id']]); ?>"
                       class="<?php echo $cat_filter === $cat['category_id'] ? 'sidebar-active' : ''; ?>">
                        <?php if ($cat['parent_name']): ?>
                            <small style="color:#aaa;"><?php echo htmlspecialchars($cat['parent_name']); ?> › </small>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Brand filter -->
        <?php if (!empty($brands)): ?>
        <div class="sidebar-section">
            <h3 class="sidebar-title">Brand</h3>
            <ul class="sidebar-links">
                <li>
                    <a href="<?php echo search_url(['brand' => null]); ?>"
                       class="<?php echo $brand_filter === '' ? 'sidebar-active' : ''; ?>">
                        All Brands
                    </a>
                </li>
                <?php foreach ($brands as $brand): ?>
                <li>
                    <a href="<?php echo search_url(['brand' => $brand]); ?>"
                       class="<?php echo strtolower($brand_filter) === strtolower($brand) ? 'sidebar-active' : ''; ?>">
                        <?php echo htmlspecialchars($brand); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Price range -->
        <div class="sidebar-section">
            <h3 class="sidebar-title">Price Range</h3>
            <form method="GET" action="search.php" class="price-range-form">
                <input type="hidden" name="q"     value="<?php echo htmlspecialchars($q); ?>">
                <input type="hidden" name="sort"  value="<?php echo htmlspecialchars($sort); ?>">
                <?php if ($brand_filter): ?>
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>">
                <?php endif; ?>
                <?php if ($cat_filter): ?>
                    <input type="hidden" name="cat" value="<?php echo $cat_filter; ?>">
                <?php endif; ?>
                <div class="price-inputs">
                    <input type="number" name="min" placeholder="Min ₱"
                           value="<?php echo $min_price ?: ''; ?>" min="0">
                    <span>—</span>
                    <input type="number" name="max" placeholder="Max ₱"
                           value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" min="0">
                </div>
                <button type="submit" class="sidebar-apply-btn">Apply</button>
                <?php if ($min_price > 0 || $max_price < 999999): ?>
                    <a href="<?php echo search_url(['min' => null, 'max' => null]); ?>"
                       class="sidebar-clear-link">Clear price</a>
                <?php endif; ?>
            </form>
        </div>

    </aside>

    <!-- ── MAIN CONTENT ───────────────────────────────────── -->
    <main class="listing-main">

        <!-- Breadcrumb -->
        <div class="listing-breadcrumb">
            <a href="index.php">Home</a> › Search results
        </div>

        <!-- Top bar: heading + sort -->
        <div class="listing-topbar">
            <h1 class="listing-title">
                Results for "<em><?php echo htmlspecialchars($q); ?></em>"
                <span class="listing-count">
                    (<?php echo $total; ?> product<?php echo $total !== 1 ? 's' : ''; ?>)
                </span>
            </h1>

            <form method="GET" action="search.php" class="sort-form">
                <input type="hidden" name="q"     value="<?php echo htmlspecialchars($q); ?>">
                <?php if ($brand_filter): ?>
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>">
                <?php endif; ?>
                <?php if ($cat_filter): ?>
                    <input type="hidden" name="cat"   value="<?php echo $cat_filter; ?>">
                <?php endif; ?>
                <?php if ($min_price > 0): ?>
                    <input type="hidden" name="min"   value="<?php echo $min_price; ?>">
                <?php endif; ?>
                <?php if ($max_price < 999999): ?>
                    <input type="hidden" name="max"   value="<?php echo $max_price; ?>">
                <?php endif; ?>
                <label>Sort by:</label>
                <select name="sort" onchange="this.form.submit()">
                    <option value="name_asc"   <?php echo $sort === 'name_asc'   ? 'selected' : ''; ?>>Name A–Z</option>
                    <option value="name_desc"  <?php echo $sort === 'name_desc'  ? 'selected' : ''; ?>>Name Z–A</option>
                    <option value="price_asc"  <?php echo $sort === 'price_asc'  ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </form>
        </div>

        <!-- Active filter tags -->
        <?php if ($brand_filter || $cat_filter || $min_price > 0 || $max_price < 999999): ?>
        <div class="active-filters">
            <span>Filters:</span>

            <?php if ($brand_filter): ?>
                <span class="filter-tag">
                    Brand: <?php echo htmlspecialchars($brand_filter); ?>
                    <a href="<?php echo search_url(['brand' => null]); ?>">✕</a>
                </span>
            <?php endif; ?>

            <?php if ($cat_filter): ?>
                <?php
                $active_cat_name = '';
                foreach ($categories as $c) {
                    if ($c['category_id'] === $cat_filter) {
                        $active_cat_name = $c['category_name'];
                        break;
                    }
                }
                ?>
                <span class="filter-tag">
                    Category: <?php echo htmlspecialchars($active_cat_name); ?>
                    <a href="<?php echo search_url(['cat' => null]); ?>">✕</a>
                </span>
            <?php endif; ?>

            <?php if ($min_price > 0 || $max_price < 999999): ?>
                <span class="filter-tag">
                    Price: ₱<?php echo number_format($min_price); ?> –
                    <?php echo $max_price < 999999 ? '₱' . number_format($max_price) : 'Any'; ?>
                    <a href="<?php echo search_url(['min' => null, 'max' => null]); ?>">✕</a>
                </span>
            <?php endif; ?>

            <a href="search.php?q=<?php echo urlencode($q); ?>"
               style="font-size:12px;color:#888;margin-left:4px;">
                Clear all
            </a>
        </div>
        <?php endif; ?>

        <!-- Product grid -->
        <?php if ($total > 0): ?>
        <div class="product-grid">
            <?php while ($product = mysqli_fetch_assoc($result)):
                $is_wishlisted = false;
                if (isset($_SESSION['customer_id'])) {
                    $wl = mysqli_query($conn,
                        "SELECT 1 FROM wishlist
                         WHERE user_id="  . intval($_SESSION['customer_id']) .
                        " AND product_id=" . intval($product['product_id']));
                    $is_wishlisted = mysqli_num_rows($wl) > 0;
                }
                $is_oos           = $product['stock'] <= 0 || $product['status'] === 'Out of Stock';
                $discount_percent = !empty($product['discount_percent']) ? intval($product['discount_percent']) : 0;
                $disc_price       = $discount_percent > 0 ? $product['price'] * (1 - $discount_percent / 100) : null;
            ?>
            <div class="product-card">
                <!-- Badge: OOS > Sale > Top Seller -->
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
                    <span class="product-card-cat">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </span>
                    <h3 class="product-card-name">
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </h3>
                    <p class="product-card-brand">
                        <?php echo htmlspecialchars($product['brand']); ?>
                    </p>
                    <?php if ($disc_price !== null): ?>
                        <p class="product-card-price">
                            <span class="price-sale">₱<?php echo number_format($disc_price, 2); ?></span>
                            <span class="price-original">₱<?php echo number_format($product['price'], 2); ?></span>
                        </p>
                    <?php else: ?>
                        <p class="product-card-price">
                            ₱<?php echo number_format($product['price'], 2); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="product-card-actions">
                    <a class="btn-view"
                       href="product-details.php?id=<?php echo $product['product_id']; ?>">
                       View
                    </a>
                    <a class="btn-wishlist"
                       href="<?php echo isset($_SESSION['customer_id'])
                           ? 'add-to-wishlist.php?id=' . $product['product_id']
                           : '#'; ?>"
                       <?php if (!isset($_SESSION['customer_id'])): ?>
                           onclick="openLoginModal()"
                       <?php endif; ?>>
                        <img src="<?php echo $is_wishlisted
                            ? 'assets/heart-filled.png'
                            : 'assets/love.png'; ?>" alt="wishlist">
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <!-- Empty state -->
        <div class="listing-empty">
            <p class="empty-title">
                No products found for "<?php echo htmlspecialchars($q); ?>"
            </p>
            <p class="empty-sub">
                Try a different keyword, or
                <?php if ($brand_filter || $cat_filter || $min_price > 0 || $max_price < 999999): ?>
                    <a href="search.php?q=<?php echo urlencode($q); ?>">clear your filters</a>.
                <?php else: ?>
                    <a href="index.php">go back to the homepage</a>.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php include 'login-modal.php'; ?>
<?php include 'footer.php'; ?>

<div id="atc-toast"></div>
<script src="javascript.js"></script>

</body>
</html>