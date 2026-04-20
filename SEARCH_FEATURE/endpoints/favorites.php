<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
	http_response_code(204);
	exit;
}

$baseDir = dirname(__DIR__);

require_once $baseDir . '/config/database.php';
require_once $baseDir . '/config/constants.php';
require_once $baseDir . '/helpers/Response.php';
require_once $baseDir . '/helpers/Sanitizer.php';
require_once $baseDir . '/services/FavoritesService.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
	Response::error('Method not allowed', 405);
}

$sanitizeSessionId = static function (mixed $value): string {
	if (!is_scalar($value)) {
		return '';
	}

	$session = trim((string) $value);
	$session = preg_replace('/\s+/', ' ', $session) ?? '';

	if (function_exists('mb_substr')) {
		$session = mb_substr($session, 0, 128);
	} else {
		$session = substr($session, 0, 128);
	}

	return $session;
};

$readJsonBody = static function (): array {
	$rawInput = file_get_contents('php://input');
	$decoded = json_decode($rawInput ?: '[]', true);
	return is_array($decoded) ? $decoded : [];
};

try {
	$payload = ($method === 'GET') ? $_GET : $readJsonBody();
	$action = strtolower(trim((string) ($payload['action'] ?? ($method === 'GET' ? 'list' : 'toggle'))));
	$sessionId = $sanitizeSessionId($payload['session_id'] ?? '');

	if ($sessionId === '') {
		Response::error('session_id is required', 400);
	}

	$projectId = Sanitizer::sanitizeInt($payload['project_id'] ?? 0);
	$flatTypeRaw = isset($payload['flat_type']) && is_scalar($payload['flat_type'])
		? trim((string) $payload['flat_type'])
		: null;
	$flatType = $flatTypeRaw !== null && $flatTypeRaw !== '' ? $flatTypeRaw : null;

	$pdo = Database::getInstance();
	$favoritesService = new FavoritesService($pdo);

	switch ($action) {
		case 'list': {
			$pagination = Sanitizer::validatePagination($payload['page'] ?? 1, $payload['limit'] ?? DEFAULT_LIMIT);
			$page = (int) $pagination['page'];
			$limit = (int) $pagination['limit'];

			$result = $favoritesService->getFavorites($sessionId, $page, $limit);
			$results = isset($result['results']) && is_array($result['results']) ? $result['results'] : [];
			$paginationPayload = isset($result['pagination']) && is_array($result['pagination'])
				? $result['pagination']
				: [
					'current_page' => $page,
					'per_page' => $limit,
					'total_count' => 0,
					'total_pages' => 0,
				];

			Response::success(
				$results,
				[],
				false,
				$paginationPayload,
				200,
				[
					'total_count' => (int) ($result['total_count'] ?? 0),
				]
			);
		}

		case 'add': {
			if ($projectId <= 0) {
				Response::error('project_id is required', 400);
			}

			$result = $favoritesService->addFavorite($sessionId, $projectId, $flatType);
			Response::success($result);
		}

		case 'remove': {
			if ($projectId <= 0) {
				Response::error('project_id is required', 400);
			}

			$result = $favoritesService->removeFavorite($sessionId, $projectId);
			Response::success($result);
		}

		case 'toggle': {
			if ($projectId <= 0) {
				Response::error('project_id is required', 400);
			}

			$result = $favoritesService->toggleFavorite($sessionId, $projectId, $flatType);
			Response::success($result);
		}

		case 'status':
		case 'is_favorite': {
			if ($projectId <= 0) {
				Response::error('project_id is required', 400);
			}

			$isFavorite = $favoritesService->isFavorite($sessionId, $projectId);
			Response::success([
				'project_id' => $projectId,
				'is_favorite' => $isFavorite,
			]);
		}

		case 'ids': {
			$ids = $favoritesService->getFavoriteIds($sessionId);
			Response::success([
				'project_ids' => $ids,
				'total_count' => count($ids),
			]);
		}

		case 'count': {
			$total = $favoritesService->getFavoriteCount($sessionId);
			Response::success([
				'total_count' => $total,
			]);
		}

		default:
			Response::error('Unsupported action', 400);
	}
} catch (Throwable $exception) {
	error_log('Favorites endpoint failed: ' . $exception->getMessage());
	Response::error('Something went wrong', 500);
}
