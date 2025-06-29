<?php
ini_set('memory_limit', '1G'); // Optional: Increase if needed

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
    echo "✅ Connected via PDO.<br>";
} catch (PDOException $e) {
    die("❌ DB connection failed: " . $e->getMessage());
}

// Step 1: Fetch Scryfall metadata via cURL
$metaCurl = curl_init("https://api.scryfall.com/bulk-data");
curl_setopt($metaCurl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($metaCurl, CURLOPT_USERAGENT, "CardSiteBot/1.0");
$response = curl_exec($metaCurl);
$httpCode = curl_getinfo($metaCurl, CURLINFO_HTTP_CODE);
curl_close($metaCurl);

if ($httpCode !== 200 || !$response) {
    die("❌ Failed to fetch Scryfall bulk meta. HTTP $httpCode");
}

$metaList = json_decode($response, true);
$jsonUrl = null;
foreach ($metaList['data'] as $item) {
    if ($item['type'] === 'default_cards') {
        $jsonUrl = $item['download_uri'];
        break;
    }
}

if (!$jsonUrl) {
    die("❌ Could not find default_cards URI.");
}

// Step 2: Download & stream parse the .json file (GZIP)
$tempFile = __DIR__ . '/default-cards.json.gz';
file_put_contents($tempFile, fopen($jsonUrl, 'r'));

$fp = gzopen($tempFile, 'r');
if (!$fp) die("❌ Could not open downloaded file.");

$insert = $pdo->prepare("
    INSERT INTO card_prices (
        scryfall_id, name, set_code, collector_number, rarity,
        usd, usd_foil, released_at, edhrec_rank, image_uri, updated_at
    ) VALUES (
        :id, :name, :set, :collector_number, :rarity,
        :usd, :usd_foil, :released_at, :edhrec_rank, :image_uri, NOW()
    )
    ON DUPLICATE KEY UPDATE
        usd = VALUES(usd),
        usd_foil = VALUES(usd_foil),
        released_at = VALUES(released_at),
        edhrec_rank = VALUES(edhrec_rank),
        image_uri = VALUES(image_uri),
        updated_at = NOW()
");

$counter = 0;

// Skip the first character `[`
gzgets($fp);

// Process line by line
while (!gzeof($fp)) {
    $line = trim(gzgets($fp));
    if ($line === ']' || $line === '') continue;

    // Remove trailing comma if present
    $line = rtrim($line, ',');

    $card = json_decode($line, true);
    if (!$card) continue;

    // Determine image URI
    $image = null;
    if (isset($card['image_uris']['normal'])) {
        $image = $card['image_uris']['normal'];
    } elseif (isset($card['card_faces'][0]['image_uris']['normal'])) {
        $image = $card['card_faces'][0]['image_uris']['normal'];
    }

    $insert->execute([
        ':id' => $card['id'],
        ':name' => $card['name'],
        ':set' => $card['set'],
        ':collector_number' => $card['collector_number'] ?? null,
        ':rarity' => $card['rarity'] ?? null,
        ':usd' => $card['prices']['usd'] ?? null,
        ':usd_foil' => $card['prices']['usd_foil'] ?? null,
        ':released_at' => $card['released_at'] ?? null,
        ':edhrec_rank' => $card['edhrec_rank'] ?? null,
        ':image_uri' => $image
    ]);

    $counter++;
    if ($counter % 1000 === 0) echo "Stored $counter cards...<br>";
}

gzclose($fp);
unlink($tempFile);

echo "✅ Done! Processed $counter cards.<br>";
?>
