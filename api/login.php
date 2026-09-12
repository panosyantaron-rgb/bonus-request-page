<?php
require_once __DIR__ . '/db.php';    // JSON headers + body()/fail() helpers
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ---- Is the current visitor logged in? (lets the panel survive a refresh) ----
if ($method === 'GET') {
    echo json_encode(['loggedIn' => is_admin()]);
    exit;
}

// ---- Log in ----
if ($method === 'POST') {
    // Per-IP lockout instead of a sleep(). The old sleep(1) held a PHP worker for
    // a full second per attempt, so a burst of bad logins could tie up every
    // worker and take the site down — the anti-brute-force measure was itself a
    // DoS lever. This throttles without occupying a worker: 10 tries per 15 min.
    rate_limit('login', 10, 900);

    $d    = body();
    $user = trim($d['username'] ?? '');
    $pass = (string) ($d['password'] ?? '');

    if (hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        activity('login.success', 'Signed in');
        echo json_encode(['ok' => true]);
        exit;
    }

    // Logged as a warning so failed attempts stand out in the Logs section.
    // The attempted username is recorded; the attempted password never is.
    activity('login.failed', 'Failed sign-in for username: ' . mb_substr($user, 0, 64), 'warning');

    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

// ---- Log out ----
if ($method === 'DELETE') {
    if (is_admin()) activity('logout', 'Signed out');
    $_SESSION = [];
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
