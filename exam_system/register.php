<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password']; // stored as plain text, no hashing (per request)
    $role      = $_POST['role'];

    if ($full_name === "" || $email === "" || $username === "" || $password === "") {
        $error = "All fields are required.";
    } elseif (get_user_by_username($conn, $username)) {
        $error = "Username already taken. Please login instead.";
    } else {
        $ok = create_user($conn, $full_name, $email, $username, $password, $role);
        if ($ok) {
            $success = "Account created successfully! You can now login.";
        } else {
            $error = "Could not create account. Email may already be in use.";
        }
    }
}

$page_title = "Create Account";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card auth-card">
    <h2>Create Account</h2>

    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?> <a href="/login.php">Login now</a></div><?php endif; ?>

    <form method="POST" action="/register.php">
        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Register as</label>
        <select name="role">
            <option value="student">Student / Candidate</option>
            <option value="teacher">Teacher</option>
        </select>

        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>

    <p style="margin-top:16px;">Already registered? <a href="/login.php">Login here</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
