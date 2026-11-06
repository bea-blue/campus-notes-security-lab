<?php
declare(strict_types=1);

/*
 * Configuration comes only from environment variables. Nothing secret is
 * committed and there is no fallback to a privileged account such as root.
 *
 *   LAB_DB_USER, LAB_DB_PASSWORD  required for MySQL
 *   LAB_DB_HOST                   optional, default 127.0.0.1
 *   LAB_DB_NAME                   optional, default notes_lab
 *   LAB_DB_DSN                    optional full PDO DSN override (used for the
 *                                 automated SQLite test run in tests/)
 *   LAB_ALLOWED_ORIGINS           optional, comma-separated list of origins
 *                                 allowed to send state-changing requests.
 *                                 Defaults to the request's own origin.
 *   LAB_DEBUG                     never set this outside a private dev box
 */

function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Origin of the current request as the server sees it, e.g. http://127.0.0.1:8000 */
function own_origin(): string
{
    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** @return string[] */
function allowed_origins(): array
{
    $list = env('LAB_ALLOWED_ORIGINS');
    if ($list === null) {
        return [own_origin()];
    }
    return array_values(array_filter(array_map('trim', explode(',', $list))));
}
