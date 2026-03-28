<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('admin');

$conn = getDB();

$userCount = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$appointmentCount = (int)$conn->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$messageCount = (int)$conn->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$uploadCount = (int)$conn->query("SELECT COUNT(*) FROM uploads")->fetchColumn();

$recentUsers = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

renderHeader('Admin Dashboard');
?>

<div class="stats-row">
    <div class="stat-card">
        <span class="stat-number"><?= $userCount ?></span>
        <span class="stat-label">Users</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= $appointmentCount ?></span>
        <span class="stat-label">Appointments</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= $messageCount ?></span>
        <span class="stat-label">Messages</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= $uploadCount ?></span>
        <span class="stat-label">Uploads</span>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Recent Users</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentUsers as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= esc($u['name']) ?></td>
                    <td><?= esc($u['email']) ?></td>
                    <td><span class="role-badge role-<?= esc($u['role']) ?>"><?= esc(ucfirst($u['role'])) ?></span></td>
                    <td><?= fmtDate($u['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2 class="card-title">Security Mode</h2>
    <p>Current mode: <strong class="<?= SECURE_MODE ? 'text-secure' : 'text-vuln' ?>">
        <?= SECURE_MODE ? 'SECURE' : 'VULNERABLE' ?>
    </strong></p>
    <p>Toggle <code>SECURE_MODE</code> in <code>config.php</code> to switch between vulnerable and hardened behaviors.</p>
</div>

<?php renderVulnHints(['A05']); ?>

<?php renderFooter(); ?>
