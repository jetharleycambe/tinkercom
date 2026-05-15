<?php
/**
 *
 * Simple technician management:
 *  - List all technicians with status
 *  - Add new technician
 *  - Edit technician details
 *  - View assigned appointments per technician
 *  - Toggle availability status
 */
include 'db.php';  
session_start();
include 'session-check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: index.php');
    exit;
}

$success = '';
$error   = '';

// ── ADD TECHNICIAN ────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_tech') {
    $full_name      = trim(mysqli_real_escape_string($conn, $_POST['full_name']      ?? ''));
    $phone          = trim(mysqli_real_escape_string($conn, $_POST['phone']          ?? ''));
    $email          = trim(mysqli_real_escape_string($conn, $_POST['email']          ?? ''));
    $specialization = trim(mysqli_real_escape_string($conn, $_POST['specialization'] ?? ''));

    if ($full_name === '') {
        $error = 'Full name is required.';
    } else {
        mysqli_query($conn,
            "INSERT INTO technicians (full_name, phone, email, specialization, status)
             VALUES ('$full_name','$phone','$email','$specialization','Available')"
        );
        $success = "Technician \"$full_name\" added successfully.";
    }
}

// ── EDIT TECHNICIAN ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit_tech') {
    $id             = intval($_POST['technician_id']);
    $full_name      = trim(mysqli_real_escape_string($conn, $_POST['full_name']      ?? ''));
    $phone          = trim(mysqli_real_escape_string($conn, $_POST['phone']          ?? ''));
    $email          = trim(mysqli_real_escape_string($conn, $_POST['email']          ?? ''));
    $specialization = trim(mysqli_real_escape_string($conn, $_POST['specialization'] ?? ''));

    if ($full_name === '') {
        $error = 'Full name is required.';
    } else {
        mysqli_query($conn,
            "UPDATE technicians SET
                full_name      = '$full_name',
                phone          = '$phone',
                email          = '$email',
                specialization = '$specialization'
             WHERE technician_id = $id"
        );
        $success = 'Technician updated successfully.';
    }
}

// ── TOGGLE STATUS ─────────────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id  = intval($_GET['toggle']);
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT status FROM technicians WHERE technician_id = $id"
    ));
    // Cycle: Available → Off Duty → Available (admin manually overrides)
    $new = $row['status'] === 'Available' ? 'Off Duty' : 'Available';
    mysqli_query($conn,
        "UPDATE technicians SET status = '$new' WHERE technician_id = $id"
    );
    header('Location: admin-technicians.php');
    exit;
}

// ── FETCH ALL TECHNICIANS ─────────────────────────────────────
$technicians = mysqli_query($conn,
    "SELECT t.*,
            COUNT(CASE WHEN a.status IN ('PENDING','CONFIRMED','ONGOING') THEN 1 END) AS active_appts,
            COUNT(a.appointment_id) AS total_appts
     FROM technicians t
     LEFT JOIN appointments a ON a.technician_id = t.technician_id
     GROUP BY t.technician_id
     ORDER BY t.status ASC, t.full_name ASC"
);

// ── EDIT MODE ─────────────────────────────────────────────────
$edit_tech = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_tech = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM technicians WHERE technician_id = " . intval($_GET['edit'])
    ));
}

// ── SELECTED TECHNICIAN'S APPOINTMENTS ───────────────────────
$view_id   = isset($_GET['view']) && is_numeric($_GET['view']) ? intval($_GET['view']) : 0;
$view_tech = null;
$view_appts = null;
if ($view_id) {
    $view_tech = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM technicians WHERE technician_id = $view_id"
    ));
    $view_appts = mysqli_query($conn,
        "SELECT a.*, u.username, u.first_name, u.last_name,
                s.service_name
         FROM appointments a
         JOIN users    u ON a.user_id    = u.user_id
         JOIN services s ON a.service_id = s.service_id
         WHERE a.technician_id = $view_id
         ORDER BY a.appointment_date DESC
         LIMIT 20"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technicians | Tinkercom Admin</title>
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
                <h1>Technicians</h1>
                <p class="topbar-sub">
                    Manage service technicians and their assignments
                </p>
            </div>
            <button class="admin-btn-primary"
                    onclick="openModal('addTechModal')">
                + Add Technician
            </button>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════
             TECHNICIAN CARDS
             ═══════════════════════════════════════════════════ -->
        <div class="tech-cards-grid">
            <?php
            mysqli_data_seek($technicians, 0);
            while ($tech = mysqli_fetch_assoc($technicians)):
                $status_color = [
                    'Available' => '#16a34a',
                    'Busy'      => '#d97706',
                    'Off Duty'  => '#6b7280',
                ][$tech['status']] ?? '#888';
            ?>
            <div class="tech-card">
                <!-- Avatar -->
                <div class="tech-avatar"
                     style="background:<?php echo $status_color; ?>;">
                    <?php echo strtoupper(substr($tech['full_name'], 0, 1)); ?>
                </div>

                <!-- Info -->
                <div class="tech-info">
                    <h3><?php echo htmlspecialchars($tech['full_name']); ?></h3>
                    <p class="tech-specialization">
                        <?php echo htmlspecialchars($tech['specialization'] ?: 'General Technician'); ?>
                    </p>

                    <div class="tech-contact">
                        <?php if ($tech['phone']): ?>
                            <span>📞 <?php echo htmlspecialchars($tech['phone']); ?></span>
                        <?php endif; ?>
                        <?php if ($tech['email']): ?>
                            <span>✉ <?php echo htmlspecialchars($tech['email']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="tech-stats">
                        <div class="tech-stat">
                            <span class="tech-stat-num">
                                <?php echo $tech['active_appts']; ?>
                            </span>
                            <span class="tech-stat-label">Active</span>
                        </div>
                        <div class="tech-stat">
                            <span class="tech-stat-num">
                                <?php echo $tech['total_appts']; ?>
                            </span>
                            <span class="tech-stat-label">Total</span>
                        </div>
                    </div>
                </div>

                <!-- Status badge -->
                <div class="tech-status-wrap">
                    <span class="tech-status-dot"
                          style="background:<?php echo $status_color; ?>;"></span>
                    <span style="color:<?php echo $status_color; ?>;font-weight:700;font-size:13px;">
                        <?php echo $tech['status']; ?>
                    </span>
                </div>

                <!-- Actions -->
                <div class="tech-card-actions">
                    <a href="admin-technicians.php?view=<?php echo $tech['technician_id']; ?>"
                       class="btn-action btn-view">
                        View Schedule
                    </a>
                    <a href="admin-technicians.php?edit=<?php echo $tech['technician_id']; ?>"
                       class="btn-action btn-edit">
                        Edit
                    </a>
                    <a href="admin-technicians.php?toggle=<?php echo $tech['technician_id']; ?>"
                       class="btn-action <?php echo $tech['status'] === 'Available' ? 'btn-danger' : 'btn-success'; ?>"
                       onclick="return confirm('Change status to <?php echo $tech['status'] === 'Available' ? 'Off Duty' : 'Available'; ?>?')">
                        <?php echo $tech['status'] === 'Available' ? 'Set Off Duty' : 'Set Available'; ?>
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════
             SELECTED TECHNICIAN'S APPOINTMENT SCHEDULE
             ═══════════════════════════════════════════════════ -->
        <?php if ($view_tech && $view_appts): ?>
        <div class="admin-table-section" style="margin-top:28px;">
            <div class="admin-table-header">
                <h2>
                     Schedule — <?php echo htmlspecialchars($view_tech['full_name']); ?>
                </h2>
                <a href="admin-technicians.php" class="btn-action btn-cancel">
                    Close
                </a>
            </div>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Appt #</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($view_appts) > 0):
                      while ($a = mysqli_fetch_assoc($view_appts)): ?>
                <tr>
                    <td>#<?php echo $a['appointment_id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars(
                            trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))
                            ?: $a['username']
                        ); ?>
                    </td>
                    <td><?php echo htmlspecialchars($a['service_name']); ?></td>
                    <td>
                        <?php echo date('M j, Y', strtotime($a['appointment_date'])); ?>
                        <br>
                        <small><?php echo date('g:i A', strtotime($a['appointment_date'])); ?></small>
                    </td>
                    <td>
                        <span class="status-badge <?php echo strtolower($a['status']); ?>">
                            <?php echo $a['status']; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr>
                    <td colspan="5" class="no-data">
                        No appointments assigned yet.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </main>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: ADD TECHNICIAN
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-modal-overlay" id="addTechModal" style="display:none;">
    <div class="admin-modal" style="max-width:480px;">
        <div class="admin-modal-header">
            <h3>Add New Technician</h3>
            <button class="modal-close"
                    onclick="closeModal('addTechModal')">✕</button>
        </div>
        <form method="POST" action="admin-technicians.php"
              class="admin-modal-form">
            <input type="hidden" name="action" value="add_tech">

            <div class="admin-form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name"
                       placeholder="e.g. Juan dela Cruz" required>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Phone</label>
                    <input type="text" name="phone"
                           placeholder="e.g. 09171234567">
                </div>
                <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           placeholder="tech@email.com">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Specialization</label>
                <input type="text" name="specialization"
                       placeholder="e.g. General Computer Repair & Maintenance">
                <small class="ai-hint">
                    What services this technician handles
                </small>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn-action btn-cancel"
                        onclick="closeModal('addTechModal')">
                    Cancel
                </button>
                <button type="submit" class="admin-btn-primary">
                    Add Technician
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: EDIT TECHNICIAN
     ═══════════════════════════════════════════════════════════ -->
<?php if ($edit_tech): ?>
<div class="admin-modal-overlay" id="editTechModal" style="display:flex;">
    <div class="admin-modal" style="max-width:480px;">
        <div class="admin-modal-header">
            <h3>Edit Technician</h3>
            <a href="admin-technicians.php" class="modal-close">✕</a>
        </div>
        <form method="POST" action="admin-technicians.php"
              class="admin-modal-form">
            <input type="hidden" name="action"       value="edit_tech">
            <input type="hidden" name="technician_id"
                   value="<?php echo $edit_tech['technician_id']; ?>">

            <div class="admin-form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name"
                       value="<?php echo htmlspecialchars($edit_tech['full_name']); ?>"
                       required>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Phone</label>
                    <input type="text" name="phone"
                           value="<?php echo htmlspecialchars($edit_tech['phone'] ?? ''); ?>">
                </div>
                <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars($edit_tech['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Specialization</label>
                <input type="text" name="specialization"
                       value="<?php echo htmlspecialchars($edit_tech['specialization'] ?? ''); ?>">
            </div>

            <div class="admin-modal-actions">
                <a href="admin-technicians.php"
                   class="btn-action btn-cancel">Cancel</a>
                <button type="submit" class="admin-btn-primary">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<script>
function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.admin-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.style.display = 'none';
    });
});
</script>

</body>
</html>