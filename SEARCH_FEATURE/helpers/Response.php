<?php
declare(strict_types=1);

final class Response
{
	private function __construct()
	{
	}

	public static function success(
		array $data = [],
		array $queryInterpreted = [],
		bool $isRelaxed = false,
		array $pagination = [],
		int $httpStatusCode = 200,
		array $extra = []
	): void {
		$payload = [
			'status' => 'success',
			'query_interpreted' => $queryInterpreted,
			'is_relaxed' => $isRelaxed,
			'pagination' => $pagination,
			'data' => $data,
		];

		if ($extra !== []) {
			$payload = array_merge($payload, $extra);
		}

		self::sendJson($payload, $httpStatusCode);
	}

	public static function error(string $message, int $httpStatusCode = 400): void
	{
		self::sendJson(
			[
				'status' => 'error',
				'message' => $message,
				'code' => $httpStatusCode,
			],
			$httpStatusCode
		);
	}
	private static function sendJson(array $payload, int $httpStatusCode): void
	{
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		http_response_code($httpStatusCode);

		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
}
