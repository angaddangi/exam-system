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

// If already submitted, go straight to result
if ($attempt['status'] !== 'in_progress') {
    header("Location: /student/result.php?attempt_id=" . $attempt_id);
    exit;
}

// If time already expired, auto-submit now
$seconds_left = strtotime($attempt['expires_at']) - time();
if ($seconds_left <= 0) {
    submit_exam_attempt($conn, $attempt_id, true);
    header("Location: /student/result.php?attempt_id=" . $attempt_id);
    exit;
}

$exam = get_exam($conn, $attempt['exam_id']);
$questions = get_attempt_questions($conn, $attempt_id);

$page_title = "Take Exam";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="timer-box" id="timerBox">Time Remaining: <span id="timerText">--:--</span></div>

<div class="card">
    <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
    <p class="meta">Subject: <?php echo htmlspecialchars($exam['subject_name']); ?> | Full Marks: <?php echo $exam['full_marks']; ?> | Pass Marks: <?php echo $exam['pass_marks']; ?></p>
</div>

<form method="POST" action="/student/submit_exam.php" id="examForm">
    <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">

    <?php foreach ($questions as $i => $q): ?>
        <div class="card question-block">
            <div class="q-title">Q<?php echo $i + 1; ?>. <?php echo htmlspecialchars($q['question_text']); ?>
                <span style="float:right; color:var(--gold-dark);">[<?php echo $q['marks']; ?> marks]</span>
            </div>
            <?php foreach ($q['options'] as $opt): ?>
                <label class="option-label">
                    <input type="radio" name="answer_<?php echo $q['attempt_question_id']; ?>" value="<?php echo $opt['option_id']; ?>">
                    Option <?php echo $opt['option_label']; ?>: <?php echo htmlspecialchars($opt['option_text']); ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <button type="submit" class="btn btn-primary btn-block">Submit Exam</button>
    </div>
</form>

<script>
let secondsLeft = <?php echo (int)$seconds_left; ?>;
const timerText = document.getElementById('timerText');
const examForm = document.getElementById('examForm');

function updateTimer() {
    if (secondsLeft <= 0) {
        // "Time Expired?" Yes -> Auto Submit
        examForm.submit();
        return;
    }
    const m = Math.floor(secondsLeft / 60);
    const s = secondsLeft % 60;
    timerText.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    secondsLeft--;
}
updateTimer();
setInterval(updateTimer, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
