<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
	http_response_code(204);
	exit;
}

$baseDir = dirname(__DIR__);

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/config/constants.php';
require_once $baseDir . '/helpers/Response.php';
require_once $baseDir . '/helpers/Sanitizer.php';
require_once $baseDir . '/services/QueryParser.php';
require_once $baseDir . '/services/LocationResolver.php';
require_once $baseDir . '/services/SearchQueryBuilder.php';
require_once $baseDir . '/services/SearchLogger.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	Response::error('Method not allowed', 405);
}

try {
	$pdo = Database::getInstance();

	$rawInput = file_get_contents('php://input');
	$body = json_decode($rawInput ?: '[]', true);
	if (!is_array($body)) {
		$body = [];
	}

	$query = Sanitizer::sanitizeQuery((string) ($body['query'] ?? ''));
	$pagination = Sanitizer::validatePagination($body['page'] ?? 1, $body['limit'] ?? DEFAULT_LIMIT);
	$page = (int) $pagination['page'];
	$limit = (int) $pagination['limit'];
	$platform = Sanitizer::sanitizePlatform((string) ($body['platform'] ?? 'unknown'));
	$userIdSanitized = Sanitizer::sanitizeInt($body['user_id'] ?? 0);
	$userId = $userIdSanitized > 0 ? $userIdSanitized : null;

	$sanitizeCoordinate = static function (mixed $value, float $min, float $max): ?float {
		if ($value === null || $value === '') {
			return null;
		}

		$floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);
		if ($floatValue === false) {
			return null;
		}

		$coordinate = (float) $floatValue;
		if ($coordinate < $min || $coordinate > $max) {
			return null;
		}

		return $coordinate;
	};

	$geoLat = $sanitizeCoordinate($body['geo_lat'] ?? null, -90.0, 90.0);
	$geoLng = $sanitizeCoordinate($body['geo_lng'] ?? null, -180.0, 180.0);

	if ($query === '') {
		$featuredLimit = 20;

		$featuredSql = "SELECT
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
		JOIN location l ON p.location_id = l.id
		JOIN flat f ON f.projects_id = p.id
		JOIN builder b ON p.builder_id = b.id
		WHERE p.status = 1 AND f.status = 1
		ORDER BY p.rank ASC
		LIMIT ?";

		$featuredStmt = $pdo->prepare($featuredSql);
		$featuredStmt->execute([$featuredLimit]);
		$featuredResults = $featuredStmt->fetchAll();

		$countSql = 'SELECT COUNT(DISTINCT p.id) AS total FROM projects p JOIN flat f ON f.projects_id = p.id WHERE p.status = 1 AND f.status = 1';
		$totalCount = (int) $pdo->query($countSql)->fetchColumn();

		Response::success(
			$featuredResults,
			[],
			false,
			[
				'current_page' => 1,
				'per_page' => $featuredLimit,
				'total_count' => $totalCount,
				'total_pages' => (int) ceil($totalCount / $featuredLimit),
			]
		);
	}

	$defaultParsed = [
		'bhk' => null,
		'transaction_type' => null,
		'max_budget' => null,
		'min_budget' => null,
		'project_segment' => null,
		'possession' => null,
		'raw_location' => null,
		'geo_intent' => false,
	];

	$parsed = array_merge($defaultParsed, QueryParser::parse($query));
	if (($parsed['geo_intent'] ?? false) === true) {
		// Keep non-geo filters from the same query, e.g. "2 bhk near me".
		$queryWithoutGeoIntent = preg_replace('/\b(?:near\s+me|nearby|close\s+to\s+me|around\s+me)\b/i', ' ', $query) ?? $query;
		$queryWithoutGeoIntent = trim((string) (preg_replace('/\s+/', ' ', $queryWithoutGeoIntent) ?? $queryWithoutGeoIntent));

		if ($queryWithoutGeoIntent !== '') {
			$refinedParsed = array_merge($defaultParsed, QueryParser::parse($queryWithoutGeoIntent));
			foreach (['bhk', 'transaction_type', 'max_budget', 'min_budget', 'project_segment', 'possession', 'raw_location'] as $field) {
				if ($refinedParsed[$field] !== null) {
					$parsed[$field] = $refinedParsed[$field];
				}
			}
		}

		if ($geoLat === null || $geoLng === null) {
			Response::error('Please enable location and send geo_lat and geo_lng for near me search', 400);
		}
	}

	$locationId = null;
	if ($parsed['raw_location'] !== null) {
		$resolver = new LocationResolver($pdo);
		$locationId = $resolver->resolve((string) $parsed['raw_location']);
	}

	$geoSearchLat = (($parsed['geo_intent'] ?? false) === true) ? $geoLat : null;
	$geoSearchLng = (($parsed['geo_intent'] ?? false) === true) ? $geoLng : null;

	$builder = new SearchQueryBuilder($pdo);
	$built = $builder->build($parsed, $locationId, $page, $limit, $geoSearchLat, $geoSearchLng);

	$runCount = static function (PDO $pdo, array $builtQuery): int {
		$countStmt = $pdo->prepare($builtQuery['count_sql']);
		$countStmt->execute($builtQuery['count_params']);
		return (int) $countStmt->fetchColumn();
	};

	$totalCount = $runCount($pdo, $built);
	$isRelaxed = false;

	if ($totalCount === 0) {
		$parsed['max_budget'] = null;
		$built = $builder->build($parsed, $locationId, $page, $limit, $geoSearchLat, $geoSearchLng);
		$totalCount = $runCount($pdo, $built);
	}

	if ($totalCount === 0) {
		$parsed['bhk'] = null;
		$built = $builder->build($parsed, $locationId, $page, $limit, $geoSearchLat, $geoSearchLng);
		$totalCount = $runCount($pdo, $built);
		$isRelaxed = true;
	}

	$stmt = $pdo->prepare($built['sql']);
	$stmt->execute($built['params']);
	$results = $stmt->fetchAll();

	try {
		$logger = new SearchLogger($pdo);
		$logger->log($query, $parsed, $totalCount, $platform, $userId, $geoSearchLat, $geoSearchLng);
	} catch (Throwable $logException) {
		error_log('Search logger wrapper failed: ' . $logException->getMessage());
	}

	$queryInterpreted = [];
	foreach (['bhk', 'transaction_type', 'max_budget', 'min_budget', 'project_segment', 'possession'] as $key) {
		if ($parsed[$key] !== null) {
			$queryInterpreted[$key] = $parsed[$key];
		}
	}

	if (($parsed['geo_intent'] ?? false) === true) {
		$queryInterpreted['geo_intent'] = true;
		$queryInterpreted['geo_lat'] = $geoSearchLat;
		$queryInterpreted['geo_lng'] = $geoSearchLng;
	}

	if ($locationId !== null) {
		$locationStmt = $pdo->prepare('SELECT name FROM location WHERE id = ? AND status = 1 LIMIT 1');
		$locationStmt->execute([$locationId]);
		$locationName = $locationStmt->fetchColumn();

		if ($locationName !== false) {
			$queryInterpreted['location'] = (string) $locationName;
		}
	} elseif ($parsed['raw_location'] !== null) {
		$queryInterpreted['location'] = (string) $parsed['raw_location'];
	}

	Response::success(
		$results,
		$queryInterpreted,
		$isRelaxed,
		[
			'current_page' => $page,
			'per_page' => $limit,
			'total_count' => $totalCount,
			'total_pages' => $totalCount > 0 ? (int) ceil($totalCount / $limit) : 0,
		]
	);
} catch (Throwable $exception) {
	error_log('Search endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
