<?php
declare(strict_types=1);

/**
 * All note queries live here. Every method takes the owner ID from the
 * server-side session (never from the request) and every query is
 * parameterised and filtered by user_id, so a user can only see or change
 * their own notes.
 */
final class NoteRepository
{
    public const TITLE_MAX = 120;
    public const BODY_MAX  = 10000;

    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array{id:int|string,title:string,body:string}> */
    public function listForOwner(int $ownerId): array
    {
        $stmt = $this->db->prepare('SELECT id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll();
    }

    /** @return array{id:int|string,title:string,body:string}|null */
    public function findForOwner(int $id, int $ownerId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, title, body FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $ownerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $ownerId, string $title, string $body): int
    {
        $stmt = $this->db->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
        $stmt->execute([$ownerId, $title, $body]);
        return (int) $this->db->lastInsertId();
    }

    /** @return bool false when the note does not exist or belongs to someone else */
    public function update(int $id, int $ownerId, string $title, string $body): bool
    {
        if ($this->findForOwner($id, $ownerId) === null) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $body, $id, $ownerId]);
        return true;
    }

    public function delete(int $id, int $ownerId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $ownerId]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Shared validation for web and API input.
     * @return string|null error message, or null when valid
     */
    public static function validate(mixed $title, mixed $body): ?string
    {
        if (!is_string($title) || !is_string($body)) {
            return 'Title and body must be text.';
        }
        $t = trim($title);
        if ($t === '' || mb_strlen($t) > self::TITLE_MAX) {
            return 'Title is required and must be at most ' . self::TITLE_MAX . ' characters.';
        }
        if (trim($body) === '' || mb_strlen($body) > self::BODY_MAX) {
            return 'Body is required and must be at most ' . self::BODY_MAX . ' characters.';
        }
        return null;
    }

    /** Parse a positive integer ID; returns null for anything else ("1 OR 1=1", "-3", "abc"). */
    public static function parseId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value) && strlen($value) <= 10) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }
        return null;
    }
}
