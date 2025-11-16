<?php
$pageTitle = 'Dashboard - QuickMed';
include 'includes/header.php';

// Get user stats
$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userPoints = $stmt->fetchColumn();

// Get recent orders
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentOrders = $stmt->fetchAll();

// Get order stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total), 0) as total_spent
    FROM orders 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Welcome, <?= htmlspecialchars($user['name']) ?>! 👋</h1>

    <!-- Stats Cards -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <!-- Health Wallet -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-indigo-100 mb-2">Health Wallet</p>
                    <p class="text-4xl font-bold"><?= $userPoints ?></p>
                    <p class="text-sm text-indigo-100 mt-2">Points</p>
                </div>
                <div class="text-6xl opacity-50">🎁</div>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 mb-2">Total Orders</p>
                    <p class="text-4xl font-bold text-gray-800"><?= $stats['total_orders'] ?></p>
                </div>
                <div class="text-6xl">📦</div>
            </div>
        </div>

        <!-- Total Spent -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 mb-2">Total Spent</p>
                    <p class="text-4xl font-bold text-gray-800">৳<?= number_format($stats['total_spent'], 2) ?></p>
                </div>
                <div class="text-6xl">💰</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid md:grid-cols-4 gap-4 mb-8">
        <a href="products.php" class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition text-center">
            <div class="text-4xl mb-3">🛒</div>
            <p class="font-semibold">Browse Products</p>
        </a>
        <a href="orders.php" class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition text-center">
            <div class="text-4xl mb-3">📋</div>
            <p class="font-semibold">My Orders</p>
        </a>
        <a href="addresses.php" class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition text-center">
            <div class="text-4xl mb-3">📍</div>
            <p class="font-semibold">Addresses</p>
        </a>
        <a href="profile.php" class="bg-white rounded-lg p-6 shadow hover:shadow-lg transition text-center">
            <div class="text-4xl mb-3">👤</div>
            <p class="font-semibold">Profile</p>
        </a>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold mb-6">Recent Orders</h2>
        
        <?php if (count($recentOrders) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($recentOrders as $order): ?>
                    <div class="border rounded-lg p-4 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-lg"><?= $order['order_number'] ?></p>
                                <p class="text-gray-600 text-sm"><?= $order['item_count'] ?> items • ৳<?= number_format($order['total'], 2) ?></p>
                                <p class="text-gray-500 text-xs mt-1"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
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
                                <a href="order-details.php?id=<?= $order['id'] ?>" 
                                   class="block text-indigo-600 text-sm mt-2 hover:underline">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <a href="orders.php" class="block text-center text-indigo-600 font-semibold mt-6 hover:underline">
                View All Orders →
            </a>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📦</div>
                <p class="text-gray-600 mb-4">No orders yet</p>
                <a href="products.php" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                    Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Re-order Section -->
    <?php
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.image,
            MIN(i.selling_price) as price
        FROM order_items oi
        JOIN inventory i ON oi.inventory_id = i.id
        JOIN medicines m ON i.medicine_id = m.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND i.quantity > 0
        GROUP BY m.id
        ORDER BY o.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reorderProducts = $stmt->fetchAll();
    ?>

    <?php if (count($reorderProducts) > 0): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-2xl font-bold mb-6">Quick Re-order</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($reorderProducts as $product): ?>
                    <a href="product-details.php?id=<?= $product['id'] ?>" 
                       class="border rounded-lg p-3 hover:shadow-lg transition">
                        <img src="/quickmed/assets/images/uploads/products/<?= $product['image'] ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-32 object-cover rounded mb-2"
                             onerror="this.src='https://via.placeholder.com/150?text=Medicine'">
                        <p class="text-sm font-semibold truncate"><?= htmlspecialchars($product['name']) ?></p>
                        <p class="text-indigo-600 font-bold">৳<?= number_format($product['price'], 2) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>