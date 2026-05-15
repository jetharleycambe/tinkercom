<?php
session_start();
// if (!isset($_SESSION["customer_name"])) {
//   header("Location: login.php");
//   exit;
// }

include 'db.php';

$service_name = isset($_GET["service"]) ? trim($_GET["service"]) : "General Service";
if (isset($_GET["service"])) {
    $service_name = trim($_GET["service"]);
} elseif (isset($_POST["service"])) {
    $service_name = trim($_POST["service"]);
}

// ── Local service catalogue (price ₱, duration in minutes) ──────────────────
$service_catalogue = [
    "Predictive and Preventive Maintenance" => ["price" => 500,  "duration" => 60],
    "Basic and Deep Cleaning"               => ["price" => 350,  "duration" => 45],
    "Re-thermal Services"                   => ["price" => 300,  "duration" => 30],
    "Keyboard Replacement"                  => ["price" => 800,  "duration" => 60],
    "Operating System Installation"         => ["price" => 600,  "duration" => 90],
    "SSD Installation and Upgrade"          => ["price" => 500,  "duration" => 45],
    "Laptop Screen Replacement"             => ["price" => 1500, "duration" => 90],
    "Battery Assessment and Installation"   => ["price" => 700,  "duration" => 45],
    "RAM Upgrade"                           => ["price" => 400,  "duration" => 30],
    "Computer Desktop Services"             => ["price" => 800,  "duration" => 120],
    "Application and Drivers Update"        => ["price" => 250,  "duration" => 30],
];
$svc_info     = isset($service_catalogue[$service_name]) ? $service_catalogue[$service_name] : ["price" => 0, "duration" => 0];
$svc_price    = $svc_info["price"];
$svc_duration = $svc_info["duration"];

function sd_format_duration($minutes) {
    if ($minutes < 60) return $minutes . " min";
    $h = intdiv($minutes, 60); $m = $minutes % 60;
    return $h . " hr" . ($h > 1 ? "s" : "") . ($m ? " {$m} min" : "");
}

// Get service_id from services table
$service_sql = "SELECT * FROM services WHERE service_name = '$service_name'";
$service_result = mysqli_query($conn, $service_sql);
$service_row = mysqli_fetch_assoc($service_result);
$service_id = $service_row ? $service_row["service_id"] : 0;

// Get booked slots for this service from appointments table
$booked_sql = "SELECT appointment_date FROM appointments
                  WHERE service_id = '$service_id'
                  AND status != 'CANCELLED'";
$booked_result = mysqli_query($conn, $booked_sql);

$booked_slots = [];
while ($row = mysqli_fetch_assoc($booked_result)) {
    // appointment_date is stored as "2026-05-01 10:00:00"
    // split into date and time parts
    $date = date("Y-m-d", strtotime($row["appointment_date"]));
    $time = date("g:i A", strtotime($row["appointment_date"]));

    if (!isset($booked_slots[$date])) {
        $booked_slots[$date] = [];
    }
    $booked_slots[$date][] = $time;
}

$booked_slots_json = json_encode($booked_slots);

// ── Fetch saved addresses for logged-in user ─────────────────────────────────
$saved_addresses = [];
if (isset($_SESSION["customer_id"])) {
    $uid = intval($_SESSION["customer_id"]);
    $addr_sql = "SELECT address_id, full_address, city, postal_code, is_default
                 FROM addresses
                 WHERE user_id = $uid
                 ORDER BY is_default DESC, address_id DESC";
    $addr_result = mysqli_query($conn, $addr_sql);
    while ($addr_row = mysqli_fetch_assoc($addr_result)) {
        $saved_addresses[] = $addr_row;
    }
}
$saved_addresses_json = json_encode($saved_addresses);

$error = "";
$booking_success = false;
$booked_appt_id  = 0;
$booked_datetime = "";

// Home service surcharge (₱ added on top of base price)
define('HOME_SERVICE_SURCHARGE', 200);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name   = trim($_POST["first_name"]);
    $last_name    = trim($_POST["last_name"]);
    $contact      = trim($_POST["contact"]);
    $book_date    = trim($_POST["book_date"]);
    $book_time    = trim($_POST["book_time"]);
    $service_type = (isset($_POST["service_type"]) && $_POST["service_type"] === "HOME SERVICE")
                    ? "HOME SERVICE" : "ON-SITE";
    $home_address = ($service_type === "HOME SERVICE") ? trim($_POST["home_address"] ?? "") : "";

    $missing = $first_name === "" || $last_name === "" || $contact === ""
               || $book_date === "" || $book_time === "";
    $missing_address = ($service_type === "HOME SERVICE" && $home_address === "");

    if ($missing) {
        $error = "Please fill in all required fields.";
    } elseif ($missing_address) {
        $error = "Please enter your home address for Home Service.";
    } else {
        $appointment_datetime = $book_date . " " . date("H:i:s", strtotime($book_time));
        $user_id  = isset($_SESSION["customer_id"]) ? $_SESSION["customer_id"] : NULL;
        $notes    = $first_name . " " . $last_name . " | " . $contact;
        $home_address_esc = mysqli_real_escape_string($conn, $home_address);
        $service_type_esc = mysqli_real_escape_string($conn, $service_type);

        $sql = "INSERT INTO appointments
          (user_id, service_id, appointment_date, status, service_type, home_address, notes)
        VALUES
          ('$user_id', '$service_id', '$appointment_datetime', 'PENDING',
           '$service_type_esc', " . ($home_address_esc !== "" ? "'$home_address_esc'" : "NULL") . ", '$notes')";

        if (mysqli_query($conn, $sql)) {
            $new_appt_id = mysqli_insert_id($conn);
            $final_price = $svc_price + ($service_type === "HOME SERVICE" ? HOME_SERVICE_SURCHARGE : 0);

            $_SESSION["appointment_id"]    = $new_appt_id;
            $_SESSION["booker_name"]       = $first_name . " " . $last_name;
            $_SESSION["booker_contact"]    = $contact;
            $_SESSION["booker_price"]      = $final_price;
            $_SESSION["booker_duration"]   = $svc_duration;
            $_SESSION["booker_svc_type"]   = $service_type;
            $_SESSION["booker_home_addr"]  = $home_address;

            include_once 'log_action.php';
            log_action($conn, 'USER', $user_id ?? 0, $_SESSION['customer_name'] ?? 'Guest',
                'Booked Appointment',
                "Service: $service_name ($service_type) on $appointment_datetime");

            $booking_success = true;
            $booked_appt_id  = $new_appt_id;
            $booked_datetime = $appointment_datetime;
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Details | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;60
0&display=swap">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body class="background">
    <?php include 'header.php'; ?>

    <section class="bd-sec">
        <h1>Book a Service</h1>

        <div class="bd-container">
            <h1>
                Booking for:
                <?php echo $service_name; ?>
            </h1>

            <!-- ── Service price & duration info ── -->
            <?php if ($svc_price > 0): ?>
            <div class="service-booking-info">
                <span class="sbi-price">₱<?php echo number_format($svc_price, 2); ?></span>
                <span class="sbi-sep">·</span>
                <span class="sbi-duration">⏱ Est. <?php echo sd_format_duration($svc_duration); ?></span>
            </div>
            <?php endif; ?>
            <form action="services-details.php" method="POST">

                <div class="bd-forms">

                    <div class="details-section">
                        <h2>Customer Details</h2>

                        <?php if ($error !== ""): ?>
                            <p style="color:red; font-size:13px;"><?php echo $error; ?></p>
                        <?php endif; ?>

                        <input type="text" name="first_name" placeholder="First Name" required />
                        <input type="text" name="last_name" placeholder="Last Name" required />
                        <input type="text" name="contact" placeholder="Contact Number" required />

                        <!-- ── Service Type Toggle ── -->
                        <div class="svc-type-toggle">
                            <label class="svc-type-label">Service Type</label>
                            <div class="svc-type-options">
                                <label class="svc-type-opt">
                                    <input type="radio" name="service_type" value="ON-SITE" checked
                                           onchange="toggleServiceType(this.value)" />
                                    <span class="svc-type-chip">&#x1F3EA; On-Site</span>
                                </label>
                                <label class="svc-type-opt">
                                    <input type="radio" name="service_type" value="HOME SERVICE"
                                           onchange="toggleServiceType(this.value)" />
                                    <span class="svc-type-chip">&#x1F3E0; Home Service
                                        <?php if (HOME_SERVICE_SURCHARGE > 0): ?>
                                            <small class="svc-surcharge">(+&#x20B1;<?php echo number_format(HOME_SERVICE_SURCHARGE, 0); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- ── Home Address (visible only for Home Service) ── -->
                        <div class="home-address-wrap" id="homeAddressWrap" style="display:none;">

                            <?php if (!empty($saved_addresses)): ?>
                            <div class="saved-addr-bar">
                                <?php if (count($saved_addresses) === 1): ?>
                                    <button type="button" class="use-saved-addr-btn"
                                            onclick="useSavedAddress(0)"
                                            style="background:#e8eeff;color:#0049af;border:1.5px solid #0049af;
                                                   border-radius:20px;padding:6px 14px;font-size:12px;
                                                   font-weight:600;cursor:pointer;white-space:nowrap;">
                                        &#x1F4CD; Use my saved address
                                    </button>
                                    <span class="saved-addr-preview" id="savedAddrPreview0">
                                        <?php
                                        $a0 = $saved_addresses[0];
                                        echo htmlspecialchars(
                                            $a0['full_address'] . ', ' . $a0['city']
                                            . ($a0['postal_code'] ? ' ' . $a0['postal_code'] : '')
                                        );
                                        ?>
                                    </span>
                                <?php else: ?>
                                    <label class="saved-addr-select-label">&#x1F4CD; My saved addresses:</label>
                                    <select id="savedAddrSelect" class="saved-addr-select"
                                            onchange="useSavedAddressFromSelect(this)">
                                        <option value="">— pick a saved address —</option>
                                        <?php foreach ($saved_addresses as $i => $sa): ?>
                                        <option value="<?php echo $i; ?>">
                                            <?php
                                            echo htmlspecialchars(
                                                $sa['full_address'] . ', ' . $sa['city']
                                                . ($sa['postal_code'] ? ' ' . $sa['postal_code'] : '')
                                                . ($sa['is_default'] ? ' ★' : '')
                                            );
                                            ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <textarea name="home_address" id="home_address"
                                      placeholder="Enter your full home address for the technician visit"
                                      rows="3"
                                      style="margin-bottom:0;"></textarea>

                            <?php if (empty($saved_addresses) && isset($_SESSION["customer_id"])): ?>
                                <p class="no-saved-addr-hint">
                                    No saved addresses yet.
                                    <a href="my-account.php?tab=addresses" target="_blank">Add one in My Account</a>.
                                </p>
                            <?php elseif (!isset($_SESSION["customer_id"])): ?>
                                <p class="no-saved-addr-hint">
                                    <a href="login.php">Log in</a> to use a saved address.
                                </p>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="service" value="<?php echo $service_name; ?>" />

                    </div>

                    <div class="calendar">
                        <div class="calendar-header">
                            <button type="button" id="prev">&#10094;</button>
                            <h2 id="monthYear"></h2>
                            <button type="button" id="next">&#10095;</button>
                        </div>

                        <div class="days-header">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>

                        <div id="days"></div>

                        <input type="hidden" name="book_date" id="selected-date" required />

                        <p id="selected-date-label"></p>
                    </div>

                    <div class="time-section">
                        <button type="button" class="time-btn" data-time="9:00 AM"
                            onclick="selectTime(this, '9:00 AM')">9:00 AM</button>
                        <button type="button" class="time-btn" data-time="10:00 AM"
                            onclick="selectTime(this, '10:00 AM')">10:00 AM</button>
                        <button type="button" class="time-btn" data-time="11:00 AM"
                            onclick="selectTime(this, '11:00 AM')">11:00 AM</button>
                        <button type="button" class="time-btn" data-time="1:00 PM"
                            onclick="selectTime(this, '1:00 PM')">1:00 PM</button>
                        <button type="button" class="time-btn" data-time="2:00 PM"
                            onclick="selectTime(this, '2:00 PM')">2:00 PM</button>
                        <button type="button" class="time-btn" data-time="3:00 PM"
                            onclick="selectTime(this, '3:00 PM')">3:00 PM</button>
                        <button type="button" class="time-btn" data-time="4:00 PM"
                            onclick="selectTime(this, '4:00 PM')">4:00 PM</button>

                        <input type="hidden" name="book_time" id="selected-time" />
                        <p id="selected-time-label"></p>
                    </div>
                    

                </div>

                <button type="submit" class="book-btn" onclick="return validateBookingForm()">BOOK NOW</button>

            </form>

        </div>
    </section>
    <?php include 'login-modal.php'; ?>

    <?php if ($booking_success): ?>
<div class="booking-modal-overlay" id="bookingSuccessModal">
    <div class="booking-modal-box">
        <div class="booking-modal-header">
            <h2>Booking Confirmed!</h2>
        </div>
        <div class="booking-modal-body">
            <div class="booking-modal-row">
                <span class="bm-label">Appointment #</span>
                <span class="bm-value"><?php echo $booked_appt_id; ?></span>
            </div>
            <div class="booking-modal-row">
                <span class="bm-label">Service</span>
                <span class="bm-value"><?php echo $service_name; ?></span>
            </div>
            <div class="booking-modal-row">
                <span class="bm-label">For</span>
                <span class="bm-value"><?php echo $_SESSION["booker_name"]; ?></span>
            </div>
            <div class="booking-modal-row">
                <span class="bm-label">When</span>
                <span class="bm-value"><?php echo date("F j, Y", strtotime($booked_datetime)); ?> at <?php echo date("g:i A", strtotime($booked_datetime)); ?></span>
            </div>
            <div class="booking-modal-row">
                <span class="bm-label">Contact</span>
                <span class="bm-value"><?php echo $_SESSION["booker_contact"]; ?></span>
            </div>
            <div class="booking-modal-row">
                <span class="bm-label">Service Type</span>
                <span class="bm-value">
                    <?php
                    $stype = $_SESSION["booker_svc_type"] ?? "ON-SITE";
                    $icon  = $stype === "HOME SERVICE" ? "&#x1F3E0;" : "&#x1F3EA;";
                    echo $icon . " " . $stype;
                    ?>
                </span>
            </div>
            <?php if (!empty($_SESSION["booker_home_addr"])): ?>
            <div class="booking-modal-row">
                <span class="bm-label">Home Address</span>
                <span class="bm-value"><?php echo htmlspecialchars($_SESSION["booker_home_addr"]); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($svc_price > 0): ?>
            <div class="booking-modal-row">
                <span class="bm-label">Service Fee</span>
                <span class="bm-value">₱<?php echo number_format($svc_price, 2); ?></span>
            </div>
            <div class="booking-modal-row">
                <span class="bm-label">Est. Duration</span>
                <span class="bm-value">⏱ <?php echo sd_format_duration($svc_duration); ?></span>
            </div>
            <?php endif; ?>
            <div class="booking-modal-row">
                <span class="bm-label">Status</span>
                <span class="bm-value" style="color:#1a7f44; font-weight:600;">PENDING</span>
            </div>
        </div>
        <div class="booking-modal-footer">
            <a href="services.php"><button class="book-btn">Back to Services</button></a>
        </div>
    </div>
</div>
<script>
    document.getElementById('bookingSuccessModal').style.display = 'flex';
</script>
<?php endif; ?>

    <script>
        const bookedSlots = <?php echo $booked_slots_json; ?>;
    
        const savedAddresses = <?php echo $saved_addresses_json; ?>;

        function toggleServiceType(val) {
            const wrap   = document.getElementById('homeAddressWrap');
            const ta     = document.getElementById('home_address');
            const select = document.getElementById('savedAddrSelect');
            if (val === 'HOME SERVICE') {
                wrap.style.display = 'block';
                ta.setAttribute('required', 'required');
            } else {
                wrap.style.display = 'none';
                ta.removeAttribute('required');
                ta.value = '';
                if (select) select.value = '';
            }
        }

        // Used when there is exactly 1 saved address
        function useSavedAddress(index) {
            const addr = savedAddresses[index];
            if (!addr) return;
            const ta = document.getElementById('home_address');
            ta.value = addr.full_address + ', ' + addr.city
                       + (addr.postal_code ? ' ' + addr.postal_code : '');
            ta.focus();
        }

        // Used when a <select> dropdown is shown (multiple saved addresses)
        function useSavedAddressFromSelect(sel) {
            const idx = sel.value;
            if (idx === '') return;
            useSavedAddress(parseInt(idx, 10));
        }

        function validateBookingForm() {
        const contact = document.querySelector('input[name="contact"]').value.trim();
        const date    = document.getElementById('selected-date').value;
        const time    = document.getElementById('selected-time').value;

        // PH format: 09XXXXXXXXX or +639XXXXXXXXX
        const phRegex = /^(09\d{9}|(\+63)9\d{9})$/;

        if (!phRegex.test(contact)) {
            alert("Please enter a valid PH mobile number (e.g. 09123456789 or +639123456789).");
            return false;
        }
        if (!date) {
            alert("Please select a date.");
            return false;
        }
        if (!time) {
            alert("Please select a time slot.");
            return false;
        }
        return true;
}

    </script>
    <?php include 'footer.php'; ?>
    <script src="javascript.js"></script>
</body>

</html>