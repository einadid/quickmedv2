<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid request'], 400);
}

$data = json_decode(file_get_contents('php://input'), true);
$inventoryIds = $data['inventory_ids'] ?? [];

if (empty($inventoryIds)) {
    jsonResponse(['items' => []]);
}

try {
    $placeholders = str_repeat('?,', count($inventoryIds) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT 
            i.id as inventory_id,
            i.selling_price,
            i.quantity as available_stock,
            i.shop_id,
            m.id as medicine_id,
            m.name as medicine_name,
            m.generic_name,
            m.image,
            s.name as shop_name
        FROM inventory i
        JOIN medicines m ON i.medicine_id = m.id
        JOIN shops s ON i.shop_id = s.id
        WHERE i.id IN ($placeholders)
    ");
    
    $stmt->execute($inventoryIds);
    $items = $stmt->fetchAll();
    
    jsonResponse(['items' => $items]);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Failed to load cart'], 500);
}
?>