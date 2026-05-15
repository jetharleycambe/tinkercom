<?php

define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

if (isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];
    if ($elapsed > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: index.php?expired=1");
        exit;
    }
}
$_SESSION['last_activity'] = time();