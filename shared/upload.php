<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireLogin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = handleUpload();
    $message = $result['message'];
    $messageType = $result['type'];
}

function handleUpload(): array {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        return ['message' => 'No file uploaded or upload error.', 'type' => 'error'];
    }

    if (!SECURE_MODE) {
        return uploadVulnerable($_FILES['file']);
    }
    return uploadSecure($_FILES['file']);
}

function uploadVulnerable(array $file): array {
    $destination = __DIR__ . '/../uploads/' . $file['name'];
    move_uploaded_file($file['tmp_name'], $destination);

    $conn = getDB();
    $userId = currentUserId();
    $name = $file['name'];
    $conn->query(
        "INSERT INTO uploads (user_id, filename, filepath) VALUES ($userId, '$name', 'uploads/$name')"
    );

    return [
        'message' => 'File uploaded: <a href="/uploads/' . $file['name'] . '">' . $file['name'] . '</a>',
        'type' => 'success',
    ];
}

function uploadSecure(array $file): array {
    $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        return ['message' => 'Invalid file type. Only PDF, JPEG, and PNG are allowed.', 'type' => 'error'];
    }

    $ext = match ($mime) {
        'application/pdf' => '.pdf',
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        default => '.dat',
    };

    $safeName = bin2hex(random_bytes(16)) . $ext;
    $destination = __DIR__ . '/../uploads/' . $safeName;
    move_uploaded_file($file['tmp_name'], $destination);

    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO uploads (user_id, filename, filepath) VALUES (?, ?, ?)");
    $stmt->execute([currentUserId(), $file['name'], 'uploads/' . $safeName]);
    writeAuditLog('upload_file: ' . $file['name']);

    return ['message' => 'File uploaded securely.', 'type' => 'success'];
}

$conn = getDB();
$stmt = $conn->prepare("SELECT * FROM uploads WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([currentUserId()]);
$uploads = $stmt->fetchAll();

renderHeader('Upload File');
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="card">
        <h2 class="card-title">Upload Document</h2>
        <form method="POST" enctype="multipart/form-data" class="form-stack">
            <div class="form-group">
                <label for="file">Select File</label>
                <input type="file" name="file" id="file" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Your Uploads</h2>
        <?php if (empty($uploads)): ?>
            <p class="text-muted">No files uploaded yet.</p>
        <?php endif; ?>
        <?php foreach ($uploads as $upload): ?>
            <div class="list-item">
                <a href="/<?= esc($upload['filepath']) ?>"><?= esc($upload['filename']) ?></a>
                <span class="text-muted"><?= esc($upload['uploaded_at']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card vuln-hint">
    <h3 class="card-title">A08 — File Upload / Webshell</h3>
    <p>Try uploading a <code>.php</code> file and navigating to <code>/uploads/yourfile.php</code>.</p>
    <p>Current mode: <strong><?= SECURE_MODE ? 'SECURE' : 'VULNERABLE' ?></strong></p>
</div>

<?php renderFooter(); ?>
