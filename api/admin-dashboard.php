<?php
include 'db.php';  
session_start();
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

// ── Quick-add expense ──────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $category    = mysqli_real_escape_string($conn, $_POST['category']    ?? 'Other');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $amount      = floatval($_POST['amount'] ?? 0);
    $date        = mysqli_real_escape_string($conn, $_POST['expense_date'] ?? date('Y-m-d'));
    if ($description !== '' && $amount > 0) {
        mysqli_query($conn,
            "INSERT INTO expenses (category, description, amount, expense_date, added_by)
             VALUES ('$category', '$description', $amount, '$date', " . intval($_SESSION['customer_id']) . ")"
        );
    }
    header("Location: admin-dashboard.php");
    exit;
}

// ── REVENUE ───────────────────────────────────────────────────
$gross_sales    = floatval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM orders WHERE status IN ('COMPLETED','DELIVERED')"))['t']);
$total_expenses = floatval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount),0) AS t FROM expenses"))['t']);
$net_revenue    = $gross_sales - $total_expenses;
$month_expenses = floatval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount),0) AS t FROM expenses
     WHERE MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())"))['t']);

// ── STAT CARDS ────────────────────────────────────────────────
$total_orders       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders"))['c'];
$pending_orders     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='PENDING'"))['c'];
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM appointments"))['c'];
$pending_appts      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status='PENDING'"))['c'];
$out_of_stock       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM products WHERE stock=0"))['c'];
$total_suppliers    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM suppliers WHERE status='Active'"))['c'];

// ── PRODUCTS BY CATEGORY (Fix #1: alphabetical ORDER BY) ──────
$total_products_all = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM products WHERE is_visible=1"))['c'];
$total_oos_all = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM products WHERE is_visible=1 AND stock=0"))['c'];

$products_by_cat = mysqli_query($conn,
    "SELECT c.category_name,
            COUNT(p.product_id) AS total,
            SUM(CASE WHEN p.stock = 0 THEN 1 ELSE 0 END) AS oos
     FROM categories c
     LEFT JOIN products p
           ON p.is_visible = 1
          AND p.category_id IN (
              SELECT category_id FROM categories
              WHERE category_id = c.category_id
                 OR parent_category_id = c.category_id
          )
     WHERE c.parent_category_id IS NULL AND c.is_visible = 1
     GROUP BY c.category_id
     ORDER BY c.category_name ASC"
);

// ── TECHNICIAN COUNTS ─────────────────────────────────────────
$tech_available = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM technicians WHERE status='Available'"))['c'];
$tech_busy      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM technicians WHERE status='Busy'"))['c'];
$tech_offduty   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM technicians WHERE status='Off Duty'"))['c'];
$tech_total     = $tech_available + $tech_busy + $tech_offduty;

// ── APPOINTMENTS BY STATUS ────────────────────────────────────
$appt_by_status = [];
foreach (['PENDING','CONFIRMED','ONGOING','COMPLETED','CANCELLED'] as $st) {
    $appt_by_status[$st] = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM appointments WHERE status='$st'"))['c'];
}

// ── BOOKING ALERTS (today to next 7 days) ─────────────────────
$booking_alerts = mysqli_query($conn,
    "SELECT a.*, u.username, u.first_name, u.last_name, s.service_name, t.full_name AS tech_name
     FROM appointments a
     JOIN users    u ON a.user_id    = u.user_id
     JOIN services s ON a.service_id = s.service_id
     LEFT JOIN technicians t ON a.technician_id = t.technician_id
     WHERE a.status IN ('PENDING','CONFIRMED','ONGOING')
       AND DATE(a.appointment_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY a.appointment_date ASC
     LIMIT 10"
);
$booking_alerts_arr = [];
while ($ba = mysqli_fetch_assoc($booking_alerts)) $booking_alerts_arr[] = $ba;

// ── LOW STOCK ─────────────────────────────────────────────────
$low_stock = mysqli_query($conn,
    "SELECT product_id, product_name, stock, image, low_stock_threshold
     FROM products WHERE stock>0 AND stock<=low_stock_threshold ORDER BY stock ASC"
);
$low_stock_count = mysqli_num_rows($low_stock);

// ── RECENT ORDERS ─────────────────────────────────────────────
$recent_orders = mysqli_query($conn,
    "SELECT orders.*, users.username FROM orders
     JOIN users ON orders.user_id=users.user_id
     ORDER BY orders.created_at DESC LIMIT 5"
);

// ── RECENT BOOKINGS ───────────────────────────────────────────
$recent_bookings = mysqli_query($conn,
    "SELECT a.appointment_id, a.appointment_date, a.status, a.created_at,
            u.username, s.service_name
     FROM appointments a
     JOIN users u ON a.user_id = u.user_id
     JOIN services s ON a.service_id = s.service_id
     ORDER BY a.created_at DESC LIMIT 5"
);

// ── TOP 10 BEST-SELLING PRODUCTS ─────────────────────────────
$top_sellers = mysqli_query($conn,
    "SELECT p.product_id, p.product_name, p.image, p.price,
            SUM(oi.quantity) AS total_sold,
            SUM(oi.subtotal) AS total_revenue
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     JOIN orders o ON oi.order_id = o.order_id
     WHERE o.status IN ('DELIVERED','COMPLETED')
     GROUP BY p.product_id
     ORDER BY total_sold DESC
     LIMIT 10"
);

// ── RECENT EXPENSES ───────────────────────────────────────────
$recent_expenses = mysqli_query($conn,
    "SELECT * FROM expenses ORDER BY expense_date DESC, created_at DESC LIMIT 6"
);

// ── AUDIT LOGS ────────────────────────────────────────────────
$admin_logs = mysqli_query($conn,
    "SELECT * FROM audit_logs WHERE actor_type='ADMIN'
     ORDER BY created_at DESC LIMIT 15"
);
$user_logs = mysqli_query($conn,
    "SELECT * FROM audit_logs
     WHERE actor_type='USER'
       AND action IN ('Placed Order','Booked Appointment','Cancelled Order','Cancelled Appointment')
     ORDER BY created_at DESC LIMIT 15"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* ─────────────────────────────────────────────────────
           BOOKING ALERT CARD — mirrors .low-stock-sidebar style
           ───────────────────────────────────────────────────── */
        .booking-alert-card {
            background: #fff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #0049af;
            border-radius: 10px;
            overflow: hidden;
        }
        .booking-alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px 12px 20px;
            border-bottom: 1px solid #dbeafe;
        }
        .booking-alert-header h2 {
            font-size: 15px;
            font-weight: 600;
            color: #1e3a8a;
            margin: 0;
        }
        .booking-alert-list {
            max-height: 260px;
            overflow-y: auto;
            padding: 4px 0;
        }
        .booking-alert-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 20px;
            border-bottom: 1px solid #dbeafe;
        }
        .booking-alert-item:last-child { border-bottom: none; }

        /* ─────────────────────────────────────────────────────
           MAIN BOTTOM GRID — Fix #3 & #4
           ───────────────────────────────────────────────────── */
        .dash-bottom-grid {
            display: grid;
            grid-template-columns: 65% 35%;
            gap: 20px;
            margin-bottom: 24px;
            align-items: stretch;
        }

        /* LEFT column: orders + bookings stack, share height equally */
        .dash-bottom-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .dash-bottom-left .admin-table-section {
            flex: 1;
            margin-bottom: 0;
        }

        /* RIGHT column: expense form + activity cards stack */
        .dash-bottom-right {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Fix #3: activity card — flex so list fills remaining space */
        .activity-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e8ecf2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
        }
        /* Fix #3: scrollable log list, capped at 220px */
        .activity-card .audit-log-list {
            max-height: 220px;
            overflow-y: auto;
            flex: 1;
        }
    </style>
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <div>
                <h1>Dashboard</h1>
                <p class="topbar-sub">Welcome back, <?php echo htmlspecialchars($_SESSION["customer_name"]); ?>!</p>
            </div>
            <div class="topbar-date"><?php echo date("l, F j, Y"); ?></div>
        </div>

        <!-- ══════════════════════════════════════════════════
             REVENUE BREAKDOWN
             ══════════════════════════════════════════════════ -->
        <div class="revenue-breakdown">
            <div class="rev-card rev-gross">
                <p class="rev-label">Gross Sales</p>
                <h2 class="rev-amount">&#8369;<?php echo number_format($gross_sales, 2); ?></h2>
                <p class="rev-sub">Completed &amp; delivered orders</p>
            </div>
            <div class="rev-divider">&#8722;</div>
            <div class="rev-card rev-expenses">
                <p class="rev-label">Total Expenses</p>
                <h2 class="rev-amount">&#8369;<?php echo number_format($total_expenses, 2); ?></h2>
                <p class="rev-sub">&#8369;<?php echo number_format($month_expenses, 2); ?> this month</p>
            </div>
            <div class="rev-divider">=</div>
            <div class="rev-card rev-net <?php echo $net_revenue >= 0 ? 'rev-positive' : 'rev-negative'; ?>">
                <p class="rev-label">Net Revenue</p>
                <h2 class="rev-amount">&#8369;<?php echo number_format(abs($net_revenue), 2); ?></h2>
                <p class="rev-sub"><?php echo $net_revenue >= 0 ? 'Profit' : 'Net Loss'; ?></p>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════
             STAT CARDS
             Row 1: Total Orders | Total Bookings | Active Suppliers
             Row 2: Pending Orders | Pending Bookings | Out of Stock
             ══════════════════════════════════════════════════ -->
        <div class="admin-stats" style="margin-bottom:24px;">

            <div class="stat-card" style="border-left:4px solid #1a7f44;">
                <p class="stat-label">Total Orders</p>
                <h2 class="stat-number" style="color:#1a7f44;"><?php echo $total_orders; ?></h2>
            </div>

            <div class="stat-card" style="border-left:4px solid #0891b2;">
                <p class="stat-label">Total Bookings</p>
                <h2 class="stat-number" style="color:#0891b2;"><?php echo $total_appointments; ?></h2>
            </div>

            <div class="stat-card" style="border-left:4px solid #16a34a;">
                <p class="stat-label">Active Suppliers</p>
                <h2 class="stat-number" style="color:#16a34a;"><?php echo $total_suppliers; ?></h2>
            </div>

            <div class="stat-card <?php echo $pending_orders > 0 ? 'stat-alert' : ''; ?>"
                 style="border-left:4px solid #d97706;">
                <p class="stat-label">&#9888; Pending Orders</p>
                <h2 class="stat-number" style="color:#d97706;"><?php echo $pending_orders; ?></h2>
                <?php if ($pending_orders > 0): ?>
                    <a href="admin-orders.php?status=PENDING" class="stat-action-link">Process now &#8594;</a>
                <?php endif; ?>
            </div>

            <div class="stat-card <?php echo $pending_appts > 0 ? 'stat-alert' : ''; ?>"
                 style="border-left:4px solid #dc2626;">
                <p class="stat-label">&#9888; Pending Bookings</p>
                <h2 class="stat-number" style="color:#dc2626;"><?php echo $pending_appts; ?></h2>
                <?php if ($pending_appts > 0): ?>
                    <a href="admin-appointments.php?status=PENDING" class="stat-action-link">Review now &#8594;</a>
                <?php endif; ?>
            </div>

            <div class="stat-card <?php echo $out_of_stock > 0 ? 'stat-alert' : ''; ?>"
                 style="border-left:4px solid #f59e0b;">
                <p class="stat-label">Out of Stock</p>
                <h2 class="stat-number" style="color:#f59e0b;"><?php echo $out_of_stock; ?></h2>
                <?php if ($out_of_stock > 0): ?>
                    <a href="admin-products.php" class="stat-action-link">View products &#8594;</a>
                <?php endif; ?>
            </div>

        </div>

        <!-- ══════════════════════════════════════════════════
             THREE-COLUMN: Products by Category | Bookings by Status | Technicians
             ══════════════════════════════════════════════════ -->
        <div class="dash-three-col">

            <!-- Fix #1: Products by Category — alphabetical, "All" row at top -->
            <div class="admin-section-card">
                <div class="admin-table-header">
                    <h2>Products by Category</h2>
                    <a href="admin-products.php" class="admin-see-all">Manage</a>
                </div>
                <table class="admin-table" style="margin-top:10px;">
                    <thead>
                        <tr><th>Category</th><th>Total</th><th>OOS</th></tr>
                    </thead>
                    <tbody>
                        <!-- "All" pinned at top — grand total row -->
                        <tr style="background:#f8fafc; font-weight:700;">
                            <td style="color:#1a1a2e;">All</td>
                            <td style="color:#1a1a2e;"><?php echo $total_products_all; ?></td>
                            <td>
                                <?php if ($total_oos_all > 0): ?>
                                    <span style="color:#dc2626; font-weight:700;"><?php echo $total_oos_all; ?></span>
                                <?php else: ?>
                                    <span style="color:#16a34a;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php while ($cat = mysqli_fetch_assoc($products_by_cat)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                            <td><?php echo $cat['total']; ?></td>
                            <td>
                                <?php if ($cat['oos'] > 0): ?>
                                    <span style="color:#dc2626; font-weight:600;"><?php echo $cat['oos']; ?></span>
                                <?php else: ?>
                                    <span style="color:#16a34a;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bookings by Status -->
            <div class="admin-section-card">
                <div class="admin-table-header">
                    <h2>Bookings by Status</h2>
                    <a href="admin-appointments.php" class="admin-see-all">View All</a>
                </div>
                <div style="margin-top:12px;">
                    <?php
                    $status_colors = [
                        'PENDING'   => '#f59e0b',
                        'CONFIRMED' => '#0049af',
                        'ONGOING'   => '#7c3aed',
                        'COMPLETED' => '#16a34a',
                        'CANCELLED' => '#dc2626',
                    ];
                    foreach ($appt_by_status as $st => $count):
                        $color = $status_colors[$st] ?? '#888';
                    ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                        <span style="font-size:13px; font-weight:500; color:#333;"><?php echo $st; ?></span>
                        <span style="background:<?php echo $color; ?>; color:#fff; font-size:12px; font-weight:700;
                                     padding:2px 10px; border-radius:12px;">
                            <?php echo $count; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Technicians -->
            <div class="admin-section-card">
                <div class="admin-table-header">
                    <h2>Technicians</h2>
                    <a href="admin-technicians.php" class="admin-see-all">View All</a>
                </div>
                <div style="margin-top:12px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                        <span style="font-size:13px;">Total</span>
                        <strong><?php echo $tech_total; ?></strong>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                        <span style="font-size:13px;">Available</span>
                        <span style="background:#16a34a; color:#fff; font-size:12px; font-weight:700; padding:2px 10px; border-radius:12px;"><?php echo $tech_available; ?></span>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                        <span style="font-size:13px;">Busy</span>
                        <span style="background:#7c3aed; color:#fff; font-size:12px; font-weight:700; padding:2px 10px; border-radius:12px;"><?php echo $tech_busy; ?></span>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0;">
                        <span style="font-size:13px;">Off Duty</span>
                        <span style="background:#9ca3af; color:#fff; font-size:12px; font-weight:700; padding:2px 10px; border-radius:12px;"><?php echo $tech_offduty; ?></span>
                    </div>
                    <a href="admin-technicians.php" class="stat-action-link" style="display:block; margin-top:12px;">Manage technicians &#8594;</a>
                </div>
            </div>

        </div>

      
        <!-- BOOKING ALERTS + TOP SELLERS + LOW STOCK — three columns -->
<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:24px;">

    <!-- Booking Alert Card -->
    <div class="booking-alert-card">
        <div class="booking-alert-header">
            <h2>
                Upcoming Booking Alerts
                <small style="font-size:11px; color:#6b7280; font-weight:400; margin-left:6px;">(Today &rarr; Next 7 Days)</small>
            </h2>
            <a href="admin-appointments.php" class="admin-see-all">View All</a>
        </div>
        <?php if (!empty($booking_alerts_arr)): ?>
        <div class="booking-alert-list">
            <?php foreach ($booking_alerts_arr as $ba):
                $appt_date     = strtotime($ba['appointment_date']);
                $is_today      = date('Y-m-d', $appt_date) === date('Y-m-d');
                $is_tomorrow   = date('Y-m-d', $appt_date) === date('Y-m-d', strtotime('tomorrow'));
                $urgency_color = $is_today ? '#dc2626' : ($is_tomorrow ? '#d97706' : '#0049af');
                $urgency_label = $is_today ? 'TODAY' : ($is_tomorrow ? 'TOMORROW' : date('M j', $appt_date));
                $cust_name     = trim(($ba['first_name'] ?? '') . ' ' . ($ba['last_name'] ?? '')) ?: $ba['username'];
            ?>
            <div class="booking-alert-item">
                <span style="background:<?php echo $urgency_color; ?>; color:#fff; font-size:11px; font-weight:700;
                             padding:2px 8px; border-radius:10px; flex-shrink:0; margin-top:2px; white-space:nowrap;">
                    <?php echo $urgency_label; ?>
                </span>
                <div style="flex:1; min-width:0;">
                    <p style="margin:0; font-weight:600; font-size:13px; color:#1e3a8a;
                              white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?php echo htmlspecialchars($cust_name); ?>
                    </p>
                    <p style="margin:0; font-size:12px; color:#4b5563;">
                        <?php echo htmlspecialchars($ba['service_name']); ?> &mdash; <?php echo date('g:i A', $appt_date); ?>
                    </p>
                    <p style="margin:0; font-size:11px; color:#9ca3af;">
                        Tech: <?php echo $ba['tech_name']
                            ? htmlspecialchars($ba['tech_name'])
                            : '<span style="color:#f59e0b; font-weight:600;">Unassigned</span>'; ?>
                        &nbsp;|&nbsp;
                        <span class="status-badge <?php echo strtolower($ba['status']); ?>"
                              style="font-size:10px; padding:1px 6px;"><?php echo $ba['status']; ?></span>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p style="padding:16px 20px; color:#9ca3af; font-size:13px; margin:0;">
                No upcoming bookings in the next 7 days.
            </p>
        <?php endif; ?>
    </div>

    <!-- Top 10 Best Sellers -->
    <div class="admin-section-card" style="border-left:4px solid #0049af; padding:0; overflow:hidden;">
        <div class="booking-alert-header" style="border-bottom:1px solid #dbeafe;">
            <h2 style="color:#1e3a8a;">Top 10 Best-Selling Products</h2>
            <a href="admin-products.php" class="admin-see-all">View All</a>
        </div>
        <?php
        $rank = 0;
        if (mysqli_num_rows($top_sellers) > 0):
            while ($ts = mysqli_fetch_assoc($top_sellers)):
                $rank++;
        ?>
        <div style="display:flex; align-items:center; gap:10px; padding:9px 16px;
                    border-bottom:1px solid #f0f4ff;">
            <span style="font-size:12px; font-weight:700; color:<?php echo $rank <= 3 ? '#0049af' : '#9ca3af'; ?>;
                         width:18px; flex-shrink:0;"><?php echo $rank; ?></span>
            <img src="<?php echo htmlspecialchars($ts['image']); ?>" alt=""
                 style="width:34px; height:34px; object-fit:contain; border-radius:5px; flex-shrink:0;">
            <div style="flex:1; min-width:0;">
                <p style="margin:0; font-size:12px; font-weight:600; white-space:nowrap;
                           overflow:hidden; text-overflow:ellipsis; color:#1a1a2e;">
                    <?php echo htmlspecialchars($ts['product_name']); ?>
                </p>
                <p style="margin:0; font-size:11px; color:#6b7280;">
                    &#8369;<?php echo number_format($ts['price'], 2); ?>
                </p>
            </div>
            <div style="text-align:right; flex-shrink:0;">
                <p style="margin:0; font-size:13px; font-weight:700; color:#0049af;">
                    <?php echo number_format($ts['total_sold']); ?>
                </p>
                <p style="margin:0; font-size:10px; color:#9ca3af;">sold</p>
            </div>
        </div>
        <?php endwhile; else: ?>
        <p style="padding:16px; color:#9ca3af; font-size:13px;">No sales data yet.</p>
        <?php endif; ?>
    </div>

    <!-- Low Stock Alert -->
    <div class="low-stock-sidebar">
        <div class="low-stock-sidebar-header">
            <h2>&#9888; Low Stock Alert</h2>
            <a href="admin-products.php" class="admin-see-all">Manage</a>
        </div>
        <?php if ($low_stock_count > 0): ?>
            <div class="low-stock-sidebar-list">
                <?php while ($item = mysqli_fetch_assoc($low_stock)): ?>
                <div class="low-stock-item">
                    <img src="<?php echo $item['image']; ?>" alt=""
                         style="width:32px;height:32px;object-fit:cover;border-radius:5px;flex-shrink:0;">
                    <span class="low-stock-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                    <span class="low-stock-qty low">
                        <?php echo $item['stock']; ?> left
                        <small style="display:block;font-size:10px;color:#b45309;">min: <?php echo $item['low_stock_threshold']; ?></small>
                    </span>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="low-stock-empty">All products have sufficient stock.</p>
        <?php endif; ?>
    </div>

</div>
        <!-- ══════════════════════════════════════════════════
             MAIN BOTTOM GRID
             LEFT  65%: Recent Orders (top) + Recent Bookings (bottom) — Fix #4: equal flex height
             RIGHT 35%: Log Expense + Admin Activity + User Activity — Fix #3: scrollable, capped
             ══════════════════════════════════════════════════ -->
        <div class="dash-bottom-grid">

            <!-- LEFT: stacked tables, equal height -->
            <div class="dash-bottom-left">

                <!-- Recent Orders -->
                <div class="admin-table-section">
                    <div class="admin-table-header">
                        <h2>Recent Orders</h2>
                        <a href="admin-orders.php" class="admin-see-all">See All</a>
                    </div>
                    <table class="admin-table">
                        <thead>
                            <tr><th>Order ID</th><th>User</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recent_orders) > 0):
                                  while ($order = mysqli_fetch_assoc($recent_orders)): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['username']); ?></td>
                                <td>&#8369;<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><span class="status-badge <?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                                <td><?php echo date("M j, Y", strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="no-data">No orders yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Bookings — same .admin-table-section styling -->
                <div class="admin-table-section">
                    <div class="admin-table-header">
                        <h2>Recent Bookings</h2>
                        <a href="admin-appointments.php" class="admin-see-all">See All</a>
                    </div>
                    <table class="admin-table">
                        <thead>
                            <tr><th>Booking ID</th><th>User</th><th>Service</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recent_bookings) > 0):
                                  while ($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                            <tr>
                                <td>#<?php echo $booking['appointment_id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                <td><span class="status-badge <?php echo strtolower($booking['status']); ?>"><?php echo $booking['status']; ?></span></td>
                                <td><?php echo date("M j, Y", strtotime($booking['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="no-data">No bookings yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- RIGHT: Expense form + Fix #3 scrollable activity cards -->
            <div class="dash-bottom-right">

                <!-- Log an Expense -->
                <div class="admin-section-card" style="margin-bottom:0;">
                    <div class="admin-table-header">
                        <h2>&#x1F4B8; Log an Expense</h2>
                        <a href="admin-expenses.php" class="admin-see-all">View All</a>
                    </div>
                    <form method="POST" action="admin-dashboard.php" class="expense-quick-form">
                        <input type="hidden" name="action" value="add_expense">
                        <div class="expense-form-row">
                            <select name="category" required>
                                <option value="">Category</option>
                                <option value="Restocking">Restocking</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Salaries">Salaries</option>
                                <option value="Rent">Rent</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="text"   name="description"  placeholder="Description" required>
                            <input type="number" name="amount"        placeholder="Amount" step="0.01" min="1" required>
                            <input type="date"   name="expense_date"  value="<?php echo date('Y-m-d'); ?>" required>
                            <button type="submit" class="admin-btn-primary">Add</button>
                        </div>
                    </form>
                    <?php if (mysqli_num_rows($recent_expenses) > 0): ?>
                    <table class="admin-table" style="margin-top:14px;">
                        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php while ($exp = mysqli_fetch_assoc($recent_expenses)): ?>
                            <tr>
                                <td><?php echo date("M j, Y", strtotime($exp['expense_date'])); ?></td>
                                <td><span class="status-badge pending"><?php echo $exp['category']; ?></span></td>
                                <td><?php echo htmlspecialchars($exp['description']); ?></td>
                                <td>&#8369;<?php echo number_format($exp['amount'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <?php
// Fetch ALL admin logs for modal
$admin_logs_all = mysqli_query($conn,
    "SELECT * FROM audit_logs WHERE actor_type='ADMIN' ORDER BY created_at DESC LIMIT 100"
);
$admin_logs_all_arr = [];
while ($r = mysqli_fetch_assoc($admin_logs_all)) $admin_logs_all_arr[] = $r;

// Fetch ALL user logs for modal
$user_logs_all = mysqli_query($conn,
    "SELECT * FROM audit_logs
     WHERE actor_type='USER'
       AND action IN ('Placed Order','Booked Appointment','Cancelled Order','Cancelled Appointment')
     ORDER BY created_at DESC LIMIT 100"
);
$user_logs_all_arr = [];
while ($r = mysqli_fetch_assoc($user_logs_all)) $user_logs_all_arr[] = $r;
?>

<!-- Admin Activity card -->
<div class="activity-card">
    <div class="admin-table-header" style="margin-bottom:10px;">
        <h2>Admin Activity</h2>
        <button type="button" class="admin-see-all" style="background:none;border:none;cursor:pointer;padding:0;"
                onclick="document.getElementById('adminActivityModal').style.display='flex'">See All</button>
    </div>
    <div class="audit-log-list">
        <?php foreach (array_slice($admin_logs_all_arr, 0, 15) as $log): ?>
        <div class="audit-log-item">
            <div class="audit-log-top">
                <span class="audit-action"><?php echo htmlspecialchars($log['action']); ?></span>
                <span class="audit-time"><?php echo date("M j, g:i A", strtotime($log['created_at'])); ?></span>
            </div>
            <div class="audit-log-desc">
                <span class="audit-actor"><?php echo htmlspecialchars($log['actor_name']); ?></span> &#8212;
                <?php echo htmlspecialchars($log['description']); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- User Activity card -->
<div class="activity-card">
    <div class="admin-table-header" style="margin-bottom:10px;">
        <h2>User Activity</h2>
        <button type="button" class="admin-see-all" style="background:none;border:none;cursor:pointer;padding:0;"
                onclick="document.getElementById('userActivityModal').style.display='flex'">See All</button>
    </div>
    <div class="audit-log-list">
        <?php foreach (array_slice($user_logs_all_arr, 0, 15) as $log): ?>
        <div class="audit-log-item">
            <div class="audit-log-top">
                <span class="audit-action"><?php echo htmlspecialchars($log['action']); ?></span>
                <span class="audit-time"><?php echo date("M j, g:i A", strtotime($log['created_at'])); ?></span>
            </div>
            <div class="audit-log-desc">
                <span class="audit-actor"><?php echo htmlspecialchars($log['actor_name']); ?></span> &#8212;
                <?php echo htmlspecialchars($log['description']); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

            </div>
        </div>

    </main>
</div>
<!-- ADMIN ACTIVITY MODAL -->
<div class="modal" id="adminActivityModal">
    <div class="modal-content" style="max-width:640px; max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <h2>Admin Activity — Full Log</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('adminActivityModal').style.display='none'">&times;</button>
        </div>
        <div style="overflow-y:auto; flex:1; padding-bottom:8px;">
            <div class="audit-log-list" style="max-height:none;">
                <?php foreach ($admin_logs_all_arr as $log): ?>
                <div class="audit-log-item">
                    <div class="audit-log-top">
                        <span class="audit-action"><?php echo htmlspecialchars($log['action']); ?></span>
                        <span class="audit-time"><?php echo date("M j, Y g:i A", strtotime($log['created_at'])); ?></span>
                    </div>
                    <div class="audit-log-desc">
                        <span class="audit-actor"><?php echo htmlspecialchars($log['actor_name']); ?></span> &#8212;
                        <?php echo htmlspecialchars($log['description']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($admin_logs_all_arr)): ?>
                    <p style="color:#aaa; padding:16px;">No admin activity found.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-primary"
                onclick="document.getElementById('adminActivityModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- USER ACTIVITY MODAL -->
<div class="modal" id="userActivityModal">
    <div class="modal-content" style="max-width:640px; max-height:80vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <h2>User Activity — Full Log</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('userActivityModal').style.display='none'">&times;</button>
        </div>
        <div style="overflow-y:auto; flex:1; padding-bottom:8px;">
            <div class="audit-log-list" style="max-height:none;">
                <?php foreach ($user_logs_all_arr as $log): ?>
                <div class="audit-log-item">
                    <div class="audit-log-top">
                        <span class="audit-action"><?php echo htmlspecialchars($log['action']); ?></span>
                        <span class="audit-time"><?php echo date("M j, Y g:i A", strtotime($log['created_at'])); ?></span>
                    </div>
                    <div class="audit-log-desc">
                        <span class="audit-actor"><?php echo htmlspecialchars($log['actor_name']); ?></span> &#8212;
                        <?php echo htmlspecialchars($log['description']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($user_logs_all_arr)): ?>
                    <p style="color:#aaa; padding:16px;">No user activity found.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-primary"
                onclick="document.getElementById('userActivityModal').style.display='none'">Close</button>
        </div>
    </div>
</div>
<script src="javascript.js"></script>
</body>
</html>