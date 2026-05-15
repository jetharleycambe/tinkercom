<?php
session_start();
// if (!isset($_SESSION["customer_name"])) {
//   header("Location: login.php");
//   exit;
// }

// ── Service catalogue (price in PHP ₱, duration in minutes) ─────────────────
function tinker_format_duration($minutes)
{
    if ($minutes < 60)
        return $minutes . " min";
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . " hr" . ($h > 1 ? "s" : "") . ($m ? " {$m} min" : "");
}

$all_services = [
    ["name" => "Predictive and Preventive Maintenance", "img" => "assets/services/Repair_or_replace_laptop.png", "desc" => "Stop issues before they start. We analyze your system's health and perform proactive tuning to extend its lifespan and prevent sudden hardware failure.", "price" => 500, "duration" => 60],
    ["name" => "Basic and Deep Cleaning", "img" => "assets/services/basic and deep cleaning.webp", "desc" => "Remove the grime you can see and the dust you can't. From surface sanitization to internal dust removal and thermal paste re-application, we keep your machine cool and spotless.", "price" => 350, "duration" => 45],
    ["name" => "Re-thermal Services", "img" => "assets/services/re thermal.webp", "desc" => "Combat overheating and fan noise. We clean your cooling system and apply premium thermal compound to help your processor run at peak efficiency.", "price" => 300, "duration" => 30],
    ["name" => "Keyboard Replacement", "img" => "assets/services/keyboard replacement.jpeg", "desc" => "Restore your typing experience. We replace sticky, unresponsive, or missing keys with brand-new, high-quality keyboard units.", "price" => 800, "duration" => 60],
    ["name" => "Operating System Installation", "img" => "assets/services/os installing.jpg", "desc" => "Get a fresh start with a clean slate. We install the latest OS versions, ensuring your software environment is stable, secure, and properly configured.", "price" => 600, "duration" => 90],
    ["name" => "SSD Installation and Upgrade", "img" => "assets/services/ssd installation.png", "desc" => "Supercharge your boot times and file transfers. We swap slow hard drives for high-speed Solid State Drives, giving your old computer a modern speed boost.", "price" => 500, "duration" => 45],
    ["name" => "Laptop Screen Replacement", "img" => "assets/services/screen replacement.jpg", "desc" => "Clear up your view. We fix cracked displays, dead pixels, and flickering screens with vibrant, high-resolution replacement panels.", "price" => 1500, "duration" => 90],
    ["name" => "Battery Assessment and Installation", "img" => "assets/services/battery installation.jpeg", "desc" => "Power your portability. We test your battery health and install genuine replacements to ensure your laptop stays charged when you're on the move.", "price" => 700, "duration" => 45],
    ["name" => "RAM Upgrade", "img" => "assets/services/ram upgrade.avif", "desc" => "Stop the lagging and start multitasking. Increasing your memory allows you to run more apps simultaneously and improves overall system responsiveness.", "price" => 400, "duration" => 30],
    ["name" => "Computer Desktop Services", "img" => "assets/services/computer desktop services.jpg", "desc" => "Full-service support for your workstation. From custom builds and cable management to complex hardware troubleshooting, we handle all your desktop needs.", "price" => 800, "duration" => 120],
    ["name" => "Application and Drivers Update", "img" => "assets/services/application and driver update.png", "desc" => "Keep your software running smoothly. We ensure your critical apps and hardware drivers are current to improve compatibility and patch security vulnerabilities.", "price" => 250, "duration" => 30],
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | Tinkercom</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;60
0&display=swap">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body class="background">
    <?php include 'header.php'; ?>


    <main class="services-main">
        <section class="services-sec">
            <h1>Services</h1>

            <div class="services-container">
                <?php foreach ($all_services as $svc): ?>
                    <div class="booking-container">
                        <div class="service-title">
                            <h3><?php echo htmlspecialchars($svc["name"]); ?></h3>
                        </div>
                        <div class="service-img">
                            <img src="<?php echo $svc["img"]; ?>" alt="<?php echo htmlspecialchars($svc["name"]); ?>">
                        </div>
                        <div class="service-description">
                            <p><?php echo $svc["desc"]; ?></p>

                            <!-- ── Price & Duration badges ── -->
                            <div class="service-meta">
                                <span class="service-price">₱<?php echo number_format($svc["price"], 2); ?></span>
                                <span class="service-duration">⏱
                                    <?php echo tinker_format_duration($svc["duration"]); ?></span>
                            </div>
                        </div>



                        <div class="book-now">
                            <?php if (isset($_SESSION['customer_id'])): ?>
                                <a href="services-details.php?service=<?php echo urlencode($svc['name']); ?>">
                                    <button>BOOK NOW</button>
                                </a>
                            <?php else: ?>
                                <a href="#" onclick="openLoginModal()">
                                    <button>BOOK NOW</button>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </section>
    </main>


    <?php include 'login-modal.php'; ?>
    <?php include 'footer.php'; ?>
    <script src="javascript.js"></script>

</body>

</html>