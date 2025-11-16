<?php
$pageTitle = 'My Sales - Salesman';
include 'includes/header.php';

// Date filter
$date_from = sanitize($_GET['from'] ?? date('Y-m-d'));
$date_to = sanitize($_GET['to'] ?? date('Y-m-d'));

// Get sales
$stmt = $pdo->prepare("
    SELECT 
        ps.*,
        u.name as customer_name,
        COUNT(psi.id) as item_count
    FROM pos_sales ps
    LEFT JOIN users u ON ps.customer_id = u.id
    LEFT JOIN pos_sale_items psi ON ps.id = psi.sale_id
    WHERE ps.salesman_id = ?
    AND DATE(ps.created_at) BETWEEN ? AND ?
    GROUP BY ps.id
    ORDER BY ps.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$sales = $stmt->fetchAll();

// Calculate totals
$total_sales = count($sales);
$total_amount = array_sum(array_column($sales, 'total'));

// Get specific invoice details if requested
$selectedSale = null;
if (isset($_GET['invoice'])) {
    $invoice = sanitize($_GET['invoice']);
    
    $stmt = $pdo->prepare("
        SELECT ps.*, u.name as customer_name, u.email as customer_email
        FROM pos_sales ps
        LEFT JOIN users u ON ps.customer_id = u.id
        WHERE ps.invoice_number = ? AND ps.salesman_id = ?
    ");
    $stmt->execute([$invoice, $_SESSION['user_id']]);
    $selectedSale = $stmt->fetch();
    
    if ($selectedSale) {
        $stmt = $pdo->prepare("
            SELECT psi.*, m.name as medicine_name, m.generic_name
            FROM pos_sale_items psi
            JOIN inventory i ON psi.inventory_id = i.id
            JOIN medicines m ON i.medicine_id = m.id
            WHERE psi.sale_id = ?
        ");
        $stmt->execute([$selectedSale['id']]);
        $selectedSale['items'] = $stmt->fetchAll();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">My Sales History</h1>

    <!-- Stats Summary -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Total Sales</p>
            <p class="text-4xl font-bold text-indigo-600"><?= $total_sales ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Total Amount</p>
            <p class="text-4xl font-bold text-green-600">৳<?= number_format($total_amount, 2) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Average Sale</p>
            <p class="text-4xl font-bold text-blue-600">৳<?= $total_sales > 0 ? number_format($total_amount / $total_sales, 2) : '0.00' ?></p>
        </div>
    </div>

    <!-- Filter -->
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
                <button type="submit" class="w-full bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                    Filter
                </button>
            </div>
            <div class="flex items-end">
                <a href="sales.php" class="block w-full text-center border border-gray-300 px-6 py-2 rounded-lg hover:bg-gray-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="grid lg:grid-cols-<?= $selectedSale ? '2' : '1' ?> gap-8">
        <!-- Sales List -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Sales (<?= count($sales) ?>)</h2>

            <?php if (count($sales) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($sales as $sale): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition
                            <?= $selectedSale && $selectedSale['id'] === $sale['id'] ? 'border-indigo-600 bg-indigo-50' : '' ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="font-bold text-lg"><?= $sale['invoice_number'] ?></p>
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                            <?= strtoupper($sale['payment_method']) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?>
                                    </p>
                                    <?php if ($sale['customer_name']): ?>
                                        <p class="text-sm text-indigo-600">👤 <?= htmlspecialchars($sale['customer_name']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500 mt-1"><?= $sale['item_count'] ?> items</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-indigo-600">৳<?= number_format($sale['total'], 2) ?></p>
                                    <a href="?invoice=<?= $sale['invoice_number'] ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" 
                                       class="text-sm text-indigo-600 hover:underline">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">📊</div>
                    <p class="text-gray-600">No sales found for this period</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Invoice Details -->
        <?php if ($selectedSale): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Invoice Details</h2>
                    <button onclick="printInvoice()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        🖨️ Print
                    </button>
                </div>

                <div id="invoicePrint">
                    <!-- Invoice Header -->
                    <div class="border-b pb-4 mb-4">
                        <div class="flex justify-between">
                            <div>
                                <h3 class="text-xl font-bold">QuickMed</h3>
                                <p class="text-gray-600"><?= htmlspecialchars($salesman['shop_name']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold"><?= $selectedSale['invoice_number'] ?></p>
                                <p class="text-sm text-gray-600"><?= date('d M Y, h:i A', strtotime($selectedSale['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <?php if ($selectedSale['customer_name']): ?>
                        <div class="bg-gray-50 rounded-lg p-3 mb-4">
                            <p class="text-sm text-gray-600">Customer</p>
                            <p class="font-semibold"><?= htmlspecialchars($selectedSale['customer_name']) ?></p>
                            <?php if ($selectedSale['customer_email']): ?>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($selectedSale['customer_email']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Items -->
                    <div class="mb-4">
                        <h4 class="font-bold mb-3">Items</h4>
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left p-2">Medicine</th>
                                    <th class="text-center p-2">Qty</th>
                                    <th class="text-right p-2">Price</th>
                                    <th class="text-right p-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedSale['items'] as $item): ?>
                                    <tr class="border-b">
                                        <td class="p-2">
                                            <p class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                            <p class="text-xs text-gray-600"><?= htmlspecialchars($item['generic_name']) ?></p>
                                        </td>
                                        <td class="text-center p-2"><?= $item['quantity'] ?></td>
                                        <td class="text-right p-2">৳<?= number_format($item['price'], 2) ?></td>
                                        <td class="text-right p-2 font-semibold">৳<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-semibold">৳<?= number_format($selectedSale['subtotal'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">VAT</span>
                            <span class="font-semibold">৳<?= number_format($selectedSale['vat'], 2) ?></span>
                        </div>
                        <?php if ($selectedSale['discount'] > 0): ?>
                            <div class="flex justify-between text-red-600">
                                <span>Discount</span>
                                <span class="font-semibold">-৳<?= number_format($selectedSale['discount'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-xl font-bold border-t pt-2">
                            <span>Total</span>
                            <span class="text-indigo-600">৳<?= number_format($selectedSale['total'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Payment Method</span>
                            <span class="font-semibold uppercase"><?= $selectedSale['payment_method'] ?></span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="text-center mt-6 pt-6 border-t text-sm text-gray-600">
                        <p>Cashier: <?= htmlspecialchars($salesman['name']) ?></p>
                        <p class="mt-2">Thank you for your purchase!</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function printInvoice() {
    const printContent = document.getElementById('invoicePrint').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}
</script>

<?php include 'includes/footer.php'; ?>