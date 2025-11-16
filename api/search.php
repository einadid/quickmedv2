<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$query = sanitize($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.medicine_id,
            i.selling_price,
            i.quantity,
            m.name as medicine_name,
            m.generic_name,
            m.image,
            m.company
        FROM inventory i
        JOIN medicines m ON i.medicine_id = m.id
        WHERE i.quantity > 0
        AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.company LIKE ?)
        GROUP BY i.medicine_id
        LIMIT 10
    ");
    
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll();
    
    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => 'Search failed']);
}
?>