<?php
declare(strict_types=1);

final class QueryParser
{
	public static function parse(string $rawQuery): array
	{
		$working = self::normalizeInput($rawQuery);
		$working = self::removeGenericPropertyTerms($working);

		if ($working === '') {
			return self::defaultPayload();
		}

		// GPS intent must be checked first and short-circuit the parse.
		if (preg_match('/\b(?:near\s+me|nearby|close\s+to\s+me|around\s+me)\b/i', $working) === 1) {
			return [
				'geo_intent' => true,
			];
		}

		$parsed = self::defaultPayload();

		// 1) BHK
		$parsed['bhk'] = self::extractMappedValue($working, self::getMapConstant('BHK_MAP'));

		// 2) Transaction type
		$parsed['transaction_type'] = self::extractMappedValue($working, self::getMapConstant('TRANSACTION_MAP'));

		// 3) Budget
		self::extractBudgets($working, $parsed);

		// 4) Project segment
		$segmentMap = self::getMapConstant('SEGMENT_MAP');
		if (isset($segmentMap['value']) && !isset($segmentMap['value homes'])) {
			$segmentMap['value homes'] = $segmentMap['value'];
		}
		$parsed['project_segment'] = self::extractMappedValue($working, $segmentMap);

		// 5) Possession status
		$possessionRules = [
			'/\b(?:ready\s+to\s+move|rtm)\b/i' => 'Ready To Move',
			'/\b(?:under\s+construction|uc)\b/i' => 'Under Construction',
			'/\bupcoming\b/i' => 'Upcoming',
		];

		foreach ($possessionRules as $pattern => $value) {
			if (preg_match($pattern, $working) === 1) {
				$parsed['possession'] = $value;
				$working = preg_replace($pattern, ' ', $working, 1) ?? $working;
				break;
			}
		}

		// 6) Raw location = remaining text after all removals.
		$remaining = self::normalizeInput($working);
		$parsed['raw_location'] = self::normalizeRawLocation($remaining);

		return $parsed;
	}

	private static function defaultPayload(): array
	{
		return [
			'bhk' => null,
			'transaction_type' => null,
			'max_budget' => null,
			'min_budget' => null,
			'project_segment' => null,
			'possession' => null,
			'raw_location' => null,
			'geo_intent' => false,
		];
	}

	private static function extractMappedValue(string &$working, array $map): ?string
	{
		if ($map === []) {
			return null;
		}

		uksort(
			$map,
			static fn (string $a, string $b): int => strlen($b) <=> strlen($a)
		);

		foreach ($map as $variant => $dbValue) {
			$pattern = self::buildVariantPattern($variant);
			if (preg_match($pattern, $working) === 1) {
				$working = preg_replace($pattern, ' ', $working, 1) ?? $working;
				return (string) $dbValue;
			}
		}

		return null;
	}

	private static function extractBudgets(string &$working, array &$parsed): void
	{
		$pattern = '/\b(?:(under|below|upto|up\s*to|max|above|minimum|min|starting)\s*)?(\d+(?:[.,]\d+)?)\s*(k|lakh|l|cr)?\b/i';
		$maxContext = ['under', 'below', 'upto', 'up to', 'max'];
		$minContext = ['above', 'minimum', 'min', 'starting'];
		$budgetMultipliers = self::getMapConstant('BUDGET_MULTIPLIERS');

		$matchCount = preg_match_all($pattern, $working, $matches, PREG_SET_ORDER);
		if ($matchCount === false || $matchCount === 0) {
			return;
		}

		$consumedTokens = [];

		foreach ($matches as $match) {
			$fullToken = trim((string) ($match[0] ?? ''));
			$context = self::normalizeInput((string) ($match[1] ?? ''));
			$numberRaw = str_replace(',', '', (string) ($match[2] ?? ''));
			$unit = self::normalizeInput((string) ($match[3] ?? ''));

			if ($fullToken === '' || $numberRaw === '') {
				continue;
			}

			$amount = (float) $numberRaw;
			if ($amount <= 0) {
				continue;
			}

			$hasContext = $context !== '';
			$hasUnit = $unit !== '';

			// Ignore tiny bare numbers so terms like "sector 5" are not treated as budgets.
			if (!$hasContext && !$hasUnit && $amount < 1000) {
				continue;
			}

			$multiplier = 1;
			if ($hasUnit && isset($budgetMultipliers[$unit])) {
				$multiplier = (int) $budgetMultipliers[$unit];
			}

			$normalizedValue = (int) round($amount * $multiplier);

			if (in_array($context, $minContext, true)) {
				if ($parsed['min_budget'] === null) {
					$parsed['min_budget'] = $normalizedValue;
				} else {
					$parsed['min_budget'] = max((int) $parsed['min_budget'], $normalizedValue);
				}
			} else {
				// Default to max budget when context is absent.
				if ($parsed['max_budget'] === null) {
					$parsed['max_budget'] = $normalizedValue;
				} else {
					$parsed['max_budget'] = min((int) $parsed['max_budget'], $normalizedValue);
				}
			}

			$consumedTokens[] = $fullToken;
		}

		foreach ($consumedTokens as $token) {
			$working = preg_replace('/' . preg_quote($token, '/') . '/i', ' ', $working, 1) ?? $working;
		}
	}

	private static function getMapConstant(string $constantName): array
	{
		if (!defined($constantName)) {
			return [];
		}

		$value = constant($constantName);

		return is_array($value) ? $value : [];
	}

	private static function buildVariantPattern(string $variant): string
	{
		$normalizedVariant = self::normalizeInput($variant);
		$escaped = preg_quote($normalizedVariant, '/');
		$escaped = str_replace(' ', '\\s+', $escaped);

		return '/\b' . $escaped . '\b/i';
	}

	private static function normalizeInput(string $value): string
	{
		$clean = strip_tags($value);

		if (function_exists('mb_strtolower')) {
			$clean = mb_strtolower($clean, 'UTF-8');
		} else {
			$clean = strtolower($clean);
		}

		$clean = preg_replace('/[^\p{L}\p{N}\s\.,]/u', ' ', $clean) ?? $clean;
		$clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

		return trim($clean);
	}

	private static function removeGenericPropertyTerms(string $value): string
	{
		$clean = preg_replace(
			'/\b(?:flat|flats|property|properties|house|houses|home|homes|apartment|apartments|residence|residential|condo|condos|villa|villas)\b/i',
			' ',
			$value
		) ?? $value;

		$clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

		return trim($clean);
	}

	private static function normalizeRawLocation(string $value): ?string
	{
		if ($value === '') {
			return null;
		}

		$clean = preg_replace(
			'/^(?:in|at|on|for|from|to|near|around|within|inside|of|the)\s+/i',
			'',
			$value
		) ?? $value;

		$clean = preg_replace(
			'/\s+(?:in|at|on|for|from|to|near|around|within|inside|of|the)\s*$/i',
			'',
			$clean
		) ?? $clean;

		$clean = trim($clean, " \t\n\r\0\x0B,.");

		return $clean !== '' ? $clean : null;
	}
}
