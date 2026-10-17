<?php
$host = '127.0.0.1';
$dbName = 'gween_laundry';
$dbUser = 'root';
$dbPass = '';

$dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Please create the database and import the schema first.');
}
