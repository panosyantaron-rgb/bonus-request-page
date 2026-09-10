<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

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
