<?php
/*
 |------------------------------------------------------------
 | Database Connection (MySQLi)
 |------------------------------------------------------------
 | Update these values if your local MySQL credentials differ.
 */
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'crm_db';

// Create a secure MySQLi connection.
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Stop execution if connection fails, so we do not continue with invalid DB state.
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Force UTF-8 support for safe and correct text handling.
$conn->set_charset('utf8mb4');
