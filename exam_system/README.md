# Quick Entrance Exam System (PHP + MySQL)

A full PHP/MySQL online examination system implementing the candidate flow:
Register → Login → Dashboard → Select Exam → Start Exam → Load Random
Questions & Timer → Answer → Submit / Auto-Submit → Automatic Evaluation →
Store Result → Display Result → Logout.

Theme: **Gold & Purple**.

> **Note:** Per request, this project does NOT use password hashing or
> encryption anywhere. Passwords are stored and compared as plain text
> (`users.password_hash` column just holds the raw password string).
> This is fine for a local demo/school project, but should never be used
> in a real production system — always hash passwords (e.g. `password_hash()`)
> before deploying publicly.

## Setup

1. **Create the database**
   - Open phpMyAdmin or the MySQL CLI.
   - Import `database.sql`. This creates the `online_exam_system` database,
     all tables, triggers, stored procedures, views, indexes, and some
     sample seed data (1 teacher, 1 student, 1 subject, 3 questions, 1 exam).

2. **Configure the DB connection**
   - Edit `includes/db.php` and set your MySQL host/user/password if
     different from the defaults (`localhost` / `root` / empty password).

3. **Run with PHP's built-in server** (from the project root):
   ```
   php -S localhost:8000
   ```
   Then visit `http://localhost:8000/`.

   Or copy the whole `exam_system` folder into your XAMPP/WAMP/MAMP
   `htdocs` directory and visit `http://localhost/exam_system/`.

## Sample login (from seed data)

- **Teacher:** username `alice_t`, password `alice123`
- **Student:** username `bob_s`, password `bob123`

(These are plain-text passwords — column is named `password_hash` for
schema compatibility only, but no hashing function is applied anywhere.)

## Folder structure

```
exam_system/
├── database.sql              -- full schema + triggers + stored procs + seed data
├── index.php                 -- redirects to login/dashboard
├── login.php                 -- login (credential check, error handling)
├── register.php              -- create account (student or teacher)
├── logout.php
├── dashboard.php              -- routes to student/teacher view
├── includes/
│   ├── db.php                 -- mysqli connection
│   ├── auth.php                -- session helpers (require_login, require_role)
│   ├── functions.php           -- all CRUD + exam-flow logic
│   ├── header.php / footer.php -- shared layout
├── assets/css/style.css        -- gold & purple theme
├── student/
│   ├── select_exam.php          -- list published exams
│   ├── start_exam.php            -- creates attempt + draws random questions
│   ├── take_exam.php              -- timer + question form
│   ├── submit_exam.php             -- saves answers, triggers auto-grading
│   ├── result.php                   -- shows pass/fail (respects privacy setting)
│   └── history.php                   -- past attempts
└── teacher/
    ├── manage_questions.php    -- add questions (subject-restricted)
    ├── create_exam.php          -- create exam (draws N random Qs)
    └── my_exams.php               -- list of exams created
```

## Key logic

- **Random questions**: `functions.php::start_exam_attempt()` runs
  `ORDER BY RAND() LIMIT N` against the subject's question bank and
  freezes the drawn set into `attempt_questions`, so every candidate can
  get a different random set and history stays accurate later.
- **Teacher-subject restriction**: `create_question()` checks
  `teacher_subjects` before allowing an insert — a teacher can only add
  questions to a subject they are assigned to (also enforced by a
  database trigger as a second layer).
- **Timer / auto-submit**: `take_exam.php` computes `seconds_left` from
  `exam_attempts.expires_at` and counts down in JavaScript; when it hits
  zero the form auto-submits, and `submit_exam.php` marks the attempt as
  `auto_submitted`.
- **Privacy feature**: `exams.visibility` and `exams.show_result_after`
  control whether an exam is public/private and when a candidate's
  result becomes visible to them.
