<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('patient');

$conn = getDB();
$userId = currentUserId();

$stmt = $conn->prepare(
    "SELECT * FROM appointments WHERE patient_id = ? ORDER BY datetime DESC LIMIT 5"
);
$stmt->execute([$userId]);
$appointments = $stmt->fetchAll();

$stmt = $conn->prepare(
    "SELECT m.*, u.name AS sender_name FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.receiver_id = ? ORDER BY m.sent_at DESC LIMIT 5"
);
$stmt->execute([$userId]);
$recentMessages = $stmt->fetchAll();

renderHeader('Patient Dashboard');
?>

<div class="dashboard-grid">
    <div class="card">
        <h2 class="card-title">Upcoming Appointments</h2>
        <?php if (empty($appointments)): ?>
            <p class="text-muted">No appointments found.</p>
        <?php endif; ?>
        <?php foreach ($appointments as $appt): ?>
            <div class="list-item">
                <span class="list-item-date"><?= esc($appt['datetime']) ?></span>
                <span class="status-badge status-<?= esc($appt['status']) ?>">
                    <?= esc(ucfirst($appt['status'])) ?>
                </span>
                <p class="list-item-note"><?= esc($appt['notes'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 class="card-title">Recent Messages</h2>
        <?php if (empty($recentMessages)): ?>
            <p class="text-muted">No messages yet.</p>
        <?php endif; ?>
        <?php foreach ($recentMessages as $msg): ?>
            <div class="list-item">
                <strong><?= esc($msg['sender_name']) ?></strong>
                <span class="text-muted"><?= esc($msg['sent_at']) ?></span>
                <p><?= esc($msg['body']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 class="card-title">Quick Links</h2>
        <div class="quick-links">
            <a href="/patient/records.php" class="btn">View Records</a>
            <a href="/patient/appointments.php" class="btn">Appointments</a>
            <a href="/patient/chat.php" class="btn">Messages</a>
            <a href="/shared/search.php" class="btn">Find a Doctor</a>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
