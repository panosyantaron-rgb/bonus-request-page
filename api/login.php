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
    $d    = body();
    $user = trim($d['username'] ?? '');
    $pass = (string) ($d['password'] ?? '');

    if (hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        echo json_encode(['ok' => true]);
        exit;
    }

    sleep(1); // slow down brute-force guessing
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

// ---- Log out ----
if ($method === 'DELETE') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
