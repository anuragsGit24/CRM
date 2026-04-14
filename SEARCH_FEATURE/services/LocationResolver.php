<?php
declare(strict_types=1);

final class LocationResolver
{
	private PDO $pdo;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function resolve(string $rawLocation): ?int
	{
		$normalized = trim($rawLocation);
		if ($normalized === '') {
			return null;
		}

		// Step 1: exact alias match.
		$aliasSql = 'SELECT location_id FROM location_aliases WHERE LOWER(alias) = LOWER(:alias) LIMIT 1';
		$aliasStmt = $this->pdo->prepare($aliasSql);
		$aliasStmt->execute([':alias' => $normalized]);
		$aliasMatch = $aliasStmt->fetchColumn();
		if ($aliasMatch !== false) {
			return (int) $aliasMatch;
		}

		// Step 2: exact location name match.
		$exactSql = 'SELECT id FROM location WHERE LOWER(name) = LOWER(:name) AND status = 1 LIMIT 1';
		$exactStmt = $this->pdo->prepare($exactSql);
		$exactStmt->execute([':name' => $normalized]);
		$exactMatch = $exactStmt->fetchColumn();
		if ($exactMatch !== false) {
			return (int) $exactMatch;
		}

		// Step 3: partial location name LIKE match.
		$likeSql = 'SELECT id FROM location WHERE LOWER(name) LIKE LOWER(:name) AND status = 1 ORDER BY name ASC LIMIT 1';
		$likeStmt = $this->pdo->prepare($likeSql);
		$likeStmt->execute([':name' => '%' . $normalized . '%']);
		$likeMatch = $likeStmt->fetchColumn();

		return $likeMatch !== false ? (int) $likeMatch : null;
	}

	public function getSuggestions(string $query): array
	{
		$normalized = trim($query);
		if ($normalized === '') {
			return [];
		}

		$like = '%' . $normalized . '%';

		$sql = '
			SELECT MIN(s.id) AS id, s.name
			FROM (
				SELECT l.id, l.name
				FROM location l
				WHERE l.status = 1
				  AND LOWER(l.name) LIKE LOWER(:name_like)

				UNION

				SELECT l2.id, l2.name
				FROM location_aliases la
				INNER JOIN location l2 ON l2.id = la.location_id
				WHERE l2.status = 1
				  AND LOWER(la.alias) LIKE LOWER(:alias_like)
			) AS s
			GROUP BY s.name
			ORDER BY s.name ASC
			LIMIT 8
		';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			':name_like' => $like,
			':alias_like' => $like,
		]);

		$rows = $stmt->fetchAll();
		$results = [];

		foreach ($rows as $row) {
			$id = isset($row['id']) ? (int) $row['id'] : 0;
			$name = isset($row['name']) ? trim((string) $row['name']) : '';

			if ($id <= 0 || $name === '') {
				continue;
			}

			$results[] = [
				'id' => $id,
				'name' => $name,
			];
		}

		return $results;
	}
}
