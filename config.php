<?php
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'talusan_waste');

define('APP_NAME', 'Barangay Talusan');

// Security settings
define('LOGIN_MAX_ATTEMPTS', 3);        // consecutive failed logins before lock
define('LOGIN_LOCK_SECONDS', 300);      // lock duration (5 minutes)
define('SESSION_IDLE_TIMEOUT', 1800);   // auto sign-out after 30 min of inactivity

define('SMS_PROVIDER', 'mock');
define('SMS_FAIL_RATE', 0.05);

define('SMS_HTTP_URL', '');
define('SMS_HTTP_API_KEY', '');
define('SMS_HTTP_SENDER', 'TALUSAN');

mysqli_report(MYSQLI_REPORT_OFF);

/**
 * Database connection
 */
function db(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_errno) {
            throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}

/**
 * Run SQL with parameters
 */
function query(string $sql, array $params = [], bool $returnInsertId = false)
{
    $stmt = db()->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('SQL prepare failed: ' . db()->error);
    }

    if ($params) {
        $types = '';

        foreach ($params as $value) {
            if (is_int($value) || is_bool($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    if ($returnInsertId) {
        return db()->insert_id;
    }

    return true;
}

/**
 * Fetch a single row
 */
function fetchOne(string $sql, array $params = [])
{
    $rows = query($sql, $params);
    return $rows[0] ?? null;
}

/**
 * HTML escape
 */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message
 */
function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

/**
 * CSRF token
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF
 */
function verifyCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!isset($_POST['csrf_token'])) {
        http_response_code(419);
        die('CSRF token missing.');
    }

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $_POST['csrf_token'])) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}

/**
 * Fully end the current session (data, server file, and browser cookie)
 */
function endSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Admin check (also signs out idle sessions)
 */
function requireAdmin(): void
{
    if (empty($_SESSION['admin']) || empty($_SESSION['admin']['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    $last = (int) ($_SESSION['last_activity'] ?? time());

    if (time() - $last > SESSION_IDLE_TIMEOUT) {
        endSession();
        session_start();
        flash('You were signed out after being inactive. Please sign in again.');
        header('Location: login.php');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Login attempt limiter (stored in the database so it survives cleared cookies)
 */
function ensureLoginThrottleTable(): void
{
    query(
        "CREATE TABLE IF NOT EXISTS login_throttle (
            username VARCHAR(50) NOT NULL PRIMARY KEY,
            failed_attempts INT NOT NULL DEFAULT 0,
            locked_until INT UNSIGNED NOT NULL DEFAULT 0,
            last_ip VARCHAR(45) NULL,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function loginKey(string $username): string
{
    return mb_substr(mb_strtolower(trim($username)), 0, 50);
}

/** Seconds left on the lock, or 0 if the account is not locked. */
function loginLockRemaining(string $key): int
{
    $row = fetchOne("SELECT locked_until FROM login_throttle WHERE username = ?", [$key]);

    if (!$row) {
        return 0;
    }

    $until = (int) $row['locked_until'];
    $left = $until - time();

    if ($left > 0) {
        return $left;
    }

    if ($until > 0) {
        // Lock has expired: start fresh
        query("UPDATE login_throttle SET failed_attempts = 0, locked_until = 0 WHERE username = ?", [$key]);
    }

    return 0;
}

/** Records a failed attempt. Returns ['left' => attempts remaining, 'lock' => lock seconds (0 if none)]. */
function loginRegisterFailure(string $key): array
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $now = time();

    query(
        "INSERT INTO login_throttle (username, failed_attempts, locked_until, last_ip, updated_at)
         VALUES (?, 1, 0, ?, ?)
         ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_ip = ?, updated_at = ?",
        [$key, $ip, $now, $ip, $now]
    );

    $row = fetchOne("SELECT failed_attempts FROM login_throttle WHERE username = ?", [$key]);
    $count = (int) ($row['failed_attempts'] ?? 1);

    if ($count >= LOGIN_MAX_ATTEMPTS) {
        query("UPDATE login_throttle SET locked_until = ? WHERE username = ?", [$now + LOGIN_LOCK_SECONDS, $key]);
        return ['left' => 0, 'lock' => LOGIN_LOCK_SECONDS];
    }

    return ['left' => LOGIN_MAX_ATTEMPTS - $count, 'lock' => 0];
}

function loginClearFailures(string $key): void
{
    query("DELETE FROM login_throttle WHERE username = ?", [$key]);
}

function formatLockTime(int $seconds): string
{
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m > 0 ? "{$m} min {$s} sec" : "{$s} sec";
}

/**
 * Current admin
 */
function currentAdmin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

/**
 * Mobile normalization
 */
function normalizePhilippineMobile($value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    if (preg_match('/^0?9\d{9}$/', $digits)) {
        $digits = ltrim($digits, '0');
        return '+63' . $digits;
    }

    return '';
}

function formatDisplayMobile($value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    if (preg_match('/^63\d{10}$/', $digits)) {
        return '+63 ' . substr($digits, 2, 3) . ' ' . substr($digits, 5, 3) . ' ' . substr($digits, 8, 4);
    }

    if (preg_match('/^09\d{9}$/', $digits)) {
        return '09' . substr($digits, 2, 3) . ' ' . substr($digits, 5, 3) . ' ' . substr($digits, 8, 4);
    }

    return (string) $value;
}

function formatPhilippineMobileInput($value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    if (strlen($digits) <= 4) {
        return $digits;
    }

    if (strlen($digits) <= 7) {
        return substr($digits, 0, 4) . ' ' . substr($digits, 4);
    }

    if (strlen($digits) <= 11) {
        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 4);
    }

    return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 4);
}

function isValidPhilippineMobile($value): bool
{
    $digits = preg_replace('/\D+/', '', (string) $value);
    return preg_match('/^09\d{9}$/', $digits) === 1;
}

function residentNameExists($name, $excludeId = 0): bool
{
    $name = trim((string) $name);

    if ($name === '') {
        return false;
    }

    $sql = "SELECT resident_id FROM residents WHERE LOWER(name) = ? AND is_deleted = 0";
    $params = [strtolower($name)];

    if ($excludeId > 0) {
        $sql .= " AND resident_id != ?";
        $params[] = $excludeId;
    }

    return fetchOne($sql, $params) !== null;
}

/**
 * Schedule logs
 */
function logScheduleAction(int $scheduleId, string $action, string $details = ''): void
{
    $admin = currentAdmin();
    $adminId = $admin['admin_id'] ?? 1;

    query(
        "INSERT INTO schedule_logs (schedule_id, admin_id, action, details) VALUES (?, ?, ?, ?)",
        [$scheduleId, $adminId, $action, $details]
    );
}

function getNextSchedule(): ?array
{
    $rows = query(
        "SELECT * FROM schedules WHERE status = 'Active' AND collection_date >= CURDATE() ORDER BY collection_date ASC, start_time ASC LIMIT 1"
    );
    return $rows[0] ?? null;
}

function getUpcomingSchedules(): array
{
    return query(
        "SELECT * FROM schedules WHERE status = 'Active' AND collection_date >= CURDATE() ORDER BY collection_date ASC, start_time ASC LIMIT 10"
    );
}

function getFailedSmsCount(): int
{
    $row = fetchOne("SELECT COUNT(*) AS total FROM sms_logs WHERE status = 'failed'");
    return (int) ($row['total'] ?? 0);
}

function getResidentCount(): int
{
    $row = fetchOne("SELECT COUNT(*) AS total FROM residents WHERE is_deleted = 0");
    return (int) ($row['total'] ?? 0);
}

function getActiveScheduleCount(): int
{
    $row = fetchOne("SELECT COUNT(*) AS total FROM schedules WHERE status = 'Active' AND collection_date >= CURDATE()");
    return (int) ($row['total'] ?? 0);
}

function getAdminByUsername(string $username): ?array
{
    return fetchOne("SELECT * FROM admin WHERE username = ?", [$username]);
}

function generatePageHeader(string $title, bool $isAdmin = false, string $bodyClass = ''): void
{
    global $page;

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    $classes = trim(($isAdmin ? 'admin-page' : 'public-page') . ' ' . $bodyClass);

    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="style.css">';
    echo '</head>';
    echo '<body class="' . h($classes) . '">';

    if ($isAdmin) {
        $admin = currentAdmin();
        $initials = '';
        $nameParts = preg_split('/\s+/', trim($admin['full_name'] ?? 'Admin'));

        foreach (array_slice($nameParts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        echo '<div class="admin-shell">';
        echo '<aside class="sidebar">';
        echo '<div class="brand-wrap">';
        echo '<img src="logo.jpg" alt="Barangay Talusan logo" class="brand-logo">';
        echo '<div class="brand-text"><strong>Barangay Talusan</strong><small>Waste Collection</small></div>';
        echo '</div>';

        echo '<nav class="sidebar-nav">';
        echo '<a href="admin.php?p=dashboard" class="' . ($page === 'dashboard' ? 'active' : '') . '">Dashboard</a>';
        echo '<a href="admin.php?p=schedules" class="' . ($page === 'schedules' || $page === 'schedule_form' ? 'active' : '') . '">Manage Schedule</a>';
        echo '<a href="admin.php?p=schedule_logs" class="' . ($page === 'schedule_logs' ? 'active' : '') . '">Schedule Logs</a>';
        echo '<a href="admin.php?p=sms" class="' . ($page === 'sms' ? 'active' : '') . '">SMS Delivery Log</a>';
        echo '<a href="admin.php?p=residents" class="' . ($page === 'residents' ? 'active' : '') . '">Residents</a>';
        echo '<a href="admin.php?p=deleted_residents" class="' . ($page === 'deleted_residents' ? 'active' : '') . '">Deleted Residents</a>';
        echo '</nav>';

        echo '<form method="post" action="logout.php" class="logout-form">';
        echo '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
        echo '<button type="submit" class="logout-btn">Log out</button>';
        echo '</form>';
        echo '</aside>';

        echo '<div class="admin-main">';
        echo '<header class="admin-topbar">';
        echo '<div class="topbar-user">';
        echo '<span class="avatar">' . h($initials) . '</span>';
        echo '<div>';
        echo '<strong>' . h($admin['full_name'] ?? 'Admin') . '</strong>';
        echo '<small>' . h($admin['position'] ?? 'Barangay Secretary') . '</small>';
        echo '</div>';
        echo '</div>';
        echo '</header>';
        echo '<main class="admin-content">';
    } else {
        echo '<header class="public-header">';
        echo '<div class="public-nav">';
        echo '<a href="index.php" class="brand-inline"><img src="logo.jpg" alt="Barangay Talusan logo"><span>Barangay Talusan</span></a>';
        echo '<nav class="public-links">';
        echo '<a href="index.php#schedule">Schedule</a>';
        echo '<a href="index.php#upcoming">Upcoming</a>';
        if (!empty($_SESSION['admin']['admin_id'])) {
            echo '<a href="admin.php" class="nav-cta">Dashboard</a>';
        } else {
            echo '<a href="login.php" class="nav-cta">Admin Login</a>';
        }
        echo '</nav>';
        echo '</div>';
        echo '</header>';
        echo '<main class="public-content">';
    }

    if ($flash) {
        echo '<div class="flash-message">' . h($flash) . '</div>';
    }
}

function generatePageFooter(bool $isAdmin = false): void
{
    echo '</main>';

    if ($isAdmin) {
        echo '</div>';
        echo '</div>';
    }

    echo '<footer class="site-footer">';
    echo '<div class="footer-inner">';
    echo '<div class="brand-inline footer-brand"><img src="logo.jpg" alt="Barangay Talusan logo"><span>Barangay Talusan – Waste Collection</span></div>';
    echo '<div class="barangay-hall">Barangay Hall</div>';
    echo '</div>';
    echo '</footer>';
    echo '<script src="app.js"></script>';
    echo '</body>';
    echo '</html>';
}

require_once __DIR__ . '/sms.php';