<?php
require __DIR__ . '/config.php';

// Logout must be a POST with a valid CSRF token, so a third-party page
// cannot sign the admin out by embedding <img src="logout.php">.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (empty($_SESSION['admin']) ? 'index.php' : 'admin.php'));
    exit;
}

verifyCsrf();

// Destroy session data, the server-side session, and the browser cookie
endSession();

// Fresh session (new ID) just to carry the confirmation message
session_start();
session_regenerate_id(true);
flash('You have been signed out.');

header('Location: login.php');
exit;