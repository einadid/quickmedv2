<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$data = json_decode(file_get_contents('php://input'), true);

$address_line = sanitize($data['address_line'] ?? '');
$city = sanitize($data['city'] ?? '');
$postal_code = sanitize($data['postal_code'] ?? '');

if (empty($address_line) || empty($city)) {
    jsonResponse(['success' => false, 'message' => 'Address and city are required']);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO customer_addresses (user_id, address_line, city, postal_code)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $address_line,
        $city,
        $postal_code
    ]);

    jsonResponse(['success' => true, 'address_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to save address'], 500);
}
?>