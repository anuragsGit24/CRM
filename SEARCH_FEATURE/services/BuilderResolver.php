<?php
declare(strict_types=1);

final class BuilderResolver
{
	private PDO $pdo;
	private const TOKEN_STOP_WORDS = [
		'property',
		'properties',
		'flat',
		'flats',
		'flates',
		'apartment',
		'apartments',
		'house',
		'houses',
		'home',
		'homes',
		'in',
		'at',
		'on',
		'for',
		'from',
		'to',
		'of',
		'near',
		'around',
		'with',
		'and',
		'south',
		'north',
		'east',
		'west',
		'mumbai',
		'bombay',
		'thane',
		'navi',
		'under',
		'over',
		'below',
		'above',
		'crore',
		'lakhs',
		'lakh',
		'cr',
		'bhk',
		'studio',
	];

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function resolve(string $builderHint): ?int
	{
		$hint = trim($builderHint);
		if ($hint === '') {
			return null;
		}

		$normalizedHint = preg_replace('/\s+/', ' ', $hint) ?? $hint;
		$normalizedHint = trim($normalizedHint);
		if ($normalizedHint === '') {
			return null;
		}

		// Step 1: exact match.
		$exactSql = 'SELECT id
			FROM builder
			WHERE LOWER(name) = LOWER(:hint)
			  AND status = 1
			LIMIT 1';
		$exactMatch = $this->fetchBuilderId($exactSql, [':hint' => $normalizedHint]);
		if ($exactMatch !== null) {
			return $exactMatch;
		}

		// Step 2: contains match.
		$containsSql = 'SELECT id
			FROM builder
			WHERE LOWER(name) LIKE LOWER(:hint)
			  AND status = 1
			ORDER BY name ASC
			LIMIT 1';
		$containsMatch = $this->fetchBuilderId($containsSql, [':hint' => '%' . $normalizedHint . '%']);
		if ($containsMatch !== null) {
			return $containsMatch;
		}

		// Step 3: if multiple words, match any word >= 4 chars.
		$tokens = preg_split('/\s+/', $normalizedHint) ?: [];
		if (count($tokens) < 2) {
			return null;
		}

		$wordSql = 'SELECT id
			FROM builder
			WHERE LOWER(name) LIKE LOWER(:hint)
			  AND status = 1
			LIMIT 1';

		foreach ($tokens as $token) {
			$word = trim($token);
			if (strlen($word) < 4) {
				continue;
			}

			$normalizedWord = strtolower($word);
			if (in_array($normalizedWord, self::TOKEN_STOP_WORDS, true)) {
				continue;
			}

			$wordMatch = $this->fetchBuilderId($wordSql, [':hint' => '%' . $word . '%']);
			if ($wordMatch !== null) {
				return $wordMatch;
			}
		}

		return null;
	}

	public function getBuilderName(int $builderId): ?string
	{
		if ($builderId <= 0) {
			return null;
		}

		$sql = 'SELECT name FROM builder WHERE id = ? LIMIT 1';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$builderId]);
		$name = $stmt->fetchColumn();

		if ($name === false) {
			return null;
		}

		$resolvedName = trim((string) $name);
		return $resolvedName !== '' ? $resolvedName : null;
	}

	public function getAllBuilders(): array
	{
		$sql = 'SELECT id, name FROM builder WHERE status = 1 ORDER BY name ASC';
		$stmt = $this->pdo->query($sql);
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

	private function fetchBuilderId(string $sql, array $params): ?int
	{
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		$match = $stmt->fetchColumn();

		return $match !== false ? (int) $match : null;
	}
}
