-- ============================================================
-- ONLINE EXAMINATION SYSTEM - DATABASE SCHEMA (v2)
-- Matches "User (Candidate) Flowchart":
--   Register/Login -> Dashboard -> Select Exam -> Start Exam
--   -> Load RANDOM Questions & Timer -> Answer -> Auto/Manual Submit
--   -> Auto Evaluate -> Store Result -> Display Result -> Logout
--
-- Key change from v1: Questions belong to a SUBJECT (question bank),
-- not directly to one exam. A teacher can only add questions to
-- subjects they are assigned to. An exam just says "pick N random
-- questions from subject X" and the system draws them per attempt.
-- MySQL 8.0+
-- ============================================================

DROP DATABASE IF EXISTS online_exam_system;
CREATE DATABASE online_exam_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE online_exam_system;

-- ============================================================
-- 1. USERS (Admin, Teacher, Candidate/Student)
-- ============================================================
CREATE TABLE users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    username        VARCHAR(50)   NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,          -- "Enter Username & Password" step
    role            ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    phone           VARCHAR(20),
    profile_picture VARCHAR(255),

    -- Privacy feature
    profile_visibility   ENUM('public','private','friends_only') NOT NULL DEFAULT 'private',
    show_result_publicly BOOLEAN NOT NULL DEFAULT FALSE,
    allow_contact        BOOLEAN NOT NULL DEFAULT FALSE,

    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. SUBJECTS
-- ============================================================
CREATE TABLE subjects (
    subject_id   INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT
) ENGINE=InnoDB;

-- ============================================================
-- 3. TEACHER <-> SUBJECT ASSIGNMENT
--    "A teacher of a subject can add questions of that subject"
--    Only a teacher assigned to a subject may insert questions into it.
-- ============================================================
CREATE TABLE teacher_subjects (
    teacher_subject_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    CONSTRAINT uq_teacher_subject UNIQUE (teacher_id, subject_id)
) ENGINE=InnoDB;

-- ============================================================
-- 4. QUESTION BANK (belongs to a SUBJECT, not an exam)
-- ============================================================
CREATE TABLE questions (
    question_id     INT AUTO_INCREMENT PRIMARY KEY,
    subject_id      INT NOT NULL,
    created_by      INT NOT NULL,               -- teacher who added it (FK -> users)
    question_text   TEXT NOT NULL,
    question_image  VARCHAR(255) DEFAULT NULL,
    difficulty      ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
    default_marks   DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,   -- soft-disable instead of deleting (keeps history intact)
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_question_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_question_teacher FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. QUESTION OPTIONS (Option 1-4, exactly one correct)
-- ============================================================
CREATE TABLE question_options (
    option_id     INT AUTO_INCREMENT PRIMARY KEY,
    question_id   INT NOT NULL,
    option_label  ENUM('1','2','3','4') NOT NULL,
    option_text   VARCHAR(500) NOT NULL,
    is_correct    BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    CONSTRAINT uq_question_label UNIQUE (question_id, option_label)
) ENGINE=InnoDB;

-- ============================================================
-- 6. EXAMS
--    An exam draws N random questions from one subject's bank
-- ============================================================
CREATE TABLE exams (
    exam_id             INT AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(200) NOT NULL,
    description         TEXT,
    subject_id          INT NOT NULL,
    teacher_id          INT NOT NULL,             -- exam creator (must be assigned to subject_id)

    number_of_questions INT NOT NULL,             -- how many RANDOM questions to load per attempt
    full_marks          DECIMAL(6,2) NOT NULL,
    pass_marks          DECIMAL(6,2) NOT NULL,
    duration_minutes    INT NOT NULL,             -- powers the exam "Timer"

    start_time          DATETIME NOT NULL,
    submission_deadline DATETIME NOT NULL,

    -- Privacy feature
    visibility        ENUM('public','private','invite_only') NOT NULL DEFAULT 'private',
    access_code       VARCHAR(20) DEFAULT NULL,
    show_result_after ENUM('immediately','after_deadline','manual') NOT NULL DEFAULT 'after_deadline',

    status            ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_exam_teacher  FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_subject  FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    CONSTRAINT chk_pass_le_full CHECK (pass_marks <= full_marks),
    CONSTRAINT chk_deadline_after_start CHECK (submission_deadline >= start_time),
    CONSTRAINT chk_question_count CHECK (number_of_questions > 0)
) ENGINE=InnoDB;

-- ============================================================
-- 7. EXAM ATTEMPTS (Candidate flow: Start Exam -> Timer runs)
-- ============================================================
CREATE TABLE exam_attempts (
    attempt_id      INT AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT NOT NULL,
    student_id      INT NOT NULL,                -- responding user id

    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- "Click Start Exam"
    expires_at       DATETIME NOT NULL,                            -- started_at + duration_minutes -> drives "Time Expired?"
    submitted_at    DATETIME DEFAULT NULL,

    total_marks_obtained DECIMAL(6,2) DEFAULT NULL,
    result_status   ENUM('pass','fail','pending') NOT NULL DEFAULT 'pending',

    status          ENUM('in_progress','submitted','auto_submitted','abandoned') NOT NULL DEFAULT 'in_progress',
    ip_address      VARCHAR(45),

    CONSTRAINT fk_attempt_exam FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_student FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT uq_student_exam UNIQUE (exam_id, student_id)  -- one attempt per student per exam; drop for retakes
) ENGINE=InnoDB;

-- ============================================================
-- 8. ATTEMPT QUESTIONS  ("Load Random Questions & Timer")
--    The exact random N questions drawn for THIS attempt are
--    frozen here at start time, so:
--      - every candidate can get a different random set
--      - re-grading/history stays accurate even if the bank changes later
-- ============================================================
CREATE TABLE attempt_questions (
    attempt_question_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id     INT NOT NULL,
    question_id    INT NOT NULL,
    question_order INT NOT NULL,             -- order shown to candidate
    marks          DECIMAL(6,2) NOT NULL,    -- marks locked in at draw time (copied from questions.default_marks)

    CONSTRAINT fk_aq_attempt  FOREIGN KEY (attempt_id) REFERENCES exam_attempts(attempt_id) ON DELETE CASCADE,
    CONSTRAINT fk_aq_question FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    CONSTRAINT uq_attempt_question UNIQUE (attempt_id, question_id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. STUDENT RESPONSES ("Answer Questions" + history)
-- ============================================================
CREATE TABLE student_responses (
    response_id        INT AUTO_INCREMENT PRIMARY KEY,
    attempt_question_id INT NOT NULL,
    selected_option_id  INT DEFAULT NULL,     -- NULL = unanswered
    is_correct          BOOLEAN DEFAULT NULL,
    marks_awarded       DECIMAL(6,2) DEFAULT 0.00,
    answered_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_response_aq FOREIGN KEY (attempt_question_id) REFERENCES attempt_questions(attempt_question_id) ON DELETE CASCADE,
    CONSTRAINT fk_response_option FOREIGN KEY (selected_option_id) REFERENCES question_options(option_id) ON DELETE SET NULL,
    CONSTRAINT uq_response_aq UNIQUE (attempt_question_id)
) ENGINE=InnoDB;

-- ============================================================
-- 10. LOGIN / ACTIVITY HISTORY
-- ============================================================
CREATE TABLE login_history (
    history_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    login_time   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_time  TIMESTAMP NULL DEFAULT NULL,
    ip_address   VARCHAR(45),
    device_info  VARCHAR(255),
    login_success BOOLEAN NOT NULL DEFAULT TRUE,   -- "Credentials Valid? No -> Show Error Message"

    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 11. TRIGGERS
-- ============================================================
DELIMITER $$

-- Only one correct option per question
CREATE TRIGGER trg_single_correct_before_insert
BEFORE INSERT ON question_options
FOR EACH ROW
BEGIN
    IF NEW.is_correct = TRUE THEN
        IF (SELECT COUNT(*) FROM question_options
            WHERE question_id = NEW.question_id AND is_correct = TRUE) > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one correct option is allowed per question.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_single_correct_before_update
BEFORE UPDATE ON question_options
FOR EACH ROW
BEGIN
    IF NEW.is_correct = TRUE AND OLD.is_correct = FALSE THEN
        IF (SELECT COUNT(*) FROM question_options
            WHERE question_id = NEW.question_id AND is_correct = TRUE
            AND option_id <> NEW.option_id) > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one correct option is allowed per question.';
        END IF;
    END IF;
END$$

-- Enforce: a teacher may only add a question to a subject they are assigned to
CREATE TRIGGER trg_question_teacher_subject_check
BEFORE INSERT ON questions
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM teacher_subjects
        WHERE teacher_id = NEW.created_by AND subject_id = NEW.subject_id) = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Teacher is not assigned to this subject and cannot add questions to it.';
    END IF;
END$$

-- Auto-grade a response on insert/update ("Automatic Evaluation")
CREATE TRIGGER trg_response_auto_grade_insert
BEFORE INSERT ON student_responses
FOR EACH ROW
BEGIN
    DECLARE v_is_correct BOOLEAN DEFAULT FALSE;
    DECLARE v_marks DECIMAL(6,2) DEFAULT 0.00;

    IF NEW.selected_option_id IS NOT NULL THEN
        SELECT is_correct INTO v_is_correct
        FROM question_options WHERE option_id = NEW.selected_option_id;

        SELECT marks INTO v_marks
        FROM attempt_questions WHERE attempt_question_id = NEW.attempt_question_id;

        IF v_is_correct = TRUE THEN
            SET NEW.marks_awarded = v_marks;
            SET NEW.is_correct = TRUE;
        ELSE
            SET NEW.marks_awarded = 0.00;
            SET NEW.is_correct = FALSE;
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_response_auto_grade_update
BEFORE UPDATE ON student_responses
FOR EACH ROW
BEGIN
    DECLARE v_is_correct BOOLEAN DEFAULT FALSE;
    DECLARE v_marks DECIMAL(6,2) DEFAULT 0.00;

    IF NEW.selected_option_id IS NOT NULL THEN
        SELECT is_correct INTO v_is_correct
        FROM question_options WHERE option_id = NEW.selected_option_id;

        SELECT marks INTO v_marks
        FROM attempt_questions WHERE attempt_question_id = NEW.attempt_question_id;

        IF v_is_correct = TRUE THEN
            SET NEW.marks_awarded = v_marks;
            SET NEW.is_correct = TRUE;
        ELSE
            SET NEW.marks_awarded = 0.00;
            SET NEW.is_correct = FALSE;
        END IF;
    ELSE
        SET NEW.marks_awarded = 0.00;
        SET NEW.is_correct = NULL;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- 12. STORED PROCEDURES  (core candidate-flow logic)
-- ============================================================
DELIMITER $$

-- "Click Start Exam" -> "Load Random Questions & Timer"
-- Creates the attempt and draws N random questions from the exam's subject bank.
CREATE PROCEDURE sp_start_exam_attempt (
    IN p_exam_id    INT,
    IN p_student_id INT,
    OUT p_attempt_id INT
)
BEGIN
    DECLARE v_subject_id INT;
    DECLARE v_num_questions INT;
    DECLARE v_duration INT;

    SELECT subject_id, number_of_questions, duration_minutes
      INTO v_subject_id, v_num_questions, v_duration
    FROM exams WHERE exam_id = p_exam_id;

    INSERT INTO exam_attempts (exam_id, student_id, started_at, expires_at, status)
    VALUES (p_exam_id, p_student_id, NOW(), NOW() + INTERVAL v_duration MINUTE, 'in_progress');

    SET p_attempt_id = LAST_INSERT_ID();

    -- Randomly draw N active questions from the subject's question bank
    INSERT INTO attempt_questions (attempt_id, question_id, question_order, marks)
    SELECT p_attempt_id,
           q.question_id,
           ROW_NUMBER() OVER (ORDER BY RAND()) AS question_order,
           q.default_marks
    FROM questions q
    WHERE q.subject_id = v_subject_id
      AND q.is_active = TRUE
    ORDER BY RAND()
    LIMIT v_num_questions;

END$$

-- "Submit Exam" / "Auto Submit" -> "Automatic Evaluation" -> "Store Result in Database"
CREATE PROCEDURE sp_submit_exam_attempt (
    IN p_attempt_id INT,
    IN p_auto_submitted BOOLEAN
)
BEGIN
    DECLARE v_total DECIMAL(6,2);
    DECLARE v_exam_id INT;
    DECLARE v_pass_marks DECIMAL(6,2);

    SELECT COALESCE(SUM(sr.marks_awarded), 0)
      INTO v_total
    FROM attempt_questions aq
    LEFT JOIN student_responses sr ON sr.attempt_question_id = aq.attempt_question_id
    WHERE aq.attempt_id = p_attempt_id;

    SELECT exam_id INTO v_exam_id FROM exam_attempts WHERE attempt_id = p_attempt_id;
    SELECT pass_marks INTO v_pass_marks FROM exams WHERE exam_id = v_exam_id;

    UPDATE exam_attempts
    SET total_marks_obtained = v_total,
        result_status = CASE WHEN v_total >= v_pass_marks THEN 'pass' ELSE 'fail' END,
        submitted_at = NOW(),
        status = CASE WHEN p_auto_submitted THEN 'auto_submitted' ELSE 'submitted' END
    WHERE attempt_id = p_attempt_id;

END$$

DELIMITER ;

-- ============================================================
-- 13. VIEWS
-- ============================================================

-- "Display Final Result to Candidate"
CREATE VIEW v_exam_results AS
SELECT
    a.attempt_id,
    a.exam_id,
    e.title AS exam_title,
    a.student_id,
    u.full_name AS student_name,
    e.full_marks,
    e.pass_marks,
    a.total_marks_obtained,
    a.result_status,
    a.started_at,
    a.submitted_at,
    e.submission_deadline
FROM exam_attempts a
JOIN exams e ON a.exam_id = e.exam_id
JOIN users u ON a.student_id = u.user_id;

-- Teacher dashboard
CREATE VIEW v_teacher_exam_overview AS
SELECT
    e.exam_id,
    e.title,
    e.teacher_id,
    t.full_name AS teacher_name,
    s.subject_name,
    e.number_of_questions,
    e.full_marks,
    e.pass_marks,
    e.submission_deadline,
    e.visibility,
    e.status,
    COUNT(a.attempt_id) AS total_attempts
FROM exams e
JOIN users t ON e.teacher_id = t.user_id
JOIN subjects s ON e.subject_id = s.subject_id
LEFT JOIN exam_attempts a ON e.exam_id = a.exam_id
GROUP BY e.exam_id, e.title, e.teacher_id, t.full_name, s.subject_name,
         e.number_of_questions, e.full_marks, e.pass_marks,
         e.submission_deadline, e.visibility, e.status;

-- Question bank per subject, showing which teacher added each question
CREATE VIEW v_subject_question_bank AS
SELECT
    q.question_id,
    s.subject_name,
    q.question_text,
    q.default_marks,
    q.difficulty,
    q.is_active,
    u.full_name AS added_by_teacher
FROM questions q
JOIN subjects s ON q.subject_id = s.subject_id
JOIN users u ON q.created_by = u.user_id;

-- ============================================================
-- 14. INDEXES
-- ============================================================
CREATE INDEX idx_exam_teacher ON exams(teacher_id);
CREATE INDEX idx_exam_subject ON exams(subject_id);
CREATE INDEX idx_exam_status ON exams(status);
CREATE INDEX idx_question_subject ON questions(subject_id);
CREATE INDEX idx_question_teacher ON questions(created_by);
CREATE INDEX idx_option_question ON question_options(question_id);
CREATE INDEX idx_attempt_student ON exam_attempts(student_id);
CREATE INDEX idx_attempt_exam ON exam_attempts(exam_id);
CREATE INDEX idx_aq_attempt ON attempt_questions(attempt_id);
CREATE INDEX idx_history_user ON login_history(user_id);

-- ============================================================
-- 15. SAMPLE SEED DATA
-- ============================================================
-- Passwords stored as plain text (no hashing/encryption), as requested
INSERT INTO users (full_name, email, username, password_hash, role, profile_visibility, show_result_publicly)
VALUES
('Alice Teacher', 'alice.teacher@example.com', 'alice_t', 'alice123', 'teacher', 'public', FALSE),
('Bob Student',   'bob.student@example.com',   'bob_s',   'bob123', 'student', 'private', TRUE);

INSERT INTO subjects (subject_name, description) VALUES ('General Knowledge', 'GK related exams');

-- Assign Alice (teacher) to General Knowledge subject
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (1, 1);

-- Alice adds questions to the GK subject bank (allowed, since she is assigned)
INSERT INTO questions (subject_id, created_by, question_text, default_marks) VALUES
(1, 1, 'What is the capital of France?', 5.00),
(1, 1, 'Which planet is known as the Red Planet?', 5.00),
(1, 1, 'How many continents are there on Earth?', 5.00);

INSERT INTO question_options (question_id, option_label, option_text, is_correct) VALUES
(1, '1', 'Berlin', FALSE), (1, '2', 'Paris', TRUE), (1, '3', 'Rome', FALSE), (1, '4', 'Madrid', FALSE),
(2, '1', 'Venus', FALSE), (2, '2', 'Jupiter', FALSE), (2, '3', 'Mars', TRUE), (2, '4', 'Saturn', FALSE),
(3, '1', '5', FALSE), (3, '2', '6', FALSE), (3, '3', '7', TRUE), (3, '4', '8', FALSE);

-- Exam draws 2 random questions out of the 3-question GK bank
INSERT INTO exams (title, description, subject_id, teacher_id, number_of_questions,
                    full_marks, pass_marks, duration_minutes, start_time, submission_deadline,
                    visibility, show_result_after, status)
VALUES ('Sample GK Exam', 'A basic sample exam', 1, 1, 2, 10.00, 5.00, 30,
        '2026-08-06 09:00:00', '2026-08-06 10:00:00', 'private', 'after_deadline', 'published');

-- Example candidate flow:
-- CALL sp_start_exam_attempt(1, 2, @attempt_id);
-- SELECT @attempt_id;
-- (student answers -> INSERT INTO student_responses ...)
-- CALL sp_submit_exam_attempt(@attempt_id, FALSE);
-- SELECT * FROM v_exam_results WHERE attempt_id = @attempt_id;

-- ============================================================
-- END OF SCRIPT
-- ============================================================
