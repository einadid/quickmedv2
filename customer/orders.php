<?php
$pageTitle = 'My Orders - QuickMed';
include 'includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get orders
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        s.name as shop_name,
        COUNT(oi.id) as item_count
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalOrders = $stmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">My Orders</h1>
        <a href="/quickmed/customer/dashboard.php" class="text-indigo-600 hover:underline">← Back to Dashboard</a>
    </div>

    <?php if (count($orders) > 0): ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-2xl transition">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <!-- Order Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="font-bold text-xl"><?= htmlspecialchars($order['order_number']) ?></h3>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold
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
                                
                                <div class="grid md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-600">Shop</p>
                                        <p class="font-semibold"><?= htmlspecialchars($order['shop_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-600">Items</p>
                                        <p class="font-semibold"><?= $order['item_count'] ?> items</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-600">Order Date</p>
                                        <p class="font-semibold"><?= date('d M Y', strtotime($order['created_at'])) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Price & Action -->
                            <div class="text-right">
                                <p class="text-3xl font-bold text-indigo-600 mb-3">৳<?= number_format($order['total'], 2) ?></p>
                                <a href="order-details.php?id=<?= $order['id'] ?>" 
                                   class="inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
                                    View Details
                                </a>
                            </div>
                        </div>

                        <!-- Order Timeline -->
                        <div class="mt-6 pt-6 border-t">
                            <div class="flex items-center justify-between">
                                <?php
                                $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                                $currentIndex = array_search($order['status'], $statuses);
                                if ($order['status'] === 'cancelled') $currentIndex = -1;
                                ?>
                                
                                <?php foreach ($statuses as $index => $status): ?>
                                    <div class="flex items-center <?= $index < count($statuses) - 1 ? 'flex-1' : '' ?>">
                                        <div class="flex flex-col items-center">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold
                                                <?= $index <= $currentIndex ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500' ?>">
                                                <?= $index <= $currentIndex ? '✓' : ($index + 1) ?>
                                            </div>
                                            <p class="text-xs mt-2 text-center capitalize"><?= $status ?></p>
                                        </div>
                                        <?php if ($index < count($statuses) - 1): ?>
                                            <div class="flex-1 h-1 mx-2 <?= $index < $currentIndex ? 'bg-green-500' : 'bg-gray-200' ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center gap-2 mt-8">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" 
                       class="px-4 py-2 rounded-lg <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">📦</div>
            <p class="text-gray-600 text-xl mb-6">No orders yet</p>
            <a href="index.php" class="inline-block bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                Start Shopping
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>