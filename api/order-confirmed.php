<?php
session_start();

if (!isset($_SESSION["last_order_id"])) {
  header("Location: index.php");
  exit;
}

$order_id = $_SESSION["last_order_id"];
unset($_SESSION["last_order_id"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Order Confirmed | Tinkercom</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="background">

  <header class="home-header">
    <div class="home-logo">
      <a href="index.php"><img src="assets/tinkercom-logoe.png" alt=""/></a>
      <h4>Tinkercom</h4>
    </div>
    <div class="header-icons">
      <a href="wishlist.php"><img src="assets/love.png" alt=""/></a>
      <a href="cart.php"><img src="assets/shopping-cart.png" alt=""/></a>
      <a href="login.php"><img src="assets/user.png" alt=""/></a>
    </div>
  </header>

  <nav class="navigation">
    <ul>
      <a href="index.php">HOME</a>
      <a href="printers.php">PRINTERS</a>
      <a href="accessories.php">ACCESSORIES</a>
      <a href="peripherals.php">PERIPHERALS</a>
      <a href="services.php">SERVICES</a>
    </ul>
  </nav>

  <section class="order-confirmed">
    <div class="order-confirmed-container">

      <div class="check">
        ✓
      </div>

      <h1>Order Confirmed!</h1>
      <p style="color:#888; font-size:14px; margin-bottom:6px;">Thank you for your order.</p>
      <p class="order-no">
        Order #<?php echo $order_id; ?>
      </p>

      <div class="view-order-or-continue-shopping">
        <a href="my-account.php?tab=orders" class="view-order">
          View My Orders
        </a>
        <a href="index.php" class="continue-shopping">
          Continue Shopping
        </a>
      </div>

    </div>
  </section>

</body>
</html>