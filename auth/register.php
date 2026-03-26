<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl());
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = processRegistration(
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['password'] ?? ''
    );
    $error = $result['error'];
    $success = $result['success'];
}

function processRegistration(string $name, string $email, string $password): array {
    if ($name === '' || $email === '' || $password === '') {
        return ['error' => 'All fields are required.', 'success' => ''];
    }

    $conn = getDB();

    if (!SECURE_MODE) {
        return registerVulnerable($conn, $name, $email, $password);
    }
    return registerSecure($conn, $name, $email, $password);
}

function registerVulnerable(PDO $conn, string $name, string $email, string $password): array {
    $hash = md5($password);
    try {
        $conn->query(
            "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$hash', 'patient')"
        );
    } catch (Exception $e) {
        return ['error' => 'Registration failed: ' . $e->getMessage(), 'success' => ''];
    }
    return ['error' => '', 'success' => 'Account created. You may now log in.'];
}

function registerSecure(PDO $conn, string $name, string $email, string $password): array {
    if (strlen($password) < 8) {
        return ['error' => 'Password must be at least 8 characters.', 'success' => ''];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'patient')");
        $stmt->execute([$name, $email, $hash]);
    } catch (Exception $e) {
        return ['error' => 'Registration failed. Email may already be in use.', 'success' => ''];
    }

    writeAuditLog('register: ' . $email);
    return ['error' => '', 'success' => 'Account created. You may now log in.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — MediCare Portal</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-brand">
            <span class="brand-icon brand-icon-lg">+</span>
            <h1>MediCare Portal</h1>
            <p class="auth-subtitle">Create Your Account</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= esc($success) ?></div>
        <?php endif; ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required placeholder="Jane Doe">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Choose a password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </form>
        <p class="auth-footer-link">
            Already registered? <a href="/auth/login.php">Sign in</a>
        </p>
    </div>
</body>
</html>
