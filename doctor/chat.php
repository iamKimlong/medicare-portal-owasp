<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('doctor');

$conn = getDB();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sendDoctorMessage($conn, $userId);
}

function sendDoctorMessage(PDO $conn, int $senderId): void {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $body = $_POST['body'] ?? '';

    if ($receiverId === 0 || $body === '') {
        return;
    }

    if (!SECURE_MODE) {
        $escaped = $conn->quote($body);
        $conn->query(
            "INSERT INTO messages (sender_id, receiver_id, body) VALUES ($senderId, $receiverId, $escaped)"
        );
        return;
    }

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)");
    $stmt->execute([$senderId, $receiverId, $body]);
    writeAuditLog('send_message to user ' . $receiverId);
}

$patientsStmt = $conn->prepare(
    "SELECT DISTINCT u.id, u.name FROM appointments a
     JOIN users u ON a.patient_id = u.id WHERE a.doctor_id = ? ORDER BY u.name"
);
$patientsStmt->execute([$userId]);
$patients = $patientsStmt->fetchAll();

$selectedPatient = (int)($_GET['patient_id'] ?? ($patients[0]['id'] ?? 0));

$messages = [];
if ($selectedPatient > 0) {
    $stmt = $conn->prepare(
        "SELECT m.*, u.name AS sender_name FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.sent_at ASC"
    );
    $stmt->execute([$userId, $selectedPatient, $selectedPatient, $userId]);
    $messages = $stmt->fetchAll();
}

renderHeader('Messages');
?>

<div class="chat-layout">
    <div class="card chat-sidebar-card">
        <h3 class="card-title">Patients</h3>
        <ul class="contact-list">
            <?php foreach ($patients as $p): ?>
                <li>
                    <a href="?patient_id=<?= $p['id'] ?>"
                       class="contact-link <?= $p['id'] === $selectedPatient ? 'active' : '' ?>">
                        <?= esc($p['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card chat-main">
        <div class="chat-messages" id="chatMessages">
            <?php foreach ($messages as $msg): ?>
                <?php $isOwn = (int)$msg['sender_id'] === $userId; ?>
                <div class="chat-bubble <?= $isOwn ? 'chat-own' : 'chat-other' ?>">
                    <span class="chat-sender"><?= esc($msg['sender_name']) ?></span>
                    <?php if (!SECURE_MODE): ?>
                        <p><?= $msg['body'] ?></p>
                    <?php else: ?>
                        <p><?= esc($msg['body']) ?></p>
                    <?php endif; ?>
                    <span class="chat-time"><?= esc($msg['sent_at']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selectedPatient > 0): ?>
            <form method="POST" class="chat-input-form">
                <input type="hidden" name="receiver_id" value="<?= $selectedPatient ?>">
                <input type="text" name="body" placeholder="Type a message..." required class="chat-input" autocomplete="off">
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>
