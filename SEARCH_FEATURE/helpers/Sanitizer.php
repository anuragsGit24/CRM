<?php
declare(strict_types=1);

final class Sanitizer
{
	private function __construct()
	{
	}

	public static function sanitizeQuery(string $query): string
	{
		$clean = strip_tags($query);
		$clean = trim($clean);
		$clean = preg_replace('/\s+/', ' ', $clean) ?? '';

		if (function_exists('mb_substr')) {
			return mb_substr($clean, 0, 200);
		}

		return substr($clean, 0, 200);
	}

	public static function sanitizeInt(mixed $value): int
	{
		if (filter_var($value, FILTER_VALIDATE_INT) === false) {
			return 0;
		}

		return (int) $value;
	}

	public static function sanitizePlatform(string $platform): string
	{
		$normalized = strtolower(trim($platform));
		$allowed = ['web', 'android', 'ios'];

		return in_array($normalized, $allowed, true) ? $normalized : 'unknown';
	}

	public static function validatePagination(mixed $page, mixed $limit): array
	{
		$sanitizedPage = self::sanitizeInt($page);
		if ($sanitizedPage < 1) {
			$sanitizedPage = 1;
		}

		$defaultLimit = defined('DEFAULT_LIMIT') ? (int) DEFAULT_LIMIT : 20;
		$maxLimit = defined('MAX_LIMIT') ? (int) MAX_LIMIT : 50;

		$sanitizedLimit = self::sanitizeInt($limit);
		if ($sanitizedLimit < 1) {
			$sanitizedLimit = $defaultLimit;
		}
		if ($sanitizedLimit > $maxLimit) {
			$sanitizedLimit = $maxLimit;
		}

		return [
			'page' => $sanitizedPage,
			'limit' => $sanitizedLimit,
		];
	}
}
