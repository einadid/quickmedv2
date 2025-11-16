<?php
$pageTitle = 'Orders - Shop Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = sanitize($_POST['status']);
    
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$new_status, $order_id, $shop_admin['shop_id']]);
            
            logActivity($pdo, $_SESSION['user_id'], 'ORDER_STATUS_UPDATED', 'orders', $order_id, "Status changed to: $new_status");
            
            $success = 'Order status updated successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to update order status';
        }
    }
}

// Filter
$status_filter = sanitize($_GET['status'] ?? 'all');

$where = ["o.shop_id = ?"];
$params = [$shop_admin['shop_id']];

if ($status_filter !== 'all') {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
}

$whereClause = implode(' AND ', $where);

// Get orders
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        u.name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        ca.address_line,
        ca.city,
        ca.postal_code,
        COUNT(oi.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN customer_addresses ca ON o.address_id = ca.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get selected order details
$selectedOrder = null;
if (isset($_GET['order'])) {
    $order_id = (int)$_GET['order'];
    
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            u.name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            ca.address_line,
            ca.city,
            ca.postal_code
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN customer_addresses ca ON o.address_id = ca.id
        WHERE o.id = ? AND o.shop_id = ?
    ");
    $stmt->execute([$order_id, $shop_admin['shop_id']]);
    $selectedOrder = $stmt->fetch();
    
    if ($selectedOrder) {
        // Get order items
        $stmt = $pdo->prepare("
            SELECT oi.*, m.name as medicine_name, m.generic_name, m.image
            FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.id
            JOIN medicines m ON i.medicine_id = m.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $selectedOrder['items'] = $stmt->fetchAll();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Online Orders Management</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <div class="flex gap-3 flex-wrap">
            <a href="?status=all" 
               class="px-4 py-2 rounded-lg <?= $status_filter === 'all' ? 'bg-purple-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                All Orders
            </a>
            <a href="?status=pending" 
               class="px-4 py-2 rounded-lg <?= $status_filter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                Pending
            </a>
            <a href="?status=processing" 
               class="px-4 py-2 rounded-lg <?= $status_filter === 'processing' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                Processing
            </a>
            <a href="?status=shipped" 
               class="px-4 py-2 rounded-lg <?= $status_filter === 'shipped' ? 'bg-purple-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                Shipped
            </a>
            <a href="?status=delivered" 
               class="px-4 py-2 rounded-lg <?= $status_filter === 'delivered' ? 'bg-green-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                Delivered
            </a>
            <a href="?status=cancelled" 
               class="px-4 py-2 rounded-lg <?= $status_filter === 'cancelled' ? 'bg-red-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                Cancelled
            </a>
        </div>
    </div>

    <div class="grid <?= $selectedOrder ? 'lg:grid-cols-2' : '' ?> gap-8">
        <!-- Orders List -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Orders (<?= count($orders) ?>)</h2>

            <?php if (count($orders) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($orders as $order): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition
                            <?= $selectedOrder && $selectedOrder['id'] === $order['id'] ? 'border-purple-600 bg-purple-50' : '' ?>">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="font-bold text-lg"><?= $order['order_number'] ?></p>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold
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
                                    <p class="text-sm text-gray-600">
                                        👤 <?= htmlspecialchars($order['customer_name']) ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        📦 <?= $order['item_count'] ?> items
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-purple-600">৳<?= number_format($order['total'], 2) ?></p>
                                    <a href="?order=<?= $order['id'] ?>&status=<?= $status_filter ?>" 
                                       class="text-sm text-purple-600 hover:underline">Manage →</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">📦</div>
                    <p class="text-gray-600">No orders found</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Details -->
        <?php if ($selectedOrder): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Order Details</h2>
                    <a href="orders.php?status=<?= $status_filter ?>" class="text-gray-600 hover:underline">✕ Close</a>
                </div>

                <!-- Customer Info -->
                <div class="bg-purple-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold mb-3">Customer Information</h3>
                    <div class="space-y-2 text-sm">
                        <p><span class="font-semibold">Name:</span> <?= htmlspecialchars($selectedOrder['customer_name']) ?></p>
                        <p><span class="font-semibold">Email:</span> <?= htmlspecialchars($selectedOrder['customer_email']) ?></p>
                        <?php if ($selectedOrder['customer_phone']): ?>
                            <p><span class="font-semibold">Phone:</span> <?= htmlspecialchars($selectedOrder['customer_phone']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold mb-3">📍 Delivery Address</h3>
                    <p class="text-sm">
                        <?= htmlspecialchars($selectedOrder['address_line']) ?><br>
                        <?= htmlspecialchars($selectedOrder['city']) ?> - <?= htmlspecialchars($selectedOrder['postal_code']) ?>
                    </p>
                </div>

                <!-- Order Items -->
                <div class="mb-6">
                    <h3 class="font-bold mb-3">Order Items</h3>
                    <div class="space-y-3">
                        <?php foreach ($selectedOrder['items'] as $item): ?>
                            <div class="flex gap-3 border-b pb-3">
                                <img src="/quickmed/assets/images/uploads/products/<?= $item['image'] ?>" 
                                     class="w-16 h-16 object-cover rounded"
                                     onerror="this.src='https://via.placeholder.com/80?text=Med'">
                                <div class="flex-1">
                                    <p class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($item['generic_name']) ?></p>
                                    <p class="text-sm text-gray-500">Qty: <?= $item['quantity'] ?> × ৳<?= number_format($item['price'], 2) ?></p>
                                </div>
                                <p class="font-bold text-purple-600">৳<?= number_format($item['quantity'] * $item['price'], 2) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="border-t pt-4 mb-6">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-semibold">৳<?= number_format($selectedOrder['subtotal'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Delivery Charge</span>
                            <span class="font-semibold">৳<?= number_format($selectedOrder['delivery_charge'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-xl font-bold border-t pt-2">
                            <span>Total</span>
                            <span class="text-purple-600">৳<?= number_format($selectedOrder['total'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Payment Method</span>
                            <span class="font-semibold uppercase"><?= $selectedOrder['payment_method'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Update Status -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-bold mb-3">Update Order Status</h3>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $selectedOrder['id'] ?>">
                        <div class="flex gap-3">
                            <select name="status" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="pending" <?= $selectedOrder['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $selectedOrder['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped" <?= $selectedOrder['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered" <?= $selectedOrder['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $selectedOrder['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_status" 
                                    class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 font-semibold">
                                Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>