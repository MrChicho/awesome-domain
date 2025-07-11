<?php
function fetchJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CardStoreBot/1.0 (your@email.com)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Optional: disable in dev
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "❌ cURL error: " . curl_error($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        echo "❌ Failed to fetch URL. HTTP status: $httpCode";
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

$url = "https://api.scryfall.com/bulk-data/default_cards";
$data = fetchJson($url);

if ($data) {
    echo "✅ Fetched via cURL<br><pre>";
    print_r($data);
    echo "</pre>";
} else {
    echo "❌ cURL fetch failed.";
}
?>
