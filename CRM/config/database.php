<?php
/*
 |------------------------------------------------------------
 | Database Connection (MySQLi)
 |------------------------------------------------------------
 | Update these values if your local MySQL credentials differ. this is my personal db configs
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

// UTF-8 support karo for safe text handling
$conn->set_charset('utf8mb4');
