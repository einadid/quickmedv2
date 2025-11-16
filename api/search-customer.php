<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('salesman');

header('Content-Type: application/json');

$query = sanitize($_GET['q'] ?? '');

if (strlen($query) < 3) {
    jsonResponse(['customers' => []]);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, points
        FROM users 
        WHERE role = 'customer' 
        AND (email LIKE ? OR phone LIKE ? OR name LIKE ?)
        LIMIT 10
    ");

    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $customers = $stmt->fetchAll();

    jsonResponse(['customers' => $customers]);
} catch (PDOException $e) {
    jsonResponse(['customers' => [], 'error' => 'Search failed'], 500);
}
?>