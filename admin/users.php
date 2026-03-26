<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireRole('admin');

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    deleteUser($conn, (int)$_POST['delete_id']);
}

function deleteUser(PDO $conn, int $id): void {
    if ($id === currentUserId()) {
        return;
    }

    if (!SECURE_MODE) {
        $conn->query("DELETE FROM users WHERE id = $id");
        return;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    writeAuditLog('delete_user: ' . $id);
}

$users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY id")->fetchAll();

renderHeader('Manage Users');
?>

<div class="card">
    <h2 class="card-title">All Users</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= esc($u['name']) ?></td>
                    <td><?= esc($u['email']) ?></td>
                    <td><span class="role-badge role-<?= esc($u['role']) ?>"><?= esc(ucfirst($u['role'])) ?></span></td>
                    <td><?= fmtDate($u['created_at']) ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== currentUserId()): ?>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">Current</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php renderFooter(); ?>
