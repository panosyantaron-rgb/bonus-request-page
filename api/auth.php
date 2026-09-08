<?php
require_once __DIR__ . '/config.php';

// Session must start before any body output. db.php only sets headers, so the
// include order between the two does not matter.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function is_admin() {
    return !empty($_SESSION['is_admin']);
}

function require_admin() {
    if (!is_admin()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authorized. Please log in.']);
        exit;
    }
}
