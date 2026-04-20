<?php
declare(strict_types=1);

final class GeoSearchService
{
	private PDO $pdo;

	/**
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $stationCache = null;

	/**
	 * @var array<string, array<int>>|null
	 */
	private static ?array $stationNameIndex = null;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function findNearestStation(float $userLat, float $userLng): array
	{
		$this->ensureStationsLoaded();

		if (self::$stationCache === null || self::$stationCache === []) {
			throw new RuntimeException('No stations available for geo search');
		}

		$nearestStation = null;
		$nearestDistance = INF;

		foreach (self::$stationCache as $station) {
			$stationLat = (float) ($station['latitude'] ?? 0.0);
			$stationLng = (float) ($station['longitude'] ?? 0.0);
			$distance = self::haversineDistanceKm($userLat, $userLng, $stationLat, $stationLng);

			if ($distance < $nearestDistance) {
				$nearestDistance = $distance;
				$nearestStation = $station;
			}
		}

		if ($nearestStation === null) {
			throw new RuntimeException('Unable to determine nearest station');
		}

		return [
			'station' => $nearestStation,
			'distance_km' => $nearestDistance,
		];
	}

	public function getNeighboringStations(int $stationId, int $depth = 1): array
	{
		$baseStationId = $stationId > 0 ? $stationId : 0;
		if ($baseStationId <= 0) {
			return [];
		}

		$this->ensureStationsLoaded();

		$depth = $depth < 1 ? 1 : $depth;
		$collected = [];
		$visited = [];

		$seedStationIds = $this->expandStationIdsBySameName($baseStationId);
		if ($seedStationIds === []) {
			$seedStationIds = [$baseStationId];
		}

		foreach ($seedStationIds as $seedId) {
			$collected[$seedId] = true;
			$visited[$seedId] = true;
		}

		$frontier = $seedStationIds;
		$currentDepth = 0;

		while ($frontier !== [] && $currentDepth < $depth) {
			$rows = $this->fetchStationGraphRowsByStationIds($frontier);
			$nextFrontier = [];

			foreach ($rows as $row) {
				$currentStationId = (int) ($row['station_id'] ?? 0);
				if ($currentStationId > 0) {
					$collected[$currentStationId] = true;
				}

				$neighbors = [
					$row['prev_station_id'] ?? null,
					$row['next_station_id'] ?? null,
				];

				foreach ($neighbors as $neighbor) {
					$neighborId = is_numeric($neighbor) ? (int) $neighbor : 0;
					if ($neighborId <= 0) {
						continue;
					}

					$collected[$neighborId] = true;
					if (!isset($visited[$neighborId])) {
						$nextFrontier[] = $neighborId;
						$visited[$neighborId] = true;
					}
				}
			}

			$frontier = array_values(array_unique($nextFrontier));
			$currentDepth++;
		}

		return array_map(
			static fn (string $id): int => (int) $id,
			array_keys($collected)
		);
	}

	public function getLocationIdsByStations(array $stationIds): array
	{
		$normalizedStationIds = $this->normalizeIntArray($stationIds);
		if ($normalizedStationIds === []) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($normalizedStationIds), '?'));
		$sql = 'SELECT id FROM location WHERE nearest_station_id IN (' . $placeholders . ')';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($normalizedStationIds);
		$rows = $stmt->fetchAll();

		$locationIds = [];
		foreach ($rows as $row) {
			$locationId = isset($row['id']) ? (int) $row['id'] : 0;
			if ($locationId > 0) {
				$locationIds[] = $locationId;
			}
		}

		return array_values(array_unique($locationIds));
	}

	public function buildGeoQuery(
		float $userLat,
		float $userLng,
		array $locationIds,
		float $radiusKm = 7.0,
		int $page = 1,
		int $limit = 20
	): array {
		return $this->buildGeoQueryWithFilters(
			$userLat,
			$userLng,
			$locationIds,
			$radiusKm,
			$page,
			$limit,
			[]
		);
	}

	public function searchNearMe(
		float $userLat,
		float $userLng,
		array $additionalFilters = [],
		int $page = 1,
		int $limit = 20
	): array {
		$page = $page > 0 ? $page : 1;
		$limit = $limit > 0 ? $limit : 20;

		$nearestStation = $this->findNearestStation($userLat, $userLng);
		$nearestStationId = (int) ($nearestStation['station']['id'] ?? 0);

		$stationIds = $this->getNeighboringStations($nearestStationId, 1);
		$locationIds = $this->getLocationIdsByStations($stationIds);

		$radiusUsedKm = 7.0;
		$fallbackUsed = false;

		$built = $this->buildGeoQueryWithFilters(
			$userLat,
			$userLng,
			$locationIds,
			$radiusUsedKm,
			$page,
			$limit,
			$additionalFilters
		);
		$totalCount = $this->executeCount($built['count_sql'], $built['count_params']);

		if ($totalCount === 0) {
			$stationIds = $this->getNeighboringStations($nearestStationId, 2);
			$locationIds = $this->getLocationIdsByStations($stationIds);

			$built = $this->buildGeoQueryWithFilters(
				$userLat,
				$userLng,
				$locationIds,
				$radiusUsedKm,
				$page,
				$limit,
				$additionalFilters
			);
			$totalCount = $this->executeCount($built['count_sql'], $built['count_params']);
		}

		if ($totalCount === 0) {
			$fallbackUsed = true;
			$radiusUsedKm = 10.0;

			$built = $this->buildGeoQueryWithFilters(
				$userLat,
				$userLng,
				[],
				$radiusUsedKm,
				$page,
				$limit,
				$additionalFilters
			);
			$totalCount = $this->executeCount($built['count_sql'], $built['count_params']);
		}

		$stmt = $this->pdo->prepare($built['sql']);
		$stmt->execute($built['params']);
		$results = $stmt->fetchAll();

		$stationsSearched = $this->resolveStationNames($stationIds);

		return [
			'results' => is_array($results) ? $results : [],
			'nearest_station' => (string) ($nearestStation['station']['name'] ?? ''),
			'station_distance_km' => round((float) $nearestStation['distance_km'], 2),
			'stations_searched' => $stationsSearched,
			'radius_used_km' => $radiusUsedKm,
			'fallback_used' => $fallbackUsed,
			'pagination' => [
				'current_page' => $page,
				'per_page' => $limit,
				'total_count' => $totalCount,
				'total_pages' => $totalCount > 0 ? (int) ceil($totalCount / $limit) : 0,
			],
			'total_count' => $totalCount,
		];
	}

	private function buildGeoQueryWithFilters(
		float $userLat,
		float $userLng,
		array $locationIds,
		float $radiusKm,
		int $page,
		int $limit,
		array $additionalFilters
	): array {
		$radiusKm = $radiusKm > 0 ? $radiusKm : 7.0;
		$page = $page > 0 ? $page : 1;
		$limit = $limit > 0 ? $limit : 20;
		$offset = ($page - 1) * $limit;

		$latDelta = $radiusKm / 111.0;
		$cosLatitude = cos(deg2rad($userLat));
		if (abs($cosLatitude) < 0.000001) {
			$cosLatitude = $cosLatitude >= 0 ? 0.000001 : -0.000001;
		}
		$lngDelta = $radiusKm / (111.0 * $cosLatitude);

		$minLat = $userLat - $latDelta;
		$maxLat = $userLat + $latDelta;
		$minLng = $userLng - $lngDelta;
		$maxLng = $userLng + $lngDelta;

		$distanceExpression = '(6371 * acos('
			. 'cos(radians(?)) * cos(radians(l.latitude)) * '
			. 'cos(radians(l.longitude) - radians(?)) + '
			. 'sin(radians(?)) * sin(radians(l.latitude))'
			. '))';

		$selectFields = [
			'p.id AS project_id',
			'p.name AS project_name',
			'p.project_status',
			'p.possession_date',
			'p.header_image',
			'p.rera_no',
			'p.project_segment',
			'p.rank',
			'p.landmark',
			'p.flat_configuration',
			'b.name AS builder_name',
			'l.id AS location_id',
			'l.name AS location_name',
			'l.latitude',
			'l.longitude',
			'ns.name AS matched_station',
			'f.id AS flat_id',
			'f.type AS flat_type',
			'f.base_price',
			'f.total_charge',
			'f.carpet_area',
			'f.builtup_area',
			'f.bathroom_count',
			'f.transaction_type',
			$distanceExpression . ' AS distance_km',
		];

		$fromAndJoins =
			"FROM projects p\n"
			. "JOIN location l ON p.location_id = l.id\n"
			. "JOIN flat f ON f.projects_id = p.id\n"
			. "JOIN builder b ON p.builder_id = b.id\n"
			. 'LEFT JOIN stations ns ON ns.id = l.nearest_station_id';

		$whereConditions = [
			'p.status = 1',
			'f.status = 1',
			'l.latitude IS NOT NULL',
			'l.longitude IS NOT NULL',
			'l.latitude BETWEEN ? AND ?',
			'l.longitude BETWEEN ? AND ?',
			$distanceExpression . ' <= ?',
		];

		$selectParams = [$userLat, $userLng, $userLat];
		$whereParams = [$minLat, $maxLat, $minLng, $maxLng, $userLat, $userLng, $userLat, $radiusKm];

		$normalizedLocationIds = $this->normalizeIntArray($locationIds);
		if ($normalizedLocationIds !== []) {
			$locationPlaceholders = implode(',', array_fill(0, count($normalizedLocationIds), '?'));
			$whereConditions[] = 'p.location_id IN (' . $locationPlaceholders . ')';
			foreach ($normalizedLocationIds as $locationId) {
				$whereParams[] = $locationId;
			}
		}

		$this->applyAdditionalFilters($whereConditions, $whereParams, $additionalFilters);

		$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

		$sql =
			"SELECT\n    " . implode(",\n    ", $selectFields) . "\n"
			. $fromAndJoins . "\n"
			. $whereClause . "\n"
			. 'ORDER BY distance_km ASC, p.rank ASC, f.base_price ASC' . "\n"
			. 'LIMIT ? OFFSET ?';

		$params = array_merge($selectParams, $whereParams, [$limit, $offset]);

		$countSql =
			'    SELECT COUNT(DISTINCT p.id) AS total' . "\n"
			. $fromAndJoins . "\n"
			. $whereClause;

		return [
			'sql' => $sql,
			'params' => $params,
			'count_sql' => $countSql,
			'count_params' => $whereParams,
		];
	}

	private function applyAdditionalFilters(array &$whereConditions, array &$whereParams, array $additionalFilters): void
	{
		$bhk = isset($additionalFilters['bhk']) ? trim((string) $additionalFilters['bhk']) : '';
		if ($bhk !== '') {
			$whereConditions[] = 'f.type = ?';
			$whereParams[] = $bhk;
		}

		$transactionType = isset($additionalFilters['transaction_type']) ? trim((string) $additionalFilters['transaction_type']) : '';
		if ($transactionType !== '') {
			$whereConditions[] = 'f.transaction_type = ?';
			$whereParams[] = $transactionType;
		}

		$maxBudget = $this->normalizeNullableInt($additionalFilters['max_budget'] ?? null);
		if ($maxBudget !== null) {
			$whereConditions[] = 'f.base_price <= ?';
			$whereParams[] = $maxBudget;
		}

		$minBudget = $this->normalizeNullableInt($additionalFilters['min_budget'] ?? null);
		if ($minBudget !== null) {
			$whereConditions[] = 'f.base_price >= ?';
			$whereParams[] = $minBudget;
		}

		$projectSegment = isset($additionalFilters['project_segment']) ? trim((string) $additionalFilters['project_segment']) : '';
		if ($projectSegment !== '') {
			$whereConditions[] = 'p.project_segment = ?';
			$whereParams[] = $projectSegment;
		}

		$possession = isset($additionalFilters['possession']) ? trim((string) $additionalFilters['possession']) : '';
		if ($possession !== '') {
			$whereConditions[] = 'p.project_status = ?';
			$whereParams[] = $possession;
		}
	}

	private function executeCount(string $countSql, array $params): int
	{
		$stmt = $this->pdo->prepare($countSql);
		$stmt->execute($params);
		return (int) $stmt->fetchColumn();
	}

	private function resolveStationNames(array $stationIds): array
	{
		$this->ensureStationsLoaded();
		$normalizedIds = $this->normalizeIntArray($stationIds);

		$names = [];
		foreach ($normalizedIds as $stationId) {
			$station = self::$stationCache[$stationId] ?? null;
			if ($station === null) {
				continue;
			}

			$stationName = trim((string) ($station['name'] ?? ''));
			if ($stationName === '') {
				continue;
			}
			$names[$stationName] = true;
		}

		return array_keys($names);
	}

	private function expandStationIdsBySameName(int $stationId): array
	{
		$this->ensureStationsLoaded();

		$station = self::$stationCache[$stationId] ?? null;
		if ($station === null) {
			return [];
		}

		$stationName = trim((string) ($station['name'] ?? ''));
		if ($stationName === '') {
			return [$stationId];
		}

		$nameKey = self::normalizeNameKey($stationName);
		$sameNameIds = self::$stationNameIndex[$nameKey] ?? [$stationId];

		return $this->normalizeIntArray($sameNameIds);
	}

	/**
	 * @param array<int> $stationIds
	 * @return array<int, array<string, mixed>>
	 */
	private function fetchStationGraphRowsByStationIds(array $stationIds): array
	{
		$normalizedIds = $this->normalizeIntArray($stationIds);
		if ($normalizedIds === []) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
		$sql = 'SELECT station_id, prev_station_id, next_station_id, line FROM station_graph WHERE station_id IN (' . $placeholders . ')';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($normalizedIds);
		$rows = $stmt->fetchAll();

		return is_array($rows) ? $rows : [];
	}

	private function ensureStationsLoaded(): void
	{
		if (self::$stationCache !== null && self::$stationNameIndex !== null) {
			return;
		}

		$sql = 'SELECT id, name, line, latitude, longitude, sequence, zone FROM stations ORDER BY id ASC';
		$stmt = $this->pdo->query($sql);
		$rows = $stmt->fetchAll();

		$cache = [];
		$nameIndex = [];

		foreach ($rows as $row) {
			$stationId = isset($row['id']) ? (int) $row['id'] : 0;
			if ($stationId <= 0) {
				continue;
			}

			$cache[$stationId] = $row;

			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}

			$nameKey = self::normalizeNameKey($name);
			if (!isset($nameIndex[$nameKey])) {
				$nameIndex[$nameKey] = [];
			}

			$nameIndex[$nameKey][] = $stationId;
		}

		self::$stationCache = $cache;
		self::$stationNameIndex = $nameIndex;
	}

	private static function normalizeNameKey(string $value): string
	{
		$lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
		$normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;
		$normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
		return trim($normalized);
	}

	private function normalizeIntArray(array $values): array
	{
		$normalized = [];
		foreach ($values as $value) {
			if (!is_numeric($value)) {
				continue;
			}

			$intValue = (int) $value;
			if ($intValue > 0) {
				$normalized[$intValue] = true;
			}
		}

		return array_keys($normalized);
	}

	private function normalizeNullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		return (int) $value;
	}

	private static function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
	{
		$earthRadius = 6371; // km
		$dLat = deg2rad($lat2 - $lat1);
		$dLng = deg2rad($lng2 - $lng1);
		$a = sin($dLat / 2) * sin($dLat / 2)
			+ cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
		$distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $distance;
	}
}
