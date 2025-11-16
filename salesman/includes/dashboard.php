<?php
$pageTitle = 'Dashboard - Salesman';
include 'includes/header.php';

// Get today's stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(total), 0) as total_amount
    FROM pos_sales 
    WHERE salesman_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$todayStats = $stmt->fetch();

// Get this month's stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(total), 0) as total_amount
    FROM pos_sales 
    WHERE salesman_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
");
$stmt->execute([$_SESSION['user_id']]);
$monthStats = $stmt->fetch();

// Get low stock items
$stmt = $pdo->prepare("
    SELECT i.*, m.name as medicine_name
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.id
    WHERE i.shop_id = ? AND i.quantity <= 10 AND i.quantity > 0
    ORDER BY i.quantity ASC
    LIMIT 5
");
$stmt->execute([$salesman['shop_id']]);
$lowStock = $stmt->fetchAll();

// Get expiring soon items
$stmt = $pdo->prepare("
    SELECT i.*, m.name as medicine_name
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.id
    WHERE i.shop_id = ? AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND i.quantity > 0
    ORDER BY i.expiry_date ASC
    LIMIT 5
");
$stmt->execute([$salesman['shop_id']]);
$expiringSoon = $stmt->fetchAll();

// Get recent sales
$stmt = $pdo->prepare("
    SELECT * FROM pos_sales 
    WHERE salesman_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentSales = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Today's Sales -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 mb-2">Today's Sales</p>
                    <p class="text-3xl font-bold"><?= $todayStats['total_sales'] ?></p>
                    <p class="text-sm text-blue-100 mt-2">৳<?= number_format($todayStats['total_amount'], 2) ?></p>
                </div>
                <div class="text-5xl opacity-50">📊</div>
            </div>
        </div>

        <!-- This Month -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 mb-2">This Month</p>
                    <p class="text-3xl font-bold"><?= $monthStats['total_sales'] ?></p>
                    <p class="text-sm text-green-100 mt-2">৳<?= number_format($monthStats['total_amount'], 2) ?></p>
                </div>
                <div class="text-5xl opacity-50">📈</div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 mb-2">Low Stock Items</p>
                    <p class="text-3xl font-bold"><?= count($lowStock) ?></p>
                    <p class="text-sm text-orange-100 mt-2">Need restocking</p>
                </div>
                <div class="text-5xl opacity-50">⚠️</div>
            </div>
        </div>

        <!-- Expiring Soon -->
        <div class="bg-gradient-to-br from-red-500 to-red-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 mb-2">Expiring Soon</p>
                    <p class="text-3xl font-bold"><?= count($expiringSoon) ?></p>
                    <p class="text-sm text-red-100 mt-2">Within 30 days</p>
                </div>
                <div class="text-5xl opacity-50">⏰</div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Recent Sales -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Recent Sales</h2>
            
            <?php if (count($recentSales) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recentSales as $sale): ?>
                        <div class="flex justify-between items-center border-b pb-3">
                            <div>
                                <p class="font-semibold"><?= $sale['invoice_number'] ?></p>
                                <p class="text-sm text-gray-600"><?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-indigo-600">৳<?= number_format($sale['total'], 2) ?></p>
                                <a href="sales.php?invoice=<?= $sale['invoice_number'] ?>" 
                                   class="text-sm text-indigo-600 hover:underline">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="sales.php" class="block text-center text-indigo-600 font-semibold mt-4 hover:underline">
                    View All Sales →
                </a>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No sales yet today</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Alerts -->
        <div class="space-y-6">
            <!-- Low Stock Alert -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-orange-600">⚠️ Low Stock Alert</h2>
                
                <?php if (count($lowStock) > 0): ?>
                    <div class="space-y-2">
                        <?php foreach ($lowStock as $item): ?>
                            <div class="flex justify-between items-center text-sm border-b pb-2">
                                <span class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></span>
                                <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded-full">
                                    <?= $item['quantity'] ?> left
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">All items well stocked ✅</p>
                <?php endif; ?>
            </div>

            <!-- Expiring Soon Alert -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-red-600">⏰ Expiring Soon</h2>
                
                <?php if (count($expiringSoon) > 0): ?>
                    <div class="space-y-2">
                        <?php foreach ($expiringSoon as $item): ?>
                            <div class="flex justify-between items-center text-sm border-b pb-2">
                                <div>
                                    <p class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                    <p class="text-xs text-gray-600">Batch: <?= $item['batch_no'] ?></p>
                                </div>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">
                                    <?= date('d M Y', strtotime($item['expiry_date'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">No items expiring soon ✅</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Action -->
    <div class="mt-8 text-center">
        <a href="pos.php" 
           class="inline-block bg-indigo-600 text-white px-12 py-4 rounded-xl text-xl font-bold hover:bg-indigo-700 shadow-lg">
            🛒 Start New Sale
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>