<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// db() is called inside each branch, AFTER the auth guard, so an unauthenticated
// request never opens a database connection.
$method = $_SERVER['REQUEST_METHOD'];

// The workflow a request moves through. 'new' is where every claim starts.
const CLAIM_STATUSES = ['new', 'approved', 'paid', 'rejected'];

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
    $newId = (int) $pdo->lastInsertId();

    // Start of this claim's timeline
    activity('claim.created', "$username ($userId) claimed \"$title\" $amount", 'info', $newId);

    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

// ---- List claims, newest first (ADMIN ONLY — this is player data) ----
if ($method === 'GET') {
    require_admin();
    $pdo = db();

    $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;

    // Optional filter by status, e.g. ?status=new
    $status = $_GET['status'] ?? '';
    $where  = '';
    $params = [];
    if ($status !== '' && in_array($status, CLAIM_STATUSES, true)) {
        $where    = ' WHERE status = ?';
        $params[] = $status;
    }

    $stmt = $pdo->prepare(
        "SELECT id, username, user_id, bonus_title, bonus_amount, bonus_description, status, created_at
         FROM claims$where ORDER BY id DESC LIMIT $limit"   // $limit clamped to an int above
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $total = (int) $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
    $users = (int) $pdo->query("SELECT COUNT(DISTINCT username) FROM claims")->fetchColumn();

    // How many sit in each status, for the dashboard counters
    $counts = array_fill_keys(CLAIM_STATUSES, 0);
    foreach ($pdo->query("SELECT status, COUNT(*) c FROM claims GROUP BY status")->fetchAll() as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']] = (int) $r['c'];
    }

    echo json_encode([
        'claims'      => $rows,
        'total'       => $total,
        'uniqueUsers' => $users,
        'statusCounts'=> $counts,
        'statuses'    => CLAIM_STATUSES,
    ]);
    exit;
}

// ---- Change a claim's status (ADMIN ONLY) ----
if ($method === 'PATCH') {
    require_admin();

    $d      = body();
    $id     = (int) ($d['id'] ?? 0);
    $status = trim($d['status'] ?? '');

    if ($id <= 0) fail('id is required');
    if (!in_array($status, CLAIM_STATUSES, true)) {
        fail('Status must be one of: ' . implode(', ', CLAIM_STATUSES));
    }

    $pdo = db();

    $row = $pdo->prepare("SELECT username, status FROM claims WHERE id = ?");
    $row->execute([$id]);
    $claim = $row->fetch();
    if (!$claim) fail('No claim with that id', 404);

    if ($claim['status'] === $status) {
        echo json_encode(['ok' => true, 'unchanged' => true]);
        exit;
    }

    $pdo->prepare("UPDATE claims SET status = ? WHERE id = ?")->execute([$status, $id]);

    activity(
        'claim.status',
        "{$claim['username']}: {$claim['status']} → $status",
        $status === 'rejected' ? 'warning' : 'info',
        $id
    );

    echo json_encode(['ok' => true, 'status' => $status]);
    exit;
}

// ---- Delete one claim (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();
    $pdo = db();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    $row = $pdo->prepare("SELECT username, bonus_title FROM claims WHERE id = ?");
    $row->execute([$id]);
    $claim = $row->fetch();

    $pdo->prepare("DELETE FROM claims WHERE id = ?")->execute([$id]);

    activity(
        'claim.delete',
        $claim ? "Deleted claim by {$claim['username']} for \"{$claim['bonus_title']}\"" : "Deleted claim #$id",
        'warning',
        $id
    );

    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
