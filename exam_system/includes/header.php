<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " - " : ""; ?>Quick Entrance Exam System</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="navbar">
    <a href="/dashboard.php" class="brand">&#9733; Quick Entrance Exam System</a>
    <div class="nav-links">
        <?php if (is_logged_in()): ?>
            <a href="/dashboard.php">Dashboard</a>
            <?php if ($_SESSION['role'] === 'teacher'): ?>
                <a href="/teacher/manage_questions.php">Question Bank</a>
                <a href="/teacher/create_exam.php">Create Exam</a>
            <?php else: ?>
                <a href="/student/select_exam.php">Exams</a>
                <a href="/student/history.php">History</a>
            <?php endif; ?>
            <a href="/logout.php">Logout</a>
        <?php else: ?>
            <a href="/login.php">Login</a>
            <a href="/register.php">Create Account</a>
        <?php endif; ?>
    </div>
</div>
<div class="container">
