<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('patient');

$conn = getDB();
$userId = currentUserId();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = handleBooking($conn, $userId);
}

function handleBooking(PDO $conn, int $userId): string {
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $datetime = $_POST['datetime'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if ($doctorId === 0 || $datetime === '') {
        return 'Doctor and date/time are required.';
    }

    if (!SECURE_MODE) {
        return bookVulnerable($conn, $userId, $doctorId, $datetime, $notes);
    }
    return bookSecure($conn, $userId, $doctorId, $datetime, $notes);
}

function bookVulnerable(PDO $conn, int $userId, int $doctorId, string $datetime, string $notes): string {
    $conn->query(
        "INSERT INTO appointments (patient_id, doctor_id, datetime, notes) VALUES ($userId, $doctorId, '$datetime', '$notes')"
    );
    return 'Appointment booked.';
}

function bookSecure(PDO $conn, int $userId, int $doctorId, string $datetime, string $notes): string {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND datetime = ? AND status = 'scheduled'"
    );
    $stmt->execute([$doctorId, $datetime]);
    if ((int)$stmt->fetchColumn() > 0) {
        return 'That time slot is already taken.';
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND DATE(datetime) = DATE(?) AND status = 'scheduled'"
    );
    $stmt->execute([$userId, $datetime]);
    if ((int)$stmt->fetchColumn() >= 3) {
        return 'You cannot book more than 3 appointments per day.';
    }

    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, datetime, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $doctorId, $datetime, $notes]);
    writeAuditLog('book_appointment: doctor ' . $doctorId);
    return 'Appointment booked successfully.';
}

$stmt = $conn->prepare("SELECT * FROM appointments WHERE patient_id = ? ORDER BY datetime DESC");
$stmt->execute([$userId]);
$appointments = $stmt->fetchAll();

$doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor'")->fetchAll();

renderHeader('Appointments');
?>

<?php if ($message): ?>
    <div class="alert <?= str_contains($message, 'success') || $message === 'Appointment booked.' ? 'alert-success' : 'alert-error' ?>">
        <?= esc($message) ?>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="card">
        <h2 class="card-title">Book Appointment</h2>
        <form method="POST" class="form-stack">
            <div class="form-group">
                <label for="doctor_id">Doctor</label>
                <select name="doctor_id" id="doctor_id" required>
                    <option value="">Select a doctor</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>"><?= esc($doc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="datetime">Date & Time</label>
                <input type="datetime-local" name="datetime" id="datetime" required>
            </div>
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" rows="3" placeholder="Reason for visit"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Book</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Your Appointments</h2>
        <?php if (empty($appointments)): ?>
            <p class="text-muted">No appointments.</p>
        <?php endif; ?>
        <?php foreach ($appointments as $appt): ?>
            <div class="list-item">
                <span class="list-item-date"><?= esc($appt['datetime']) ?></span>
                <span class="status-badge status-<?= esc($appt['status']) ?>"><?= esc(ucfirst($appt['status'])) ?></span>
                <p class="list-item-note"><?= esc($appt['notes'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php renderFooter(); ?>
