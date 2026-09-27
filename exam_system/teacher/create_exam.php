<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher');

$user = current_user();
$my_subjects = get_teacher_subjects($conn, $user['user_id']);
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'subject_id' => (int)$_POST['subject_id'],
        'number_of_questions' => (int)$_POST['number_of_questions'],
        'full_marks' => (float)$_POST['full_marks'],
        'pass_marks' => (float)$_POST['pass_marks'],
        'duration_minutes' => (int)$_POST['duration_minutes'],
        'start_time' => $_POST['start_time'],
        'submission_deadline' => $_POST['submission_deadline'],
        'visibility' => $_POST['visibility'],
        'show_result_after' => $_POST['show_result_after'],
    ];

    $available = count(get_questions_by_subject($conn, $data['subject_id']));
    if ($data['number_of_questions'] > $available) {
        $error = "Subject question bank only has $available questions, but you requested {$data['number_of_questions']}.";
    } else {
        $result = create_exam($conn, $data, $user['user_id']);
        if ($result['ok']) {
            $success = "Exam created successfully.";
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = "Create Exam";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Create New Exam</h2>

    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (empty($my_subjects)): ?>
        <div class="alert alert-error">You are not assigned to any subject yet.</div>
    <?php else: ?>
    <form method="POST">
        <label>Exam Title</label>
        <input type="text" name="title" required>

        <label>Description</label>
        <textarea name="description" rows="2"></textarea>

        <label>Subject</label>
        <select name="subject_id" required>
            <?php foreach ($my_subjects as $s): ?>
                <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['subject_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Number of Random Questions to Draw</label>
        <input type="number" name="number_of_questions" min="1" required>

        <label>Full Marks</label>
        <input type="number" step="0.01" name="full_marks" required>

        <label>Pass Marks</label>
        <input type="number" step="0.01" name="pass_marks" required>

        <label>Duration (minutes)</label>
        <input type="number" name="duration_minutes" required>

        <label>Start Time</label>
        <input type="datetime-local" name="start_time" required>

        <label>Submission Deadline</label>
        <input type="datetime-local" name="submission_deadline" required>

        <label>Visibility (Privacy)</label>
        <select name="visibility">
            <option value="private">Private</option>
            <option value="public">Public</option>
            <option value="invite_only">Invite Only</option>
        </select>

        <label>Show Result</label>
        <select name="show_result_after">
            <option value="after_deadline">After Deadline</option>
            <option value="immediately">Immediately</option>
            <option value="manual">Manually (Teacher Releases)</option>
        </select>

        <button type="submit" class="btn btn-primary btn-block">Create Exam</button>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
