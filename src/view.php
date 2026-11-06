<?php
declare(strict_types=1);

/** Escape for HTML text and quoted attribute contexts. Use for every dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Render a template from templates/ with the given variables. */
function render(string $template, array $vars = []): void
{
    header('Content-Type: text/html; charset=utf-8');
    // No inline scripts or styles are used, so a strict CSP is possible.
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    extract($vars, EXTR_SKIP);
    require dirname(__DIR__) . '/templates/' . basename($template) . '.php';
}

/** One-time message stored in the session instead of a reflected ?message= parameter. */
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function redirect(string $to = '/'): never
{
    header('Location: ' . $to, true, 303);
    exit;
}
