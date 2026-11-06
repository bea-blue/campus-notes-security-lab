<?php
session_start();
ini_set('display_errors', '1');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

$db = new PDO(
    'mysql:host=localhost;dbname=notes_lab;charset=utf8mb4',
    getenv('LAB_DB_USER') ?: 'root',
    getenv('LAB_DB_PASSWORD') ?: ''
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $owner = $_GET['user_id'] ?? '';
    $stmt = $db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ?');
    $stmt->execute([$owner]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ?');
    $stmt->execute([$data['title'], $data['body'], $data['id']]);
    echo json_encode(['updated' => $stmt->rowCount()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not supported']);
