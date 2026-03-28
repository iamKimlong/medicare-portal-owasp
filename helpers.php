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
        <title><?= esc($title) ?> - MediCare</title>
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
    ?>
                </main>
                <footer class="app-footer">
                    <?php renderModeStatus(); ?>
                </footer>
            </div>
        </div>
        <script src="/assets/main.js"></script>
    </body>
    </html>
    <?php
}

function renderModeStatus(): void {
    $showToggle = SHOW_SECURE_TOGGLE;
    $showHint = SHOW_VULN_HINT;

    if (!$showToggle && !$showHint) {
        return;
    }

    $isSecure = SECURE_MODE;
    ?>
    <div class="mode-status" id="modeStatus" data-secure="<?= $isSecure ? '1' : '0' ?>" data-hints="<?= $showHint ? '1' : '0' ?>">
        <?php if ($showToggle): ?>
            <button type="button" class="mode-pill" id="modeToggle">
                <span class="mode-pill-option mode-pill-secure <?= $isSecure ? 'is-active' : '' ?>">SECURE</span>
                <span class="mode-pill-option mode-pill-vuln <?= !$isSecure ? 'is-active' : '' ?>">VULNERABLE</span>
            </button>
        <?php else: ?>
            <span class="mode-pill-static <?= $isSecure ? 'mode-pill-static--secure' : 'mode-pill-static--vuln' ?>">
                <?= $isSecure ? 'SECURE' : 'VULNERABLE' ?> MODE
            </span>
        <?php endif; ?>
        <button type="button" class="hints-toggle <?= $showHint ? 'is-active' : '' ?>" id="hintsToggle" title="Toggle vulnerability hints">
            <span class="hints-toggle-icon">?</span>
            <span class="hints-toggle-label"><?= $showHint ? 'Hide Hints' : 'Show Hints' ?></span>
        </button>
    </div>
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
        ['url' => '/shared/insurance_fetch.php', 'label' => 'Insurance Verification'],
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

function getVulnHintRegistry(): array {
    return [
        'A01' => [
            'title' => 'A01 - Broken Access Control (IDOR)',
            'desc' => 'The <code>patient_id</code> parameter is used directly without ownership checks.',
            'test' => 'Change <code>?patient_id=2</code> in the URL to access another patient\'s data.',
        ],
        'A02' => [
            'title' => 'A02 - Cryptographic Failures',
            'desc' => 'Passwords are hashed with MD5 - fast, unsalted, and trivially reversible via rainbow tables.',
            'test' => 'Run <code>SELECT email, password FROM users</code> and paste any hash into an MD5 lookup.',
        ],
        'A03-sqli' => [
            'title' => 'A03 - Injection (SQL)',
            'desc' => 'User input is concatenated into SQL queries, allowing attackers to alter query logic.',
            'test' => 'Search for <code>\' OR \'1\'=\'1</code> to dump all users including password hashes.',
        ],
        'A03-xss' => [
            'title' => 'A03 - Injection (XSS)',
            'desc' => 'Message content is rendered as raw HTML. Stored payloads execute in every viewer\'s browser.',
            'test' => 'Send <code>&lt;img src=x onerror="alert(document.cookie)"&gt;</code> as a message.',
        ],
        'A04' => [
            'title' => 'A04 - Insecure Design',
            'desc' => 'No server-side validation for conflicting time slots or booking limits.',
            'test' => 'Book the same doctor, date, and time repeatedly - every duplicate is accepted.',
        ],
        'A05' => [
            'title' => 'A05 - Security Misconfiguration',
            'desc' => 'PHP exceptions with stack traces, database names, and file paths are shown to the user.',
            'test' => 'Change <code>DB_NAME</code> in <code>config.php</code> to a typo and reload any page.',
        ],
        'A06' => [
            'title' => 'A06 - Vulnerable Components',
            'desc' => '<code>composer.json</code> declares PHPMailer 5.2.23 which has CVE-2016-10033 (RCE).',
            'test' => 'Run <code>composer audit</code> to see the advisory.',
        ],
        'A07' => [
            'title' => 'A07 - Auth & Session Failures',
            'desc' => 'Session ID is not regenerated on login, enabling session fixation attacks.',
            'test' => 'Note <code>PHPSESSID</code> before and after login - it stays the same.',
        ],
        'A08' => [
            'title' => 'A08 - Unrestricted File Upload',
            'desc' => 'No file type validation. Uploading a <code>.php</code> file creates a web-accessible shell.',
            'test' => 'Upload a PHP file, then visit <code>/uploads/yourfile.php?cmd=whoami</code>.',
        ],
        'A09' => [
            'title' => 'A09 - Logging & Monitoring Failures',
            'desc' => '<code>writeAuditLog()</code> is a no-op in vulnerable mode. No actions are recorded.',
            'test' => 'Perform actions, then check the Audit Log as admin - it\'s empty.',
        ],
        'A10' => [
            'title' => 'A10 - Server-Side Request Forgery',
            'desc' => 'User-supplied URLs are fetched by the server with no domain or IP restrictions.',
            'test' => 'Fetch <code>http://localhost/config.php</code> or <code>file:///etc/passwd</code>.',
        ],
    ];
}

function renderVulnHints(array $keys): void {
    if (!SHOW_VULN_HINT) {
        return;
    }

    $registry = getVulnHintRegistry();
    $hints = [];
    foreach ($keys as $key) {
        if (!isset($registry[$key])) {
            continue;
        }
        $hints[] = $registry[$key];
    }

    if (empty($hints)) {
        return;
    }
    ?>
    <div class="card vuln-hint">
        <h3 class="card-title">OWASP 2021 Top 10 Vulnerabilities on This Page</h3>
        <?php foreach ($hints as $hint): ?>
            <div class="vuln-hint-item">
                <strong><?= $hint['title'] ?></strong>
                <p><?= $hint['desc'] ?></p>
                <p class="vuln-hint-test">Try it: <?= $hint['test'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
