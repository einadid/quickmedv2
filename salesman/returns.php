<?php
$pageTitle = 'Returns - Salesman';
include 'includes/header.php';

$error = '';
$success = '';

// Process return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $invoice_number = sanitize($_POST['invoice_number']);
    $return_items = $_POST['return_items'] ?? [];
    $reason = sanitize($_POST['reason']);
    
    if (empty($return_items)) {
        $error = 'Please select items to return';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get sale
            $stmt = $pdo->prepare("SELECT * FROM pos_sales WHERE invoice_number = ? AND shop_id = ?");
            $stmt->execute([$invoice_number, $salesman['shop_id']]);
            $sale = $stmt->fetch();
            
            if (!$sale) {
                throw new Exception('Invalid invoice');
            }
            
            // Process each return item
            foreach ($return_items as $item_id => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity <= 0) continue;
                
                // Get sale item
                $stmt = $pdo->prepare("SELECT * FROM pos_sale_items WHERE id = ? AND sale_id = ?");
                $stmt->execute([$item_id, $sale['id']]);
                $saleItem = $stmt->fetch();
                
                if (!$saleItem || $quantity > $saleItem['quantity']) {
                    throw new Exception('Invalid return quantity');
                }
                
                // Update inventory (add back)
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $saleItem['inventory_id']]);
                
                // Update sale item quantity or delete if full return
                if ($quantity === $saleItem['quantity']) {
                    $stmt = $pdo->prepare("DELETE FROM pos_sale_items WHERE id = ?");
                    $stmt->execute([$item_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE pos_sale_items SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity, $item_id]);
                }
            }
            
            // Update sale total
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(quantity * price), 0) as new_subtotal
                FROM pos_sale_items
                WHERE sale_id = ?
            ");
            $stmt->execute([$sale['id']]);
            $new_subtotal = $stmt->fetchColumn();
            
            if ($new_subtotal > 0) {
                $vat_percent = $sale['subtotal'] > 0 ? ($sale['vat'] / $sale['subtotal']) : 0;
                $discount_percent = $sale['subtotal'] > 0 ? ($sale['discount'] / $sale['subtotal']) : 0;
                
                $new_vat = $new_subtotal * $vat_percent;
                $new_discount = $new_subtotal * $discount_percent;
                $new_total = $new_subtotal + $new_vat - $new_discount;
                
                $stmt = $pdo->prepare("
                    UPDATE pos_sales 
                    SET subtotal = ?, vat = ?, discount = ?, total = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_subtotal, $new_vat, $new_discount, $new_total, $sale['id']]);
            } else {
                // Delete entire sale if all items returned
                $stmt = $pdo->prepare("DELETE FROM pos_sales WHERE id = ?");
                $stmt->execute([$sale['id']]);
            }
            
            // Log activity
            logActivity($pdo, $_SESSION['user_id'], 'RETURN_PROCESSED', 'pos_sales', $sale['id'], "Return for invoice: $invoice_number - Reason: $reason");
            
            $pdo->commit();
            $success = 'Return processed successfully!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Search invoice
$searchedSale = null;
if (isset($_GET['search'])) {
    $search_invoice = sanitize($_GET['invoice']);
    
    $stmt = $pdo->prepare("
        SELECT * FROM pos_sales 
        WHERE invoice_number = ? AND shop_id = ?
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$search_invoice, $salesman['shop_id']]);
    $searchedSale = $stmt->fetch();
    
    if ($searchedSale) {
        $stmt = $pdo->prepare("
            SELECT psi.*, m.name as medicine_name, m.generic_name
            FROM pos_sale_items psi
            JOIN inventory i ON psi.inventory_id = i.id
            JOIN medicines m ON i.medicine_id = m.id
            WHERE psi.sale_id = ?
        ");
        $stmt->execute([$searchedSale['id']]);
        $searchedSale['items'] = $stmt->fetchAll();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Returns Management</h1>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Search Invoice -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Search Invoice</h2>
        <form method="GET" class="flex gap-4">
            <input type="text" 
                   name="invoice" 
                   placeholder="Enter invoice number..." 
                   required
                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg">
            <button type="submit" 
                    name="search" 
                    class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                Search
            </button>
        </form>
        <p class="text-sm text-gray-500 mt-2">ℹ️ You can only process returns for sales within last 7 days</p>
    </div>

    <!-- Return Form -->
    <?php if ($searchedSale): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Process Return - <?= $searchedSale['invoice_number'] ?></h2>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="grid md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-600">Sale Date</p>
                        <p class="font-semibold"><?= date('d M Y, h:i A', strtotime($searchedSale['created_at'])) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Payment Method</p>
                        <p class="font-semibold uppercase"><?= $searchedSale['payment_method'] ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Total Amount</p>
                        <p class="font-semibold text-lg">৳<?= number_format($searchedSale['total'], 2) ?></p>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="invoice_number" value="<?= $searchedSale['invoice_number'] ?>">
                
                <h3 class="font-bold mb-3">Select Items to Return</h3>
                <div class="space-y-3 mb-6">
                    <?php foreach ($searchedSale['items'] as $item): ?>
                        <div class="border rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($item['generic_name']) ?></p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Sold: <?= $item['quantity'] ?> × ৳<?= number_format($item['price'], 2) ?> = 
                                        ৳<?= number_format($item['quantity'] * $item['price'], 2) ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label class="text-sm font-semibold">Return Qty:</label>
                                    <input type="number" 
                                           name="return_items[<?= $item['id'] ?>]" 
                                           min="0" 
                                           max="<?= $item['quantity'] ?>"
                                           value="0"
                                           class="w-20 px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-2">Return Reason</label>
                    <textarea name="reason" 
                              required
                              rows="3"
                              placeholder="Enter reason for return..."
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg"></textarea>
                </div>

                <button type="submit" 
                        name="process_return"
                        class="w-full bg-red-600 text-white py-3 rounded-lg font-bold hover:bg-red-700">
                    🔄 Process Return
                </button>
            </form>
        </div>
    <?php elseif (isset($_GET['search'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">❌</div>
            <p class="text-gray-600 text-xl">Invoice not found or return period expired</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>