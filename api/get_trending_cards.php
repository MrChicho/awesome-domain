<?php
// DB connection
$host = '127.0.0.1';
$db = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Connected to DB.<br>";
} catch (PDOException $e) {
    die("❌ DB connection failed: " . $e->getMessage());
}

// --- Function to fetch JSON using cURL ---
function fetchJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CardStoreBot/1.0 (admin@example.com)');
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("❌ cURL error: " . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        die("❌ Failed to fetch $url — HTTP $httpCode");
    }

    curl_close($ch);
    return json_decode($response, true);
}

// Step 1: Get Scryfall metadata
$meta = fetchJson("https://api.scryfall.com/bulk-data/default_cards");
$jsonUrl = $meta['download_uri'] ?? null;
if (!$jsonUrl) {
    die("❌ Could not find download URI.");
}
echo "⬇ Downloading card data...<br>";

// Step 2: Download bulk JSON data with cURL
$ch = curl_init($jsonUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'CardStoreBot/1.0');
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');  // JSON file is gzip-compressed
$cardData = curl_exec($ch);

if (curl_errno($ch)) {
    die("❌ Error downloading card data: " . curl_error($ch));
}

curl_close($ch);
$cards = json_decode($cardData, true);

if (!$cards || !is_array($cards)) {
    die("❌ Failed to decode card data.");
}

// Step 3: Prepare insert (adjusted for your schema)
$stmt = $pdo->prepare("
    INSERT INTO card_prices (scryfall_id, name, set_code, collector_number, usd_foil, usd, updated_at)
    VALUES (:id, :name, :set, :collector, :usd_foil, :usd, NOW())
    ON DUPLICATE KEY UPDATE
        usd_foil = VALUES(usd_foil),
        usd = VALUES(usd),
        updated_at = NOW()
");

// Step 4: Loop and store cards
$counter = 0;
foreach ($cards as $card) {
    $id = $card['id'];
    $name = $card['name'];
    $set = $card['set'];
    $collector = $card['collector_number'];
    $usd = $card['prices']['usd'] ?? null;
    $usd_foil = $card['prices']['usd_foil'] ?? null;

    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':set' => $set,
        ':collector' => $collector,
        ':usd_foil' => $usd_foil,
        ':usd' => $usd
    ]);

    $counter++;
    if ($counter % 1000 === 0) {
        echo "✅ Stored $counter cards...<br>";
    }
}
