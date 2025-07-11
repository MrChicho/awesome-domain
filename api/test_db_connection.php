<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is running.<br>";

$host = "127.0.0.1";
$user = "foicyrte_admin";
$password = "5A3NA4vTinX.HJL";
$database = "foicyrte_awesome-domain";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Connected successfully to the database.";
?>
