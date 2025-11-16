<?php
$pageTitle = 'Reports - Admin';
include 'includes/header.php';

// Date range
$date_from = sanitize($_GET['from'] ?? date('Y-m-01'));
$date_to = sanitize($_GET['to'] ?? date('Y-m-d'));

// Overall Sales Summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total) as total_revenue,
        SUM(vat) as total_vat,
        SUM(discount) as total_discount
    FROM pos_sales
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$posSales = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total) as total_revenue
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$onlineOrders = $stmt->fetch();

$total_transactions = $posSales['total_sales'] + $onlineOrders['total_orders'];
$total_revenue = $posSales['total_revenue'] + $onlineOrders['total_revenue'];

// Revenue by Shop
$stmt = $pdo->prepare("
    SELECT 
        s.name as shop_name,
        COUNT(ps.id) as pos_sales,
        COALESCE(SUM(ps.total), 0) as pos_revenue,
        COUNT(o.id) as online_orders,
        COALESCE(SUM(o.total), 0) as online_revenue
    FROM shops s
    LEFT JOIN pos_sales ps ON s.id = ps.shop_id AND DATE(ps.created_at) BETWEEN ? AND ?
    LEFT JOIN orders o ON s.id = o.shop_id AND DATE(o.created_at) BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY (COALESCE(SUM(ps.total), 0) + COALESCE(SUM(o.total), 0)) DESC
");
$stmt->execute([$date_from, $date_to, $date_from, $date_to]);
$shopRevenue = $stmt->fetchAll();

// Top Selling Medicines (Overall)
$stmt = $pdo->prepare("
    SELECT 
        m.name as medicine_name,
        m.generic_name,
        m.company,
        SUM(psi.quantity) as total_sold,
        SUM(psi.quantity * psi.price) as total_revenue
    FROM pos_sale_items psi
    JOIN pos_sales ps ON psi.sale_id = ps.id
    JOIN inventory i ON psi.inventory_id = i.id
    JOIN medicines m ON i.medicine_id = m.id
    WHERE DATE(ps.created_at) BETWEEN ? AND ?
    GROUP BY i.medicine_id
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute([$date_from, $date_to]);
$topMedicines = $stmt->fetchAll();

// User Registration Stats
$stmt = $pdo->prepare("
    SELECT role, COUNT(*) as count
    FROM users
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY role
");
$stmt->execute([$date_from, $date_to]);
$newUsers = $stmt->fetchAll();

// Daily Revenue Trend
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        SUM(total) as revenue
    FROM (
        SELECT created_at, total FROM pos_sales WHERE DATE(created_at) BETWEEN ? AND ?
        UNION ALL
        SELECT created_at, total FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
    ) combined
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$date_from, $date_to, $date_from, $date_to]);
$dailyRevenue = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">System Reports</h1>

    <!-- Date Filter -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-2">From Date</label>
                <input type="date" name="from" value="<?= $date_from ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">To Date</label>
                <input type="date" name="to" value="<?= $date_to ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                    Generate Report
                </button>
            </div>
            <div class="flex items-end">
                <button type="button" onclick="window.print()" 
                        class="w-full border border-red-600 text-red-600 px-6 py-2 rounded-lg hover:bg-red-50">
                    🖨️ Print
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-blue-100 mb-2">Total Transactions</p>
            <p class="text-4xl font-bold"><?= $total_transactions ?></p>
            <p class="text-sm text-blue-100 mt-2">
                POS: <?= $posSales['total_sales'] ?> | Online: <?= $onlineOrders['total_orders'] ?>
            </p>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-green-100 mb-2">Total Revenue</p>
            <p class="text-4xl font-bold">৳<?= number_format($total_revenue, 0) ?></p>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-purple-100 mb-2">VAT Collected</p>
            <p class="text-4xl font-bold">৳<?= number_format($posSales['total_vat'], 0) ?></p>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-orange-100 mb-2">Discounts Given</p>
            <p class="text-4xl font-bold">৳<?= number_format($posSales['total_discount'], 0) ?></p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid lg:grid-cols-2 gap-8 mb-8">
        <!-- Daily Revenue Chart -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Daily Revenue Trend</h2>
            <canvas id="revenueChart"></canvas>
        </div>

        <!-- Shop Performance Chart -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Revenue by Shop</h2>
            <canvas id="shopChart"></canvas>
        </div>
    </div>

    <!-- Tables -->
    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Top Medicines -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Top 10 Selling Medicines</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-red-50">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Medicine</th>
                            <th class="px-4 py-3 text-center">Qty</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topMedicines as $index => $med): ?>
                            <tr class="border-b">
                                <td class="px-4 py-3 font-bold"><?= $index + 1 ?></td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold"><?= htmlspecialchars($med['medicine_name']) ?></p>
                                    <p class="text-xs text-gray-600"><?= htmlspecialchars($med['generic_name']) ?></p>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold"><?= $med['total_sold'] ?></td>
                                <td class="px-4 py-3 text-right font-semibold text-green-600">
                                    ৳<?= number_format($med['total_revenue'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shop Revenue -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Revenue by Shop</h2>
            <div class="space-y-3">
                <?php foreach ($shopRevenue as $shop): ?>
                    <div class="border-b pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <p class="font-semibold"><?= htmlspecialchars($shop['shop_name']) ?></p>
                            <p class="text-xl font-bold text-red-600">
                                ৳<?= number_format($shop['pos_revenue'] + $shop['online_revenue'], 2) ?>
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                            <div class="bg-blue-50 p-2 rounded">
                                <span class="font-semibold">POS:</span> <?= $shop['pos_sales'] ?> sales (৳<?= number_format($shop['pos_revenue'], 0) ?>)
                            </div>
                            <div class="bg-green-50 p-2 rounded">
                                <span class="font-semibold">Online:</span> <?= $shop['online_orders'] ?> orders (৳<?= number_format($shop['online_revenue'], 0) ?>)
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Daily Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueData = <?= json_encode($dailyRevenue) ?>;

new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: revenueData.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
        datasets: [{
            label: 'Revenue (৳)',
            data: revenueData.map(d => parseFloat(d.revenue)),
            borderColor: 'rgb(220, 38, 38)',
            backgroundColor: 'rgba(220, 38, 38, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => '৳' + value.toLocaleString()
                }
            }
        }
    }
});

// Shop Revenue Chart
const shopCtx = document.getElementById('shopChart').getContext('2d');
const shopData = <?= json_encode($shopRevenue) ?>;

new Chart(shopCtx, {
    type: 'bar',
    data: {
        labels: shopData.map(s => s.shop_name),
        datasets: [{
            label: 'Revenue (৳)',
            data: shopData.map(s => parseFloat(s.pos_revenue) + parseFloat(s.online_revenue)),
            backgroundColor: 'rgba(220, 38, 38, 0.7)',
            borderColor: 'rgb(220, 38, 38)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => '৳' + value.toLocaleString()
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>