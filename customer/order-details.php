<?php
$pageTitle = 'Order Details - QuickMed';
include 'includes/header.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order
$stmt = $pdo->prepare("
    SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone,
           ca.address_line, ca.city, ca.postal_code
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN customer_addresses ca ON o.address_id = ca.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/quickmed/customer/orders.php');
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, m.name as medicine_name, m.generic_name, m.image
    FROM order_items oi
    JOIN inventory i ON oi.inventory_id = i.id
    JOIN medicines m ON i.medicine_id = m.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="orders.php" class="text-indigo-600 hover:underline">← Back to Orders</a>
    </div>

    <!-- Order Header -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold mb-2">Order #<?= htmlspecialchars($order['order_number']) ?></h1>
                <p class="text-gray-600">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
            </div>
            <div>
                <span class="inline-block px-4 py-2 rounded-full text-lg font-semibold
                    <?php
                    echo match($order['status']) {
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'shipped' => 'bg-purple-100 text-purple-800',
                        'delivered' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800'
                    };
                    ?>">
                    <?= ucfirst($order['status']) ?>
                </span>
            </div>
        </div>

        <!-- Progress Timeline -->
        <div class="border-t pt-6">
            <div class="flex items-center justify-between">
                <?php
                $statuses = [
                    ['key' => 'pending', 'label' => 'Order Placed', 'icon' => '📝'],
                    ['key' => 'processing', 'label' => 'Processing', 'icon' => '⚙️'],
                    ['key' => 'shipped', 'label' => 'Shipped', 'icon' => '🚚'],
                    ['key' => 'delivered', 'label' => 'Delivered', 'icon' => '✅']
                ];
                $currentIndex = array_search($order['status'], array_column($statuses, 'key'));
                if ($order['status'] === 'cancelled') $currentIndex = -1;
                ?>
                
                <?php foreach ($statuses as $index => $status): ?>
                    <div class="flex items-center <?= $index < count($statuses) - 1 ? 'flex-1' : '' ?>">
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl
                                <?= $index <= $currentIndex ? 'bg-green-500 text-white' : 'bg-gray-200' ?>">
                                <?= $status['icon'] ?>
                            </div>
                            <p class="text-sm mt-2 text-center font-semibold"><?= $status['label'] ?></p>
                        </div>
                        <?php if ($index < count($statuses) - 1): ?>
                            <div class="flex-1 h-2 mx-4 rounded <?= $index < $currentIndex ? 'bg-green-500' : 'bg-gray-200' ?>"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Order Items -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Items (<?= count($items) ?>)</h2>
                
                <div class="space-y-4">
                    <?php foreach ($items as $item): ?>
                        <div class="flex gap-4 pb-4 border-b last:border-b-0">
                            <img src="/quickmed/assets/images/uploads/products/<?= $item['image'] ?>" 
                                 alt="<?= htmlspecialchars($item['medicine_name']) ?>"
                                 class="w-20 h-20 object-cover rounded"
                                 onerror="this.src='https://via.placeholder.com/100?text=Medicine'">
                            <div class="flex-1">
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($item['medicine_name']) ?></h3>
                                <p class="text-gray-600"><?= htmlspecialchars($item['generic_name']) ?></p>
                                <p class="text-sm text-gray-500 mt-1">Qty: <?= $item['quantity'] ?> × ৳<?= number_format($item['price'], 2) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-indigo-600">৳<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Price Summary -->
                <div class="border-t mt-6 pt-6 space-y-3">
                    <div class="flex justify-between text-lg">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-semibold">৳<?= number_format($order['subtotal'], 2) ?></span>
                    </div>
                    <div class="flex justify-between text-lg">
                        <span class="text-gray-600">Delivery Charge</span>
                        <span class="font-semibold">৳<?= number_format($order['delivery_charge'], 2) ?></span>
                    </div>
                    <div class="flex justify-between text-2xl font-bold border-t pt-3">
                        <span>Total</span>
                        <span class="text-indigo-600">৳<?= number_format($order['total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="space-y-6">
            <!-- Delivery Address -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">📍 Delivery Address</h3>
                <p class="text-gray-700">
                    <?= htmlspecialchars($order['address_line']) ?><br>
                    <?= htmlspecialchars($order['city']) ?> - <?= htmlspecialchars($order['postal_code']) ?>
                </p>
            </div>

            <!-- Shop Info -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">🏪 Shop Details</h3>
                <p class="font-semibold"><?= htmlspecialchars($order['shop_name']) ?></p>
                <p class="text-gray-600 text-sm mt-2"><?= htmlspecialchars($order['shop_address']) ?></p>
                <p class="text-gray-600 text-sm">📞 <?= htmlspecialchars($order['shop_phone']) ?></p>
            </div>

            <!-- Payment Info -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">💳 Payment Method</h3>
                <p class="text-gray-700 uppercase"><?= htmlspecialchars($order['payment_method']) ?></p>
            </div>

            <!-- Re-order Button -->
            <button onclick="reorder()" 
                    class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700">
                🔄 Re-order Items
            </button>
        </div>
    </div>
</div>

<script>
async function reorder() {
    const items = <?= json_encode($items) ?>;
    
    // Get current cart
    let cart = JSON.parse(localStorage.getItem('quickmed_cart') || '[]');
    
    // Add items to cart
    items.forEach(item => {
        const existingIndex = cart.findIndex(c => c.inventory_id === item.inventory_id);
        if (existingIndex > -1) {
            cart[existingIndex].quantity += item.quantity;
        } else {
            cart.push({
                inventory_id: item.inventory_id,
                quantity: item.quantity
            });
        }
    });
    
    localStorage.setItem('quickmed_cart', JSON.stringify(cart));
    window.dispatchEvent(new Event('cartUpdated'));
    
    if (confirm('Items added to cart! Go to cart now?')) {
        window.location.href = 'cart.php';
    }
}
</script>

<?php include 'includes/footer.php'; ?>