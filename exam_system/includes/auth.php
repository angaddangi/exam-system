<?php
session_start();
require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: /login.php");
        exit;
    }
}

function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header("Location: /dashboard.php");
        exit;
    }
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}
