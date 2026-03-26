<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('admin');

$conn = getDB();

$logs = $conn->query(
    "SELECT a.*, u.name AS user_name FROM audit_log a
     LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.timestamp DESC LIMIT 50"
)->fetchAll();

renderHeader('Audit Log');
?>

<div class="card">
    <h2 class="card-title">Security Audit Log</h2>

    <?php if (!SECURE_MODE): ?>
        <div class="alert alert-error">
            Audit logging is <strong>disabled</strong> in VULNERABLE mode. No actions are being recorded.
            Toggle <code>SECURE_MODE = true</code> in <code>config.php</code> to enable logging.
        </div>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
        <p class="text-muted">No log entries. <?= SECURE_MODE ? '' : 'Enable SECURE_MODE to start logging.' ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>IP</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><?= esc($log['user_name'] ?? 'Unknown') ?></td>
                        <td><?= esc($log['action']) ?></td>
                        <td><?= esc($log['ip'] ?? '') ?></td>
                        <td><?= fmtDate($u['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card vuln-hint">
    <h3 class="card-title">A09 — Logging & Monitoring Failures</h3>
    <p>In VULNERABLE mode, sensitive actions like logins, record access, and uploads are <strong>not logged</strong>.</p>
    <p>Current mode: <strong><?= SECURE_MODE ? 'SECURE' : 'VULNERABLE' ?></strong></p>
</div>

<?php renderFooter(); ?>
