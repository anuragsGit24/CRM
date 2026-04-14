<?php
declare(strict_types=1);

final class SearchLogger
{
	private PDO $pdo;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	public function log(
		string $rawQuery,
		array $parsed,
		int $resultCount,
		string $platform = 'unknown',
		?int $userId = null,
		?float $geoLat = null,
		?float $geoLng = null
	): void {
		$sql = 'INSERT INTO search_logs 
			(raw_query, parsed_output, result_count, platform, user_id, geo_lat, geo_lng, created_on)
			VALUES 
			(?, ?, ?, ?, ?, ?, ?, NOW())';

		$parsedJson = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($parsedJson === false) {
			$parsedJson = '{}';
		}

		try {
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				$rawQuery,
				$parsedJson,
				$resultCount,
				$platform,
				$userId,
				$geoLat,
				$geoLng,
			]);
		} catch (Throwable $exception) {
			error_log('Search logging failed: ' . $exception->getMessage());
		}
	}
}
