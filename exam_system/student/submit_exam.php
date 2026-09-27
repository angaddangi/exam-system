<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('student');

$attempt_id = (int)($_POST['attempt_id'] ?? 0);
$attempt = get_attempt($conn, $attempt_id);
$user = current_user();

if (!$attempt || $attempt['student_id'] != $user['user_id']) {
    header("Location: /student/select_exam.php");
    exit;
}

if ($attempt['status'] === 'in_progress') {
    // "Answer Questions" -> save each response
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'answer_') === 0) {
            $attempt_question_id = (int)str_replace('answer_', '', $key);
            $selected_option_id = (int)$value;
            save_response($conn, $attempt_question_id, $selected_option_id);
        }
    }

    // Was this an auto-submit due to expired timer, or manual submit?
    $auto = strtotime($attempt['expires_at']) <= time();

    // "Automatic Evaluation" -> "Store Result in Database"
    submit_exam_attempt($conn, $attempt_id, $auto);
}

header("Location: /student/result.php?attempt_id=" . $attempt_id);
exit;
