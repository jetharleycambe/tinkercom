<?php
include 'db.php';  
session_start();
include 'session-check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id === 0) {
    echo json_encode([]);
    exit;
}

// ── MODE: totals=1 → return order-level fee breakdown ─────────
if (isset($_GET['totals'])) {

    // Only select columns that actually exist in orders + addresses
    $order_res = mysqli_query($conn,
        "SELECT o.shipping_fee, o.discount_amount, o.total_amount,
                o.delivery_type,
                a.city, a.full_address
         FROM orders o
         LEFT JOIN addresses a ON o.address_id = a.address_id
         WHERE o.order_id = $order_id"
    );

    if (!$order_res) {
        echo json_encode(['error' => mysqli_error($conn)]);
        exit;
    }

    $order = mysqli_fetch_assoc($order_res);
    if (!$order) {
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    // Derive subtotal from order_items (no subtotal column on orders)
    $subtotal_res = mysqli_query($conn,
        "SELECT SUM(subtotal) AS sub FROM order_items WHERE order_id = $order_id"
    );
    $subtotal = floatval(mysqli_fetch_assoc($subtotal_res)['sub'] ?? 0);

    // Total weight from order items × product weights
    $weight_res = mysqli_query($conn,
        "SELECT SUM(p.weight_kg * oi.quantity) AS total_weight
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = $order_id"
    );
    $total_weight = round(floatval(mysqli_fetch_assoc($weight_res)['total_weight'] ?? 0), 3);

    $shipping = floatval($order['shipping_fee']    ?? 0);
    $discount = floatval($order['discount_amount'] ?? 0);
    $total    = floatval($order['total_amount']    ?? 0);

    // Infer zone info: extra_fee = shipping - base (base = shipping - extra)
    // Since we don't have zone tables yet, show what we can
    $extra_fee = 0;
    $base_fee  = $shipping;   // fallback: all shipping is base fee
    if ($total_weight > 1 && $shipping > 0) {
        // Estimate: if weight > 1kg, show breakdown hint
        $base_fee  = null;   // unknown without zone table
        $extra_fee = null;
    }

    echo json_encode([
        'subtotal'        => $subtotal,
        'shipping_fee'    => $shipping,
        'discount_amount' => $discount,
        'total_amount'    => $total,
        'delivery_type'   => $order['delivery_type'] ?? 'delivery',
        'zone_name'       => null,
        'base_fee'        => $shipping > 0 ? $shipping : null,
        'extra_fee'       => 0,
        'weight_kg'       => $total_weight ?: null,
        'address_city'    => $order['city'] ?? null,
    ]);
    exit;
}

// ── MODE: default → return order items list ───────────────────
$items = mysqli_query($conn,
    "SELECT oi.quantity, oi.price, oi.subtotal,
            p.product_name, p.brand, p.image
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = $order_id"
);

$result = [];
while ($row = mysqli_fetch_assoc($items)) {
    $result[] = $row;
}

echo json_encode($result);