<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('doctor');

$conn = getDB();
$doctorId = currentUserId();

$stmt = $conn->prepare(
    "SELECT DISTINCT u.id, u.name, u.email, p.blood_type, p.allergies
     FROM appointments a
     JOIN users u ON a.patient_id = u.id
     LEFT JOIN patients p ON p.user_id = u.id
     WHERE a.doctor_id = ?
     ORDER BY u.name"
);
$stmt->execute([$doctorId]);
$patients = $stmt->fetchAll();

renderHeader('My Patients');
?>

<div class="card">
    <h2 class="card-title">Patient List</h2>
    <?php if (empty($patients)): ?>
        <p class="text-muted">No patients assigned.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Blood Type</th>
                    <th>Allergies</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= esc($p['name']) ?></td>
                        <td><?= esc($p['email']) ?></td>
                        <td><?= esc($p['blood_type'] ?? 'N/A') ?></td>
                        <td><?= esc($p['allergies'] ?? 'None') ?></td>
                        <td>
                            <a href="/patient/records.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm">View Record</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
