<?php
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// === Simple file cache (5 min) ===
$cacheFile = __DIR__ . '/../data/price_history.cache.json';
$cacheTtl = 300; // 5 minutes
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    readfile($cacheFile);
    exit;
}

// Database config
$host = '127.0.0.1';
$db   = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Allow large memory use
ini_set('memory_limit', '1G');

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Image cache directory
$cacheDir = __DIR__ . '/../img/cache/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Utility: safe filename
function filenameFromCardName($name) {
    return preg_replace('/[^a-zA-Z0-9\-]/', '_', $name) . '.jpg';
}

// Fetch price history (last 30 days)
$sql = "
    SELECT p.name, p.scryfall_id, h.date, h.usd
    FROM price_history h
    JOIN card_prices p ON h.scryfall_id = p.scryfall_id
    WHERE h.date >= CURDATE() - INTERVAL 30 DAY
    ORDER BY h.date
";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
    exit;
}


// 1. Build price history per card
$card_prices = [];
foreach ($rows as $row) {
    $name = $row['name'];
    $date = $row['date'];
    $price = (float)$row['usd'];
    if (!isset($card_prices[$name])) {
        $card_prices[$name] = [];
    }
    $card_prices[$name][$date] = $price;
}

// 2. Calculate price change for each card
$card_changes = [];
foreach ($card_prices as $name => $prices_by_date) {
    ksort($prices_by_date); // ensure chronological
    $first = reset($prices_by_date);
    $last = end($prices_by_date);
    $change = $last - $first;
    $card_changes[$name] = [
        'change' => $change,
        'first' => $first,
        'last' => $last,
        'prices' => array_values($prices_by_date)
    ];
}

// 3. Get top 5 up and 5 down
uasort($card_changes, function($a, $b) {
    return $b['change'] <=> $a['change'];
});
$top_up = array_slice($card_changes, 0, 5, true);
uasort($card_changes, function($a, $b) {
    return $a['change'] <=> $b['change'];
});
$top_down = array_slice($card_changes, 0, 5, true);

$final_cards = $top_up + $top_down;

// 4. Only process images for these cards
$trends = [];
foreach ($final_cards as $name => $info) {
    $filename = filenameFromCardName($name);
    $localPath = $cacheDir . $filename;
    $webPath = '/img/cache/' . $filename;
    $fallbackImage = '/img/missing.jpg';

    $image_uris_normal = null;
    $debug_log = __DIR__ . '/../img/debug_image.log';
    $log_msg = "[" . date('c') . "] $name | local: ";
    if (!file_exists($localPath)) {
        $searchName = explode('//', $name)[0];
        file_put_contents(__DIR__ . '/../img/missing.log', $searchName . PHP_EOL, FILE_APPEND);
        $log_msg .= "NO | ";
        $scryfall = @file_get_contents('https://api.scryfall.com/cards/named?fuzzy=' . urlencode($searchName));
        if ($scryfall) {
            $scryData = json_decode($scryfall, true);
            if (isset($scryData['image_uris']['normal'])) {
                $image_uris_normal = $scryData['image_uris']['normal'];
                $log_msg .= "scryfall: $image_uris_normal";
            } else {
                $log_msg .= "scryfall: MISSING";
            }
        } else {
            $log_msg .= "scryfall: ERROR";
        }
    } else {
        $log_msg .= "YES | scryfall: SKIP";
    }
    file_put_contents($debug_log, $log_msg . "\n", FILE_APPEND);

    // Prefer Scryfall image if available, then local, then fallback
    if ($image_uris_normal) {
        $image_uri_final = $image_uris_normal;
    } elseif (file_exists($localPath)) {
        $image_uri_final = $webPath;
    } else {
        $image_uri_final = $fallbackImage;
    }

    $trends[$name] = [
        'prices' => $info['prices'],
        'image_uri' => $image_uri_final,
        'image_uris' => $image_uris_normal ? ['normal' => $image_uris_normal] : null,
        'change' => $info['change'],
        'first' => $info['first'],
        'last' => $info['last']
    ];
}

// Output valid JSON
file_put_contents($cacheFile, json_encode($trends, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo json_encode($trends, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
