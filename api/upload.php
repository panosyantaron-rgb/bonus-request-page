<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Image upload for bonus logos and slider images.
 *
 * File upload is the easiest way to hand someone a shell, so this is deliberately
 * strict:
 *   - admin session required before anything is touched
 *   - the file must really be an image (getimagesize), not merely named like one
 *   - only jpg / png / gif / webp, decided by what the file IS, not its name
 *   - the client's filename is discarded entirely; the stored name is random hex
 *   - the uploads folder is given an .htaccess that refuses to execute anything
 *
 * A file called shell.php.png therefore lands as a1b2….png in a folder that
 * cannot run PHP, under a name the uploader cannot predict.
 */

const UPLOAD_MAX_BYTES = 2097152; // 2 MB
const UPLOAD_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

function upload_dir() {
    $dir = dirname(__DIR__) . '/uploads';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // Belt and braces: even if something did get written here, it must not run.
    $guard = $dir . '/.htaccess';
    if (is_dir($dir) && !file_exists($guard)) {
        @file_put_contents($guard, implode("\n", [
            '# Serve images only. Nothing in this folder may ever be executed.',
            'php_flag engine off',
            'AddType text/plain .php .php3 .php4 .php5 .php7 .phtml .phps .pl .py .cgi .sh',
            '<FilesMatch "\.(php|php3|php4|php5|php7|phtml|phps|pl|py|cgi|sh|htaccess)$">',
            '    Require all denied',
            '</FilesMatch>',
            'Options -ExecCGI -Indexes',
            '',
        ]));
    }

    return $dir;
}

$method = $_SERVER['REQUEST_METHOD'];

// ---- Upload an image (ADMIN ONLY) ----
if ($method === 'POST') {
    require_admin();

    if (empty($_FILES['file'])) fail('No file received');

    $f = $_FILES['file'];

    if (!isset($f['error']) || is_array($f['error'])) fail('Malformed upload');

    switch ($f['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            fail('That image is too large (2 MB maximum)');
        case UPLOAD_ERR_NO_FILE:
            fail('No file received');
        default:
            fail('Upload failed, please try again');
    }

    if ($f['size'] > UPLOAD_MAX_BYTES) fail('That image is too large (2 MB maximum)');
    if (!is_uploaded_file($f['tmp_name'])) fail('Invalid upload');

    // The only thing that decides the type: what the bytes actually are.
    $info = @getimagesize($f['tmp_name']);
    if ($info === false || !isset($info[2]) || !isset(UPLOAD_TYPES[$info[2]])) {
        activity('upload.rejected', 'Rejected a file that is not a real image', 'warning');
        fail('That file is not an image');
    }

    // getimagesize() only reads a header, and it can be fooled: a file that is
    // just PNG magic bytes followed by PHP passes it, reporting nonsense
    // dimensions. Sanity-check the size, then make GD actually decode the
    // image — a fake cannot survive that.
    [$w, $h] = $info;
    // Cap dimensions AND total pixels. A 10000x10000 image is ~400 MB once GD
    // decodes it below — enough to OOM the worker even though the file is tiny
    // (a decompression bomb). 25 megapixels keeps the decode within a normal
    // PHP memory_limit.
    if ($w < 1 || $h < 1 || $w > 10000 || $h > 10000 || ($w * $h) > 25000000) {
        activity('upload.rejected', "Rejected an oversized image ({$w}x{$h})", 'warning');
        fail('That image is too large in dimensions');
    }

    if (function_exists('imagecreatefromstring')) {
        $decoded = @imagecreatefromstring(file_get_contents($f['tmp_name']));
        if ($decoded === false) {
            activity('upload.rejected', 'Rejected a file that could not be decoded as an image', 'warning');
            fail('That file is not a valid image');
        }
        imagedestroy($decoded);
    }

    $ext  = UPLOAD_TYPES[$info[2]];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;   // client filename discarded

    $dir = upload_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        fail('The uploads folder is not writable on the server', 500);
    }

    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        fail('Could not save the image', 500);
    }
    @chmod($dir . '/' . $name, 0644);

    activity('upload.create', "Uploaded image $name ({$info[0]}x{$info[1]}, $ext)");

    echo json_encode([
        'ok'    => true,
        'file'  => $name,
        'url'   => '/uploads/' . $name,
        'width' => $info[0],
        'height'=> $info[1],
    ]);
    exit;
}

// ---- List uploaded images (ADMIN ONLY) ----
if ($method === 'GET') {
    require_admin();

    $dir   = upload_dir();
    $files = [];

    foreach (glob($dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $path) {
        $files[] = [
            'file'     => basename($path),
            'url'      => '/uploads/' . basename($path),
            'size'     => filesize($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
        ];
    }

    usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));

    echo json_encode(['files' => $files]);
    exit;
}

// ---- Delete an uploaded image (ADMIN ONLY) ----
if ($method === 'DELETE') {
    require_admin();

    $name = (string) ($_GET['file'] ?? '');

    // Only ever a name this endpoint generated — no paths, no traversal
    if (!preg_match('/^[0-9a-f]{32}\.(jpg|jpeg|png|gif|webp)$/i', $name)) {
        fail('Invalid filename');
    }

    $path = upload_dir() . '/' . $name;
    if (is_file($path)) {
        @unlink($path);
        activity('upload.delete', "Deleted image $name", 'warning');
    }

    echo json_encode(['ok' => true]);
    exit;
}

fail('Method not allowed', 405);
