<?php
require_once __DIR__ . '/config.php';

function renderHeader(string $title = 'MediCare Portal'): void {
    $role = currentRole();
    $name = $_SESSION['user_name'] ?? 'Guest';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= esc($title) ?> — MediCare</title>
        <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body>
        <div class="layout">
            <?php renderSidebar($role); ?>
            <div class="main-area">
                <header class="top-bar">
                    <h1 class="page-title"><?= esc($title) ?></h1>
                    <div class="user-info">
                        <span class="user-name"><?= esc($name) ?></span>
                        <span class="role-badge role-<?= esc($role) ?>"><?= esc(ucfirst($role)) ?></span>
                        <a href="/auth/logout.php" class="btn btn-sm">Logout</a>
                    </div>
                </header>
                <main class="content">
    <?php
}

function renderFooter(): void {
    $mode = SECURE_MODE ? 'SECURE' : 'VULNERABLE';
    $modeClass = SECURE_MODE ? 'mode-secure' : 'mode-vuln';
    ?>
                </main>
                <footer class="app-footer">
                    <span class="mode-indicator <?= $modeClass ?>"><?= $mode ?> MODE</span>
                </footer>
            </div>
        </div>
        <script src="/assets/main.js"></script>
    </body>
    </html>
    <?php
}

function renderSidebar(string $role): void {
    $navItems = getSidebarItems($role);
    ?>
    <nav class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">+</span>
            <span class="brand-text">MediCare</span>
        </div>
        <ul class="nav-list">
            <?php foreach ($navItems as $item): ?>
                <li>
                    <a href="<?= esc($item['url']) ?>" class="nav-link"><?= esc($item['label']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php
}

function getSidebarItems(string $role): array {
    $shared = [
        ['url' => '/shared/search.php', 'label' => 'Find a Doctor'],
        ['url' => '/shared/upload.php', 'label' => 'Upload File'],
    ];

    $roleMenus = [
        'patient' => [
            ['url' => '/patient/dashboard.php', 'label' => 'Dashboard'],
            ['url' => '/patient/records.php', 'label' => 'My Records'],
            ['url' => '/patient/appointments.php', 'label' => 'Appointments'],
            ['url' => '/patient/chat.php', 'label' => 'Messages'],
        ],
        'doctor' => [
            ['url' => '/doctor/dashboard.php', 'label' => 'Dashboard'],
            ['url' => '/doctor/patients.php', 'label' => 'My Patients'],
            ['url' => '/doctor/chat.php', 'label' => 'Messages'],
        ],
        'admin' => [
            ['url' => '/admin/dashboard.php', 'label' => 'Dashboard'],
            ['url' => '/admin/users.php', 'label' => 'Manage Users'],
            ['url' => '/admin/audit_log.php', 'label' => 'Audit Log'],
        ],
    ];

    return array_merge($roleMenus[$role] ?? [], $shared);
}
