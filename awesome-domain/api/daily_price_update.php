<?php
ini_set('memory_limit', '1024M');  // Keep this here for safety

// DB connection
$host = '127.0.0.1';
$db = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ Connected to DB.<br>";
} catch (PDOException $e) {
    die("❌ DB connection failed: " . $e->getMessage());
}

// Get download URL from metadata
function fetchJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CardStoreBot/1.0');
    $response = curl_exec($ch);
    if (curl_errno($ch)) die("❌ cURL error: " . curl_error($ch));
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) die("❌ Failed to fetch $url — HTTP $httpCode");
    return json_decode($response, true);
}

$meta = fetchJson("https://api.scryfall.com/bulk-data/default_cards");
$jsonUrl = $meta['download_uri'] ?? null;
if (!$jsonUrl) die("❌ Could not find download URI.");

// Step 1: Download JSON file to disk
$localFile = '/home1/foicyrte/card_dump.json';
file_put_contents($localFile, fopen($jsonUrl, 'r'));
echo "⬇ Downloaded to local file<br>";

// Step 2: Stream & parse each card one-by-one
$handle = fopen($localFile, 'r');
if (!$handle) die("❌ Could not open file for reading.");

// Skip opening bracket
fgets($handle);

// Prepare insert
$stmt = $pdo->prepare("
    INSERT INTO card_prices (scryfall_id, NAME, set_code, collector_number, foil_price, nonfoil_price, TIMESTAMP)
    VALUES (:id, :name, :set, :collector, :foil_price, :nonfoil_price, NOW())
    ON DUPLICATE KEY UPDATE
        foil_price = VALUES(foil_price),
        nonfoil_price = VALUES(nonfoil_price),
        TIMESTAMP = NOW()
");

$counter = 0;
while (($line = fgets($handle)) !== false) {
    $line = trim($line, ",\r\n[]");
    if (empty($line)) continue;

    $card = json_decode($line, true);
    if (!$card) continue;

    $stmt->execute([
        ':id' => $card['id'],
        ':name' => $card['name'],
        ':set' => $card['set'],
        ':collector' => $card['collector_number'],
        ':foil_price' => $card['prices']['usd_foil'] ?? null,
        ':nonfoil_price' => $card['prices']['usd'] ?? null
    ]);

    $counter++;
    if ($counter % 1000 === 0) echo "✅ Stored $counter cards...<br>";
}
fclose($handle);

echo "<br>📦 Storing historical snapshot...<br>";

// Prepare insert for price history
$historyStmt = $pdo->prepare("
    INSERT INTO price_history (scryfall_id, date, foil_price, nonfoil_price)
    VALUES (:id, CURDATE(), :foil_price, :nonfoil_price)
    ON DUPLICATE KEY UPDATE
        foil_price = VALUES(foil_price),
        nonfoil_price = VALUES(nonfoil_price)
");

// Get current prices from card_prices table
$fetchStmt = $pdo->query("SELECT scryfall_id, foil_price, nonfoil_price FROM card_prices");

$histCounter = 0;
while ($row = $fetchStmt->fetch(PDO::FETCH_ASSOC)) {
    $historyStmt->execute([
        ':id' => $row['scryfall_id'],
        ':foil_price' => $row['foil_price'],
        ':nonfoil_price' => $row['nonfoil_price']
    ]);
    $histCounter++;
}
echo "🕓 Saved $histCounter daily price snapshots.<br>";


echo "<br>✅ Finished! Total cards: $counter<br>";
?>
