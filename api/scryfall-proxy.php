<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function fetch($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Magic Card Store/1.0 (+https://yourdomain.com)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $response];
}

if (isset($_GET['edh']) && $_GET['edh'] === 'week') {
    [$status, $body] = fetch('https://json.edhrec.com/pages/top/week.json');
    if ($status !== 200) {
        http_response_code($status);
        echo $body;
        exit;
    }

    // Filter only card name keys (exclude internal metadata like "cardlists")
    $parsed = json_decode($body, true);
    $valid = [];

    if (isset($parsed['container']['json_dict'])) {
        foreach ($parsed['container']['json_dict'] as $key => $value) {
            if (is_array($value) && isset($value[0]['name'])) {
                $valid[$value[0]['name']] = true;
            }
        }
    }

    echo json_encode(array_keys($valid));
    exit;
}

if (isset($_GET['name'])) {
    $query = urlencode($_GET['name']);
    [$status, $body] = fetch("https://api.scryfall.com/cards/named?fuzzy=$query");
    http_response_code($status);
    echo $body;
    exit;
}

if (isset($_GET['exact'])) {
    $query = urlencode($_GET['exact']);
    [$status, $body] = fetch("https://api.scryfall.com/cards/named?exact=$query");
    http_response_code($status);
    echo $body;
    exit;
}

if (isset($_GET['search'])) {
    $q = $_GET['search'];
    $params = [];
    if (isset($_GET['unique'])) {
        $params[] = 'unique=' . urlencode($_GET['unique']);
    }
    // Add more params here if needed
    $paramStr = $params ? ('&' . implode('&', $params)) : '';
    $q_enc = urlencode($q);
    [$status, $body] = fetch("https://api.scryfall.com/cards/search?q=$q_enc$paramStr");
    http_response_code($status);
    echo $body;
    exit;
}

http_response_code(400);
echo json_encode(["error" => "Invalid request"]);
