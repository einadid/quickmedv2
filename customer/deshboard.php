<?php
$pageTitle = 'Dashboard - QuickMed';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    redirect('/quickmed/login.php');
}

// Check if customer
if ($_SESSION['role'] !== 'customer') {
    redirect('/quickmed/login.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('/quickmed/login.php');
}

// Get user stats
$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userPoints = $stmt->fetchColumn();

// Membership Number (email এর @ এর আগের অংশ)
$emailParts = explode('@', $user['email']);
$membershipId = strtoupper($emailParts[0]);

// Get recent orders
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentOrders = $stmt->fetchAll();

// Get order stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total), 0) as total_spent
    FROM orders 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get quick reorder products
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.name,
        m.image,
        MIN(i.selling_price) as price
    FROM order_items oi
    JOIN inventory i ON oi.inventory_id = i.id
    JOIN medicines m ON i.medicine_id = m.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ? AND i.quantity > 0
    GROUP BY m.id
    ORDER BY o.created_at DESC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id']]);
$reorderProducts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <a href="/quickmed/customer/dashboard.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">💊</span>
                    </div>
                    <span class="text-2xl font-bold text-gray-800">QuickMed</span>
                </a>

                <div class="flex items-center gap-4">
                    <a href="/quickmed/customer/cart.php" class="relative">
                        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </a>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=6366f1&color=fff" 
                                 class="w-8 h-8 rounded-full">
                            <span class="font-semibold"><?= htmlspecialchars($user['name']) ?></span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2">
                            <a href="/quickmed/customer/dashboard.php" class="block px-4 py-2 hover:bg-gray-100">Dashboard</a>
                            <a href="/quickmed/customer/orders.php" class="block px-4 py-2 hover:bg-gray-100">Orders</a>
                            <a href="/quickmed/customer/profile.php" class="block px-4 py-2 hover:bg-gray-100">Profile</a>
                            <hr class="my-2">
                            <a href="/quickmed/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-3xl shadow-2xl p-8 mb-8 text-white">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-4xl font-bold mb-2">Welcome back, <?= htmlspecialchars($user['name']) ?>! 👋</h1>
                    <p class="text-indigo-100 text-lg">Here's what's happening with your health today</p>
                </div>
                <div class="bg-white bg-opacity-20 backdrop-blur-lg rounded-2xl p-4">
                    <p class="text-sm text-indigo-100 mb-1">Membership ID</p>
                    <p class="text-3xl font-bold font-mono"><?= $membershipId ?></p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <!-- Health Wallet -->
            <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-yellow-100 text-sm mb-1">Health Wallet</p>
                        <p class="text-5xl font-bold"><?= $userPoints ?></p>
                        <p class="text-sm text-yellow-100 mt-1">Reward Points</p>
                    </div>
                    <div class="text-6xl opacity-30">🎁</div>
                </div>
                <div class="bg-white bg-opacity-20 rounded-xl p-3 mt-4">
                    <p class="text-xs">💡 Earn 100 points for every ৳1,000 spent!</p>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white rounded-2xl shadow-xl p-6 transform hover:scale-105 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 mb-2 font-medium">Total Orders</p>
                        <p class="text-5xl font-bold text-gray-800"><?= $stats['total_orders'] ?></p>
                    </div>
                    <div class="text-6xl">📦</div>
                </div>
            </div>

            <!-- Total Spent -->
            <div class="bg-white rounded-2xl shadow-xl p-6 transform hover:scale-105 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 mb-2 font-medium">Total Spent</p>
                        <p class="text-4xl font-bold text-gray-800">৳<?= number_format($stats['total_spent'], 0) ?></p>
                    </div>
                    <div class="text-6xl">💰</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid md:grid-cols-4 gap-4 mb-8">
            <a href="/quickmed/customer/products.php" class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition text-center">
                <div class="text-5xl mb-3">🛒</div>
                <p class="font-bold text-lg">Browse Products</p>
            </a>
            
            <a href="/quickmed/customer/orders.php" class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition text-center">
                <div class="text-5xl mb-3">📋</div>
                <p class="font-bold text-lg">My Orders</p>
            </a>
            
            <a href="/quickmed/customer/addresses.php" class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition text-center">
                <div class="text-5xl mb-3">📍</div>
                <p class="font-bold text-lg">Addresses</p>
            </a>
            
            <a href="/quickmed/customer/profile.php" class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition text-center">
                <div class="text-5xl mb-3">👤</div>
                <p class="font-bold text-lg">Profile</p>
            </a>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Recent Orders -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Recent Orders</h2>
                    <a href="/quickmed/customer/orders.php" class="text-indigo-600 font-semibold hover:underline">View All →</a>
                </div>
                
                <?php if (count($recentOrders) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentOrders as $order): ?>
                            <div class="border-2 border-gray-100 rounded-xl p-4 hover:border-indigo-300 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <p class="font-bold text-lg"><?= $order['order_number'] ?></p>
                                        <p class="text-sm text-gray-600"><?= $order['item_count'] ?> items • <?= date('d M Y', strtotime($order['created_at'])) ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                                        <?php
                                        echo match($order['status']) {
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'processing' => 'bg-blue-100 text-blue-800',
                                            'shipped' => 'bg-purple-100 text-purple-800',
                                            'delivered' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <p class="text-2xl font-bold text-indigo-600">৳<?= number_format($order['total'], 2) ?></p>
                                    <a href="/quickmed/customer/order-details.php?id=<?= $order['id'] ?>" 
                                       class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📦</div>
                        <p class="text-gray-600 mb-4">No orders yet</p>
                        <a href="/quickmed/customer/products.php" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                            Start Shopping
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Health Tips -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-6 border-2 border-green-200">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center text-2xl mr-3">💡</div>
                        <h3 class="font-bold text-lg">Health Tip</h3>
                    </div>
                    <p class="text-gray-700 text-sm">Drink at least 8 glasses of water daily to stay hydrated and maintain optimal health.</p>
                </div>

                <!-- Support -->
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="font-bold text-lg mb-4">Need Help?</h3>
                    <div class="space-y-3">
                        <a href="#" class="flex items-center text-gray-700 hover:text-indigo-600">
                            <span class="mr-3">📞</span> Call Support
                        </a>
                        <a href="#" class="flex items-center text-gray-700 hover:text-indigo-600">
                            <span class="mr-3">📧</span> Email Us
                        </a>
                        <a href="#" class="flex items-center text-gray-700 hover:text-indigo-600">
                            <span class="mr-3">❓</span> FAQs
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Re-order -->
        <?php if (count($reorderProducts) > 0): ?>
            <div class="bg-white rounded-2xl shadow-xl p-6 mt-8">
                <h2 class="text-2xl font-bold mb-6">Quick Re-order</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <?php foreach ($reorderProducts as $product): ?>
                        <a href="/quickmed/customer/product-details.php?id=<?= $product['id'] ?>" 
                           class="border-2 border-gray-100 rounded-xl p-4 hover:border-indigo-300 hover:shadow-lg transition">
                            <img src="/quickmed/assets/images/uploads/products/<?= $product['image'] ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="w-full h-32 object-cover rounded-lg mb-3"
                                 onerror="this.src='https://via.placeholder.com/150?text=Medicine'">
                            <p class="text-sm font-semibold truncate mb-1"><?= htmlspecialchars($product['name']) ?></p>
                            <p class="text-indigo-600 font-bold">৳<?= number_format($product['price'], 2) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-20">
        <div class="container mx-auto px-4 py-12">
            <div class="grid md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">QuickMed</h3>
                    <p class="text-gray-400">Your trusted online pharmacy</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="/quickmed/customer/products.php" class="hover:text-white">Products</a></li>
                        <li><a href="/quickmed/customer/orders.php" class="hover:text-white">Orders</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Contact</h4>
                    <p class="text-gray-400">support@quickmed.com</p>
                    <p class="text-gray-400">+880 1700-000000</p>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 QuickMed. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>