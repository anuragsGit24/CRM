<?php
declare(strict_types=1);

final class PostQueryParser
{
	public static function parse(string $rawQuery): array
	{
		$working = self::normalizeInput($rawQuery);
		$parsed = self::defaultPayload();

		if ($working === '') {
			return $parsed;
		}

		// 1) Post type
		$parsed['post_type'] = self::extractFirstMappedIntValue($working, self::getMapConstant('POST_TYPE_MAP'));

		// 2) Post for
		$parsed['post_for'] = self::extractFirstMappedIntValue($working, self::getMapConstant('POST_FOR_MAP'));

		// 3) Flat type (multi-select)
		$parsed['flat_type_ids'] = self::extractMultiMappedIntValues($working, self::getMapConstant('POST_FLAT_TYPE_MAP'));

		// 4) Property type
		$parsed['flat_property_type'] = self::extractFirstMappedIntValue($working, self::getMapConstant('POST_PROPERTY_TYPE_MAP'));

		// 5) Budget
		self::extractBudgets($working, $parsed);

		// 6) Carpet area
		self::extractCarpetRange($working, $parsed);

		// 7) Remaining text becomes raw location
		$remaining = self::normalizeInput($working);
		$parsed['raw_location'] = self::normalizeRawLocation($remaining);

		return $parsed;
	}

	private static function defaultPayload(): array
	{
		return [
			'post_type' => null,
			'post_for' => null,
			'flat_type_ids' => [],
			'flat_property_type' => null,
			'min_budget' => null,
			'max_budget' => null,
			'min_carpet' => null,
			'max_carpet' => null,
			'raw_location' => null,
			'project_name_hint' => null,
		];
	}

	private static function extractFirstMappedIntValue(string &$working, array $map): ?int
	{
		if ($map === []) {
			return null;
		}

		$sortedMap = self::sortMapByKeyLength($map);
		$best = null;

		foreach ($sortedMap as $variant => $dbValue) {
			$value = (int) $dbValue;
			if ($value <= 0) {
				continue;
			}

			$pattern = self::buildVariantPattern((string) $variant);
			if (preg_match($pattern, $working, $matches, PREG_OFFSET_CAPTURE) !== 1) {
				continue;
			}

			$matchedText = (string) ($matches[0][0] ?? '');
			$offset = (int) ($matches[0][1] ?? -1);
			if ($matchedText === '' || $offset < 0) {
				continue;
			}

			$candidate = [
				'offset' => $offset,
				'length' => strlen($matchedText),
				'value' => $value,
			];

			if ($best === null || $candidate['offset'] < $best['offset'] || ($candidate['offset'] === $best['offset'] && $candidate['length'] > $best['length'])) {
				$best = $candidate;
			}
		}

		if ($best === null) {
			return null;
		}

		$working = substr_replace($working, str_repeat(' ', $best['length']), $best['offset'], $best['length']);

		return (int) $best['value'];
	}

	private static function extractMultiMappedIntValues(string &$working, array $map): array
	{
		if ($map === []) {
			return [];
		}

		$sortedMap = self::sortMapByKeyLength($map);
		$capturedMatches = [];

		foreach ($sortedMap as $variant => $dbValue) {
			$value = (int) $dbValue;
			if ($value <= 0) {
				continue;
			}

			$pattern = self::buildVariantPattern((string) $variant);

			while (preg_match($pattern, $working, $matches, PREG_OFFSET_CAPTURE) === 1) {
				$matchedText = (string) ($matches[0][0] ?? '');
				$offset = (int) ($matches[0][1] ?? -1);
				if ($matchedText === '' || $offset < 0) {
					break;
				}

				$capturedMatches[] = [
					'offset' => $offset,
					'value' => $value,
				];

				$working = substr_replace($working, str_repeat(' ', strlen($matchedText)), $offset, strlen($matchedText));
			}
		}

		if ($capturedMatches === []) {
			return [];
		}

		usort(
			$capturedMatches,
			static fn (array $a, array $b): int => ((int) $a['offset']) <=> ((int) $b['offset'])
		);

		$ordered = [];
		foreach ($capturedMatches as $item) {
			$value = (int) $item['value'];
			if (!in_array($value, $ordered, true)) {
				$ordered[] = $value;
			}
		}

		return $ordered;
	}

	private static function extractBudgets(string &$working, array &$parsed): void
	{
		$pattern = '/\b(?:(under|below|upto|up\s*to|max|above|minimum|min|starting)\s*)?(\d+(?:[.,]\d+)?)\s*(k|lakh|l|cr)?\b/i';
		$minContext = ['above', 'minimum', 'min', 'starting'];
		$carpetAsBudgetThreshold = 5000;
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

			// Keep normal sqft values as carpet. Treat area values above threshold as budget.
			$tokenAsAreaPattern = '/\\b' . preg_quote($fullToken, '/') . '\\s*(?:sq\\.?\\s*ft|sqft|sq\\s*ft|square\\s*feet|sft)\\b/i';
			$areaMatch = [];
			$isAreaPhrase = preg_match($tokenAsAreaPattern, $working, $areaMatch) === 1;
			if ($isAreaPhrase && $amount <= $carpetAsBudgetThreshold) {
				continue;
			}

			// Ignore tiny bare numbers so location-like tokens are not treated as budgets.
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
				if ($parsed['max_budget'] === null) {
					$parsed['max_budget'] = $normalizedValue;
				} else {
					$parsed['max_budget'] = min((int) $parsed['max_budget'], $normalizedValue);
				}
			}

			$consumedTokens[] = $isAreaPhrase && isset($areaMatch[0])
				? trim((string) $areaMatch[0])
				: $fullToken;
		}

		foreach ($consumedTokens as $token) {
			if ($token === '') {
				continue;
			}

			$working = preg_replace('/' . preg_quote($token, '/') . '/i', ' ', $working, 1) ?? $working;
		}
	}

	private static function extractCarpetRange(string &$working, array &$parsed): void
	{
		$pattern = '/\b(?:(above|over|minimum|min|at\s*least|below|under|max|maximum|upto|up\s*to)\s*)?(\d{2,5})(?:\s*(?:-|to)\s*(\d{2,5}))?\s*(sq\.?\s*ft|sqft|sq\s*ft|square\s*feet|sft)\b/i';
		$carpetAsBudgetThreshold = 5000;

		$matchCount = preg_match_all($pattern, $working, $matches, PREG_SET_ORDER);
		if ($matchCount === false || $matchCount === 0) {
			return;
		}

		$consumedTokens = [];

		foreach ($matches as $match) {
			$fullToken = trim((string) ($match[0] ?? ''));
			$context = self::normalizeInput((string) ($match[1] ?? ''));
			$first = (int) ($match[2] ?? 0);
			$second = (int) ($match[3] ?? 0);

			if ($fullToken === '' || $first <= 0) {
				continue;
			}

			if ($first > $carpetAsBudgetThreshold || ($second > 0 && $second > $carpetAsBudgetThreshold)) {
				continue;
			}

			if ($second > 0) {
				$low = min($first, $second);
				$high = max($first, $second);
				$parsed['min_carpet'] = $parsed['min_carpet'] === null ? $low : max((int) $parsed['min_carpet'], $low);
				$parsed['max_carpet'] = $parsed['max_carpet'] === null ? $high : min((int) $parsed['max_carpet'], $high);
				$consumedTokens[] = $fullToken;
				continue;
			}

			if (in_array($context, ['above', 'over', 'minimum', 'min', 'at least'], true)) {
				$parsed['min_carpet'] = $parsed['min_carpet'] === null ? $first : max((int) $parsed['min_carpet'], $first);
			} elseif (in_array($context, ['below', 'under', 'max', 'maximum', 'upto', 'up to'], true)) {
				$parsed['max_carpet'] = $parsed['max_carpet'] === null ? $first : min((int) $parsed['max_carpet'], $first);
			} else {
				$parsed['min_carpet'] = $parsed['min_carpet'] === null ? $first : max((int) $parsed['min_carpet'], $first);
				$parsed['max_carpet'] = $parsed['max_carpet'] === null ? $first : min((int) $parsed['max_carpet'], $first);
			}

			$consumedTokens[] = $fullToken;
		}

		foreach ($consumedTokens as $token) {
			if ($token === '') {
				continue;
			}

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

	private static function sortMapByKeyLength(array $map): array
	{
		$keys = array_map(static fn (mixed $key): string => (string) $key, array_keys($map));

		usort(
			$keys,
			static function (string $a, string $b): int {
				$lengthDiff = strlen($b) <=> strlen($a);
				if ($lengthDiff !== 0) {
					return $lengthDiff;
				}

				return strcmp($a, $b);
			}
		);

		$sorted = [];
		foreach ($keys as $key) {
			if (array_key_exists($key, $map)) {
				$sorted[$key] = $map[$key];
			}
		}

		return $sorted;
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

	private static function normalizeRawLocation(string $value): ?string
	{
		if ($value === '') {
			return null;
		}

		$clean = preg_replace(
			'/^(?:(?:in|at|on|for|from|to|near|around|within|inside|of|the|and|by)\s+)+/i',
			'',
			$value
		) ?? $value;

		$clean = preg_replace(
			'/\s+(?:(?:in|at|on|for|from|to|near|around|within|inside|of|the|and|by)\s*)+$/i',
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
