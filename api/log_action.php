<?php
// log_action.php — Call this anytime you want to record an activity
// Usage: log_action($conn, 'ADMIN', $admin_id, $admin_name, 'Edited Product', 'Updated Epson L121 price');

function log_action($conn, $actor_type, $actor_id, $actor_name, $action, $description = '') {
    $actor_type  = mysqli_real_escape_string($conn, $actor_type);
    $actor_id    = intval($actor_id);
    $actor_name  = mysqli_real_escape_string($conn, $actor_name);
    $action      = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);

    mysqli_query($conn, "INSERT INTO audit_logs (actor_type, actor_id, actor_name, action, description)
                         VALUES ('$actor_type', $actor_id, '$actor_name', '$action', '$description')");
}
?>