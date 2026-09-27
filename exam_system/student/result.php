<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('student');

$attempt_id = (int)($_GET['attempt_id'] ?? 0);
$attempt = get_attempt($conn, $attempt_id);
$user = current_user();

if (!$attempt || $attempt['student_id'] != $user['user_id']) {
    header("Location: /student/select_exam.php");
    exit;
}

$exam = get_exam($conn, $attempt['exam_id']);
$result = get_result($conn, $attempt_id);

// Respect the exam's "show_result_after" privacy setting
$can_show_result = true;
$message = "";
if ($exam['show_result_after'] === 'after_deadline' && strtotime($exam['submission_deadline']) > time()) {
    $can_show_result = false;
    $message = "Your result will be available after the submission deadline (" . htmlspecialchars($exam['submission_deadline']) . ").";
} elseif ($exam['show_result_after'] === 'manual') {
    $can_show_result = false;
    $message = "Your result will be released manually by the teacher.";
}

$page_title = "Exam Result";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2><?php echo htmlspecialchars($exam['title']); ?> - Result</h2>

    <?php if (!$can_show_result): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php else: ?>
        <div class="result-score"><?php echo $result['total_marks_obtained']; ?> / <?php echo $result['full_marks']; ?></div>
        <p style="text-align:center; font-size:20px;">
            Pass Marks: <?php echo $result['pass_marks']; ?> &nbsp;|&nbsp;
            Status:
            <span class="result-status-<?php echo $result['result_status']; ?>">
                <?php echo strtoupper($result['result_status']); ?>
            </span>
        </p>
    <?php endif; ?>

    <p class="meta" style="text-align:center;">Submitted at: <?php echo htmlspecialchars($attempt['submitted_at']); ?></p>

    <div style="text-align:center; margin-top:20px;">
        <a href="/student/select_exam.php" class="btn btn-secondary">Back to Exams</a>
        <a href="/student/history.php" class="btn btn-primary">View History</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
