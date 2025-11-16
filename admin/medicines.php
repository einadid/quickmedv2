<?php
$pageTitle = 'Medicine Catalog - Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Add Medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $name = sanitize($_POST['name']);
    $generic_name = sanitize($_POST['generic_name']);
    $company = sanitize($_POST['company']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    
    // Handle image upload
    $image = 'medicine-default.jpg';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_result = uploadImage($_FILES['image'], 'products');
        if ($upload_result['success']) {
            $image = $upload_result['filename'];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO medicines (name, generic_name, company, category, description, image)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $generic_name, $company, $category, $description, $image]);
        
        logActivity($pdo, $_SESSION['user_id'], 'MEDICINE_CREATED', 'medicines', $pdo->lastInsertId(), "Medicine: $name");
        
        $success = 'Medicine added successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to add medicine';
    }
}

// Update Medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_medicine'])) {
    $medicine_id = (int)$_POST['medicine_id'];
    $name = sanitize($_POST['name']);
    $generic_name = sanitize($_POST['generic_name']);
    $company = sanitize($_POST['company']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    
    // Handle image upload
    $image = $_POST['current_image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_result = uploadImage($_FILES['image'], 'products');
        if ($upload_result['success']) {
            $image = $upload_result['filename'];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE medicines 
            SET name = ?, generic_name = ?, company = ?, category = ?, description = ?, image = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $generic_name, $company, $category, $description, $image, $medicine_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'MEDICINE_UPDATED', 'medicines', $medicine_id, "Medicine updated: $name");
        
        $success = 'Medicine updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update medicine';
    }
}

// Delete Medicine
if (isset($_GET['delete'])) {
    $medicine_id = (int)$_GET['delete'];
    
    try {
        // Check if medicine is in inventory
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE medicine_id = ?");
        $stmt->execute([$medicine_id]);
        $inventory_count = $stmt->fetchColumn();
        
        if ($inventory_count > 0) {
            $error = 'Cannot delete medicine that exists in shop inventory!';
        } else {
            $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
            $stmt->execute([$medicine_id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'MEDICINE_DELETED', 'medicines', $medicine_id, 'Medicine deleted');
            
            $success = 'Medicine deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete medicine';
    }
}

// Filters
$category = sanitize($_GET['category'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = [];
$params = [];

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(name LIKE ? OR generic_name LIKE ? OR company LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get medicines
$stmt = $pdo->prepare("SELECT * FROM medicines $whereClause ORDER BY name ASC");
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container mx-auto px-4 py-8" x-data="{ showAddModal: false, editMedicine: null }">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Medicine Catalog</h1>
        <button @click="showAddModal = true" 
                class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700">
            ➕ Add Medicine
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

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-4 gap-4">
            <input type="text" 
                   name="search" 
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search medicine..."
                   class="md:col-span-2 px-4 py-2 border border-gray-300 rounded-lg">
            
            <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                Filter
            </button>
        </form>
    </div>

    <!-- Medicines Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($medicines as $medicine): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <img src="/quickmed/assets/images/uploads/products/<?= $medicine['image'] ?>" 
                     class="w-full h-48 object-cover"
                     onerror="this.src='https://via.placeholder.com/300x200?text=Medicine'">
                
                <div class="p-4">
                    <?php if ($medicine['category']): ?>
                        <span class="inline-block bg-red-100 text-red-600 px-2 py-1 rounded-full text-xs font-semibold mb-2">
                            <?= htmlspecialchars($medicine['category']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <h3 class="font-bold text-lg mb-1"><?= htmlspecialchars($medicine['name']) ?></h3>
                    <p class="text-sm text-gray-600 mb-1"><?= htmlspecialchars($medicine['generic_name']) ?></p>
                    <p class="text-xs text-gray-500 mb-3"><?= htmlspecialchars($medicine['company']) ?></p>
                    
                    <?php if ($medicine['description']): ?>
                        <p class="text-xs text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars($medicine['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="flex gap-2">
                        <button @click="editMedicine = <?= htmlspecialchars(json_encode($medicine)) ?>"
                                class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700">
                            Edit
                        </button>
                        <a href="?delete=<?= $medicine['id'] ?>"
                           onclick="return confirm('Delete this medicine?')"
                           class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">
                            🗑️
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($medicines) === 0): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">💊</div>
            <p class="text-gray-600">No medicines found</p>
        </div>
    <?php endif; ?>

    <!-- Add Medicine Modal -->
    <div x-show="showAddModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full my-8">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Add New Medicine</h2>
                    <button @click="showAddModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Medicine Name *</label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Generic Name *</label>
                            <input type="text" name="generic_name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Company *</label>
                            <input type="text" name="company" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Category</label>
                            <input type="text" name="category" list="categories"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <datalist id="categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Product Image</label>
                        <input type="file" name="image" accept="image/*"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_medicine"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Add Medicine
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

    <!-- Edit Medicine Modal -->
    <div x-show="editMedicine" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full my-8">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Edit Medicine</h2>
                    <button @click="editMedicine = null" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="medicine_id" x-model="editMedicine?.id">
                    <input type="hidden" name="current_image" x-model="editMedicine?.image">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Medicine Name *</label>
                            <input type="text" name="name" x-model="editMedicine.name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Generic Name *</label>
                            <input type="text" name="generic_name" x-model="editMedicine.generic_name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Company *</label>
                            <input type="text" name="company" x-model="editMedicine.company" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Category</label>
                            <input type="text" name="category" x-model="editMedicine.category" list="categories"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Description</label>
                        <textarea name="description" x-model="editMedicine.description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Product Image</label>
                        <input type="file" name="image" accept="image/*"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="update_medicine"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Update Medicine
                        </button>
                        <button type="button" @click="editMedicine = null"
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