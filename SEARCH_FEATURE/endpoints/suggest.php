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

$baseDir = dirname(__DIR__);

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/helpers/Response.php';
require_once $baseDir . '/helpers/Sanitizer.php';
require_once $baseDir . '/services/LocationResolver.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	Response::error('Method not allowed', 405);
}

try {
	$q = Sanitizer::sanitizeQuery((string) ($_GET['q'] ?? ''));

	$queryLength = function_exists('mb_strlen')
		? mb_strlen($q, 'UTF-8')
		: strlen($q);

	if ($queryLength < 3) {
		Response::success(
			['suggestions' => []],
			[],
			false,
			[]
		);
	}

	$pdo = Database::getInstance();
	$resolver = new LocationResolver($pdo);
	$results = $resolver->getSuggestions($q);

	Response::success(
		['suggestions' => $results],
		[],
		false,
		[]
	);
} catch (Throwable $exception) {
	error_log('Suggest endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
