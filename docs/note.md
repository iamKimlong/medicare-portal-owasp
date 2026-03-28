# Testing Guide

Step-by-step instructions for testing every OWASP vulnerability in the portal. Each section explains why the vulnerability exists, how to exploit it, and how the fix works at the code level.

All tests assume you're running via Docker or Arch Linux as described in `README.md`, and that you're starting with `SECURE_MODE = false` in `config.php`.

> [!tip]
> You don't need to restart `httpd` after changing `config.php` or any PHP file. PHP reads them fresh on every request.

---

# Before You Start

1. Confirm the app is running at `http://localhost`.
2. Confirm you can log in as `minhanh@demo.com` / `password123`.
3. Use the **SECURE / VULNERABLE** pill toggle in the page footer to switch modes instantly, or edit `config.php` directly.
4. Use Firefox. Chromium has built-in XSS filtering that may interfere with the A03 XSS test.

---

# A01 - Broken Access Control (IDOR)

**File:** `patient/records.php`

## Why it's vulnerable

The page takes a `patient_id` parameter from the URL and queries the database with it directly. There is no check to verify that the logged-in user is authorized to view that patient's data. Any authenticated user can view any patient's record by changing the ID in the URL.

## Test (vulnerable)

1. Log in as Minh Anh (`minhanh@demo.com` / `password123`).
2. Navigate to **My Records**. You see Minh Anh's medical record.
3. Change the URL to: `http://localhost/patient/records.php?patient_id=2`
4. You now see Bob's full medical record - DOB, blood type, allergies, clinical notes - without any authorization check.

## Test (secure)

1. Set `SECURE_MODE = true` in `config.php`.
2. Repeat step 3 above.
3. You get a **403 Access Denied** response.

## How the fix works

The secure version casts the `patient_id` to an integer and compares it against `$_SESSION['user_id']`. If they don't match and the user isn't a doctor or admin, the request is rejected before any database query runs. This is an ownership check - the server enforces that you can only access your own data.

```php
if ((int)$id !== currentUserId() && currentRole() !== 'doctor' && currentRole() !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}
```

The query also uses a prepared statement instead of string concatenation, which fixes a secondary SQL injection vector.

---

# A02 - Cryptographic Failures (MD5 Passwords)

**File:** `auth/register.php`, `auth/login.php`

## Why it's vulnerable

Passwords are hashed with MD5, which is a fast, unsalted hash originally designed for checksums - not password storage. MD5 hashes can be reversed via precomputed rainbow tables in seconds. Every identical password produces the same hash, making bulk cracking trivial.

## Test (vulnerable)

1. Open a MySQL shell:
   ```bash
   mariadb -u root -p medicare
   ```
2. Run:
   ```sql
   SELECT email, password FROM users;
   ```
3. All passwords are 32-character hex strings. Copy any one and paste it into an online MD5 reverse lookup - the plaintext comes back instantly.

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Register a new account at `/auth/register.php`.
3. Query the database again:
   ```sql
   SELECT email, password FROM users ORDER BY id DESC LIMIT 1;
   ```
4. The new password starts with `$2y$` - a bcrypt hash that is salted and computationally expensive to crack.

> [!note]
> The original seed accounts (minhanh, chenwei, haruka.sato, admin) have MD5 hashes. Additional seed accounts (jihoon.park, xiaoming.li, admin.tanaka) have bcrypt hashes. In secure mode, `verifyPassword()` detects both formats automatically - MD5 accounts work in both modes, bcrypt accounts only work in secure mode.

## How the fix works

`password_hash($password, PASSWORD_BCRYPT)` generates a unique salt per password and runs the bcrypt algorithm with a configurable cost factor (default 10 = 1024 iterations). Even if two users have the same password, their hashes differ. Verification uses `password_verify()` which extracts the salt from the stored hash and recomputes - no raw comparison.

---

# A03 - SQL Injection

**File:** `shared/search.php`

## Why it's vulnerable

User input from the search bar is concatenated directly into the SQL query string. The database engine has no way to distinguish between data and SQL syntax, so an attacker can inject additional SQL clauses to change the query's logic.

## Test (vulnerable)

1. Log in as any user.
2. Go to **Find a Doctor**.
3. In the search box, type:
   ```
   ' OR '1'='1
   ```
4. Hit Search.
5. The constructed query becomes:
   ```sql
   SELECT * FROM users WHERE role='doctor' AND name LIKE '%' OR '1'='1%'
   ```
   The `OR '1'='1'` clause is always true, so the query returns every row in the `users` table - including emails, roles, and MD5 password hashes.

You can also try:
```
' UNION SELECT 1,2,3,4,5,6 -- -
```

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Repeat the same injection.
3. The search returns zero results. The `'` is treated as a literal character, not SQL syntax.

## How the fix works

The secure version uses PDO prepared statements. The query structure is sent to the database first with a `?` placeholder, and the user input is bound separately as a parameter. The database engine knows the parameter is data, never SQL - so injection is structurally impossible regardless of the input content.

```php
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE role='doctor' AND name LIKE ?");
$stmt->execute(["%$name%"]);
```

The secure query also returns only `id`, `name`, and `email` instead of `SELECT *`, which avoids leaking password hashes even if the query logic were somehow bypassed.

---

# A03 - Cross-Site Scripting (XSS)

**File:** `patient/chat.php`, `doctor/chat.php`

## Why it's vulnerable

Message content from the database is rendered directly into the HTML with `<?= $msg['body'] ?>`. The browser interprets any HTML or JavaScript in the message body as markup, not text. An attacker can store a payload in the database that executes in every user's browser when they view the conversation.

## Test (vulnerable)

1. Log in as Minh Anh (`minhanh@demo.com`).
2. Go to **Messages**, select Dr. Smith, and send:
   ```
   <img src=x onerror="alert(document.cookie)">
   ```
3. The page reloads and a JavaScript alert fires showing your session cookie.
4. Log in as Dr. Sato (`haruka.sato@demo.com` / `password123`), go to Messages, click Minh Anh - the alert fires again.

Other payloads to try:
```
<b onmouseover="alert('XSS')">hover me</b>
<marquee onstart="alert('XSS')">test</marquee>
```

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Send the same payload.
3. The message renders as visible text. You can read `<img src=x onerror="alert(document.cookie)">` as a string in the chat bubble - it doesn't execute.

## How the fix works

`htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` converts `<` to `&lt;`, `>` to `&gt;`, `"` to `&quot;`, and `'` to `&#039;`. The browser renders these as visible characters instead of parsing them as HTML tags or JavaScript event handlers.

```php
// Vulnerable: raw output
<p><?= $msg['body'] ?></p>

// Secure: escaped output
<p><?= htmlspecialchars($msg['body'], ENT_QUOTES, 'UTF-8') ?></p>
```

---

# A04 - Insecure Design

**File:** `patient/appointments.php`

## Why it's vulnerable

The booking form has no server-side validation beyond checking that the fields aren't empty. There is no check for conflicting time slots, no limit on how many appointments a patient can book per day, and no verification that the doctor is available. This is a design flaw, not a coding bug - the validation logic was never implemented.

## Test (vulnerable)

1. Log in as Alice. Go to **Appointments**.
2. Book the same doctor, date, and time slot repeatedly - it accepts every one. You can create 50 duplicate appointments.
3. There is no business logic preventing overbooking.

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Book an appointment, then try to book the same slot again - you get: "That time slot is already taken."
3. Book 3 appointments on the same day, then try a 4th - you get: "You cannot book more than 3 appointments per day."

## How the fix works

Two server-side checks run before the insert:

```php
// 1. Check if the slot is already taken
SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND datetime = ? AND status = 'scheduled'

// 2. Check daily booking limit
SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND DATE(datetime) = DATE(?) AND status = 'scheduled'
```

Both use prepared statements and return early with an error message if the constraint is violated. The insert only proceeds if both pass.

---

# A05 - Security Misconfiguration

**Files:** `.htaccess`, Apache config, PHP error handling

## Why it's vulnerable

In vulnerable mode, PHP exceptions bubble up to the browser with full stack traces, database connection details, and internal file paths. This gives an attacker detailed information about the server's internals - database name, table structure, PHP version, file system layout - which helps them craft targeted attacks.

## Test (vulnerable)

1. With `SECURE_MODE = false`, temporarily change `DB_NAME` in `config.php` to something that doesn't exist (e.g. `medicare_typo`).
2. Load any page.
3. You'll see a full PDO exception on screen: `SQLSTATE[HY000] [1049] Unknown database 'medicare_typo'` with the file path and line number.
4. Restore `DB_NAME` to `medicare` when done.

## Test (secure)

1. Set `SECURE_MODE = true` and repeat the same steps.
2. The user sees a generic "An error occurred. Please try again." message. No stack trace, no database name, no file paths.

## How the fix works

The `renderError()` function in `config.php` checks `SECURE_MODE`. In secure mode, it displays a generic error message regardless of the actual exception. In a production environment, you'd also set `display_errors = Off` in `/etc/php/php.ini` and log errors to a file instead of displaying them.

---

# A06 - Vulnerable Components

**File:** `composer.json`

This is a reference demo, not a runtime exploit.

## Why it's vulnerable

The project declares a dependency on `phpmailer/phpmailer` version `5.2.23`, which has **CVE-2016-10033** - a remote code execution vulnerability via mail header injection. Using outdated dependencies with known CVEs is one of the most common attack vectors in real applications.

## Test

1. Install Composer if you don't have it:
   ```bash
   sudo pacman -S composer
   ```
2. Run:
   ```bash
   cd /path/to/medicare-portal
   composer install
   composer audit
   ```
3. The audit output flags the known vulnerability with a severity level and CVE link.

## How the fix works

Update the dependency to a patched version (`6.x+`), run `composer audit` again, and confirm zero advisories. In practice, you'd also use `composer outdated` regularly and integrate dependency scanning into CI/CD.

---

# A07 - Authentication & Session Failures

**File:** `auth/login.php`

## Why it's vulnerable

When a user logs in, the server sets session variables but does not regenerate the session ID. This means the session ID from *before* login is still valid *after* login. An attacker who knows (or sets) the pre-login session ID can hijack the authenticated session - this is called **session fixation**.

## Test (vulnerable)

1. Open DevTools → Application → Cookies.
2. Note the `PHPSESSID` cookie value.
3. Log in as Alice.
4. Check the cookie again - **it's the same value**. The session ID was not regenerated.

To demonstrate fixation:
1. Visit the login page. Copy your `PHPSESSID`.
2. In a second browser (or incognito window), open DevTools → Console and run:
   ```javascript
   document.cookie = "PHPSESSID=<paste_value_here>; path=/";
   ```
3. Go back to the first browser and log in as Alice.
4. Refresh the second browser at `http://localhost/patient/dashboard.php` - you're logged in as Alice without ever entering credentials.

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Note the `PHPSESSID` before login.
3. Log in.
4. The session ID changes. The old ID is invalidated. The fixation attack no longer works.

## How the fix works

`session_regenerate_id(true)` creates a new session ID and **destroys** the old session file. The `true` parameter is important - without it, the old session data remains accessible. A login timestamp is also stored to enable session expiration checks.

```php
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['login_time'] = time();
```

---

# A08 - Unrestricted File Upload (Webshell)

**File:** `shared/upload.php`

## Why it's vulnerable

The upload handler calls `move_uploaded_file()` using the original filename with no validation. Since Apache is configured to execute `.php` files, uploading a PHP script to the `/uploads/` directory creates a web-accessible endpoint that runs arbitrary code on the server.

## Test (vulnerable)

1. Create a test file:
   ```bash
   echo '<?php echo shell_exec($_GET["cmd"]); ?>' > /tmp/shell.php
   ```
2. Log in as any user. Go to **Upload File**.
3. Upload `shell.php`.
4. Navigate to:
   ```
   http://localhost/uploads/shell.php?cmd=whoami
   ```
5. You see the output of `whoami`. You have arbitrary command execution on the server.

Try other commands:
```
http://localhost/uploads/shell.php?cmd=cat /etc/passwd
http://localhost/uploads/shell.php?cmd=ls -la /
```

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Try uploading `shell.php` again.
3. You get: "Invalid file type. Only PDF, JPEG, and PNG are allowed."
4. Even renaming `shell.php` to `shell.pdf` fails - the server checks the actual MIME type of the file content, not the extension.

## How the fix works

Three layers of defense:

1. **MIME type validation** - `finfo_file()` reads the file's magic bytes to determine the real content type, regardless of extension. Only `application/pdf`, `image/jpeg`, and `image/png` are allowed.
2. **Filename randomization** - the file is saved as a random 32-character hex string with the correct extension (`.pdf`, `.jpg`, `.png`), making it impossible to predict or request by name.
3. **Storage location** - in a production setup, uploads would be stored outside the webroot so Apache can't serve them directly. The uploaded file path would be served through a PHP handler that checks permissions.

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    die('Invalid file type.');
}
$safeName = bin2hex(random_bytes(16)) . $ext;
```

---

# A09 - Logging & Monitoring Failures

**File:** `admin/audit_log.php`

## Why it's vulnerable

In vulnerable mode, `writeAuditLog()` returns immediately without writing anything. Sensitive actions - logins, record access, file uploads, searches, messages - happen silently. If the system is compromised, there is no trail to investigate what happened, when, or by whom.

## Test (vulnerable)

1. Log in as Admin (`admin@demo.com` / `admin123`).
2. Go to **Audit Log**. The table is empty.
3. Perform several actions: log out, log back in, view patient records, upload files, search doctors.
4. Check the audit log again - still empty.

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Reset the database.
    - Manual: `mariadb -u root -p < db/schema.sql`
    - Docker: `docker exec medicare bash -c "mysql -u root -pmedicare_demo medicare < /var/www/html/db/schema.sql"`.
3. Log in as Admin and repeat the same actions.
4. Go to the Audit Log - every action is recorded with user, action description, IP address, and timestamp.

## How the fix works

In secure mode, `writeAuditLog()` inserts a row into the `audit_log` table via a prepared statement. It's called at every security-relevant point: login, logout, record access, search, upload, message send, user deletion, and appointment booking.

```php
function writeAuditLog(string $action): void {
    if (!SECURE_MODE) {
        return; // <-- the vulnerability: this line skips all logging
    }
    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, ip) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $_SERVER['REMOTE_ADDR'] ?? '']);
}
```

---

# A10 - Server-Side Request Forgery (SSRF)

**File:** `shared/insurance_fetch.php`

## Why it's vulnerable

The page takes a URL from user input and passes it directly to `file_get_contents()`. This PHP function follows the URL from the server's perspective, which means the attacker can make the server fetch internal resources that are not accessible from outside - localhost services, cloud metadata endpoints, or local files via `file://`.

## Test (vulnerable)

1. Log in as any user. Go to **Insurance Verification** in the sidebar.
2. Enter this URL:
   ```
   http://localhost/config.php
   ```
3. Hit Fetch. The response shows the full source code of `config.php`, including database credentials.
4. Try:
   ```
   file:///etc/passwd
   ```
5. You get the contents of the system password file. The server is acting as an open proxy.

## Test (secure)

1. Set `SECURE_MODE = true`.
2. Try the same URLs.
3. You get: "URL not permitted. Only approved insurance provider domains are allowed."

## How the fix works

Two layers of validation:

1. **Domain whitelist** - only URLs starting with approved insurance provider domains are allowed. Everything else is rejected before any request is made.
2. **Private IP blocking** - even if a whitelisted domain resolves to a private IP (DNS rebinding attack), the server resolves the hostname and checks the IP against RFC 1918 ranges (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) and loopback (`127.0.0.0/8`). Private IPs are blocked.

```php
$parsed = parse_url($url);
$ip = gethostbyname($parsed['host'] ?? '');
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    die('Resolved IP is in a private range. Request blocked.');
}
```

---

# Quick Test Cycle

For rapid demo iterations:
```bash
# Toggle SECURE_MODE via the UI pill toggle in the page footer,
# or manually:
vim /path/to/medicare-portal/config.php

# Reset the database (wipes all data, re-seeds accounts)
# Manual:
mariadb -u root -p < /path/to/medicare-portal/db/schema.sql
# Docker:
docker exec medicare bash -c "mysql -u root -pmedicare_demo medicare < /var/www/html/db/schema.sql"
```

---

# Troubleshooting

**"Connection refused" or blank page:**
    - Check Apache is running: `sudo systemctl status httpd`
    - Check PHP errors: `sudo journalctl -u httpd -f`
    - Verify `pdo_mysql` extension is enabled in `/etc/php/php.ini`

**"Access denied" from MySQL:**
    - Verify credentials in `config.php` match what you set during `mariadb-secure-installation`
    - Test manually: `mariadb -u root -p medicare -e "SELECT 1"`

**Uploads don't execute (A08 test fails):**
    - You must use Apache, not `php -S`. The built-in PHP server doesn't execute uploaded `.php` files from the uploads directory.
    - Check that `uploads/.htaccess` is not blocking PHP execution (in vulnerable mode, it shouldn't be).

**Session fixation test (A07) not working:**
    - Use two separate browser profiles or one regular + one incognito window. Tabs in the same browser share the same cookie jar.

**XSS payload doesn't fire:**
    - Use Firefox. Some Chromium builds have built-in XSS auditors that silently block script execution.
    - Verify `SECURE_MODE = false` in `config.php`.
    - If `<script>alert('XSS')</script>` doesn't work, use `<img src=x onerror="alert(document.cookie)">` instead - it bypasses more browser protections.

---
