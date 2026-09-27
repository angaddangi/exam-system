<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$page_title = "Dashboard";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> &#128081;</h1>
    <p>Role: <strong style="color:var(--purple-main); text-transform:capitalize;"><?php echo htmlspecialchars($user['role']); ?></strong></p>
</div>

<?php if ($user['role'] === 'student'): ?>
    <div class="card">
        <h2>Candidate Dashboard</h2>
        <p>Ready to test your knowledge? Select an exam and start whenever you're ready.</p>
        <a href="/student/select_exam.php" class="btn btn-primary">Select Exam</a>
        <a href="/student/history.php" class="btn btn-secondary">View History</a>
    </div>
<?php else: ?>
    <div class="card">
        <h2>Teacher Dashboard</h2>
        <p>Add questions to subjects you're assigned to, and create exams that draw random questions from your question bank.</p>
        <a href="/teacher/manage_questions.php" class="btn btn-primary">Manage Question Bank</a>
        <a href="/teacher/create_exam.php" class="btn btn-secondary">Create Exam</a>
        <a href="/teacher/my_exams.php" class="btn btn-secondary">My Exams</a>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
