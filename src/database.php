<?php
declare(strict_types=1);

/**
 * Single place that opens the database connection.
 * Credentials must be supplied through the environment; there is no root/empty
 * password fallback.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real server-side prepared statements
    ];

    $dsn = env('LAB_DB_DSN');
    if ($dsn !== null) {
        $pdo = new PDO($dsn, env('LAB_DB_USER'), env('LAB_DB_PASSWORD'), $options);
        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }

    $user = env('LAB_DB_USER');
    $pass = env('LAB_DB_PASSWORD');
    if ($user === null || $pass === null) {
        throw new RuntimeException('Database credentials are not configured (LAB_DB_USER / LAB_DB_PASSWORD).');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        env('LAB_DB_HOST', '127.0.0.1'),
        env('LAB_DB_NAME', 'notes_lab')
    );
    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
