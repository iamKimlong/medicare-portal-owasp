<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('patient');

$conn = getDB();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sendMessage($conn, $userId);
}

function sendMessage(PDO $conn, int $senderId): void {
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

$doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor'")->fetchAll();

$selectedDoctor = (int)($_GET['doctor_id'] ?? ($doctors[0]['id'] ?? 0));
$messages = fetchMessages($conn, $userId, $selectedDoctor);

function fetchMessages(PDO $conn, int $userId, int $doctorId): array {
    if ($doctorId === 0) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT m.*, u.name AS sender_name FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.sent_at ASC"
    );
    $stmt->execute([$userId, $doctorId, $doctorId, $userId]);
    return $stmt->fetchAll();
}

renderHeader('Messages');
?>

<div class="chat-layout">
    <div class="card chat-sidebar-card">
        <h3 class="card-title">Doctors</h3>
        <ul class="contact-list">
            <?php foreach ($doctors as $doc): ?>
                <li>
                    <a href="?doctor_id=<?= $doc['id'] ?>"
                       class="contact-link <?= $doc['id'] === $selectedDoctor ? 'active' : '' ?>">
                        <?= esc($doc['name']) ?>
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
                    <span class="chat-time"><?= fmtDate($msg['sent_at']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selectedDoctor > 0): ?>
            <form method="POST" class="chat-input-form">
                <input type="hidden" name="receiver_id" value="<?= $selectedDoctor ?>">
                <input type="text" name="body" placeholder="Type a message..." required class="chat-input" autocomplete="off">
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card vuln-hint">
    <h3 class="card-title">A03 - XSS in Chat</h3>
    <p>Try sending <code>&lt;img src=x onerror="alert(document.cookie)"&gt;</code> as a message.</p>
    <p>Current mode: <strong><?= SECURE_MODE ? 'SECURE' : 'VULNERABLE' ?></strong></p>
</div>

<?php renderFooter(); ?>
