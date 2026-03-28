<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireLogin();

$results = [];
$query = $_GET['q'] ?? '';

if ($query !== '') {
    $results = searchDoctors($query);
}

function searchDoctors(string $name): array {
    $conn = getDB();

    if (!SECURE_MODE) {
        return searchVulnerable($conn, $name);
    }
    return searchSecure($conn, $name);
}

function searchVulnerable(PDO $conn, string $name): array {
    try {
        $result = $conn->query(
            "SELECT * FROM users WHERE role='doctor' AND name LIKE '%$name%'"
        );
        return $result->fetchAll();
    } catch (Exception $e) {
        echo '<div class="alert alert-error">SQL Error: ' . $e->getMessage() . '</div>';
        return [];
    }
}

function searchSecure(PDO $conn, string $name): array {
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE role='doctor' AND name LIKE ?");
    $stmt->execute(["%$name%"]);
    writeAuditLog('search_doctors: ' . $name);
    return $stmt->fetchAll();
}

renderHeader('Find a Doctor');
?>

<div class="card">
    <h2 class="card-title">Doctor Search</h2>
    <form method="GET" class="search-form">
        <div class="search-row">
            <input type="text" name="q" value="<?= esc($query) ?>"
                   placeholder="Search by name..." class="search-input" autofocus>
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php if ($query !== '' && empty($results)): ?>
        <p class="text-muted">No doctors found matching "<?= esc($query) ?>".</p>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <?php if (!SECURE_MODE): ?>
                        <th>Password Hash</th>
                        <th>Role</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= esc((string)$row['id']) ?></td>
                        <td><?= esc($row['name']) ?></td>
                        <td><?= esc($row['email']) ?></td>
                        <?php if (!SECURE_MODE): ?>
                            <td><?= esc($row['password'] ?? '') ?></td>
                            <td><?= esc($row['role'] ?? '') ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php renderVulnHints(['A03-sqli', 'A02']); ?>

<?php renderFooter(); ?>
