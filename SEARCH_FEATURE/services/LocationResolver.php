<?php
declare(strict_types=1);

final class LocationResolver
{
private PDO $pdo;
private const BROAD_CITY_TERMS = ['mumbai', 'bombay', 'mumbai city', 'greater mumbai'];
private const MAX_LOCATION_MATCHES = 12;
private const DIRECTION_MAP = [
'e' => 'east',
'east' => 'east',
'w' => 'west',
'west' => 'west',
'n' => 'north',
'north' => 'north',
's' => 'south',
'south' => 'south',
];

public function __construct(PDO $pdo)
{
$this->pdo = $pdo;
}

public function resolve(string $rawLocation): ?int
{
$ids = $this->resolveIds($rawLocation);
return $ids[0] ?? null;
}

public function resolveIds(string $rawLocation): array
{
$normalized = self::normalizeRawLocation($rawLocation);
if ($normalized === '') {
return [];
}

if ($this->isBroadCityQuery($normalized)) {
return [];
}

$aliasMatch = $this->findExactAliasMatch($normalized);
if ($aliasMatch !== null) {
return [$aliasMatch];
}

$exactMatch = $this->findExactNameMatch($normalized);
if ($exactMatch !== null) {
return [$exactMatch];
}

$directional = self::extractDirectionalParts($normalized);
if ($directional !== null) {
$directionalMatches = $this->findDirectionalNameMatches($directional['base'], $directional['direction']);
if ($directionalMatches !== []) {
return $directionalMatches;
}

// Avoid mapping "east" queries to a different direction like west.
return [];
}

$familyMatches = $this->findBaseFamilyNameMatches($normalized);
if ($familyMatches !== []) {
return $familyMatches;
}

$prefixMatches = $this->findNamePrefixMatches($normalized, self::MAX_LOCATION_MATCHES);
if ($prefixMatches !== []) {
return $prefixMatches;
}

if (str_contains($normalized, ' ')) {
$containsMatch = $this->findBestNameContainsMatch($normalized);
if ($containsMatch !== null) {
return [$containsMatch];
}
}

return [];
}

public function isBroadCityQuery(string $rawLocation): bool
{
$normalized = self::normalizeRawLocation($rawLocation);
if ($normalized === '') {
return false;
}

return in_array($normalized, self::BROAD_CITY_TERMS, true);
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

private static function normalizeRawLocation(string $rawLocation): string
{
$normalized = trim($rawLocation);
if ($normalized === '') {
return '';
}

$normalized = preg_replace(
'/^(?:in|at|on|for|from|to|near|around|within|inside|of|the)\s+/i',
'',
$normalized
) ?? $normalized;

$normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

return trim($normalized, " \t\n\r\0\x0B,.");
}

private function findExactAliasMatch(string $value): ?int
{
$sql = 'SELECT la.location_id
FROM location_aliases la
INNER JOIN location l ON l.id = la.location_id
WHERE l.status = 1
  AND LOWER(la.alias) = LOWER(:alias)
LIMIT 1';

$stmt = $this->pdo->prepare($sql);
$stmt->execute([':alias' => $value]);
$match = $stmt->fetchColumn();

return $match !== false ? (int) $match : null;
}

private function findExactNameMatch(string $value): ?int
{
$sql = 'SELECT id FROM location WHERE status = 1 AND LOWER(name) = LOWER(:name) LIMIT 1';
$stmt = $this->pdo->prepare($sql);
$stmt->execute([':name' => $value]);
$match = $stmt->fetchColumn();

return $match !== false ? (int) $match : null;
}

private function findBaseFamilyNameMatches(string $value): array
{
$normalized = self::normalizeRawLocation($value);
if ($normalized === '') {
return [];
}

$sql = "SELECT id
FROM location
WHERE status = 1
  AND (
        LOWER(name) = LOWER(:exact_match)
OR LOWER(name) LIKE LOWER(:family_prefix) ESCAPE '\\\\'
  )
ORDER BY
        CASE WHEN LOWER(name) = LOWER(:exact_order) THEN 0 ELSE 1 END,
  LENGTH(name) ASC,
  name ASC
LIMIT " . self::MAX_LOCATION_MATCHES;

$stmt = $this->pdo->prepare($sql);
$stmt->execute([
      ':exact_match' => $normalized,
      ':exact_order' => $normalized,
':family_prefix' => self::escapeLike($normalized) . ' %',
]);

return self::normalizeIdList($stmt->fetchAll(PDO::FETCH_COLUMN));
}

private function findDirectionalNameMatches(string $base, string $direction): array
{
$baseNormalized = self::normalizeRawLocation($base);
$directionNormalized = self::canonicalDirection($direction);

if ($baseNormalized === '' || $directionNormalized === null) {
return [];
}

$baseEscaped = self::escapeLike($baseNormalized);
$directionEscaped = self::escapeLike($directionNormalized);
$preferred = $baseNormalized . ' ' . $directionNormalized;

$sql = "SELECT id
FROM location
WHERE status = 1
  AND LOWER(name) LIKE LOWER(:base_contains) ESCAPE '\\\\'
  AND LOWER(name) LIKE LOWER(:direction_contains) ESCAPE '\\\\'
ORDER BY
  CASE
WHEN LOWER(name) = LOWER(:preferred_exact) THEN 0
WHEN LOWER(name) LIKE LOWER(:preferred_prefix) ESCAPE '\\\\' THEN 1
WHEN LOWER(name) LIKE LOWER(:base_prefix) ESCAPE '\\\\' THEN 2
ELSE 3
  END,
  LENGTH(name) ASC,
  name ASC
LIMIT " . self::MAX_LOCATION_MATCHES;

$stmt = $this->pdo->prepare($sql);
$stmt->execute([
':base_contains' => '%' . $baseEscaped . '%',
':direction_contains' => '%' . $directionEscaped . '%',
':preferred_exact' => $preferred,
':preferred_prefix' => self::escapeLike($preferred) . '%',
':base_prefix' => $baseEscaped . '%',
]);

return self::normalizeIdList($stmt->fetchAll(PDO::FETCH_COLUMN));
}

private function findNamePrefixMatches(string $value, int $limit = 1): array
{
$normalized = self::normalizeRawLocation($value);
if ($normalized === '') {
return [];
}

$safeLimit = max(1, min($limit, self::MAX_LOCATION_MATCHES));

$sql = "SELECT id
FROM location
WHERE status = 1
  AND LOWER(name) LIKE LOWER(:prefix) ESCAPE '\\\\'
ORDER BY LENGTH(name) ASC, name ASC
LIMIT {$safeLimit}";

$stmt = $this->pdo->prepare($sql);
$stmt->execute([
':prefix' => self::escapeLike($normalized) . '%',
]);

return self::normalizeIdList($stmt->fetchAll(PDO::FETCH_COLUMN));
}

private function findBestNameContainsMatch(string $value): ?int
{
$normalized = self::normalizeRawLocation($value);
if ($normalized === '') {
return null;
}

$sql = "SELECT id
FROM location
WHERE status = 1
  AND LOWER(name) LIKE LOWER(:contains) ESCAPE '\\\\'
ORDER BY LOCATE(LOWER(:term), LOWER(name)) ASC, LENGTH(name) ASC, name ASC
LIMIT 1";

$stmt = $this->pdo->prepare($sql);
$stmt->execute([
':contains' => '%' . self::escapeLike($normalized) . '%',
':term' => $normalized,
]);
$match = $stmt->fetchColumn();

return $match !== false ? (int) $match : null;
}

private static function extractDirectionalParts(string $value): ?array
{
$normalized = self::normalizeRawLocation($value);
if ($normalized === '') {
return null;
}

$tokens = preg_split('/\s+/', $normalized) ?: [];
if ($tokens === []) {
return null;
}

$direction = null;
$baseTokens = [];
$ignored = ['of', 'in', 'at', 'near', 'around', 'to', 'from', 'the'];

foreach ($tokens as $token) {
$word = trim($token);
if ($word === '') {
continue;
}

$canonicalDirection = self::canonicalDirection($word);
if ($canonicalDirection !== null) {
if ($direction === null) {
$direction = $canonicalDirection;
}
continue;
}

if (in_array($word, $ignored, true)) {
continue;
}

$baseTokens[] = $word;
}

if ($direction === null || $baseTokens === []) {
return null;
}

$base = implode(' ', $baseTokens);
return $base !== ''
? ['base' => $base, 'direction' => $direction]
: null;
}

private static function canonicalDirection(string $value): ?string
{
$key = trim(strtolower($value));
return self::DIRECTION_MAP[$key] ?? null;
}

private static function normalizeIdList(array $values): array
{
$unique = [];
foreach ($values as $value) {
$id = (int) $value;
if ($id > 0) {
$unique[$id] = true;
}
}

return array_keys($unique);
}

private static function escapeLike(string $value): string
{
return str_replace(
['\\', '%', '_'],
['\\\\', '\\%', '\\_'],
$value
);
}
}