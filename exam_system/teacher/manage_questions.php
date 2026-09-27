<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher');

$user = current_user();
$my_subjects = get_teacher_subjects($conn, $user['user_id']);

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = (int)$_POST['subject_id'];
    $question_text = trim($_POST['question_text']);
    $marks = (float)$_POST['marks'];

    $options = [
        '1' => ['text' => trim($_POST['option_1']), 'is_correct' => ($_POST['correct'] === '1')],
        '2' => ['text' => trim($_POST['option_2']), 'is_correct' => ($_POST['correct'] === '2')],
        '3' => ['text' => trim($_POST['option_3']), 'is_correct' => ($_POST['correct'] === '3')],
        '4' => ['text' => trim($_POST['option_4']), 'is_correct' => ($_POST['correct'] === '4')],
    ];

    $result = create_question($conn, $subject_id, $user['user_id'], $question_text, $marks, $options);
    if ($result['ok']) {
        $success = "Question added successfully.";
    } else {
        $error = $result['error'];
    }
}

$selected_subject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : ($my_subjects[0]['subject_id'] ?? 0);
$bank = $selected_subject ? get_questions_by_subject($conn, $selected_subject) : [];

$page_title = "Question Bank";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Add Question to Your Subject</h2>
    <p class="meta">Only subjects you are assigned to appear below. This is enforced both in this form and by the database itself.</p>

    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (empty($my_subjects)): ?>
        <div class="alert alert-error">You are not assigned to any subject yet. Contact an admin.</div>
    <?php else: ?>
    <form method="POST">
        <label>Subject</label>
        <select name="subject_id" required>
            <?php foreach ($my_subjects as $s): ?>
                <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['subject_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Question Text</label>
        <textarea name="question_text" rows="3" required></textarea>

        <label>Marks</label>
        <input type="number" step="0.01" name="marks" value="1.00" required>

        <label>Option 1</label>
        <input type="text" name="option_1" required>
        <label>Option 2</label>
        <input type="text" name="option_2" required>
        <label>Option 3</label>
        <input type="text" name="option_3" required>
        <label>Option 4</label>
        <input type="text" name="option_4" required>

        <label>Correct Option</label>
        <select name="correct" required>
            <option value="1">Option 1</option>
            <option value="2">Option 2</option>
            <option value="3">Option 3</option>
            <option value="4">Option 4</option>
        </select>

        <button type="submit" class="btn btn-primary btn-block">Add Question</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($my_subjects)): ?>
<div class="card">
    <h2>Existing Questions</h2>
    <form method="GET" style="margin-bottom:16px;">
        <label>Filter by Subject</label>
        <select name="subject_id" onchange="this.form.submit()">
            <?php foreach ($my_subjects as $s): ?>
                <option value="<?php echo $s['subject_id']; ?>" <?php echo $s['subject_id'] == $selected_subject ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['subject_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <table>
        <tr><th>Question</th><th>Marks</th><th>Added By</th></tr>
        <?php foreach ($bank as $q): ?>
        <tr>
            <td><?php echo htmlspecialchars($q['question_text']); ?></td>
            <td><?php echo $q['default_marks']; ?></td>
            <td><?php echo htmlspecialchars($q['teacher_name']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
