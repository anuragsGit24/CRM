<?php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('/auth/login.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$customerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$customerId) {
    echo 'Unauthorized access';
    exit;
}

$ownershipSql = "SELECT id FROM customers WHERE id = ? AND user_id = ? LIMIT 1";
$ownershipStmt = $conn->prepare($ownershipSql);

if (!$ownershipStmt) {
    echo 'Unable to verify ownership.';
    exit;
}

$ownershipStmt->bind_param('ii', $customerId, $userId);
$ownershipStmt->execute();
$ownedRecord = $ownershipStmt->get_result()->fetch_assoc();
$ownershipStmt->close();

if (!$ownedRecord) {
    echo 'Unauthorized access';
    exit;
}

$deleteSql = "DELETE FROM customers WHERE id = ? AND user_id = ?";
$deleteStmt = $conn->prepare($deleteSql);

if (!$deleteStmt) {
    echo 'Unable to delete customer right now.';
    exit;
}

$deleteStmt->bind_param('ii', $customerId, $userId);
$deleteStmt->execute();
$deleteStmt->close();

header('Location: ' . app_url('/customers/index.php'));
exit;
