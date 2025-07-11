<?php
// Allow unlimited execution time and increase memory for large batch jobs
set_time_limit(0);
ini_set('memory_limit', '1G');
// save_all_images.php
// Run this script once to download all card images to img/cache/

// Database config
$host = '127.0.0.1';
$db   = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Utility: safe filename
function filenameFromCardName($name) {
    return preg_replace('/[^a-zA-Z0-9\-]/', '_', $name) . '.jpg';
}

$cacheDir = __DIR__ . '/../img/cache/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

$sql = "SELECT name, scryfall_id FROM card_prices";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

$log400 = fopen(__DIR__ . '/../img/scryfall_400.log', 'a');

foreach ($rows as $row) {
    $name = $row['name'];
    $scryfall_id = $row['scryfall_id'] ?? null;
    $filename = filenameFromCardName($name);
    $localPath = $cacheDir . $filename;
    echo "[TRY] $name => $filename\n";
    if (file_exists($localPath)) {
        echo "[SKIP] $name (already exists)\n";
        continue;
    }
    $searchName = explode('//', $name)[0];
    echo "  Fetching Scryfall data for: $searchName\n";
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    // Hardcoded proxy URL for CLI compatibility
    $proxy_url = 'http://awesome-domain.net/api/scryfall-proxy.php';
    // Use local proxy for name (fuzzy search)
    $scryfall = @file_get_contents($proxy_url . '?name=' . urlencode($searchName), false, $context);
    $http_code = 0;
    if (isset($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
        $http_code = (int)$m[1];
    }
    if ($scryfall === false || $http_code == 400) {
        echo "[NO SCRYFALL by name] $name | HTTP $http_code\n";
        // Try by Scryfall ID if available
        if ($scryfall_id) {
            echo "  Trying by Scryfall ID: $scryfall_id\n";
            $scryfall = @file_get_contents($proxy_url . '?id=' . urlencode($scryfall_id), false, $context);
            $http_code2 = 0;
            if (isset($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m2)) {
                $http_code2 = (int)$m2[1];
            }
            if ($scryfall === false || $http_code2 == 400) {
                echo "[NO SCRYFALL by id] $name | HTTP $http_code2\n";
                fwrite($log400, "$name | $scryfall_id | HTTP $http_code2\n");
                continue;
            }
        } else {
            fwrite($log400, "$name | (no id) | HTTP $http_code\n");
            continue;
        }
    }
    $scryData = json_decode($scryfall, true);
    if (isset($scryData['image_uris']['normal'])) {
        $img_url = $scryData['image_uris']['normal'];
        echo "  Downloading image: $img_url\n";
        $img_data = @file_get_contents($img_url);
        if ($img_data) {
            if (file_put_contents($localPath, $img_data) !== false) {
                echo "[OK] $name\n";
            } else {
                echo "[FAIL WRITE] $name | Could not write to $localPath\n";
            }
        } else {
            $error = error_get_last();
            echo "[FAIL IMG] $name | Error: " . ($error['message'] ?? 'unknown') . "\n";
        }
    } else {
        echo "[NO IMG] $name | Scryfall response: " . json_encode($scryData) . "\n";
    }
    // Sleep to avoid hammering Scryfall
    usleep(200000); // 0.2s
    // Flush output for real-time progress
    flush();
    if (function_exists('ob_flush')) ob_flush();
}
fclose($log400);
echo "Done.\n";
