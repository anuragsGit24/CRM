<?php
// Shared MySQLi connection for customers module.
if (!defined('HOST')) {
    define('HOST', 'localhost');
}
if (!defined('USER')) {
    define('USER', 'root');
}
if (!defined('PASS')) {
    define('PASS', '');
}
if (!defined('DB')) {
    define('DB', 'crm_db');
}

$conn = new mysqli(HOST, USER, PASS, DB);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
