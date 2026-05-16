<?php
include 'db.php';  
session_start();
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

// $categories = mysqli_query($conn, "SELECT categories.*, COUNT(products.product_id) as product_count 
//                                    FROM categories 
//                                    LEFT JOIN products ON categories.category_id = products.category_id 
//                                    GROUP BY categories.category_id 
//                                    ORDER BY categories.category_id DESC");

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <div>
                <h1>Categories</h1>
                <p class="topbar-sub">Manage product categories</p>
            </div>
            <button class="btn-primary" id="openAddModal">+ Add Category</button>
        </div>

        <?php if ($success === 'added'): ?>
            <div class="alert alert-success">Category added successfully.</div>
        <?php elseif ($success === 'edited'): ?>
            <div class="alert alert-success">Category updated successfully.</div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">Category deleted successfully.</div>
        <?php elseif ($success === 'error'): ?>
            <div class="alert alert-error">Cannot delete a category that has products.</div>
        <?php endif; ?>

        <?php
// Filters
$cat_sort   = isset($_GET['sort'])   ? $_GET['sort']   : 'id_desc';
$cat_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sort_map_cat = [
    'id_desc'    => 'categories.category_id DESC',
    'id_asc'     => 'categories.category_id ASC',
    'name_asc'   => 'categories.category_name ASC',
    'name_desc'  => 'categories.category_name DESC',
    'count_desc' => 'product_count DESC',
    'count_asc'  => 'product_count ASC',
];
$cat_sort_clause = $sort_map_cat[$cat_sort] ?? 'categories.category_id DESC';
$cat_search_sql = $cat_search ? "HAVING categories.category_name LIKE '%" . mysqli_real_escape_string($conn, $cat_search) . "%'" : '';

$categories = mysqli_query($conn,
    "SELECT categories.*, COUNT(products.product_id) as product_count
     FROM categories
     LEFT JOIN products ON categories.category_id = products.category_id
     GROUP BY categories.category_id
     $cat_search_sql
     ORDER BY $cat_sort_clause"
);
?>

<div class="admin-table-section">
    <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
        <h2>All Categories</h2>
        <form method="GET" action="admin-categories.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search category..." value="<?php echo htmlspecialchars($cat_search); ?>"
                   style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; width:180px;">
            <select name="sort" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                <option value="id_desc"    <?php echo $cat_sort==='id_desc'    ? 'selected':''; ?>>Newest First</option>
                <option value="id_asc"     <?php echo $cat_sort==='id_asc'     ? 'selected':''; ?>>Oldest First</option>
                <option value="name_asc"   <?php echo $cat_sort==='name_asc'   ? 'selected':''; ?>>Name A–Z</option>
                <option value="name_desc"  <?php echo $cat_sort==='name_desc'  ? 'selected':''; ?>>Name Z–A</option>
                <option value="count_desc" <?php echo $cat_sort==='count_desc' ? 'selected':''; ?>>Most Products</option>
                <option value="count_asc"  <?php echo $cat_sort==='count_asc'  ? 'selected':''; ?>>Least Products</option>
            </select>
            <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
            <?php if ($cat_search): ?>
                <a href="admin-categories.php" style="font-size:12px; color:#e53935;">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Category Name</th>
                <th>No. of Products</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($categories)): ?>
            <tr>
                <td>#<?php echo $row['category_id']; ?></td>
                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                <td><?php echo $row['product_count']; ?></td>
                <td>
                    <button class="btn-edit" onclick="openEdit(<?php echo $row['category_id']; ?>, '<?php echo addslashes($row['category_name']); ?>')">Edit</button>
                    <button class="btn-delete" onclick="confirmDelete(<?php echo $row['category_id']; ?>, '<?php echo addslashes($row['category_name']); ?>')">Delete</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h2>Add Category</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('addModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-category-save.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-field" style="margin-bottom:20px;">
                <label>Category Name</label>
                <input type="text" name="category_name" placeholder="e.g. Printers" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h2>Edit Category</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-category-save.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="modal-field" style="margin-bottom:20px;">
                <label>Category Name</label>
                <input type="text" name="category_name" id="edit_category_name" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Delete Category</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('deleteModal').style.display='none'">&times;</button>
        </div>
        <p style="font-size:14px; color:#555; margin: 8px 0 20px 0;">
            Are you sure you want to delete <strong id="deleteCategoryName"></strong>? This cannot be undone.
        </p>
        <form action="admin-category-save.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" id="delete_category_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-delete-confirm">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('openAddModal').onclick = () => document.getElementById('addModal').style.display = 'flex';

function openEdit(id, name) {
    document.getElementById('edit_category_id').value   = id;
    document.getElementById('edit_category_name').value = name;
    document.getElementById('editModal').style.display  = 'flex';
}

function confirmDelete(id, name) {
    document.getElementById('delete_category_id').value        = id;
    document.getElementById('deleteCategoryName').textContent  = name;
    document.getElementById('deleteModal').style.display       = 'flex';
}
</script>

</body>
</html>