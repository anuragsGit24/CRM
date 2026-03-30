<?php
// Start the session on every request so we can read login data if it exists.
session_start();

// Include URL helper for correct redirect paths.
require_once __DIR__ . '/config/app.php';

// If user is already logged in, send them directly to the correct dashboard.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
	if ($_SESSION['role'] === 'admin') {
		header('Location: ' . app_url('/admin/dashboard.php'));
		exit;
	}

	header('Location: ' . app_url('/user/dashboard.php'));
	exit;
}

// If not logged in, open the login page.
header('Location: ' . app_url('/auth/login.php'));
exit;
?>