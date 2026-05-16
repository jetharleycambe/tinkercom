<?php
include 'db.php';  
session_start();

// ── Read URL filters ─────────────────────────────────────────
$sub_id       = isset($_GET['sub'])   ? intval($_GET['sub'])   : 0;
$min_price    = isset($_GET['min'])   && $_GET['min'] !== '' ? max(0, floatval($_GET['min'])) : 0;
$max_price    = isset($_GET['max'])   && $_GET['max'] !== '' ? max(0, floatval($_GET['max'])) : 999999;
$brand_filter = isset($_GET['brand']) ? trim($_GET['brand'])   : '';
$sort         = isset($_GET['sort'])  ? $_GET['sort']          : 'name_asc';

// ── Sort ─────────────────────────────────────────────────────
$sort_map = [
    'name_asc'   => 'p.product_name ASC',
    'name_desc'  => 'p.product_name DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
];
$sort_clause = $sort_map[$sort] ?? 'p.product_name ASC';

// ── Sub-categories under Accessories (parent_id = 3) ─────────
$subs_result = mysqli_query($conn,
    "SELECT category_id, category_name FROM categories
     WHERE parent_category_id = 3 AND is_visible = 1
     ORDER BY display_order ASC"
);
$sub_categories = [];
while ($s = mysqli_fetch_assoc($subs_result)) {
    $sub_categories[] = $s;
}

// ── Build WHERE ───────────────────────────────────────────────
if ($sub_id > 0) {
    $cat_where = "p.category_id = $sub_id";
} else {
    $sub_ids_str = implode(',', array_column($sub_categories, 'category_id'));
    $cat_where   = $sub_ids_str ? "p.category_id IN ($sub_ids_str)" : "1=0";
}

$brand_sql = $brand_filter
    ? "AND TRIM(UPPER(p.brand)) = '" . strtoupper(trim(mysqli_real_escape_string($conn, $brand_filter))) . "'"
    : "";
$price_sql = "AND p.price BETWEEN $min_price AND $max_price";

// ── Products ──────────────────────────────────────────────────
$sql = "SELECT p.*, c.category_name
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE $cat_where AND p.is_visible = 1 $brand_sql $price_sql
        ORDER BY $sort_clause";
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$total  = mysqli_num_rows($result);

// ── Brands ────────────────────────────────────────────────────
$sub_ids_str_for_brands = implode(',', array_column($sub_categories, 'category_id'));
$brands_result = mysqli_query($conn,
    "SELECT DISTINCT TRIM(brand) AS brand FROM products
     WHERE category_id IN ($sub_ids_str_for_brands) AND is_visible = 1
     ORDER BY brand ASC"
);

// ── Current sub name ──────────────────────────────────────────
$current_sub_name = 'All Accessories';
if ($sub_id > 0) {
    foreach ($sub_categories as $s) {
        if ((int)$s['category_id'] === $sub_id) {
            $current_sub_name = $s['category_name'];
            break;
        }
    }
}
$discount_percent = isset($product['discount_percent']) ? intval($product['discount_percent']) : 0;
$disc_price = $discount_percent > 0 ? $product['price'] * (1 - $discount_percent / 100) : null;


$active_nav = "accessories";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_sub_name); ?> | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
</head>
<body class="background">

<?php include 'header.php'; ?>

<div class="listing-page">

    <!-- ── LEFT SIDEBAR ───────────────────────────────────── -->
    <aside class="listing-sidebar">

        <div class="sidebar-section">
            <h3 class="sidebar-title">Category</h3>
            <ul class="sidebar-links">
                <li>
                    <a href="accessories.php"
                       class="<?php echo $sub_id === 0 ? 'sidebar-active' : ''; ?>">
                        All Accessories
                    </a>
                </li>
                <?php foreach ($sub_categories as $s): ?>
                <li>
                    <a href="accessories.php?sub=<?php echo $s['category_id'];
                       echo $brand_filter ? '&brand=' . urlencode($brand_filter) : '';
                       echo $sort !== 'name_asc' ? '&sort=' . $sort : ''; ?>"
                       class="<?php echo $sub_id === intval($s['category_id']) ? 'sidebar-active' : ''; ?>">
                        <?php echo htmlspecialchars($s['category_name']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3 class="sidebar-title">Brand</h3>
            <ul class="sidebar-links">
                <li>
                    <a href="accessories.php?<?php echo $sub_id ? 'sub='.$sub_id.'&' : ''; ?>sort=<?php echo $sort; ?>"
                       class="<?php echo $brand_filter === '' ? 'sidebar-active' : ''; ?>">All Brands</a>
                </li>
                <?php while ($b = mysqli_fetch_assoc($brands_result)): ?>
                <li>
                    <a href="accessories.php?<?php echo $sub_id ? 'sub='.$sub_id.'&' : ''; ?>brand=<?php echo urlencode($b['brand']); ?>&sort=<?php echo $sort; ?>"
                       class="<?php echo strtolower($brand_filter) === strtolower($b['brand']) ? 'sidebar-active' : ''; ?>">
                        <?php echo htmlspecialchars($b['brand']); ?>
                    </a>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3 class="sidebar-title">Price Range</h3>
            <form method="GET" action="accessories.php" class="sidebar-price-form">
                <?php if ($sub_id): ?><input type="hidden" name="sub" value="<?php echo $sub_id; ?>"><?php endif; ?>
                <?php if ($brand_filter): ?><input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>"><?php endif; ?>
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <div class="price-inputs">
                    <input type="number" name="min" placeholder="Min ₱" value="<?php echo $min_price ?: ''; ?>" min="0">
                    <span>—</span>
                    <input type="number" name="max" placeholder="Max ₱" value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" min="0">
                </div>
                <button type="submit" class="sidebar-apply-btn">Apply</button>
                <?php if ($min_price > 0 || $max_price < 999999): ?>
                    <a href="accessories.php?<?php echo $sub_id ? 'sub='.$sub_id.'&' : ''; ?><?php echo $brand_filter ? 'brand='.urlencode($brand_filter).'&' : ''; ?>sort=<?php echo $sort; ?>"
                       class="sidebar-clear-link">Clear price</a>
                <?php endif; ?>
            </form>
        </div>

    </aside>

    <!-- ── MAIN CONTENT ───────────────────────────────────── -->
    <main class="listing-main">

        <div class="listing-breadcrumb">
            <a href="index.php">Home</a> ›
            <a href="accessories.php">Accessories</a>
            <?php if ($sub_id > 0): ?> › <span><?php echo htmlspecialchars($current_sub_name); ?></span><?php endif; ?>
        </div>

        <div class="listing-topbar">
            <h1 class="listing-title">
                <?php echo htmlspecialchars($current_sub_name); ?>
                <span class="listing-count">
                    (<?php echo $total; ?> product<?php echo $total !== 1 ? 's' : ''; ?>)</span>
            </h1>
            <form method="GET" action="accessories.php" class="sort-form">
                <?php if ($sub_id): ?><input type="hidden" name="sub" value="<?php echo $sub_id; ?>"><?php endif; ?>
                <?php if ($brand_filter): ?><input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>"><?php endif; ?>
                <?php if ($min_price > 0): ?><input type="hidden" name="min" value="<?php echo $min_price; ?>"><?php endif; ?>
                <?php if ($max_price < 999999): ?><input type="hidden" name="max" value="<?php echo $max_price; ?>"><?php endif; ?>
                <label>Sort by:</label>
                <select name="sort" onchange="this.form.submit()">
                    <option value="name_asc"   <?php echo $sort === 'name_asc'   ? 'selected' : ''; ?>>Name A–Z</option>
                    <option value="name_desc"  <?php echo $sort === 'name_desc'  ? 'selected' : ''; ?>>Name Z–A</option>
                    <option value="price_asc"  <?php echo $sort === 'price_asc'  ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </form>
        </div>

        <?php if ($brand_filter || $min_price > 0 || $max_price < 999999): ?>
        <div class="active-filters">
            <span>Filters:</span>
            <?php if ($brand_filter): ?>
                <span class="filter-tag">Brand: <?php echo htmlspecialchars($brand_filter); ?>
                    <a href="accessories.php?<?php echo $sub_id ? 'sub='.$sub_id.'&' : ''; ?>sort=<?php echo $sort; ?>">✕</a>
                </span>
            <?php endif; ?>
            <?php if ($min_price > 0 || $max_price < 999999): ?>
                <span class="filter-tag">Price: ₱<?php echo number_format($min_price); ?> – <?php echo $max_price < 999999 ? '₱'.number_format($max_price) : 'Any'; ?>
                    <a href="accessories.php?<?php echo $sub_id ? 'sub='.$sub_id.'&' : ''; ?><?php echo $brand_filter ? 'brand='.urlencode($brand_filter).'&' : ''; ?>sort=<?php echo $sort; ?>">✕</a>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($total > 0): ?>
        <div class="product-grid">
            <?php while ($product = mysqli_fetch_assoc($result)):
                $is_wishlisted = false;
                if (isset($_SESSION['customer_id'])) {
                    $wl = mysqli_query($conn, "SELECT 1 FROM wishlist WHERE user_id=" . intval($_SESSION['customer_id']) . " AND product_id=" . intval($product['product_id']));
                    $is_wishlisted = mysqli_num_rows($wl) > 0;
                }
                $is_oos = $product['stock'] <= 0 || $product['status'] === 'Out of Stock';
                $disc_price = null;
                $discount = 0;
                if (!empty($product['discount_percent']) && $product['discount_percent'] > 0) {
                    $discount = intval($product['discount_percent']);
                    $disc_price = $product['price'] * (1 - $discount / 100);
                }
            ?>
            <div class="product-card">
                <?php if ($is_oos): ?>
                    <span class="product-badge badge-oos">Out of Stock</span>
                <?php elseif ($discount > 0): ?>
                    <span class="product-badge badge-sale">-<?php echo $discount; ?>% OFF</span>
                <?php elseif ($product['is_featured']): ?>
                    <span class="product-badge badge-hot">⭐ Top Seller</span>
                <?php endif; ?>
                <div class="product-card-img">
                    <a href="product-details.php?id=<?php echo $product['product_id']; ?>">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    </a>
                </div>
                <div class="product-card-body">
                    <span class="product-card-cat"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <h3 class="product-card-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <p class="product-card-brand"><?php echo htmlspecialchars($product['brand']); ?></p>
                    <p class="product-card-price">₱<?php echo number_format($product['price'], 2); ?></p>
                </div>
                <div class="product-card-actions">
                    <a class="btn-view" href="product-details.php?id=<?php echo $product['product_id']; ?>">View</a>
                    <a class="btn-wishlist"
                       href="<?php echo isset($_SESSION['customer_id']) ? 'add-to-wishlist.php?id=' . $product['product_id'] : '#'; ?>"
                       <?php if (!isset($_SESSION['customer_id'])): ?>onclick="openLoginModal()"<?php endif; ?>>
                        <img src="<?php echo $is_wishlisted ? 'assets/heart-filled.png' : 'assets/love.png'; ?>" alt="wishlist">
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="listing-empty">
            <p class="empty-title">No products found</p>
            <p class="empty-sub">Try adjusting your filters or <a href="accessories.php">clear all filters</a>.</p>
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