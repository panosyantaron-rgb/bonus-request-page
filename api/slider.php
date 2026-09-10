<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Five carousel slots. Each holds either an uploaded image or an emoji;
// the main page prefers the image and falls back to the emoji.
const SLIDER_SLOTS = 5;

$method = $_SERVER['REQUEST_METHOD'];

// ---- Read the slider (PUBLIC — the main page renders it) ----
if ($method === 'GET') {
    $pdo = db();

    $rows = $pdo->query(
        "SELECT position, image, emoji FROM slider_images ORDER BY position ASC"
    )->fetchAll();

    foreach ($rows as &$r) {
        $r['position'] = (int) $r['position'];
        $r['url'] = $r['image'] ? '/uploads/' . $r['image'] : null;
    }

    echo json_encode(['slides' => $rows]);
    exit;
}

// ---- Set one slot (ADMIN ONLY) ----
if ($method === 'POST') {
    require_admin();

    $d   = body();
    $pos = (int) ($d['position'] ?? 0);
    if ($pos < 1 || $pos > SLIDER_SLOTS) fail('Position must be 1 to ' . SLIDER_SLOTS);

    $image = trim((string) ($d['image'] ?? ''));
    $emoji = trim((string) ($d['emoji'] ?? ''));

    // Images may only be a filename this server generated in api/upload.php
    if ($image !== '' && !preg_match('/^[0-9a-f]{32}\.(jpg|jpeg|png|gif|webp)$/i', $image)) {
        fail('Invalid image reference');
    }
    if (mb_strlen($emoji) > 8) fail('Emoji is too long');
    if (preg_match('#[/\\\\<>"\']#', $emoji)) fail('Invalid emoji');

    if ($image === '' && $emoji === '') fail('Give the slot an image or an emoji');

    $pdo  = db();
    $stmt = $pdo->prepare(
        "INSERT INTO slider_images (position, image, emoji) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE image = VALUES(image), emoji = VALUES(emoji)"
    );
    $stmt->execute([$pos, $image !== '' ? $image : null, $emoji !== '' ? $emoji : null]);

    activity('slider.update', "Slot $pos set to " . ($image !== '' ? "image $image" : "emoji $emoji"), 'info', $pos);

    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
