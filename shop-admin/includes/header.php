<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('shop_admin');

// Get shop admin data
$stmt = $pdo->prepare("SELECT u.*, s.name as shop_name FROM users u JOIN shops s ON u.shop_id = s.id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$shop_admin = $stmt->fetch();

if (!$shop_admin['shop_id']) {
    die('Error: No shop assigned to this admin');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Shop Admin - QuickMed' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold">QuickMed</h1>
                    <span class="bg-purple-500 px-3 py-1 rounded-full text-sm">
                        📍 <?= htmlspecialchars($shop_admin['shop_name']) ?>
                    </span>
                </div>

                <div class="flex items-center gap-6">
                    <span class="hidden md:block">👤 <?= htmlspecialchars($shop_admin['name']) ?></span>
                    <a href="/quickmed/logout.php" class="hover:bg-purple-500 px-4 py-2 rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Desktop Sidebar -->
    <div class="hidden md:block fixed left-0 top-16 w-64 h-full bg-white shadow-lg">
        <div class="p-6">
            <div class="space-y-2">
                <a href="/quickmed/shop-admin/dashboard.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-purple-100 text-purple-600 font-semibold' : '' ?>">
                    📊 Dashboard
                </a>
                <a href="/quickmed/shop-admin/inventory.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'bg-purple-100 text-purple-600 font-semibold' : '' ?>">
                    📦 Inventory
                </a>
                <a href="/quickmed/shop-admin/add-stock.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'add-stock.php' ? 'bg-purple-100 text-purple-600 font-semibold' : '' ?>">
                    ➕ Add Stock
                </a>
                <a href="/quickmed/shop-admin/orders.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'bg-purple-100 text-purple-600 font-semibold' : '' ?>">
                    🛍️ Orders
                </a>
                <a href="/quickmed/shop-admin/reports.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'bg-purple-100 text-purple-600 font-semibold' : '' ?>">
                    📈 Reports
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white shadow-lg z-50">
        <div class="grid grid-cols-5 gap-1 p-2">
            <a href="/quickmed/shop-admin/dashboard.php" class="text-center py-2">
                <div class="text-2xl">📊</div>
                <div class="text-xs">Dashboard</div>
            </a>
            <a href="/quickmed/shop-admin/inventory.php" class="text-center py-2">
                <div class="text-2xl">📦</div>
                <div class="text-xs">Inventory</div>
            </a>
            <a href="/quickmed/shop-admin/add-stock.php" class="text-center py-2">
                <div class="text-2xl">➕</div>
                <div class="text-xs">Add</div>
            </a>
            <a href="/quickmed/shop-admin/orders.php" class="text-center py-2">
                <div class="text-2xl">🛍️</div>
                <div class="text-xs">Orders</div>
            </a>
            <a href="/quickmed/shop-admin/reports.php" class="text-center py-2">
                <div class="text-2xl">📈</div>
                <div class="text-xs">Reports</div>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen pb-20 md:pb-4">