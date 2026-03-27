<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl());
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = processLogin($_POST['email'] ?? '', $_POST['password'] ?? '');
}

function processLogin(string $email, string $password): string {
    if ($email === '' || $password === '') {
        return 'Email and password are required.';
    }

    $conn = getDB();

    if (!SECURE_MODE) {
        return loginVulnerable($conn, $email, $password);
    }
    return loginSecure($conn, $email, $password);
}

function loginVulnerable(PDO $conn, string $email, string $password): string {
    $hash = md5($password);
    $result = $conn->query(
        "SELECT * FROM users WHERE email = '$email' AND password = '$hash'"
    );
    $user = $result->fetch();

    if (!$user) {
        return 'Invalid credentials.';
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    header('Location: ' . getDashboardUrl());
    exit;
}

function loginSecure(PDO $conn, string $email, string $password): string {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return 'Invalid credentials.';
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    writeAuditLog('login');
    header('Location: ' . getDashboardUrl());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediCare Portal</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-brand">
            <span class="brand-icon brand-icon-lg">+</span>
            <h1>MediCare Portal</h1>
            <p class="auth-subtitle">Secure Patient Health Platform</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter your password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
        </form>
        <p class="auth-footer-link">
            Don't have an account? <a href="/auth/register.php">Register</a>
        </p>
    </div>
</body>
</html>
