<?php
declare(strict_types=1);

final class FavoritesService
{
	private PDO $pdo;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function addFavorite(string $sessionId, int $projectId, ?string $flatType = null): array
	{
		$session = trim($sessionId);
		if ($session === '' || $projectId <= 0) {
			return [
				'success' => false,
				'action' => 'invalid_input',
			];
		}

		if ($this->isFavorite($session, $projectId)) {
			return [
				'success' => true,
				'action' => 'already_exists',
			];
		}

		$normalizedFlatType = $this->normalizeFlatType($flatType);

		$sql = 'INSERT INTO favorites (session_id, user_id, project_id, flat_type, created_on) VALUES (?, NULL, ?, ?, NOW())';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$session, $projectId, $normalizedFlatType]);

		return [
			'success' => true,
			'action' => 'added',
		];
	}

	public function removeFavorite(string $sessionId, int $projectId): array
	{
		$session = trim($sessionId);
		if ($session === '' || $projectId <= 0) {
			return [
				'success' => false,
				'action' => 'invalid_input',
			];
		}

		$sql = 'DELETE FROM favorites WHERE session_id = ? AND project_id = ?';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$session, $projectId]);

		return [
			'success' => true,
			'action' => 'removed',
		];
	}

	public function toggleFavorite(string $sessionId, int $projectId, ?string $flatType = null): array
	{
		$session = trim($sessionId);
		if ($session === '' || $projectId <= 0) {
			return [
				'success' => false,
				'action' => 'invalid_input',
				'is_favorite' => false,
			];
		}

		if ($this->isFavorite($session, $projectId)) {
			$this->removeFavorite($session, $projectId);
			return [
				'success' => true,
				'action' => 'removed',
				'is_favorite' => false,
			];
		}

		$addResult = $this->addFavorite($session, $projectId, $flatType);
		$isFavorite = ($addResult['success'] ?? false) === true;

		return [
			'success' => $isFavorite,
			'action' => $isFavorite ? 'added' : 'invalid_input',
			'is_favorite' => $isFavorite,
		];
	}

	public function getFavorites(string $sessionId, int $page = 1, int $limit = 20): array
	{
		$session = trim($sessionId);
		if ($session === '') {
			return [
				'results' => [],
				'pagination' => [
					'current_page' => 1,
					'per_page' => 20,
					'total_count' => 0,
					'total_pages' => 0,
				],
				'total_count' => 0,
			];
		}

		$normalizedPage = $page > 0 ? $page : 1;
		$normalizedLimit = $this->normalizeLimit($limit);
		$offset = ($normalizedPage - 1) * $normalizedLimit;

		$projectLatitudeExpression = 'COALESCE(NULLIF(p.proj_latitude, 0), l.latitude)';
		$projectLongitudeExpression = 'COALESCE(NULLIF(p.proj_longitude, 0), l.longitude)';

		$fromAndJoins =
			"FROM (\n"
			. "\tSELECT project_id, MAX(id) AS favorite_id\n"
			. "\tFROM favorites\n"
			. "\tWHERE session_id = ?\n"
			. "\tGROUP BY project_id\n"
			. ") fav_idx\n"
			. "JOIN favorites fav ON fav.id = fav_idx.favorite_id\n"
			. "JOIN projects p ON p.id = fav.project_id\n"
			. "JOIN location l ON p.location_id = l.id\n"
			. "JOIN builder b ON p.builder_id = b.id\n"
			. "JOIN flat f ON f.id = (\n"
			. "\tSELECT f2.id\n"
			. "\tFROM flat f2\n"
			. "\tWHERE f2.projects_id = p.id AND f2.status = 1\n"
			. "\tORDER BY\n"
			. "\t\tCASE\n"
			. "\t\t\tWHEN fav.flat_type IS NOT NULL AND fav.flat_type <> '' AND f2.type = fav.flat_type THEN 0\n"
			. "\t\t\tWHEN fav.flat_type IS NOT NULL AND fav.flat_type <> '' THEN 1\n"
			. "\t\t\tELSE 0\n"
			. "\t\tEND,\n"
			. "\t\tf2.base_price ASC,\n"
			. "\t\tf2.id ASC\n"
			. "\tLIMIT 1\n"
			. ')';

		$whereClause = 'WHERE p.status = 1';

		$countSql =
			'SELECT COUNT(*) AS total' . "\n"
			. $fromAndJoins . "\n"
			. $whereClause;

		$countStmt = $this->pdo->prepare($countSql);
		$countStmt->execute([$session]);
		$totalCount = (int) $countStmt->fetchColumn();

		$sql =
			"SELECT\n"
			. "\tp.id AS project_id,\n"
			. "\tp.name AS project_name,\n"
			. "\tp.project_status,\n"
			. "\tp.possession_date,\n"
			. "\tp.header_image,\n"
			. "\tp.rera_no,\n"
			. "\tp.project_segment,\n"
			. "\tp.rank,\n"
			. "\tp.landmark,\n"
			. "\tp.flat_configuration,\n"
			. "\tb.name AS builder_name,\n"
			. "\tl.id AS location_id,\n"
			. "\tl.name AS location_name,\n"
			. "\t" . $projectLatitudeExpression . " AS latitude,\n"
			. "\t" . $projectLongitudeExpression . " AS longitude,\n"
			. "\tf.id AS flat_id,\n"
			. "\tf.type AS flat_type,\n"
			. "\tf.base_price,\n"
			. "\tf.total_charge,\n"
			. "\tf.carpet_area,\n"
			. "\tf.builtup_area,\n"
			. "\tf.bathroom_count,\n"
			. "\tf.transaction_type,\n"
			. "\t1 AS is_favorite\n"
			. $fromAndJoins . "\n"
			. $whereClause . "\n"
			. 'ORDER BY fav.created_on DESC, p.rank ASC, f.base_price ASC' . "\n"
			. 'LIMIT ? OFFSET ?';

		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(1, $session, PDO::PARAM_STR);
		$stmt->bindValue(2, $normalizedLimit, PDO::PARAM_INT);
		$stmt->bindValue(3, $offset, PDO::PARAM_INT);
		$stmt->execute();

		$rows = $stmt->fetchAll();
		$results = [];

		foreach ($rows as $row) {
			$row['is_favorite'] = true;
			$results[] = $row;
		}

		$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $normalizedLimit) : 0;

		return [
			'results' => $results,
			'pagination' => [
				'current_page' => $normalizedPage,
				'per_page' => $normalizedLimit,
				'total_count' => $totalCount,
				'total_pages' => $totalPages,
			],
			'total_count' => $totalCount,
		];
	}

	public function isFavorite(string $sessionId, int $projectId): bool
	{
		$session = trim($sessionId);
		if ($session === '' || $projectId <= 0) {
			return false;
		}

		$sql = 'SELECT COUNT(*) AS total FROM favorites WHERE session_id = ? AND project_id = ?';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$session, $projectId]);

		return ((int) $stmt->fetchColumn()) > 0;
	}

	public function getFavoriteIds(string $sessionId): array
	{
		$session = trim($sessionId);
		if ($session === '') {
			return [];
		}

		$sql = 'SELECT DISTINCT project_id FROM favorites WHERE session_id = ? ORDER BY created_on DESC';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$session]);
		$rows = $stmt->fetchAll();

		$ids = [];
		foreach ($rows as $row) {
			$projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
			if ($projectId > 0) {
				$ids[] = $projectId;
			}
		}

		return $ids;
	}

	public function getFavoriteCount(string $sessionId): int
	{
		$session = trim($sessionId);
		if ($session === '') {
			return 0;
		}

		$sql = 'SELECT COUNT(*) AS total FROM favorites WHERE session_id = ?';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$session]);

		return (int) $stmt->fetchColumn();
	}

	private function normalizeFlatType(?string $flatType): ?string
	{
		if ($flatType === null) {
			return null;
		}

		$normalized = trim($flatType);
		return $normalized !== '' ? $normalized : null;
	}

	private function normalizeLimit(int $limit): int
	{
		$normalized = $limit > 0 ? $limit : 20;
		if (defined('MAX_LIMIT')) {
			$max = (int) constant('MAX_LIMIT');
			if ($max > 0) {
				$normalized = min($normalized, $max);
			}
		}

		return $normalized;
	}
}
