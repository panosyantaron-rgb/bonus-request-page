<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ---- Read the activity log (ADMIN ONLY) ----
if ($method === 'GET') {
    require_admin();
    $pdo = db();

    $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;

    // Optional filters: level=info|warning|error, ref=<claim id> for one timeline
    $where  = [];
    $params = [];

    if (!empty($_GET['level']) && in_array($_GET['level'], ['info', 'warning', 'error'], true)) {
        $where[]  = 'level = ?';
        $params[] = $_GET['level'];
    }
    if (!empty($_GET['ref'])) {
        $where[]  = 'ref_id = ?';
        $params[] = (int) $_GET['ref'];
    }

    $sql = "SELECT id, created_at, level, actor, action, detail, ref_id, ip FROM activity_log";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= " ORDER BY id DESC LIMIT $limit";   // $limit is an int clamped above

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['logs' => $stmt->fetchAll()]);
    exit;
}

// ---- Clear the log (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();
    $pdo = db();

    $pdo->exec("DELETE FROM activity_log");
    activity('log.cleared', 'Activity log cleared', 'warning');

    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
