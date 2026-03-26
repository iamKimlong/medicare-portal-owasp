<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('doctor');

$conn = getDB();
$doctorId = currentUserId();

$stmt = $conn->prepare(
    "SELECT a.*, u.name AS patient_name FROM appointments a
     JOIN users u ON a.patient_id = u.id
     WHERE a.doctor_id = ? AND a.status = 'scheduled'
     ORDER BY a.datetime ASC LIMIT 10"
);
$stmt->execute([$doctorId]);
$upcoming = $stmt->fetchAll();

$stmt = $conn->prepare(
    "SELECT m.*, u.name AS sender_name FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.receiver_id = ? ORDER BY m.sent_at DESC LIMIT 5"
);
$stmt->execute([$doctorId]);
$recentMessages = $stmt->fetchAll();

$patientCount = $conn->prepare(
    "SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?"
);
$patientCount->execute([$doctorId]);
$totalPatients = (int)$patientCount->fetchColumn();

renderHeader('Doctor Dashboard');
?>

<div class="stats-row">
    <div class="stat-card">
        <span class="stat-number"><?= count($upcoming) ?></span>
        <span class="stat-label">Upcoming Appointments</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= $totalPatients ?></span>
        <span class="stat-label">Total Patients</span>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h2 class="card-title">Upcoming Appointments</h2>
        <?php if (empty($upcoming)): ?>
            <p class="text-muted">No upcoming appointments.</p>
        <?php endif; ?>
        <?php foreach ($upcoming as $appt): ?>
            <div class="list-item">
                <strong><?= esc($appt['patient_name']) ?></strong>
                <span class="list-item-date"><?= esc($appt['datetime']) ?></span>
                <p class="list-item-note"><?= esc($appt['notes'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 class="card-title">Recent Messages</h2>
        <?php if (empty($recentMessages)): ?>
            <p class="text-muted">No messages.</p>
        <?php endif; ?>
        <?php foreach ($recentMessages as $msg): ?>
            <div class="list-item">
                <strong><?= esc($msg['sender_name']) ?></strong>
                <span class="text-muted"><?= esc($msg['sent_at']) ?></span>
                <p><?= esc($msg['body']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php renderFooter(); ?>
