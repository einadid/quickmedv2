<?php
$pageTitle = 'My Addresses - QuickMed';
include 'includes/header.php';

$success = '';
$error = '';

// Add Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    $address_line = sanitize($_POST['address_line']);
    $city = sanitize($_POST['city']);
    $postal_code = sanitize($_POST['postal_code']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    try {
        // If setting as default, unset other defaults
        if ($is_default) {
            $stmt = $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO customer_addresses (user_id, address_line, city, postal_code, is_default)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $address_line, $city, $postal_code, $is_default]);
        
        $success = 'Address added successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to add address';
    }
}

// Delete Address
if (isset($_GET['delete'])) {
    $address_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM customer_addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$address_id, $_SESSION['user_id']]);
        
        $success = 'Address deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to delete address';
    }
}

// Set Default
if (isset($_GET['set_default'])) {
    $address_id = (int)$_GET['set_default'];
    
    try {
        $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        $pdo->prepare("UPDATE customer_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$address_id, $_SESSION['user_id']]);
        
        $success = 'Default address updated!';
    } catch (PDOException $e) {
        $error = 'Failed to update default address';
    }
}

// Get addresses
$stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8" x-data="{ showAddModal: false }">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">My Addresses</h1>
        <button @click="showAddModal = true" 
                class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
            ➕ Add New Address
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

    <?php if (count($addresses) > 0): ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($addresses as $address): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 relative">
                    <?php if ($address['is_default']): ?>
                        <span class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Default
                        </span>
                    <?php endif; ?>

                    <div class="mb-4">
                        <p class="text-xl mb-2">📍</p>
                        <p class="font-semibold text-lg mb-2"><?= htmlspecialchars($address['address_line']) ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($address['city']) ?> - <?= htmlspecialchars($address['postal_code']) ?></p>
                    </div>

                    <div class="flex gap-2">
                        <?php if (!$address['is_default']): ?>
                            <a href="?set_default=<?= $address['id'] ?>" 
                               class="flex-1 text-center bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700">
                                Set Default
                            </a>
                        <?php endif; ?>
                        <a href="?delete=<?= $address['id'] ?>" 
                           onclick="return confirm('Delete this address?')"
                           class="<?= $address['is_default'] ? 'flex-1' : '' ?> bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 text-center">
                            Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">📍</div>
            <p class="text-gray-600 text-xl mb-6">No addresses saved</p>
            <button @click="showAddModal = true" 
                    class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                Add Your First Address
            </button>
        </div>
    <?php endif; ?>

    <!-- Add Address Modal -->
    <div x-show="showAddModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Add New Address</h2>
                    <button @click="showAddModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Address Line *</label>
                        <textarea name="address_line" required rows="3"
                                  placeholder="House/Flat, Street, Area..."
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">City *</label>
                            <input type="text" name="city" required
                                   placeholder="e.g., Dhaka"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Postal Code *</label>
                            <input type="text" name="postal_code" required
                                   placeholder="e.g., 1212"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="is_default" id="is_default" class="mr-2">
                        <label for="is_default" class="text-sm">Set as default address</label>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_address"
                                class="flex-1 bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700">
                            Save Address
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
</div>

<?php include 'includes/footer.php'; ?>