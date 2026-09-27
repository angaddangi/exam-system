<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('student');

$exam_id = (int)($_GET['exam_id'] ?? 0);
$exam = get_exam($conn, $exam_id);
$user = current_user();

if (!$exam) {
    header("Location: /student/select_exam.php");
    exit;
}

// Enforce submission deadline before allowing a new attempt to start
if (strtotime($exam['submission_deadline']) < time() && !get_existing_attempt($conn, $exam_id, $user['user_id'])) {
    $page_title = "Deadline Passed";
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card"><div class="alert alert-error">The submission deadline for this exam has passed.</div>
          <a href="/student/select_exam.php" class="btn btn-secondary">Back to Exams</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$attempt_id = start_exam_attempt($conn, $exam_id, $user['user_id']);
header("Location: /student/take_exam.php?attempt_id=" . $attempt_id);
exit;
