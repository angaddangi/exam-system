<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('student');

$exams = get_available_exams($conn);
$page_title = "Select Exam";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Available Exams</h2>

    <?php if (empty($exams)): ?>
        <p>No exams are currently published.</p>
    <?php endif; ?>

    <?php foreach ($exams as $exam): ?>
        <div class="exam-item">
            <div>
                <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                <div class="meta">
                    Subject: <?php echo htmlspecialchars($exam['subject_name']); ?> &nbsp;|&nbsp;
                    Questions: <?php echo (int)$exam['number_of_questions']; ?> &nbsp;|&nbsp;
                    Duration: <?php echo (int)$exam['duration_minutes']; ?> min &nbsp;|&nbsp;
                    Full Marks: <?php echo $exam['full_marks']; ?> &nbsp;|&nbsp;
                    Pass Marks: <?php echo $exam['pass_marks']; ?><br>
                    Deadline: <?php echo htmlspecialchars($exam['submission_deadline']); ?>
                </div>
            </div>
            <a href="/student/start_exam.php?exam_id=<?php echo (int)$exam['exam_id']; ?>" class="btn btn-primary">Start Exam</a>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
