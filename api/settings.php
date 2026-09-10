<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only these keys can be read or written — an admin cannot invent new ones,
// and nothing else in the database is reachable through this endpoint.
const ALLOWED_SETTINGS = [
    'section_title',
    'page_title',
    'page_subtitle',
    'accent_color',
    'tab_sport',
    'tab_casino',
    'tab_livecasino',
    'claim_button',
];

$method = $_SERVER['REQUEST_METHOD'];

// ---- Read settings (PUBLIC — the main page renders from these) ----
if ($method === 'GET') {
    $pdo = db();

    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        if (in_array($r['setting_key'], ALLOWED_SETTINGS, true)) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
    }

    echo json_encode(['settings' => $out]);
    exit;
}

// ---- Save settings (ADMIN ONLY) ----
if ($method === 'POST') {
    require_admin();

    $d = body();
    if (!$d) fail('No settings supplied');

    // Validate everything BEFORE touching the database, so a bad request is
    // rejected without opening a connection and nothing is half-written.
    $clean = [];
    foreach ($d as $key => $value) {
        if (!in_array($key, ALLOWED_SETTINGS, true)) continue;

        $value = trim((string) $value);
        if ($value === '') continue;                  // never blank out a label
        if (mb_strlen($value) > 200) $value = mb_substr($value, 0, 200);

        // Colours must be a real hex value — this one goes straight into CSS
        if ($key === 'accent_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            fail('Accent colour must look like #00d9ff');
        }

        $clean[$key] = $value;
    }

    if (!$clean) fail('Nothing valid to save');

    $pdo  = db();
    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($clean as $key => $value) $stmt->execute([$key, $value]);

    echo json_encode(['ok' => true, 'saved' => count($clean)]);
    exit;
}

fail('Method not allowed', 405);
