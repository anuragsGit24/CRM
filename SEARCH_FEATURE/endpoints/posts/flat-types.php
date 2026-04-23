<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	require_once dirname(__DIR__, 2) . '/helpers/Response.php';
	Response::error('Method not allowed', 405);
}

$baseDir = dirname(__DIR__, 2);

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/helpers/Response.php';

try {
	$pdo = Database::getInstance();

	$stmt = $pdo->query('SELECT id, label, sort_order FROM flat_types WHERE 1 ORDER BY sort_order ASC');
	$rows = $stmt->fetchAll();
	$flatTypes = is_array($rows) ? $rows : [];

	Response::success([
		'flat_types' => $flatTypes,
	]);
} catch (Throwable $exception) {
	error_log('Post flat-types endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
