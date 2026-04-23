<?php

declare(strict_types=1);

const DB_PORT = '3306';

function getDatabaseConfig(): array
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    if (in_array($host, ['streaming.reborxstore.com', 'streaming.reboxstore.com'], true)) {
        return [
            'host' => 'srv1999.hstgr.io',
            'port' => DB_PORT,
            'name' => 'u680460687_admincorreos',
            'user' => 'u680460687_admincorreos',
            'pass' => 'n7!/$l1K#',
        ];
    }

    return [
        'host' => '127.0.0.1',
        'port' => DB_PORT,
        'name' => 'admincorreos',
        'user' => 'root',
        'pass' => '',
    ];
}

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = getDatabaseConfig();

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']);

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}