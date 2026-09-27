<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: /dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // compared as plain text, no hashing (per request)

    $user = get_user_by_username($conn, $username);

    // "Credentials Valid?" check
    if ($user && $user['password_hash'] === $password) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        log_login($conn, $user['user_id'], 1);

        header("Location: /dashboard.php");
        exit;
    } else {
        if ($user) log_login($conn, $user['user_id'], 0);
        $error = "Invalid username or password."; // "Show Error Message"
    }
}

$page_title = "Login";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card auth-card">
    <h2>Login</h2>

    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" action="/login.php">
        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>

    <p style="margin-top:16px;">Not registered yet? <a href="/register.php">Create an account</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
