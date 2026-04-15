<?php
declare(strict_types=1);

final class SearchQueryBuilder
{
	private PDO $pdo;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function build(
		array $parsed,
		?int $locationId = null,
		int $page = 1,
		int $limit = 20,
		?float $geoLat = null,
		?float $geoLng = null
	): array
	{
		$page = $page > 0 ? $page : 1;
		$limit = $limit > 0 ? $limit : 20;
		$offset = ($page - 1) * $limit;

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
			'l.name AS location_name',
			'l.latitude',
			'l.longitude',
			'f.type AS flat_type',
			'f.base_price',
			'f.total_charge',
			'f.carpet_area',
			'f.builtup_area',
			'f.bathroom_count',
			'f.transaction_type',
		];

		$fromAndJoins =
			"FROM projects p\n"
			. "JOIN location l ON p.location_id = l.id\n"
			. "JOIN flat f ON f.projects_id = p.id\n"
			. 'JOIN builder b ON p.builder_id = b.id';

		$whereConditions = [
			'p.status = 1',
			'f.status = 1',
		];

		$selectParams = [];
		$whereParams = [];
		$countParams = [];
		$usedFulltext = false;
		$usedGeoDistance = (($parsed['geo_intent'] ?? false) === true) && $geoLat !== null && $geoLng !== null;

		if ($usedGeoDistance) {
			$distanceExpression = '(
				6371 * ACOS(
					LEAST(
						1,
						GREATEST(
							-1,
							COS(RADIANS(?)) * COS(RADIANS(l.latitude)) * COS(RADIANS(l.longitude) - RADIANS(?))
							+ SIN(RADIANS(?)) * SIN(RADIANS(l.latitude))
						)
					)
				)
			)';

			$selectFields[] = $distanceExpression . ' AS distance_km';
			$selectParams[] = $geoLat;
			$selectParams[] = $geoLng;
			$selectParams[] = $geoLat;

			$whereConditions[] = 'l.latitude IS NOT NULL';
			$whereConditions[] = 'l.longitude IS NOT NULL';
			$whereConditions[] = $distanceExpression . ' <= COALESCE(NULLIF(l.dist_range, 0), 5)';

			$whereParams[] = $geoLat;
			$whereParams[] = $geoLng;
			$whereParams[] = $geoLat;

			$countParams[] = $geoLat;
			$countParams[] = $geoLng;
			$countParams[] = $geoLat;
		}

		$rawLocation = self::normalizeNullableString($parsed['raw_location'] ?? null);

		if ($locationId !== null) {
			$whereConditions[] = 'p.location_id = ?';
			$whereParams[] = $locationId;
			$countParams[] = $locationId;
		} elseif ($rawLocation !== null) {
			$usedFulltext = true;
			$matchExpression = 'MATCH(p.name, p.site_address, p.landmark, p.amenities, p.flat_configuration) AGAINST(? IN BOOLEAN MODE)';

			// First placeholder belongs to SELECT relevance_score.
			$selectFields[] = $matchExpression . ' AS relevance_score';
			$selectParams[] = $rawLocation;

			$whereConditions[] = $matchExpression;
			$whereParams[] = $rawLocation;
			$countParams[] = $rawLocation;
		}

		$bhk = self::normalizeNullableString($parsed['bhk'] ?? null);
		if ($bhk !== null) {
			$whereConditions[] = 'f.type = ?';
			$whereParams[] = $bhk;
			$countParams[] = $bhk;
		}

		$transactionType = self::normalizeNullableString($parsed['transaction_type'] ?? null);
		if ($transactionType !== null) {
			$whereConditions[] = 'f.transaction_type = ?';
			$whereParams[] = $transactionType;
			$countParams[] = $transactionType;
		}

		$maxBudget = self::normalizeNullableInt($parsed['max_budget'] ?? null);
		if ($maxBudget !== null) {
			$whereConditions[] = 'f.base_price <= ?';
			$whereParams[] = $maxBudget;
			$countParams[] = $maxBudget;
		}

		$minBudget = self::normalizeNullableInt($parsed['min_budget'] ?? null);
		if ($minBudget !== null) {
			$whereConditions[] = 'f.base_price >= ?';
			$whereParams[] = $minBudget;
			$countParams[] = $minBudget;
		}

		$projectSegment = self::normalizeNullableString($parsed['project_segment'] ?? null);
		if ($projectSegment !== null) {
			$whereConditions[] = 'p.project_segment = ?';
			$whereParams[] = $projectSegment;
			$countParams[] = $projectSegment;
		}

		$possession = self::normalizeNullableString($parsed['possession'] ?? null);
		if ($possession !== null) {
			$whereConditions[] = 'p.project_status = ?';
			$whereParams[] = $possession;
			$countParams[] = $possession;
		}

		$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

		if ($usedGeoDistance && $usedFulltext) {
			$orderClause = 'ORDER BY distance_km ASC, relevance_score DESC, p.rank ASC, p.possession_date ASC';
		} elseif ($usedGeoDistance) {
			$orderClause = 'ORDER BY distance_km ASC, p.rank ASC, p.possession_date ASC';
		} elseif ($usedFulltext) {
			$orderClause = 'ORDER BY relevance_score DESC, p.rank ASC, p.possession_date ASC';
		} else {
			$orderClause = 'ORDER BY p.rank ASC, p.possession_date ASC';
		}

		$sql =
			"SELECT\n    " . implode(",\n    ", $selectFields) . "\n"
			. $fromAndJoins . "\n"
			. $whereClause . "\n"
			. $orderClause . "\n"
			. 'LIMIT ? OFFSET ?';

		$params = array_merge($selectParams, $whereParams);
		$params[] = $limit;
		$params[] = $offset;

		$countSql =
			'SELECT COUNT(DISTINCT p.id) AS total' . "\n"
			. $fromAndJoins . "\n"
			. $whereClause;

		return [
			'sql' => $sql,
			'count_sql' => $countSql,
			'params' => $params,
			'count_params' => $countParams,
			'used_fulltext' => $usedFulltext,
		];
	}

	private static function normalizeNullableString(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$normalized = trim((string) $value);

		return $normalized !== '' ? $normalized : null;
	}

	private static function normalizeNullableInt(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		return (int) $value;
	}
}
