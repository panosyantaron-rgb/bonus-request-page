<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * The conversation on a claim: admin notes, and questions from the player.
 *
 * Players are NOT authenticated anywhere in this app — the main page identifies
 * them from ?username=…&id=… in the URL. So the best available check is that
 * both values match the claim being read or written, which is what
 * verify_owner() does. Anyone who knows a real username/id pair can still read
 * and post as that player; only real player login would close that, and there
 * isn't one. Admins go through the session instead and can see everything.
 */

const MSG_MAX_LEN = 1000;
const MSG_RATE_LIMIT = 10;      // player messages per claim per hour

/** Presence check only — runs before any database connection is opened. */
function require_identity($username, $userId) {
    if ($username === '' || $userId === '') fail('username and id are required', 401);
}

function verify_owner(PDO $pdo, $claimId, $username, $userId) {
    $q = $pdo->prepare("SELECT id FROM claims WHERE id = ? AND username = ? AND user_id = ?");
    $q->execute([$claimId, $username, $userId]);

    if (!$q->fetchColumn()) {
        // Same answer whether the claim is missing or belongs to someone else,
        // so this cannot be used to discover which claim ids exist.
        fail('No such request', 404);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// ---- Read a claim's thread ----
// Admin: any claim. Player: only with a matching username + id.
if ($method === 'GET') {
    $claimId = (int) ($_GET['claim'] ?? 0);
    if ($claimId <= 0) fail('claim is required');

    $username = trim($_GET['username'] ?? '');
    $userId   = trim($_GET['id'] ?? '');

    // Reject a request that carries no identity at all before connecting
    if (!is_admin()) require_identity($username, $userId);

    $pdo = db();

    if (!is_admin()) verify_owner($pdo, $claimId, $username, $userId);

    $stmt = $pdo->prepare(
        "SELECT id, author, body, created_at FROM claim_messages
         WHERE claim_id = ? ORDER BY id ASC LIMIT 200"
    );
    $stmt->execute([$claimId]);

    echo json_encode(['messages' => $stmt->fetchAll()]);
    exit;
}

// ---- Post to a claim's thread ----
if ($method === 'POST') {
    $d       = body();
    $claimId = (int) ($d['claim'] ?? 0);
    $text    = trim((string) ($d['body'] ?? ''));

    if ($claimId <= 0) fail('claim is required');
    if ($text === '') fail('Write something first');
    if (mb_strlen($text) > MSG_MAX_LEN) fail('That message is too long (' . MSG_MAX_LEN . ' characters maximum)');

    $username = trim($d['username'] ?? '');
    $userId   = trim($d['userId'] ?? '');

    // Everything checkable without the database is checked first
    if (!is_admin()) require_identity($username, $userId);

    $pdo = db();

    if (is_admin()) {
        $author = 'admin';
    } else {
        $author = 'user';
        verify_owner($pdo, $claimId, $username, $userId);

        // Keep one claim from being used as a message firehose
        $recent = $pdo->prepare(
            "SELECT COUNT(*) FROM claim_messages
             WHERE claim_id = ? AND author = 'user' AND created_at > (NOW() - INTERVAL 1 HOUR)"
        );
        $recent->execute([$claimId]);
        if ((int) $recent->fetchColumn() >= MSG_RATE_LIMIT) {
            fail('Too many messages on this request. Please wait a while.', 429);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO claim_messages (claim_id, author, body) VALUES (?, ?, ?)");
    $stmt->execute([$claimId, $author, $text]);

    activity(
        $author === 'admin' ? 'note.admin' : 'note.user',
        ($author === 'admin' ? 'Note added: ' : 'Player asked: ') . mb_substr($text, 0, 120),
        'info',
        $claimId
    );

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit;
}

// ---- Delete one message (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) fail('id is required');

    db()->prepare("DELETE FROM claim_messages WHERE id = ?")->execute([$id]);
    activity('note.delete', "Deleted message #$id", 'warning');

    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
