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
    $category    = mysqli_real_escape_string($conn, $_POST['category']     ?? 'Other');
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $amount      = floatval($_POST['amount'] ?? 0);
    $date        = mysqli_real_escape_string($conn, $_POST['expense_date'] ?? date('Y-m-d'));
    $added_by    = intval($_SESSION['customer_id']);

    if ($description !== '' && $amount > 0) {
        mysqli_query($conn,
            "INSERT INTO expenses (category, description, amount, expense_date, added_by)
             VALUES ('$category', '$description', $amount, '$date', $added_by)"
        );
        log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
            'Added Expense', "Added ₱" . number_format($amount, 2) . " — $category: $description");
    }
    header('Location: admin-expenses.php?success=added');
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id          = intval($_POST['expense_id']);
    $category    = mysqli_real_escape_string($conn, $_POST['category']     ?? 'Other');
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $amount      = floatval($_POST['amount'] ?? 0);
    $date        = mysqli_real_escape_string($conn, $_POST['expense_date'] ?? date('Y-m-d'));

    mysqli_query($conn,
        "UPDATE expenses SET
            category     = '$category',
            description  = '$description',
            amount       = $amount,
            expense_date = '$date'
         WHERE expense_id = $id"
    );
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Edited Expense', "Updated expense ID: $id — $category: $description");
    header('Location: admin-expenses.php?success=edited');
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['expense_id']);
    mysqli_query($conn, "DELETE FROM expenses WHERE expense_id = $id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'],
        'Deleted Expense', "Deleted expense ID: $id");
    header('Location: admin-expenses.php?success=deleted');
    exit;
}

// ── FILTERS ───────────────────────────────────────────────────
$filter_cat   = isset($_GET['cat'])   ? $_GET['cat']              : '';
$filter_month = isset($_GET['month']) ? $_GET['month']            : '';
$filter_sort  = isset($_GET['sort'])  ? $_GET['sort']             : 'newest';
$filter_search = isset($_GET['search']) ? trim($_GET['search'])   : '';

$sort_map = [
    'newest'      => 'expense_date DESC, created_at DESC',
    'oldest'      => 'expense_date ASC,  created_at ASC',
    'amount_desc' => 'amount DESC',
    'amount_asc'  => 'amount ASC',
];
$sort_clause = $sort_map[$filter_sort] ?? 'expense_date DESC, created_at DESC';

$where = "WHERE 1=1";
if ($filter_cat !== '')   $where .= " AND category = '" . mysqli_real_escape_string($conn, $filter_cat) . "'";
if ($filter_month !== '') $where .= " AND DATE_FORMAT(expense_date, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filter_month) . "'";
if ($filter_search !== '') {
    $s = mysqli_real_escape_string($conn, $filter_search);
    $where .= " AND description LIKE '%$s%'";
}

$expenses = mysqli_query($conn,
    "SELECT * FROM expenses $where ORDER BY $sort_clause"
);

// ── SUMMARY STATS ─────────────────────────────────────────────
$total_all    = floatval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) t FROM expenses"))['t']);
$total_month  = floatval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount),0) t FROM expenses
     WHERE MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())"))['t']);
$total_year   = floatval(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount),0) t FROM expenses
     WHERE YEAR(expense_date)=YEAR(CURDATE())"))['t']);
$count_all    = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM expenses"))['c']);

// Per-category totals for breakdown
$cat_totals_res = mysqli_query($conn,
    "SELECT category, COUNT(*) as cnt, SUM(amount) as total
     FROM expenses GROUP BY category ORDER BY total DESC"
);
$cat_totals = [];
while ($r = mysqli_fetch_assoc($cat_totals_res)) $cat_totals[] = $r;

$cat_colors = [
    'Restocking' => '#0049af',
    'Utilities'  => '#7c3aed',
    'Salaries'   => '#16a34a',
    'Rent'       => '#dc2626',
    'Equipment'  => '#d97706',
    'Other'      => '#6b7280',
];

$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses | Tinkercom Admin</title>
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
                <h1>Expenses</h1>
                <p class="topbar-sub">Track and manage all business expenses</p>
            </div>
            <button class="btn-primary" id="openAddModal">+ Log Expense</button>
        </div>

        <!-- Alerts -->
        <?php if ($success === 'added'): ?>
            <div class="alert alert-success">Expense logged successfully.</div>
        <?php elseif ($success === 'edited'): ?>
            <div class="alert alert-success">Expense updated successfully.</div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">Expense deleted successfully.</div>
        <?php endif; ?>

        <!-- ── SUMMARY STAT CARDS ─────────────────────────────── -->
        <div class="admin-stats" style="margin-bottom:24px; grid-template-columns: repeat(4,1fr);">
            <div class="stat-card" style="border-left:4px solid #0049af;">
                <p class="stat-label">Total Expenses</p>
                <h2 class="stat-number" style="color:#0049af;">&#8369;<?php echo number_format($total_all, 2); ?></h2>
                <p style="font-size:11px; color:#aaa; margin:4px 0 0;"><?php echo $count_all; ?> records</p>
            </div>
            <div class="stat-card" style="border-left:4px solid #7c3aed;">
                <p class="stat-label">This Month</p>
                <h2 class="stat-number" style="color:#7c3aed;">&#8369;<?php echo number_format($total_month, 2); ?></h2>
                <p style="font-size:11px; color:#aaa; margin:4px 0 0;"><?php echo date('F Y'); ?></p>
            </div>
            <div class="stat-card" style="border-left:4px solid #16a34a;">
                <p class="stat-label">This Year</p>
                <h2 class="stat-number" style="color:#16a34a;">&#8369;<?php echo number_format($total_year, 2); ?></h2>
                <p style="font-size:11px; color:#aaa; margin:4px 0 0;"><?php echo date('Y'); ?></p>
            </div>
            <div class="stat-card" style="border-left:4px solid #d97706;">
                <p class="stat-label">Categories</p>
                <h2 class="stat-number" style="color:#d97706;"><?php echo count($cat_totals); ?></h2>
                <p style="font-size:11px; color:#aaa; margin:4px 0 0;">Active expense types</p>
            </div>
        </div>

        <!-- ── CATEGORY BREAKDOWN ROW ────────────────────────── -->
        <?php if (!empty($cat_totals) && $total_all > 0): ?>
        <div class="admin-section-card" style="margin-bottom:24px;">
            <div class="admin-table-header" style="margin-bottom:16px;">
                <h2>Breakdown by Category</h2>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                <?php foreach ($cat_totals as $ct):
                    $pct   = round($ct['total'] / $total_all * 100, 1);
                    $color = $cat_colors[$ct['category']] ?? '#6b7280';
                ?>
                <div style="flex:1; min-width:160px; background:#f8fafc; border-radius:10px; padding:14px 18px;
                            border-left:4px solid <?php echo $color; ?>;">
                    <p style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
                               color:<?php echo $color; ?>; margin:0 0 6px;"><?php echo $ct['category']; ?></p>
                    <p style="font-size:20px; font-weight:700; color:#1a2a3a; margin:0 0 4px;">
                        &#8369;<?php echo number_format($ct['total'], 2); ?>
                    </p>
                    <div style="display:flex; align-items:center; gap:8px; margin-top:8px;">
                        <div style="flex:1; height:4px; background:#e5e7eb; border-radius:4px; overflow:hidden;">
                            <div style="width:<?php echo $pct; ?>%; height:100%; background:<?php echo $color; ?>; border-radius:4px;"></div>
                        </div>
                        <span style="font-size:11px; color:#888; white-space:nowrap;"><?php echo $pct; ?>%</span>
                    </div>
                    <p style="font-size:11px; color:#aaa; margin:4px 0 0;"><?php echo $ct['cnt']; ?> record<?php echo $ct['cnt'] != 1 ? 's' : ''; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── EXPENSE TABLE ──────────────────────────────────── -->
        <div class="admin-table-section">
            <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
                <h2>All Expenses
                    <span style="font-size:13px; font-weight:400; color:#888; margin-left:6px;">
                        (<?php echo mysqli_num_rows($expenses); ?>)
                    </span>
                </h2>
                <form method="GET" action="admin-expenses.php"
                      style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <input type="text" name="search" placeholder="Search description..."
                           value="<?php echo htmlspecialchars($filter_search); ?>"
                           style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; width:190px;">
                    <select name="cat" onchange="this.form.submit()"
                            style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                        <option value="">All Categories</option>
                        <?php foreach (array_keys($cat_colors) as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo $filter_cat === $c ? 'selected' : ''; ?>>
                                <?php echo $c; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>"
                           style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                    <select name="sort" onchange="this.form.submit()"
                            style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                        <option value="newest"      <?php echo $filter_sort==='newest'      ? 'selected':''; ?>>Newest First</option>
                        <option value="oldest"      <?php echo $filter_sort==='oldest'      ? 'selected':''; ?>>Oldest First</option>
                        <option value="amount_desc" <?php echo $filter_sort==='amount_desc' ? 'selected':''; ?>>Amount High–Low</option>
                        <option value="amount_asc"  <?php echo $filter_sort==='amount_asc'  ? 'selected':''; ?>>Amount Low–High</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
                    <?php if ($filter_cat || $filter_month || $filter_search): ?>
                        <a href="admin-expenses.php" style="font-size:12px; color:#e53935;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <table class="admin-table" id="expensesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($expenses) === 0): ?>
                    <tr><td colspan="6" class="no-data">No expenses found.</td></tr>
                <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($expenses)):
                    $color = $cat_colors[$row['category']] ?? '#6b7280';
                ?>
                    <tr>
                        <td>#<?php echo $row['expense_id']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($row['expense_date'])); ?></td>
                        <td>
                            <span class="status-badge"
                                  style="background:<?php echo $color; ?>22; color:<?php echo $color; ?>;">
                                <?php echo $row['category']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td style="font-weight:700; color:#1a2a3a;">
                            &#8369;<?php echo number_format($row['amount'], 2); ?>
                        </td>
                        <td>
                            <button class="btn-edit"
                                onclick="openEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                            <button class="btn-delete"
                                onclick="confirmDelete(<?php echo $row['expense_id']; ?>, '<?php echo addslashes($row['description']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (mysqli_num_rows($expenses) > 0): ?>
            <!-- Running total footer -->
            <?php
            mysqli_data_seek($expenses, 0);
            $running_total = 0;
            while ($r = mysqli_fetch_assoc($expenses)) $running_total += $r['amount'];
            ?>
            <div style="display:flex; justify-content:flex-end; padding:12px 12px 0;
                        border-top:2px solid #f0f0f0; margin-top:8px;">
                <span style="font-size:14px; color:#888; margin-right:16px;">Filtered Total:</span>
                <span style="font-size:16px; font-weight:700; color:#0049af;">
                    &#8369;<?php echo number_format($running_total, 2); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ── ADD MODAL ─────────────────────────────────────────────── -->
<div class="modal" id="addModal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2>Log Expense</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('addModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-expenses.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-grid">
                <div class="modal-field">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Restocking">Restocking</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Salaries">Salaries</option>
                        <option value="Rent">Rent</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Date</label>
                    <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="modal-field modal-field-full">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="e.g. Monthly electricity bill" required>
                </div>
                <div class="modal-field modal-field-full">
                    <label>Amount (&#8369;)</label>
                    <input type="number" name="amount" placeholder="e.g. 3500.00" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT MODAL ────────────────────────────────────────────── -->
<div class="modal" id="editModal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2>Edit Expense</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('editModal').style.display='none'">&times;</button>
        </div>
        <form action="admin-expenses.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="expense_id" id="edit_expense_id">
            <div class="modal-grid">
                <div class="modal-field">
                    <label>Category</label>
                    <select name="category" id="edit_category" required>
                        <option value="Restocking">Restocking</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Salaries">Salaries</option>
                        <option value="Rent">Rent</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Date</label>
                    <input type="date" name="expense_date" id="edit_expense_date" required>
                </div>
                <div class="modal-field modal-field-full">
                    <label>Description</label>
                    <input type="text" name="description" id="edit_description" required>
                </div>
                <div class="modal-field modal-field-full">
                    <label>Amount (&#8369;)</label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Update Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- ── DELETE MODAL ──────────────────────────────────────────── -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Delete Expense</h2>
            <button type="button" class="modal-close"
                onclick="document.getElementById('deleteModal').style.display='none'">&times;</button>
        </div>
        <p style="font-size:14px; color:#555; margin:8px 0 20px;">
            Are you sure you want to delete <strong id="deleteExpenseName"></strong>? This cannot be undone.
        </p>
        <form action="admin-expenses.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="expense_id" id="delete_expense_id">
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

function openEdit(data) {
    document.getElementById('edit_expense_id').value   = data.expense_id;
    document.getElementById('edit_category').value     = data.category;
    document.getElementById('edit_expense_date').value = data.expense_date;
    document.getElementById('edit_description').value  = data.description;
    document.getElementById('edit_amount').value       = data.amount;
    document.getElementById('editModal').style.display = 'flex';
}

function confirmDelete(id, name) {
    document.getElementById('delete_expense_id').value     = id;
    document.getElementById('deleteExpenseName').textContent = name;
    document.getElementById('deleteModal').style.display    = 'flex';
}
</script>

</body>
</html>