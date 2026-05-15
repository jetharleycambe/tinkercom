<?php
include 'db.php';  
session_start();
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

// $orders = mysqli_query($conn, "SELECT orders.*, users.username, users.email, users.phone,
//                                        addresses.full_address, addresses.city, addresses.postal_code,
//                                        users.first_name, users.last_name
//                                 FROM orders 
//                                 JOIN users ON orders.user_id = users.user_id 
//                                 LEFT JOIN addresses ON orders.address_id = addresses.address_id
//                                 ORDER BY orders.created_at DESC");

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <div>
                <h1>Orders</h1>
                <p class="topbar-sub">Manage customer orders</p>
            </div>
        </div>

        <?php if ($success === 'updated'): ?>
            <div class="alert alert-success">Order status updated successfully.</div>
        <?php endif; ?>

        <?php
$ord_status = isset($_GET['ostatus']) ? $_GET['ostatus'] : '';
$ord_date   = isset($_GET['date'])    ? $_GET['date']    : '';
$ord_search = isset($_GET['search'])  ? trim($_GET['search']) : '';

// Count per status
$ord_status_counts = ['ALL' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders"))['c']];
foreach (['PENDING','PROCESSING','SHIPPED','DELIVERED','CANCELLED'] as $s) {
    $ord_status_counts[$s] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='$s'"))['c'];
}
?>

<!-- Status Tabs -->
<div class="appt-tabs">
    <a href="admin-orders.php<?php echo ($ord_date||$ord_search) ? '?'.http_build_query(array_filter(['date'=>$ord_date,'search'=>$ord_search])) : ''; ?>"
       class="appt-tab <?php echo $ord_status==='' ? 'active' : ''; ?>">
        All <span class="tab-count"><?php echo $ord_status_counts['ALL']; ?></span>
    </a>
    <?php foreach (['PENDING','PROCESSING','SHIPPED','DELIVERED','CANCELLED'] as $s): ?>
    <a href="admin-orders.php?ostatus=<?php echo $s; ?><?php echo $ord_date ? '&date='.$ord_date : ''; ?><?php echo $ord_search ? '&search='.urlencode($ord_search) : ''; ?>"
       class="appt-tab appt-tab-<?php echo strtolower($s); ?> <?php echo $ord_status===$s ? 'active' : ''; ?>">
        <?php echo ucfirst(strtolower($s)); ?>
        <span class="tab-count"><?php echo $ord_status_counts[$s]; ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="admin-table-section" style="border-radius:0 8px 8px 8px;">
    <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
        <h2>
            <?php echo $ord_status ? ucfirst(strtolower($ord_status)) . ' Orders' : 'All Orders'; ?>
        </h2>
        <form method="GET" action="admin-orders.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($ord_status): ?><input type="hidden" name="ostatus" value="<?php echo $ord_status; ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search by customer/order ID..."
                   value="<?php echo htmlspecialchars($ord_search); ?>"
                   style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; width:220px;">
            <label style="font-size:13px;">Date:</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($ord_date); ?>"
                   style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
            <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
            <?php if ($ord_date || $ord_search): ?>
                <a href="admin-orders.php<?php echo $ord_status ? '?ostatus='.$ord_status : ''; ?>" style="font-size:12px; color:#e53935;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php
    // Build query with all active filters
    $ord_where = "WHERE 1=1";
    if ($ord_status) $ord_where .= " AND orders.status = '" . mysqli_real_escape_string($conn, $ord_status) . "'";
    if ($ord_date)   $ord_where .= " AND DATE(orders.created_at) = '" . mysqli_real_escape_string($conn, $ord_date) . "'";
    if ($ord_search) {
        $s = mysqli_real_escape_string($conn, $ord_search);
        $ord_where .= " AND (users.username LIKE '%$s%' OR orders.order_id LIKE '%$s%' OR users.first_name LIKE '%$s%' OR users.last_name LIKE '%$s%')";
    }

    $filtered_orders = mysqli_query($conn,
        "SELECT orders.*, users.username, users.email, users.phone,
                addresses.full_address, addresses.city, addresses.postal_code,
                users.first_name, users.last_name
         FROM orders
         JOIN users    ON orders.user_id    = users.user_id
         LEFT JOIN addresses ON orders.address_id = addresses.address_id
         $ord_where
         ORDER BY orders.created_at DESC"
    );
    ?>

    <table class="admin-table" id="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($filtered_orders)): ?>
            <tr>
                <td>#<?php echo $row['order_id']; ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                <td>
                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                        <?php echo $row['status']; ?>
                    </span>
                </td>
                <td><?php echo date("M j, Y", strtotime($row['created_at'])); ?></td>
                <td>
                    <button class="btn-edit" onclick="openOrderItems(<?php echo $row['order_id']; ?>, '<?php echo $row['delivery_type']; ?>')">View Items</button>
                    <button class="btn-edit" onclick="openCustomerInfo(<?php echo htmlspecialchars(json_encode($row)); ?>)">View Customer</button>
                    <button class="btn-edit" onclick="openStatus(<?php echo $row['order_id']; ?>, '<?php echo $row['status']; ?>')">Update Status</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- UPDATE STATUS MODAL -->
<div class="modal" id="statusModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Update Order Status</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('statusModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-order-save.php" method="POST">
            <input type="hidden" name="order_id" id="status_order_id">
            <div class="modal-field" style="margin-bottom:20px;">
                <label>New Status</label>
                <select name="status" id="status_select">
                    <option value="PENDING">PENDING</option>
                    <option value="PROCESSING">PROCESSING</option>
                    <option value="SHIPPED">SHIPPED</option>
                    <option value="DELIVERED">DELIVERED</option>
                    <option value="CANCELLED">CANCELLED</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('statusModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- CUSTOMER INFO MODAL -->
<div class="modal" id="customerModal">
    <div class="modal-content" style="max-width:460px;">
        <div class="modal-header">
            <h2>Customer Information</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('customerModal').style.display='none'">&times;</button>
        </div>
        <div style="padding: 0 0 16px 0;">
            <p style="margin-bottom:10px;"><strong>Name:</strong> <span id="ci-name">—</span></p>
            <p style="margin-bottom:10px;"><strong>Username:</strong> <span id="ci-username">—</span></p>
            <p style="margin-bottom:10px;"><strong>Email:</strong> <span id="ci-email">—</span></p>
            <p style="margin-bottom:10px;"><strong>Phone:</strong> <span id="ci-phone">—</span></p>
            <hr style="margin: 14px 0; border-color:#eee;">
            <p style="margin-bottom:6px;"><strong>Delivery Address:</strong></p>
            <p id="ci-address" style="color:#444; font-size:14px;">—</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-primary" onclick="document.getElementById('customerModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- ORDER ITEMS MODAL -->
<div class="modal" id="orderItemsModal">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h2 id="orderItemsTitle">Order Items</h2>
            <button type="button" class="modal-close" 
                onclick="document.getElementById('orderItemsModal').style.display='none'">&times;</button>
        </div>
        <div id="orderItemsBody" style="padding: 0 0 16px 0;">
            <p style="color:#aaa;">Loading...</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-primary" 
                onclick="document.getElementById('orderItemsModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
function openStatus(id, currentStatus) {
    document.getElementById('status_order_id').value = id;
    document.getElementById('status_select').value   = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', () => {
    const searchEl = document.getElementById('orderSearch');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#ordersTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});


function openCustomerInfo(row) {
    const firstName = row.first_name || '';
    const lastName  = row.last_name  || '';
    const fullName  = (firstName + ' ' + lastName).trim() || row.username;
    
    document.getElementById('ci-name').textContent     = fullName;
    document.getElementById('ci-username').textContent = row.username;
    document.getElementById('ci-email').textContent    = row.email    || '—';
    document.getElementById('ci-phone').textContent    = row.phone    || '—';
    
    const addr = row.full_address 
        ? row.full_address + ', ' + row.city + (row.postal_code ? ' ' + row.postal_code : '')
        : 'No address on file';
    document.getElementById('ci-address').textContent = addr;
    
    document.getElementById('customerModal').style.display = 'flex';
}


function openOrderItems(orderId, deliveryType) {
    document.getElementById('orderItemsTitle').textContent = 'Order #' + orderId + ' — Items';
    document.getElementById('orderItemsBody').innerHTML = '<p style="color:#aaa;">Loading...</p>';
    document.getElementById('orderItemsModal').style.display = 'flex';

    Promise.all([
        fetch('admin-get-order-items.php?order_id=' + orderId).then(r => r.json()),
        fetch('admin-get-order-items.php?order_id=' + orderId + '&totals=1').then(r => r.json())
    ]).then(([items, totals]) => {
        if (items.length === 0) {
            document.getElementById('orderItemsBody').innerHTML = '<p style="color:#aaa;">No items found.</p>';
            return;
        }

        const fmt = v => parseFloat(v || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});

        // ── Items table ──────────────────────────────────────
        let html = '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
        html += '<thead><tr style="background:#f5f5f5;">'
              + '<th style="padding:8px; text-align:left;">Product</th>'
              + '<th style="padding:8px; text-align:center;">Qty</th>'
              + '<th style="padding:8px; text-align:right;">Price</th>'
              + '<th style="padding:8px; text-align:right;">Subtotal</th>'
              + '</tr></thead><tbody>';

        items.forEach(item => {
            html += `<tr style="border-bottom:1px solid #eee;">
                <td style="padding:10px 8px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img src="${item.image}" style="width:40px;height:40px;object-fit:contain;border-radius:4px;flex-shrink:0;" />
                        <div>
                            <p style="margin:0;font-weight:600;">${item.product_name}</p>
                            <p style="margin:0;font-size:11px;color:#888;">${item.brand}</p>
                        </div>
                    </div>
                </td>
                <td style="padding:10px 8px;text-align:center;">${item.quantity}</td>
                <td style="padding:10px 8px;text-align:right;">₱${fmt(item.price)}</td>
                <td style="padding:10px 8px;text-align:right;font-weight:600;">₱${fmt(item.subtotal)}</td>
            </tr>`;
        });
        html += '</tbody></table>';

        // ── Order summary breakdown ──────────────────────────
        if (totals && !totals.error) {
            const subtotal  = parseFloat(totals.subtotal        || 0);
            const shipping  = parseFloat(totals.shipping_fee    || 0);
            const discount  = parseFloat(totals.discount_amount || 0);
            const total     = parseFloat(totals.total_amount    || 0);

            // Use the delivery type passed directly from PHP (most reliable)
            const deliv = deliveryType || totals.delivery_type || 'delivery';
            const isPickup = deliv === 'pickup';

            const summaryRow = (label, value, extraStyle = '') =>
                `<div style="display:flex;justify-content:space-between;align-items:center;
                             font-size:13px;padding:7px 0;border-bottom:1px solid #f5f5f5;${extraStyle}">
                    <span style="color:#555;">${label}</span>
                    <span>${value}</span>
                 </div>`;

            html += `<div style="margin-top:16px;border-top:2px solid #f0f0f0;padding-top:14px;">`;

            // ── Fulfillment type row ─────────────────────────
            const delivBadge = isPickup
                ? `<span style="background:#e0f2fe;color:#075985;border:1.5px solid #7dd3fc;
                               padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;
                               display:inline-flex;align-items:center;gap:4px;">🏬 Store Pickup</span>`
                : `<span style="background:#f0fdf4;color:#166534;border:1.5px solid #86efac;
                               padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;
                               display:inline-flex;align-items:center;gap:4px;">🚚 Home Delivery</span>`;

            html += summaryRow('Order Type', delivBadge);

            // ── Subtotal ─────────────────────────────────────
            html += summaryRow('Subtotal', '₱' + fmt(subtotal));

            // ── Shipping fee ─────────────────────────────────
            let shippingVal;
            if (isPickup) {
                shippingVal = '<span style="color:#0049af;font-weight:600;">FREE</span>';
            } else if (shipping === 0) {
                shippingVal = '<span style="color:#16a34a;font-weight:600;">FREE ✓</span>';
            } else {
                const city   = totals.address_city ? `<small style="color:#aaa;"> · ${totals.address_city}</small>` : '';
                const wt     = totals.weight_kg    ? `<small style="color:#aaa;"> · ${totals.weight_kg} kg</small>` : '';
                shippingVal  = `<span>₱${fmt(shipping)}${city}${wt}</span>`;
            }
            html += summaryRow('Shipping Fee', shippingVal);

            // ── Discount ─────────────────────────────────────
            if (discount > 0) {
                html += summaryRow('Discount',
                    `<span style="color:#e53935;font-weight:600;">−₱${fmt(discount)}</span>`);
            }

            // ── Total ────────────────────────────────────────
            html += summaryRow('Total',
                `<strong style="color:#0049af;font-size:15px;">₱${fmt(total)}</strong>`,
                'border-bottom:none;padding-top:10px;margin-top:4px;');

            html += `</div>`;
        }

        document.getElementById('orderItemsBody').innerHTML = html;
    }).catch(err => {
        document.getElementById('orderItemsBody').innerHTML =
            '<p style="color:#e53935;">Failed to load order details. Please try again.</p>';
        console.error('openOrderItems error:', err);
    });
}
</script>

</body>
</html>