<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireLogin();

$conn = getDB();

$record = SECURE_MODE ? fetchRecordSecure($conn) : fetchRecordVulnerable($conn);

function fetchRecordVulnerable(PDO $conn): ?array {
    $id = $_GET['patient_id'] ?? $_SESSION['user_id'];
    if ($id === '') {
        $id = $_SESSION['user_id'];
    }
    $result = $conn->query("SELECT p.*, u.name, u.email FROM patients p JOIN users u ON p.user_id = u.id WHERE p.user_id = $id");
    return $result->fetch() ?: null;
}

function fetchRecordSecure(PDO $conn): ?array {
    $id = $_GET['patient_id'] ?? $_SESSION['user_id'];
    if ($id === '') {
        $id = $_SESSION['user_id'];
    }
    if ((int)$id !== currentUserId() && currentRole() !== 'doctor' && currentRole() !== 'admin') {
        http_response_code(403);
        die('Access denied.');
    }

    $stmt = $conn->prepare("SELECT p.*, u.name, u.email FROM patients p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
    $stmt->execute([$id]);
    writeAuditLog('view_record: patient ' . $id);
    return $stmt->fetch() ?: null;
}

renderHeader('Patient Records');
?>

<div class="card">
    <h2 class="card-title">Medical Record</h2>
    <?php if (!$record): ?>
        <p class="text-muted">No record found.</p>
    <?php else: ?>
        <table class="data-table">
            <tr><th>Name</th><td><?= esc($record['name']) ?></td></tr>
            <tr><th>Email</th><td><?= esc($record['email']) ?></td></tr>
            <tr><th>Date of Birth</th><td><?= esc($record['dob'] ?? 'N/A') ?></td></tr>
            <tr><th>Blood Type</th><td><?= esc($record['blood_type'] ?? 'N/A') ?></td></tr>
            <tr><th>Allergies</th><td><?= esc($record['allergies'] ?? 'None') ?></td></tr>
            <tr><th>Notes</th><td><?= esc($record['notes'] ?? '') ?></td></tr>
        </table>
    <?php endif; ?>
</div>

<?php renderVulnHints(['A01']); ?>

<?php renderFooter(); ?>
