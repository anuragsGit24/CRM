<?php
declare(strict_types=1);

final class PostSearchQueryBuilder
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
		array $directFilters = []
	): array
	{
		$page = $page > 0 ? $page : 1;
		$limit = $limit > 0 ? $limit : 20;
		$offset = ($page - 1) * $limit;

		$resolved = $this->resolveFilters($parsed, $locationId, $directFilters);

		$selectFields = [
			'cpf.id AS post_id',
			'cpf.title',
			'cpf.description',
			'cpf.type AS post_type',
			'cpf.post_for',
			'cpf.flat_property_type',
			'cpf.project_name',
			'cpf.budget',
			'cpf.deposit',
			'cpf.monthly_rent',
			'cpf.carpet',
			'cpf.status AS post_status',
			'cpf.posting_type',
			'cpf.landmark',
			'cpf.created_on',
			'cpf.rank',
			'cpf.latitude AS post_lat',
			'cpf.longitude AS post_lng',
			'ft.label AS flat_type_label',
			'l.name AS location_name',
			'l.latitude AS loc_latitude',
			'l.longitude AS loc_longitude',
		];

		$fromAndJoins =
			"FROM cp_customers_flats cpf\n"
			. 'LEFT JOIN flat_types ft ON cpf.flat_type_id = ft.id' . "\n"
			. 'JOIN location l ON cpf.location_id = l.id';

		$whereConditions = [
			'l.status = 1',
		];

		$params = [];
		$countParams = [];
		$usedFulltext = false;

		$this->appendInCondition(
			$whereConditions,
			'cpf.status',
			'resolved_status',
			$resolved['statuses'],
			$params,
			$countParams
		);

		if ($resolved['location_ids'] !== []) {
			$this->appendInCondition(
				$whereConditions,
				'cpf.location_id',
				'location_id',
				$resolved['location_ids'],
				$params,
				$countParams
			);
		} elseif ($resolved['location_id'] !== null) {
			$whereConditions[] = 'cpf.location_id = :location_id';
			$params[':location_id'] = $resolved['location_id'];
			$countParams[':location_id'] = $resolved['location_id'];
		} elseif ($resolved['raw_location'] !== null) {
			$usedFulltext = true;
			$booleanQuery = self::buildRequiredBooleanQuery($resolved['raw_location']);
			$matchExpression = 'MATCH(cpf.title, cpf.description, cpf.project_name, cpf.landmark)';

			$selectFields[] = $matchExpression . ' AGAINST(:fulltext_select IN BOOLEAN MODE) AS relevance_score';
			$whereConditions[] = $matchExpression . ' AGAINST(:fulltext_where IN BOOLEAN MODE)';

			$params[':fulltext_select'] = $booleanQuery;
			$params[':fulltext_where'] = $booleanQuery;
			$countParams[':fulltext_where'] = $booleanQuery;
		}

		if ($resolved['post_type'] !== null) {
			$whereConditions[] = 'cpf.type = :post_type';
			$params[':post_type'] = $resolved['post_type'];
			$countParams[':post_type'] = $resolved['post_type'];
		}

		if ($resolved['post_for'] !== null) {
			$whereConditions[] = 'cpf.post_for = :post_for';
			$params[':post_for'] = $resolved['post_for'];
			$countParams[':post_for'] = $resolved['post_for'];
		}

		$this->appendInCondition(
			$whereConditions,
			'cpf.flat_type_id',
			'flat_type_id',
			$resolved['flat_type_ids'],
			$params,
			$countParams
		);

		if ($resolved['flat_property_types'] !== []) {
			$this->appendInCondition(
				$whereConditions,
				'cpf.flat_property_type',
				'flat_property_type',
				$resolved['flat_property_types'],
				$params,
				$countParams
			);
		} elseif ($resolved['flat_property_type'] !== null) {
			$whereConditions[] = 'cpf.flat_property_type = :flat_property_type';
			$params[':flat_property_type'] = $resolved['flat_property_type'];
			$countParams[':flat_property_type'] = $resolved['flat_property_type'];
		}

		$budgetColumn = $resolved['post_for'] === 2 ? 'cpf.monthly_rent' : 'cpf.budget';

		if ($resolved['min_budget'] !== null) {
			$whereConditions[] = $budgetColumn . ' >= :min_budget';
			$params[':min_budget'] = $resolved['min_budget'];
			$countParams[':min_budget'] = $resolved['min_budget'];
		}

		if ($resolved['max_budget'] !== null) {
			$whereConditions[] = $budgetColumn . ' <= :max_budget';
			$params[':max_budget'] = $resolved['max_budget'];
			$countParams[':max_budget'] = $resolved['max_budget'];
		}

		if ($resolved['min_carpet'] !== null) {
			$whereConditions[] = 'cpf.carpet >= :min_carpet';
			$params[':min_carpet'] = $resolved['min_carpet'];
			$countParams[':min_carpet'] = $resolved['min_carpet'];
		}

		if ($resolved['max_carpet'] !== null) {
			$whereConditions[] = 'cpf.carpet <= :max_carpet';
			$params[':max_carpet'] = $resolved['max_carpet'];
			$countParams[':max_carpet'] = $resolved['max_carpet'];
		}

		if ($resolved['project_name'] !== null) {
			$whereConditions[] = 'cpf.project_name LIKE :project_name';
			$projectNameLike = '%' . $resolved['project_name'] . '%';
			$params[':project_name'] = $projectNameLike;
			$countParams[':project_name'] = $projectNameLike;
		}

		$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

		$orderBy = [
			'cpf.posting_type DESC',
		];

		if ($usedFulltext) {
			$orderBy[] = 'relevance_score DESC';
		}

		$orderBy[] = 'cpf.rank IS NULL ASC';
		$orderBy[] = 'cpf.rank ASC';
		$orderBy[] = 'cpf.created_on DESC';

		$sql =
			"SELECT\n    " . implode(",\n    ", $selectFields) . "\n"
			. $fromAndJoins . "\n"
			. $whereClause . "\n"
			. 'ORDER BY ' . implode(', ', $orderBy) . "\n"
			. 'LIMIT :limit OFFSET :offset';

		$params[':limit'] = $limit;
		$params[':offset'] = $offset;

		$countSql =
			'SELECT COUNT(DISTINCT cpf.id) AS total' . "\n"
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

	private function resolveFilters(array $parsed, ?int $locationId, array $directFilters): array
	{
		$defaultStatuses = self::defaultStatuses();

		$parsedPostType = self::normalizeAllowedInt($parsed['post_type'] ?? null, [1, 2]);
		$parsedPostFor = self::normalizeAllowedInt($parsed['post_for'] ?? null, [1, 2]);
		$parsedFlatTypeIds = self::normalizeIntArray($parsed['flat_type_ids'] ?? [], 1, 9999);
		$parsedFlatPropertyType = self::normalizeAllowedInt($parsed['flat_property_type'] ?? null, [1, 2, 3, 4, 5]);
		$parsedMinBudget = self::normalizeNullableInt($parsed['min_budget'] ?? null, 0);
		$parsedMaxBudget = self::normalizeNullableInt($parsed['max_budget'] ?? null, 0);
		$parsedMinCarpet = self::normalizeNullableInt($parsed['min_carpet'] ?? null, 0);
		$parsedMaxCarpet = self::normalizeNullableInt($parsed['max_carpet'] ?? null, 0);
		$parsedRawLocation = self::normalizeNullableString($parsed['raw_location'] ?? null);

		$postType = array_key_exists('post_type', $directFilters)
			? self::normalizeAllowedInt($directFilters['post_type'] ?? null, [1, 2])
			: $parsedPostType;

		$postFor = array_key_exists('post_for', $directFilters)
			? self::normalizeAllowedInt($directFilters['post_for'] ?? null, [1, 2])
			: $parsedPostFor;

		$flatTypeIds = array_key_exists('flat_type_ids', $directFilters)
			? self::normalizeIntArray($directFilters['flat_type_ids'] ?? [], 1, 9999)
			: $parsedFlatTypeIds;

		$flatPropertyTypes = array_key_exists('flat_property_types', $directFilters)
			? self::normalizeIntArray($directFilters['flat_property_types'] ?? [], 1, 5)
			: [];

		$flatPropertyType = $flatPropertyTypes === [] ? $parsedFlatPropertyType : null;

		$minBudget = array_key_exists('min_budget', $directFilters)
			? self::normalizeNullableInt($directFilters['min_budget'] ?? null, 0)
			: $parsedMinBudget;

		$maxBudget = array_key_exists('max_budget', $directFilters)
			? self::normalizeNullableInt($directFilters['max_budget'] ?? null, 0)
			: $parsedMaxBudget;

		$minCarpet = array_key_exists('min_carpet', $directFilters)
			? self::normalizeNullableInt($directFilters['min_carpet'] ?? null, 0)
			: $parsedMinCarpet;

		$maxCarpet = array_key_exists('max_carpet', $directFilters)
			? self::normalizeNullableInt($directFilters['max_carpet'] ?? null, 0)
			: $parsedMaxCarpet;

		$statuses = array_key_exists('statuses', $directFilters)
			? self::normalizeIntArray($directFilters['statuses'] ?? [], 0, 4)
			: $defaultStatuses;

		if ($statuses === []) {
			$statuses = $defaultStatuses;
		}

		$projectName = array_key_exists('project_name', $directFilters)
			? self::normalizeNullableString($directFilters['project_name'] ?? null)
			: self::normalizeNullableString($parsed['project_name_hint'] ?? null);

		$resolvedLocationIds = array_key_exists('location_ids', $directFilters)
			? self::normalizeIntArray($directFilters['location_ids'] ?? [], 1, null)
			: [];

		$resolvedLocationId = array_key_exists('location_id', $directFilters)
			? self::normalizeNullableInt($directFilters['location_id'] ?? null, 1)
			: self::normalizeNullableInt($locationId, 1);

		if ($resolvedLocationIds !== []) {
			$resolvedLocationId = null;
		}

		if ($minBudget !== null && $maxBudget !== null && $minBudget > $maxBudget) {
			[$minBudget, $maxBudget] = [$maxBudget, $minBudget];
		}

		if ($minCarpet !== null && $maxCarpet !== null && $minCarpet > $maxCarpet) {
			[$minCarpet, $maxCarpet] = [$maxCarpet, $minCarpet];
		}

		return [
			'post_type' => $postType,
			'post_for' => $postFor,
			'flat_type_ids' => $flatTypeIds,
			'flat_property_types' => $flatPropertyTypes,
			'flat_property_type' => $flatPropertyType,
			'min_budget' => $minBudget,
			'max_budget' => $maxBudget,
			'min_carpet' => $minCarpet,
			'max_carpet' => $maxCarpet,
			'statuses' => $statuses,
			'project_name' => $projectName,
			'location_ids' => $resolvedLocationIds,
			'location_id' => $resolvedLocationId,
			'raw_location' => $parsedRawLocation,
		];
	}

	private function appendInCondition(
		array &$whereConditions,
		string $column,
		string $prefix,
		array $values,
		array &$params,
		array &$countParams
	): void
	{
		if ($values === []) {
			return;
		}

		$placeholders = [];
		$index = 1;

		foreach ($values as $value) {
			$name = ':' . $prefix . '_' . $index;
			$placeholders[] = $name;
			$params[$name] = $value;
			$countParams[$name] = $value;
			$index++;
		}

		$whereConditions[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
	}

	private static function normalizeNullableString(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$normalized = trim((string) $value);
		return $normalized !== '' ? $normalized : null;
	}

	private static function normalizeNullableInt(mixed $value, int $min = 0, ?int $max = null): ?int
	{
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
	}

	private static function normalizeAllowedInt(mixed $value, array $allowed): ?int
	{
		$intValue = self::normalizeNullableInt($value, 0);
		if ($intValue === null) {
			return null;
		}

		return in_array($intValue, $allowed, true) ? $intValue : null;
	}

	private static function normalizeIntArray(mixed $value, int $min, ?int $max): array
	{
		if (!is_array($value)) {
			return [];
		}

		$normalized = [];
		foreach ($value as $item) {
			$intValue = self::normalizeNullableInt($item, $min, $max);
			if ($intValue === null) {
				continue;
			}

			$normalized[$intValue] = true;
		}

		return array_keys($normalized);
	}

	private static function defaultStatuses(): array
	{
		if (!defined('DEFAULT_POST_STATUSES')) {
			return [0, 1];
		}

		$value = constant('DEFAULT_POST_STATUSES');
		$statuses = self::normalizeIntArray($value, 0, 4);

		return $statuses !== [] ? $statuses : [0, 1];
	}

	private static function buildRequiredBooleanQuery(string $input): string
	{
		$tokens = preg_split('/\s+/', strtolower($input)) ?: [];
		$requiredTerms = [];

		foreach ($tokens as $token) {
			$clean = preg_replace('/[^a-z0-9]/', '', $token) ?? '';
			if ($clean === '' || strlen($clean) < 2) {
				continue;
			}

			if (in_array($clean, ['in', 'at', 'on', 'of', 'the', 'to', 'for', 'near', 'and'], true)) {
				continue;
			}

			$requiredTerms[$clean] = true;
		}

		if ($requiredTerms === []) {
			return $input;
		}

		return implode(' ', array_map(static fn(string $term): string => '+' . $term, array_keys($requiredTerms)));
	}
}