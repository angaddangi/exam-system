<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    // update logout_time on most recent login_history row for this user
    $uid = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE login_history SET logout_time = NOW()
                          WHERE user_id = $uid AND logout_time IS NULL
                          ORDER BY history_id DESC LIMIT 1");
}

session_destroy();
header("Location: /login.php");
exit;
