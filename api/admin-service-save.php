<?php
include 'db.php';  
session_start();
include 'log_action.php';
include 'session-check.php';

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN") {
    header("Location: index.php");
    exit;
}

$action = $_POST["action"] ?? '';

if ($action === "add") {
    $name     = mysqli_real_escape_string($conn, trim($_POST["service_name"]));
    $desc     = mysqli_real_escape_string($conn, trim($_POST["description"]));
    $price    = floatval($_POST["price"]);
    $duration = intval($_POST["duration_minutes"]);

    mysqli_query($conn, "INSERT INTO services (service_name, description, price, duration_minutes) 
                         VALUES ('$name', '$desc', $price, $duration)");
                         log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Added Service', "Added: $name");
    header("Location: admin-services.php?success=added");
    exit;
}

if ($action === "edit") {
    $id       = intval($_POST["service_id"]);
    $name     = mysqli_real_escape_string($conn, trim($_POST["service_name"]));
    $desc     = mysqli_real_escape_string($conn, trim($_POST["description"]));
    $price    = floatval($_POST["price"]);
    $duration = intval($_POST["duration_minutes"]);

    mysqli_query($conn, "UPDATE services SET 
                            service_name     = '$name',
                            description      = '$desc',
                            price            = $price,
                            duration_minutes = $duration
                         WHERE service_id = $id");
                         log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Edited Service', "Updated service ID: $id - $name");
    header("Location: admin-services.php?success=edited");
    exit;
}

if ($action === "delete") {
    $id = intval($_POST["service_id"]);

    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE service_id = $id"));
    if ($check["count"] > 0) {
        header("Location: admin-services.php?success=error");
        exit;
    }

    mysqli_query($conn, "DELETE FROM services WHERE service_id = $id");
    log_action($conn, 'ADMIN', $_SESSION['customer_id'], $_SESSION['customer_name'], 'Deleted Service', "Deleted service ID: $id");
    header("Location: admin-services.php?success=deleted");
    exit;
}

header("Location: admin-services.php");
exit;
?>