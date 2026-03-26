<?php
define('SECURE_MODE', false);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'awdonline');
define('DB_NAME', 'medicare');

function getDB(): PDO {
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    if (!SECURE_MODE) {
        $conn = new PDO($dsn, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    }

    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $conn;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (isLoggedIn()) {
        return;
    }
    header('Location: /auth/login.php');
    exit;
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] === $role) {
        return;
    }
    http_response_code(403);
    die('Access denied.');
}

function currentUserId(): int {
    return (int) $_SESSION['user_id'];
}

function currentRole(): string {
    return $_SESSION['role'] ?? '';
}

function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fmtDate(string $datetime): string {
    return date('d-m-Y H:i', strtotime($datetime));
}

function writeAuditLog(string $action): void {
    if (!SECURE_MODE) {
        return;
    }

    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, ip) VALUES (?, ?, ?)");
    $userId = $_SESSION['user_id'] ?? null;
    $stmt->execute([$userId, $action, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function getDashboardUrl(): string {
    $routes = [
        'patient' => '/patient/dashboard.php',
        'doctor'  => '/doctor/dashboard.php',
        'admin'   => '/admin/dashboard.php',
    ];
    return $routes[currentRole()] ?? '/auth/login.php';
}

function renderError(string $message): void {
    if (SECURE_MODE) {
        echo '<div class="alert alert-error">An error occurred. Please try again.</div>';
        return;
    }
    echo '<div class="alert alert-error">' . $message . '</div>';
}
