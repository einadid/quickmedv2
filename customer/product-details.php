<?php
$pageTitle = 'Product Details - QuickMed';
include 'includes/header.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get product details
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        GROUP_CONCAT(DISTINCT m.category) as categories
    FROM medicines m
    WHERE m.id = ?
    GROUP BY m.id
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('/quickmed/customer/index.php');
}

// Get inventory from different shops
$stmt = $pdo->prepare("
    SELECT 
        i.*,
        s.name as shop_name,
        s.address as shop_address
    FROM inventory i
    JOIN shops s ON i.shop_id = s.id
    WHERE i.medicine_id = ? AND i.quantity > 0 AND s.is_active = 1
    ORDER BY i.selling_price ASC
");
$stmt->execute([$product_id]);
$inventory = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="grid md:grid-cols-2 gap-8 p-8">
            <!-- Product Image -->
            <div>
                <img src="/quickmed/assets/images/uploads/products/<?= $product['image'] ?>" 
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     class="w-full h-96 object-contain rounded-lg"
                     onerror="this.src='https://via.placeholder.com/400x400?text=Medicine'">
            </div>

            <!-- Product Info -->
            <div>
                <?php if ($product['category']): ?>
                    <span class="inline-block bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-sm font-semibold">
                        <?= htmlspecialchars($product['category']) ?>
                    </span>
                <?php endif; ?>

                <h1 class="text-4xl font-bold mt-4 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                
                <p class="text-xl text-gray-600 mb-2">
                    <span class="font-semibold">Generic:</span> <?= htmlspecialchars($product['generic_name']) ?>
                </p>
                
                <p class="text-lg text-gray-600 mb-6">
                    <span class="font-semibold">Company:</span> <?= htmlspecialchars($product['company']) ?>
                </p>

                <?php if ($product['description']): ?>
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold mb-2">Description</h3>
                        <p class="text-gray-700"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Available in Shops -->
                <div class="mb-6">
                    <h3 class="text-xl font-semibold mb-4">Available in <?= count($inventory) ?> shop(s)</h3>
                    
                    <?php if (count($inventory) > 0): ?>
                        <div class="space-y-3" x-data="{ selectedInventory: <?= $inventory[0]['id'] ?> }">
                            <?php foreach ($inventory as $inv): ?>
                                <div class="border rounded-lg p-4 cursor-pointer transition"
                                     :class="selectedInventory === <?= $inv['id'] ?> ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300 hover:border-indigo-300'"
                                     @click="selectedInventory = <?= $inv['id'] ?>">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-semibold text-lg"><?= htmlspecialchars($inv['shop_name']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($inv['shop_address']) ?></p>
                                            <p class="text-sm text-green-600 mt-1">
                                                Stock: <?= $inv['quantity'] ?> | 
                                                Batch: <?= htmlspecialchars($inv['batch_no']) ?> | 
                                                Exp: <?= date('M Y', strtotime($inv['expiry_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-3xl font-bold text-indigo-600">৳<?= number_format($inv['selling_price'], 2) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Add to Cart Form -->
                            <div class="bg-gray-50 rounded-lg p-4 mt-4">
                                <label class="block text-sm font-semibold mb-2">Quantity</label>
                                <div class="flex gap-4">
                                    <input type="number" 
                                           x-model="quantity" 
                                           min="1" 
                                           value="1"
                                           class="border border-gray-300 rounded-lg px-4 py-2 w-24">
                                    
                                    <button @click="addToCart(selectedInventory, quantity)"
                                            class="flex-1 bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                                        🛒 Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                            <p class="text-red-600 font-semibold">Currently out of stock</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Similar Products -->
    <?php
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.image,
            MIN(i.selling_price) as price
        FROM medicines m
        JOIN inventory i ON m.id = i.medicine_id
        WHERE m.category = ? AND m.id != ? AND i.quantity > 0
        GROUP BY m.id
        LIMIT 4
    ");
    $stmt->execute([$product['category'], $product_id]);
    $similarProducts = $stmt->fetchAll();
    ?>

    <?php if (count($similarProducts) > 0): ?>
        <div class="mt-12">
            <h2 class="text-2xl font-bold mb-6">Similar Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($similarProducts as $similar): ?>
                    <a href="product-details.php?id=<?= $similar['id'] ?>" 
                       class="bg-white rounded-xl shadow-lg p-4 hover:shadow-2xl transition">
                        <img src="/quickmed/assets/images/uploads/products/<?= $similar['image'] ?>" 
                             alt="<?= htmlspecialchars($similar['name']) ?>"
                             class="w-full h-40 object-cover rounded mb-3"
                             onerror="this.src='https://via.placeholder.com/200?text=Medicine'">
                        <p class="font-semibold truncate"><?= htmlspecialchars($similar['name']) ?></p>
                        <p class="text-indigo-600 font-bold">৳<?= number_format($similar['price'], 2) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function addToCart(inventoryId, quantity) {
    quantity = parseInt(quantity) || 1;
    
    // Get existing cart
    let cart = JSON.parse(localStorage.getItem('quickmed_cart') || '[]');
    
    // Check if item already exists
    const existingIndex = cart.findIndex(item => item.inventory_id === inventoryId);
    
    if (existingIndex > -1) {
        cart[existingIndex].quantity += quantity;
    } else {
        cart.push({
            inventory_id: inventoryId,
            quantity: quantity,
            added_at: new Date().toISOString()
        });
    }
    
    localStorage.setItem('quickmed_cart', JSON.stringify(cart));
    
    // Trigger cart update event
    window.dispatchEvent(new Event('cartUpdated'));
    
    // Show success message
    alert('✅ Added to cart successfully!');
}
</script>

<?php include 'includes/footer.php'; ?>