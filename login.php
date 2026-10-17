<?php
require __DIR__ . '/config.php';

// Already signed in? Go straight to the dashboard.
if (!empty($_SESSION['admin']['admin_id'])) {
    header('Location: admin.php');
    exit;
}

ensureLoginThrottleTable();

$error = '';
$lockSeconds = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $key = loginKey($username);
        $lockSeconds = loginLockRemaining($key);

        if ($lockSeconds > 0) {
            // Locked: do not even check the password
            $error = 'Too many failed attempts. This account is locked temporarily.';
        } else {
            $admin = fetchOne("SELECT * FROM admin WHERE username = ?", [$username]);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                loginClearFailures($key);
                session_regenerate_id(true);

                $_SESSION['admin'] = [
                    'admin_id' => (int) $admin['admin_id'],
                    'full_name' => $admin['full_name'],
                    'position' => $admin['position'],
                    'username' => $admin['username'],
                ];
                $_SESSION['last_activity'] = time();

                header('Location: admin.php');
                exit;
            }

            $result = loginRegisterFailure($key);

            if ($result['lock'] > 0) {
                $lockSeconds = $result['lock'];
                $error = 'Too many failed attempts. This account is locked temporarily.';
            } else {
                $error = 'Incorrect username or password. ' . $result['left'] . ' attempt' . ($result['left'] === 1 ? '' : 's') . ' remaining before a temporary lock.';
            }
        }
    }
}

generatePageHeader('Admin Login', false);
?>

<section class="auth-box">
    <div class="auth-inner">
        <div class="auth-image">
            <img src="logo.jpg" alt="Barangay Talusan church">
            <div class="auth-image-text">
                <span class="tag">Barangay staff only</span>
                <strong>Barangay Talusan</strong>
                <small>Waste Collection &amp; SMS Notification</small>
            </div>
        </div>

        <div class="auth-form-wrap">
            <p class="eyebrow">Barangay staff only</p>
            <h1>Admin login</h1>
            <p class="muted">Sign in to manage collection schedules and SMS notifications.</p>

            <?php if ($error !== ''): ?>
                <div class="alert-box" role="alert">
                    <?= h($error) ?>
                    <?php if ($lockSeconds > 0): ?>
                        <br>Try again in <strong id="lock-timer" data-lock-seconds="<?= (int) $lockSeconds ?>"><?= h(formatLockTime($lockSeconds)) ?></strong>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" id="login-form" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

                <fieldset class="lock-fieldset" <?= $lockSeconds > 0 ? 'disabled' : '' ?>>
                    <label>
                        Username
                        <input type="text" name="username" autocomplete="username" required value="<?= h($_POST['username'] ?? '') ?>">
                    </label>

                    <label>
                        Password
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>

                    <div class="auth-actions">
                        <button type="submit" class="primary-btn full-width"><?= $lockSeconds > 0 ? 'Account locked' : 'Sign in' ?></button>
                    </div>
                </fieldset>

                <a href="index.php" class="secondary-btn full-width">Back to public page</a>
            </form>
        </div>
    </div>
</section>

<?php generatePageFooter(false); ?>