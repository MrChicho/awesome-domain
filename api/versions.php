<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing card name']);
    exit;
}

$name = $_GET['name'];
$url = 'https://api.scryfall.com/cards/search?q=' . urlencode('!"' . $name . '"');

$json = @file_get_contents($url);
$data = $json ? json_decode($json, true) : null;

if (!$data || !isset($data['data'])) {
    echo json_encode([]);
    exit;
}

$results = array_map(function ($card) {
    return [
        'id' => $card['id'],
        'name' => $card['name'],
        'image' => $card['image_uris']['normal'] ?? '',
        'set_name' => $card['set_name'] ?? '',
        'rarity' => $card['rarity'] ?? '',
        'collector_number' => $card['collector_number'] ?? '',
        'prices' => $card['prices'] ?? []
    ];
}, $data['data']);

echo json_encode($results);
