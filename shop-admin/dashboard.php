<?php
$pageTitle = 'Dashboard - Shop Admin';
include 'includes/header.php';

$shop_id = $shop_admin['shop_id'];

// Today's stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(total), 0) as total_amount
    FROM pos_sales 
    WHERE shop_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$shop_id]);
$todayStats = $stmt->fetch();

// Total inventory value
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(quantity) as total_quantity,
        SUM(quantity * purchase_price) as purchase_value,
        SUM(quantity * selling_price) as selling_value
    FROM inventory 
    WHERE shop_id = ? AND quantity > 0
");
$stmt->execute([$shop_id]);
$inventoryStats = $stmt->fetch();

// Pending online orders
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM orders 
    WHERE shop_id = ? AND status = 'pending'
");
$stmt->execute([$shop_id]);
$pendingOrders = $stmt->fetchColumn();

// Low stock items
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM inventory 
    WHERE shop_id = ? AND quantity > 0 AND quantity <= 10
");
$stmt->execute([$shop_id]);
$lowStockCount = $stmt->fetchColumn();

// Last 7 days sales for chart
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        SUM(total) as total
    FROM pos_sales
    WHERE shop_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$shop_id]);
$salesChart = $stmt->fetchAll();

// Recent online orders
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, COUNT(oi.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.shop_id = ? AND o.status = 'pending'
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$shop_id]);
$recentOrders = $stmt->fetchAll();
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
                <div class="text-5xl opacity-50">💰</div>
            </div>
        </div>

        <!-- Total Stock -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 mb-2">Total Stock</p>
                    <p class="text-3xl font-bold"><?= number_format($inventoryStats['total_quantity']) ?></p>
                    <p class="text-sm text-green-100 mt-2"><?= $inventoryStats['total_items'] ?> unique items</p>
                </div>
                <div class="text-5xl opacity-50">📦</div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 mb-2">Pending Orders</p>
                    <p class="text-3xl font-bold"><?= $pendingOrders ?></p>
                    <p class="text-sm text-orange-100 mt-2">Need attention</p>
                </div>
                <div class="text-5xl opacity-50">🛍️</div>
            </div>
        </div>

        <!-- Low Stock -->
        <div class="bg-gradient-to-br from-red-500 to-red-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 mb-2">Low Stock Alert</p>
                    <p class="text-3xl font-bold"><?= $lowStockCount ?></p>
                    <p class="text-sm text-red-100 mt-2">Items ≤ 10 qty</p>
                </div>
                <div class="text-5xl opacity-50">⚠️</div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Sales Chart -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Last 7 Days Sales</h2>
            <canvas id="salesChart"></canvas>
        </div>

        <!-- Quick Stats -->
        <div class="space-y-6">
            <!-- Inventory Value -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">Inventory Value</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-600">Purchase Value</p>
                        <p class="text-2xl font-bold text-blue-600">৳<?= number_format($inventoryStats['purchase_value'], 2) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Selling Value</p>
                        <p class="text-2xl font-bold text-green-600">৳<?= number_format($inventoryStats['selling_value'], 2) ?></p>
                    </div>
                    <div class="border-t pt-3">
                        <p class="text-sm text-gray-600">Potential Profit</p>
                        <p class="text-2xl font-bold text-purple-600">
                            ৳<?= number_format($inventoryStats['selling_value'] - $inventoryStats['purchase_value'], 2) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Orders -->
    <?php if (count($recentOrders) > 0): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Pending Online Orders</h2>
                <a href="orders.php" class="text-purple-600 font-semibold hover:underline">View All →</a>
            </div>
            
            <div class="space-y-3">
                <?php foreach ($recentOrders as $order): ?>
                    <div class="border rounded-lg p-4 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-bold"><?= $order['order_number'] ?></p>
                                <p class="text-sm text-gray-600">
                                    <?= htmlspecialchars($order['customer_name']) ?> • 
                                    <?= $order['item_count'] ?> items
                                </p>
                                <p class="text-xs text-gray-500"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-purple-600">৳<?= number_format($order['total'], 2) ?></p>
                                <a href="orders.php?order=<?= $order['id'] ?>" 
                                   class="text-sm text-purple-600 hover:underline">Process →</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Sales Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesData = <?= json_encode($salesChart) ?>;

const labels = salesData.map(item => {
    const date = new Date(item.date);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});

const data = salesData.map(item => parseFloat(item.total));

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Sales (৳)',
            data: data,
            borderColor: 'rgb(147, 51, 234)',
            backgroundColor: 'rgba(147, 51, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '৳' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>