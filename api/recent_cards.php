<?php
// ===== recent_cards.php =====

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// === Simple file cache (5 min) ===
$cacheFile = __DIR__ . '/../data/recent_cards.cache.json';
$cacheTtl = 300; // 5 minutes
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    readfile($cacheFile);
    exit;
}

$host = '127.0.0.1';
$db   = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

function fetchCard($name) {
    $url = 'https://awesome-domain.net/api/scryfall-proxy.php?exact=' . urlencode($name);
    $json = file_get_contents($url);
    return $json ? json_decode($json, true) : null;
}

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

    $imageUrl = $data['image_uris']['normal'];
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $card['name']) . '.jpg';
    $imagePath = __DIR__ . '/../img/cache/' . $safeName;
    $webPath = '/img/cache/' . $safeName;

    if (!file_exists($imagePath)) {
        $imgData = file_get_contents($imageUrl);
        if ($imgData !== false) {
            file_put_contents($imagePath, $imgData);
        }
    }

    $output[] = [
        'name' => $data['name'],
        'image_uri' => file_exists($imagePath) ? $webPath : $imageUrl,
        'usd' => $data['prices']['usd'] ?? null,
        'released_at' => $card['released_at'],
    ];
}

file_put_contents($cacheFile, json_encode($output));
echo json_encode($output);
