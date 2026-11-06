<?php
declare(strict_types=1);

/**
 * Never show PHP errors or exceptions to the client. Log a short, sanitized
 * line instead (no SQL, no parameters, no stack trace in the response).
 */
function configure_error_handling(): void
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        // Turn warnings/notices into exceptions so they cannot leak or be ignored.
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $e): void {
        $ref = bin2hex(random_bytes(4));
        // Class, file and line are enough to debug; the message of a PDOException
        // can contain SQL or connection details, so it is not logged verbatim.
        error_log(sprintf('[campus-notes %s] %s at %s:%d', $ref, get_class($e), basename($e->getFile()), $e->getLine()));
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'api.php')) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => 'Internal server error', 'ref' => $ref]);
        } else {
            echo 'Something went wrong. Please try again later. (ref ' . $ref . ')';
        }
    });
}
