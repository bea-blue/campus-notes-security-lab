<?php
declare(strict_types=1);

/** Per-session synchronizer token for every state-changing request. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $submitted): bool
{
    return is_string($submitted)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Origin/Referer check as a second layer. Browsers send Origin on POST; if it
 * is present it must be in the allow-list. If only Referer is present its
 * origin must match. Requests with neither (e.g. curl) still need the token.
 */
function origin_allowed(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null) {
        return in_array($origin, allowed_origins(), true);
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    if ($referer !== null) {
        $p = parse_url($referer);
        if (!isset($p['scheme'], $p['host'])) {
            return false;
        }
        $refOrigin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        return in_array($refOrigin, allowed_origins(), true);
    }
    return true;
}
