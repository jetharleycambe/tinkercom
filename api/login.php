<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);
  $password = trim($_POST["password"]);

  // Validation code remains the same...
  if ($username === "" && $password === "") {
    echo "Input username/email and password.";
    exit;
  }
  if ($username === "") {
    echo "Username/Email is required.";
    exit;
  }
  if ($password === "") {
    echo "Password is required.";
    exit;
  }

  $sql = "SELECT * FROM users WHERE username = '$username' OR email = '$username'";
  $result = mysqli_query($conn, $sql);

  if (mysqli_num_rows($result) !== 1) {
    echo "Account does not exist.";
    exit;
  }

  $user = mysqli_fetch_assoc($result);

  if (!password_verify($password, $user["password"])) {
    echo "Password is incorrect.";
    exit;
  }

  // SUCCESS LOGIN
  $_SESSION["customer_id"] = $user["user_id"];
  $_SESSION["customer_name"] = $user["username"];

  $role_sql = "SELECT roles.role_name
               FROM user_roles
               JOIN roles ON user_roles.role_id = roles.role_id
               WHERE user_roles.user_id = '" . $user["user_id"] . "'";

  $role_result = mysqli_query($conn, $role_sql);
  $role_row = mysqli_fetch_assoc($role_result);
  $_SESSION["role"] = $role_row["role_name"];

  if ($_SESSION["role"] === "ADMIN") {
    echo "admin";
    exit;
  }

  // Check if profile is complete
  if ($user["account_info"] == 0) {
    echo "account-info"; // needs profile setup
    exit;
  }

  // Profile complete — check if they have at least one address
  $addr_check = mysqli_query(
    $conn,
    "SELECT address_id FROM addresses WHERE user_id = " . $user["user_id"] . " LIMIT 1"
  );

  if (mysqli_num_rows($addr_check) === 0) {
    echo "account-info-address"; // has profile but no address
  } else {
    echo "index"; // fully complete
    exit;
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="assets/logo/logo.png" type="image/x-icon">
  <link rel="stylesheet" href="style.css">
  <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;60
0&display=swap">

  <title>Login | Tinkercom</title>
</head>

<body id="log-in-body">
  <main>
    <div class="log-in">
      <form action="login.php" method="POST" class="login-form" id="login-form">
        <h1>Welcome to Tinkercom</h1>
        <h3>Login</h3>
        <p class="error-message"></p>

        <!-- PHP prints the error here -->

        <div>
          <label for="log-username" id="login-lbl-user">Username/Email</label>
          <input id="log-username" type="text" name="username" />
        </div>

        <div>
          <label for="log-pass" id="login-lbl-pass">Password</label>
          <input id="log-pass" type="password" name="password" />
        </div>

        <div>
          <button id="log-in-btn" type="submit">Login</button>
        </div>

        <div>
          <p id="log-to-reg">
            Don't have an account yet?
            <a id="" href="register.php">Create your account.</a>
          </p>
        </div>
      </form>
    </div>
  </main>
  <script src="javascript.js"></script>
</body>

</html>