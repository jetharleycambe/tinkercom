<?php
session_start();
include 'db.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

// Filters
$usr_role   = isset($_GET['role'])   ? $_GET['role']   : '';
$usr_sort   = isset($_GET['sort'])   ? $_GET['sort']   : 'newest';
$usr_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$usr_where = "WHERE 1=1";
if ($usr_role === 'ADMIN') {
    $usr_where .= " AND roles.role_name = 'ADMIN'";
} elseif ($usr_role === 'USER') {
    $usr_where .= " AND users.user_id NOT IN (
        SELECT ur2.user_id FROM user_roles ur2
        JOIN roles r2 ON ur2.role_id = r2.role_id
        WHERE r2.role_name = 'ADMIN'
    )";
}
if ($usr_search) {
    $s = mysqli_real_escape_string($conn, $usr_search);
    $usr_where .= " AND (users.username LIKE '%$s%' OR users.email LIKE '%$s%')";
}

$usr_sort_clause = match($usr_sort) {
    'oldest'   => 'users.created_at ASC',
    'name_asc' => 'users.username ASC',
    'name_desc'=> 'users.username DESC',
    default    => 'users.created_at DESC',
};

$users = mysqli_query($conn,
    "SELECT users.*, roles.role_name
     FROM users
     LEFT JOIN user_roles ON users.user_id = user_roles.user_id
     LEFT JOIN roles ON user_roles.role_id = roles.role_id
     $usr_where
     ORDER BY $usr_sort_clause"
);

// Count for badges
$total_admins   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM user_roles ur JOIN roles r ON ur.role_id = r.role_id WHERE r.role_name='ADMIN'"))['c'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM users WHERE user_id NOT IN (
        SELECT ur.user_id FROM user_roles ur
        JOIN roles r ON ur.role_id = r.role_id
        WHERE r.role_name = 'ADMIN'
    )"))['c'];

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <div>
                <h1>Users</h1>
                <p class="topbar-sub">View and manage registered accounts</p>
            </div>
        </div>

        <?php if ($success === 'deleted'): ?>
            <div class="alert alert-success">User deleted successfully.</div>
        <?php elseif ($success === 'error'): ?>
            <div class="alert alert-error">Cannot delete your own account.</div>
        <?php endif; ?>

        <div class="admin-table-section">
            <!-- Role Tabs -->
<div class="appt-tabs" style="margin-bottom:0; border-radius:8px 8px 0 0;">
    <a href="admin-users.php<?php echo ($usr_sort!=='newest'||$usr_search)?'?'.http_build_query(array_filter(['sort'=>$usr_sort,'search'=>$usr_search])):''; ?>"
       class="appt-tab <?php echo $usr_role==='' ? 'active' : ''; ?>">
        All <span class="tab-count"><?php echo $total_admins + $total_customers; ?></span>
    </a>
    <a href="admin-users.php?role=ADMIN<?php echo $usr_search?'&search='.urlencode($usr_search):''; ?>"
       class="appt-tab appt-tab-confirmed <?php echo $usr_role==='ADMIN' ? 'active' : ''; ?>">
        Admins <span class="tab-count"><?php echo $total_admins; ?></span>
    </a>
    <a href="admin-users.php?role=USER<?php echo $usr_search?'&search='.urlencode($usr_search):''; ?>"
       class="appt-tab appt-tab-pending <?php echo $usr_role==='USER' ? 'active' : ''; ?>">
        Customers <span class="tab-count"><?php echo $total_customers; ?></span>
    </a>
</div>

<div class="admin-table-section" style="border-radius:0 8px 8px 8px;">
    <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
        <h2><?php echo $usr_role ? ($usr_role==='ADMIN' ? 'Admins' : 'Customers') : 'All Users'; ?></h2>
        <form method="GET" action="admin-users.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($usr_role): ?><input type="hidden" name="role" value="<?php echo $usr_role; ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search by username or email..."
                   value="<?php echo htmlspecialchars($usr_search); ?>"
                   style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; width:220px;">
            <select name="sort" onchange="this.form.submit()"
                    style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                <option value="newest"    <?php echo $usr_sort==='newest'    ?'selected':''; ?>>Newest First</option>
                <option value="oldest"    <?php echo $usr_sort==='oldest'    ?'selected':''; ?>>Oldest First</option>
                <option value="name_asc"  <?php echo $usr_sort==='name_asc'  ?'selected':''; ?>>Name A–Z</option>
                <option value="name_desc" <?php echo $usr_sort==='name_desc' ?'selected':''; ?>>Name Z–A</option>
            </select>
            <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
            <?php if ($usr_search || $usr_role): ?>
                <a href="admin-users.php" style="font-size:12px; color:#e53935;">Clear</a>
            <?php endif; ?>
        </form>
    </div>
            <table class="admin-table" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($users)): ?>
                    <tr>
                        <td>#<?php echo $row['user_id']; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['email'] ?? '—'; ?></td>
                        <td><?php echo $row['phone'] ?? '—'; ?></td>
                        <td>
                            <span class="status-badge <?php echo $row['role_name'] === 'ADMIN' ? 'confirmed' : 'pending'; ?>">
                                <?php echo $row['role_name'] ?? 'USER'; ?>
                            </span>
                        </td>
                        <td><?php echo date("M j, Y", strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if ($row['user_id'] != $_SESSION["customer_id"]): ?>
                                <button class="btn-delete" onclick="confirmDelete(<?php echo $row['user_id']; ?>, '<?php echo addslashes($row['username']); ?>')">Delete</button>
                            <?php else: ?>
                                <span style="font-size:12px; color:#aaa;">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Delete User</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('deleteModal').style.display='none'">&times;</button>
        </div>
        <p style="font-size:14px; color:#555; margin: 8px 0 20px 0;">
            Are you sure you want to delete <strong id="deleteUsername"></strong>? This cannot be undone.
        </p>
        <form action="admin-user-save.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="delete_user_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-delete-confirm">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('delete_user_id').value      = id;
    document.getElementById('deleteUsername').textContent = name;
    document.getElementById('deleteModal').style.display  = 'flex';
}

</script>

</body>
</html>