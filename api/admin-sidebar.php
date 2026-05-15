<?php

$current_page = basename($_SERVER['PHP_SELF']);

// Pending counts for badges (conn already available from db.php)
$pend_orders = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='PENDING'")
)['c'];
$pend_appts = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE status='PENDING'")
)['c'];

// Low stock count
$low_stock_count = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE stock > 0 AND stock <= low_stock_threshold")
)['c'];
?>

<aside class="admin-sidebar">
    <div class="admin-logo">
        <img src="assets/tinkercom-logoe.png" alt="Tinkercom">
        <h3>Tinkercom</h3>
    </div>

    <nav class="admin-nav">

        <p class="nav-section-label">MAIN</p>
        <a href="admin-dashboard.php"
           class="admin-nav-link <?php echo $current_page === 'admin-dashboard.php' ? 'active' : ''; ?>">
             Dashboard
            <?php if ($low_stock_count > 0): ?>
                <span class="nav-badge" style="background:#f59e0b;">
                    <?php echo $low_stock_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <p class="nav-section-label">CATALOG</p>
        <a href="admin-products.php"
           class="admin-nav-link <?php echo $current_page === 'admin-products.php' ? 'active' : ''; ?>">
             Products
        </a>
        <a href="admin-categories.php"
           class="admin-nav-link <?php echo $current_page === 'admin-categories.php' ? 'active' : ''; ?>">
             Categories
        </a>
        <a href="admin-services.php"
           class="admin-nav-link <?php echo $current_page === 'admin-services.php' ? 'active' : ''; ?>">
             Services
        </a>

        <p class="nav-section-label">TRANSACTIONS</p>
        <a href="admin-orders.php"
           class="admin-nav-link <?php echo $current_page === 'admin-orders.php' ? 'active' : ''; ?>">
             Orders
            <?php if ($pend_orders > 0): ?>
                <span class="nav-badge"><?php echo $pend_orders; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin-appointments.php"
           class="admin-nav-link <?php echo $current_page === 'admin-appointments.php' ? 'active' : ''; ?>">
             Bookings
            <?php if ($pend_appts > 0): ?>
                <span class="nav-badge"><?php echo $pend_appts; ?></span>
            <?php endif; ?>
        </a>

        <p class="nav-section-label">BUSINESS</p>
        <a href="admin-suppliers.php"
           class="admin-nav-link <?php echo $current_page === 'admin-suppliers.php' ? 'active' : ''; ?>">
             Suppliers
        </a>
        <a href="admin-expenses.php"
           class="admin-nav-link <?php echo $current_page === 'admin-expenses.php' ? 'active' : ''; ?>">
             Expenses
        </a>
        <a href="admin-discounts.php"
           class="admin-nav-link <?php echo $current_page === 'admin-discounts.php' ? 'active' : ''; ?>">
             Discounts
        </a>

        <p class="nav-section-label">ACCOUNTS</p>
        <a href="admin-users.php"
           class="admin-nav-link <?php echo $current_page === 'admin-users.php' ? 'active' : ''; ?>">
            Users
        </a>
        <a href="admin-technicians.php"
           class="admin-nav-link <?php echo $current_page === 'admin-technicians.php' ? 'active' : ''; ?>">
            Technicians
        </a>

    </nav>

    <div class="admin-sidebar-footer">
        <div class="admin-user-info">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION["customer_name"], 0, 1)); ?>
            </div>
            <div>
                <p class="admin-username"><?php echo htmlspecialchars($_SESSION["customer_name"]); ?></p>
                <p class="admin-role-label">Administrator</p>
            </div>
        </div>
        <a href="logout.php" class="admin-logout">Logout</a>
    </div>
</aside>