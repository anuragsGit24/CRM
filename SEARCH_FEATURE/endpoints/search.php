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
require_once $baseDir . '/services/BuilderResolver.php';
require_once $baseDir . '/services/SearchQueryBuilder.php';
require_once $baseDir . '/services/SearchLogger.php';
require_once $baseDir . '/services/GeoSearchService.php';

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

	$directBuilderIdRaw = Sanitizer::sanitizeInt($body['builder_id'] ?? 0);
	$directBuilderId = $directBuilderIdRaw > 0 ? $directBuilderIdRaw : null;

	$directPropertyTypeRaw = Sanitizer::sanitizeInt($body['property_type'] ?? 0);
	$directPropertyType = in_array($directPropertyTypeRaw, [1, 2, 3], true) ? $directPropertyTypeRaw : null;

	$directAmenities = [];
	if (isset($body['amenities']) && is_array($body['amenities'])) {
		$uniqueAmenities = [];
		foreach ($body['amenities'] as $amenity) {
			$name = trim((string) $amenity);
			if ($name === '') {
				continue;
			}

			$uniqueAmenities[$name] = true;
		}

		$directAmenities = array_keys($uniqueAmenities);
	}

	$hasStructuredFilters = $directBuilderId !== null || $directPropertyType !== null || $directAmenities !== [];

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

	$geoLat = $sanitizeCoordinate($body['geo_lat'] ?? ($body['lat'] ?? null), -90.0, 90.0);
	$geoLng = $sanitizeCoordinate($body['geo_lng'] ?? ($body['lng'] ?? null), -180.0, 180.0);

	if ($query === '' && !$hasStructuredFilters) {
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
		'property_type' => null,
		'amenities' => [],
		'raw_builder_hint' => null,
		'builder_name' => null,
	];

	$parsed = array_merge($defaultParsed, QueryParser::parse($query));

	$builderId = null;
	$builderResolver = new BuilderResolver($pdo);

	// Prefer resolving builders from the full raw query so names like "Marathon Group"
	// are recognized even when the parser also sees location words nearby.
	$builderId = $builderResolver->resolve($query);
	if ($builderId !== null) {
		$builderName = $builderResolver->getBuilderName($builderId);
		if ($builderName !== null) {
			$parsed['builder_name'] = $builderName;
		}
	}

	// Mobile sends structured builder_id directly.
	if ($directBuilderId !== null) {
		$builderId = $directBuilderId;
		$builderName = $builderResolver->getBuilderName($builderId);
		if ($builderName !== null) {
			$parsed['builder_name'] = $builderName;
		}
	}

	if ($builderId !== null && $parsed['raw_location'] !== null && !empty($parsed['builder_name'])) {
		$rawLocationValue = trim((string) $parsed['raw_location']);
		$builderNameValue = trim((string) $parsed['builder_name']);

		if ($rawLocationValue !== '' && $builderNameValue !== '') {
			$rawTokens = preg_split('/\s+/', strtolower($rawLocationValue)) ?: [];
			$builderTokens = preg_split('/\s+/', strtolower($builderNameValue)) ?: [];
			$builderLookup = [];

			foreach ($builderTokens as $token) {
				$clean = trim((string) (preg_replace('/[^a-z0-9]/', '', $token) ?? ''));
				if (strlen($clean) >= 4) {
					$builderLookup[$clean] = true;
				}
			}

			$keptTokens = [];
			foreach ($rawTokens as $token) {
				$clean = trim((string) (preg_replace('/[^a-z0-9]/', '', $token) ?? ''));
				if ($clean !== '' && isset($builderLookup[$clean])) {
					continue;
				}

				$keptTokens[] = $token;
			}

			$cleanedRawLocation = trim((string) (preg_replace('/\s+/', ' ', implode(' ', $keptTokens)) ?? ''));
			$parsed['raw_location'] = $cleanedRawLocation !== '' ? $cleanedRawLocation : null;
		}
	}

	// Mobile sends property_type directly as int
	if ($directPropertyType !== null && $parsed['property_type'] === null) {
		$parsed['property_type'] = $directPropertyType;
	}

	// Mobile sends amenities directly as array of strings
	if ($directAmenities !== [] && (!is_array($parsed['amenities']) || $parsed['amenities'] === [])) {
		$parsed['amenities'] = $directAmenities;
	}

	$requestedForInterpretation = $parsed;

	if (($parsed['geo_intent'] ?? false) === true) {
		// Keep non-geo filters from the same query, e.g. "2 bhk near me".
		$queryWithoutGeoIntent = preg_replace('/\b(?:near\s+me|near\s*by|nearby|close\s+to\s+me|around\s+me)\b/i', ' ', $query) ?? $query;
		$queryWithoutGeoIntent = trim((string) (preg_replace('/\s+/', ' ', $queryWithoutGeoIntent) ?? $queryWithoutGeoIntent));

		if ($queryWithoutGeoIntent !== '') {
			$refinedParsed = array_merge($defaultParsed, QueryParser::parse($queryWithoutGeoIntent));
			foreach (['bhk', 'transaction_type', 'max_budget', 'min_budget', 'project_segment', 'possession', 'raw_location', 'property_type', 'amenities', 'raw_builder_hint'] as $field) {
				if ($refinedParsed[$field] !== null) {
					$parsed[$field] = $refinedParsed[$field];
				}
			}
		}

		if ($geoLat === null || $geoLng === null) {
			// Graceful fallback: if geo coordinates are missing, continue with normal text search.
			$parsed['geo_intent'] = false;
		} else {
			$additionalFilters = [];
			foreach (['bhk', 'transaction_type', 'max_budget', 'min_budget', 'project_segment', 'possession'] as $filterKey) {
				if ($parsed[$filterKey] !== null) {
					$additionalFilters[$filterKey] = $parsed[$filterKey];
				}
			}

			$geoService = new GeoSearchService($pdo);
			$geoResult = $geoService->searchNearMe($geoLat, $geoLng, $additionalFilters, $page, $limit);

			$queryInterpreted = [
				'geo' => true,
				'geo_lat' => $geoLat,
				'geo_lng' => $geoLng,
			];

			foreach (['bhk', 'transaction_type', 'max_budget', 'min_budget', 'project_segment', 'possession'] as $key) {
				if ($parsed[$key] !== null) {
					$queryInterpreted[$key] = $parsed[$key];
				}
			}

			$results = isset($geoResult['results']) && is_array($geoResult['results']) ? $geoResult['results'] : [];
			$paginationPayload = isset($geoResult['pagination']) && is_array($geoResult['pagination'])
				? $geoResult['pagination']
				: [
					'current_page' => $page,
					'per_page' => $limit,
					'total_count' => 0,
					'total_pages' => 0,
				];

			$parsedForLog = $parsed;
			$parsedForLog['search_type'] = 'geo';
			$parsedForLog['nearest_station'] = $geoResult['nearest_station'] ?? null;
			$parsedForLog['stations_searched'] = $geoResult['stations_searched'] ?? [];
			$parsedForLog['radius_used_km'] = $geoResult['radius_used_km'] ?? null;
			$parsedForLog['fallback_used'] = $geoResult['fallback_used'] ?? false;

			try {
				$logger = new SearchLogger($pdo);
				$logger->log(
					$query,
					$parsedForLog,
					(int) ($geoResult['total_count'] ?? 0),
					$platform,
					$userId,
					$geoLat,
					$geoLng
				);
			} catch (Throwable $logException) {
				error_log('Search logger wrapper failed: ' . $logException->getMessage());
			}

			Response::success(
				$results,
				$queryInterpreted,
				false,
				$paginationPayload,
				200,
				[
					'search_type' => 'geo',
					'nearest_station' => (string) ($geoResult['nearest_station'] ?? ''),
					'station_distance_km' => (float) ($geoResult['station_distance_km'] ?? 0.0),
					'stations_searched' => isset($geoResult['stations_searched']) && is_array($geoResult['stations_searched']) ? $geoResult['stations_searched'] : [],
					'radius_used_km' => (float) ($geoResult['radius_used_km'] ?? 7.0),
					'fallback_used' => (bool) ($geoResult['fallback_used'] ?? false),
				]
			);
		}
	}

	$locationIds = [];
	$originalRawLocation = null;
	$isBroadCityLocation = false;
	$broadCityIntent = null;

	if ($parsed['raw_location'] !== null) {
		$resolver = new LocationResolver($pdo);
		$originalRawLocation = (string) $parsed['raw_location'];
		$isBroadCityLocation = $resolver->isBroadCityQuery($originalRawLocation);
		$broadCityIntent = $resolver->extractBroadCityIntent($originalRawLocation);
		if ($broadCityIntent !== null) {
			$isBroadCityLocation = true;
		}
		$locationIds = $resolver->resolveIds($originalRawLocation);

		if ($locationIds === [] && $isBroadCityLocation) {
			// City-level intents like "mumbai" should not collapse to a single locality.
			$parsed['raw_location'] = null;
		}
	}

	$geoSearchLat = (($parsed['geo_intent'] ?? false) === true) ? $geoLat : null;
	$geoSearchLng = (($parsed['geo_intent'] ?? false) === true) ? $geoLng : null;

	$builder = new SearchQueryBuilder($pdo);
	$built = $builder->build($parsed, $locationIds, $page, $limit, $geoSearchLat, $geoSearchLng, $builderId);
	$inferredBuilderId = ($directBuilderId === null && $builderId !== null) ? $builderId : null;

	$runCount = static function (PDO $pdo, array $builtQuery): int {
		$countStmt = $pdo->prepare($builtQuery['count_sql']);
		$countStmt->execute($builtQuery['count_params']);
		return (int) $countStmt->fetchColumn();
	};

	$totalCount = $runCount($pdo, $built);
	$isRelaxed = false;

	if ($totalCount === 0 && $inferredBuilderId !== null) {
		$builderId = null;
		$built = $builder->build($parsed, $locationIds, $page, $limit, $geoSearchLat, $geoSearchLng, null);
		$totalCount = $runCount($pdo, $built);
		if ($totalCount > 0) {
			$isRelaxed = true;
		}
	}

	if ($totalCount === 0) {
		$parsed['max_budget'] = null;
		$built = $builder->build($parsed, $locationIds, $page, $limit, $geoSearchLat, $geoSearchLng, $builderId);
		$totalCount = $runCount($pdo, $built);
	}

	if ($totalCount === 0) {
		$parsed['bhk'] = null;
		$built = $builder->build($parsed, $locationIds, $page, $limit, $geoSearchLat, $geoSearchLng, $builderId);
		$totalCount = $runCount($pdo, $built);
		$isRelaxed = true;
	}

	if ($totalCount === 0 && (($built['used_fulltext'] ?? false) === true) && $parsed['raw_location'] !== null) {
		$parsed['raw_location'] = null;
		$built = $builder->build($parsed, $locationIds, $page, $limit, $geoSearchLat, $geoSearchLng, $builderId);
		$totalCount = $runCount($pdo, $built);
		if ($totalCount > 0) {
			$isRelaxed = true;
		}
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
		if ($requestedForInterpretation[$key] !== null) {
			$queryInterpreted[$key] = $requestedForInterpretation[$key];
		}
	}

	$builderNameInterpreted = trim((string) ($requestedForInterpretation['builder_name'] ?? ''));
	if ($builderNameInterpreted !== '') {
		$queryInterpreted['builder'] = $builderNameInterpreted;
	}

	$propertyTypeMap = [
		1 => 'Flat',
		2 => 'Office Space',
		3 => 'Shop',
	];
	$propertyTypeValue = isset($requestedForInterpretation['property_type']) ? (int) $requestedForInterpretation['property_type'] : 0;
	if (isset($propertyTypeMap[$propertyTypeValue])) {
		$queryInterpreted['property_type'] = $propertyTypeMap[$propertyTypeValue];
	}

	if (is_array($requestedForInterpretation['amenities']) && $requestedForInterpretation['amenities'] !== []) {
		$amenities = array_values(array_unique(array_filter(array_map(static fn($value) => trim((string) $value), $requestedForInterpretation['amenities']), static fn(string $value): bool => $value !== '')));
		if ($amenities !== []) {
			$queryInterpreted['amenities'] = $amenities;
		}
	}

	if (($parsed['geo_intent'] ?? false) === true) {
		$queryInterpreted['geo_intent'] = true;
		$queryInterpreted['geo_lat'] = $geoSearchLat;
		$queryInterpreted['geo_lng'] = $geoSearchLng;
	}

	if ($locationIds !== []) {
		$safeLocationIds = array_values(array_filter(array_map(static fn($id) => (int) $id, $locationIds), static fn(int $id): bool => $id > 0));
		if ($safeLocationIds !== []) {
			$placeholders = implode(',', array_fill(0, count($safeLocationIds), '?'));
			$locationStmt = $pdo->prepare("SELECT name FROM location WHERE status = 1 AND id IN ($placeholders) ORDER BY name ASC");
			$locationStmt->execute($safeLocationIds);
			$locationNames = array_values(array_filter(array_map(static fn($name) => trim((string) $name), $locationStmt->fetchAll(PDO::FETCH_COLUMN)), static fn(string $name): bool => $name !== ''));

			if ($locationNames !== []) {
				if (count($locationNames) > 1 && $originalRawLocation !== null) {
					$normalizedRawLocation = strtolower(trim((string) $originalRawLocation));
					$allNamesShareRawPrefix = $normalizedRawLocation !== '';

					foreach ($locationNames as $resolvedName) {
						if (stripos($resolvedName, $normalizedRawLocation) !== 0) {
							$allNamesShareRawPrefix = false;
							break;
						}
					}

					if ($allNamesShareRawPrefix) {
						$queryInterpreted['location'] = $originalRawLocation;
					} else {
						$queryInterpreted['location'] = implode(', ', $locationNames);
					}
				} else {
					$queryInterpreted['location'] = $locationNames[0];
				}
			}
		}
	} elseif ($isBroadCityLocation && $originalRawLocation !== null) {
		$queryInterpreted['location'] = $broadCityIntent ?? $originalRawLocation;
	} elseif ($parsed['raw_location'] !== null) {
		$queryInterpreted['location'] = (string) $parsed['raw_location'];
	} elseif (($requestedForInterpretation['raw_location'] ?? null) !== null) {
		$queryInterpreted['location'] = (string) $requestedForInterpretation['raw_location'];
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
