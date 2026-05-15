<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);
  $email = trim($_POST["email"]);
  $password = trim($_POST["password"]);
  $confirm = trim($_POST["confirm_password"]);

  if ($username === "" && $email === "" && $password === "" && $confirm === "") {
    echo "Input username/email and password.";
    exit;
  }
  if ($username !== "" && $email === "" && $password === "") {
    echo "Input email and password.";
    exit;
  }
  if ($username !== "" && $email !== "" && $password === "") {
    echo "Password is required.";
    exit;
  }
  if ($confirm === "") {
    echo "Confirm your password.";
    exit;
  }
  if (strlen($password) < 8) {
    echo "Password must be at least 8 characters.";
    exit;
  }
  if ($password !== $confirm) {
    echo "Password does not match.";
    exit;
  }

  $check = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$username' OR email = '$email'");
  if (mysqli_num_rows($check) > 0) {
    echo "Account already exists.";
    exit;
  }

  $hashed = password_hash($password, PASSWORD_DEFAULT);
  $sql = "INSERT INTO users (username, email, password, account_info)
          VALUES ('$username', '$email', '$hashed', 0)"; 

  if (mysqli_query($conn, $sql)) {
    $new_user_id = mysqli_insert_id($conn);
    mysqli_query($conn, "INSERT INTO user_roles (user_id, role_id) VALUES ('$new_user_id', 1)");
    echo "login";
    exit;
  } else {
    echo "Something went wrong. Please try again.";
    exit;
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | Tinkercom</title>
  <link rel="stylesheet" href="style.css">
  <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>

<body id="register-body">
  <main>
    <div class="register">
      <form action="register.php" method="POST" class="reg-form" id="reg-form">
        <h1>Welcome to Tinkercom</h1>
        <h3>Create your Account</h3>
        <p class="error-message"></p>

        <!-- PHP prints the error or success message here -->
        
      
        <div>
            <label for="reg-username">Username</label>
            <input id="reg-username" type="text" name="username" />
        </div>

        <div>
          <label for="reg-email">Email</label>
          <input id="reg-email" type="email" name="email" />
        </div>

        <div>
          <label for="reg-pass" id="reg-lbl-pass">Password</label>
          <input id="reg-pass" type="password" name="password" />

          <p class="strength-text"></p>
          <ul class="password-hints">
            <li id="len">At least 8 characters</li>
            <li id="upper">At least 1 uppercase letter</li>
            <li id="num">At least 1 number</li>
            <li id="sym">At least 1 special character</li>
          </ul>
        </div>

        <div>
          <label for="reg-conpass" id="reg-lbl-conpass">Confirm Password</label>
          <input id="reg-conpass" type="password" name="confirm_password" />
        </div>

        <div>
          <button id="register-btn" type="submit">Register</button>
        </div>

        <div>
          <p id="reg-to-log">
            Already have an account?
            <a href="login.php">Login.</a>
          </p>
        </div>
      </form>
    </div>
  </main>
  <script src="javascript.js"></script>
</body>
<?php include 'footer.php'; ?>

</html>

