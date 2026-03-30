<?php
// Start session to access current login data.
session_start();

// Include URL helper for reliable redirects.
require_once __DIR__ . '/../config/app.php';

// Remove all session variables.
$_SESSION = [];

// Destroy the session fully.
session_destroy();

// Send user back to login page.
header('Location: ' . app_url('/auth/login.php'));
exit;
?>
