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
	header('Allow: GET, OPTIONS');
	$response = [
		'status' => 'error',
		'message' => 'Method not allowed',
		'code' => 405,
	];
	http_response_code(405);
	echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

$baseDir = dirname(__DIR__);

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/helpers/Response.php';
require_once $baseDir . '/services/BuilderResolver.php';

try {
	$pdo = Database::getInstance();
	$resolver = new BuilderResolver($pdo);
	$results = $resolver->getAllBuilders();

	Response::success([
		'builders' => $results,
	]);
} catch (Throwable $exception) {
	error_log('Builders endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
