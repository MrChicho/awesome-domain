<?php
// Database config
$host = '127.0.0.1';
$db = 'foicyrte_awesome-domain';
$user = 'foicyrte_admin';
$pass = '5A3NA4vTinX.HJL';

header('Content-Type: application/json');

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Get card ID from query string
$scryfall_id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'nonfoil';

if (!$scryfall_id || !in_array($type, ['foil', 'nonfoil'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid parameters.']);
    exit;
}

// Build and run query
$stmt = $pdo->prepare("
    SELECT date, {$type}_price AS price
    FROM price_history
    WHERE scryfall_id = :id
    ORDER BY date DESC
    LIMIT 7
");

$stmt->execute([':id' => $scryfall_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reverse to go oldest → newest
$data = array_reverse($data);

echo json_encode($data);
