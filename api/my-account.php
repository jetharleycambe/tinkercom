<?php
include 'db.php';  
session_start();

// Must be logged in
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["customer_id"];

// Handle all form submissions
$success = "";
$error   = "";

// ---- UPDATE PROFILE ----
if (isset($_POST["action"]) && $_POST["action"] === "update_profile") {
    $first_name = trim(mysqli_real_escape_string($conn, $_POST["first_name"]));
    $last_name  = trim(mysqli_real_escape_string($conn, $_POST["last_name"]));
    $username   = trim(mysqli_real_escape_string($conn, $_POST["username"]));
    $email      = trim(mysqli_real_escape_string($conn, $_POST["email"]));
    $phone      = trim(mysqli_real_escape_string($conn, $_POST["phone"]));

    // Phone validation — PH format
    $phone_regex = '/^(09\d{9}|(\+63)9\d{9})$/';

    if ($username === "" || $email === "") {
        $error = "Username and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($phone !== "" && !preg_match($phone_regex, $phone)) {
        $error = "Phone number must be in PH format (e.g. 09171234567 or +639171234567).";
    } else {
        // Check if email is already used by another user
        $email_check = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT user_id FROM users WHERE email = '$email' AND user_id != $user_id")
        );
        if ($email_check) {
            $error = "That email address is already used by another account.";
        } else {
            mysqli_query($conn,
                "UPDATE users SET
                    first_name = '$first_name',
                    last_name  = '$last_name',
                    username   = '$username',
                    email      = '$email',
                    phone      = '$phone'
                 WHERE user_id = $user_id"
            );
            $_SESSION["customer_name"] = $username;
            $success = "Profile updated successfully!";
        }
    }
}

// ---- CHANGE PASSWORD ----
if (isset($_POST["action"]) && $_POST["action"] === "change_password") {
    $current = trim($_POST["current_password"]);
    $new     = trim($_POST["new_password"]);
    $confirm = trim($_POST["confirm_password"]);

    $user_row = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT password FROM users WHERE user_id = $user_id")
    );

    if (!password_verify($current, $user_row["password"])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE user_id = $user_id");
        $success = "Password changed successfully!";
    }
}

// ---- ADD ADDRESS ----
if (isset($_POST["action"]) && $_POST["action"] === "add_address") {
    $full_address = trim(mysqli_real_escape_string($conn, $_POST["full_address"] ?? ""));
    $city         = trim(mysqli_real_escape_string($conn, $_POST["city"]         ?? ""));
    $province     = trim(mysqli_real_escape_string($conn, $_POST["province"]      ?? ""));
    $postal_code  = trim(mysqli_real_escape_string($conn, $_POST["postal_code"]  ?? ""));
    $is_default   = isset($_POST["is_default"]) ? 1 : 0;
 
    if ($full_address === "" || $city === "" || $province === "") {
        $error = "Please complete all address fields (street, city, and province are required).";
    } else {
        if ($is_default) {
            mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
        }
        mysqli_query($conn,
            "INSERT INTO addresses (user_id, full_address, city, province, postal_code, is_default)
             VALUES ($user_id, '$full_address', '$city', '$province', '$postal_code', $is_default)"
        );
        header("Location: my-account.php?tab=addresses&success=added");
        exit;
    }
}

// ---- EDIT ADDRESS ----
if (isset($_POST["action"]) && $_POST["action"] === "edit_address") {
    $address_id   = intval($_POST["address_id"]);
    $full_address = trim(mysqli_real_escape_string($conn, $_POST["full_address"] ?? ""));
    $city         = trim(mysqli_real_escape_string($conn, $_POST["city"]         ?? ""));
    $province     = trim(mysqli_real_escape_string($conn, $_POST["province"]      ?? ""));
    $postal_code  = trim(mysqli_real_escape_string($conn, $_POST["postal_code"]  ?? ""));
    $is_default   = isset($_POST["is_default"]) ? 1 : 0;
 
    if ($full_address === "" || $city === "" || $province === "") {
        $error = "Please complete all address fields.";
    } else {
        if ($is_default) {
            mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
        }
        mysqli_query($conn,
            "UPDATE addresses SET
                full_address = '$full_address',
                city         = '$city',
                province     = '$province',
                postal_code  = '$postal_code',
                is_default   = $is_default
             WHERE address_id = $address_id AND user_id = $user_id"
        );
        header("Location: my-account.php?tab=addresses&success=updated");
        exit;
    }
}

// ---- DELETE ADDRESS ----
if (isset($_GET["delete_address"])) {
    $address_id = intval($_GET["delete_address"]);
    mysqli_query($conn, "DELETE FROM addresses WHERE address_id = $address_id AND user_id = $user_id");
    header("Location: my-account.php?tab=addresses&success=removed");
    exit;
}

// ---- SET DEFAULT ADDRESS ----
if (isset($_GET["set_default"])) {
    $address_id = intval($_GET["set_default"]);
    mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
    mysqli_query($conn, "UPDATE addresses SET is_default = 1 WHERE address_id = $address_id AND user_id = $user_id");
    header("Location: my-account.php?tab=addresses&success=default");
    exit;
}


// ---- ORDER RECEIVED ----
if (isset($_GET["order_received"])) {
    $order_id = intval($_GET["order_received"]);
    $check = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT status, is_received FROM orders WHERE order_id = $order_id AND user_id = $user_id")
    );
    if ($check && $check["status"] === "DELIVERED" && $check["is_received"] == 0) {
        mysqli_query($conn, "UPDATE orders SET is_received = 1 WHERE order_id = $order_id AND user_id = $user_id");
        $success = "Order #$order_id confirmed as received!";
    } else {
        $error = "Unable to confirm this order.";
    }
}
// ---- CANCEL ORDER ----
if (isset($_GET["cancel_order"])) {
    $order_id = intval($_GET["cancel_order"]);
    $check = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT status FROM orders WHERE order_id = $order_id AND user_id = $user_id")
    );

    if ($check && $check["status"] === "PENDING") {
        mysqli_query($conn, "UPDATE orders SET status = 'CANCELLED' WHERE order_id = $order_id AND user_id = $user_id");
        include_once 'log_action.php';
        log_action($conn, 'USER', $user_id, $_SESSION['customer_name'], 'Cancelled Order', "Order #$order_id cancelled by user");
        $success = "Order #$order_id has been cancelled.";
    } else {
        $error = "This order cannot be cancelled.";
    }
}

// ---- CANCEL APPOINTMENT ----
if (isset($_GET["cancel_appt"])) {
    $appt_id = intval($_GET["cancel_appt"]);
    $check = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT status FROM appointments WHERE appointment_id = $appt_id AND user_id = $user_id")
    );

    if ($check && in_array($check["status"], ["PENDING", "CONFIRMED"])) {
        mysqli_query($conn, "UPDATE appointments SET status = 'CANCELLED' WHERE appointment_id = $appt_id AND user_id = $user_id");
        include_once 'log_action.php'; // ← DITO kasama
        log_action($conn, 'USER', $user_id, $_SESSION['customer_name'], 'Cancelled Appointment', "Appointment #$appt_id cancelled by user"); // ← DITO
        $success = "Appointment cancelled.";
    } else {
        $error = "This appointment cannot be cancelled.";
    }
}

// ---- SUBMIT SITE REVIEW ----
if (isset($_POST["action"]) && $_POST["action"] === "submit_site_review") {
    $sr_rating  = intval($_POST["site_rating"]  ?? 0);
    $sr_comment = trim(mysqli_real_escape_string($conn, $_POST["site_comment"] ?? ""));

    if ($sr_rating < 1 || $sr_rating > 5) {
        $error = "Please select a star rating.";
    } else {
        // Check if already submitted
        $sr_existing = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT site_review_id FROM site_reviews WHERE user_id = $user_id")
        );
        if ($sr_existing) {
            // Update existing
            mysqli_query($conn,
                "UPDATE site_reviews SET rating = $sr_rating, comment = '$sr_comment', created_at = NOW()
                 WHERE user_id = $user_id"
            );
            $success = "Your review has been updated. Thank you!";
        } else {
            mysqli_query($conn,
                "INSERT INTO site_reviews (user_id, rating, comment) VALUES ($user_id, $sr_rating, '$sr_comment')"
            );
            $success = "Thank you for rating Tinkercom!";
        }
    }
}

// ---- SUBMIT REVIEW (inline, same page via modal) ----
if (isset($_POST["action"]) && $_POST["action"] === "submit_review") {
    $r_product_id = intval($_POST["product_id"] ?? 0);
    $r_order_id   = intval($_POST["order_id"]   ?? 0);
    $r_rating     = intval($_POST["rating"]      ?? 0);
    $r_comment    = trim(mysqli_real_escape_string($conn, $_POST["comment"] ?? ""));

    if ($r_product_id > 0 && $r_order_id > 0 && $r_rating >= 1 && $r_rating <= 5) {
        // Verify order belongs to user and is DELIVERED or COMPLETED
        $r_order_check = mysqli_fetch_assoc(
            mysqli_query($conn,
                "SELECT status FROM orders WHERE order_id = $r_order_id AND user_id = $user_id")
        );
        // Verify product is in that order
        $r_item_check = mysqli_fetch_assoc(
            mysqli_query($conn,
                "SELECT 1 FROM order_items WHERE order_id = $r_order_id AND product_id = $r_product_id")
        );
        // Verify not already reviewed
        $r_existing = mysqli_fetch_assoc(
            mysqli_query($conn,
                "SELECT review_id FROM product_reviews
                 WHERE user_id = $user_id AND product_id = $r_product_id AND order_id = $r_order_id")
        );

        if ($r_order_check && in_array($r_order_check["status"], ["DELIVERED", "COMPLETED"])
            && $r_item_check && !$r_existing) {
            mysqli_query($conn,
                "INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment)
                 VALUES ($r_product_id, $user_id, $r_order_id, $r_rating, '$r_comment')"
            );
            $success = "Your review has been submitted. Thank you!";
        } else {
            $error = "Unable to submit review. You may have already reviewed this product.";
        }
    } else {
        $error = "Please select a star rating before submitting.";
    }
}

// ---- FETCH DATA ----

// User info
$user = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id")
);

// Full name helper
$first_name = isset($user["first_name"]) ? $user["first_name"] : "";
$last_name  = isset($user["last_name"])  ? $user["last_name"]  : "";
$full_name  = trim($first_name . " " . $last_name);
if ($full_name === "") {
    $full_name = $user["username"];
}

// Addresses
$addresses_result = mysqli_query($conn, "SELECT * FROM addresses WHERE user_id = $user_id ORDER BY is_default DESC");
$addresses = [];
while ($row = mysqli_fetch_assoc($addresses_result)) {
    $addresses[] = $row;
}

// Orders with item count + first item preview
$orders_result = mysqli_query($conn,
    "SELECT orders.*,
            COUNT(order_items.order_item_id) AS item_count,
            MIN(products.product_name)       AS first_item_name,
            MIN(products.image)              AS first_item_image
     FROM orders
     LEFT JOIN order_items ON orders.order_id = order_items.order_id
     LEFT JOIN products    ON order_items.product_id = products.product_id
     WHERE orders.user_id = $user_id
     GROUP BY orders.order_id
     ORDER BY orders.created_at DESC"
);
$my_orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) {
    $my_orders[] = $row;
}

// Appointments
$appts_result = mysqli_query($conn,
    "SELECT appointments.*, services.service_name
     FROM appointments
     JOIN services ON appointments.service_id = services.service_id
     WHERE appointments.user_id = $user_id
     ORDER BY appointments.appointment_date DESC"
);
$my_appointments = [];
while ($row = mysqli_fetch_assoc($appts_result)) {
    $my_appointments[] = $row;
}

// Pre-fetch all reviews this user has submitted (keyed by "product_id-order_id")
$reviewed_keys = [];
$reviews_fetch = mysqli_query($conn,
    "SELECT product_id, order_id FROM product_reviews WHERE user_id = $user_id"
);
while ($rv = mysqli_fetch_assoc($reviews_fetch)) {
    $reviewed_keys[$rv["product_id"] . "-" . $rv["order_id"]] = true;
}

// Pre-fetch order items for all DELIVERED/COMPLETED orders (for Review buttons)
$deliverable_items = [];
foreach ($my_orders as $o) {
    if (in_array($o["status"], ["DELIVERED", "COMPLETED"]) && $o["is_received"] == 1) {
        $oid = intval($o["order_id"]);
        $items_q = mysqli_query($conn,
            "SELECT oi.product_id, p.product_name, p.image
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = $oid"
        );
        $items_arr = [];
        while ($it = mysqli_fetch_assoc($items_q)) {
            $items_arr[] = $it;
        }
        $deliverable_items[$oid] = $items_arr;
    }
}

// Fetch user's existing site review (if any)
$my_site_review = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM site_reviews WHERE user_id = $user_id")
) ?: null;

// Address to edit (if edit button was clicked)
$editing_address = null;
if (isset($_GET["edit_address"])) {
    $edit_id = intval($_GET["edit_address"]);
    $editing_address = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT * FROM addresses WHERE address_id = $edit_id AND user_id = $user_id")
    );
}

// Which tab is active
$tab = isset($_GET["tab"]) ? $_GET["tab"] : "profile";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Account | Tinkercom</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body class="background">

    <!-- ===== HEADER ===== -->
    <?php include 'header.php'; ?>

    <!-- ===== ACCOUNT PAGE ===== -->
    <section class="account-page">
        <div class="account-layout">

            <!-- SIDEBAR -->
            <aside class="account-sidebar">
                <div class="account-user-card">
                    <div class="account-avatar">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <div>
                        <p class="account-username"><?php echo $full_name; ?></p>
                        <p class="account-email"><?php echo $user["email"]; ?></p>
                    </div>
                </div>

                <nav class="account-nav">
                    <a href="my-account.php?tab=profile"
                       class="account-nav-link <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                        My Profile
                    </a>
                    <a href="my-account.php?tab=addresses"
                       class="account-nav-link <?php echo $tab === 'addresses' ? 'active' : ''; ?>">
                        My Addresses
                    </a>
                    <a href="my-account.php?tab=orders"
                       class="account-nav-link <?php echo $tab === 'orders' ? 'active' : ''; ?>">
                        My Orders
                        <?php if (count($my_orders) > 0): ?>
                            <span class="nav-count"><?php echo count($my_orders); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="my-account.php?tab=appointments"
                       class="account-nav-link <?php echo $tab === 'appointments' ? 'active' : ''; ?>">
                        My Bookings
                        <?php if (count($my_appointments) > 0): ?>
                            <span class="nav-count"><?php echo count($my_appointments); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="my-account.php?tab=password"
                       class="account-nav-link <?php echo $tab === 'password' ? 'active' : ''; ?>">
                        Change Password
                    </a>
                    <a href="my-account.php?tab=rate-us"
                       class="account-nav-link <?php echo $tab === 'rate-us' ? 'active' : ''; ?>">
                         Rate Us
                    </a>
                    <a href="logout.php" class="account-nav-link account-logout">
                        Logout
                    </a>
                </nav>
            </aside>

            <!-- MAIN CONTENT -->
            <div class="account-content">

                <!-- Success / Error alerts -->
                <?php if ($success !== ""): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error !== ""): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>


                <!-- ==============================
                     TAB: PROFILE
                ============================== -->
                <?php if ($tab === "profile"): ?>
                    <div class="account-card">
                        <h2 class="account-card-title">My Profile</h2>

                        <form action="my-account.php?tab=profile" method="POST">
                            <input type="hidden" name="action" value="update_profile" />

                            <div class="account-form-row">
                                <div class="account-form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" value="<?php echo $first_name; ?>" required />
                                </div>
                                <div class="account-form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" value="<?php echo $last_name; ?>" required />
                                </div>
                            </div>

                            <div class="account-form-row">
                                <div class="account-form-group">
                                    <label>Username</label>
                                    <input type="text" name="username" value="<?php echo $user["username"]; ?>" required />
                                </div>
                                <div class="account-form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" value="<?php echo $user["email"]; ?>" />
                                </div>
                            </div>

                            <div class="account-form-row">
                                <div class="account-form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" value="<?php echo $user["phone"]; ?>" placeholder="e.g. 09171234567" />
                                </div>
                            </div>

                            <button type="submit" class="account-btn">Save Changes</button>
                        </form>
                    </div>


                <!-- ==============================
                     TAB: ADDRESSES
                ============================== -->
                <?php elseif ($tab === "addresses"): ?>

                    <?php
                    // Show success from redirect (delete / set default)
                    if (isset($_GET['success']) && $success === "") {
                        if ($_GET['success'] === 'removed') $success = "Address removed.";
                        if ($_GET['success'] === 'default') $success = "Default address updated!";
                    }
                    ?>

                    <div class="account-card">
                        <h2 class="account-card-title">My Addresses</h2>

                        <!-- ── Saved addresses list ── -->
                        <?php if (!empty($addresses)): ?>
                            <div class="address-list">
                                <?php foreach ($addresses as $addr): ?>
                                    <div class="address-item <?php echo $addr["is_default"] ? 'address-default' : ''; ?>">
                                        <div class="address-item-info">
                                            <?php if ($addr["is_default"]): ?>
                                                <span class="default-badge">Default</span>
                                            <?php endif; ?>
                                            <p class="address-full"><?php echo htmlspecialchars($addr["full_address"]); ?></p>
                                            <p class="address-city">
                                                <?php echo htmlspecialchars($addr["city"]); ?>
                                                <?php echo !empty($addr["province"]) ? ", " . htmlspecialchars($addr["province"]) : ""; ?>
                                                <?php echo $addr["postal_code"] ? " " . $addr["postal_code"] : ""; ?>
                                            </p>
                                        </div>

                                        <div class="address-item-actions">
                                            <a href="my-account.php?tab=addresses&edit_address=<?php echo $addr["address_id"]; ?>"
                                               class="addr-action-link">Edit</a>

                                            <?php if (!$addr["is_default"]): ?>
                                                <a href="my-account.php?tab=addresses&set_default=<?php echo $addr["address_id"]; ?>"
                                                   class="addr-action-link">Set as Default</a>
                                            <?php endif; ?>

                                            <a href="my-account.php?tab=addresses&delete_address=<?php echo $addr["address_id"]; ?>"
                                               class="addr-action-link addr-delete"
                                               onclick="return confirm('Remove this address?')">Remove</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data-msg">No addresses saved yet.</p>
                        <?php endif; ?>


                        <!-- ── EDIT ADDRESS FORM (lumalabas lang kapag pinindot Edit) ── -->
                        <?php if ($editing_address): ?>
                            <h3 class="account-sub-title">Edit Address</h3>
                            <form action="my-account.php?tab=addresses" method="POST" class="address-form-bg" id="edit-address-form">
                                <input type="hidden" name="action"     value="edit_address" />
                                <input type="hidden" name="address_id" value="<?php echo $editing_address["address_id"]; ?>" />

                                <!-- Hidden fields — PHP will save these -->
                                <input type="hidden" name="full_address" id="edit_full_address_hidden" value="<?php echo htmlspecialchars($editing_address["full_address"]); ?>" />
                                <input type="hidden" name="city"         id="edit_city_hidden"         value="<?php echo htmlspecialchars($editing_address["city"]); ?>" />
                                <input type="hidden" name="province"     id="edit_province_hidden"     value="<?php echo htmlspecialchars($editing_address["province"] ?? ""); ?>" />

                                <!-- Show current saved address so user knows what's there -->
                                <div class="account-form-group">
                                    <label>Current Saved Address</label>
                                    <p style="font-size:13px; color:#555; background:#f5f5f5; padding:8px 12px; border-radius:6px; margin:0;">
                                        <?php echo htmlspecialchars($editing_address["full_address"]); ?>,
                                        <?php echo htmlspecialchars($editing_address["city"]); ?>
                                        <?php echo $editing_address["postal_code"] ? ", " . $editing_address["postal_code"] : ""; ?>
                                    </p>
                                </div>

                                <p style="font-size:13px; color:#888; margin: 4px 0 12px;">
                                    Select dropdowns below to change the address, or leave them to keep the current one.
                                </p>

                                <!-- Street -->
                                <div class="account-form-group">
                                    <label>House/Unit No., Street <span style="color:#888; font-weight:400;">(optional update)</span></label>
                                    <input type="text" id="edit_street" placeholder="e.g. Blk 6 Lot 4, Alegra St." />
                                </div>

                                <!-- Region & Province -->
                                <div class="account-form-row">
                                    <div class="account-form-group">
                                        <label>Region</label>
                                        <select id="edit_region">
                                            <option value="">-- Select Region --</option>
                                        </select>
                                    </div>
                                    <div class="account-form-group">
                                        <label>Province / Independent City</label>
                                        <select id="edit_province" disabled>
                                            <option value="">-- Select Province/City --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- City/Mun & Barangay -->
                                <div class="account-form-row">
                                    <div class="account-form-group">
                                        <label>City / Municipality</label>
                                        <select id="edit_city_mun" disabled>
                                            <option value="">-- Select City/Municipality --</option>
                                        </select>
                                    </div>
                                    <div class="account-form-group">
                                        <label>Barangay</label>
                                        <select id="edit_barangay" disabled>
                                            <option value="">-- Select Barangay --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Postal Code -->
                                <div class="account-form-row">
                                    <div class="account-form-group">
                                        <label>Postal Code</label>
                                        <input type="text" name="postal_code"
                                               value="<?php echo htmlspecialchars($editing_address["postal_code"]); ?>"
                                               placeholder="e.g. 3022" maxlength="4" />
                                    </div>
                                </div>

                                <div class="account-form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_default" class="checkbox-input"
                                               <?php echo $editing_address["is_default"] ? "checked" : ""; ?> />
                                        Set as default address
                                    </label>
                                </div>

                                <div class="form-action-row">
                                    <button type="submit" class="account-btn">Save Changes</button>
                                    <a href="my-account.php?tab=addresses" class="btn-cancel">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>


                        <!-- ── ADD NEW ADDRESS FORM ── -->
                        <h3 class="account-sub-title">Add New Address</h3>
                        <form action="my-account.php?tab=addresses" method="POST" id="add-address-form">
                            <input type="hidden" name="action" value="add_address" />

                            <!-- These hidden fields are what PHP actually saves -->
                            <input type="hidden" name="full_address" id="add_full_address_hidden" />
                            <input type="hidden" name="city"         id="add_city_hidden" />
                            <input type="hidden" name="province"     id="add_province_hidden" />

                            <!-- Street -->
                            <div class="account-form-group">
                                <label>House/Unit No., Street</label>
                                <input type="text" id="add_street" placeholder="e.g. Blk 6 Lot 4, Alegra St." required />
                            </div>

                            <!-- Region & Province -->
                            <div class="account-form-row">
                                <div class="account-form-group">
                                    <label>Region</label>
                                    <select id="add_region" required>
                                        <option value="">-- Select Region --</option>
                                    </select>
                                </div>
                                <div class="account-form-group">
                                    <label>Province / Independent City</label>
                                    <select id="add_province" disabled required>
                                        <option value="">-- Select Province/City --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- City/Mun & Barangay -->
                            <div class="account-form-row">
                                <div class="account-form-group">
                                    <label>City / Municipality</label>
                                    <select id="add_city_mun" disabled required>
                                        <option value="">-- Select City/Municipality --</option>
                                    </select>
                                </div>
                                <div class="account-form-group">
                                    <label>Barangay</label>
                                    <select id="add_barangay" disabled required>
                                        <option value="">-- Select Barangay --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Postal Code -->
                            <div class="account-form-row">
                                <div class="account-form-group">
                                    <label>Postal Code</label>
                                    <input type="text" name="postal_code" id="add_postal_code"
                                           placeholder="e.g. 3022" maxlength="4" />
                                </div>
                            </div>

                            <div class="account-form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_default" class="checkbox-input" />
                                    Set as default address
                                </label>
                            </div>

                            <button type="submit" class="account-btn">Add Address</button>
                        </form>
                    </div>

                    <!-- ── PSGC DROPDOWN SCRIPT ── -->
                    <script>
                    const PSGC = "https://psgc.gitlab.io/api";

                    // ============================================================
                    // HELPER: populate a <select> from an array of {code, name}
                    // ============================================================
                    function fillSelect(sel, items, placeholder) {
                        sel.innerHTML = `<option value="">${placeholder}</option>`;
                        items.sort((a, b) => a.name.localeCompare(b.name))
                             .forEach(item => {
                                const opt = document.createElement("option");
                                opt.value       = item.code;
                                opt.textContent = item.name;
                                sel.appendChild(opt);
                             });
                        sel.disabled = false;
                    }

                    // ============================================================
                    // HELPER: reset + disable a list of selects
                    // ============================================================
                    function resetSelects(...selects) {
                        selects.forEach((sel, i) => {
                            const labels = ["-- Select Province/City --",
                                            "-- Select City/Municipality --",
                                            "-- Select Barangay --"];
                            sel.innerHTML = `<option value="">${labels[i] || "-- Select --"}</option>`;
                            sel.disabled  = true;
                        });
                    }

                    // ============================================================
                    // HELPER: load barangays for a city or municipality code
                    // ============================================================
                    function loadBarangays(code, brgySelect) {
                        Promise.all([
                            fetch(`${PSGC}/cities/${code}/barangays/`).then(r => r.json()).catch(() => []),
                            fetch(`${PSGC}/municipalities/${code}/barangays/`).then(r => r.json()).catch(() => [])
                        ]).then(([b1, b2]) => {
                            const all = [...b1, ...b2];
                            if (all.length > 0) {
                                // barangay names are used as values (not codes)
                                brgySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
                                all.sort((a, b) => a.name.localeCompare(b.name))
                                   .forEach(item => {
                                        const opt = document.createElement("option");
                                        opt.value       = item.name;
                                        opt.textContent = item.name;
                                        brgySelect.appendChild(opt);
                                   });
                                brgySelect.disabled = false;
                            }
                        });
                    }

                    // ============================================================
                    // HELPER: load cities+municipalities under a province/city code
                    // ============================================================
                    function loadCities(code, citySelect, brgySelect) {
                        resetSelects(citySelect, brgySelect);
                        Promise.all([
                            fetch(`${PSGC}/provinces/${code}/cities/`).then(r => r.json()).catch(() => []),
                            fetch(`${PSGC}/provinces/${code}/municipalities/`).then(r => r.json()).catch(() => []),
                            // independent cities directly under a region have their own endpoint
                            fetch(`${PSGC}/cities/${code}/barangays/`).then(r => r.json()).catch(() => [])
                        ]).then(([cities, munis, directBrgys]) => {
                            const combined = [...cities, ...munis];
                            if (combined.length > 0) {
                                fillSelect(citySelect, combined, "-- Select City/Municipality --");
                            } else if (directBrgys.length > 0) {
                                // It's an independent city — skip city level, go straight to barangay
                                citySelect.innerHTML = `<option value="${code}">${document.getElementById('add_province')?.options[document.getElementById('add_province').selectedIndex]?.text || ''}</option>`;
                                citySelect.disabled  = false;
                                loadBarangays(code, brgySelect);
                            }
                        });
                    }

                    // ============================================================
                    // SETUP FOR "ADD" FORM
                    // ============================================================
                    (function setupAddForm() {
                        const regSel  = document.getElementById("add_region");
                        const provSel = document.getElementById("add_province");
                        const citySel = document.getElementById("add_city_mun");
                        const brgySel = document.getElementById("add_barangay");

                        if (!regSel) return;

                        // Load all regions
                        fetch(`${PSGC}/regions/`)
                            .then(r => r.json())
                            .then(data => fillSelect(regSel, data, "-- Select Region --"));

                        // Region → Province/City
                        regSel.addEventListener("change", function () {
                            const code = this.value;
                            resetSelects(provSel, citySel, brgySel);
                            if (!code) return;

                            Promise.all([
                                fetch(`${PSGC}/regions/${code}/provinces/`).then(r => r.json()).catch(() => []),
                                fetch(`${PSGC}/regions/${code}/cities/`).then(r => r.json()).catch(() => [])
                            ]).then(([provs, cities]) => {
                                fillSelect(provSel, [...provs, ...cities], "-- Select Province/City --");
                            });
                        });

                        // Province → City/Municipality
                        provSel.addEventListener("change", function () {
                            const code = this.value;
                            resetSelects(citySel, brgySel);
                            if (!code) return;
                            loadCities(code, citySel, brgySel);
                        });

                        // City/Municipality → Barangay
                        citySel.addEventListener("change", function () {
                            const code = this.value;
                            brgySel.innerHTML = '<option value="">-- Select Barangay --</option>';
                            brgySel.disabled  = true;
                            if (!code) return;
                            loadBarangays(code, brgySel);
                        });

                        // On submit — assemble full_address and city hidden fields
                        document.getElementById("add-address-form").addEventListener("submit", function (e) {
                            const street   = document.getElementById("add_street").value.trim();
                            const barangay = brgySel.value;
                            const cityText = citySel.options[citySel.selectedIndex]?.text || "";
                            const provText = provSel.options[provSel.selectedIndex]?.text || "";

                            if (!barangay || !cityText || !provText || !street) {
                                e.preventDefault();
                                alert("Please fill in all address fields (street, region, province, city/municipality, and barangay).");
                                return;
                            }

                            // full_address = "Blk 6 Lot 4 Alegra St., Brgy. San Vicente, Santa Maria"
                            const fullAddr = street + ", " + barangay + ", " + cityText;
                            document.getElementById("add_full_address_hidden").value = fullAddr;
                            document.getElementById("add_city_hidden").value         = cityText;
                            document.getElementById("add_province_hidden").value     = provText;
                        });

                    })();


                    // ============================================================
                    // SETUP FOR "EDIT" FORM (only runs if edit form is on the page)
                    // ============================================================
                    (function setupEditForm() {
                        const regSel  = document.getElementById("edit_region");
                        const provSel = document.getElementById("edit_province");
                        const citySel = document.getElementById("edit_city_mun");
                        const brgySel = document.getElementById("edit_barangay");
                        const form    = document.getElementById("edit-address-form");

                        if (!regSel || !form) return; // edit form is not on the page right now

                        // Load all regions
                        fetch(`${PSGC}/regions/`)
                            .then(r => r.json())
                            .then(data => fillSelect(regSel, data, "-- Select Region --"));

                        regSel.addEventListener("change", function () {
                            const code = this.value;
                            resetSelects(provSel, citySel, brgySel);
                            if (!code) return;

                            Promise.all([
                                fetch(`${PSGC}/regions/${code}/provinces/`).then(r => r.json()).catch(() => []),
                                fetch(`${PSGC}/regions/${code}/cities/`).then(r => r.json()).catch(() => [])
                            ]).then(([provs, cities]) => {
                                fillSelect(provSel, [...provs, ...cities], "-- Select Province/City --");
                            });
                        });

                        provSel.addEventListener("change", function () {
                            const code = this.value;
                            resetSelects(citySel, brgySel);
                            if (!code) return;
                            loadCities(code, citySel, brgySel);
                        });

                        citySel.addEventListener("change", function () {
                            const code = this.value;
                            brgySel.innerHTML = '<option value="">-- Select Barangay --</option>';
                            brgySel.disabled  = true;
                            if (!code) return;
                            loadBarangays(code, brgySel);
                        });

                        // On submit — if user selected new dropdowns, update hidden fields
                        // If user left dropdowns blank, keep the existing saved values (already in the hidden inputs)
                        form.addEventListener("submit", function (e) {
                            const street   = document.getElementById("edit_street").value.trim();
                            const barangay = brgySel.value;
                            const cityText = citySel.options[citySel.selectedIndex]?.text || "";

                            // Only update if the user actually selected something new
                            if (barangay && cityText && street) {
                                const fullAddr = street + ", " + barangay + ", " + cityText;
                                const editProvText = document.getElementById("edit_province")?.options[document.getElementById("edit_province").selectedIndex]?.text || "";
                                document.getElementById("edit_full_address_hidden").value = fullAddr;
                                document.getElementById("edit_city_hidden").value         = cityText;
                                document.getElementById("edit_province_hidden").value     = editProvText;
                            }
                            // else: the hidden inputs already hold the old saved values — PHP saves those
                        });

                    })();
                    </script>

                <!-- ==============================
                     TAB: ORDERS
                ============================== -->
                <?php elseif ($tab === "orders"): ?>
    <?php
    $order_status_filter = isset($_GET['ostatus']) ? $_GET['ostatus'] : 'ALL';
    $filtered_orders = array_filter($my_orders, function($o) use ($order_status_filter) {
        return $order_status_filter === 'ALL' || $o['status'] === $order_status_filter;
    });
    $order_statuses = ['ALL','PENDING','PROCESSING','SHIPPED','DELIVERED','CANCELLED'];
    $order_counts = ['ALL' => count($my_orders)];
    foreach (['PENDING','PROCESSING','SHIPPED','DELIVERED','CANCELLED'] as $s) {
        $order_counts[$s] = count(array_filter($my_orders, fn($o) => $o['status'] === $s));
    }
    ?>
    <div class="account-card">
        <h2 class="account-card-title">My Orders</h2>

        <div class="account-status-tabs">
            <?php foreach ($order_statuses as $s): ?>
                <a href="my-account.php?tab=orders&ostatus=<?php echo $s; ?>"
                   class="account-status-tab <?php echo $order_status_filter === $s ? 'active' : ''; ?>">
                    <?php echo ucfirst(strtolower($s)); ?>
                    <span class="tab-count-small"><?php echo $order_counts[$s]; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($filtered_orders)): ?>
            <table class="account-table orders-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_orders as $order): ?>
                        <tr>
                            <!-- ORDER # + PAYMENT METHOD -->
                            <td>
                                <span class="order-ref-num">#<?php echo $order["order_id"]; ?></span>
                                <?php if (!empty($order["payment_method"])): ?>
                                    <span class="order-payment-method"><?php echo htmlspecialchars($order["payment_method"]); ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- ITEM PREVIEW: thumbnail + name + "+X more" -->
                            <td>
                                <div class="order-item-preview">
                                    <?php if (!empty($order["first_item_image"])): ?>
                                        <img src="<?php echo htmlspecialchars($order["first_item_image"]); ?>"
                                             alt="<?php echo htmlspecialchars($order["first_item_name"]); ?>"
                                             class="order-preview-thumb" />
                                    <?php endif; ?>
                                    <div class="order-preview-info">
                                        <span class="order-preview-name">
                                            <?php
                                            $name = $order["first_item_name"] ?? "";
                                            echo htmlspecialchars(mb_strlen($name) > 30 ? mb_substr($name, 0, 30) . "…" : $name);
                                            ?>
                                        </span>
                                        <?php if ($order["item_count"] > 1): ?>
                                            <span class="order-preview-more">+<?php echo $order["item_count"] - 1; ?> more item<?php echo ($order["item_count"] - 1) > 1 ? "s" : ""; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- TOTAL -->
                            <td class="order-total-cell">₱<?php echo number_format($order["total_amount"], 2); ?></td>

                            <!-- STATUS + sub-text for DELIVERED -->
                            <td>
                                <span class="status-badge <?php echo strtolower($order["status"]); ?>">
                                    <?php echo $order["status"]; ?>
                                </span>
                                <?php if ($order["status"] === "DELIVERED"): ?>
                                    <div class="order-received-sub <?php echo $order["is_received"] == 1 ? "confirmed" : "pending"; ?>">
                                        <?php echo $order["is_received"] == 1 ? "✓ Confirmed" : "Awaiting confirmation"; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- DATE -->
                            <td class="order-date-cell"><?php echo date("M j, Y", strtotime($order["created_at"])); ?></td>

                            <!-- ACTIONS -->
                            <td>
    <div class="order-actions-cell">

        <!-- VIEW — lagi nandito -->
        <a href="my-account.php?tab=order-detail&id=<?php echo $order["order_id"]; ?>"
           class="view-link">View</a>

        <!-- CANCEL — Pending lang -->
        <?php if ($order["status"] === "PENDING"): ?>
            <a href="my-account.php?tab=orders&cancel_order=<?php echo $order["order_id"]; ?>"
               class="addr-action-link addr-delete"
               onclick="return confirm('Cancel this order? This cannot be undone.')">
                Cancel
            </a>
        <?php endif; ?>

        <!-- ORDER RECEIVED — Delivered pero hindi pa confirmed -->
        <?php if ($order["status"] === "DELIVERED" && $order["is_received"] == 0): ?>
            <a href="my-account.php?tab=orders&order_received=<?php echo $order["order_id"]; ?>&ostatus=DELIVERED"
               class="btn-order-received"
               onclick="return confirm('Confirm that you have received this order?')">
                Order Received
            </a>
        <?php endif; ?>

        <!-- REVIEW — Delivered + confirmed received + may items pa hindi reviewed -->
        <?php if ($order["status"] === "DELIVERED" && $order["is_received"] == 1
                  && !empty($deliverable_items[$order["order_id"]])): ?>
            <?php
            $oid = $order["order_id"];
            $all_reviewed = true;
            foreach ($deliverable_items[$oid] as $it) {
                if (empty($reviewed_keys[$it["product_id"] . "-" . $oid])) {
                    $all_reviewed = false;
                    break;
                }
            }
            ?>
            <?php if (!$all_reviewed): ?>
                <button type="button"
                        class="btn-review-order"
                        onclick="openReviewModal(<?php echo $oid; ?>)">
                    Review
                </button>
            <?php else: ?>
                <span class="review-done-badge">Reviewed ✓</span>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data-msg">No orders found<?php echo $order_status_filter !== 'ALL' ? ' with status ' . $order_status_filter : ''; ?>.</p>
            <?php if ($order_status_filter === 'ALL'): ?>
                <a href="index.php" class="account-btn account-book-link">Start Shopping</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>


                <!-- ==============================
                     TAB: ORDER DETAIL
                ============================== -->
                <?php elseif ($tab === "order-detail" && isset($_GET["id"])): ?>
                    <?php
                    $order_id = intval($_GET["id"]);
                    $order_detail = mysqli_fetch_assoc(
                        mysqli_query($conn,
                            "SELECT orders.*, addresses.full_address, addresses.city, addresses.postal_code
                             FROM orders
                             LEFT JOIN addresses ON orders.address_id = addresses.address_id
                             WHERE orders.order_id = $order_id AND orders.user_id = $user_id"
                        )
                    );

                    // Fetch tracking history for this order
    $show_tracking = isset($_GET['track']) && $_GET['track'] == '1';
    $tracking_steps_raw = [];
    if ($order_detail) {
        $track_result = mysqli_query($conn,
            "SELECT status, note, created_at FROM order_tracking
             WHERE order_id = $order_id ORDER BY created_at ASC"
        );
        while ($tr = mysqli_fetch_assoc($track_result)) {
            $tracking_steps_raw[] = $tr;
        }
    }
    $tracking_statuses = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED'];
    // Build a keyed array: status => tracking row (or null if not yet reached)
    $tracking_map = [];
    foreach ($tracking_statuses as $ts) {
        $tracking_map[$ts] = null;
    }
    foreach ($tracking_steps_raw as $tr) {
        $tracking_map[$tr['status']] = $tr;
    }

                    if (!$order_detail): ?>
                        <div class="account-card">
                            <p class="no-data-msg">Order not found.</p>
                            <a href="my-account.php?tab=orders" class="view-link">← Back to Orders</a>
                        </div>

                    <?php else:
                        $items_result = mysqli_query($conn,
                            "SELECT order_items.*, products.product_name, products.image, products.brand
                             FROM order_items
                             JOIN products ON order_items.product_id = products.product_id
                             WHERE order_items.order_id = $order_id"
                        );
                        $order_items = [];
                        while ($row = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $row;
                        }
                    ?>

                        <div class="account-card">
                            <div class="order-detail-header">
                                <h2 class="account-card-title order-detail-title-inline">
                                    Order #<?php echo $order_id; ?>
                                </h2>
                                <a href="my-account.php?tab=orders" class="view-link">← Back to Orders</a>
                            </div>

                            <!-- Order status -->
                            <div class="order-detail-status">
                                <span class="status-badge <?php echo strtolower($order_detail["status"]); ?>">
                                    <?php echo $order_detail["status"]; ?>
                                </span>
                                <span class="order-detail-date">
                                    Placed on <?php echo date("F j, Y", strtotime($order_detail["created_at"])); ?>
                                </span>
                            </div>

                            <!-- ==============================
     TRACK ORDER BUTTON & TIMELINE
============================== -->
<?php if (in_array($order_detail["status"], ["PENDING","PROCESSING","SHIPPED","DELIVERED"])): ?>
    <div class="track-order-toggle-wrap">
        <a href="my-account.php?tab=order-detail&id=<?php echo $order_id; ?><?php echo $show_tracking ? '' : '&track=1'; ?>"
           class="track-order-btn <?php echo $show_tracking ? 'active' : ''; ?>">
            <?php echo $show_tracking ? 'Hide Tracking' : 'Track Order'; ?>
        </a>
    </div>
<?php endif; ?>

<?php if ($show_tracking): ?>
<div class="order-timeline-wrap">
    <h3 class="order-timeline-title">Order Tracking</h3>
    <div class="order-timeline">
        <?php
        $status_labels = [
            'PENDING'    => 'Order Placed',
            'PROCESSING' => 'Processing',
            'SHIPPED'    => 'Shipped',
            'DELIVERED'  => 'Delivered',
        ];
        $status_icons = [
            'PENDING'    => '🛒',
            'PROCESSING' => '⚙️',
            'SHIPPED'    => '🚚',
            'DELIVERED'  => '✅',
        ];
        $current_status = $order_detail["status"];
        $current_index  = array_search($current_status, $tracking_statuses);
        if ($current_status === 'CANCELLED') $current_index = -1;

        foreach ($tracking_statuses as $idx => $ts):
            $is_done    = $idx <= $current_index;
            $is_current = $idx === $current_index;
            $track_row  = $tracking_map[$ts];
        ?>
        <div class="timeline-step <?php echo $is_done ? 'done' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>">
            <div class="timeline-icon-col">
                <div class="timeline-dot">
                    <?php echo $is_done ? '✓' : $status_icons[$ts]; ?>
                </div>
                <?php if ($idx < count($tracking_statuses) - 1): ?>
                    <div class="timeline-line <?php echo $is_done ? 'done' : ''; ?>"></div>
                <?php endif; ?>
            </div>
            <div class="timeline-content">
                <p class="timeline-status-label"><?php echo $status_labels[$ts]; ?></p>
                <?php if ($track_row): ?>
                    <p class="timeline-note"><?php echo htmlspecialchars($track_row['note']); ?></p>
                    <p class="timeline-date"><?php echo date("M j, Y g:i A", strtotime($track_row['created_at'])); ?></p>
                <?php elseif ($is_done): ?>
                    <p class="timeline-date"><?php echo date("M j, Y", strtotime($order_detail["created_at"])); ?></p>
                <?php else: ?>
                    <p class="timeline-note timeline-pending-note">Waiting...</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

                            <!-- Delivery address -->
                            <?php if ($order_detail["full_address"]): ?>
                                <div class="order-detail-section">
                                    <h3>Delivery Address</h3>
                                    <p><?php echo $order_detail["full_address"]; ?></p>
                                    <p><?php echo $order_detail["city"]; ?>, <?php echo $order_detail["postal_code"]; ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Items ordered -->
                            <div class="order-detail-section">
                                <h3>Items Ordered</h3>
                                <table class="account-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Qty</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="order-item-cell">
                                                        <img src="<?php echo $item["image"]; ?>"
                                                             class="order-item-img"
                                                             alt="<?php echo $item["product_name"]; ?>" />
                                                        <div>
                                                            <p class="order-item-name"><?php echo $item["product_name"]; ?></p>
                                                            <p class="order-item-brand"><?php echo $item["brand"]; ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>₱<?php echo number_format($item["price"],    2); ?></td>
                                                <td><?php echo $item["quantity"]; ?></td>
                                                <td>₱<?php echo number_format($item["subtotal"], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Order summary breakdown -->
                            <?php
                            $od_subtotal  = 0;
                            foreach ($order_items as $oi) $od_subtotal += floatval($oi['subtotal']);
                            $od_shipping  = floatval($order_detail['shipping_fee']  ?? 0);
                            $od_discount  = floatval($order_detail['discount_amount'] ?? 0);
                            $od_total     = floatval($order_detail['total_amount']   ?? 0);
                            $od_deliv     = $order_detail['delivery_type'] ?? 'delivery';
                            ?>
                            <div class="order-detail-summary">

                                <!-- Order type row -->
                                <div class="od-summary-row">
                                    <span class="od-summary-label">Order Type</span>
                                    <span>
                                        <?php if ($od_deliv === 'pickup'): ?>
                                            <span class="od-type-badge od-pickup">🏬 Store Pickup</span>
                                        <?php else: ?>
                                            <span class="od-type-badge od-delivery">🚚 Home Delivery</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <!-- Subtotal -->
                                <div class="od-summary-row">
                                    <span class="od-summary-label">Subtotal</span>
                                    <span>₱<?php echo number_format($od_subtotal, 2); ?></span>
                                </div>

                                <!-- Shipping fee -->
                                <div class="od-summary-row">
                                    <span class="od-summary-label">Shipping Fee</span>
                                    <span>
                                        <?php if ($od_deliv === 'pickup'): ?>
                                            <span class="od-free-label">FREE (Store Pickup)</span>
                                        <?php elseif ($od_shipping === 0.0): ?>
                                            <span class="od-free-label">FREE</span>
                                        <?php else: ?>
                                            ₱<?php echo number_format($od_shipping, 2); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <!-- Discount — only show if applied -->
                                <?php if ($od_discount > 0): ?>
                                <div class="od-summary-row">
                                    <span class="od-summary-label">Discount</span>
                                    <span class="od-discount-val">−₱<?php echo number_format($od_discount, 2); ?></span>
                                </div>
                                <?php endif; ?>

                                <!-- Total -->
                                <div class="od-summary-row od-summary-total">
                                    <span>Total Payment</span>
                                    <span class="order-total-amount">₱<?php echo number_format($od_total, 2); ?></span>
                                </div>

                            </div>
                        </div>
                    <?php endif; ?>


                <!-- ==============================
                     TAB: APPOINTMENTS
                ============================== -->
                <?php elseif ($tab === "appointments"): ?>
    <?php
    $appt_status_filter = isset($_GET['astatus']) ? $_GET['astatus'] : 'ALL';
    $filtered_appts = array_filter($my_appointments, function($a) use ($appt_status_filter) {
        return $appt_status_filter === 'ALL' || $a['status'] === $appt_status_filter;
    });
    $appt_statuses = ['ALL','PENDING','CONFIRMED','ONGOING','COMPLETED','CANCELLED'];
    $appt_counts = ['ALL' => count($my_appointments)];
    foreach (['PENDING','CONFIRMED','ONGOING','COMPLETED','CANCELLED'] as $s) {
        $appt_counts[$s] = count(array_filter($my_appointments, fn($a) => $a['status'] === $s));
    }
    ?>
    <div class="account-card">
        <h2 class="account-card-title">My Bookings</h2>

        <div class="account-status-tabs">
            <?php foreach ($appt_statuses as $s): ?>
                <a href="my-account.php?tab=appointments&astatus=<?php echo $s; ?>"
                   class="account-status-tab <?php echo $appt_status_filter === $s ? 'active' : ''; ?>">
                    <?php echo ucfirst(strtolower($s)); ?>
                    <span class="tab-count-small"><?php echo $appt_counts[$s]; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($filtered_appts)): ?>
            <table class="account-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_appts as $appt): ?>
                        <tr>
                            <td>#<?php echo $appt["appointment_id"]; ?></td>
                            <td><?php echo htmlspecialchars($appt["service_name"]); ?></td>
                            <td>
                                <?php echo date("M j, Y", strtotime($appt["appointment_date"])); ?>
                                <br>
                                <span class="appt-time-sub">
                                    <?php echo date("g:i A", strtotime($appt["appointment_date"])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo strtolower($appt["status"]); ?>">
                                    <?php echo $appt["status"]; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (in_array($appt["status"], ["PENDING","CONFIRMED"])): ?>
                                    <a href="my-account.php?tab=appointments&cancel_appt=<?php echo $appt["appointment_id"]; ?>"
                                       class="addr-action-link addr-delete"
                                       onclick="return confirm('Cancel this appointment?')">
                                        Cancel
                                    </a>
                                <?php else: ?>
                                    <span class="no-action">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data-msg">No bookings found<?php echo $appt_status_filter !== 'ALL' ? ' with status ' . $appt_status_filter : ''; ?>.</p>
            <?php if ($appt_status_filter === 'ALL'): ?>
                <a href="services.php" class="account-btn account-book-link">Book a Service</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>


                <!-- ==============================
                     TAB: CHANGE PASSWORD
                ============================== -->
                <?php elseif ($tab === "password"): ?>
                    <div class="account-card">
                        <h2 class="account-card-title">Change Password</h2>

                        <form action="my-account.php?tab=password" method="POST">
                            <input type="hidden" name="action" value="change_password" />

                            <div class="account-form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" placeholder="Enter current password" required />
                            </div>

                            <div class="account-form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="At least 8 characters" required />
                            </div>

                            <div class="account-form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Repeat new password" required />
                            </div>

                            <button type="submit" class="account-btn">Change Password</button>
                        </form>
                    </div>

                <?php elseif ($tab === "rate-us"): ?>
                    <div class="account-card">
                        <h2 class="account-card-title">Rate Tinkercom</h2>
                        <p style="color:#666; font-size:14px; margin-bottom:24px;">
                            Share your overall experience with our store. Your feedback helps us improve!
                        </p>

                        <?php if ($my_site_review): ?>
                            <div class="site-review-existing">
                                <p class="site-review-existing-label">Your current rating:</p>
                                <div class="site-review-existing-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="<?php echo $i <= $my_site_review['rating'] ? 'srs-star active' : 'srs-star'; ?>">★</span>
                                    <?php endfor; ?>
                                    <span class="site-review-existing-date">
                                        — submitted <?php echo date("F j, Y", strtotime($my_site_review['created_at'])); ?>
                                    </span>
                                </div>
                                <?php if (!empty($my_site_review['comment'])): ?>
                                    <p class="site-review-existing-comment">"<?php echo htmlspecialchars($my_site_review['comment']); ?>"</p>
                                <?php endif; ?>
                                <p style="font-size:13px; color:#888; margin-top:10px;">You can update your review below.</p>
                            </div>
                        <?php endif; ?>

                        <form action="my-account.php?tab=rate-us" method="POST" id="site-review-form">
                            <input type="hidden" name="action"      value="submit_site_review" />
                            <input type="hidden" name="site_rating" id="site-rating-val"
                                   value="<?php echo $my_site_review ? $my_site_review['rating'] : ''; ?>" />

                            <div class="account-form-group">
                                <label>Your Rating</label>
                                <div class="srs-row" id="srs-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="srs-star-input <?php echo ($my_site_review && $i <= $my_site_review['rating']) ? 'active' : ''; ?>"
                                              data-val="<?php echo $i; ?>"
                                              onclick="setSiteRating(<?php echo $i; ?>)">★</span>
                                    <?php endfor; ?>
                                </div>
                                <p class="srs-label" id="srs-label">
                                    <?php
                                    $labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                                    echo $my_site_review ? $labels[$my_site_review['rating']] : 'Tap a star to rate';
                                    ?>
                                </p>
                            </div>

                            <div class="account-form-group">
                                <label>Your Review <span style="font-weight:400; color:#aaa;">(optional)</span></label>
                                <textarea name="site_comment" class="review-textarea"
                                          placeholder="Tell us about your experience shopping at Tinkercom..."
                                          maxlength="1000"><?php echo $my_site_review ? htmlspecialchars($my_site_review['comment']) : ''; ?></textarea>
                            </div>

                            <button type="submit" class="account-btn">
                                <?php echo $my_site_review ? 'Update My Review' : 'Submit Review'; ?>
                            </button>
                        </form>
                    </div>

                    <script>
                    function setSiteRating(val) {
                        document.getElementById('site-rating-val').value = val;
                        var stars  = document.querySelectorAll('.srs-star-input');
                        var labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                        stars.forEach(function(s, idx) { s.classList.toggle('active', idx < val); });
                        var lbl = document.getElementById('srs-label');
                        lbl.textContent = labels[val];
                        lbl.style.color = '#555';
                    }
                    document.getElementById('site-review-form').addEventListener('submit', function(e) {
                        if (!document.getElementById('site-rating-val').value) {
                            e.preventDefault();
                            var lbl = document.getElementById('srs-label');
                            lbl.textContent = 'Please select a star rating!';
                            lbl.style.color = '#e53e3e';
                        }
                    });
                    </script>

                <?php endif; ?>

            </div><!-- end account-content -->

        </div><!-- end account-layout -->

    </section>
    <!-- ==============================
         REVIEW MODAL
    ============================== -->
    <div id="review-modal-overlay" class="review-modal-overlay" style="display:none;" onclick="closeReviewModal(event)">
        <div class="review-modal-box">
            <button class="review-modal-close" onclick="closeReviewModal(null)">✕</button>
            <h2 class="review-modal-title">Rate Your Purchase</h2>
            <p class="review-modal-sub">Select a product below to leave your review.</p>

            <!-- Product tabs (filled by JS) -->
            <div class="review-product-tabs" id="review-product-tabs"></div>

            <!-- Review form -->
            <form action="my-account.php?tab=orders&ostatus=DELIVERED" method="POST" id="review-form">
                <input type="hidden" name="action"     value="submit_review" />
                <input type="hidden" name="order_id"   id="review-order-id"   value="" />
                <input type="hidden" name="product_id" id="review-product-id" value="" />
                <input type="hidden" name="rating"     id="review-rating-val" value="" />

                <!-- Product image + name preview -->
                <div class="review-product-preview">
                    <img src="" alt="" id="review-product-img" class="review-product-img" />
                    <p id="review-product-name" class="review-product-name"></p>
                </div>

                <!-- Star rating -->
                <div class="review-stars-row" id="review-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="review-star" data-val="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">★</span>
                    <?php endfor; ?>
                </div>
                <p class="review-star-label" id="review-star-label">Tap a star to rate</p>

                <!-- Comment -->
                <textarea name="comment" class="review-textarea"
                          placeholder="Share your experience with this product (optional)"
                          maxlength="1000"></textarea>

                <button type="submit" class="account-btn review-submit-btn">Submit Review</button>
            </form>
        </div>
    </div>

    <?php
    // Encode deliverable_items as JSON for JS
    $deliverable_json = json_encode($deliverable_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    $reviewed_json    = json_encode($reviewed_keys,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <script>
    var deliverableItems = <?php echo $deliverable_json; ?>;
    var reviewedKeys     = <?php echo $reviewed_json; ?>;

    function openReviewModal(orderId) {
        var items   = deliverableItems[orderId] || [];
        var overlay = document.getElementById('review-modal-overlay');
        var tabs    = document.getElementById('review-product-tabs');

        // Filter out already-reviewed products
        var pending = items.filter(function(it) {
            return !reviewedKeys[it.product_id + '-' + orderId];
        });

        if (pending.length === 0) return;

        // Set order ID
        document.getElementById('review-order-id').value = orderId;

        // Build product tabs
        tabs.innerHTML = '';
        pending.forEach(function(it, idx) {
            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'review-tab-btn' + (idx === 0 ? ' active' : '');
            btn.textContent = it.product_name.length > 22
                              ? it.product_name.substring(0, 22) + '…'
                              : it.product_name;
            btn.onclick = function() {
                document.querySelectorAll('.review-tab-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                selectProduct(it);
            };
            tabs.appendChild(btn);
        });

        // Select first product
        selectProduct(pending[0]);

        // Reset rating
        setRating(0);

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function selectProduct(item) {
        document.getElementById('review-product-id').value   = item.product_id;
        document.getElementById('review-product-img').src    = item.image;
        document.getElementById('review-product-img').alt    = item.product_name;
        document.getElementById('review-product-name').textContent = item.product_name;
        // Reset stars when switching product
        setRating(0);
    }

    function closeReviewModal(event) {
        if (event && event.target !== document.getElementById('review-modal-overlay')) return;
        document.getElementById('review-modal-overlay').style.display = 'none';
        document.body.style.overflow = '';
    }

    function setRating(val) {
        document.getElementById('review-rating-val').value = val;
        var stars = document.querySelectorAll('.review-star');
        var labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        stars.forEach(function(s, idx) {
            s.classList.toggle('active', idx < val);
        });
        document.getElementById('review-star-label').textContent =
            val > 0 ? labels[val] : 'Tap a star to rate';
    }

    // Validate rating before submit
    document.getElementById('review-form').addEventListener('submit', function(e) {
        if (!document.getElementById('review-rating-val').value) {
            e.preventDefault();
            document.getElementById('review-star-label').textContent = 'Please select a star rating!';
            document.getElementById('review-star-label').style.color = '#e53e3e';
        }
    });
    </script>

    <script src="javascript.js"></script>

</body>

</html>