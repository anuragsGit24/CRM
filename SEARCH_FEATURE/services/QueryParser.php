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
		if (preg_match('/\b(?:near\s+me|near\s*by|nearby|close\s+to\s+me|around\s+me)\b/i', $working) === 1) {
			return array_merge(self::defaultPayload(), [
				'geo_intent' => true,
			]);
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

		$postExtractionWorking = $remaining;

		// A) Property type extraction.
		$parsed['property_type'] = self::extractMappedIntValue($postExtractionWorking, self::getMapConstant('PROPERTY_TYPE_MAP'));

		// B) Amenities extraction (multi-select).
		$parsed['amenities'] = self::extractAmenityValues($postExtractionWorking, self::getMapConstant('AMENITY_ALIASES'));

		// Refresh raw location after removing property type and amenities tokens.
		$finalRemaining = self::normalizeInput($postExtractionWorking);
		$parsed['raw_location'] = self::normalizeRawLocation($finalRemaining);

		// C) Builder hint extraction from remaining unresolved token(s).
		$parsed['raw_builder_hint'] = self::extractRawBuilderHint($rawQuery, $parsed['raw_location']);

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
			'property_type' => null,
			'amenities' => [],
			'raw_builder_hint' => null,
		];
	}

	private static function extractMappedIntValue(string &$working, array $map): ?int
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
				$value = (int) $dbValue;
				return $value > 0 ? $value : null;
			}
		}

		return null;
	}

	private static function extractAmenityValues(string &$working, array $aliases): array
	{
		if ($aliases === []) {
			return [];
		}

		uksort(
			$aliases,
			static fn (string $a, string $b): int => strlen($b) <=> strlen($a)
		);

		$matched = [];

		foreach ($aliases as $alias => $canonical) {
			$pattern = self::buildVariantPattern($alias);

			while (preg_match($pattern, $working) === 1) {
				$canonicalValue = trim((string) $canonical);
				if ($canonicalValue !== '') {
					$matched[$canonicalValue] = true;
				}

				$working = preg_replace($pattern, ' ', $working, 1) ?? $working;
			}
		}

		$working = self::normalizeInput($working);

		return array_keys($matched);
	}

	private static function extractRawBuilderHint(string $rawQuery, ?string $rawLocation): ?string
	{
		if ($rawLocation === null) {
			return null;
		}

		$normalizedLocation = self::normalizeInput($rawLocation);
		if ($normalizedLocation === '') {
			return null;
		}

		$minLength = self::getIntConstant('BUILDER_SEARCH_MIN_LENGTH', 3);
		$tokens = preg_split('/\s+/', $normalizedLocation) ?: [];
		if ($tokens === []) {
			return null;
		}

		$queryText = strip_tags($rawQuery);

		// Prefer token that appears capitalized in original query.
		foreach ($tokens as $token) {
			$cleanToken = trim($token);
			if (self::stringLength($cleanToken) < $minLength) {
				continue;
			}

			$pattern = '/\b' . preg_quote($cleanToken, '/') . '\b/ui';
			if (preg_match($pattern, $queryText, $matches) === 1) {
				$captured = trim((string) ($matches[0] ?? ''));
				if ($captured !== '' && preg_match('/^\p{Lu}/u', $captured) === 1) {
					return $captured;
				}
			}
		}

		// Fallback for lowercase typed queries: keep first meaningful token.
		$fallbackCandidates = [];
		foreach ($tokens as $token) {
			$cleanToken = trim($token);
			if (self::stringLength($cleanToken) < $minLength) {
				continue;
			}

			$fallbackCandidates[] = $cleanToken;
		}

		if (count($fallbackCandidates) < 2) {
			return null;
		}

		$firstCandidate = $fallbackCandidates[0];
		if (function_exists('mb_convert_case')) {
			return mb_convert_case($firstCandidate, MB_CASE_TITLE, 'UTF-8');
		}

		return ucfirst($firstCandidate);
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

	private static function getIntConstant(string $constantName, int $default): int
	{
		if (!defined($constantName)) {
			return $default;
		}

		$value = constant($constantName);
		if (!is_numeric($value)) {
			return $default;
		}

		$intValue = (int) $value;
		return $intValue > 0 ? $intValue : $default;
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

	private static function stringLength(string $value): int
	{
		if (function_exists('mb_strlen')) {
			return mb_strlen($value, 'UTF-8');
		}

		return strlen($value);
	}

	private static function removeGenericPropertyTerms(string $value): string
	{
		$clean = preg_replace(
			'/\b(?:property|properties|house|houses|home|homes|residence|residential|condo|condos|villa|villas)\b/i',
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

		$clean = preg_replace(
			'/\s+(?:with|and|near|nearby|in|at|on|for|from|to|around|within|inside|of|the|by)\s*$/i',
			'',
			$clean
		) ?? $clean;

		$clean = trim($clean, " \t\n\r\0\x0B,.");

		return $clean !== '' ? $clean : null;
	}
}
