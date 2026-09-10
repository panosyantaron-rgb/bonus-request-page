<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// db() is called inside each branch, AFTER the auth guard, so an unauthenticated
// request never opens a database connection.
$method = $_SERVER['REQUEST_METHOD'];

// ---- List bonuses (PUBLIC — the main page renders these) ----
if ($method === 'GET') {
    $pdo = db();

    $rows = $pdo->query(
        "SELECT id, title, amount, description, category FROM bonuses ORDER BY id ASC"
    )->fetchAll();

    echo json_encode(['bonuses' => $rows]);
    exit;
}

// ---- Add a bonus (ADMIN ONLY) ----
if ($method === 'POST') {
    require_admin();
    $pdo = db();

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

// ---- Edit a bonus (ADMIN ONLY) ----
if ($method === 'PUT') {
    require_admin();

    $d  = body();
    $id = (int) ($d['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    $title       = trim($d['title'] ?? '');
    $amount      = trim($d['amount'] ?? '');
    $description = trim($d['description'] ?? '');
    $category    = trim($d['category'] ?? '');

    // Validate before connecting, so a bad request never opens a connection
    if ($title === '' || $amount === '' || $description === '') fail('All fields are required');
    if (!in_array($category, ['sport', 'casino', 'livecasino'], true)) fail('Invalid category');

    $pdo  = db();
    $stmt = $pdo->prepare(
        "UPDATE bonuses SET title = ?, amount = ?, description = ?, category = ? WHERE id = ?"
    );
    $stmt->execute([$title, $amount, $description, $category, $id]);

    if ($stmt->rowCount() === 0) {
        // Either the id is gone, or nothing actually changed — tell them apart
        $exists = $pdo->prepare("SELECT 1 FROM bonuses WHERE id = ?");
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) fail('No bonus with that id', 404);
    }

    echo json_encode(['ok' => true]);
    exit;
}

// ---- Delete a bonus (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();
    $pdo = db();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    $pdo->prepare("DELETE FROM bonuses WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
