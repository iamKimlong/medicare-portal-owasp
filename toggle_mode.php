<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$target = $_POST['target'] ?? 'secure_mode';

if ($target === 'secure_mode' && SHOW_SECURE_TOGGLE) {
    toggleSecureMode();
}

if ($target === 'vuln_hint') {
    toggleVulnHint();
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
exit;
