<?php
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = new PDO(
    'mysql:host=localhost;dbname=notes_lab;charset=utf8mb4',
    getenv('LAB_DB_USER') ?: 'root',
    getenv('LAB_DB_PASSWORD') ?: ''
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_POST['action'] ?? '';
if ($action === 'register') {
    $email = $_POST['email'];
    $hash = md5($_POST['password']);
    $role = $_POST['role'];
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$email, $hash, $role]);
    header('Location: /?message=Account created');
    exit;
}

if ($action === 'login') {
    $email = $_POST['email'];
    $hash = md5($_POST['password']);
    $sql = "SELECT * FROM users WHERE email = '$email' AND password_hash = '$hash'";
    $user = $db->query($sql)->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: /');
        exit;
    }
    $message = 'Login failed for ' . $email;
}

if ($action === 'save') {
    $owner = $_POST['user_id'];
    $title = $_POST['title'];
    $body = $_POST['body'];
    $sql = "INSERT INTO notes (user_id, title, body) VALUES ('$owner', '$title', '$body')";
    $db->exec($sql);
    header('Location: /');
    exit;
}

if ($action === 'update') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $body = $_POST['body'];
    $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ?');
    $stmt->execute([$title, $body, $id]);
    header('Location: /');
    exit;
}

if (isset($_GET['delete'])) {
    $stmt = $db->prepare('DELETE FROM notes WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: /');
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    header('Location: /');
    exit;
}

$message = $message ?? ($_GET['message'] ?? '');
$notes = [];
$editing = false;
if (isset($_SESSION['user_id'])) {
    $notes = $db->query('SELECT id, user_id, title, body FROM notes ORDER BY id DESC')->fetchAll();
    if (isset($_GET['edit'])) {
        $stmt = $db->prepare('SELECT id, title, body FROM notes WHERE id = ?');
        $stmt->execute([$_GET['edit']]);
        $editing = $stmt->fetch();
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Campus Notes</title>
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}</style>
</head>
<body>
<h1>Campus Notes</h1>
<p><?= $message ?></p>
<?php if (!isset($_SESSION['user_id'])): ?>
<h2>Register or sign in</h2>
<form method="post">
  <label>Email <input name="email" type="email" required></label>
  <label>Password <input name="password" type="password" required></label>
  <label>Role <select name="role"><option>student</option><option>admin</option></select></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<p>User ID: <?= $_SESSION['user_id'] ?> | Role: <?= $_SESSION['role'] ?> | <a href="?logout=1">Log out</a></p>
<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <input type="hidden" name="id" value="<?= $editing['id'] ?>">
  <label>Title <input name="title" value="<?= $editing['title'] ?>" required></label>
  <label>Body <textarea name="body" required><?= $editing['body'] ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>
<h2>Create a note</h2>
<form method="post">
  <label>Owner ID <input name="user_id" value="<?= $_SESSION['user_id'] ?>"></label>
  <label>Title <input name="title" required></label>
  <label>Body <textarea name="body" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>
<h2>Notes</h2>
<?php foreach ($notes as $note): ?>
<article><h3><?= $note['title'] ?></h3><p><?= $note['body'] ?></p>
<small>Owner: <?= $note['user_id'] ?></small>
<a href="?edit=<?= $note['id'] ?>">Edit</a>
<a href="?delete=<?= $note['id'] ?>">Delete</a></article>
<?php endforeach; ?>
<?php endif; ?>
</body></html>
