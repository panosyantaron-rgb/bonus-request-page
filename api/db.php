<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Add a column only if it is missing, so an existing database upgrades itself.
// $table/$column/$definition are hardcoded by us and never come from a request.
function ensure_column(PDO $pdo, $table, $column, $definition) {
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $q->execute([$table, $column]);
    if (!$q->fetchColumn()) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

/**
 * @param bool $fatal When false, a connection failure returns null instead of
 *                    ending the request. Used by activity() so that logging can
 *                    never turn a working request into a 500 — a plain
 *                    try/catch cannot help there, because the failure path
 *                    below calls exit, which is not catchable.
 */
function db($fatal = true) {
    static $pdo = null;
    static $failed = false;

    if ($pdo !== null) return $pdo;
    if ($failed && !$fatal) return null;

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        $failed = true;
        if (!$fatal) return null;

        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed. Check api/config.php']);
        exit;
    }

    // Auto-create tables on first run
    $pdo->exec("CREATE TABLE IF NOT EXISTS claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(191) NOT NULL,
        user_id VARCHAR(191) NOT NULL,
        bonus_title VARCHAR(255) NOT NULL,
        bonus_amount VARCHAR(64) NOT NULL,
        bonus_description TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bonuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        amount VARCHAR(64) NOT NULL,
        description TEXT,
        category VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(64) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS slider_images (
        position TINYINT UNSIGNED PRIMARY KEY,
        image VARCHAR(255) DEFAULT NULL,
        emoji VARCHAR(16) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Two-way thread on a claim: admin notes and player questions.
    $pdo->exec("CREATE TABLE IF NOT EXISTS claim_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        claim_id INT NOT NULL,
        author VARCHAR(16) NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_claim (claim_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        level VARCHAR(16) NOT NULL DEFAULT 'info',
        actor VARCHAR(64) NOT NULL DEFAULT 'system',
        action VARCHAR(64) NOT NULL,
        detail TEXT,
        ref_id INT DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        INDEX idx_created (created_at),
        INDEX idx_level (level),
        INDEX idx_ref (ref_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Columns added after the tables first shipped. Existing installs get them
    // here rather than needing a manual ALTER — names are ours, never user input.
    ensure_column($pdo, 'claims',  'status', "status VARCHAR(16) NOT NULL DEFAULT 'new'");
    ensure_column($pdo, 'bonuses', 'logo',   "logo VARCHAR(255) DEFAULT NULL");
    ensure_column($pdo, 'bonuses', 'sort_order', "sort_order INT NOT NULL DEFAULT 0");

    // Five carousel slots, emoji by default until images are uploaded
    $sliderDefaults = ['⚽', '🎰', '🎲', '🏆', '💎'];
    $slot = $pdo->prepare("INSERT IGNORE INTO slider_images (position, emoji) VALUES (?, ?)");
    foreach ($sliderDefaults as $i => $e) $slot->execute([$i + 1, $e]);

    // Seed page settings once, only for keys that are missing
    $defaultSettings = [
        'section_title'  => 'Our Bonuses',
        'page_title'     => 'Claim Your Bonus',
        'page_subtitle'  => 'Select a bonus offer and start winning today',
        'accent_color'   => '#00d9ff',
        'tab_sport'      => 'Sport',
        'tab_casino'     => 'Casino',
        'tab_livecasino' => 'Live Casino',
        'claim_button'   => 'Claim Bonus',
    ];
    $seed = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $k => $v) $seed->execute([$k, $v]);

    // Seed the 9 default bonuses once, only if the table is empty
    $count = (int) $pdo->query("SELECT COUNT(*) FROM bonuses")->fetchColumn();
    if ($count === 0) {
        $defaults = [
            ['Welcome Bonus',   '+100%', 'Match bonus on your first bet',      'sport'],
            ['Weekly Cashback', '+5%',   'Get cash back on all sports bets',   'sport'],
            ['Parlay Boost',    '+25%',  'Enhanced odds on parlay bets',       'sport'],
            ['Casino Welcome',  '+150%', 'Bonus plus free spins',              'casino'],
            ['Daily Reload',    '+50%',  'Get bonus every day',                'casino'],
            ['VIP Rewards',     '+200%', 'Exclusive VIP member benefits',      'casino'],
            ['Live Welcome',    '+120%', 'Play with live dealers instantly',   'livecasino'],
            ['High Roller',     '+250%', 'For big bet players',                'livecasino'],
            ['Table Master',    '+180%', 'Exclusive live table access',        'livecasino'],
        ];
        $stmt = $pdo->prepare("INSERT INTO bonuses (title, amount, description, category) VALUES (?, ?, ?, ?)");
        foreach ($defaults as $b) $stmt->execute($b);
    }

    return $pdo;
}

/**
 * Write one line to the activity log.
 *
 * Logging must never break the request it is describing, so every failure here
 * is swallowed. $level is 'info' | 'warning' | 'error'; $refId ties an entry to
 * a claim so the panel can show that claim's timeline.
 */
function activity($action, $detail = '', $level = 'info', $refId = null) {
    try {
        $actor = !empty($_SESSION['is_admin']) ? (defined('ADMIN_USER') ? ADMIN_USER : 'admin') : 'anonymous';

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip) $ip = mb_substr(trim(explode(',', $ip)[0]), 0, 45);

        $pdo = db(false);            // never ends the request if the DB is down
        if (!$pdo) return;

        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (level, actor, action, detail, ref_id, ip)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$level, $actor, $action, mb_substr((string) $detail, 0, 1000), $refId, $ip]);
    } catch (Throwable $e) {
        // Deliberately silent — a failed log entry is not worth a failed request
    }
}

function body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}
