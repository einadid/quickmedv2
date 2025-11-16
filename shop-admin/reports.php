<?php
$pageTitle = 'Reports - Shop Admin';
include 'includes/header.php';

$shop_id = $shop_admin['shop_id'];

// Date range
$date_from = sanitize($_GET['from'] ?? date('Y-m-01')); // First day of current month
$date_to = sanitize($_GET['to'] ?? date('Y-m-d'));

// Sales Summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        SUM(subtotal) as total_subtotal,
        SUM(vat) as total_vat,
        SUM(discount) as total_discount,
        SUM(total) as total_amount
    FROM pos_sales 
    WHERE shop_id = ? 
    AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$shop_id, $date_from, $date_to]);
$salesSummary = $stmt->fetch();

// Top Selling Products
$stmt = $pdo->prepare("
    SELECT 
        m.name as medicine_name,
        m.generic_name,
        SUM(psi.quantity) as total_quantity,
        SUM(psi.quantity * psi.price) as total_revenue
    FROM pos_sale_items psi
    JOIN pos_sales ps ON psi.sale_id = ps.id
    JOIN inventory i ON psi.inventory_id = i.id
    JOIN medicines m ON i.medicine_id = m.id
    WHERE ps.shop_id = ?
    AND DATE(ps.created_at) BETWEEN ? AND ?
    GROUP BY i.medicine_id
    ORDER BY total_quantity DESC
    LIMIT 10
");
$stmt->execute([$shop_id, $date_from, $date_to]);
$topProducts = $stmt->fetchAll();

// Sales by Salesman
$stmt = $pdo->prepare("
    SELECT 
        u.name as salesman_name,
        COUNT(ps.id) as total_sales,
        SUM(ps.total) as total_amount
    FROM pos_sales ps
    JOIN users u ON ps.salesman_id = u.id
    WHERE ps.shop_id = ?
    AND DATE(ps.created_at) BETWEEN ? AND ?
    GROUP BY ps.salesman_id
    ORDER BY total_amount DESC
");
$stmt->execute([$shop_id, $date_from, $date_to]);
$salesByStaff = $stmt->fetchAll();

// Daily Sales Trend
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as sales_count,
        SUM(total) as total_amount
    FROM pos_sales
    WHERE shop_id = ?
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$shop_id, $date_from, $date_to]);
$dailySales = $stmt->fetchAll();

// Online Orders Summary
$stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(total) as total
    FROM orders
    WHERE shop_id = ?
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$shop_id, $date_from, $date_to]);
$ordersByStatus = $stmt->fetchAll();

// Low Stock Report
$stmt = $pdo->prepare("
    SELECT 
        m.name as medicine_name,
        i.quantity,
        i.batch_no,
        i.expiry_date
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.id
    WHERE i.shop_id = ? AND i.quantity > 0 AND i.quantity <= 10
    ORDER BY i.quantity ASC
");
$stmt->execute([$shop_id]);
$lowStockItems = $stmt->fetchAll();

// Expiring Soon Report
$stmt = $pdo->prepare("
    SELECT 
        m.name as medicine_name,
        i.quantity,
        i.batch_no,
        i.expiry_date
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.id
    WHERE i.shop_id = ? 
    AND i.quantity > 0 
    AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    ORDER BY i.expiry_date ASC
");
$stmt->execute([$shop_id]);
$expiringItems = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Reports & Analytics</h1>

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
                <button type="submit" class="w-full bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                    Generate Report
                </button>
            </div>
            <div class="flex items-end">
                <button type="button" onclick="window.print()" 
                        class="w-full border border-purple-600 text-purple-600 px-6 py-2 rounded-lg hover:bg-purple-50">
                    🖨️ Print
                </button>
            </div>
        </form>
    </div>

    <!-- Sales Summary Cards -->
    <div class="grid md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-blue-100 mb-2">Total Sales</p>
            <p class="text-4xl font-bold"><?= $salesSummary['total_sales'] ?></p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-green-100 mb-2">Revenue</p>
            <p class="text-4xl font-bold">৳<?= number_format($salesSummary['total_amount'], 0) ?></p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-purple-100 mb-2">VAT Collected</p>
            <p class="text-4xl font-bold">৳<?= number_format($salesSummary['total_vat'], 0) ?></p>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6">
            <p class="text-orange-100 mb-2">Discounts</p>
            <p class="text-4xl font-bold">৳<?= number_format($salesSummary['total_discount'], 0) ?></p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid lg:grid-cols-2 gap-8 mb-8">
        <!-- Daily Sales Chart -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Daily Sales Trend</h2>
            <canvas id="dailySalesChart"></canvas>
        </div>

        <!-- Online Orders by Status -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Online Orders by Status</h2>
            <canvas id="orderStatusChart"></canvas>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="grid lg:grid-cols-2 gap-8 mb-8">
        <!-- Top Selling Products -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Top 10 Selling Products</h2>
            <?php if (count($topProducts) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-purple-50">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Medicine</th>
                                <th class="px-4 py-3 text-center">Qty Sold</th>
                                <th class="px-4 py-3 text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-3 font-bold"><?= $index + 1 ?></td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold"><?= htmlspecialchars($product['medicine_name']) ?></p>
                                        <p class="text-xs text-gray-600"><?= htmlspecialchars($product['generic_name']) ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold"><?= $product['total_quantity'] ?></td>
                                    <td class="px-4 py-3 text-right font-semibold text-green-600">
                                        ৳<?= number_format($product['total_revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-8">No sales data</p>
            <?php endif; ?>
        </div>

        <!-- Sales by Staff -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6">Sales by Salesman</h2>
            <?php if (count($salesByStaff) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($salesByStaff as $staff): ?>
                        <div class="border-b pb-3">
                            <div class="flex justify-between items-center mb-2">
                                <p class="font-semibold"><?= htmlspecialchars($staff['salesman_name']) ?></p>
                                <p class="text-xl font-bold text-purple-600">৳<?= number_format($staff['total_amount'], 2) ?></p>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span><?= $staff['total_sales'] ?> sales</span>
                                <span>Avg: ৳<?= number_format($staff['total_amount'] / $staff['total_sales'], 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-8">No sales data</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inventory Alerts -->
    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Low Stock -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6 text-orange-600">⚠️ Low Stock Items (<?= count($lowStockItems) ?>)</h2>
            <?php if (count($lowStockItems) > 0): ?>
                <div class="max-h-96 overflow-y-auto space-y-2">
                    <?php foreach ($lowStockItems as $item): ?>
                        <div class="border-b pb-2 flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-sm"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                <p class="text-xs text-gray-600">Batch: <?= $item['batch_no'] ?></p>
                            </div>
                            <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded-full text-sm font-semibold">
                                <?= $item['quantity'] ?> left
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-8">All items well stocked ✅</p>
            <?php endif; ?>
        </div>

        <!-- Expiring Soon -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold mb-6 text-red-600">⏰ Expiring Soon (<?= count($expiringItems) ?>)</h2>
            <?php if (count($expiringItems) > 0): ?>
                <div class="max-h-96 overflow-y-auto space-y-2">
                    <?php foreach ($expiringItems as $item): ?>
                        <div class="border-b pb-2 flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-sm"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                <p class="text-xs text-gray-600">
                                    Batch: <?= $item['batch_no'] ?> • Qty: <?= $item['quantity'] ?>
                                </p>
                            </div>
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-semibold">
                                <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-8">No items expiring soon ✅</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Daily Sales Chart
const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
const dailySalesData = <?= json_encode($dailySales) ?>;

new Chart(dailySalesCtx, {
    type: 'bar',
    data: {
        labels: dailySalesData.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
        datasets: [{
            label: 'Sales Amount (৳)',
            data: dailySalesData.map(d => parseFloat(d.total_amount)),
            backgroundColor: 'rgba(147, 51, 234, 0.7)',
            borderColor: 'rgb(147, 51, 234)',
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

// Order Status Chart
const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
const orderStatusData = <?= json_encode($ordersByStatus) ?>;

new Chart(orderStatusCtx, {
    type: 'doughnut',
    data: {
        labels: orderStatusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
        datasets: [{
            data: orderStatusData.map(d => parseInt(d.count)),
            backgroundColor: [
                'rgba(234, 179, 8, 0.7)',   // pending - yellow
                'rgba(59, 130, 246, 0.7)',  // processing - blue
                'rgba(147, 51, 234, 0.7)',  // shipped - purple
                'rgba(34, 197, 94, 0.7)',   // delivered - green
                'rgba(239, 68, 68, 0.7)'    // cancelled - red
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>