<?php
$pageTitle = 'Shop Management - Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Add Shop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shop'])) {
    $name = sanitize($_POST['name']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO shops (name, address, phone) VALUES (?, ?, ?)");
        $stmt->execute([$name, $address, $phone]);
        
        logActivity($pdo, $_SESSION['user_id'], 'SHOP_CREATED', 'shops', $pdo->lastInsertId(), "Shop: $name");
        
        $success = 'Shop created successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to create shop';
    }
}

// Update Shop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop'])) {
    $shop_id = (int)$_POST['shop_id'];
    $name = sanitize($_POST['name']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    
    try {
        $stmt = $pdo->prepare("UPDATE shops SET name = ?, address = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $address, $phone, $shop_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'SHOP_UPDATED', 'shops', $shop_id, "Shop updated: $name");
        
        $success = 'Shop updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update shop';
    }
}

// Toggle Shop Status
if (isset($_GET['toggle'])) {
    $shop_id = (int)$_GET['toggle'];
    
    try {
        $stmt = $pdo->prepare("UPDATE shops SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$shop_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'SHOP_TOGGLED', 'shops', $shop_id, 'Shop status toggled');
        
        $success = 'Shop status updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update shop status';
    }
}

// Delete Shop
if (isset($_GET['delete'])) {
    $shop_id = (int)$_GET['delete'];
    
    try {
        // Check if shop has users or inventory
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE shop_id = ?");
        $stmt->execute([$shop_id]);
        $user_count = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE shop_id = ?");
        $stmt->execute([$shop_id]);
        $inventory_count = $stmt->fetchColumn();
        
        if ($user_count > 0 || $inventory_count > 0) {
            $error = 'Cannot delete shop with assigned users or inventory. Please reassign or remove them first.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM shops WHERE id = ?");
            $stmt->execute([$shop_id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'SHOP_DELETED', 'shops', $shop_id, 'Shop deleted');
            
            $success = 'Shop deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete shop';
    }
}

// Get all shops with stats
$stmt = $pdo->query("
    SELECT 
        s.*,
        COUNT(DISTINCT u.id) as user_count,
        COUNT(DISTINCT i.id) as inventory_count,
        COALESCE(SUM(i.quantity), 0) as total_stock,
        COALESCE(SUM(ps.total), 0) as total_sales
    FROM shops s
    LEFT JOIN users u ON s.id = u.shop_id
    LEFT JOIN inventory i ON s.id = i.shop_id
    LEFT JOIN pos_sales ps ON s.id = ps.shop_id
    GROUP BY s.id
    ORDER BY s.name ASC
");
$shops = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8" x-data="{ showAddModal: false, editShop: null }">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Shop Management</h1>
        <button @click="showAddModal = true" 
                class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700">
            ➕ Add New Shop
        </button>
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

    <!-- Shops Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($shops as $shop): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($shop['name']) ?></h3>
                            <p class="text-sm text-gray-600 mb-1">📍 <?= htmlspecialchars($shop['address']) ?></p>
                            <p class="text-sm text-gray-600">📞 <?= htmlspecialchars($shop['phone']) ?></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            <?= $shop['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $shop['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3 mb-4 pt-4 border-t">
                        <div class="bg-blue-50 rounded-lg p-3">
                            <p class="text-xs text-blue-600 mb-1">Staff</p>
                            <p class="text-2xl font-bold text-blue-700"><?= $shop['user_count'] ?></p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3">
                            <p class="text-xs text-green-600 mb-1">Stock Items</p>
                            <p class="text-2xl font-bold text-green-700"><?= $shop['inventory_count'] ?></p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3">
                            <p class="text-xs text-purple-600 mb-1">Total Stock</p>
                            <p class="text-2xl font-bold text-purple-700"><?= number_format($shop['total_stock']) ?></p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3">
                            <p class="text-xs text-orange-600 mb-1">Total Sales</p>
                            <p class="text-lg font-bold text-orange-700">৳<?= number_format($shop['total_sales'] / 1000, 0) ?>K</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button @click="editShop = <?= htmlspecialchars(json_encode($shop)) ?>"
                                class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700">
                            Edit
                        </button>
                        <a href="?toggle=<?= $shop['id'] ?>"
                           class="flex-1 bg-orange-600 text-white py-2 rounded-lg text-sm text-center hover:bg-orange-700">
                            <?= $shop['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </a>
                        <?php if ($shop['user_count'] == 0 && $shop['inventory_count'] == 0): ?>
                            <a href="?delete=<?= $shop['id'] ?>"
                               onclick="return confirm('Delete this shop?')"
                               class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">
                                🗑️
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Shop Modal -->
    <div x-show="showAddModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Add New Shop</h2>
                    <button @click="showAddModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Shop Name *</label>
                        <input type="text" name="name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Address *</label>
                        <textarea name="address" required rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Phone *</label>
                        <input type="tel" name="phone" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_shop"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Create Shop
                        </button>
                        <button type="button" @click="showAddModal = false"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Shop Modal -->
    <div x-show="editShop" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Edit Shop</h2>
                    <button @click="editShop = null" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="shop_id" x-model="editShop?.id">
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Shop Name *</label>
                        <input type="text" name="name" x-model="editShop.name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Address *</label>
                        <textarea name="address" x-model="editShop.address" required rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Phone *</label>
                        <input type="tel" name="phone" x-model="editShop.phone" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="update_shop"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Update Shop
                        </button>
                        <button type="button" @click="editShop = null"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>