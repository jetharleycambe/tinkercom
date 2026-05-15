<?php
session_start();
include 'db.php';
include 'log_action.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

$appointment_id = intval($_POST["appointment_id"]);
$status         = mysqli_real_escape_string($conn, $_POST["status"]);

$allowed = ["PENDING", "CONFIRMED", "COMPLETED", "CANCELLED"];
if (in_array($status, $allowed)) {
    mysqli_query($conn, "UPDATE appointments SET status = '$status' WHERE appointment_id = $appointment_id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Updated Appointment Status', "Appointment #$appointment_id set to $status");
}

header("Location: admin-appointments.php?success=updated");
exit;
?>