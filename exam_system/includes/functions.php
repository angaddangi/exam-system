<?php
require_once __DIR__ . '/db.php';

// ============================================================
// Small helper to run a prepared statement and return results
// ============================================================
function db_query($conn, $sql, $types = "", $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }
    if ($types !== "") {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

// ------------------------------------------------------------
// USERS (CRUD)
// ------------------------------------------------------------
function create_user($conn, $full_name, $email, $username, $password, $role) {
    // NOTE: no hashing / no encryption, password stored as plain text as requested
    $sql = "INSERT INTO users (full_name, email, username, password_hash, role)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = db_query($conn, $sql, "sssss", [$full_name, $email, $username, $password, $role]);
    return mysqli_stmt_affected_rows($stmt) > 0;
}

function get_user_by_username($conn, $username) {
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = db_query($conn, $sql, "s", [$username]);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function get_user_by_id($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = db_query($conn, $sql, "i", [$user_id]);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function update_user($conn, $user_id, $full_name, $email, $phone) {
    $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
    $stmt = db_query($conn, $sql, "sssi", [$full_name, $email, $phone, $user_id]);
    return mysqli_stmt_affected_rows($stmt) >= 0;
}

function delete_user($conn, $user_id) {
    $sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = db_query($conn, $sql, "i", [$user_id]);
    return mysqli_stmt_affected_rows($stmt) > 0;
}

// ------------------------------------------------------------
// LOGIN HISTORY
// ------------------------------------------------------------
function log_login($conn, $user_id, $success) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $sql = "INSERT INTO login_history (user_id, ip_address, login_success) VALUES (?, ?, ?)";
    db_query($conn, $sql, "isi", [$user_id, $ip, $success]);
}

// ------------------------------------------------------------
// SUBJECTS
// ------------------------------------------------------------
function get_all_subjects($conn) {
    $result = mysqli_query($conn, "SELECT * FROM subjects ORDER BY subject_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function teacher_has_subject($conn, $teacher_id, $subject_id) {
    $sql = "SELECT COUNT(*) AS cnt FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?";
    $stmt = db_query($conn, $sql, "ii", [$teacher_id, $subject_id]);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row['cnt'] > 0;
}

function get_teacher_subjects($conn, $teacher_id) {
    $sql = "SELECT s.* FROM subjects s
            JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
            WHERE ts.teacher_id = ?";
    $stmt = db_query($conn, $sql, "i", [$teacher_id]);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

// ------------------------------------------------------------
// QUESTIONS (CRUD) - teacher can only add to assigned subject
// ------------------------------------------------------------
function create_question($conn, $subject_id, $teacher_id, $question_text, $marks, $options) {
    if (!teacher_has_subject($conn, $teacher_id, $subject_id)) {
        return ["ok" => false, "error" => "You are not assigned to this subject."];
    }

    $sql = "INSERT INTO questions (subject_id, created_by, question_text, default_marks)
            VALUES (?, ?, ?, ?)";
    $stmt = db_query($conn, $sql, "iisd", [$subject_id, $teacher_id, $question_text, $marks]);
    $question_id = mysqli_insert_id($conn);

    foreach ($options as $label => $opt) {
        $sql2 = "INSERT INTO question_options (question_id, option_label, option_text, is_correct)
                 VALUES (?, ?, ?, ?)";
        $is_correct = ($opt['is_correct']) ? 1 : 0;
        db_query($conn, $sql2, "issi", [$question_id, $label, $opt['text'], $is_correct]);
    }

    return ["ok" => true, "question_id" => $question_id];
}

function get_questions_by_subject($conn, $subject_id) {
    $sql = "SELECT q.*, u.full_name AS teacher_name FROM questions q
            JOIN users u ON q.created_by = u.user_id
            WHERE q.subject_id = ? ORDER BY q.created_at DESC";
    $stmt = db_query($conn, $sql, "i", [$subject_id]);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

function get_question_options($conn, $question_id) {
    $sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY option_label";
    $stmt = db_query($conn, $sql, "i", [$question_id]);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

function delete_question($conn, $question_id, $teacher_id) {
    $sql = "DELETE FROM questions WHERE question_id = ? AND created_by = ?";
    $stmt = db_query($conn, $sql, "ii", [$question_id, $teacher_id]);
    return mysqli_stmt_affected_rows($stmt) > 0;
}

// ------------------------------------------------------------
// EXAMS (CRUD)
// ------------------------------------------------------------
function create_exam($conn, $data, $teacher_id) {
    if (!teacher_has_subject($conn, $teacher_id, $data['subject_id'])) {
        return ["ok" => false, "error" => "You are not assigned to this subject."];
    }

    $sql = "INSERT INTO exams
            (title, description, subject_id, teacher_id, number_of_questions,
             full_marks, pass_marks, duration_minutes, start_time, submission_deadline,
             visibility, show_result_after, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published')";
    $stmt = db_query($conn, $sql, "ssiiiddisssss", [
        $data['title'], $data['description'], $data['subject_id'], $teacher_id,
        $data['number_of_questions'], $data['full_marks'], $data['pass_marks'],
        $data['duration_minutes'], $data['start_time'], $data['submission_deadline'],
        $data['visibility'], $data['show_result_after']
    ]);
    return ["ok" => true, "exam_id" => mysqli_insert_id($conn)];
}

function get_available_exams($conn) {
    $sql = "SELECT e.*, s.subject_name FROM exams e
            JOIN subjects s ON e.subject_id = s.subject_id
            WHERE e.status = 'published'
            ORDER BY e.start_time DESC";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function get_exam($conn, $exam_id) {
    $sql = "SELECT e.*, s.subject_name FROM exams e
            JOIN subjects s ON e.subject_id = s.subject_id
            WHERE e.exam_id = ?";
    $stmt = db_query($conn, $sql, "i", [$exam_id]);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function get_teacher_exams($conn, $teacher_id) {
    $sql = "SELECT e.*, s.subject_name FROM exams e
            JOIN subjects s ON e.subject_id = s.subject_id
            WHERE e.teacher_id = ? ORDER BY e.created_at DESC";
    $stmt = db_query($conn, $sql, "i", [$teacher_id]);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

// ------------------------------------------------------------
// EXAM ATTEMPT FLOW (Start -> Random Questions -> Submit)
// ------------------------------------------------------------
function get_existing_attempt($conn, $exam_id, $student_id) {
    $sql = "SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ?";
    $stmt = db_query($conn, $sql, "ii", [$exam_id, $student_id]);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// "Click Start Exam" -> "Load Random Questions & Timer"
function start_exam_attempt($conn, $exam_id, $student_id) {
    $existing = get_existing_attempt($conn, $exam_id, $student_id);
    if ($existing) {
        return $existing['attempt_id'];
    }

    $exam = get_exam($conn, $exam_id);
    $duration = (int)$exam['duration_minutes'];
    $num_questions = (int)$exam['number_of_questions'];
    $subject_id = (int)$exam['subject_id'];

    $sql = "INSERT INTO exam_attempts (exam_id, student_id, started_at, expires_at, status)
            VALUES (?, ?, NOW(), NOW() + INTERVAL ? MINUTE, 'in_progress')";
    $stmt = db_query($conn, $sql, "iii", [$exam_id, $student_id, $duration]);
    $attempt_id = mysqli_insert_id($conn);

    // Randomly draw N active questions from the subject's question bank
    $sql2 = "SELECT question_id, default_marks FROM questions
              WHERE subject_id = ? AND is_active = 1
              ORDER BY RAND() LIMIT ?";
    $stmt2 = db_query($conn, $sql2, "ii", [$subject_id, $num_questions]);
    $questions = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);

    $order = 1;
    foreach ($questions as $q) {
        $sql3 = "INSERT INTO attempt_questions (attempt_id, question_id, question_order, marks)
                 VALUES (?, ?, ?, ?)";
        db_query($conn, $sql3, "iiid", [$attempt_id, $q['question_id'], $order, $q['default_marks']]);
        $order++;
    }

    return $attempt_id;
}

function get_attempt($conn, $attempt_id) {
    $sql = "SELECT * FROM exam_attempts WHERE attempt_id = ?";
    $stmt = db_query($conn, $sql, "i", [$attempt_id]);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function get_attempt_questions($conn, $attempt_id) {
    $sql = "SELECT aq.attempt_question_id, aq.marks, q.question_id, q.question_text
            FROM attempt_questions aq
            JOIN questions q ON aq.question_id = q.question_id
            WHERE aq.attempt_id = ? ORDER BY aq.question_order";
    $stmt = db_query($conn, $sql, "i", [$attempt_id]);
    $questions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    foreach ($questions as &$q) {
        $q['options'] = get_question_options($conn, $q['question_id']);
    }
    return $questions;
}

// "Answer Questions" - save/update one response
function save_response($conn, $attempt_question_id, $selected_option_id) {
    $sql = "SELECT response_id FROM student_responses WHERE attempt_question_id = ?";
    $stmt = db_query($conn, $sql, "i", [$attempt_question_id]);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($existing) {
        $sql2 = "UPDATE student_responses SET selected_option_id = ? WHERE attempt_question_id = ?";
        db_query($conn, $sql2, "ii", [$selected_option_id, $attempt_question_id]);
    } else {
        $sql2 = "INSERT INTO student_responses (attempt_question_id, selected_option_id) VALUES (?, ?)";
        db_query($conn, $sql2, "ii", [$attempt_question_id, $selected_option_id]);
    }
}

// "Submit Exam" / "Auto Submit" -> "Automatic Evaluation" -> "Store Result"
function submit_exam_attempt($conn, $attempt_id, $auto_submitted = false) {
    $sql = "SELECT COALESCE(SUM(sr.marks_awarded), 0) AS total
            FROM attempt_questions aq
            LEFT JOIN student_responses sr ON sr.attempt_question_id = aq.attempt_question_id
            WHERE aq.attempt_id = ?";
    $stmt = db_query($conn, $sql, "i", [$attempt_id]);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $total = $row['total'];

    $attempt = get_attempt($conn, $attempt_id);
    $exam = get_exam($conn, $attempt['exam_id']);
    $status = $total >= $exam['pass_marks'] ? 'pass' : 'fail';
    $submit_status = $auto_submitted ? 'auto_submitted' : 'submitted';

    $sql2 = "UPDATE exam_attempts
             SET total_marks_obtained = ?, result_status = ?, submitted_at = NOW(), status = ?
             WHERE attempt_id = ?";
    db_query($conn, $sql2, "dssi", [$total, $status, $submit_status, $attempt_id]);

    return $total;
}

function get_result($conn, $attempt_id) {
    $sql = "SELECT * FROM v_exam_results WHERE attempt_id = ?";
    $stmt = db_query($conn, $sql, "i", [$attempt_id]);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function get_student_history($conn, $student_id) {
    $sql = "SELECT * FROM v_exam_results WHERE student_id = ? ORDER BY submitted_at DESC";
    $stmt = db_query($conn, $sql, "i", [$student_id]);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}
