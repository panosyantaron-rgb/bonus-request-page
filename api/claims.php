<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// db() is called inside each branch, AFTER the auth guard, so an unauthenticated
// request never opens a database connection.
$method = $_SERVER['REQUEST_METHOD'];

// ---- Save a claim (PUBLIC — players are not logged in) ----
if ($method === 'POST') {
    $pdo = db();
    $d = body();

    $username = trim($d['username'] ?? '');
    $userId   = trim($d['userId'] ?? '');
    $title    = trim($d['title'] ?? '');
    $amount   = trim($d['amount'] ?? '');

    if ($username === '' || $userId === '') fail('username and userId are required');
    if ($title === '')                      fail('bonus title is required');

    $stmt = $pdo->prepare(
        "INSERT INTO claims (username, user_id, bonus_title, bonus_amount, bonus_description)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$username, $userId, $title, $amount, trim($d['description'] ?? '')]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit;
}

// ---- List claims, newest first (ADMIN ONLY — this is player data) ----
if ($method === 'GET') {
    require_admin();
    $pdo = db();

    $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;

    $rows = $pdo->query(
        "SELECT id, username, user_id, bonus_title, bonus_amount, bonus_description, created_at
         FROM claims ORDER BY id DESC LIMIT $limit"
    )->fetchAll();

    $total  = (int) $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
    $users  = (int) $pdo->query("SELECT COUNT(DISTINCT username) FROM claims")->fetchColumn();

    echo json_encode(['claims' => $rows, 'total' => $total, 'uniqueUsers' => $users]);
    exit;
}

// ---- Delete one claim (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();
    $pdo = db();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    $pdo->prepare("DELETE FROM claims WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
