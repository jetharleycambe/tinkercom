<?php
session_start();
include 'db.php';

if (!isset($_SESSION["appointment_id"])) {
    header("Location: services.php");
    exit;
}

$appointment_id = $_SESSION["appointment_id"];

// Fetch appointment with service name joined
$sql = "SELECT appointments.*, services.service_name
        FROM appointments
        JOIN services ON appointments.service_id = services.service_id
        WHERE appointments.appointment_id = '$appointment_id'";

$result  = mysqli_query($conn, $sql);
$booking = mysqli_fetch_assoc($result);

// Pull booker info from session (set during booking in services-details.php)
$booker_name    = isset($_SESSION["booker_name"])    ? $_SESSION["booker_name"]    : "N/A";
$booker_contact = isset($_SESSION["booker_contact"]) ? $_SESSION["booker_contact"] : "N/A";
$booker_email   = isset($_SESSION["booker_email"])   ? $_SESSION["booker_email"]   : "";
$booker_svc_type  = isset($_SESSION["booker_svc_type"])  ? $_SESSION["booker_svc_type"]  : "ON-SITE";
$booker_home_addr = isset($_SESSION["booker_home_addr"]) ? $_SESSION["booker_home_addr"] : "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Summary | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body class="background">

    <!-- ===== HEADER ===== -->
    <header class="home-header">
        <div class="home-logo">
            <a href="index.php"><img src="assets/tinkercom-logoe.png" alt="" /></a>
            <h4>Tinkercom</h4>
        </div>
        <form class="search-bar" action="search.php" method="GET">
            <input type="search" name="q" placeholder="Search products..." />
            <button type="submit"><img src="assets/search.png" alt="" /></button>
        </form>
        <div class="header-icons">
            <a href="wishlist.php"><img src="assets/love.png" alt="" /></a>
            <a href="cart.php"><img src="assets/shopping-cart.png" alt="" /></a>
            <?php if (isset($_SESSION["customer_name"])): ?>
                <div class="user-dropdown">
                    <img src="assets/user.png" alt="" class="user-icon" />
                    <div class="dropdown-menu">
                        <p>Hi, <?php echo $_SESSION["customer_name"]; ?></p>
                        <a href="my-account.php">My Account</a>
                        <a href="my-account.php?tab=orders">My Orders</a>
                        <a href="my-account.php?tab=appointments">My Appointments</a>
                        <a href="wishlist.php">My Wishlist</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php"><img src="assets/user.png" alt="" /></a>
            <?php endif; ?>
        </div>
    </header>

    <!-- ===== NAVIGATION ===== -->
    <nav class="navigation">
        <ul>
            <a href="index.php">HOME</a>
            <a href="printers.php">PRINTERS</a>
            <a href="accessories.php">ACCESSORIES</a>
            <a href="peripherals.php">PERIPHERALS</a>
            <a href="services.php" class="nav-active">SERVICES</a>
        </ul>
    </nav>

    <!-- ===== BOOKING SUMMARY ===== -->
    <section class="booking-summary-sec">
        <div class="summary-container">

            <div class="summary-title">
                <h1>Booking Summary</h1>
            </div>

            <div class="summary-details">

                <div class="summary-service">
                    <h3>Appointment #:</h3>
                    <p><?php echo $booking["appointment_id"]; ?></p>
                </div>

                <div class="summary-service">
                    <h3>Service:</h3>
                    <p><?php echo $booking["service_name"]; ?></p>
                </div>

                <div class="summary-for">
                    <h3>For:</h3>
                    <p><?php echo $booker_name; ?></p>
                </div>

                <div class="summary-when">
                    <h3>When:</h3>
                    <p>
                        <?php echo date("F j, Y", strtotime($booking["appointment_date"])); ?>
                        at
                        <?php echo date("g:i A", strtotime($booking["appointment_date"])); ?>
                    </p>
                </div>

                <div class="summary-contact">
                    <h3>Contact Number:</h3>
                    <p><?php echo $booker_contact; ?></p>
                </div>

                <?php if (!empty($booker_email)): ?>
                    <div class="summary-email">
                        <h3>Email Address:</h3>
                        <p><?php echo $booker_email; ?></p>
                    </div>
                <?php endif; ?>

                <div class="summary-service-type">
                    <h3>Service Type:</h3>
                    <p>
                        <?php
                        $icon = $booker_svc_type === "HOME SERVICE" ? "&#x1F3E0;" : "&#x1F3EA;";
                        echo $icon . " " . $booker_svc_type;
                        ?>
                    </p>
                </div>

                <?php if (!empty($booker_home_addr)): ?>
                    <div class="summary-home-address">
                        <h3>Home Address:</h3>
                        <p><?php echo htmlspecialchars($booker_home_addr); ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <h3>Status:</h3>
                    <p class="status-confirmed-text"><?php echo $booking["status"]; ?></p>
                </div>

            </div>

            <div class="summary-book-btn">
                <a href="services.php">
                    <button>BACK TO SERVICES</button>
                </a>
            </div>

        </div>
    </section>

</body>

</html>