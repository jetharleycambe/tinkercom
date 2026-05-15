<?php
include 'db.php';  
session_start();
include 'log_action.php';
include 'session-check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: index.php');
    exit;
}

// ── ADD ───────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $code         = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['code'] ?? '')));
    $description  = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $type         = $_POST['type'] === 'fixed' ? 'fixed' : 'percentage';
    $value        = floatval($_POST['value'] ?? 0);
    $min_order    = floatval($_POST['min_order'] ?? 0);
    $max_discount = $_POST['max_discount'] !== '' ? floatval($_POST['max_discount']) : 'NULL';
    $usage_limit  = $_POST['usage_limit'] !== '' ? intval($_POST['usage_limit'])     : 'NULL';
    $is_active    = intval($_POST['is_active'] ?? 1);
    $start_date   = $_POST['start_date'] !== '' ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : 'NULL';
    $end_date     = $_POST['end_date']   !== '' ? "'" . mysqli_real_escape_string($conn, $_POST['end_date'])   . "'" : 'NULL';

    // Check duplicate code
    $exists = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM discounts WHERE code = '$code'"))['c'];
    if ($exists > 0) {
        header('Location: admin-discounts.php?error=duplicate');
        exit;
    }

    mysqli_query($conn,
        "INSERT INTO discounts (code, description, type, value, min_order, max_discount, is_active, start_date, end_date, usage_limit)
         VALUES ('$code', '$description', '$type', $value, $min_order, $max_discount, $is_active, $start_date, $end_date, $usage_limit)"
    );
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Added Discount', "Added promo code: $code ($type: $value)");
    header('Location: admin-discounts.php?success=added');
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id           = intval($_POST['discount_id']);
    $code         = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['code'] ?? '')));
    $description  = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $type         = $_POST['type'] === 'fixed' ? 'fixed' : 'percentage';
    $value        = floatval($_POST['value'] ?? 0);
    $min_order    = floatval($_POST['min_order'] ?? 0);
    $max_discount = $_POST['max_discount'] !== '' ? floatval($_POST['max_discount']) : 'NULL';
    $usage_limit  = $_POST['usage_limit']  !== '' ? intval($_POST['usage_limit'])    : 'NULL';
    $is_active    = intval($_POST['is_active'] ?? 1);
    $start_date   = $_POST['start_date'] !== '' ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : 'NULL';
    $end_date     = $_POST['end_date']   !== '' ? "'" . mysqli_real_escape_string($conn, $_POST['end_date'])   . "'" : 'NULL';

    // Check duplicate code (exclude self)
    $exists = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM discounts WHERE code = '$code' AND discount_id != $id"))['c'];
    if ($exists > 0) {
        header('Location: admin-discounts.php?error=duplicate');
        exit;
    }

    mysqli_query($conn,
        "UPDATE discounts SET
            code         = '$code',
            description  = '$description',
            type         = '$type',
            value        = $value,
            min_order    = $min_order,
            max_discount = $max_discount,
            is_active    = $is_active,
            start_date   = $start_date,
            end_date     = $end_date,
            usage_limit  = $usage_limit
         WHERE discount_id = $id"
    );
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Edited Discount', "Updated discount ID: $id — $code");
    header('Location: admin-discounts.php?success=edited');
    exit;
}

// ── TOGGLE ACTIVE ─────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $id  = intval($_GET['toggle']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_active, code FROM discounts WHERE discount_id = $id"));
    $new = $row['is_active'] ? 0 : 1;
    mysqli_query($conn, "UPDATE discounts SET is_active = $new WHERE discount_id = $id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Toggled Discount', "Discount {$row['code']} set to " . ($new ? 'Active' : 'Inactive'));
    header('Location: admin-discounts.php');
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['discount_id']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT code FROM discounts WHERE discount_id = $id"));
    mysqli_query($conn, "DELETE FROM discounts WHERE discount_id = $id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Deleted Discount', "Deleted discount: {$row['code']}");
    header('Location: admin-discounts.php?success=deleted');
    exit;
}

// ── FILTERS ───────────────────────────────────────────────────
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type   = isset($_GET['type'])   ? $_GET['type']   : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE 1=1";
if ($filter_status === 'active')   $where .= " AND is_active = 1";
if ($filter_status === 'inactive') $where .= " AND is_active = 0";
if ($filter_status === 'expired')  $where .= " AND end_date IS NOT NULL AND end_date < CURDATE()";
if ($filter_type !== '')           $where .= " AND type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";
if ($filter_search !== '') {
    $s = mysqli_real_escape_string($conn, $filter_search);
    $where .= " AND (code LIKE '%$s%' OR description LIKE '%$s%')";
}

$discounts = mysqli_query($conn, "SELECT * FROM discounts $where ORDER BY created_at DESC");

// ── SUMMARY COUNTS ────────────────────────────────────────────
$cnt_total    = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM discounts"))['c']);
$cnt_active   = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM discounts WHERE is_active=1"))['c']);
$cnt_inactive = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM discounts WHERE is_active=0"))['c']);
$cnt_expired  = intval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM discounts WHERE end_date IS NOT NULL AND end_date < CURDATE()"))['c']);
$total_used   = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(times_used),0) c FROM discounts"))['c']);

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';
$today   = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discounts | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <div>
                <h1>Discounts</h1>
                <p class="topbar-sub">Manage promo codes and discount rules</p>
            </div>
            <button class="btn-primary" id="openAddModal">+ Create Discount</button>
        </div>

        <!-- Alerts -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success">Discount code created successfully.</div>
        <?php elseif ($success === 'edited'): ?>
            <div class="alert alert-success">Discount updated successfully.</div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">Discount deleted successfully.</div>
        <?php elseif ($error === 'duplicate'): ?>
            <div class="alert alert-error">That promo code already exists. Please use a different code.</div>
        <?php endif; ?>

        <!-- ── SUMMARY STAT CARDS ─────────────────────────── -->
        <div class="admin-stats" style="margin-bottom:24px; grid-template-columns:repeat(4,1fr);">
            <div class="stat-card" style="border-left:4px solid #0049af;">
                <p class="stat-label">Total Codes</p>
                <h2 class="stat-number" style="color:#0049af;"><?php echo $cnt_total; ?></h2>
            </div>
            <div class="stat-card" style="border-left:4px solid #16a34a;">
                <p class="stat-label">Active</p>
                <h2 class="stat-number" style="color:#16a34a;"><?php echo $cnt_active; ?></h2>
            </div>
            <div class="stat-card" style="border-left:4px solid #dc2626;">
                <p class="stat-label">Inactive / Expired</p>
                <h2 class="stat-number" style="color:#dc2626;"><?php echo $cnt_inactive + $cnt_expired; ?></h2>
            </div>
            <div class="stat-card" style="border-left:4px solid #7c3aed;">
                <p class="stat-label">Total Times Used</p>
                <h2 class="stat-number" style="color:#7c3aed;"><?php echo $total_used; ?></h2>
            </div>
        </div>

        <!-- ── STATUS TABS + FILTER BAR ──────────────────── -->
        <div class="appt-tabs" style="margin-bottom:0;">
            <?php
            $tab_defs = [
                ''         => ['All', $cnt_total,            ''],
                'active'   => ['Active', $cnt_active,        'appt-tab-confirmed'],
                'inactive' => ['Inactive', $cnt_inactive,    'appt-tab-cancelled'],
                'expired'  => ['Expired', $cnt_expired,      'appt-tab-pending'],
            ];
            foreach ($tab_defs as $val => [$label, $count, $cls]):
                $params = array_filter(['status' => $val, 'type' => $filter_type, 'search' => $filter_search]);
                $href   = 'admin-discounts.php' . ($params ? '?' . http_build_query($params) : '');
            ?>
            <a href="<?php echo $href; ?>"
               class="appt-tab <?php echo $cls; ?> <?php echo $filter_status === $val ? 'active' : ''; ?>">
                <?php echo $label; ?> <span class="tab-count"><?php echo $count; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── DISCOUNTS TABLE ───────────────────────────── -->
        <div class="admin-table-section" style="border-radius:0 8px 8px 8px;">
            <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
                <h2>
                    <?php
                    if ($filter_status === 'active')   echo 'Active Discounts';
                    elseif ($filter_status === 'inactive') echo 'Inactive Discounts';
                    elseif ($filter_status === 'expired')  echo 'Expired Discounts';
                    else echo 'All Discounts';
                    ?>
                    <span style="font-size:13px; font-weight:400; color:#888; margin-left:6px;">
                        (<?php echo mysqli_num_rows($discounts); ?>)
                    </span>
                </h2>
                <form method="GET" action="admin-discounts.php"
                      style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <?php if ($filter_status): ?><input type="hidden" name="status" value="<?php echo $filter_status; ?>"><?php endif; ?>
                    <input type="text" name="search" placeholder="Search code or description..."
                           value="<?php echo htmlspecialchars($filter_search); ?>"
                           style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; width:210px;">
                    <select name="type" onchange="this.form.submit()"
                            style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                        <option value="">All Types</option>
                        <option value="percentage" <?php echo $filter_type==='percentage' ? 'selected':''; ?>>Percentage (%)</option>
                        <option value="fixed"      <?php echo $filter_type==='fixed'      ? 'selected':''; ?>>Fixed Amount (&#8369;)</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
                    <?php if ($filter_search || $filter_type): ?>
                        <a href="admin-discounts.php<?php echo $filter_status ? '?status='.$filter_status : ''; ?>"
                           style="font-size:12px; color:#e53935;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <table class="admin-table" id="discountsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Min. Order</th>
                        <th>Validity</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($discounts) === 0): ?>
                    <tr><td colspan="9" class="no-data">No discounts found.</td></tr>
                <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($discounts)):
                    $is_expired = $row['end_date'] && $row['end_date'] < $today;
                    $usage_pct  = ($row['usage_limit'] > 0)
                        ? round($row['times_used'] / $row['usage_limit'] * 100)
                        : null;
                ?>
                    <tr>
                        <td>
                            <span style="font-family:monospace; font-size:14px; font-weight:700;
                                         background:#f0f5ff; color:#0049af; padding:3px 10px;
                                         border-radius:6px; letter-spacing:1px;">
                                <?php echo htmlspecialchars($row['code']); ?>
                            </span>
                        </td>
                        <td style="font-size:13px; color:#555; max-width:180px;">
                            <?php echo htmlspecialchars($row['description'] ?: '—'); ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $row['type']==='percentage' ? 'confirmed' : 'completed'; ?>">
                                <?php echo $row['type'] === 'percentage' ? '% Off' : 'Fixed'; ?>
                            </span>
                        </td>
                        <td style="font-weight:700; color:#1a2a3a;">
                            <?php if ($row['type'] === 'percentage'): ?>
                                <?php echo $row['value']; ?>%
                                <?php if ($row['max_discount']): ?>
                                    <small style="color:#888; font-weight:400; display:block; font-size:11px;">
                                        max &#8369;<?php echo number_format($row['max_discount'], 2); ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                &#8369;<?php echo number_format($row['value'], 2); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['min_order'] > 0
                                ? '&#8369;' . number_format($row['min_order'], 2)
                                : '<span style="color:#aaa;">None</span>'; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?php if ($row['start_date'] || $row['end_date']): ?>
                                <?php echo $row['start_date'] ? date('M j, Y', strtotime($row['start_date'])) : '—'; ?>
                                &nbsp;→&nbsp;
                                <?php if ($row['end_date']): ?>
                                    <span style="<?php echo $is_expired ? 'color:#dc2626; font-weight:600;' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($row['end_date'])); ?>
                                        <?php if ($is_expired): ?> <span style="font-size:10px;">(expired)</span><?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#aaa;">No end</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#aaa;">No limit</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['usage_limit'] > 0): ?>
                                <span style="font-size:13px; font-weight:600;">
                                    <?php echo $row['times_used']; ?> / <?php echo $row['usage_limit']; ?>
                                </span>
                                <div style="width:80px; height:4px; background:#e5e7eb; border-radius:4px;
                                            margin-top:4px; overflow:hidden;">
                                    <div style="width:<?php echo min($usage_pct, 100); ?>%; height:100%;
                                                background:<?php echo $usage_pct >= 100 ? '#dc2626' : '#0049af'; ?>;
                                                border-radius:4px;"></div>
                                </div>
                            <?php else: ?>
                                <span style="font-size:13px; font-weight:600;"><?php echo $row['times_used']; ?></span>
                                <small style="color:#aaa; display:block; font-size:11px;">Unlimited</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_expired): ?>
                                <span class="status-badge cancelled">Expired</span>
                            <?php elseif ($row['is_active']): ?>
                                <span class="status-badge completed">Active</span>
                            <?php else: ?>
                                <span class="status-badge cancelled">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-edit"
                                onclick="openEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                            <a href="admin-discounts.php?toggle=<?php echo $row['discount_id']; ?>"
                               class="btn-edit"
                               style="<?php echo $row['is_active'] ? 'background:#fef3c7;color:#92400e;' : 'background:#f0fdf4;color:#15803d;'; ?>"
                               onclick="return confirm('<?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?> this discount?')">
                               <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <button class="btn-delete"
                                onclick="confirmDelete(<?php echo $row['discount_id']; ?>, '<?php echo addslashes($row['code']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<!-- ── ADD MODAL ─────────────────────────────────────────────── -->
<div class="modal" id="addModal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h2>Create Discount Code</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('addModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-discounts.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-grid">
                <div class="modal-field">
                    <label>Promo Code <span class="field-hint">(auto-uppercased)</span></label>
                    <input type="text" name="code" placeholder="e.g. SALE10" required
                           style="text-transform:uppercase; letter-spacing:1px; font-family:monospace;">
                </div>
                <div class="modal-field">
                    <label>Discount Type</label>
                    <select name="type" id="add_type" onchange="toggleAddFields(this.value)">
                        <option value="percentage">Percentage (% off)</option>
                        <option value="fixed">Fixed Amount (&#8369; off)</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label id="add_value_label">Discount Value (%)</label>
                    <input type="number" name="value" id="add_value" placeholder="e.g. 10"
                           step="0.01" min="0.01" required>
                </div>
                <div class="modal-field" id="add_max_wrap">
                    <label>Max Discount Cap (&#8369;) <span class="field-hint">optional</span></label>
                    <input type="number" name="max_discount" placeholder="e.g. 200" step="0.01" min="0">
                </div>
                <div class="modal-field modal-field-full">
                    <label>Description <span class="field-hint">optional</span></label>
                    <input type="text" name="description" placeholder="e.g. Summer sale 10% off all orders">
                </div>
                <div class="modal-field">
                    <label>Minimum Order (&#8369;)</label>
                    <input type="number" name="min_order" placeholder="0 = no minimum" step="0.01" min="0" value="0">
                </div>
                <div class="modal-field">
                    <label>Usage Limit <span class="field-hint">blank = unlimited</span></label>
                    <input type="number" name="usage_limit" placeholder="e.g. 100" min="1">
                </div>
                <div class="modal-field">
                    <label>Start Date <span class="field-hint">optional</span></label>
                    <input type="date" name="start_date">
                </div>
                <div class="modal-field">
                    <label>End Date <span class="field-hint">optional</span></label>
                    <input type="date" name="end_date">
                </div>
                <div class="modal-field">
                    <label>Status</label>
                    <select name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Create Discount</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT MODAL ────────────────────────────────────────────── -->
<div class="modal" id="editModal">
    <div class="modal-content modal-wide">
        <div class="modal-header">
            <h2>Edit Discount Code</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('editModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-discounts.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="discount_id" id="edit_discount_id">
            <div class="modal-grid">
                <div class="modal-field">
                    <label>Promo Code</label>
                    <input type="text" name="code" id="edit_code" required
                           style="text-transform:uppercase; letter-spacing:1px; font-family:monospace;">
                </div>
                <div class="modal-field">
                    <label>Discount Type</label>
                    <select name="type" id="edit_type" onchange="toggleEditFields(this.value)">
                        <option value="percentage">Percentage (% off)</option>
                        <option value="fixed">Fixed Amount (&#8369; off)</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label id="edit_value_label">Discount Value (%)</label>
                    <input type="number" name="value" id="edit_value" step="0.01" min="0.01" required>
                </div>
                <div class="modal-field" id="edit_max_wrap">
                    <label>Max Discount Cap (&#8369;) <span class="field-hint">optional</span></label>
                    <input type="number" name="max_discount" id="edit_max_discount" step="0.01" min="0">
                </div>
                <div class="modal-field modal-field-full">
                    <label>Description</label>
                    <input type="text" name="description" id="edit_description">
                </div>
                <div class="modal-field">
                    <label>Minimum Order (&#8369;)</label>
                    <input type="number" name="min_order" id="edit_min_order" step="0.01" min="0">
                </div>
                <div class="modal-field">
                    <label>Usage Limit <span class="field-hint">blank = unlimited</span></label>
                    <input type="number" name="usage_limit" id="edit_usage_limit" min="1">
                </div>
                <div class="modal-field">
                    <label>Start Date</label>
                    <input type="date" name="start_date" id="edit_start_date">
                </div>
                <div class="modal-field">
                    <label>End Date</label>
                    <input type="date" name="end_date" id="edit_end_date">
                </div>
                <div class="modal-field">
                    <label>Status</label>
                    <select name="is_active" id="edit_is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Update Discount</button>
            </div>
        </form>
    </div>
</div>

<!-- ── DELETE MODAL ──────────────────────────────────────────── -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Delete Discount</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('deleteModal').style.display='none'">&times;</button>
        </div>
        <p style="font-size:14px; color:#555; margin:8px 0 20px;">
            Are you sure you want to delete promo code
            <strong id="deleteDiscountCode" style="font-family:monospace; color:#0049af;"></strong>?
            This cannot be undone.
        </p>
        <form action="admin-discounts.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="discount_id" id="delete_discount_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-delete-confirm">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('openAddModal').onclick =
    () => document.getElementById('addModal').style.display = 'flex';

// Toggle max_discount field visibility based on type
function toggleAddFields(type) {
    const maxWrap = document.getElementById('add_max_wrap');
    const label   = document.getElementById('add_value_label');
    if (type === 'percentage') {
        maxWrap.style.display = 'flex';
        label.textContent = 'Discount Value (%)';
    } else {
        maxWrap.style.display = 'none';
        label.textContent = 'Discount Value (₱)';
    }
}

function toggleEditFields(type) {
    const maxWrap = document.getElementById('edit_max_wrap');
    const label   = document.getElementById('edit_value_label');
    if (type === 'percentage') {
        maxWrap.style.display = 'flex';
        label.textContent = 'Discount Value (%)';
    } else {
        maxWrap.style.display = 'none';
        label.textContent = 'Discount Value (₱)';
    }
}

function openEdit(data) {
    document.getElementById('edit_discount_id').value  = data.discount_id;
    document.getElementById('edit_code').value         = data.code;
    document.getElementById('edit_type').value         = data.type;
    document.getElementById('edit_value').value        = data.value;
    document.getElementById('edit_max_discount').value = data.max_discount ?? '';
    document.getElementById('edit_description').value  = data.description  ?? '';
    document.getElementById('edit_min_order').value    = data.min_order    ?? 0;
    document.getElementById('edit_usage_limit').value  = data.usage_limit  ?? '';
    document.getElementById('edit_start_date').value   = data.start_date   ?? '';
    document.getElementById('edit_end_date').value     = data.end_date     ?? '';
    document.getElementById('edit_is_active').value    = data.is_active;
    toggleEditFields(data.type);
    document.getElementById('editModal').style.display = 'flex';
}

function confirmDelete(id, code) {
    document.getElementById('delete_discount_id').value       = id;
    document.getElementById('deleteDiscountCode').textContent = code;
    document.getElementById('deleteModal').style.display      = 'flex';
}
</script>

</body>
</html>