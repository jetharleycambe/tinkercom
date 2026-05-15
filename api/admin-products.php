<?php
session_start();
include 'db.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

// ── Filters ───────────────────────────────────────────────────
$filter_cat    = isset($_GET['cat'])    ? intval($_GET['cat'])                  : 0;
$filter_status = isset($_GET['status']) ? $_GET['status']                       : '';
$filter_brand  = isset($_GET['brand'])  ? trim($_GET['brand'])                  : '';
$filter_sort   = isset($_GET['sort'])   ? $_GET['sort']                         : 'newest';
$filter_min    = isset($_GET['min'])    && $_GET['min'] !== '' ? floatval($_GET['min']) : '';
$filter_max    = isset($_GET['max'])    && $_GET['max'] !== '' ? floatval($_GET['max']) : '';


$sort_map = [
    'newest'     => 'p.product_id DESC',
    'oldest'     => 'p.product_id ASC',
    'name_asc'   => 'p.product_name ASC',
    'name_desc'  => 'p.product_name DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'stock_asc'  => 'p.stock ASC',
    'stock_desc' => 'p.stock DESC',
];
$sort_clause = $sort_map[$filter_sort] ?? 'p.product_id DESC';

$where = "WHERE 1=1";
if ($filter_cat > 0) $where .= " AND (p.category_id = $filter_cat OR c.parent_category_id = $filter_cat)";
if ($filter_status !== '') $where .= " AND p.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
if ($filter_brand !== '')  $where .= " AND p.brand = '"  . mysqli_real_escape_string($conn, $filter_brand)  . "'";
if ($filter_min !== '')    $where .= " AND p.price >= $filter_min";
if ($filter_max !== '')    $where .= " AND p.price <= $filter_max";

$products = mysqli_query($conn,
    "SELECT p.*, c.category_name, c2.category_name AS parent_cat_name
     FROM products p
     LEFT JOIN categories c  ON p.category_id = c.category_id
     LEFT JOIN categories c2 ON c.parent_category_id = c2.category_id
     $where
     ORDER BY $sort_clause"
);

// Parent categories for tabs
$parent_cats = mysqli_query($conn,
    "SELECT c.category_id, c.category_name,
            COUNT(p.product_id) AS product_count
     FROM categories c
     LEFT JOIN products p
           ON p.category_id IN (
               SELECT category_id FROM categories
               WHERE category_id = c.category_id
                  OR parent_category_id = c.category_id
           )
     WHERE c.parent_category_id IS NULL AND c.is_visible = 1
     GROUP BY c.category_id ORDER BY c.display_order ASC"
);
$parent_cats_arr = [];
$total_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products"))['c'];
while ($r = mysqli_fetch_assoc($parent_cats)) $parent_cats_arr[] = $r;

// Brands for filter dropdown
$brands_result = mysqli_query($conn, "SELECT DISTINCT brand FROM products ORDER BY brand ASC");

$cats_for_modal = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error   = isset($_GET['error'])   ? $_GET['error']   : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <div>
                <h1>Products</h1>
                <p class="topbar-sub">Manage your product catalog</p>
            </div>
            <button class="btn-primary" id="openAddModal">+ Add Product</button>
        </div>

        <?php if ($success === 'added'): ?>
            <div class="alert alert-success">Product added successfully.</div>
        <?php elseif ($success === 'edited'): ?>
            <div class="alert alert-success">Product updated successfully.</div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">Product deleted successfully.</div>
        <?php elseif ($error === 'upload'): ?>
            <div class="alert alert-error">Image upload failed. Please try again.</div>
        <?php elseif ($error === 'featured_limit'): ?>
            <div class="alert alert-error">Cannot feature this product. Maximum of 5 featured products allowed. Please unfeature another product first.</div>
        <?php elseif ($success === 'error' || isset($_GET['error'])): ?>
            <?php if ($_GET['error'] === 'invalid_image'): ?>
                <div class="alert alert-error">Invalid image type. Only JPG, PNG, and WEBP are allowed.</div>
            <?php elseif ($_GET['error'] === 'image_too_large'): ?>
                <div class="alert alert-error">Image is too large. Maximum size is 5MB.</div>
            <?php endif; ?>
        <?php endif; ?>

        
            <!-- Category Tabs -->
<div class="appt-tabs">
    <a href="admin-products.php<?php echo http_build_query(array_filter(['status'=>$filter_status,'brand'=>$filter_brand,'sort'=>$filter_sort,'min'=>$filter_min,'max'=>$filter_max])) ? '?'.http_build_query(array_filter(['status'=>$filter_status,'brand'=>$filter_brand,'sort'=>$filter_sort,'min'=>$filter_min,'max'=>$filter_max])) : ''; ?>"
       class="appt-tab <?php echo $filter_cat === 0 ? 'active' : ''; ?>">
        All <span class="tab-count"><?php echo $total_all; ?></span>
    </a>
    <?php foreach ($parent_cats_arr as $pc):
        $params = array_filter(['cat'=>$pc['category_id'],'status'=>$filter_status,'brand'=>$filter_brand,'sort'=>$filter_sort,'min'=>$filter_min,'max'=>$filter_max]);
    ?>
    <a href="admin-products.php?<?php echo http_build_query($params); ?>"
       class="appt-tab <?php echo $filter_cat === intval($pc['category_id']) ? 'active' : ''; ?>">
        <?php echo htmlspecialchars($pc['category_name']); ?>
        <span class="tab-count"><?php echo $pc['product_count']; ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="admin-table-section" style="border-radius:0 8px 8px 8px;">
    <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
        <h2>
            <?php
            if ($filter_cat > 0) {
                foreach ($parent_cats_arr as $pc) {
                    if (intval($pc['category_id']) === $filter_cat) { echo htmlspecialchars($pc['category_name']); break; }
                }
            } else {
                echo "All Products";
            }
            echo " (" . mysqli_num_rows($products) . ")";
            ?>
        </h2>
        <!-- Filter Bar -->
        <form method="GET" action="admin-products.php" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <?php if ($filter_cat > 0): ?><input type="hidden" name="cat" value="<?php echo $filter_cat; ?>"><?php endif; ?>
            <select name="status" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                <option value="">All Status</option>
                <option value="In Stock"     <?php echo $filter_status === 'In Stock'     ? 'selected' : ''; ?>>In Stock</option>
                <option value="Out of Stock" <?php echo $filter_status === 'Out of Stock' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
            <select name="brand" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                <option value="">All Brands</option>
                <?php mysqli_data_seek($brands_result, 0); while ($b = mysqli_fetch_assoc($brands_result)): ?>
                    <option value="<?php echo htmlspecialchars($b['brand']); ?>" <?php echo $filter_brand === $b['brand'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['brand']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <input type="number" name="min" placeholder="Min ₱" value="<?php echo htmlspecialchars($filter_min); ?>" style="width:80px; padding:6px 8px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
            <input type="number" name="max" placeholder="Max ₱" value="<?php echo htmlspecialchars($filter_max); ?>" style="width:80px; padding:6px 8px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
            <select name="sort" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                <option value="newest"     <?php echo $filter_sort==='newest'     ? 'selected':''; ?>>Newest First</option>
                <option value="oldest"     <?php echo $filter_sort==='oldest'     ? 'selected':''; ?>>Oldest First</option>
                <option value="name_asc"   <?php echo $filter_sort==='name_asc'   ? 'selected':''; ?>>Name A–Z</option>
                <option value="name_desc"  <?php echo $filter_sort==='name_desc'  ? 'selected':''; ?>>Name Z–A</option>
                <option value="price_asc"  <?php echo $filter_sort==='price_asc'  ? 'selected':''; ?>>Price Low–High</option>
                <option value="price_desc" <?php echo $filter_sort==='price_desc' ? 'selected':''; ?>>Price High–Low</option>
                <option value="stock_asc"  <?php echo $filter_sort==='stock_asc'  ? 'selected':''; ?>>Stock Low–High</option>
                <option value="stock_desc" <?php echo $filter_sort==='stock_desc' ? 'selected':''; ?>>Stock High–Low</option>
            </select>
            <?php if ($filter_status || $filter_brand || $filter_min !== '' || $filter_max !== ''): ?>
                <a href="admin-products.php<?php echo $filter_cat ? '?cat='.$filter_cat : ''; ?>" style="font-size:12px; color:#e53935;">Clear filters</a>
            <?php endif; ?>
            <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
        </form>
    </div>

    <table class="admin-table" id="productsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Discount</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Visible</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        mysqli_data_seek($products, 0);
        while ($row = mysqli_fetch_assoc($products)): ?>
            <tr>
                <td>#<?php echo $row['product_id']; ?></td>
                <td><img src="<?php echo $row['image']; ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;"></td>
                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                <td><?php echo htmlspecialchars($row['parent_cat_name'] ?? $row['category_name'] ?? '—'); ?></td>
                <td>₱<?php echo number_format($row['price'], 2); ?></td>
                <td>
                    <?php if (!empty($row['discount_percent']) && $row['discount_percent'] > 0): ?>
                        <span class="status-badge" style="background:#fde8e8;color:#c62828;font-weight:700;">
                            <?php echo $row['discount_percent']; ?>% OFF
                        </span>
                        <small style="display:block;color:#555;margin-top:2px;">
                            → ₱<?php echo number_format($row['price'] * (1 - $row['discount_percent']/100), 2); ?>
                        </small>
                    <?php else: ?>
                        <span style="color:#aaa;">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['stock']; ?></td>
                <td><span class="status-badge <?php echo $row['status']==='In Stock' ? 'confirmed' : 'cancelled'; ?>"><?php echo $row['status']; ?></span></td>
                <td><span class="status-badge <?php echo $row['is_featured'] ? 'completed' : ''; ?>"><?php echo $row['is_featured'] ? 'Yes' : 'No'; ?></span></td>
                <td><span class="status-badge <?php echo $row['is_visible'] ? 'completed' : 'cancelled'; ?>"><?php echo $row['is_visible'] ? 'Visible' : 'Hidden'; ?></span></td>
                <td>
                    <button class="btn-edit" onclick="openEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                    <button class="btn-delete" onclick="confirmDelete(<?php echo $row['product_id']; ?>, '<?php echo addslashes($row['product_name']); ?>')">Delete</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ADD PRODUCT MODAL -->
<div class="modal" id="addModal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h2>Add New Product</h2>
            <button type="button" class="modal-close" id="closeAddModal">&times;</button>
        </div>
        <form action="admin-product-save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-grid">
                <div class="modal-field">
                    <label>Product Name</label>
                    <input type="text" name="product_name" placeholder="e.g. Epson L121" required>
                </div>
                <div class="modal-field">
                    <label>Brand</label>
                    <input type="text" name="brand" placeholder="e.g. Epson" required>
                </div>
                <div class="modal-field">
                    <label>Category</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php while ($cat = mysqli_fetch_assoc($cats_for_modal)): ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo $cat['category_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Price (₱)</label>
                    <input type="number" name="price" placeholder="e.g. 5300" step="0.01" required>
                </div>
                <div class="modal-field">
                    <label>Discount % <span class="field-hint">(0 = no discount)</span></label>
                    <input type="number" name="discount_percent" placeholder="e.g. 10" min="0" max="100" value="0">
                </div>
                <div class="modal-field">
                    <label>Stock</label>
                    <input type="number" name="stock" placeholder="e.g. 10" required>
                </div>
                <div class="modal-field">
                    <label>Warranty</label>
                    <input type="text" name="warranty" placeholder="e.g. 1 Year">
                </div>
                <div class="modal-field">
                    <label>Weight (kg) <span class="field-hint">(used for shipping fee calculation)</span></label>
                    <input type="number" name="weight_kg" placeholder="e.g. 0.5" step="0.001" min="0" value="0">
                </div>
                <div class="modal-field modal-field-full">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Product description..."></textarea>
                </div>
                <div class="modal-field modal-field-full">
                    <label>Key Features <span class="field-hint">(separate each with | e.g. Print Speed: 20ppm | Color: Black)</span></label>
                    <textarea name="features" rows="3" placeholder="Feature 1: Value | Feature 2: Value"></textarea>
                </div>
                <div class="modal-field">
                    <label>Product Image</label>
                    <input type="file" name="image" id="add-image-input" accept="image/*" required onchange="previewImage(this, 'add-image-preview')">
                    <img id="add-image-preview" src="#" alt="Preview" style="display:none; margin-top:8px; max-width:100%; max-height:140px; border-radius:6px; border:1px solid #e5e7eb; object-fit:contain;">
                </div>
                <div class="modal-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="In Stock">In Stock</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Featured on Homepage <span class="field-hint">(max 5 allowed)</span></label>
                    <select name="is_featured" id="edit_is_featured">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Visible to Customers</label>
                    <select name="is_visible">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="closeAddModal2">Cancel</button>
                <button type="submit" class="btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h2>Edit Product</h2>
            <button type="button" class="modal-close" id="closeEditModal">&times;</button>
        </div>
        <form action="admin-product-save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div class="modal-grid">
                <div class="modal-field">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_product_name" required>
                </div>
                <div class="modal-field">
                    <label>Brand</label>
                    <input type="text" name="brand" id="edit_brand" required>
                </div>
                <div class="modal-field">
                    <label>Category</label>
                    <select name="category_id" id="edit_category_id" required>
                        <option value="">Select Category</option>
                        <?php
                        $cats_edit = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
                        while ($cat = mysqli_fetch_assoc($cats_edit)):
                        ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo $cat['category_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Price (₱)</label>
                    <input type="number" name="price" id="edit_price" step="0.01" required>
                </div>
                <div class="modal-field">
                    <label>Discount % <span class="field-hint">(0 = no discount)</span></label>
                    <input type="number" name="discount_percent" id="edit_discount_percent" min="0" max="100" value="0">
                </div>
                <div class="modal-field">
                    <label>Stock</label>
                    <input type="number" name="stock" id="edit_stock" required>
                </div>
                <div class="modal-field">
                    <label>Warranty</label>
                    <input type="text" name="warranty" id="edit_warranty">
                </div>
                <div class="modal-field">
                    <label>Weight (kg) <span class="field-hint">(used for shipping fee calculation)</span></label>
                    <input type="number" name="weight_kg" id="edit_weight_kg" step="0.001" min="0">
                </div>
                <div class="modal-field modal-field-full">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                <div class="modal-field modal-field-full">
                    <label>Key Features <span class="field-hint">(separate each with |)</span></label>
                    <textarea name="features" id="edit_features" rows="3"></textarea>
                </div>
                <div class="modal-field">
                    <label>New Image <span class="field-hint">(leave blank to keep current)</span></label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="modal-field">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="In Stock">In Stock</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Featured on Homepage</label>
                    <select name="is_featured" id="edit_is_featured">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                    <p id="featured-warning" style="display:none; color:#c62828; font-size:12px; margin-top:4px;">
                    5 products are already featured. You must unfeature one before featuring this product.
                    </p>
                </div>
                <div class="modal-field">
                    <label>Visible to Customers</label>
                    <select name="is_visible" id="edit_is_visible">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="closeEditModal2">Cancel</button>
                <button type="submit" class="btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Delete Product</h2>
            <button type="button" class="modal-close" id="closeDeleteModal">&times;</button>
        </div>
        <p style="font-size:14px; color:#555; margin: 8px 0 20px 0;">
            Are you sure you want to delete <strong id="deleteProductName"></strong>? This cannot be undone.
        </p>
        <form action="admin-product-save.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="product_id" id="delete_product_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="closeDeleteModal2">Cancel</button>
                <button type="submit" class="btn-delete-confirm">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// ADD MODAL
document.getElementById('openAddModal').onclick    = () => document.getElementById('addModal').style.display    = 'flex';
document.getElementById('closeAddModal').onclick   = () => document.getElementById('addModal').style.display    = 'none';
document.getElementById('closeAddModal2').onclick  = () => document.getElementById('addModal').style.display    = 'none';

// EDIT MODAL
document.getElementById('closeEditModal').onclick  = () => document.getElementById('editModal').style.display   = 'none';
document.getElementById('closeEditModal2').onclick = () => document.getElementById('editModal').style.display   = 'none';

function openEdit(data) {
    document.getElementById('edit_product_id').value   = data.product_id;
    document.getElementById('edit_product_name').value = data.product_name;
    document.getElementById('edit_brand').value        = data.brand;
    document.getElementById('edit_category_id').value  = data.category_id;
    document.getElementById('edit_price').value        = data.price;
    document.getElementById('edit_stock').value        = data.stock;
    document.getElementById('edit_warranty').value     = data.warranty   ?? '';
    document.getElementById('edit_weight_kg').value    = data.weight_kg  ?? '0';
    document.getElementById('edit_description').value  = data.description ?? '';
    document.getElementById('edit_features').value     = data.features   ?? '';
    document.getElementById('edit_status').value       = data.status;
    document.getElementById('edit_is_featured').value  = data.is_featured;
    document.getElementById('edit_is_visible').value   = data.is_visible;
    document.getElementById('edit_discount_percent').value = data.discount_percent ?? 0;
    document.getElementById('editModal').style.display = 'flex';

    
    // Show warning kung 5 na ang featured at hindi pa featured yung product na ito
    const featuredWarning = document.getElementById('featured-warning');
    if (featuredWarning) {
        const currentFeaturedCount = <?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE is_featured = 1"))['cnt']; ?>;
        if (currentFeaturedCount >= 5 && data.is_featured == 0) {
            featuredWarning.style.display = 'block';
        } else {
            featuredWarning.style.display = 'none';
        }
    }

    // Show current product image in edit modal
    const editPreview = document.getElementById('edit-image-preview');
    if (data.image && data.image !== '') {
        editPreview.src = data.image;
        editPreview.style.display = 'block';
    } else {
        editPreview.style.display = 'none';
    }
    // Reset file input
    document.getElementById('edit-image-input').value = '';

}

// DELETE MODAL
document.getElementById('closeDeleteModal').onclick  = () => document.getElementById('deleteModal').style.display = 'none';
document.getElementById('closeDeleteModal2').onclick = () => document.getElementById('deleteModal').style.display = 'none';

function confirmDelete(id, name) {
    document.getElementById('delete_product_id').value  = id;
    document.getElementById('deleteProductName').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}

// SEARCH
document.getElementById('productSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#productsTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});


function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}
</script>

</body>
</html>