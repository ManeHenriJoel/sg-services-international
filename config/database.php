<?php
declare(strict_types=1);

define('STORAGE_PATH', getenv('APP_STORAGE_PATH') ?: dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'premierapp-storage');

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (getenv('APP_ENV') === 'production' && (!getenv('DB_HOST') || !getenv('DB_NAME') || !getenv('DB_USER') || getenv('DB_PASSWORD') === false || !getenv('APP_STORAGE_PATH'))) {
        throw new RuntimeException('Les variables DB_* et APP_STORAGE_PATH doivent être définies en production.');
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'sg_services';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}