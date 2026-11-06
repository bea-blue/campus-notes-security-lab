<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// No Access-Control-Allow-* headers are sent: the API is same-origin only, so
// browsers block other sites from reading responses. The old code reflected
// any Origin together with Allow-Credentials, which let any site read and
// change a logged-in user's notes.

function json_out(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Reject cross-origin requests up front (including CORS preflights).
if (!origin_allowed()) {
    json_out(403, ['error' => 'Origin not allowed']);
}
if ($method === 'OPTIONS') {
    header('Allow: GET, POST');
    json_out(405, ['error' => 'Method not supported']);
}

// Authentication: every API call needs a signed-in session.
$userId = current_user_id();
if ($userId === null) {
    json_out(401, ['error' => 'Authentication required']);
}

$notes = new NoteRepository(db());

if ($method === 'GET') {
    // The owner always comes from the session. user_id is accepted only for
    // backward compatibility and must match the signed-in user.
    if (isset($_GET['user_id']) && NoteRepository::parseId($_GET['user_id']) !== $userId) {
        json_out(403, ['error' => 'Forbidden']);
    }
    json_out(200, ['notes' => $notes->listForOwner($userId)]);
}

if ($method === 'POST') {
    // CSRF: a custom header cannot be sent cross-site without a CORS preflight
    // (which is refused above), and its value must match the session token.
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_out(403, ['error' => 'Invalid CSRF token']);
    }

    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        json_out(415, ['error' => 'Content-Type must be application/json']);
    }

    $raw = file_get_contents('php://input', false, null, 0, 65536);
    try {
        $data = json_decode((string) $raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_out(400, ['error' => 'Malformed JSON']);
    }
    if (!is_array($data) || $data === [] || array_keys($data) === range(0, count($data) - 1)) {
        json_out(400, ['error' => 'Request body must be a JSON object']);
    }

    $unknown = array_diff(array_keys($data), ['id', 'title', 'body']);
    if ($unknown) {
        json_out(422, ['error' => 'Unexpected fields']); // e.g. user_id cannot be changed
    }

    $id = NoteRepository::parseId($data['id'] ?? null);
    if ($id === null) {
        json_out(422, ['error' => 'id must be a positive integer']);
    }
    $error = NoteRepository::validate($data['title'] ?? null, $data['body'] ?? null);
    if ($error !== null) {
        json_out(422, ['error' => $error]);
    }

    // Per-record authorization: the update only matches notes owned by the session user.
    // Not-found and not-yours return the same 404 so IDs of other users' notes are not confirmed.
    if (!$notes->update($id, $userId, trim($data['title']), $data['body'])) {
        json_out(404, ['error' => 'Note not found']);
    }
    json_out(200, ['updated' => 1]);
}

header('Allow: GET, POST');
json_out(405, ['error' => 'Method not supported']);
