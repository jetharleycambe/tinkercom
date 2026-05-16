<?php
/**
 * PART 7 — APPOINTMENT MANAGEMENT
 * FILE: admin-appointments.php (REPLACE your existing file)
 *
 * FIXES:
 *  1. Status flow: PENDING → CONFIRMED → ONGOING → COMPLETED / CANCELLED
 *  2. Future appointments cannot be marked COMPLETED
 *  3. Past/old appointments cannot stay PENDING (auto-flagged)
 *  4. Technician assignment
 *  5. Device info (type + brand) captured
 *  6. Filter by status tab
 *  7. Appointment detail modal
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

// ── UPDATE STATUS ─────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $appt_id    = intval($_POST['appointment_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $tech_id    = isset($_POST['technician_id']) && $_POST['technician_id'] !== ''
                    ? intval($_POST['technician_id']) : null;
    $admin_note = mysqli_real_escape_string($conn, $_POST['admin_note'] ?? '');

    // Fetch the appointment to validate transition
    $appt = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM appointments WHERE appointment_id = $appt_id"
    ));

    $valid_transitions = [
        'PENDING'   => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['ONGOING',   'CANCELLED'],
        'ONGOING'   => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    $current = $appt['status'];

    if (!in_array($new_status, $valid_transitions[$current])) {
        $error = "Cannot move from $current to $new_status.";
    } elseif ($new_status === 'COMPLETED'
              && strtotime($appt['appointment_date']) > time()) {
        $error = 'Cannot mark a future appointment as Completed.';
    } else {
        $tech_sql = $tech_id ? "technician_id = $tech_id," : '';
        mysqli_query($conn,
            "UPDATE appointments SET
                status       = '$new_status',
                $tech_sql
                updated_at   = NOW()
             WHERE appointment_id = $appt_id"
        );

        // Update technician availability
        if ($tech_id) {
            $tech_status = ($new_status === 'ONGOING') ? 'Busy' : 'Available';
            mysqli_query($conn,
                "UPDATE technicians SET status = '$tech_status'
                 WHERE technician_id = $tech_id"
            );
        }

        $success = "Appointment #$appt_id updated to $new_status.";
    }
}

// ── FILTERS ───────────────────────────────────────────────────
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date   = isset($_GET['date'])   ? $_GET['date']   : '';

$where = "WHERE 1=1";
if ($filter_status) {
    $filter_status = mysqli_real_escape_string($conn, $filter_status);
    $where .= " AND a.status = '$filter_status'";
}
if ($filter_date) {
    $filter_date = mysqli_real_escape_string($conn, $filter_date);
    $where .= " AND DATE(a.appointment_date) = '$filter_date'";
}

// ── FETCH APPOINTMENTS ────────────────────────────────────────
$appointments = mysqli_query($conn,
    "SELECT a.*,
            a.service_type, a.home_address,
            u.username, u.first_name, u.last_name, u.phone AS user_phone,
            s.service_name, s.price AS service_price,
            t.full_name AS technician_name
     FROM appointments a
     JOIN users    u ON a.user_id    = u.user_id
     JOIN services s ON a.service_id = s.service_id
     LEFT JOIN technicians t ON a.technician_id = t.technician_id
     $where
     ORDER BY
         FIELD(a.status,'PENDING','CONFIRMED','ONGOING','COMPLETED','CANCELLED'),
         a.appointment_date ASC"
);

// ── STATUS COUNTS for tabs ────────────────────────────────────
$counts = [];
foreach (['PENDING','CONFIRMED','ONGOING','COMPLETED','CANCELLED'] as $st) {
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM appointments WHERE status = '$st'"
    ));
    $counts[$st] = $row['c'];
}
$counts['ALL'] = array_sum($counts);

// ── CALENDAR DATA: appointments in the current month ─────────
$cal_year  = isset($_GET['cal_year'])  ? intval($_GET['cal_year'])  : intval(date('Y'));
$cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : intval(date('m'));

// Clamp month
if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }

$cal_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$cal_end   = date('Y-m-t', strtotime($cal_start));

$cal_events_raw = mysqli_query($conn,
    "SELECT a.appointment_id, a.appointment_date, a.status,
            s.service_name,
            u.username, u.first_name, u.last_name
     FROM appointments a
     JOIN services s ON a.service_id = s.service_id
     JOIN users    u ON a.user_id    = u.user_id
     WHERE DATE(a.appointment_date) BETWEEN '$cal_start' AND '$cal_end'
     ORDER BY a.appointment_date ASC"
);

// Group by day
$cal_events = [];
while ($ev = mysqli_fetch_assoc($cal_events_raw)) {
    $day = intval(date('j', strtotime($ev['appointment_date'])));
    $cal_events[$day][] = $ev;
}

// ── TECHNICIANS for assignment dropdown ───────────────────────
$technicians = mysqli_query($conn,
    "SELECT * FROM technicians ORDER BY status ASC, full_name ASC"
);
$tech_list = [];
while ($t = mysqli_fetch_assoc($technicians)) {
    $tech_list[] = $t;
}

// ── AUTO-FLAG: overdue PENDING appointments ───────────────────
// Any appointment date in the past that is still PENDING → flag visually
// (we don't auto-cancel — admin decides)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
    <div>
        <h1>Bookings</h1>
        <p class="topbar-sub">Manage service bookings and technician assignments</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <button type="button" class="btn-primary" id="toggleCalBtn"
                onclick="toggleCalendar()" style="font-size:13px; padding:8px 18px;">
            Show Calendar
        </button>
            <!-- Date filter -->
            <form method="GET" action="admin-appointments.php" class="sort-form">
                <?php if ($filter_status): ?>
                    <input type="hidden" name="status" value="<?php echo $filter_status; ?>">
                <?php endif; ?>
                <label>Date:</label>
                <input type="date" name="date"
                       value="<?php echo htmlspecialchars($filter_date); ?>"
                       onchange="this.form.submit()"
                       style="padding:7px 10px;border:1.5px solid #dde1ea;border-radius:8px;font-size:13px;">
                <?php if ($filter_date): ?>
                    <a href="admin-appointments.php?<?php echo $filter_status ? 'status='.$filter_status : ''; ?>"
                       style="font-size:12px;color:#e53935;">Clear date</a>
                <?php endif; ?>
            </form>
        </div>
                </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>


        <!-- ══ CALENDAR VIEW (hidden by default) ══ -->
<div id="bookingCalendar" style="display:none; margin-bottom:24px;">
    <div class="admin-section-card" style="padding:0; overflow:hidden;">

        <!-- Calendar Navigation -->
        <div style="display:flex; align-items:center; justify-content:space-between;
                    padding:14px 20px; background:#0049af; color:#fff; border-radius:8px 8px 0 0;">
            <a href="admin-appointments.php?cal_year=<?php
                $prev_m = $cal_month - 1; $prev_y = $cal_year;
                if ($prev_m < 1) { $prev_m = 12; $prev_y--; }
                echo "$prev_y&cal_month=$prev_m";
                echo $filter_status ? '&status='.$filter_status : '';
            ?>#bookingCalendar"
               style="color:#fff; font-size:20px; text-decoration:none; line-height:1;">&lsaquo;</a>

            <h3 style="margin:0; font-size:16px; font-weight:700;">
                <?php echo date('F Y', mktime(0,0,0,$cal_month,1,$cal_year)); ?>
            </h3>

            <a href="admin-appointments.php?cal_year=<?php
                $next_m = $cal_month + 1; $next_y = $cal_year;
                if ($next_m > 12) { $next_m = 1; $next_y++; }
                echo "$next_y&cal_month=$next_m";
                echo $filter_status ? '&status='.$filter_status : '';
            ?>#bookingCalendar"
               style="color:#fff; font-size:20px; text-decoration:none; line-height:1;">&rsaquo;</a>
        </div>

        <!-- Day Headers -->
        <div style="display:grid; grid-template-columns:repeat(7,1fr);
                    background:#f0f4ff; border-bottom:1px solid #dde1ea;">
            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
            <div style="text-align:center; padding:8px 4px; font-size:12px;
                        font-weight:700; color:#6b7280; text-transform:uppercase;">
                <?php echo $dh; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Calendar Grid -->
        <?php
        $first_dow  = intval(date('w', strtotime($cal_start))); // 0=Sun
        $days_total = intval(date('t', strtotime($cal_start)));
        $today_day  = (date('Y-m') === sprintf('%04d-%02d', $cal_year, $cal_month))
                        ? intval(date('j')) : -1;
        $cell       = 0;
        ?>
        <div style="display:grid; grid-template-columns:repeat(7,1fr);">
            <?php
            // Empty cells before month starts
            for ($i = 0; $i < $first_dow; $i++, $cell++):
            ?>
            <div style="min-height:90px; border-right:1px solid #f0f0f0;
                        border-bottom:1px solid #f0f0f0; background:#fafafa;"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $days_total; $d++, $cell++): ?>
            <?php
            $is_today  = ($d === $today_day);
            $is_sunday = ($cell % 7 === 0);
            $events    = $cal_events[$d] ?? [];
            ?>
            <div style="min-height:90px; padding:6px; border-right:1px solid #f0f0f0;
                        border-bottom:1px solid #f0f0f0;
                        background:<?php echo $is_today ? '#eff6ff' : '#fff'; ?>;">
                <div style="font-size:13px; font-weight:<?php echo $is_today ? '700' : '500'; ?>;
                             color:<?php echo $is_today ? '#0049af' : ($is_sunday ? '#dc2626' : '#374151'); ?>;
                             margin-bottom:4px;">
                    <?php echo $d; ?>
                    <?php if ($is_today): ?>
                        <span style="font-size:10px; background:#0049af; color:#fff;
                                     border-radius:8px; padding:1px 5px; margin-left:3px;">Today</span>
                    <?php endif; ?>
                </div>
                <?php foreach (array_slice($events, 0, 3) as $ev):
                    $status_colors_cal = [
                        'PENDING'   => '#f59e0b',
                        'CONFIRMED' => '#0049af',
                        'ONGOING'   => '#7c3aed',
                        'COMPLETED' => '#16a34a',
                        'CANCELLED' => '#dc2626',
                    ];
                    $dot_color = $status_colors_cal[$ev['status']] ?? '#888';
                    $cname = trim(($ev['first_name']??'').' '.($ev['last_name']??'')) ?: $ev['username'];
                ?>
                <div style="background:<?php echo $dot_color; ?>18; border-left:3px solid <?php echo $dot_color; ?>;
                             border-radius:3px; padding:2px 5px; margin-bottom:3px; font-size:10px;
                             white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                     title="<?php echo htmlspecialchars($ev['service_name'].' - '.$cname); ?>">
                    <?php echo date('g:i A', strtotime($ev['appointment_date'])); ?>
                    &mdash; <?php echo htmlspecialchars($cname); ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($events) > 3): ?>
                    <div style="font-size:10px; color:#9ca3af; padding-left:2px;">
                        +<?php echo count($events) - 3; ?> more
                    </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>

            <?php
            // Fill remaining cells
            $remaining = (7 - ($cell % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++):
            ?>
            <div style="min-height:90px; border-right:1px solid #f0f0f0;
                        border-bottom:1px solid #f0f0f0; background:#fafafa;"></div>
            <?php endfor; ?>
        </div>

        <!-- Legend -->
        <div style="display:flex; gap:16px; padding:12px 20px; background:#f9fafb;
                    border-top:1px solid #e5e7eb; flex-wrap:wrap;">
            <?php foreach ([
                'PENDING'=>'#f59e0b','CONFIRMED'=>'#0049af',
                'ONGOING'=>'#7c3aed','COMPLETED'=>'#16a34a','CANCELLED'=>'#dc2626'
            ] as $lbl => $lclr): ?>
            <span style="font-size:11px; display:flex; align-items:center; gap:5px;">
                <span style="width:10px; height:10px; background:<?php echo $lclr; ?>;
                             border-radius:50%; display:inline-block;"></span>
                <?php echo $lbl; ?>
            </span>
            <?php endforeach; ?>
        </div>

    </div>
</div>


        <!-- Status Tabs -->
        <div class="appt-tabs">
            <a href="admin-appointments.php<?php echo $filter_date ? '?date='.$filter_date : ''; ?>"
               class="appt-tab <?php echo $filter_status === '' ? 'active' : ''; ?>">
                All <span class="tab-count"><?php echo $counts['ALL']; ?></span>
            </a>
            <?php foreach (['PENDING','CONFIRMED','ONGOING','COMPLETED','CANCELLED'] as $st): ?>
            <a href="admin-appointments.php?status=<?php echo $st; ?><?php echo $filter_date ? '&date='.$filter_date : ''; ?>"
               class="appt-tab appt-tab-<?php echo strtolower($st); ?>
                      <?php echo $filter_status === $st ? 'active' : ''; ?>">
                <?php echo ucfirst(strtolower($st)); ?>
                <span class="tab-count"><?php echo $counts[$st]; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Appointments Table -->
        <div class="admin-table-section">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Date &amp; Time</th>
                        <th>Device</th>
                        <th>Technician</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $now = time();
                if (mysqli_num_rows($appointments) > 0):
                while ($a = mysqli_fetch_assoc($appointments)):
                    $appt_time  = strtotime($a['appointment_date']);
                    $is_past    = $appt_time < $now;
                    $is_overdue = $is_past && $a['status'] === 'PENDING';

                    // What status can this appointment move to?
                    $transitions = [
                        'PENDING'   => ['CONFIRMED', 'CANCELLED'],
                        'CONFIRMED' => ['ONGOING',   'CANCELLED'],
                        'ONGOING'   => ['COMPLETED', 'CANCELLED'],
                        'COMPLETED' => [],
                        'CANCELLED' => [],
                    ];
                    $next_steps = $transitions[$a['status']] ?? [];
                ?>
                <tr class="<?php echo $is_overdue ? 'row-overdue' : ''; ?>">
                    <td>
                        #<?php echo $a['appointment_id']; ?>
                        <?php if ($is_overdue): ?>
                            <span class="overdue-badge">⚠ Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>
                            <?php echo htmlspecialchars(
                                trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))
                                ?: $a['username']
                            ); ?>
                        </strong>
                        <?php if ($a['user_phone']): ?>
                            <br><small style="color:#888;"><?php echo htmlspecialchars($a['user_phone']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($a['service_name']); ?>
                        <br><small style="color:#0049af;">₱<?php echo number_format($a['service_price'], 2); ?></small>
                    </td>
                    <td>
                        <?php echo date('M j, Y', $appt_time); ?>
                        <br><small><?php echo date('g:i A', $appt_time); ?></small>
                        <?php if ($is_past && !in_array($a['status'], ['COMPLETED','CANCELLED'])): ?>
                            <br><small style="color:#e53935;">Past date</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($a['device_type'])): ?>
                            <?php echo htmlspecialchars($a['device_type']); ?>
                            <?php if (!empty($a['device_brand'])): ?>
                                <br><small><?php echo htmlspecialchars($a['device_brand']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($a['technician_name']): ?>
                            <span style="color:#15803d;font-weight:600;">
                                <?php echo htmlspecialchars($a['technician_name']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#f59e0b;font-size:12px;">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $stype = $a['service_type'] ?? 'ON-SITE';
                        $stype_class = $stype === 'HOME SERVICE' ? 'stype-home' : 'stype-onsite';
                        $stype_icon  = $stype === 'HOME SERVICE' ? '&#x1F3E0;' : '&#x1F3EA;';
                        echo '<span class="stype-badge ' . $stype_class . '">' . $stype_icon . ' ' . $stype . '</span>';
                        ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo strtolower($a['status']); ?>">
                            <?php echo $a['status']; ?>
                        </span>
                    </td>
                    <td class="action-btns">
                        <!-- View details -->
                        <button class="btn-action btn-view"
                                onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($a), ENT_QUOTES); ?>)">
                            View
                        </button>

                        <!-- Status transition buttons -->
                        <?php foreach ($next_steps as $next):
                            // Don't show COMPLETED if appointment is in the future
                            if ($next === 'COMPLETED' && !$is_past) continue;
                            $btn_class = $next === 'CANCELLED' ? 'btn-danger' : 'btn-success';
                        ?>
                        <button class="btn-action <?php echo $btn_class; ?>"
                                onclick="openUpdateModal(
                                    <?php echo $a['appointment_id']; ?>,
                                    '<?php echo $a['status']; ?>',
                                    '<?php echo $next; ?>',
                                    <?php echo $a['technician_id'] ?? 'null'; ?>
                                )">
                            <?php echo ucfirst(strtolower($next)); ?>
                        </button>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr>
                    <td colspan="8" class="no-data">No bookings found.</td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: UPDATE STATUS
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-modal-overlay" id="updateModal" style="display:none;">
    <div class="admin-modal" style="max-width:460px;">
        <div class="admin-modal-header">
            <h3 id="updateModal_title">Update Booking</h3>
            <button class="modal-close" onclick="closeModal('updateModal')">✕</button>
        </div>
        <form method="POST" action="admin-appointments.php" class="admin-modal-form">
            <input type="hidden" name="action"         value="update_status">
            <input type="hidden" name="appointment_id" id="update_appt_id">
            <input type="hidden" name="new_status"     id="update_new_status">

            <div class="appt-status-flow">
                <div class="flow-step" id="flow_pending">PENDING</div>
                <div class="flow-arrow">→</div>
                <div class="flow-step" id="flow_confirmed">CONFIRMED</div>
                <div class="flow-arrow">→</div>
                <div class="flow-step" id="flow_ongoing">ONGOING</div>
                <div class="flow-arrow">→</div>
                <div class="flow-step" id="flow_completed">COMPLETED</div>
            </div>
            <p id="update_description" style="font-size:13px;color:#555;margin-bottom:8px;"></p>

            <!-- Technician assignment -->
            <div class="admin-form-group">
                <label>Assign Technician</label>
                <select name="technician_id" id="update_technician">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($tech_list as $t): ?>
                    <option value="<?php echo $t['technician_id']; ?>"
                            data-status="<?php echo $t['status']; ?>">
                        <?php echo htmlspecialchars($t['full_name']); ?>
                        (<?php echo $t['status']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn-action btn-cancel"
                        onclick="closeModal('updateModal')">Cancel</button>
                <button type="submit" class="admin-btn-primary" id="update_submit_btn">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: APPOINTMENT DETAIL VIEW
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-modal-overlay" id="detailModal" style="display:none;">
    <div class="admin-modal" style="max-width:500px;">
        <div class="admin-modal-header">
            <h3>Bookings Details</h3>
            <button class="modal-close" onclick="closeModal('detailModal')">✕</button>
        </div>
        <div class="admin-modal-form" id="detailModal_body">
            <!-- Filled by JS -->
        </div>
    </div>
</div>


<script>
function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.admin-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.style.display = 'none';
    });
});

// Status flow colors
const flowColors = {
    'PENDING'   : '#f59e0b',
    'CONFIRMED' : '#0049af',
    'ONGOING'   : '#7c3aed',
    'COMPLETED' : '#16a34a',
    'CANCELLED' : '#dc2626',
};

const flowDescriptions = {
    'CONFIRMED' : 'Mark this appointment as Confirmed — customer will be notified.',
    'ONGOING'   : 'Mark as Ongoing — technician is now working on this.',
    'COMPLETED' : 'Mark as Completed — service has been finished.',
    'CANCELLED' : 'Cancel this appointment.',
};

function openUpdateModal(apptId, currentStatus, newStatus, currentTechId) {
    document.getElementById('update_appt_id').value   = apptId;
    document.getElementById('update_new_status').value = newStatus;
    document.getElementById('updateModal_title').textContent =
        'Move Appointment #' + apptId + ' → ' + newStatus;

    // Highlight flow steps
    ['pending','confirmed','ongoing','completed'].forEach(step => {
        const el = document.getElementById('flow_' + step);
        el.style.background = '#f0f0f0';
        el.style.color      = '#888';
        el.style.fontWeight = 'normal';

        const STATUS = step.toUpperCase();
        if (STATUS === currentStatus) {
            el.style.background = flowColors[currentStatus] || '#ccc';
            el.style.color      = 'white';
        }
        if (STATUS === newStatus) {
            el.style.background = flowColors[newStatus] || '#ccc';
            el.style.color      = 'white';
            el.style.fontWeight = '700';
        }
    });

    // Description
    document.getElementById('update_description').textContent =
        flowDescriptions[newStatus] || '';

    // Submit button color
    const btn = document.getElementById('update_submit_btn');
    btn.style.background = newStatus === 'CANCELLED' ? '#dc2626' : '';

    // Pre-select current technician
    const techSel = document.getElementById('update_technician');
    if (currentTechId) {
        techSel.value = currentTechId;
    }

    openModal('updateModal');
}

function openDetailModal(appt) {
    const body = document.getElementById('detailModal_body');

    const apptDate = new Date(appt.appointment_date);
    const dateStr  = apptDate.toLocaleDateString('en-PH', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    const timeStr  = apptDate.toLocaleTimeString('en-PH', {
        hour: '2-digit', minute: '2-digit'
    });

    const statusColor = {
        'PENDING'  :'#f59e0b','CONFIRMED':'#0049af',
        'ONGOING'  :'#7c3aed','COMPLETED':'#16a34a','CANCELLED':'#dc2626'
    };

    body.innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Appointment ID</span>
            <span>#${appt.appointment_id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="status-badge ${appt.status.toLowerCase()}">${appt.status}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Customer</span>
            <span>${appt.first_name || ''} ${appt.last_name || ''} (${appt.username})</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Phone</span>
            <span>${appt.user_phone || '—'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Service</span>
            <span>${appt.service_name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Service Fee</span>
            <span>₱${parseFloat(appt.service_price).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Date</span>
            <span>${dateStr}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Time</span>
            <span>${timeStr}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Service Type</span>
            <span>
                ${appt.service_type === 'HOME SERVICE'
                    ? '&#x1F3E0; <strong>Home Service</strong>'
                    : '&#x1F3EA; On-Site'}
            </span>
        </div>
        ${appt.home_address ? `
        <div class="detail-row">
            <span class="detail-label">Home Address</span>
            <span>${appt.home_address}</span>
        </div>` : ''}
        <div class="detail-row">
            <span class="detail-label">Device</span>
            <span>${appt.device_type ? appt.device_type + (appt.device_brand ? ' — ' + appt.device_brand : '') : '—'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Technician</span>
            <span>${appt.technician_name || '<em style="color:#f59e0b">Unassigned</em>'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Notes</span>
            <span>${appt.notes || '—'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Booked On</span>
            <span>${new Date(appt.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'})}</span>
        </div>
    `;
    openModal('detailModal');
}

function toggleCalendar() {
    const cal = document.getElementById('bookingCalendar');
    const btn = document.getElementById('toggleCalBtn');
    if (cal.style.display === 'none') {
        cal.style.display = 'block';
        btn.textContent = 'Hide Calendar';
    } else {
        cal.style.display = 'none';
        btn.textContent = 'Show Calendar';
    }
}

// Auto-show calendar if navigated to it via anchor
if (window.location.hash === '#bookingCalendar') {
    const cal = document.getElementById('bookingCalendar');
    const btn = document.getElementById('toggleCalBtn');
    if (cal) {
        cal.style.display = 'block';
        btn.textContent = 'Hide Calendar';
    }
}
</script>

</body>
</html>