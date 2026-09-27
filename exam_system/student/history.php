<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('student');

$user = current_user();
$history = get_student_history($conn, $user['user_id']);

$page_title = "My History";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>My Exam History</h2>

    <?php if (empty($history)): ?>
        <p>You haven't attempted any exams yet.</p>
    <?php else: ?>
    <table>
        <tr>
            <th>Exam</th><th>Marks Obtained</th><th>Full Marks</th><th>Pass Marks</th><th>Status</th><th>Submitted At</th>
        </tr>
        <?php foreach ($history as $h): ?>
        <tr>
            <td><?php echo htmlspecialchars($h['exam_title']); ?></td>
            <td><?php echo $h['total_marks_obtained'] ?? '-'; ?></td>
            <td><?php echo $h['full_marks']; ?></td>
            <td><?php echo $h['pass_marks']; ?></td>
            <td><span class="badge badge-<?php echo $h['result_status']; ?>"><?php echo strtoupper($h['result_status']); ?></span></td>
            <td><?php echo htmlspecialchars($h['submitted_at'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
