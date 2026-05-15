<?php
/**
 * get-shipping-fee.php
 * Called via AJAX from checkout.php
 * Returns JSON: { zone_name, base_fee, extra_fee, total_fee, free_shipping }
 */
include 'db.php';  
session_start();

header('Content-Type: application/json');

$province   = trim($_GET['province'] ?? '');
$total_weight = floatval($_GET['weight'] ?? 0);

if ($province === '') {
    echo json_encode(['error' => 'No province provided.']);
    exit;
}

// Find zone based on province name
$escaped = mysqli_real_escape_string($conn, $province);
$zone_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT sz.zone_id, sz.zone_name, sz.base_fee, sz.fee_per_kg, sz.free_threshold
     FROM province_zones pz
     JOIN shipping_zones sz ON pz.zone_id = sz.zone_id
     WHERE LOWER(pz.province_name) LIKE LOWER('%$escaped%')
     LIMIT 1"
));

// Fallback: if province not found, use Zone 3 (Luzon) as default
if (!$zone_row) {
    $zone_row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM shipping_zones WHERE zone_id = 3"
    ));
    $zone_row['zone_name'] = $zone_row['zone_name'] . ' (default)';
}

$base_fee    = floatval($zone_row['base_fee']);
$fee_per_kg  = floatval($zone_row['fee_per_kg']);
$threshold   = floatval($zone_row['free_threshold']);

// Weight-based extra: charged per kg OVER the first 1kg
$extra_kg    = max(0, $total_weight - 1);
$extra_fee   = round($extra_kg * $fee_per_kg, 2);
$total_fee   = $base_fee + $extra_fee;

// Free shipping check (threshold = 0 means disabled)
$free_shipping = false;
if ($threshold > 0) {
    $subtotal = floatval($_GET['subtotal'] ?? 0);
    if ($subtotal >= $threshold) {
        $total_fee    = 0;
        $free_shipping = true;
    }
}

echo json_encode([
    'zone_name'    => $zone_row['zone_name'],
    'base_fee'     => $base_fee,
    'extra_fee'    => $extra_fee,
    'total_fee'    => $total_fee,
    'free_shipping'=> $free_shipping,
    'weight_kg'    => $total_weight,
]);