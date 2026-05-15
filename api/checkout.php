<?php
/**
 * checkout.php — Zone + Weight-based Shipping Fee
 *
 * Shipping is calculated dynamically via AJAX (get-shipping-fee.php)
 * based on:
 *  - Province selected in the address form
 *  - Total weight of cart items (products.weight_kg × quantity)
 *  - Subtotal (for free shipping threshold check)
 */
include 'db.php';  
session_start();

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["customer_id"];
$error   = '';

// ── Fetch user ────────────────────────────────────────────────
$user       = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM users WHERE user_id = $user_id"
));
$first_name = $user["first_name"] ?? '';
$last_name  = $user["last_name"]  ?? '';
$full_name  = trim("$first_name $last_name") ?: $user["username"];

// ── Fetch ALL addresses ───────────────────────────────────────
$addresses = [];
$addr_res  = mysqli_query($conn,
    "SELECT * FROM addresses WHERE user_id = $user_id ORDER BY is_default DESC"
);
while ($a = mysqli_fetch_assoc($addr_res)) {
    $addresses[] = $a;
}
$default_address = $addresses[0] ?? null;

// ── Fetch all provinces for dropdown ─────────────────────────
$provinces_result = mysqli_query($conn,
    "SELECT pz.province_name, sz.zone_id, sz.zone_name
     FROM province_zones pz
     JOIN shipping_zones sz ON pz.zone_id = sz.zone_id
     ORDER BY sz.zone_id ASC, pz.province_name ASC"
);
$provinces = [];
while ($prow = mysqli_fetch_assoc($provinces_result)) {
    $provinces[] = $prow;
}

// ── Build cart items ──────────────────────────────────────────
$cart_items     = [];
$subtotal       = 0;
$total_weight   = 0;
$is_buynow      = false;
$buynow_cart_id = null;

// BUY NOW MODE
if (isset($_GET["buynow"]) && isset($_GET["product_id"])) {
    $is_buynow  = true;
    $product_id = intval($_GET["product_id"]);
    $qty        = max(1, intval($_GET["qty"] ?? 1));

    $stock_check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT stock, status FROM products WHERE product_id = $product_id"
    ));
    if (!$stock_check || $stock_check['stock'] <= 0 || $stock_check['status'] === 'Out of Stock') {
        $redirect = $_SERVER["HTTP_REFERER"] ?? "index.php";
        header("Location: {$redirect}?out_of_stock=1");
        exit;
    }

    $prod = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM products WHERE product_id = $product_id AND is_visible = 1"
    ));
    if ($prod) {
        $prod["quantity"] = $qty;
        $prod["subtotal"] = $prod["price"] * $qty;
        $cart_items[]     = $prod;
        $subtotal         = $prod["subtotal"];
        $total_weight    += floatval($prod["weight_kg"]) * $qty;
    }

    $_SESSION["buynow_product_id"] = $product_id;
    $_SESSION["buynow_qty"]        = $qty;

// CART MODE
} else {
    if (!isset($_GET["items"]) || trim($_GET["items"]) === "") {
        header("Location: cart.php?empty=1"); exit;
    }

    $raw_ids  = preg_replace('/[^0-9,]/', '', $_GET["items"]);
    $id_array = array_filter(explode(",", $raw_ids));
    if (empty($id_array)) { header("Location: cart.php?empty=1"); exit; }

    $ids_for_query = implode(",", array_map('intval', $id_array));

    $cart_row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT cart_id FROM carts WHERE user_id = $user_id"
    ));
    if (!$cart_row) { header("Location: cart.php?empty=1"); exit; }

    $buynow_cart_id = $cart_row["cart_id"];

    $items_result = mysqli_query($conn,
        "SELECT ci.*, p.product_name, p.price, p.image, p.brand, p.product_id, p.weight_kg
         FROM cart_items ci
         JOIN products p ON ci.product_id = p.product_id
         WHERE ci.cart_id = $buynow_cart_id
           AND ci.cart_item_id IN ($ids_for_query)"
    );
    while ($item = mysqli_fetch_assoc($items_result)) {
        $item["subtotal"]  = $item["price"] * $item["quantity"];
        $subtotal         += $item["subtotal"];
        $total_weight     += floatval($item["weight_kg"]) * $item["quantity"];
        $cart_items[]      = $item;
    }

    foreach ($cart_items as $item) {
        $check = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT stock, status FROM products WHERE product_id = {$item['product_id']}"
        ));
        if (!$check || $check['stock'] <= 0 || $check['status'] === 'Out of Stock') {
            header("Location: cart.php?out_of_stock=1"); exit;
        }
    }

    if (empty($cart_items)) { header("Location: cart.php?empty=1"); exit; }
}

// ── DISCOUNT AJAX VALIDATION ──────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'validate_discount') {
    header('Content-Type: application/json');
    $code  = trim(mysqli_real_escape_string($conn, $_POST['code'] ?? ''));
    $sub   = floatval($_POST['subtotal'] ?? 0);
    $today = date('Y-m-d');

    $disc = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM discounts
         WHERE code = '$code'
           AND is_active = 1
           AND (start_date IS NULL OR start_date <= '$today')
           AND (end_date   IS NULL OR end_date   >= '$today')
           AND (usage_limit IS NULL OR times_used < usage_limit)
         LIMIT 1"
    ));

    if (!$disc) {
        echo json_encode(['valid' => false, 'message' => 'Invalid or expired promo code.']);
    } elseif ($sub < $disc['min_order']) {
        echo json_encode(['valid' => false,
            'message' => 'Minimum order of ₱' . number_format($disc['min_order'], 2) . ' required.']);
    } else {
        $disc_amt = $disc['type'] === 'percentage'
            ? ($sub * $disc['value'] / 100)
            : floatval($disc['value']);
        if ($disc['max_discount'] && $disc_amt > $disc['max_discount']) {
            $disc_amt = floatval($disc['max_discount']);
        }
        echo json_encode([
            'valid'           => true,
            'discount_id'     => $disc['discount_id'],
            'discount_amount' => round($disc_amt, 2),
            'message'         => '✅ ' . $disc['description'] . ' — ₱' . number_format($disc_amt, 2) . ' off!',
        ]);
    }
    exit;
}

// ── PLACE ORDER ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {

    $delivery_type   = ($_POST['delivery_type'] ?? 'delivery') === 'pickup' ? 'pickup' : 'delivery';
    $address_id      = $delivery_type === 'delivery' ? intval($_POST['address_id'] ?? 0) : null;
    $discount_id     = !empty($_POST['discount_id'])     ? intval($_POST['discount_id'])     : null;
    $discount_amount = !empty($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
    $notes           = trim(mysqli_real_escape_string($conn, $_POST['notes'] ?? ''));

    // Use the shipping fee computed by JS (validated server-side for safety)
    $shipping_fee = 0;
    if ($delivery_type === 'delivery') {
        $posted_province = trim(mysqli_real_escape_string($conn, $_POST['shipping_province'] ?? ''));
        if ($posted_province !== '') {
            // Recompute server-side for safety
            $zone_row = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT sz.base_fee, sz.fee_per_kg, sz.free_threshold
                 FROM province_zones pz
                 JOIN shipping_zones sz ON pz.zone_id = sz.zone_id
                 WHERE LOWER(pz.province_name) LIKE LOWER('%$posted_province%')
                 LIMIT 1"
            ));
            if (!$zone_row) {
                $zone_row = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT base_fee, fee_per_kg, free_threshold FROM shipping_zones WHERE zone_id = 3"
                ));
            }
            $extra_kg    = max(0, $total_weight - 1);
            $shipping_fee = floatval($zone_row['base_fee']) + round($extra_kg * floatval($zone_row['fee_per_kg']), 2);
            $threshold    = floatval($zone_row['free_threshold']);
            if ($threshold > 0 && $subtotal >= $threshold) {
                $shipping_fee = 0;
            }
        }
    }

    // Require address for delivery
    if ($delivery_type === 'delivery' && !$address_id) {
        $error = 'Please select a delivery address.';
    } else {
        $total_payment = $subtotal + $shipping_fee - $discount_amount;
        if ($total_payment < 0) $total_payment = 0;

        $addr_sql    = $address_id ?? 'NULL';
        $disc_id_sql = $discount_id ?? 'NULL';

        mysqli_query($conn,
            "INSERT INTO orders
                (user_id, address_id, delivery_type, shipping_fee,
                 discount_id, discount_amount, total_amount, status, notes)
             VALUES
                ($user_id, $addr_sql, '$delivery_type', $shipping_fee,
                 $disc_id_sql, $discount_amount, $total_payment, 'PENDING', '$notes')"
        );

        $order_id = mysqli_insert_id($conn);

        if (file_exists('log_action.php')) {
            include_once 'log_action.php';
            log_action($conn, 'USER', $user_id, $_SESSION['customer_name'],
                'Placed Order', "Order #$order_id, Total: ₱$total_payment");
        }

        // Determine items to save
        $items_to_save = [];
        if (isset($_POST["is_buynow"]) && $_POST["is_buynow"] === "1") {
            $pid = intval($_SESSION["buynow_product_id"] ?? 0);
            $qty = intval($_SESSION["buynow_qty"] ?? 1);
            if ($pid > 0) {
                $p = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT price FROM products WHERE product_id = $pid"
                ));
                $items_to_save[] = [
                    "product_id" => $pid, "quantity" => $qty,
                    "price"      => $p["price"],
                    "subtotal"   => $p["price"] * $qty,
                ];
            }
            unset($_SESSION["buynow_product_id"], $_SESSION["buynow_qty"]);
        } else {
            foreach ($cart_items as $item) {
                $items_to_save[] = [
                    "product_id" => $item["product_id"],
                    "quantity"   => $item["quantity"],
                    "price"      => $item["price"],
                    "subtotal"   => $item["subtotal"],
                ];
            }
        }

        // Insert order items + deduct stock
        foreach ($items_to_save as $item) {
            $final = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT stock FROM products WHERE product_id = {$item['product_id']}"
            ));
            if (!$final || $final['stock'] < $item['quantity']) {
                mysqli_query($conn, "DELETE FROM orders WHERE order_id = $order_id");
                header("Location: cart.php?out_of_stock=1"); exit;
            }
            mysqli_query($conn,
                "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
                 VALUES ($order_id, {$item['product_id']}, {$item['quantity']},
                         {$item['price']}, {$item['subtotal']})"
            );
            mysqli_query($conn,
                "UPDATE products
                 SET stock  = stock - {$item['quantity']},
                     status = CASE WHEN stock - {$item['quantity']} <= 0
                                   THEN 'Out of Stock' ELSE 'In Stock' END
                 WHERE product_id = {$item['product_id']}"
            );
        }

        // Insert payment record
        mysqli_query($conn,
            "INSERT INTO payments (order_id, payment_method, payment_status)
             VALUES ($order_id, 'COD', 'PENDING')"
        );

        // Increment discount usage
        if ($discount_id) {
            mysqli_query($conn,
                "UPDATE discounts SET times_used = times_used + 1
                 WHERE discount_id = $discount_id"
            );
        }

        // Remove ordered items from cart
        if (!$is_buynow && $buynow_cart_id && !empty($items_to_save)) {
            $ordered_pids = implode(",", array_map(fn($i) => intval($i['product_id']), $items_to_save));
            mysqli_query($conn,
                "DELETE FROM cart_items
                 WHERE cart_id = $buynow_cart_id
                   AND product_id IN ($ordered_pids)"
            );
        }

        $_SESSION["last_order_id"] = $order_id;
        header("Location: order-confirmed.php");
        exit;
    }
}

$active_nav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="background">

<?php include 'header.php'; ?>

<div class="checkout-wrap">
    <h1 class="checkout-heading">Checkout</h1>

    <?php if ($error): ?>
        <div class="alert alert-error" style="max-width:1100px;margin:0 auto 16px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">

        <!-- ── LEFT: Form ──────────────────────────────────── -->
        <div class="checkout-left">

            <form method="POST" action="checkout.php?<?php
                echo $is_buynow
                    ? 'buynow=1&product_id=' . intval($_GET['product_id']) . '&qty=' . intval($_GET['qty'])
                    : 'items=' . htmlspecialchars($_GET['items']);
            ?>" id="checkout-form">
                <input type="hidden" name="place_order"       value="1">
                <input type="hidden" name="is_buynow"         value="<?php echo $is_buynow ? '1' : '0'; ?>">
                <input type="hidden" name="discount_id"       id="hidden_discount_id"     value="">
                <input type="hidden" name="discount_amount"   id="hidden_discount_amount" value="0">
                <!-- Province is posted so the server can recompute shipping safely -->
                <input type="hidden" name="shipping_province" id="hidden_shipping_province" value="">

                <!-- ── STEP 1: Delivery Method ──────────── -->
                <div class="co-section">
                    <h2 class="co-section-title">
                        <span class="co-step">1</span> Delivery Method
                    </h2>
                    <div class="co-delivery-options">
                        <label class="co-delivery-opt selected" id="opt_delivery">
                            <input type="radio" name="delivery_type" value="delivery"
                                   checked onchange="onDeliveryChange('delivery')">
                            <div class="co-delivery-content">
                                <span class="co-del-icon">🚚</span>
                                <div>
                                    <strong>Home Delivery</strong>
                                    <p>Delivered straight to your address</p>
                                </div>
                                <span class="co-del-fee" id="delivery_fee_badge">
                                    Calculating...
                                </span>
                            </div>
                        </label>

                        <label class="co-delivery-opt" id="opt_pickup">
                            <input type="radio" name="delivery_type" value="pickup"
                                   onchange="onDeliveryChange('pickup')">
                            <div class="co-delivery-content">
                                <span class="co-del-icon">🏪</span>
                                <div>
                                    <strong>Store Pickup</strong>
                                    <p>Blk 6 Lot 4 Alegra Heights, Santa Maria, Bulacan</p>
                                </div>
                                <span class="co-del-fee free">FREE</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- ── STEP 2: Delivery Address ─────────── -->
                <div class="co-section" id="section-address">
                    <h2 class="co-section-title">
                        <span class="co-step">2</span> Delivery Address
                    </h2>

                    <?php if (!empty($addresses)): ?>
                        <div class="co-address-list">
                            <?php foreach ($addresses as $idx => $addr): ?>
                            <label class="co-address-opt"
                                   data-province="<?php echo htmlspecialchars($addr['province'] ?? ''); ?>">
                                <input type="radio" name="address_id"
                                       value="<?php echo $addr['address_id']; ?>"
                                       onchange="onAddressSelect(this)"
                                       <?php echo ($idx === 0) ? 'checked' : ''; ?>>
                                <div class="co-address-content">
                                    <p class="co-addr-name">
                                        <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                        <?php if ($user['phone']): ?>
                                            — <?php echo htmlspecialchars($user['phone']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="co-addr-full">
                                        <?php echo htmlspecialchars($addr['full_address']); ?>,
                                        <?php echo htmlspecialchars($addr['city']); ?>
                                        <?php if (!empty($addr['province'])): ?>
                                            , <?php echo htmlspecialchars($addr['province']); ?>
                                        <?php endif; ?>
                                        <?php echo $addr['postal_code'] ? ' ' . $addr['postal_code'] : ''; ?>
                                    </p>
                                    <?php if ($addr['is_default']): ?>
                                        <span class="co-default-tag">Default</span>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <a href="my-account.php?tab=addresses" target="_blank"
                           class="co-add-address-link">
                            + Add a new address (opens in new tab)
                        </a>

                    <?php else: ?>
                        <div class="co-warning">
                            ⚠ No saved address.
                            <a href="my-account.php?tab=addresses" target="_blank">
                                Add one here
                            </a>
                            (opens in new tab, then refresh this page)
                        </div>
                    <?php endif; ?>

                    <!-- Province override — shown when saved address has no province -->
                    <div id="province-picker" style="margin-top:14px; display:none;">
                        <label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">
                            Province / Area <span style="color:#e53935;">*</span>
                            <span style="font-weight:400; color:#888;">(needed to compute shipping fee)</span>
                        </label>
                        <select id="province_select" onchange="onProvinceChange(this.value)"
                                style="width:100%; padding:9px 12px; border:1.5px solid #dde1ea;
                                       border-radius:8px; font-size:13px; background:#fff;">
                            <option value="">— Select your province —</option>
                            <?php
                            $current_zone = '';
                            foreach ($provinces as $prov):
                                if ($prov['zone_name'] !== $current_zone):
                                    if ($current_zone !== '') echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($prov['zone_name']) . '">';
                                    $current_zone = $prov['zone_name'];
                                endif;
                            ?>
                                <option value="<?php echo htmlspecialchars($prov['province_name']); ?>">
                                    <?php echo htmlspecialchars($prov['province_name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($current_zone !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>

                    <!-- Shipping fee info box -->
                    <div id="shipping-info-box" style="
                        margin-top:12px; padding:10px 14px;
                        background:#f0f7ff; border:1.5px solid #bfdbfe;
                        border-radius:8px; font-size:13px; display:none;">
                        <span id="shipping-info-text">Calculating...</span>
                    </div>
                </div>

                <!-- ── STEP 3: Promo Code ────────────────── -->
                <div class="co-section">
                    <h2 class="co-section-title">
                        <span class="co-step">3</span>
                        Promo Code
                        <span class="co-optional">(optional)</span>
                    </h2>
                    <div class="co-promo-row">
                        <input type="text" id="promo_code_input"
                               placeholder="Enter promo code e.g. WELCOME10">
                        <button type="button" class="co-promo-btn" onclick="applyPromo()">
                            Apply
                        </button>
                        <button type="button" class="co-promo-clear"
                                id="promo_clear_btn"
                                onclick="clearPromo()"
                                style="display:none;">
                            Clear
                        </button>
                    </div>
                    <p id="promo_message" class="co-promo-msg"></p>
                </div>

                <!-- ── STEP 4: Order Notes ───────────────── -->
                <div class="co-section">
                    <h2 class="co-section-title">
                        <span class="co-step">4</span>
                        Order Notes
                        <span class="co-optional">(optional)</span>
                    </h2>
                    <textarea name="notes" rows="2"
                              class="co-notes"
                              placeholder="Special instructions for your order...">
                    </textarea>
                </div>

            </form>
        </div>

        <!-- ── RIGHT: Order Summary ─────────────────────── -->
        <div class="checkout-right">
            <div class="co-summary">
                <h2 class="co-summary-title">Order Summary</h2>

                <!-- Items list -->
                <div class="co-items">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="co-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>"
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <div class="co-item-info">
                            <p class="co-item-name">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </p>
                            <p class="co-item-meta">
                                <?php echo $item['quantity']; ?> ×
                                ₱<?php echo number_format($item['price'], 2); ?>
                                <span style="color:#888; font-size:11px;">
                                    (<?php echo number_format(floatval($item['weight_kg']) * $item['quantity'], 2); ?> kg)
                                </span>
                            </p>
                        </div>
                        <p class="co-item-sub">
                            ₱<?php echo number_format($item['subtotal'], 2); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="co-divider"></div>

                <!-- Totals -->
                <div class="co-totals">
                    <div class="co-total-row">
                        <span>Subtotal</span>
                        <span>₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="co-total-row">
                        <span>Total Weight</span>
                        <span><?php echo number_format($total_weight, 2); ?> kg</span>
                    </div>
                    <div class="co-total-row" id="row_shipping">
                        <span>Shipping Fee
                            <span id="shipping-zone-label"
                                  style="font-size:11px; color:#888; display:block;"></span>
                        </span>
                        <span id="val_shipping" style="text-align:right;">
                            <span style="color:#888; font-size:12px;">Select address</span>
                        </span>
                    </div>
                    <div class="co-total-row co-discount-row"
                         id="row_discount" style="display:none;">
                        <span>Discount</span>
                        <span id="val_discount" style="color:#16a34a;">−₱0.00</span>
                    </div>
                </div>

                <div class="co-divider"></div>

                <div class="co-grand-row">
                    <span>Total</span>
                    <span id="val_total" class="co-grand-total">
                        ₱<?php echo number_format($subtotal, 2); ?>
                    </span>
                </div>

                <p class="co-payment-method">💵 Payment: Cash on Delivery</p>

                <?php if (empty($addresses)): ?>
                    <a href="my-account.php?tab=addresses" class="co-place-btn co-place-disabled"
                       target="_blank">
                        Add Address First
                    </a>
                <?php else: ?>
                    <button type="submit" form="checkout-form"
                            id="place-order-btn"
                            class="co-place-btn" disabled
                            style="opacity:0.6; cursor:not-allowed;">
                        Place Order
                    </button>
                <?php endif; ?>

                <p class="co-terms">
                    By placing your order, you agree to our terms of service.
                </p>
            </div>
        </div>

    </div><!-- end checkout-layout -->
</div><!-- end checkout-wrap -->

<?php include 'login-modal.php'; ?>
<?php include 'footer.php'; ?>

<script>
const SUBTOTAL      = <?php echo $subtotal; ?>;
const TOTAL_WEIGHT  = <?php echo $total_weight; ?>;
let   shippingFee   = 0;
let   shippingReady = false;
let   discAmt       = 0;
let   discId        = '';

// ── Recompute and display totals ────────────────────────────
function updateTotals() {
    const type    = document.querySelector('input[name="delivery_type"]:checked')?.value;
    const fee     = (type === 'delivery') ? shippingFee : 0;
    const total   = Math.max(0, SUBTOTAL + fee - discAmt);

    if (type === 'pickup') {
        document.getElementById('val_shipping').textContent = 'FREE';
        document.getElementById('shipping-zone-label').textContent = '';
        enablePlaceOrder(true);
    }

    document.getElementById('val_total').textContent =
        '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2});
}

// ── Fetch shipping fee from server ───────────────────────────
function fetchShippingFee(province) {
    if (!province) return;

    document.getElementById('val_shipping').innerHTML =
        '<span style="color:#888; font-size:12px;">Calculating...</span>';
    document.getElementById('shipping-zone-label').textContent = '';
    document.getElementById('shipping-info-box').style.display = 'block';
    document.getElementById('shipping-info-text').textContent  = 'Calculating...';
    document.getElementById('hidden_shipping_province').value  = province;
    enablePlaceOrder(false);

    const url = `get-shipping-fee.php?province=${encodeURIComponent(province)}&weight=${TOTAL_WEIGHT}&subtotal=${SUBTOTAL}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                document.getElementById('val_shipping').innerHTML =
                    '<span style="color:#e53935;">Error</span>';
                return;
            }

            shippingFee   = data.total_fee;
            shippingReady = true;

            // Update fee badge on delivery option card
            const badge = document.getElementById('delivery_fee_badge');
            badge.textContent = data.free_shipping
                ? 'FREE'
                : '+₱' + parseFloat(data.total_fee).toLocaleString('en-PH', {minimumFractionDigits:2});
            badge.className   = 'co-del-fee' + (data.free_shipping ? ' free' : '');

            // Update summary row
            const shippingEl = document.getElementById('val_shipping');
            if (data.free_shipping) {
                shippingEl.innerHTML = '<span style="color:#16a34a; font-weight:600;">FREE</span>';
            } else {
                shippingEl.textContent =
                    '₱' + parseFloat(data.total_fee).toLocaleString('en-PH', {minimumFractionDigits:2});
            }

            // Zone label
            let zoneLabel = data.zone_name;
            if (data.extra_fee > 0) {
                zoneLabel += ` · Base ₱${data.base_fee} + ₱${data.extra_fee} extra`;
            }
            document.getElementById('shipping-zone-label').textContent = zoneLabel;

            // Info box
            let infoText = `Zone: ${data.zone_name} · Weight: ${data.weight_kg} kg`;
            if (data.free_shipping) {
                infoText += ' · 🎉 Free shipping applied!';
            } else if (data.extra_fee > 0) {
                infoText += ` · Base ₱${data.base_fee} + extra ₱${data.extra_fee} (for weight over 1 kg)`;
            } else {
                infoText += ` · Base fee: ₱${data.base_fee}`;
            }
            document.getElementById('shipping-info-text').textContent = infoText;

            updateTotals();
            enablePlaceOrder(true);
        })
        .catch(() => {
            document.getElementById('val_shipping').innerHTML =
                '<span style="color:#e53935; font-size:12px;">Could not load fee</span>';
        });
}

// ── Enable/disable Place Order button ────────────────────────
function enablePlaceOrder(enable) {
    const btn = document.getElementById('place-order-btn');
    if (!btn) return;
    btn.disabled = !enable;
    btn.style.opacity = enable ? '1' : '0.6';
    btn.style.cursor  = enable ? 'pointer' : 'not-allowed';
}

// ── Address radio changed ────────────────────────────────────
function onAddressSelect(radio) {
    const label    = radio.closest('label.co-address-opt');
    const province = label?.dataset.province || '';
    const picker   = document.getElementById('province-picker');

    if (province) {
        picker.style.display = 'none';
        document.getElementById('province_select').value = '';
        fetchShippingFee(province);
    } else {
        // Address has no province — show the picker
        picker.style.display = 'block';
        document.getElementById('shipping-info-box').style.display = 'none';
        document.getElementById('val_shipping').innerHTML =
            '<span style="color:#888; font-size:12px;">Select province below</span>';
        document.getElementById('hidden_shipping_province').value = '';
        enablePlaceOrder(false);
    }
}

// ── Province picker changed ──────────────────────────────────
function onProvinceChange(province) {
    fetchShippingFee(province);
}

// ── Delivery/Pickup toggle ───────────────────────────────────
function onDeliveryChange(value) {
    const section = document.getElementById('section-address');
    const optD    = document.getElementById('opt_delivery');
    const optP    = document.getElementById('opt_pickup');

    section.style.display = (value === 'delivery') ? 'block' : 'none';
    optD.classList.toggle('selected', value === 'delivery');
    optP.classList.toggle('selected', value === 'pickup');

    if (value === 'pickup') {
        shippingFee = 0;
        document.getElementById('shipping-info-box').style.display = 'none';
        enablePlaceOrder(true);
    } else {
        // Re-check if we have a province ready
        const province = document.getElementById('hidden_shipping_province').value;
        if (!province) enablePlaceOrder(false);
    }

    updateTotals();
}

// ── Apply promo code ─────────────────────────────────────────
function applyPromo() {
    const code     = document.getElementById('promo_code_input').value.trim();
    const msgEl    = document.getElementById('promo_message');
    const clearBtn = document.getElementById('promo_clear_btn');

    if (!code) {
        msgEl.textContent = 'Please enter a promo code.';
        msgEl.style.color = '#e53935';
        return;
    }

    msgEl.textContent = 'Checking...';
    msgEl.style.color = '#888';

    const formData = new FormData();
    formData.append('action',   'validate_discount');
    formData.append('code',     code);
    formData.append('subtotal', SUBTOTAL);

    fetch('checkout.php?<?php
        echo $is_buynow
            ? 'buynow=1&product_id=' . intval($_GET['product_id']) . '&qty=' . intval($_GET['qty'])
            : 'items=' . htmlspecialchars($_GET['items']);
    ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            discAmt = data.discount_amount;
            discId  = data.discount_id;

            document.getElementById('hidden_discount_id').value    = discId;
            document.getElementById('hidden_discount_amount').value = discAmt;

            document.getElementById('row_discount').style.display = 'flex';
            document.getElementById('val_discount').textContent   =
                '−₱' + discAmt.toLocaleString('en-PH', {minimumFractionDigits: 2});

            msgEl.textContent = data.message;
            msgEl.style.color = '#16a34a';
            clearBtn.style.display = 'inline-block';
            updateTotals();
        } else {
            msgEl.textContent = data.message;
            msgEl.style.color = '#e53935';
        }
    });
}

// ── Clear promo code ─────────────────────────────────────────
function clearPromo() {
    discAmt = 0;
    discId  = '';
    document.getElementById('hidden_discount_id').value    = '';
    document.getElementById('hidden_discount_amount').value = '0';
    document.getElementById('promo_code_input').value      = '';
    document.getElementById('promo_message').textContent   = '';
    document.getElementById('row_discount').style.display  = 'none';
    document.getElementById('promo_clear_btn').style.display = 'none';
    updateTotals();
}

// ── Init: auto-trigger on the pre-selected address ───────────
document.addEventListener('DOMContentLoaded', () => {
    const checkedAddr = document.querySelector('input[name="address_id"]:checked');
    if (checkedAddr) {
        onAddressSelect(checkedAddr);
    } else {
        // No saved address — show province picker
        const picker = document.getElementById('province-picker');
        if (picker) picker.style.display = 'block';
    }
});
</script>

</body>
</html>