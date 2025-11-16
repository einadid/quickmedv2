<?php
$pageTitle = 'Order Successful - QuickMed';
include 'includes/header.php';

$order_number = sanitize($_GET['order'] ?? '');

if (!$order_number) {
    redirect('/quickmed/customer/orders.php');
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, s.name as shop_name, ca.address_line, ca.city
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN customer_addresses ca ON o.address_id = ca.id
    WHERE o.order_number = ? AND o.user_id = ?
");
$stmt->execute([$order_number, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/quickmed/customer/orders.php');
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, m.name as medicine_name, m.image
    FROM order_items oi
    JOIN inventory i ON oi.inventory_id = i.id
    JOIN medicines m ON i.medicine_id = m.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <!-- Success Message -->
        <div class="bg-green-50 border-2 border-green-500 rounded-xl p-8 text-center mb-8">
            <div class="text-6xl mb-4">✅</div>
            <h1 class="text-3xl font-bold text-green-700 mb-2">Order Placed Successfully!</h1>
            <p class="text-green-600">Thank you for your order. We'll process it soon.</p>
        </div>

        <!-- Order Details -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">Order Details</h2>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="text-gray-600 text-sm">Order Number</p>
                    <p class="font-bold text-lg"><?= htmlspecialchars($order['order_number']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Order Date</p>
                    <p class="font-semibold"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Shop</p>
                    <p class="font-semibold"><?= htmlspecialchars($order['shop_name']) ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Payment Method</p>
                    <p class="font-semibold"><?= strtoupper($order['payment_method']) ?></p>
                </div>
            </div>

            <div class="border-t pt-4">
                <p class="text-gray-600 text-sm mb-2">Delivery Address</p>
                <p class="font-semibold">
                    <?= htmlspecialchars($order['address_line']) ?>, 
                    <?= htmlspecialchars($order['city']) ?>
                </p>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Items Ordered (<?= count($items) ?>)</h2>
            
            <div class="space-y-4">
                <?php foreach ($items as $item): ?>
                    <div class="flex gap-4 pb-4 border-b last:border-b-0">
                        <img src="/quickmed/assets/images/uploads/products/<?= $item['image'] ?>" 
                             alt="<?= htmlspecialchars($item['medicine_name']) ?>"
                             class="w-16 h-16 object-cover rounded"
                             onerror="this.src='https://via.placeholder.com/80?text=Medicine'">
                        <div class="flex-1">
                            <p class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></p>
                            <p class="text-sm text-gray-600">Quantity: <?= $item['quantity'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-indigo-600">৳<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                            <p class="text-sm text-gray-600">৳<?= number_format($item['price'], 2) ?> each</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="border-t mt-4 pt-4 space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-semibold">৳<?= number_format($order['subtotal'], 2) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Delivery Charge</span>
                    <span class="font-semibold">৳<?= number_format($order['delivery_charge'], 2) ?></span>
                </div>
                <div class="flex justify-between text-xl font-bold border-t pt-2">
                    <span>Total</span>
                    <span class="text-indigo-600">৳<?= number_format($order['total'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="grid grid-cols-2 gap-4">
            <a href="/quickmed/customer/orders.php" 
               class="block text-center bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700">
                View All Orders
            </a>
            <a href="/quickmed/customer/index.php" 
               class="block text-center border border-indigo-600 text-indigo-600 py-3 rounded-lg font-semibold hover:bg-indigo-50">
                Continue Shopping
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>