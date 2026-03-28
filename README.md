[Setup](#setup) · [Test Accounts](#test-accounts) · [SECURE_MODE](#how-secure_mode-works) · [OWASP Coverage](#owasp-top-10-coverage) · [Project Structure](#project-structure)

# MediCare Portal

**Intentionally vulnerable healthcare patient portal for demonstrating the OWASP Top 10 (2021).**

Every vulnerability has a hardened counterpart controlled by a single boolean in `config.php`. Flip it during a live demo to show the attack, then the fix, in real time.

![image](https://github.com/user-attachments/assets/432dc62b-daab-4ecc-a9e7-402ed8b794ca)

---

<a name="setup"></a>
## ⚡ Setup

Pick one:

<details>
<summary><strong>Option A: Docker (recommended)</strong></summary>

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) installed and running

### 1. Build and run

```bash
cd medicare-portal
docker build -t medicare-portal .
docker run -d -p 80:80 --name medicare medicare-portal
```

### 2. Open the app

Go to `http://localhost` - you should see the login page.

### 3. Stop / restart

```bash
docker stop medicare
docker start medicare

# or
docker restart medicare
```

### 4. Reset the database

```bash
docker exec medicare bash -c "mysql -u root -pmedicare_demo medicare < /var/www/html/db/schema.sql"
```

### 5. Teardown

```bash
docker rm -f medicare
docker rmi medicare-portal
```

> **Note:** The Docker image bundles Apache, PHP, and MariaDB in a single container for simplicity.
> This is fine for a demo - don't do this in production.

</details>

<details>
<summary><strong>Option B: Manual setup (Arch Linux + Apache + MariaDB)</strong></summary>

> **Note:** These commands are specific to Arch Linux. Adjust package names and service commands for your distribution.

### 1. Install dependencies

```bash
sudo pacman -S php apache mariadb php-apache
```

### 2. Set up MariaDB

```bash
sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
sudo systemctl start mariadb
sudo systemctl enable mariadb
sudo mariadb-secure-installation
```

Set a root password when prompted. Then import the schema:
```bash
mariadb -u root -p < db/schema.sql
```

This creates the `medicare` database, all tables, and seeds the test accounts.

> **Note:** If you get `ERROR 1698 (28000): Access denied for user 'root'@'localhost'`,
> run `sudo mariadb-secure-installation` again and change the root password when prompted.

### 3. Configure Apache

All edits below go in `/etc/httpd/conf/httpd.conf`.

**Switch to prefork MPM** (required because Arch's PHP module is not thread-safe):
```apache
# Find this line and comment it out:
#LoadModule mpm_event_module modules/mod_mpm_event.so

# Add below it:
LoadModule mpm_prefork_module modules/mod_mpm_prefork.so
```

**Enable PHP and rewrite** (add at the end of the `LoadModule` list):
```apache
LoadModule php_module modules/libphp.so
AddHandler php-script .php
Include conf/extra/php_module.conf
```

**Uncomment mod_rewrite** (find the existing commented line):
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

**Set the document root** (find and replace the existing `DocumentRoot` and `<Directory>` block):
```apache
DocumentRoot "/path/to/medicare-portal"
<Directory "/path/to/medicare-portal">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Replace `/path/to/medicare-portal` with the actual absolute path to the project.

### 4. Configure PHP

Edit `/etc/php/php.ini` and uncomment:
```ini
extension=pdo_mysql
extension=mysqli
```

### 5. Allow Apache to access your home directory

> **Note:** Skip this step if your project is outside `/home` (e.g. in `/srv/http`).

Arch's systemd unit for Apache sets `ProtectHome=yes`, which blocks access to anything under `/home`. If your project lives there, override it:

```bash
sudo vim /etc/systemd/system/httpd.service.d/hardening.conf
```

Uncomment (or add) this line:
```ini
ProtectHome=no
```

Then reload the service config:
```bash
sudo systemctl daemon-reload
```

### 6. Set file permissions

```bash
chmod -R 755 /path/to/medicare-portal
chmod -R 777 /path/to/medicare-portal/uploads
```

### 7. Update database credentials

Edit `config.php`:
```php
define('DB_PASS', 'your_password_here');  // whatever you set in step 2
```

### 8. Start Apache

```bash
sudo systemctl restart httpd
```

Open `http://localhost` - you should see the login page.

### Alternative: PHP built-in server (no Apache)

For quick testing without Apache config:
```bash
cd /path/to/medicare-portal
php -S localhost:8080
```

This works for most features but `.htaccess` rules won't apply, and the webshell upload demo (A08) won't auto-execute PHP files in `/uploads/`.

### Teardown

See [docs/undo.md](docs/undo.md) for full reversal steps.

</details>

---

<a name="test-accounts"></a>
## Test Accounts

Seed accounts with **MD5 hashes** (for A02 vulnerability demo):
| Role    | Email                | Password    |
|---------|-------------------   |-------------|
| Patient | minhanh@demo.com     | password123 |
| Patient | chenwei@demo.com     | password123 |
| Doctor  | haruka.sato@demo.com | password123 |
| Admin   | admin@demo.com       | admin123    |

Seed accounts with **bcrypt hashes** (for secure mode login):
| Role    | Email                 | Password    |
|---------|-----------------------|-------------|
| Patient | jihoon.park@demo.com  | password123 |
| Doctor  | xiaoming.li@demo.com  | password123 |
| Admin   | admin.tanaka@demo.com | admin123    |

MD5 accounts work in both modes. Bcrypt accounts only work in secure mode.

---

<a name="how-secure_mode-works"></a>
## ⚙️ How SECURE_MODE Works

Every vulnerable code path checks a single constant in `config.php`:
```php
define('SECURE_MODE', false);  // vulnerable
define('SECURE_MODE', true);   // hardened
```

Each feature file contains two functions - one vulnerable, one secure - and dispatches to the right one based on this flag. Change the value, refresh the browser, and the behavior flips instantly. No restart needed.

### UI Toggles

The app footer includes a pill toggle to switch between SECURE and VULNERABLE mode directly from the browser - no need to edit `config.php` manually. A separate button toggles the OWASP vulnerability hint cards on each page.

Both toggles rewrite `config.php` on disk so the change persists across requests.

### Config Options

```php
define('SECURE_MODE', false);       // false = vulnerable, true = hardened
define('SHOW_SECURE_TOGGLE', true); // show/hide the mode toggle in the footer
define('SHOW_VULN_HINT', true);     // show/hide OWASP vulnerability hint cards
```

Set `SHOW_SECURE_TOGGLE` and `SHOW_VULN_HINT` to `false` for a clean demo without UI chrome.

---

<a name="owasp-top-10-coverage"></a>
## 🚀 OWASP Top 10 Coverage

| #   | Category                        | File                         | Vulnerable Behavior                     | Secure Fix                                   |
|-----|---------------------------------|------------------------------|-----------------------------------------|----------------------------------------------|
| A01 | Broken Access Control           | `patient/records.php`        | IDOR via `?patient_id=` parameter           | Session ownership check + role gating        |
| A02 | Cryptographic Failures          | `auth/register.php`          | Passwords stored as plain MD5             | `password_hash()` with bcrypt                  |
| A03 | Injection (SQLi)                | `shared/search.php`          | Raw string concatenation in query         | PDO prepared statements                      |
| A03 | Injection (XSS)                 | `patient/chat.php`           | Raw `echo $msg['body']`                     | `htmlspecialchars()` on output                 |
| A04 | Insecure Design                 | `patient/appointments.php`   | No rate limit, no conflict checks         | Slot conflict + daily booking cap            |
| A05 | Security Misconfiguration       | `.htaccess`, Apache config   | Verbose errors, directory listing         | Error suppression, `Options -Indexes`          |
| A06 | Vulnerable Components           | `composer.json`              | PHPMailer 5.2.23 (CVE-2016-10033)         | Reference patched version in slides          |
| A07 | Auth & Session Failures         | `auth/login.php`             | No `session_regenerate_id()` on login       | Session regeneration + login timestamp       |
| A08 | Software & Data Integrity       | `shared/upload.php`          | No MIME check, `.php` files execute         | MIME whitelist + random rename               |
| A09 | Logging & Monitoring Failures   | `admin/audit_log.php`        | No audit entries written                  | All sensitive actions logged to `audit_log`    |
| A10 | SSRF                            | `shared/insurance_fetch.php` | `file_get_contents()` on any URL            | Domain whitelist + private IP blocking       |

> [!note]
> This project was built against the **OWASP Top 10 (2021)** classification. OWASP released an
> updated **2025 edition** in November 2025 that reshuffles the ranking and introduces two new
> categories. The vulnerabilities demonstrated here remain the same - only the numbering changed:
>
> | This Project (2021)                   | OWASP 2025 Equivalent                                    |
> |---------------------------------------|----------------------------------------------------------|
> | A01 - Broken Access Control           | A01:2025 - Broken Access Control (unchanged)             |
> | A02 - Cryptographic Failures          | A04:2025 - Cryptographic Failures (moved to `#4`)        |
> | A03 - Injection (SQLi, XSS)           | A05:2025 - Injection (moved to `#5`)                     |
> | A04 - Insecure Design                 | A06:2025 - Insecure Design (moved to `#6`)               |
> | A05 - Security Misconfiguration       | A02:2025 - Security Misconfiguration (moved to `#2`)     |
> | A06 - Vulnerable Components           | A03:2025 - Software Supply Chain Failures (expanded)     |
> | A07 - Auth & Session Failures         | A07:2025 - Authentication Failures (unchanged)           |
> | A08 - Software & Data Integrity       | A08:2025 - Software & Data Integrity Failures (unchanged)|
> | A09 - Logging & Monitoring Failures   | A09:2025 - Security Logging & Alerting Failures (renamed)|
> | A10 - SSRF                            | Merged into A01:2025 - Broken Access Control             |

---

<a name="project-structure"></a>
## Project Structure

```
medicare-portal/
├── config.php               ← SECURE_MODE + config toggles + DB connection + helpers
├── helpers.php              ← Layout rendering + centralized vuln hint registry
├── toggle_mode.php          ← Endpoint for UI mode/hint toggles
├── index.php                ← Redirects to login or dashboard
│
├── auth/
│   ├── login.php            ← A02 (MD5), A07 (session fixation)
│   ├── register.php         ← A02 (MD5 vs bcrypt)
│   └── logout.php
│
├── patient/
│   ├── dashboard.php        ← Appointments + messages overview
│   ├── records.php          ← A01 (IDOR)
│   ├── appointments.php     ← A04 (insecure design)
│   └── chat.php             ← A03 (stored XSS)
│
├── doctor/
│   ├── dashboard.php        ← Upcoming appointments + messages
│   ├── patients.php         ← Patient list for this doctor
│   └── chat.php             ← A03 (stored XSS, doctor side)
│
├── admin/
│   ├── dashboard.php        ← System stats + A05 (error leakage)
│   ├── users.php            ← User management (delete)
│   └── audit_log.php        ← A09 (logging failures)
│
├── shared/
│   ├── search.php           ← A03 (SQL injection) + A02 (hash leakage)
│   ├── upload.php           ← A08 (unrestricted file upload)
│   └── insurance_fetch.php  ← A10 (SSRF)
│
├── uploads/                 ← Uploaded files land here (writable)
├── assets/
│   ├── style.css            ← Full custom CSS
│   └── main.js              ← Chat scroll + nav highlighting + toggle handlers
│
├── db/
│   └── schema.sql           ← Full schema + seed data (MD5 + bcrypt accounts)
│
├── docs/
│   ├── note.md              ← Step-by-step OWASP testing guide
│   └── undo.md              ← Teardown instructions
│
├── docker/
│   ├── apache-vhost.conf    ← Apache vhost for Docker
│   └── entrypoint.sh        ← Starts MariaDB + imports schema + runs Apache
│
├── Dockerfile               ← Single-container build (Apache + PHP + MariaDB)
├── .dockerignore
├── composer.json            ← A06 (vulnerable dependency)
├── .htaccess                ← A05 (misconfiguration surface)
└── .gitignore
```

---

## ⚠️ Disclaimer

This application is **intentionally vulnerable**. Run it only in isolated environments - your local machine, a VM, or a container. Never expose it to a network you do not fully control.

---
