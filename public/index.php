<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$notes  = new NoteRepository(db());
$userId = current_user_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
 * Every state-changing action is POST-only and must carry a valid CSRF token
 * from an allowed origin. GET requests never change data.
 */
if ($method === 'POST') {
    if (!origin_allowed() || !csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Request rejected: invalid or missing security token. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    // Actions available to anonymous visitors.
    if ($action === 'register') {
        $error = register_user((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        flash($error ?? 'Account created. You can now sign in.');
        redirect('/');
    }

    if ($action === 'login') {
        if (attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect('/');
        }
        flash('Invalid email or password.'); // generic: no echo of input, no account enumeration
        redirect('/');
    }

    // Everything below requires a signed-in user.
    if ($userId === null) {
        http_response_code(401);
        exit('Please sign in.');
    }

    if ($action === 'logout') {
        logout_user();
        session_start();
        flash('You have been signed out.');
        redirect('/');
    }

    if ($action === 'save') {
        $title = $_POST['title'] ?? null;
        $body  = $_POST['body'] ?? null;
        $error = NoteRepository::validate($title, $body);
        if ($error === null) {
            $notes->create($userId, trim($title), $body); // owner = session user, never a form field
        }
        flash($error ?? 'Note saved.');
        redirect('/');
    }

    if ($action === 'update') {
        $id    = NoteRepository::parseId($_POST['id'] ?? null);
        $title = $_POST['title'] ?? null;
        $body  = $_POST['body'] ?? null;
        $error = NoteRepository::validate($title, $body);
        if ($id === null || ($error === null && !$notes->update($id, $userId, trim($title), $body))) {
            http_response_code(404);
            exit('Note not found.');
        }
        flash($error ?? 'Note updated.');
        redirect($error === null ? '/' : '/?edit=' . $id);
    }

    if ($action === 'delete') {
        $id = NoteRepository::parseId($_POST['id'] ?? null);
        if ($id === null || !$notes->delete($id, $userId)) {
            http_response_code(404);
            exit('Note not found.');
        }
        flash('Note deleted.');
        redirect('/');
    }

    http_response_code(400);
    exit('Unknown action.');
}

if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

// Read-only page rendering.
$editing = null;
$list    = [];
if ($userId !== null) {
    $list = $notes->listForOwner($userId);
    if (isset($_GET['edit'])) {
        $id = NoteRepository::parseId($_GET['edit']);
        $editing = $id === null ? null : $notes->findForOwner($id, $userId);
        if ($editing === null) {
            flash('Note not found.');
            redirect('/');
        }
    }
}

render('page', [
    'message' => flash(),
    'userId'  => $userId,
    'role'    => current_role(),
    'notes'   => $list,
    'editing' => $editing,
]);
