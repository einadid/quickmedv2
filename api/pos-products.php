<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('salesman');

header('Content-Type: application/json');

$shop_id = (int)($_GET['shop_id'] ?? 0);
$query = sanitize($_GET['q'] ?? '');
$category = sanitize($_GET['category'] ?? '');

if (!$shop_id) {
    jsonResponse(['products' => []]);
}

try {
    $where = ["i.shop_id = ?", "i.quantity > 0"];
    $params = [$shop_id];

    if ($query) {
        $where[] = "(m.name LIKE ? OR m.generic_name LIKE ? OR m.company LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($category) {
        $where[] = "m.category = ?";
        $params[] = $category;
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.selling_price,
            i.quantity,
            i.batch_no,
            m.name as medicine_name,
            m.generic_name,
            m.image,
            m.category
        FROM inventory i
        JOIN medicines m ON i.medicine_id = m.id
        WHERE $whereClause
        ORDER BY m.name ASC
        LIMIT 50
    ");

    $stmt->execute($params);
    $products = $stmt->fetchAll();

    jsonResponse(['products' => $products]);
} catch (PDOException $e) {
    jsonResponse(['products' => [], 'error' => 'Failed to load products'], 500);
}
?>