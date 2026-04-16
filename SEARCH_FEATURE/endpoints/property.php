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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	Response::error('Method not allowed', 405);
}

try {
	$projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
	if ($projectId === false || $projectId === null || $projectId <= 0) {
		Response::error('Invalid property id', 400);
	}

	$pdo = Database::getInstance();

	$projectSql = "SELECT
		p.id AS project_id,
		p.name AS project_name,
		p.project_status,
		p.possession_date,
		p.header_image,
		p.rera_no,
		p.project_segment,
		p.rank,
		p.landmark,
		p.flat_configuration,
		b.name AS builder_name,
		l.name AS location_name,
		l.latitude,
		l.longitude,
		f.type AS flat_type,
		f.base_price,
		f.total_charge,
		f.carpet_area,
		f.builtup_area,
		f.bathroom_count,
		f.transaction_type
	FROM projects p
	JOIN builder b ON b.id = p.builder_id
	JOIN location l ON l.id = p.location_id
	LEFT JOIN flat f ON f.projects_id = p.id AND f.status = 1
	WHERE p.id = ? AND p.status = 1
	ORDER BY f.base_price ASC
	LIMIT 1";

	$projectStmt = $pdo->prepare($projectSql);
	$projectStmt->execute([$projectId]);
	$project = $projectStmt->fetch();

	if ($project === false) {
		Response::error('Property not found', 404);
	}

	$flatsSql = "SELECT
		id,
		type,
		base_price,
		total_charge,
		carpet_area,
		builtup_area,
		bathroom_count,
		transaction_type
	FROM flat
	WHERE projects_id = ? AND status = 1
	ORDER BY base_price ASC";

	$flatsStmt = $pdo->prepare($flatsSql);
	$flatsStmt->execute([$projectId]);
	$flats = $flatsStmt->fetchAll();

	Response::success([
		'project' => $project,
		'flats' => $flats,
	]);
} catch (Throwable $exception) {
	error_log('Property details endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
