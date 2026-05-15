<?php
include 'db.php';

header("Content-Type: application/json");

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";

if ($q === "" || strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT products.product_id, products.product_name, products.price,
                                   products.discount_percent, products.image,
                                   categories.category_name
                            FROM products
                            JOIN categories ON products.category_id = categories.category_id
                            WHERE products.is_visible = 1
                            AND (
                                products.product_name LIKE ? OR
                                products.brand LIKE ? OR
                                categories.category_name LIKE ?
                            )
                            ORDER BY products.product_name ASC
                            LIMIT 6");
    
    $searchTerm = "%$q%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $discount_percent = !empty($row["discount_percent"]) ? intval($row["discount_percent"]) : 0;
        $disc_price       = $discount_percent > 0
            ? $row["price"] * (1 - $discount_percent / 100)
            : null;

        $items[] = [
            "id"               => $row["product_id"],
            "name"             => $row["product_name"],
            "price"            => number_format($row["price"], 2),
            "disc_price"       => $disc_price !== null ? number_format($disc_price, 2) : null,
            "discount_percent" => $discount_percent,
            "image"            => $row["image"],
            "category"         => $row["category_name"]
        ];
    }
    
    echo json_encode($items);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>