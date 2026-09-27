<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher');

$user = current_user();
$exams = get_teacher_exams($conn, $user['user_id']);

$page_title = "My Exams";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>My Exams</h2>

    <?php if (empty($exams)): ?>
        <p>You haven't created any exams yet.</p>
    <?php else: ?>
    <table>
        <tr><th>Title</th><th>Subject</th><th>Questions</th><th>Full/Pass Marks</th><th>Deadline</th><th>Visibility</th><th>Status</th></tr>
        <?php foreach ($exams as $e): ?>
        <tr>
            <td><?php echo htmlspecialchars($e['title']); ?></td>
            <td><?php echo htmlspecialchars($e['subject_name']); ?></td>
            <td><?php echo (int)$e['number_of_questions']; ?></td>
            <td><?php echo $e['full_marks']; ?> / <?php echo $e['pass_marks']; ?></td>
            <td><?php echo htmlspecialchars($e['submission_deadline']); ?></td>
            <td><?php echo htmlspecialchars($e['visibility']); ?></td>
            <td><?php echo htmlspecialchars($e['status']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
