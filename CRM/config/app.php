<?php
/*
 |------------------------------------------------------------
 | App URL Helper
 |------------------------------------------------------------
 | This helper builds correct URLs for both cases:
 | 1) Project inside XAMPP subfolder like /Internship/CRM
 | 2) Project directly in web root
 */

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        if (strpos($scriptName, '/Internship/CRM/') !== false) {
            return '/Internship/CRM';
        }

        return '';
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = '/'): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        return app_base_path() . $normalizedPath;
    }
}
