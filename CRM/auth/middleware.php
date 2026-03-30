<?php
// Load URL helper to build correct redirect paths in any deployment mode.
require_once __DIR__ . '/../config/app.php';

/*
 |------------------------------------------------------------
 | Reusable Authentication Middleware
 |------------------------------------------------------------
 | Use these functions on any protected page:
 | - require_login()
 | - require_admin()
 | - require_user()
 */

if (!function_exists('ensure_session_started')) {
    function ensure_session_started(): void
    {
        // Start session only once; safe for all pages that include this file.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        ensure_session_started();
        return isset($_SESSION['user_id'], $_SESSION['role']);
    }
}

if (!function_exists('require_login')) {
    function require_login(string $loginPath = '/auth/login.php'): void
    {
        // Redirect guests to login page.
        if (!is_logged_in()) {
            header('Location: ' . app_url($loginPath));
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(string $fallbackPath = '/user/dashboard.php'): void
    {
        // First ensure the user is authenticated.
        require_login();

        // Allow access only to admin role.
        if (($_SESSION['role'] ?? '') !== 'admin') {
            header('Location: ' . app_url($fallbackPath));
            exit;
        }
    }
}

if (!function_exists('require_user')) {
    function require_user(string $fallbackPath = '/admin/dashboard.php'): void
    {
        // First ensure the user is authenticated.
        require_login();

        // Allow access only to user role.
        if (($_SESSION['role'] ?? '') !== 'user') {
            header('Location: ' . app_url($fallbackPath));
            exit;
        }
    }
}
