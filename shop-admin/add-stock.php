<?php
$pageTitle = 'Add Stock - Shop Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Add stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = (int)$_POST['medicine_id'];
    $batch_no = sanitize($_POST['batch_no']);
    $quantity = (int)$_POST['quantity'];
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $expiry_date = sanitize($_POST['expiry_date']);
    
    if ($medicine_id && $quantity > 0 && $purchase_price > 0 && $selling_price > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO inventory (shop_id, medicine_id, batch_no, quantity, purchase_price, selling_price, expiry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $shop_admin['shop_id'],
                $medicine_id,
                $batch_no,
                $quantity,
                $purchase_price,
                $selling_price,
                $expiry_date
            ]);
            
            logActivity($pdo, $_SESSION['user_id'], 'STOCK_ADDED', 'inventory', $pdo->lastInsertId(), "Added $quantity units");
            
            $success = 'Stock added successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to add stock';
        }
    } else {
        $error = 'Please fill all required fields correctly';
    }
}

// Get all medicines for selection
$medicines = $pdo->query("SELECT * FROM medicines ORDER BY name ASC")->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Add New Stock</h1>

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

    <div class="bg-white rounded-xl shadow-lg p-6" x-data="stockForm()">
        <form method="POST" class="space-y-6">
            <!-- Medicine Selection -->
            <div>
                <label class="block text-sm font-semibold mb-2">Select Medicine *</label>
                <div class="relative">
                    <input type="text" 
                           x-model="searchQuery"
                           @input="searchMedicines()"
                           placeholder="Search medicine by name, generic, or company..."
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    
                    <div x-show="filteredMedicines.length > 0 && searchQuery.length > 0" 
                         x-cloak
                         class="absolute w-full bg-white mt-2 rounded-lg shadow-xl max-h-64 overflow-y-auto z-10">
                        <template x-for="medicine in filteredMedicines" :key="medicine.id">
                            <div @click="selectMedicine(medicine)" 
                                 class="px-4 py-3 hover:bg-gray-100 cursor-pointer border-b">
                                <p class="font-semibold" x-text="medicine.name"></p>
                                <p class="text-sm text-gray-600" x-text="medicine.generic_name"></p>
                                <p class="text-xs text-gray-500" x-text="medicine.company"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <input type="hidden" name="medicine_id" x-model="selectedMedicine?.id">
                
                <div x-show="selectedMedicine" class="mt-3 p-4 bg-purple-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold" x-text="selectedMedicine?.name"></p>
                            <p class="text-sm text-gray-600" x-text="selectedMedicine?.generic_name"></p>
                            <p class="text-xs text-gray-500" x-text="selectedMedicine?.company"></p>
                        </div>
                        <button type="button" @click="selectedMedicine = null; searchQuery = ''" 
                                class="text-red-600 text-sm font-semibold">Remove</button>
                    </div>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Batch Number -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Batch Number *</label>
                    <input type="text" name="batch_no" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                </div>

                <!-- Quantity -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Quantity *</label>
                    <input type="number" name="quantity" min="1" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                </div>

                <!-- Purchase Price -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Purchase Price (per unit) *</label>
                    <input type="number" name="purchase_price" step="0.01" min="0" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                </div>

                <!-- Selling Price -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Selling Price (per unit) *</label>
                    <input type="number" name="selling_price" step="0.01" min="0" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                </div>

                <!-- Expiry Date -->
                <div>
                    <label class="block text-sm font-semibold mb-2">Expiry Date *</label>
                    <input type="date" name="expiry_date" required
                           :min="new Date().toISOString().split('T')[0]"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" 
                        :disabled="!selectedMedicine"
                        class="flex-1 bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    ➕ Add Stock
                </button>
                <a href="inventory.php" 
                   class="flex-1 text-center border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function stockForm() {
    return {
        medicines: <?= json_encode($medicines) ?>,
        filteredMedicines: [],
        searchQuery: '',
        selectedMedicine: null,

        searchMedicines() {
            if (this.searchQuery.length < 2) {
                this.filteredMedicines = [];
                return;
            }

            const query = this.searchQuery.toLowerCase();
            this.filteredMedicines = this.medicines.filter(med => 
                med.name.toLowerCase().includes(query) ||
                med.generic_name.toLowerCase().includes(query) ||
                (med.company && med.company.toLowerCase().includes(query))
            ).slice(0, 10);
        },

        selectMedicine(medicine) {
            this.selectedMedicine = medicine;
            this.searchQuery = '';
            this.filteredMedicines = [];
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>