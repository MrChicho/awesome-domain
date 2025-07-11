<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// === Simple file cache (5 min) ===
$cacheFile = __DIR__ . '/../data/top_edhrec.cache.json';
$cacheTtl = 300; // 5 minutes
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    readfile($cacheFile);
    exit;
}

// Manual DB setup
$host = '127.0.0.1';
$db = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

function fetchCard($name) {
    $url = 'https://awesome-domain.net/api/scryfall-proxy.php?exact=' . urlencode($name);
    $json = @file_get_contents($url);
    return $json ? json_decode($json, true) : null;
}

function cacheImage($url, $name) {
    $cacheDir = __DIR__ . '/../img/cache/';
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) . '.jpg';
    $localPath = $cacheDir . $safeName;
    $webPath = '/img/cache/' . $safeName;

    if (!file_exists($localPath)) {
        $imgData = @file_get_contents($url);
        if ($imgData !== false) {
            @file_put_contents($localPath, $imgData);
        }
    }

    return file_exists($localPath) ? $webPath : $url;
}

try {
    $stmt = $pdo->query("
        SELECT name, MIN(edhrec_rank) AS best_rank
        FROM card_prices
        WHERE edhrec_rank IS NOT NULL
          AND name NOT IN (
            'Sol Ring', 'Command Tower', 'Arcane Signet',
            'Exotic Orchard', 'Reliquary Tower',
            'Sol Ring // Sol Ring', 'Command Tower // Command Tower'
          )
        GROUP BY name
        ORDER BY best_rank ASC
        LIMIT 10
    ");
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($cards as $card) {
        $data = fetchCard($card['name']);
        if (is_array($data) && isset($data['image_uris'])) {
            $cachedImg = cacheImage($data['image_uris']['normal'], $data['name']);
            $results[] = [
                'id' => $data['id'],
                'name' => $data['name'],
                'image_uri' => $cachedImg,
                'prices' => $data['prices'],
                'set_name' => $data['set_name'],
                'rarity' => $data['rarity'],
                'collector_number' => $data['collector_number']
            ];
        }
    }

    file_put_contents($cacheFile, json_encode($results));
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query or fetch failed: ' . $e->getMessage()]);
}
