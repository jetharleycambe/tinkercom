<?php
session_start();
include 'db.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

// $services = mysqli_query($conn, "SELECT services.*, COUNT(appointments.appointment_id) as booking_count 
//                                   FROM services 
//                                   LEFT JOIN appointments ON services.service_id = appointments.service_id 
//                                   GROUP BY services.service_id 
//                                   ORDER BY services.service_id DESC");

$success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body class="admin-body">
    <div class="admin-layout">

        <?php include 'admin-sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-topbar">
                <div>
                    <h1>Services</h1>
                    <p class="topbar-sub">Manage repair and maintenance services</p>
                </div>
                <button class="btn-primary" id="openAddModal">+ Add Service</button>
            </div>

            <?php if ($success === 'added'): ?>
                <div class="alert alert-success">Service added successfully.</div>
            <?php elseif ($success === 'edited'): ?>
                <div class="alert alert-success">Service updated successfully.</div>
            <?php elseif ($success === 'deleted'): ?>
                <div class="alert alert-success">Service deleted successfully.</div>
            <?php elseif ($success === 'error'): ?>
                <div class="alert alert-error">Cannot delete a service that has existing appointments.</div>
            <?php endif; ?>

            <?php
$svc_sort   = isset($_GET['sort'])   ? $_GET['sort']   : 'id_desc';
$svc_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$svc_min    = isset($_GET['min'])    && $_GET['min'] !== '' ? floatval($_GET['min']) : '';
$svc_max    = isset($_GET['max'])    && $_GET['max'] !== '' ? floatval($_GET['max']) : '';

$svc_sort_map = [
    'id_desc'      => 'services.service_id DESC',
    'id_asc'       => 'services.service_id ASC',
    'name_asc'     => 'services.service_name ASC',
    'name_desc'    => 'services.service_name DESC',
    'price_asc'    => 'services.price ASC',
    'price_desc'   => 'services.price DESC',
    'duration_asc' => 'services.duration_minutes ASC',
    'duration_desc'=> 'services.duration_minutes DESC',
    'bookings_desc'=> 'booking_count DESC',
    'bookings_asc' => 'booking_count ASC',
];
$svc_sort_clause = $svc_sort_map[$svc_sort] ?? 'services.service_id DESC';

$svc_where = "HAVING 1=1";
if ($svc_search) $svc_where .= " AND services.service_name LIKE '%" . mysqli_real_escape_string($conn, $svc_search) . "%'";
if ($svc_min !== '') $svc_where .= " AND services.price >= $svc_min";
if ($svc_max !== '') $svc_where .= " AND services.price <= $svc_max";

$services = mysqli_query($conn,
    "SELECT services.*, COUNT(appointments.appointment_id) as booking_count
     FROM services
     LEFT JOIN appointments ON services.service_id = appointments.service_id
     GROUP BY services.service_id
     $svc_where
     ORDER BY $svc_sort_clause"
);
?>

<div class="admin-table-section">
    <div class="admin-table-header" style="flex-wrap:wrap; gap:8px;">
        <h2>All Services</h2>
        <form method="GET" action="admin-services.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search service..." value="<?php echo htmlspecialchars($svc_search); ?>"
                   style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px; width:160px;">
            <input type="number" name="min" placeholder="Min ₱" value="<?php echo htmlspecialchars($svc_min); ?>"
                   style="width:80px; padding:6px 8px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
            <input type="number" name="max" placeholder="Max ₱" value="<?php echo htmlspecialchars($svc_max); ?>"
                   style="width:80px; padding:6px 8px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
            <select name="sort" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid #dde1ea; border-radius:8px; font-size:13px;">
                <option value="id_desc"       <?php echo $svc_sort==='id_desc'       ? 'selected':''; ?>>Newest First</option>
                <option value="name_asc"      <?php echo $svc_sort==='name_asc'      ? 'selected':''; ?>>Name A–Z</option>
                <option value="name_desc"     <?php echo $svc_sort==='name_desc'     ? 'selected':''; ?>>Name Z–A</option>
                <option value="price_asc"     <?php echo $svc_sort==='price_asc'     ? 'selected':''; ?>>Price Low–High</option>
                <option value="price_desc"    <?php echo $svc_sort==='price_desc'    ? 'selected':''; ?>>Price High–Low</option>
                <option value="duration_asc"  <?php echo $svc_sort==='duration_asc'  ? 'selected':''; ?>>Duration Short–Long</option>
                <option value="duration_desc" <?php echo $svc_sort==='duration_desc' ? 'selected':''; ?>>Duration Long–Short</option>
                <option value="bookings_desc" <?php echo $svc_sort==='bookings_desc' ? 'selected':''; ?>>Most Bookings</option>
                <option value="bookings_asc"  <?php echo $svc_sort==='bookings_asc'  ? 'selected':''; ?>>Least Bookings</option>
            </select>
            <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply</button>
            <?php if ($svc_search || $svc_min !== '' || $svc_max !== ''): ?>
                <a href="admin-services.php" style="font-size:12px; color:#e53935;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <table class="admin-table" id="servicesTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Service Name</th>
                <th>Description</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Total Bookings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($services)): ?>
                <tr>
                    <td>#<?php echo $row['service_id']; ?></td>
                    <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                    <td style="max-width:200px; font-size:12px; color:#666;">
                        <?php echo strlen($row['description']) > 60 ? substr(htmlspecialchars($row['description']), 0, 60) . '...' : htmlspecialchars($row['description']); ?>
                    </td>
                    <td>₱<?php echo number_format($row['price'], 2); ?></td>
                    <td><?php echo $row['duration_minutes']; ?> mins</td>
                    <td><?php echo $row['booking_count']; ?></td>
                    <td>
                        <button class="btn-edit" onclick="openEdit(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                        <button class="btn-delete" onclick="confirmDelete(<?php echo $row['service_id']; ?>, '<?php echo addslashes($row['service_name']); ?>')">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

    <!-- ADD MODAL -->
    <div class="modal" id="addModal">
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h2>Add Service</h2>
                <button type="button" class="modal-close"
                    onclick="document.getElementById('addModal').style.display='none'">&times;</button>
            </div>
            <form action="admin-service-save.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-grid">
                    <div class="modal-field modal-field-full">
                        <label>Service Name</label>
                        <input type="text" name="service_name" placeholder="e.g. Basic and Deep Cleaning" required>
                    </div>
                    <div class="modal-field modal-field-full">
                        <label>Description</label>
                        <textarea name="description" rows="3"
                            placeholder="Describe what this service covers..."></textarea>
                    </div>
                    <div class="modal-field">
                        <label>Price (P)</label>
                        <input type="number" name="price" placeholder="e.g. 500" step="0.01" required>
                    </div>
                    <div class="modal-field">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" placeholder="e.g. 60" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel"
                        onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal" id="editModal">
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h2>Edit Service</h2>
                <button type="button" class="modal-close"
                    onclick="document.getElementById('editModal').style.display='none'">&times;</button>
            </div>
            <form action="admin-service-save.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="service_id" id="edit_service_id">
                <div class="modal-grid">
                    <div class="modal-field modal-field-full">
                        <label>Service Name</label>
                        <input type="text" name="service_name" id="edit_service_name" required>
                    </div>
                    <div class="modal-field modal-field-full">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="modal-field">
                        <label>Price (P)</label>
                        <input type="number" name="price" id="edit_price" step="0.01" required>
                    </div>
                    <div class="modal-field">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" id="edit_duration" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel"
                        onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-primary">Update Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h2>Delete Service</h2>
                <button type="button" class="modal-close"
                    onclick="document.getElementById('deleteModal').style.display='none'">&times;</button>
            </div>
            <p style="font-size:14px; color:#555; margin: 8px 0 20px 0;">
                Are you sure you want to delete <strong id="deleteServiceName"></strong>? This cannot be undone.
            </p>
            <form action="admin-service-save.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="service_id" id="delete_service_id">
                <div class="modal-actions">
                    <button type="button" class="btn-cancel"
                        onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-delete-confirm">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('openAddModal').onclick = () => document.getElementById('addModal').style.display = 'flex';

        function openEdit(data) {
            document.getElementById('edit_service_id').value = data.service_id;
            document.getElementById('edit_service_name').value = data.service_name;
            document.getElementById('edit_description').value = data.description ?? '';
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_duration').value = data.duration_minutes;
            document.getElementById('editModal').style.display = 'flex';
        }

        function confirmDelete(id, name) {
            document.getElementById('delete_service_id').value = id;
            document.getElementById('deleteServiceName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('serviceSearch').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#servicesTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    </script>

</body>

</html>