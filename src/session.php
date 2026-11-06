<?php
declare(strict_types=1);

/**
 * Start the session with hardened cookie settings.
 * - strict mode: PHP refuses session IDs it did not create (stops fixation
 *   with an attacker-chosen ID)
 * - cookies only, no IDs in URLs
 * - HttpOnly (no JavaScript access), SameSite=Lax, Secure when served over HTTPS
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('campus_notes_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout: 30 minutes without activity ends the session.
    $now = time();
    if (isset($_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > 1800) {
        destroy_session();
        session_start();
    }
    $_SESSION['last_seen'] = $now;
}

/** Fully end the session: clear data, expire the cookie, destroy server storage. */
function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}
