<?php
$pageTitle = 'Dashboard - Admin';
include 'includes/header.php';

// System Overview Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$total_shops = $pdo->query("SELECT COUNT(*) FROM shops WHERE is_active = 1")->fetchColumn();
$total_medicines = $pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();

// Sales Stats
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(total), 0) as total_revenue
    FROM pos_sales
");
$salesStats = $stmt->fetch();

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total), 0) as total_revenue
    FROM orders
");
$orderStats = $stmt->fetch();

$total_revenue = $salesStats['total_revenue'] + $orderStats['total_revenue'];

// Today's Activity
$stmt = $pdo->query("
    SELECT COUNT(*) FROM pos_sales WHERE DATE(created_at) = CURDATE()
");
$today_pos_sales = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()
");
$today_orders = $stmt->fetchColumn();

// Sales by Shop (for pie chart)
$stmt = $pdo->query("
    SELECT 
        s.name as shop_name,
        COUNT(ps.id) as sales_count,
        COALESCE(SUM(ps.total), 0) as total_amount
    FROM shops s
    LEFT JOIN pos_sales ps ON s.id = ps.shop_id
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY total_amount DESC
");
$salesByShop = $stmt->fetchAll();

// Recent Users
$stmt = $pdo->query("
    SELECT u.*, s.name as shop_name 
    FROM users u 
    LEFT JOIN shops s ON u.shop_id = s.id
    ORDER BY u.created_at DESC 
    LIMIT 5
");
$recentUsers = $stmt->fetchAll();

// System Health
$total_inventory = $pdo->query("SELECT SUM(quantity) FROM inventory")->fetchColumn();
$low_stock_count = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity > 0 AND quantity <= 10")->fetchColumn();
$out_of_stock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity = 0")->fetchColumn();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">System Dashboard</h1>

    <!-- Overview Cards -->
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 mb-2">Total Users</p>
                    <p class="text-4xl font-bold"><?= $total_users ?></p>
                    <p class="text-sm text-blue-100 mt-2"><?= $total_customers ?> customers</p>
                </div>
                <div class="text-6xl opacity-50">👥</div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 mb-2">Total Revenue</p>
                    <p class="text-4xl font-bold">৳<?= number_format($total_revenue / 1000, 0) ?>K</p>
                    <p class="text-sm text-green-100 mt-2">All time</p>
                </div>
                <div class="text-6xl opacity-50">💰</div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 mb-2">Active Shops</p>
                    <p class="text-4xl font-bold"><?= $total_shops ?></p>
                    <p class="text-sm text-purple-100 mt-2">Branches</p>
                </div>
                <div class="text-6xl opacity-50">🏪</div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 mb-2">Medicine Catalog</p>
                    <p class="text-4xl font-bold"><?= $total_medicines ?></p>
                    <p class="text-sm text-orange-100 mt-2">Unique medicines</p>
                </div>
                <div class="text-6xl opacity-50">💊</div>
            </div>
        </div>
    </div>

    <!-- Today's Activity -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="font-bold text-lg mb-2">Today's POS Sales</h3>
            <p class="text-4xl font-bold text-blue-600"><?= $today_pos_sales ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="font-bold text-lg mb-2">Today's Online Orders</h3>
            <p class="text-4xl font-bold text-green-600"><?= $today_orders ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="font-bold text-lg mb-2">Total Activity</h3>
            <p class="text-4xl font-bold text-purple-600"><?= $today_pos_sales + $today_orders ?></p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-8 mb-8">
        <!-- Sales by Shop Chart -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Sales by Shop</h2>
            <canvas id="shopSalesChart"></canvas>
        </div>

        <!-- System Health -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Inventory Health</h2>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between mb-2">
                        <span class="font-semibold">Total Stock</span>
                        <span class="text-2xl font-bold text-green-600"><?= number_format($total_inventory) ?></span>
                    </div>
                </div>
                <div class="border-t pt-4">
                    <div class="flex justify-between mb-2">
                        <span class="font-semibold">Low Stock Items</span>
                        <span class="text-2xl font-bold text-orange-600"><?= $low_stock_count ?></span>
                    </div>
                </div>
                <div class="border-t pt-4">
                    <div class="flex justify-between mb-2">
                        <span class="font-semibold">Out of Stock</span>
                        <span class="text-2xl font-bold text-red-600"><?= $out_of_stock ?></span>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <div class="text-center">
                        <div class="inline-block">
                            <?php
                            $health_percent = $total_inventory > 0 ? 
                                (($total_inventory - $low_stock_count - $out_of_stock) / $total_inventory) * 100 : 0;
                            ?>
                            <div class="text-5xl font-bold mb-2
                                <?= $health_percent >= 80 ? 'text-green-600' : 
                                    ($health_percent >= 60 ? 'text-orange-600' : 'text-red-600') ?>">
                                <?= round($health_percent) ?>%
                            </div>
                            <p class="text-gray-600">System Health</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shop Performance Table -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <h2 class="text-2xl font-bold mb-6">Shop Performance</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold">Shop Name</th>
                        <th class="px-6 py-3 text-center font-semibold">Total Sales</th>
                        <th class="px-6 py-3 text-right font-semibold">Revenue</th>
                        <th class="px-6 py-3 text-right font-semibold">Avg Sale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesByShop as $shop): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($shop['shop_name']) ?></td>
                            <td class="px-6 py-4 text-center"><?= $shop['sales_count'] ?></td>
                            <td class="px-6 py-4 text-right font-semibold text-green-600">
                                ৳<?= number_format($shop['total_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-600">
                                ৳<?= $shop['sales_count'] > 0 ? number_format($shop['total_amount'] / $shop['sales_count'], 2) : '0.00' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Recent Users</h2>
            <a href="users.php" class="text-red-600 font-semibold hover:underline">View All →</a>
        </div>
        <div class="space-y-3">
            <?php foreach ($recentUsers as $user): ?>
                <div class="flex justify-between items-center border-b pb-3">
                    <div class="flex items-center gap-3">
                        <img src="/quickmed/assets/images/uploads/profiles/<?= $user['profile_image'] ?>" 
                             class="w-10 h-10 rounded-full"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>'">
                        <div>
                            <p class="font-semibold"><?= htmlspecialchars($user['name']) ?></p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                            <?= match($user['role']) {
                                'admin' => 'bg-red-100 text-red-800',
                                'shop_admin' => 'bg-purple-100 text-purple-800',
                                'salesman' => 'bg-blue-100 text-blue-800',
                                default => 'bg-green-100 text-green-800'
                            } ?>">
                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                        </span>
                        <p class="text-xs text-gray-500 mt-1"><?= date('d M Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Shop Sales Pie Chart
const ctx = document.getElementById('shopSalesChart').getContext('2d');
const shopData = <?= json_encode($salesByShop) ?>;

new Chart(ctx, {
    type: 'pie',
    data: {
        labels: shopData.map(s => s.shop_name),
        datasets: [{
            data: shopData.map(s => parseFloat(s.total_amount)),
            backgroundColor: [
                'rgba(239, 68, 68, 0.7)',
                'rgba(59, 130, 246, 0.7)',
                'rgba(34, 197, 94, 0.7)',
                'rgba(168, 85, 247, 0.7)',
                'rgba(251, 146, 60, 0.7)',
                'rgba(236, 72, 153, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ৳' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>