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

$baseDir = dirname(__DIR__, 2);

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/config/constants.php';
require_once $baseDir . '/helpers/Response.php';
require_once $baseDir . '/helpers/Sanitizer.php';
require_once $baseDir . '/services/PostQueryParser.php';
require_once $baseDir . '/services/LocationResolver.php';
require_once $baseDir . '/services/PostSearchQueryBuilder.php';
require_once $baseDir . '/services/SearchLogger.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	Response::error('Method not allowed', 405);
}

try {
	$pdo = Database::getInstance();

	$rawInput = file_get_contents('php://input');
	if (($rawInput === false || $rawInput === '') && PHP_SAPI === 'cli') {
		$rawInput = file_get_contents('php://stdin');
	}

	if ($rawInput === false) {
		$rawInput = '';
	}

	$body = json_decode($rawInput ?: '[]', true);
	if (!is_array($body)) {
		$body = [];
	}

	$sanitizeNullableInt = static function (mixed $value, int $min = 0, ?int $max = null): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		$intValue = (int) $value;
		if ($intValue < $min) {
			return null;
		}

		if ($max !== null && $intValue > $max) {
			return null;
		}

		return $intValue;
	};

	$sanitizeAllowedInt = static function (mixed $value, array $allowed) use ($sanitizeNullableInt): ?int {
		$intValue = $sanitizeNullableInt($value, 0, null);
		if ($intValue === null) {
			return null;
		}

		return in_array($intValue, $allowed, true) ? $intValue : null;
	};

	$sanitizeIntArray = static function (mixed $value, int $min, ?int $max = null) use ($sanitizeNullableInt): array {
		if (!is_array($value)) {
			return [];
		}

		$unique = [];
		foreach ($value as $item) {
			$intValue = $sanitizeNullableInt($item, $min, $max);
			if ($intValue === null) {
				continue;
			}

			$unique[$intValue] = true;
		}

		return array_keys($unique);
	};

	$sanitizeNullableString = static function (mixed $value, int $maxLength = 250): ?string {
		if ($value === null) {
			return null;
		}

		$clean = trim(strip_tags((string) $value));
		if ($clean === '') {
			return null;
		}

		if (function_exists('mb_substr')) {
			return mb_substr($clean, 0, $maxLength, 'UTF-8');
		}

		return substr($clean, 0, $maxLength);
	};

	$query = Sanitizer::sanitizeQuery((string) ($body['query'] ?? ''));
	$pagination = Sanitizer::validatePagination($body['page'] ?? 1, $body['limit'] ?? DEFAULT_LIMIT);
	$page = (int) $pagination['page'];
	$limit = (int) $pagination['limit'];
	$platform = Sanitizer::sanitizePlatform((string) ($body['platform'] ?? 'unknown'));
	$userIdRaw = Sanitizer::sanitizeInt($body['user_id'] ?? 0);
	$userId = $userIdRaw > 0 ? $userIdRaw : null;

	$defaultParsed = [
		'post_type' => null,
		'post_for' => null,
		'flat_type_ids' => [],
		'flat_property_type' => null,
		'min_budget' => null,
		'max_budget' => null,
		'min_carpet' => null,
		'max_carpet' => null,
		'raw_location' => null,
		'project_name_hint' => null,
	];

	$parsed = $query !== ''
		? array_merge($defaultParsed, PostQueryParser::parse($query))
		: $defaultParsed;

	$postType = $sanitizeAllowedInt($body['post_type'] ?? null, [1, 2]);
	$postFor = $sanitizeAllowedInt($body['post_for'] ?? null, [1, 2]);
	$flatTypeIds = $sanitizeIntArray($body['flat_type_ids'] ?? [], 1);
	$flatPropertyTypes = $sanitizeIntArray($body['flat_property_types'] ?? [], 1, 5);
	$statuses = $sanitizeIntArray($body['statuses'] ?? [], 0, 4);
	$minBudget = $sanitizeNullableInt($body['min_budget'] ?? null, 0, null);
	$maxBudget = $sanitizeNullableInt($body['max_budget'] ?? null, 0, null);
	$minCarpet = $sanitizeNullableInt($body['min_carpet'] ?? null, 0, null);
	$maxCarpet = $sanitizeNullableInt($body['max_carpet'] ?? null, 0, null);
	$projectName = $sanitizeNullableString($body['project_name'] ?? null, 250);
	$locationIdFromBody = $sanitizeNullableInt($body['location_id'] ?? null, 1, null);
	$parsedRawLocation = $sanitizeNullableString($parsed['raw_location'] ?? null, 250);
	$usedResolvedQueryLocation = false;

	$locationId = null;
	if ($locationIdFromBody !== null) {
		$locationId = $locationIdFromBody;
	} elseif ($parsedRawLocation !== null) {
		$resolver = new LocationResolver($pdo);
		$locationId = $resolver->resolve($parsedRawLocation);
		$usedResolvedQueryLocation = $locationId !== null;
	}

	$directFilters = [];
	if (array_key_exists('post_type', $body)) {
		$directFilters['post_type'] = $postType;
	}
	if (array_key_exists('post_for', $body)) {
		$directFilters['post_for'] = $postFor;
	}
	if (array_key_exists('flat_type_ids', $body)) {
		$directFilters['flat_type_ids'] = $flatTypeIds;
	}
	if (array_key_exists('flat_property_types', $body)) {
		$directFilters['flat_property_types'] = $flatPropertyTypes;
	}
	if (array_key_exists('min_budget', $body)) {
		$directFilters['min_budget'] = $minBudget;
	}
	if (array_key_exists('max_budget', $body)) {
		$directFilters['max_budget'] = $maxBudget;
	}
	if (array_key_exists('min_carpet', $body)) {
		$directFilters['min_carpet'] = $minCarpet;
	}
	if (array_key_exists('max_carpet', $body)) {
		$directFilters['max_carpet'] = $maxCarpet;
	}
	if (array_key_exists('statuses', $body)) {
		$directFilters['statuses'] = $statuses;
	}
	if (array_key_exists('project_name', $body)) {
		$directFilters['project_name'] = $projectName;
	}
	if (array_key_exists('location_id', $body) && $locationIdFromBody !== null) {
		$directFilters['location_id'] = $locationIdFromBody;
	}

	$builder = new PostSearchQueryBuilder($pdo);

	$activeParsed = $parsed;
	$activeFilters = $directFilters;

	$runCount = static function (PDO $pdo, array $built): int {
		$stmt = $pdo->prepare((string) $built['count_sql']);
		foreach (($built['count_params'] ?? []) as $param => $value) {
			$type = PDO::PARAM_STR;
			if (is_int($value)) {
				$type = PDO::PARAM_INT;
			} elseif ($value === null) {
				$type = PDO::PARAM_NULL;
			}

			$stmt->bindValue((string) $param, $value, $type);
		}
		$stmt->execute();
		return (int) $stmt->fetchColumn();
	};

	$runSelect = static function (PDO $pdo, array $built): array {
		$stmt = $pdo->prepare((string) $built['sql']);
		foreach (($built['params'] ?? []) as $param => $value) {
			$type = PDO::PARAM_STR;
			if (is_int($value)) {
				$type = PDO::PARAM_INT;
			} elseif ($value === null) {
				$type = PDO::PARAM_NULL;
			}

			$stmt->bindValue((string) $param, $value, $type);
		}
		$stmt->execute();
		$rows = $stmt->fetchAll();
		return is_array($rows) ? $rows : [];
	};

	$hasBudgetFilter = static function (array $parsedState, array $filters): bool {
		if (array_key_exists('min_budget', $filters) && $filters['min_budget'] !== null) {
			return true;
		}
		if (array_key_exists('max_budget', $filters) && $filters['max_budget'] !== null) {
			return true;
		}

		if (!array_key_exists('min_budget', $filters) && ($parsedState['min_budget'] ?? null) !== null) {
			return true;
		}
		if (!array_key_exists('max_budget', $filters) && ($parsedState['max_budget'] ?? null) !== null) {
			return true;
		}

		return false;
	};

	$clearBudgetFilter = static function (array &$parsedState, array &$filters): void {
		if (array_key_exists('min_budget', $filters)) {
			$filters['min_budget'] = null;
		} else {
			$parsedState['min_budget'] = null;
		}

		if (array_key_exists('max_budget', $filters)) {
			$filters['max_budget'] = null;
		} else {
			$parsedState['max_budget'] = null;
		}
	};

	$hasFlatTypeFilter = static function (array $parsedState, array $filters): bool {
		if (array_key_exists('flat_type_ids', $filters)) {
			return is_array($filters['flat_type_ids']) && $filters['flat_type_ids'] !== [];
		}

		return is_array($parsedState['flat_type_ids'] ?? null) && ($parsedState['flat_type_ids'] ?? []) !== [];
	};

	$clearFlatTypeFilter = static function (array &$parsedState, array &$filters): void {
		if (array_key_exists('flat_type_ids', $filters)) {
			$filters['flat_type_ids'] = [];
		} else {
			$parsedState['flat_type_ids'] = [];
		}
	};

	$built = $builder->build($activeParsed, $locationId, $page, $limit, $activeFilters);
	$totalCount = $runCount($pdo, $built);
	$isRelaxed = false;

	if ($totalCount === 0 && $hasBudgetFilter($activeParsed, $activeFilters)) {
		$clearBudgetFilter($activeParsed, $activeFilters);
		$built = $builder->build($activeParsed, $locationId, $page, $limit, $activeFilters);
		$totalCount = $runCount($pdo, $built);
		$isRelaxed = true;
	}

	if ($totalCount === 0 && $hasFlatTypeFilter($activeParsed, $activeFilters)) {
		$clearFlatTypeFilter($activeParsed, $activeFilters);
		$built = $builder->build($activeParsed, $locationId, $page, $limit, $activeFilters);
		$totalCount = $runCount($pdo, $built);
		$isRelaxed = true;
	}

	if ($totalCount === 0 && $usedResolvedQueryLocation && $parsedRawLocation !== null) {
		$locationId = null;
		$activeParsed['raw_location'] = $parsedRawLocation;
		$built = $builder->build($activeParsed, $locationId, $page, $limit, $activeFilters);
		$totalCount = $runCount($pdo, $built);
		$isRelaxed = true;
	}

	$rows = $runSelect($pdo, $built);

	$getEffectiveInt = static function (string $key) use (&$activeParsed, &$activeFilters, $sanitizeNullableInt): ?int {
		if (array_key_exists($key, $activeFilters)) {
			return $sanitizeNullableInt($activeFilters[$key] ?? null, 0, null);
		}

		return $sanitizeNullableInt($activeParsed[$key] ?? null, 0, null);
	};

	$getEffectiveIntArray = static function (string $key) use (&$activeParsed, &$activeFilters): array {
		if (array_key_exists($key, $activeFilters)) {
			return is_array($activeFilters[$key]) ? $activeFilters[$key] : [];
		}

		return is_array($activeParsed[$key] ?? null) ? $activeParsed[$key] : [];
	};

	$effectivePostType = $getEffectiveInt('post_type');
	$effectivePostFor = $getEffectiveInt('post_for');
	$effectiveFlatTypeIds = $getEffectiveIntArray('flat_type_ids');
	$effectiveMaxBudget = $getEffectiveInt('max_budget');
	$effectiveMinCarpet = $getEffectiveInt('min_carpet');
	$effectiveMaxCarpet = $getEffectiveInt('max_carpet');

	$locationName = null;
	if ($locationId !== null) {
		$locationStmt = $pdo->prepare('SELECT name FROM location WHERE status = 1 AND id = :location_id LIMIT 1');
		$locationStmt->execute([':location_id' => $locationId]);
		$resolvedName = $locationStmt->fetchColumn();
		if ($resolvedName !== false) {
			$locationName = trim((string) $resolvedName);
		}
	}
	if ($locationName === null && ($activeParsed['raw_location'] ?? null) !== null) {
		$locationName = (string) $activeParsed['raw_location'];
	}

	$flatTypeLabelsById = [];
	if ($effectiveFlatTypeIds !== []) {
		$ids = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $effectiveFlatTypeIds), static fn (int $id): bool => $id > 0));
		if ($ids !== []) {
			$placeholders = [];
			$params = [];
			foreach ($ids as $index => $id) {
				$key = ':id_' . ($index + 1);
				$placeholders[] = $key;
				$params[$key] = $id;
			}

			$flatTypeStmt = $pdo->prepare('SELECT id, label FROM flat_types WHERE id IN (' . implode(', ', $placeholders) . ')');
			foreach ($params as $param => $value) {
				$flatTypeStmt->bindValue($param, $value, PDO::PARAM_INT);
			}
			$flatTypeStmt->execute();

			foreach ($flatTypeStmt->fetchAll() as $flatTypeRow) {
				$id = isset($flatTypeRow['id']) ? (int) $flatTypeRow['id'] : 0;
				$label = isset($flatTypeRow['label']) ? trim((string) $flatTypeRow['label']) : '';
				if ($id > 0 && $label !== '') {
					$flatTypeLabelsById[$id] = $label;
				}
			}
		}
	}

	$postTypeLabels = [1 => 'Buyer', 2 => 'Seller'];
	$postForLabels = [1 => 'Sale', 2 => 'Rental'];
	$postStatusLabels = [
		0 => 'In Progress',
		1 => 'Verified',
		2 => 'Deal Lapsed',
		3 => 'Blocked',
		4 => 'Deal Confirmed',
	];
	$propertyTypeLabels = [
		1 => 'Flat',
		2 => 'Office Space',
		3 => 'Shop',
		4 => 'Bungalow',
		5 => 'Plot',
	];

	$formatBudget = static function (?int $amount): ?string {
		if ($amount === null || $amount <= 0) {
			return null;
		}

		if ($amount >= 10000000) {
			return '₹' . number_format($amount / 10000000, 2, '.', '') . ' Cr';
		}

		if ($amount >= 100000) {
			return '₹' . number_format($amount / 100000, 2, '.', '') . ' Lac';
		}

		return '₹' . (string) $amount;
	};

	$queryInterpreted = [
		'post_type' => isset($postTypeLabels[$effectivePostType ?? 0]) ? $postTypeLabels[$effectivePostType] : null,
		'post_for' => isset($postForLabels[$effectivePostFor ?? 0]) ? $postForLabels[$effectivePostFor] : null,
		'flat_types' => array_values(array_filter(array_map(
			static fn ($id) => $flatTypeLabelsById[(int) $id] ?? null,
			$effectiveFlatTypeIds
		))),
		'location' => $locationName,
		'max_budget' => $effectiveMaxBudget,
		'carpet_range' => null,
	];

	if ($effectiveMinCarpet !== null && $effectiveMaxCarpet !== null) {
		$queryInterpreted['carpet_range'] = $effectiveMinCarpet . '-' . $effectiveMaxCarpet . ' sqft';
	} elseif ($effectiveMinCarpet !== null) {
		$queryInterpreted['carpet_range'] = '>=' . $effectiveMinCarpet . ' sqft';
	} elseif ($effectiveMaxCarpet !== null) {
		$queryInterpreted['carpet_range'] = '<=' . $effectiveMaxCarpet . ' sqft';
	}

	$results = [];
	foreach ($rows as $row) {
		$postTypeValue = isset($row['post_type']) ? (int) $row['post_type'] : 0;
		$postForValue = isset($row['post_for']) ? (int) $row['post_for'] : 0;
		$flatPropertyTypeValue = isset($row['flat_property_type']) ? (int) $row['flat_property_type'] : 0;
		$postStatusValue = isset($row['post_status']) ? (int) $row['post_status'] : 0;

		$budgetRaw = isset($row['budget']) && is_numeric($row['budget']) ? (int) $row['budget'] : null;
		$monthlyRentRaw = isset($row['monthly_rent']) && is_numeric($row['monthly_rent']) ? (int) $row['monthly_rent'] : null;
		$budgetForLabel = $postForValue === 2 && $monthlyRentRaw !== null && $monthlyRentRaw > 0
			? $monthlyRentRaw
			: $budgetRaw;

		$results[] = [
			'post_id' => isset($row['post_id']) ? (int) $row['post_id'] : 0,
			'title' => isset($row['title']) ? (string) $row['title'] : '',
			'description' => isset($row['description']) ? (string) $row['description'] : '',
			'post_type' => $postTypeValue,
			'post_type_label' => $postTypeLabels[$postTypeValue] ?? null,
			'post_for' => $postForValue,
			'post_for_label' => $postForLabels[$postForValue] ?? null,
			'flat_type_label' => isset($row['flat_type_label']) ? (string) $row['flat_type_label'] : null,
			'flat_property_type' => $flatPropertyTypeValue,
			'flat_property_type_label' => $propertyTypeLabels[$flatPropertyTypeValue] ?? null,
			'location_name' => isset($row['location_name']) ? (string) $row['location_name'] : null,
			'budget' => $budgetRaw,
			'budget_formatted' => $formatBudget($budgetForLabel),
			'carpet' => isset($row['carpet']) ? (int) $row['carpet'] : null,
			'post_status' => $postStatusValue,
			'post_status_label' => $postStatusLabels[$postStatusValue] ?? null,
			'project_name' => isset($row['project_name']) ? (string) $row['project_name'] : '',
			'landmark' => $row['landmark'] ?? null,
			'created_on' => isset($row['created_on']) ? (string) $row['created_on'] : null,
			'posting_type' => isset($row['posting_type']) ? (int) $row['posting_type'] : null,
			'deposit' => isset($row['deposit']) && is_numeric($row['deposit']) ? (int) $row['deposit'] : null,
			'monthly_rent' => $monthlyRentRaw,
			'rank' => isset($row['rank']) && is_numeric($row['rank']) ? (int) $row['rank'] : null,
			'post_lat' => isset($row['post_lat']) ? (string) $row['post_lat'] : null,
			'post_lng' => isset($row['post_lng']) ? (string) $row['post_lng'] : null,
		];
	}

	$parsedForLog = $activeParsed;
	$parsedForLog['search_type'] = 'post';
	$parsedForLog['direct_filters'] = $activeFilters;

	try {
		$logger = new SearchLogger($pdo);
		$logger->log($query, $parsedForLog, $totalCount, $platform, $userId);
	} catch (Throwable $logException) {
		error_log('Post search logger wrapper failed: ' . $logException->getMessage());
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
		],
		200,
		[
			'search_domain' => 'posts',
		]
	);
} catch (Throwable $exception) {
	error_log('Post search endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
