<?php
declare(strict_types=1);

/*
 * Shared start-up for every entry point (public/index.php, public/api.php).
 * Order matters: error handling first, then session hardening, then the
 * helpers that depend on both.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/errors.php';
require __DIR__ . '/database.php';
require __DIR__ . '/session.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/NoteRepository.php';
require __DIR__ . '/view.php';

configure_error_handling();
start_secure_session();

// Baseline response headers for both HTML and JSON responses.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
