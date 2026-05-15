<?php

session_start();
include 'db.php';

// Must be logged in to access this page
if (!isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = intval($_SESSION['customer_id']);
$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'profile'; // 'profile' or 'address'

// ── Fetch current user data ──────────────────────────────────
$user = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id")
);

// If already complete, skip straight to homepage
if ($user['account_info'] == 1) {
    header('Location: index.php');
    exit;
}

// ── STEP 1: Save Profile ─────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    $first_name = trim(mysqli_real_escape_string($conn, $_POST['first_name'] ?? ''));
    $last_name = trim(mysqli_real_escape_string($conn, $_POST['last_name'] ?? ''));
    $phone = trim(mysqli_real_escape_string($conn, $_POST['phone'] ?? ''));
    $username = trim(mysqli_real_escape_string($conn, $_POST['username'] ?? ''));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email'] ?? ''));

    $phone_regex = '/^(09\d{9}|(\+63)9\d{9})$/';

    if ($first_name === '' || $last_name === '') {
        $error = 'First name and last name are required.';
    } elseif ($phone !== '' && !preg_match($phone_regex, $phone)) {
        $error = 'Phone number must be in PH format (e.g. 09171234567).';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        mysqli_query(
            $conn,
            "UPDATE users SET
                first_name = '$first_name',
                last_name  = '$last_name',
                phone      = '$phone',
                username   = '$username',
                email      = '$email'
             WHERE user_id = $user_id"
        );
        $_SESSION['customer_name'] = $username;
        // Move to step 2
        header('Location: account-info.php?step=address');
        exit;
    }
}

// ── STEP 2: Save Address ─────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_address') {
    $full_address = trim(mysqli_real_escape_string($conn, $_POST['full_address'] ?? ''));
    $city = trim(mysqli_real_escape_string($conn, $_POST['city'] ?? ''));
    $postal_code = trim(mysqli_real_escape_string($conn, $_POST['postal_code'] ?? ''));

    if ($full_address === '' || $city === '') {
        $error = 'Please complete all address fields.';
        $step = 'address';
    } else {
        // Insert address as default
        mysqli_query(
            $conn,
            "INSERT INTO addresses (user_id, full_address, city, postal_code, is_default)
             VALUES ($user_id, '$full_address', '$city', '$postal_code', 1)"
        );
        // Mark account as complete
        mysqli_query(
            $conn,
            "UPDATE users SET account_info = 1 WHERE user_id = $user_id"
        );
        // Redirect to homepage
        header('Location: index.php?welcome=1');
        exit;
    }
}

// ── Skip address (optional — user can add later from my-account) ──
if (isset($_GET['skip_address'])) {
    mysqli_query($conn, "UPDATE users SET account_info = 1 WHERE user_id = $user_id");
    header('Location: index.php?welcome=1');
    exit;
}

// ── Re-fetch user after possible update ──────────────────────
$user = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id")
);
$first_name = $user['first_name'] ?? '';
$last_name = $user['last_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body class="background">

    <?php include 'header.php'; ?>

    <section class="setup-page">
        <div class="setup-container">

            <!-- Progress Steps -->
            <div class="setup-progress">
                <div class="setup-step <?php echo $step === 'profile' ? 'active' : 'done'; ?>">
                    <div class="step-circle">
                        <?php echo $step === 'profile' ? '1' : '✓'; ?>
                    </div>
                    <span>Your Info</span>
                </div>
                <div class="setup-step-line"></div>
                <div class="setup-step <?php echo $step === 'address' ? 'active' : ''; ?>">
                    <div class="step-circle">2</div>
                    <span>Your Address</span>
                </div>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>


            <!-- =============================================
             STEP 1: PROFILE INFO
             ============================================= -->
            <?php if ($step === 'profile'): ?>

                <div class="setup-card">
                    <div class="setup-card-header">
                        <h2>Welcome to Tinkercom! </h2>
                        <p>Hi, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!
                            Let's set up your profile so you can start shopping.</p>
                    </div>

                    <form action="account-info.php?step=profile" method="POST" class="setup-form">
                        <input type="hidden" name="action" value="save_profile">

                        <div class="account-form-row">
                            <div class="account-form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>"
                                    placeholder="e.g. Juan" required autocomplete="given-name">
                            </div>
                            <div class="account-form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>"
                                    placeholder="e.g. dela Cruz" required autocomplete="family-name">
                            </div>
                        </div>

                        <div class="account-form-row">
                            <div class="account-form-group">
                                <label>Username <span class="required">*</span></label>
                                <input type="text" name="username"
                                    value="<?php echo htmlspecialchars($user['username']); ?>" required
                                    autocomplete="username">
                            </div>
                            <div class="account-form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                                    placeholder="your@email.com" autocomplete="email">
                            </div>
                        </div>

                        <div class="account-form-row">
                            <div class="account-form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    placeholder="e.g. 09171234567" maxlength="13" autocomplete="tel">
                                <small class="field-hint">PH format: 09XXXXXXXXX or +639XXXXXXXXX</small>
                            </div>
                        </div>

                        <div class="setup-actions">
                            <button type="submit" class="account-btn setup-btn-next">
                                Continue to Address →
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>


            <!-- =============================================
             STEP 2: ADDRESS
             ============================================= -->
            <?php if ($step === 'address'): ?>

                <div class="setup-card">
                    <div class="setup-card-header">
                        <h2>Add Your Delivery Address</h2>
                        <p>This will be used as your default address for orders and deliveries.</p>
                    </div>

                    <form action="account-info.php?step=address" method="POST" id="setup-address-form" class="setup-form">
                        <input type="hidden" name="action" value="save_address">
                        <input type="hidden" name="full_address" id="setup_full_address_hidden">
                        <input type="hidden" name="city" id="setup_city_hidden">

                        <!-- Street -->
                        <div class="account-form-group">
                            <label>House/Unit No. & Street <span class="required">*</span></label>
                            <input type="text" id="setup_street" placeholder="e.g. Blk 6 Lot 4, Alegra Heights St."
                                required>
                        </div>

                        <!-- Region & Province -->
                        <div class="account-form-row">
                            <div class="account-form-group">
                                <label>Region <span class="required">*</span></label>
                                <select id="setup_region" required>
                                    <option value="">-- Select Region --</option>
                                </select>
                            </div>
                            <div class="account-form-group">
                                <label>Province / Independent City <span class="required">*</span></label>
                                <select id="setup_province" disabled required>
                                    <option value="">-- Select Province/City --</option>
                                </select>
                            </div>
                        </div>

                        <!-- City/Municipality & Barangay -->
                        <div class="account-form-row">
                            <div class="account-form-group">
                                <label>City / Municipality <span class="required">*</span></label>
                                <select id="setup_city_mun" disabled required>
                                    <option value="">-- Select City/Municipality --</option>
                                </select>
                            </div>
                            <div class="account-form-group">
                                <label>Barangay <span class="required">*</span></label>
                                <select id="setup_barangay" disabled required>
                                    <option value="">-- Select Barangay --</option>
                                </select>
                            </div>
                        </div>

                        <!-- Postal Code -->
                        <div class="account-form-row">
                            <div class="account-form-group">
                                <label>Postal Code</label>
                                <input type="text" name="postal_code" placeholder="e.g. 3022" maxlength="4">
                            </div>
                        </div>

                        <div class="setup-actions">
                            <button type="submit" class="account-btn setup-btn-next">
                                Complete Setup
                            </button>
                            <a href="account-info.php?skip_address=1" class="setup-skip-link">
                                Skip for now — I'll add my address later
                            </a>
                        </div>
                    </form>
                </div>

                <!-- PSGC Dropdown Script — same logic as my-account.php -->
                <script>
                    const PSGC = "https://psgc.gitlab.io/api";

                    function fillSelect(sel, items, placeholder) {
                        sel.innerHTML = `<option value="">${placeholder}</option>`;
                        items.sort((a, b) => a.name.localeCompare(b.name))
                            .forEach(item => {
                                const opt = document.createElement("option");
                                opt.value = item.code;
                                opt.textContent = item.name;
                                sel.appendChild(opt);
                            });
                        sel.disabled = false;
                    }

                    function resetSelects(...selects) {
                        selects.forEach((sel, i) => {
                            const labels = [
                                "-- Select Province/City --",
                                "-- Select City/Municipality --",
                                "-- Select Barangay --"
                            ];
                            sel.innerHTML = `<option value="">${labels[i] || "-- Select --"}</option>`;
                            sel.disabled = true;
                        });
                    }

                    function loadBarangays(code, brgySelect) {
                        Promise.all([
                            fetch(`${PSGC}/cities/${code}/barangays/`).then(r => r.json()).catch(() => []),
                            fetch(`${PSGC}/municipalities/${code}/barangays/`).then(r => r.json()).catch(() => [])
                        ]).then(([b1, b2]) => {
                            const all = [...b1, ...b2];
                            if (all.length > 0) {
                                brgySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
                                all.sort((a, b) => a.name.localeCompare(b.name))
                                    .forEach(item => {
                                        const opt = document.createElement("option");
                                        opt.value = item.name;
                                        opt.textContent = item.name;
                                        brgySelect.appendChild(opt);
                                    });
                                brgySelect.disabled = false;
                            }
                        });
                    }

                    function loadCities(code, citySelect, brgySelect) {
                        resetSelects(citySelect, brgySelect);
                        Promise.all([
                            fetch(`${PSGC}/provinces/${code}/cities/`).then(r => r.json()).catch(() => []),
                            fetch(`${PSGC}/provinces/${code}/municipalities/`).then(r => r.json()).catch(() => []),
                            fetch(`${PSGC}/cities/${code}/barangays/`).then(r => r.json()).catch(() => [])
                        ]).then(([cities, munis, directBrgys]) => {
                            const combined = [...cities, ...munis];
                            if (combined.length > 0) {
                                fillSelect(citySelect, combined, "-- Select City/Municipality --");
                            } else if (directBrgys.length > 0) {
                                // Independent city — skip city level, load barangays directly
                                const provText = document.getElementById("setup_province")
                                    .options[document.getElementById("setup_province").selectedIndex]?.text || "";
                                citySelect.innerHTML = `<option value="${code}">${provText}</option>`;
                                citySelect.disabled = false;
                                loadBarangays(code, brgySelect);
                            }
                        });
                    }

                    const regSel = document.getElementById("setup_region");
                    const provSel = document.getElementById("setup_province");
                    const citySel = document.getElementById("setup_city_mun");
                    const brgySel = document.getElementById("setup_barangay");

                    // Load regions on page load
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

                    // Province → City/Municipality  ← THIS WAS THE BUG: must use this.value (code), not text
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
                        brgySel.disabled = true;
                        if (!code) return;
                        loadBarangays(code, brgySel);
                    });

                    // On submit — assemble full_address
                    document.getElementById("setup-address-form").addEventListener("submit", function (e) {
                        const street = document.getElementById("setup_street").value.trim();
                        const barangay = brgySel.value;
                        const cityText = citySel.options[citySel.selectedIndex]?.text || "";
                        const provText = provSel.options[provSel.selectedIndex]?.text || "";

                        if (!street || !barangay || !cityText || !provText) {
                            e.preventDefault();
                            alert("Please fill in all address fields: street, region, province, city/municipality, and barangay.");
                            return;
                        }

                        document.getElementById("setup_full_address_hidden").value = street + ", " + barangay + ", " + cityText;
                        document.getElementById("setup_city_hidden").value = cityText;
                    });
                </script>

            <?php endif; ?>

        </div>
    </section>

</body>

</html>