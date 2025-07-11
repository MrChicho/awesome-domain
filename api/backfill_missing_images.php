<?php
$logFile = __DIR__ . '/../img/missing.log';
$cacheDir = __DIR__ . '/../img/cache/';

function filenameFromCardName($name) {
    return preg_replace('/[^a-zA-Z0-9\-]/', '_', $name) . '.jpg';
}

function fetchAndCacheImage($name) {
    global $cacheDir;

    $safeName = filenameFromCardName($name);
    $targetPath = $cacheDir . $safeName;

    $url = 'https://api.scryfall.com/cards/named?exact=' . urlencode($name);
    $json = @file_get_contents($url);
    if (!$json) return false;

    $data = json_decode($json, true);
    if (!isset($data['image_uris']['normal'])) return false;

    $img = @file_get_contents($data['image_uris']['normal']);
    if (!$img) return false;

    file_put_contents($targetPath, $img);
    return true;
}

// Run the fix
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lines = array_unique($lines);

foreach ($lines as $name) {
    echo "Fetching image for: $name ... ";
    if (fetchAndCacheImage($name)) {
        echo "✅ Success\n";
    } else {
        echo "❌ Failed\n";
    }
}

// Clear log
file_put_contents($logFile, '');
