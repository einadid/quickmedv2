<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$data = json_decode(file_get_contents('php://input'), true);

$items = $data['items'] ?? [];
$address_id = (int)($data['address_id'] ?? 0);
$payment_method = sanitize($data['payment_method'] ?? 'cod');
$subtotal = (float)($data['subtotal'] ?? 0);
$delivery_charge = (float)($data['delivery_charge'] ?? 60);
$total = (float)($data['total'] ?? 0);

if (empty($items) || !$address_id) {
    jsonResponse(['success' => false, 'message' => 'Invalid order data']);
}

try {
    $pdo->beginTransaction();

    // Group items by shop
    $stmt = $pdo->prepare("SELECT shop_id FROM inventory WHERE id = ?");
    $shopGroups = [];
    
    foreach ($items as $item) {
        $stmt->execute([$item['inventory_id']]);
        $shop_id = $stmt->fetchColumn();
        
        if (!isset($shopGroups[$shop_id])) {
            $shopGroups[$shop_id] = [];
        }
        $shopGroups[$shop_id][] = $item;
    }

    // Create orders for each shop
    $orderNumbers = [];
    $isFirstOrder = true;

    foreach ($shopGroups as $shop_id => $shopItems) {
        $order_number = generateOrderNumber();
        $orderNumbers[] = $order_number;

        // Calculate shop subtotal
        $shop_subtotal = 0;
        foreach ($shopItems as $item) {
            $stmt = $pdo->prepare("SELECT selling_price FROM inventory WHERE id = ?");
            $stmt->execute([$item['inventory_id']]);
            $price = $stmt->fetchColumn();
            $shop_subtotal += $price * $item['quantity'];
        }

        // Only first order gets delivery charge
        $order_delivery = $isFirstOrder ? $delivery_charge : 0;
        $order_total = $shop_subtotal + $order_delivery;

        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, user_id, shop_id, address_id, subtotal, delivery_charge, total, payment_method, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $order_number,
            $_SESSION['user_id'],
            $shop_id,
            $address_id,
            $shop_subtotal,
            $order_delivery,
            $order_total,
            $payment_method
        ]);

        $order_id = $pdo->lastInsertId();

        // Insert order items and update inventory
        foreach ($shopItems as $item) {
            // Get current price
            $stmt = $pdo->prepare("SELECT selling_price, quantity FROM inventory WHERE id = ?");
            $stmt->execute([$item['inventory_id']]);
            $inventory = $stmt->fetch();

            if (!$inventory || $inventory['quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock");
            }

            // Insert order item
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, inventory_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['inventory_id'],
                $item['quantity'],
                $inventory['selling_price']
            ]);

            // Update inventory
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET quantity = quantity - ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['inventory_id']]);
        }

        $isFirstOrder = false;
    }


    // Award points (100 points per 1000 BDT spent)
$points = calculatePoints($total);
if ($points > 0) {
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points, $_SESSION['user_id']]);

    $stmt = $pdo->prepare("
        INSERT INTO point_history (user_id, points, type, description)
        VALUES (?, ?, 'earned', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $points, "Order purchase: ৳" . number_format($total, 2)]);
}

    // Log activity
    logActivity($pdo, $_SESSION['user_id'], 'ORDER_PLACED', 'orders', $order_id, "Order: " . implode(', ', $orderNumbers));

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'order_number' => $orderNumbers[0],
        'message' => 'Order placed successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>