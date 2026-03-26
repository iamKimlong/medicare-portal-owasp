<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireLogin();

$fetchResult = '';
$fetchError = '';
$url = $_GET['url'] ?? '';

if ($url !== '') {
    $result = fetchInsuranceData($url);
    $fetchResult = $result['data'];
    $fetchError = $result['error'];
}

function fetchInsuranceData(string $url): array {
    if (!SECURE_MODE) {
        return fetchVulnerable($url);
    }
    return fetchSecure($url);
}

function fetchVulnerable(string $url): array {
    $content = @file_get_contents($url);
    if ($content === false) {
        return ['data' => '', 'error' => 'Failed to fetch URL: ' . $url];
    }
    return ['data' => $content, 'error' => ''];
}

function fetchSecure(string $url): array {
    $allowed = [
        'https://insurance-provider-a.com',
        'https://insurance-provider-b.com',
    ];

    $isAllowed = false;
    foreach ($allowed as $domain) {
        if (str_starts_with($url, $domain)) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        return ['data' => '', 'error' => 'URL not permitted. Only approved insurance provider domains are allowed.'];
    }

    $parsed = parse_url($url);
    $ip = gethostbyname($parsed['host'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['data' => '', 'error' => 'Resolved IP is in a private range. Request blocked.'];
    }

    writeAuditLog('insurance_fetch: ' . $url);
    $content = @file_get_contents($url);
    if ($content === false) {
        return ['data' => '', 'error' => 'Failed to fetch URL.'];
    }
    return ['data' => $content, 'error' => ''];
}

renderHeader('Insurance Verification');
?>

<div class="card">
    <h2 class="card-title">Fetch Insurance Data</h2>
    <form method="GET" class="search-form">
        <div class="search-row">
            <input type="url" name="url" value="<?= esc($url) ?>"
                   placeholder="https://insurance-provider-a.com/verify?id=12345" class="search-input">
            <button type="submit" class="btn btn-primary">Fetch</button>
        </div>
    </form>
</div>

<?php if ($fetchError): ?>
    <div class="alert alert-error"><?= esc($fetchError) ?></div>
<?php endif; ?>

<?php if ($fetchResult): ?>
    <div class="card">
        <h2 class="card-title">Response</h2>
        <pre class="code-block"><?= esc($fetchResult) ?></pre>
    </div>
<?php endif; ?>

<div class="card vuln-hint">
    <h3 class="card-title">A10 — SSRF</h3>
    <p>Try fetching <code>http://localhost/config.php</code> or <code>file:///etc/passwd</code>.</p>
    <p>Current mode: <strong><?= SECURE_MODE ? 'SECURE' : 'VULNERABLE' ?></strong></p>
</div>

<?php renderFooter(); ?>
