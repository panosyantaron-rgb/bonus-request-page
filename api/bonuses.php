<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// db() is called inside each branch, AFTER the auth guard, so an unauthenticated
// request never opens a database connection.
$method = $_SERVER['REQUEST_METHOD'];

/**
 * A logo is either an uploaded filename (from api/upload.php) or a single emoji.
 * Empty means "no logo" — the page falls back to the category icon.
 * Uploaded names are hex + extension, so anything with a path separator is junk.
 */
function clean_logo($value) {
    $value = trim((string) $value);
    if ($value === '') return null;
    if (mb_strlen($value) > 255) fail('Logo value is too long');

    // Uploaded file
    if (preg_match('/^[0-9a-f]{32}\.(jpg|jpeg|png|gif|webp)$/i', $value)) return $value;

    // Emoji or short label — reject anything that looks like a path or markup
    if (preg_match('#[/\\\\<>"\']#', $value)) fail('Invalid logo value');
    if (mb_strlen($value) > 8) fail('Logo must be an uploaded image or a single emoji');

    return $value;
}

// ---- List bonuses (PUBLIC — the main page renders these) ----
if ($method === 'GET') {
    $pdo = db();

    $rows = $pdo->query(
        "SELECT id, title, amount, description, category, logo
         FROM bonuses ORDER BY sort_order ASC, id ASC"
    )->fetchAll();

    echo json_encode(['bonuses' => $rows]);
    exit;
}

// ---- Add a bonus (ADMIN ONLY) ----
if ($method === 'POST') {
    require_admin();

    $d           = body();
    $title       = trim($d['title'] ?? '');
    $amount      = trim($d['amount'] ?? '');
    $description = trim($d['description'] ?? '');
    $category    = trim($d['category'] ?? '');

    if ($title === '' || $amount === '' || $description === '') fail('All fields are required');
    if (!in_array($category, ['sport', 'casino', 'livecasino'], true)) fail('Invalid category');
    $logo = clean_logo($d['logo'] ?? '');

    $pdo  = db();
    $stmt = $pdo->prepare(
        "INSERT INTO bonuses (title, amount, description, category, logo) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$title, $amount, $description, $category, $logo]);
    $newId = (int) $pdo->lastInsertId();

    activity('bonus.create', "Added bonus \"$title\" ($amount, $category)", 'info', $newId);

    echo json_encode(['ok' => true, 'id' => $newId]);
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
    $logo = clean_logo($d['logo'] ?? '');

    $pdo  = db();
    $stmt = $pdo->prepare(
        "UPDATE bonuses SET title = ?, amount = ?, description = ?, category = ?, logo = ? WHERE id = ?"
    );
    $stmt->execute([$title, $amount, $description, $category, $logo, $id]);

    if ($stmt->rowCount() === 0) {
        // Either the id is gone, or nothing actually changed — tell them apart
        $exists = $pdo->prepare("SELECT 1 FROM bonuses WHERE id = ?");
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) fail('No bonus with that id', 404);
    }

    activity('bonus.update', "Edited bonus \"$title\" ($amount, $category)", 'info', $id);

    echo json_encode(['ok' => true]);
    exit;
}

// ---- Delete a bonus (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();
    $pdo = db();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    $row = $pdo->prepare("SELECT title FROM bonuses WHERE id = ?");
    $row->execute([$id]);
    $title = $row->fetchColumn();

    $pdo->prepare("DELETE FROM bonuses WHERE id = ?")->execute([$id]);

    activity('bonus.delete', 'Deleted bonus "' . ($title ?: "#$id") . '"', 'warning', $id);

    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
