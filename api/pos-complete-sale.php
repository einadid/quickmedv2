<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('salesman');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$data = json_decode(file_get_contents('php://input'), true);

$items = $data['items'] ?? [];
$customer_id = isset($data['customer_id']) ? (int)$data['customer_id'] : null;
$subtotal = (float)($data['subtotal'] ?? 0);
$vat = (float)($data['vat'] ?? 0);
$discount = (float)($data['discount'] ?? 0);
$total = (float)($data['total'] ?? 0);
$payment_method = sanitize($data['payment_method'] ?? 'cash');

if (empty($items) || $total <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid sale data']);
}

// Get salesman's shop
$stmt = $pdo->prepare("SELECT shop_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$shop_id = $stmt->fetchColumn();

if (!$shop_id) {
    jsonResponse(['success' => false, 'message' => 'No shop assigned']);
}

try {
    $pdo->beginTransaction();

    // Generate invoice number
    $invoice_number = generateInvoiceNumber();

    // Insert sale
    $stmt = $pdo->prepare("
        INSERT INTO pos_sales (invoice_number, shop_id, salesman_id, customer_id, subtotal, vat, discount, total, payment_method)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $invoice_number,
        $shop_id,
        $_SESSION['user_id'],
        $customer_id,
        $subtotal,
        $vat,
        $discount,
        $total,
        $payment_method
    ]);

    $sale_id = $pdo->lastInsertId();

    // Insert sale items and update inventory
    foreach ($items as $item) {
        // Verify inventory
        $stmt = $pdo->prepare("SELECT quantity, selling_price FROM inventory WHERE id = ? AND shop_id = ?");
        $stmt->execute([$item['inventory_id'], $shop_id]);
        $inventory = $stmt->fetch();

        if (!$inventory) {
            throw new Exception("Invalid inventory item");
        }

        if ($inventory['quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock");
        }

        // Insert sale item
        $stmt = $pdo->prepare("
            INSERT INTO pos_sale_items (sale_id, inventory_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $sale_id,
            $item['inventory_id'],
            $item['quantity'],
            $item['selling_price']
        ]);

        // Update inventory
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['inventory_id']]);
    }

// Award points to customer if registered (100 points per 1000 BDT)
if ($customer_id) {
    $points = calculatePoints($total);
    if ($points > 0) {
        $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$points, $customer_id]);

        $stmt = $pdo->prepare("
            INSERT INTO point_history (user_id, points, type, description)
            VALUES (?, ?, 'earned', ?)
        ");
        $stmt->execute([$customer_id, $points, "POS purchase: ৳" . number_format($total, 2)]);
    }
}

    // Log activity
    logActivity($pdo, $_SESSION['user_id'], 'POS_SALE', 'pos_sales', $sale_id, "Invoice: $invoice_number, Total: ৳$total");

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'sale' => [
            'id' => $sale_id,
            'invoice_number' => $invoice_number,
            'total' => $total
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>