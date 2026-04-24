<?php
declare(strict_types=1);

final class Database
{
	private static ?PDO $instance = null;

	private function __construct()
	{
	}

	private function __clone()
	{
	}

	public static function getInstance(): PDO
	{
		if (self::$instance instanceof PDO) {
			return self::$instance;
		}

		$host = 'localhost';
		$database = 'real_estate_db';
		$username = 'root';
		$password = '';
		$charset = 'utf8mb4';

		$dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

		self::$instance = new PDO(
			$dsn,
			$username,
			$password,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			]
		);

		
		return self::$instance;
	}
}
