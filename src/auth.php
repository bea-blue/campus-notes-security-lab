<?php
declare(strict_types=1);

const PASSWORD_MIN_LENGTH = 12;
const PASSWORD_MAX_BYTES  = 72; // bcrypt ignores bytes after 72

/**
 * Create a student account. The role is fixed on the server; nothing the
 * client sends can choose it.
 *
 * @return string|null error message, or null on success
 */
function register_user(string $email, string $password): ?string
{
    $email = trim($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
        return 'Enter a valid email address.';
    }
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH || strlen($password) > PASSWORD_MAX_BYTES) {
        return 'Password must be ' . PASSWORD_MIN_LENGTH . ' to ' . PASSWORD_MAX_BYTES . ' characters.';
    }

    $stmt = db()->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'student']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // unique email constraint
            return 'Could not create that account.';
        }
        throw $e;
    }
    return null;
}

/** @return bool true when the email/password pair is valid and the session is now logged in */
function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    // Verify against a dummy hash when the user does not exist so the response
    // time does not reveal which emails are registered.
    $hash = $user['password_hash'] ?? '$2y$10$FVQU4i3Vi8fT5jhSt2YtsOKGUzq8NMnGovmwfjldYSmkYeTnBRB1K';
    if (!password_verify($password, $hash) || !$user) {
        return false;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    // New session ID on privilege change prevents session fixation.
    session_regenerate_id(true);
    $_SESSION = [
        'user_id'    => (int) $user['id'],
        'role'       => (string) $user['role'],
        'last_seen'  => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    return true;
}

function logout_user(): void
{
    destroy_session();
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_role(): string
{
    return (string) ($_SESSION['role'] ?? '');
}
