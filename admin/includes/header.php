<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('admin');

// Get admin data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin - QuickMed' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="bg-gradient-to-r from-red-600 to-pink-600 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold">👑 QuickMed Admin</h1>
                    <span class="bg-red-500 px-3 py-1 rounded-full text-sm">Super Admin</span>
                </div>

                <div class="flex items-center gap-6">
                    <span class="hidden md:block">👤 <?= htmlspecialchars($admin['name']) ?></span>
                    <a href="/quickmed/logout.php" class="hover:bg-red-500 px-4 py-2 rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Desktop Sidebar -->
    <div class="hidden md:block fixed left-0 top-16 w-64 h-full bg-white shadow-lg overflow-y-auto">
        <div class="p-6">
            <div class="space-y-2">
                <a href="/quickmed/admin/dashboard.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
                    📊 Dashboard
                </a>
                <a href="/quickmed/admin/users.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
                    👥 Users
                </a>
                <a href="/quickmed/admin/shops.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'shops.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
                    🏪 Shops
                </a>
                <a href="/quickmed/admin/medicines.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'medicines.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
                    💊 Medicine Catalog
                </a>
                <a href="/quickmed/admin/reports.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
                    📈 Reports
                </a>
                <a href="/quickmed/admin/audit-logs.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'audit-logs.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
                    📝 Audit Logs
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white shadow-lg z-50">
        <div class="grid grid-cols-6 gap-1 p-2">
            <a href="/quickmed/admin/dashboard.php" class="text-center py-2">
                <div class="text-2xl">📊</div>
                <div class="text-xs">Dashboard</div>
            </a>
            <a href="/quickmed/admin/users.php" class="text-center py-2">
                <div class="text-2xl">👥</div>
                <div class="text-xs">Users</div>
            </a>
            <a href="/quickmed/admin/shops.php" class="text-center py-2">
                <div class="text-2xl">🏪</div>
                <div class="text-xs">Shops</div>
            </a>
            <a href="/quickmed/admin/medicines.php" class="text-center py-2">
                <div class="text-2xl">💊</div>
                <div class="text-xs">Medicines</div>
            </a>
            <a href="/quickmed/admin/reports.php" class="text-center py-2">
                <div class="text-2xl">📈</div>
                <div class="text-xs">Reports</div>
                
            </a>
            <a href="/quickmed/admin/verification-codes.php" 
   class="block px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition
   <?= basename($_SERVER['PHP_SELF']) === 'verification-codes.php' ? 'bg-red-100 text-red-600 font-semibold' : '' ?>">
    🔑 Verification Codes
</a>
            <a href="/quickmed/admin/audit-logs.php" class="text-center py-2">
                <div class="text-2xl">📝</div>
                <div class="text-xs">Logs</div>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen pb-20 md:pb-4">