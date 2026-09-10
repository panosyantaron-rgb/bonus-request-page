<?php
require_once __DIR__ . '/db.php';

/**
 * A player's own requests, for the "My Requests" tab on the main page.
 *
 * Public by necessity — players never log in. Both username AND id must match,
 * which is the same weak identity the rest of the page already relies on, and
 * no stronger. It returns only that player's own rows and never the whole list.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$username = trim($_GET['username'] ?? '');
$userId   = trim($_GET['id'] ?? '');

if ($username === '' || $userId === '') fail('username and id are required', 401);

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT c.id, c.bonus_title, c.bonus_amount, c.bonus_description, c.status, c.created_at,
            (SELECT COUNT(*) FROM claim_messages m WHERE m.claim_id = c.id) AS message_count,
            (SELECT COUNT(*) FROM claim_messages m WHERE m.claim_id = c.id AND m.author = 'admin') AS admin_notes
     FROM claims c
     WHERE c.username = ? AND c.user_id = ?
     ORDER BY c.id DESC
     LIMIT 100"
);
$stmt->execute([$username, $userId]);

$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['id']            = (int) $r['id'];
    $r['message_count'] = (int) $r['message_count'];
    $r['admin_notes']   = (int) $r['admin_notes'];
}

echo json_encode(['claims' => $rows]);
