<?php
require_once __DIR__ . '/db.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// ---- List bonuses ----
if ($method === 'GET') {
    $rows = $pdo->query(
        "SELECT id, title, amount, description, category FROM bonuses ORDER BY id ASC"
    )->fetchAll();

    echo json_encode(['bonuses' => $rows]);
    exit;
}

// ---- Add a bonus ----
if ($method === 'POST') {
    $d = body();

    $title       = trim($d['title'] ?? '');
    $amount      = trim($d['amount'] ?? '');
    $description = trim($d['description'] ?? '');
    $category    = trim($d['category'] ?? '');

    if ($title === '' || $amount === '' || $description === '') fail('All fields are required');
    if (!in_array($category, ['sport', 'casino', 'livecasino'], true)) fail('Invalid category');

    $stmt = $pdo->prepare(
        "INSERT INTO bonuses (title, amount, description, category) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$title, $amount, $description, $category]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit;
}

// ---- Delete a bonus ----
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    $pdo->prepare("DELETE FROM bonuses WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
