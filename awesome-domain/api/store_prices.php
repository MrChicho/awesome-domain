<?php
// Connect to your DB
$conn = new mysqli("localhost", "your_user", "your_password", "your_database");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Sample cards or fetch dynamically
$cards = [
  ["set" => "cmm", "number" => "686"],
  ["set" => "woe", "number" => "5"],
  ["set" => "m11", "number" => "143"]
];

foreach ($cards as $card) {
  $url = "https://api.scryfall.com/cards/{$card['set']}/{$card['number']}";
  $opts = [
    "http" => [
      "header" => [
        "User-Agent: MTG Store/1.0",
        "Accept: application/json"
      ]
    ]
  ];
  $context = stream_context_create($opts);
  $json = file_get_contents($url, false, $context);
  $data = json_decode($json, true);

  if (!isset($data["id"])) continue;

  $stmt = $conn->prepare("INSERT INTO card_prices (scryfall_id, name, set_code, collector_number, foil_price, nonfoil_price) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param(
    "ssssdd",
    $data["id"],
    $data["name"],
    $data["set"],
    $data["collector_number"],
    floatval($data["prices"]["usd_foil"] ?? 0),
    floatval($data["prices"]["usd"] ?? 0)
  );
  $stmt->execute();
}

$conn->close();
echo "Prices updated.";
