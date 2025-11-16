<?php
$pageTitle = 'Shop - QuickMed';
include 'includes/header.php';





// Get all shops
$shops = $pdo->query("SELECT * FROM shops WHERE is_active = 1")->fetchAll();

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Filters
$category = sanitize($_GET['category'] ?? '');
$shop_id = isset($_GET['shop']) ? (int)$_GET['shop'] : 0;
$sort = sanitize($_GET['sort'] ?? 'name_asc');

// Build query
$where = ["i.quantity > 0"];
$params = [];

if ($category) {
    $where[] = "m.category = ?";
    $params[] = $category;
}

if ($shop_id) {
    $where[] = "i.shop_id = ?";
    $params[] = $shop_id;
}

$whereClause = implode(' AND ', $where);

// Sorting
$orderBy = match($sort) {
    'price_asc' => 'i.selling_price ASC',
    'price_desc' => 'i.selling_price DESC',
    'name_desc' => 'm.name DESC',
    default => 'm.name ASC'
};

// Get products
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.name,
        m.generic_name,
        m.company,
        m.image,
        m.category,
        MIN(i.selling_price) as min_price,
        MAX(i.selling_price) as max_price,
        SUM(i.quantity) as total_stock
    FROM medicines m
    JOIN inventory i ON m.id = i.medicine_id
    WHERE $whereClause
    GROUP BY m.id
    ORDER BY $orderBy
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get total count
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT m.id)
    FROM medicines m
    JOIN inventory i ON m.id = i.medicine_id
    WHERE $whereClause
");
$stmt->execute($params);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);
?>

<div class="container mx-auto px-4 py-8">
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-4 gap-4" id="filterForm">
            <!-- Category Filter -->
            <select name="category" class="border border-gray-300 rounded-lg px-4 py-2" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Shop Filter -->
            <select name="shop" class="border border-gray-300 rounded-lg px-4 py-2" onchange="this.form.submit()">
                <option value="">All Shops</option>
                <?php foreach ($shops as $shop): ?>
                    <option value="<?= $shop['id'] ?>" <?= $shop_id === $shop['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($shop['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Sort -->
            <select name="sort" class="border border-gray-300 rounded-lg px-4 py-2" onchange="this.form.submit()">
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
            </select>

            <!-- Reset -->
            <a href="index.php" class="border border-gray-300 rounded-lg px-4 py-2 text-center hover:bg-gray-50">
                Reset Filters
            </a>
        </form>
    </div>

    <!-- Products Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($products as $product): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-2xl transition">
                <a href="product-details.php?id=<?= $product['id'] ?>">
                    <img src="/quickmed/assets/images/uploads/products/<?= $product['image'] ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         class="w-full h-48 object-cover"
                         onerror="this.src='https://via.placeholder.com/300x200?text=Medicine'">
                </a>
                
                <div class="p-4">
                    <?php if ($product['category']): ?>
                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-1 rounded-full">
                            <?= htmlspecialchars($product['category']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <a href="product-details.php?id=<?= $product['id'] ?>">
                        <h3 class="font-bold text-lg mt-2 hover:text-indigo-600">
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>
                    </a>
                    
                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($product['generic_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($product['company']) ?></p>
                    
                    <div class="flex justify-between items-center mt-4">
                        <div>
                            <?php if ($product['min_price'] === $product['max_price']): ?>
                                <p class="text-2xl font-bold text-indigo-600">৳<?= number_format($product['min_price'], 2) ?></p>
                            <?php else: ?>
                                <p class="text-lg font-bold text-indigo-600">
                                    ৳<?= number_format($product['min_price'], 2) ?> - ৳<?= number_format($product['max_price'], 2) ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-xs text-green-600">In Stock: <?= $product['total_stock'] ?></p>
                        </div>
                    </div>
                    
                    <button onclick="quickView(<?= $product['id'] ?>)" 
                            class="w-full mt-4 bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition">
                        View Details
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($products) === 0): ?>
        <div class="text-center py-20">
            <div class="text-6xl mb-4">🔍</div>
            <p class="text-gray-600 text-xl">No products found</p>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center gap-2 mt-8">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&category=<?= $category ?>&shop=<?= $shop_id ?>&sort=<?= $sort ?>" 
                   class="px-4 py-2 rounded-lg <?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function quickView(productId) {
    window.location.href = 'product-details.php?id=' + productId;
}
</script>

<?php include 'includes/footer.php'; ?>