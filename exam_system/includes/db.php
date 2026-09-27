<?php
// ============================================================
// DATABASE CONNECTION
// No hashing / no encryption used anywhere in this project,
// as requested. Passwords are stored and compared in plain text.
// ============================================================
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "online_exam_system";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
