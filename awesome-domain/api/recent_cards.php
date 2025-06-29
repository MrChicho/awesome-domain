<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// DB connection (your credentials)
$host = '127.0.0.1';
$db   = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Helper: Fetch card info from proxy
function fetchCard($name) {
    $url = 'https://awesome-domain.net/api/scryfall-proxy.php?exact=' . urlencode($name);
    $json = file_get_contents($url);
    if ($json === false) return null;
    return json_decode($json, true);
}

// Query for latest 10 unique cards (by print date)
$sql = "
    SELECT name, MIN(released_at) AS released_at
    FROM card_prices
    WHERE released_at IS NOT NULL
    GROUP BY name
    ORDER BY released_at DESC
    LIMIT 10;
";

$stmt = $pdo->query($sql);
$cards = $stmt->fetchAll();

$output = [];

foreach ($cards as $card) {
    $data = fetchCard($card['name']);
    if (!$data || !isset($data['image_uris']['normal'])) continue;

    $output[] = [
        'name' => $data['name'] ?? $card['name'],
        'image' => $data['image_uris']['normal'],
        'usd' => $data['prices']['usd'] ?? null,
        'released_at' => $card['released_at'],
    ];
}

echo json_encode($output);
