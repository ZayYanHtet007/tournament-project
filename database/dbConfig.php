<?php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

require_once __DIR__ . '/../loadenv.php';
loadEnv(__DIR__ . "/../.env");

$host = $_ENV["DB_HOST"] ?? '127.0.0.1';
$port = $_ENV["DB_PORT"] ?? 3306;
$user = $_ENV["DB_USER"] ?? 'root';
$pass = $_ENV["DB_PASSWORD"] ?? '';
$db   = $_ENV["DB_NAME"] ?? 'tourna_x';

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    // echo "Connected successfully";
} catch (Exception $e) {
    // Don't kill the whole page if DB is unreachable; set $conn to null and log the error.
    error_log("[dbConfig] Database connection failed: " . $e->getMessage());
    $conn = null;
}
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("PDO connection failed: " . $e->getMessage());
    
}
