<?php
$pageTitle = 'Inventory - Shop Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Delete stock
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ? AND shop_id = ?");
        $stmt->execute([$id, $shop_admin['shop_id']]);
        
        logActivity($pdo, $_SESSION['user_id'], 'STOCK_DELETED', 'inventory', $id, 'Stock item deleted');
        
        $success = 'Stock deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to delete stock';
    }
}

// Update stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $id = (int)$_POST['id'];
    $quantity = (int)$_POST['quantity'];
    $selling_price = (float)$_POST['selling_price'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE inventory 
            SET quantity = ?, selling_price = ?
            WHERE id = ? AND shop_id = ?
        ");
        $stmt->execute([$quantity, $selling_price, $id, $shop_admin['shop_id']]);
        
        logActivity($pdo, $_SESSION['user_id'], 'STOCK_UPDATED', 'inventory', $id, 'Stock updated');
        
        $success = 'Stock updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update stock';
    }
}

// Filters
$category = sanitize($_GET['category'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$stock_filter = sanitize($_GET['stock'] ?? 'all');

// Build query
$where = ["i.shop_id = ?"];
$params = [$shop_admin['shop_id']];

if ($category) {
    $where[] = "m.category = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(m.name LIKE ? OR m.generic_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($stock_filter === 'low') {
    $where[] = "i.quantity > 0 AND i.quantity <= 10";
} elseif ($stock_filter === 'out') {
    $where[] = "i.quantity = 0";
}

$whereClause = implode(' AND ', $where);

// Get inventory
$stmt = $pdo->prepare("
    SELECT 
        i.*,
        m.name as medicine_name,
        m.generic_name,
        m.company,
        m.category,
        m.image
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.id
    WHERE $whereClause
    ORDER BY m.name ASC
");
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Inventory Management</h1>
        <a href="add-stock.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700">
            ➕ Add Stock
        </a>
    </div>

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

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-4 gap-4">
            <input type="text" 
                   name="search" 
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search medicine..."
                   class="px-4 py-2 border border-gray-300 rounded-lg">
            
            <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="stock" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="all" <?= $stock_filter === 'all' ? 'selected' : '' ?>>All Stock</option>
                <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock (≤10)</option>
                <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
            </select>

            <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700">
                Filter
            </button>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-purple-50">
                    <tr>
                        <th class="px-6 py-4 text-left font-semibold">Medicine</th>
                        <th class="px-6 py-4 text-left font-semibold">Batch</th>
                        <th class="px-6 py-4 text-center font-semibold">Stock</th>
                        <th class="px-6 py-4 text-right font-semibold">Purchase</th>
                        <th class="px-6 py-4 text-right font-semibold">Selling</th>
                        <th class="px-6 py-4 text-center font-semibold">Expiry</th>
                        <th class="px-6 py-4 text-center font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <tr class="border-b hover:bg-gray-50" x-data="{ editing: false }">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="/quickmed/assets/images/uploads/products/<?= $item['image'] ?>" 
                                         class="w-12 h-12 object-cover rounded"
                                         onerror="this.src='https://via.placeholder.com/50?text=Med'">
                                    <div>
                                        <p class="font-semibold"><?= htmlspecialchars($item['medicine_name']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($item['generic_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($item['company']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm bg-gray-100 px-2 py-1 rounded">
                                    <?= htmlspecialchars($item['batch_no']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <template x-if="!editing">
                                    <span class="inline-block px-3 py-1 rounded-full font-semibold
                                        <?= $item['quantity'] === 0 ? 'bg-red-100 text-red-800' : 
                                            ($item['quantity'] <= 10 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') ?>">
                                        <?= $item['quantity'] ?>
                                    </span>
                                </template>
                                <template x-if="editing">
                                    <input type="number" 
                                           x-model="quantity" 
                                           min="0"
                                           class="w-20 px-2 py-1 border border-gray-300 rounded text-center">
                                </template>
                            </td>
                            <td class="px-6 py-4 text-right">৳<?= number_format($item['purchase_price'], 2) ?></td>
                            <td class="px-6 py-4 text-right">
                                <template x-if="!editing">
                                    <span>৳<?= number_format($item['selling_price'], 2) ?></span>
                                </template>
                                <template x-if="editing">
                                    <input type="number" 
                                           x-model="selling_price" 
                                           step="0.01"
                                           min="0"
                                           class="w-24 px-2 py-1 border border-gray-300 rounded text-right">
                                </template>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-sm <?= strtotime($item['expiry_date']) <= strtotime('+30 days') ? 'text-red-600 font-semibold' : '' ?>">
                                    <?= date('M Y', strtotime($item['expiry_date'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div x-data="{ 
                                    quantity: <?= $item['quantity'] ?>, 
                                    selling_price: <?= $item['selling_price'] ?> 
                                }">
                                    <template x-if="!editing">
                                        <div class="flex gap-2 justify-center">
                                            <button @click="editing = true" 
                                                    class="text-blue-600 hover:underline text-sm">Edit</button>
                                            <a href="?delete=<?= $item['id'] ?>" 
                                               onclick="return confirm('Delete this stock?')"
                                               class="text-red-600 hover:underline text-sm">Delete</a>
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <form method="POST" class="flex gap-2 justify-center">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="quantity" x-model="quantity">
                                            <input type="hidden" name="selling_price" x-model="selling_price">
                                            <button type="submit" name="update_stock" 
                                                    class="text-green-600 hover:underline text-sm">Save</button>
                                            <button type="button" @click="editing = false" 
                                                    class="text-gray-600 hover:underline text-sm">Cancel</button>
                                        </form>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($inventory) === 0): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📦</div>
                <p class="text-gray-600">No inventory found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>