<?php
include 'db.php';  
session_start();
include 'log_action.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

$action = $_POST["action"] ?? '';

// ── Shared image upload helper ────────────────────────────────
function handle_image_upload($redirect_on_error) {
    if (!isset($_FILES["image"]) || $_FILES["image"]["error"] !== 0) {
        return null; // No new image uploaded — caller decides what to do
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $allowed_exts  = ['jpg', 'jpeg', 'png', 'webp'];

    $file_type = mime_content_type($_FILES["image"]["tmp_name"]);
    $ext       = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

    if (!in_array($file_type, $allowed_types) || !in_array($ext, $allowed_exts)) {
        header("Location: {$redirect_on_error}?error=invalid_image");
        exit;
    }

    if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
        header("Location: {$redirect_on_error}?error=image_too_large");
        exit;
    }

    $filename   = "product_" . time() . "." . $ext;
    $upload_dir = "assets/products/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $filename)) {
        return $upload_dir . $filename;
    }

    header("Location: {$redirect_on_error}?error=upload");
    exit;
}

// ── ADD ───────────────────────────────────────────────────────
if ($action === "add") {
    $name             = mysqli_real_escape_string($conn, trim($_POST["product_name"]));
    $brand            = mysqli_real_escape_string($conn, trim($_POST["brand"]));
    $category_id      = intval($_POST["category_id"]);
    $price            = floatval($_POST["price"]);
    $stock            = intval($_POST["stock"]);
    $warranty         = mysqli_real_escape_string($conn, trim($_POST["warranty"]));
    $description      = mysqli_real_escape_string($conn, trim($_POST["description"]));
    $features         = mysqli_real_escape_string($conn, trim($_POST["features"]));
    $status           = $_POST["status"] === "In Stock" ? "In Stock" : "Out of Stock";
    $is_featured      = intval($_POST["is_featured"]);
    $is_visible       = intval($_POST["is_visible"]);
    $weight_kg        = floatval($_POST["weight_kg"] ?? 0.5);
    $discount_percent = floatval($_POST["discount_percent"] ?? 0);

    $image_path = handle_image_upload("admin-products.php") ?? '';

    if ($is_featured === 1) {
        $featured_count = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) as cnt FROM products WHERE is_featured = 1"
        ));
        if ($featured_count['cnt'] >= 5) {
            header("Location: admin-products.php?error=featured_limit");
            exit;
        }
    }

    $sql = "INSERT INTO products
                (category_id, product_name, brand, description, features, image, warranty,
                 price, stock, weight_kg, discount_percent, status, is_featured, is_visible)
            VALUES
                ($category_id, '$name', '$brand', '$description', '$features', '$image_path', '$warranty',
                 $price, $stock, $weight_kg, $discount_percent, '$status', $is_featured, $is_visible)";
    mysqli_query($conn, $sql);
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Added Product', "Added product: $name");
    header("Location: admin-products.php?success=added");
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────
if ($action === "edit") {
    $product_id       = intval($_POST["product_id"]);
    $name             = mysqli_real_escape_string($conn, trim($_POST["product_name"]));
    $brand            = mysqli_real_escape_string($conn, trim($_POST["brand"]));
    $category_id      = intval($_POST["category_id"]);
    $price            = floatval($_POST["price"]);
    $stock            = intval($_POST["stock"]);
    $warranty         = mysqli_real_escape_string($conn, trim($_POST["warranty"]));
    $description      = mysqli_real_escape_string($conn, trim($_POST["description"]));
    $features         = mysqli_real_escape_string($conn, trim($_POST["features"]));
    $status           = $_POST["status"] === "In Stock" ? "In Stock" : "Out of Stock";
    $is_featured      = intval($_POST["is_featured"]);
    $is_visible       = intval($_POST["is_visible"]);
    $weight_kg        = floatval($_POST["weight_kg"] ?? 0.5);
    $discount_percent = floatval($_POST["discount_percent"] ?? 0);

    // Handle optional new image — only update image column if a new file was uploaded
    $new_image_path = handle_image_upload("admin-products.php");
    $image_sql      = $new_image_path !== null
        ? ", image = '" . mysqli_real_escape_string($conn, $new_image_path) . "'"
        : '';   // No new image: keep existing one

    if ($is_featured === 1) {
        $featured_count = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) as cnt FROM products WHERE is_featured = 1 AND product_id != $product_id"
        ));
        if ($featured_count['cnt'] >= 5) {
            header("Location: admin-products.php?error=featured_limit&id=$product_id");
            exit;
        }
    }

    $sql = "UPDATE products SET
                category_id       = $category_id,
                product_name      = '$name',
                brand             = '$brand',
                description       = '$description',
                features          = '$features',
                warranty          = '$warranty',
                price             = $price,
                stock             = $stock,
                weight_kg         = $weight_kg,
                discount_percent  = $discount_percent,
                status            = '$status',
                is_featured       = $is_featured,
                is_visible        = $is_visible
                $image_sql
            WHERE product_id = $product_id";
    mysqli_query($conn, $sql);
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Edited Product', "Updated product ID: $product_id - $name");
    header("Location: admin-products.php?success=edited");
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if ($action === "delete") {
    $product_id = intval($_POST["product_id"]);
    mysqli_query($conn, "DELETE FROM products WHERE product_id = $product_id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Deleted Product', "Deleted product ID: $product_id");
    header("Location: admin-products.php?success=deleted");
    exit;
}

header("Location: admin-products.php");
exit;
?>