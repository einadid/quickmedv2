<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    redirect('/quickmed/login.php');
}

// Check if salesman
if ($_SESSION['role'] !== 'salesman') {
    redirect('/quickmed/login.php');
}

// Get salesman data with shop
$stmt = $pdo->prepare("
    SELECT u.*, s.name as shop_name 
    FROM users u 
    LEFT JOIN shops s ON u.shop_id = s.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$salesman = $stmt->fetch();

if (!$salesman || !$salesman['shop_id']) {
    die('Error: No shop assigned to this salesman. Please contact administrator.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Salesman - QuickMed' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- Top Navigation -->
    <nav class="bg-indigo-700 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold">QuickMed POS</h1>
                    <span class="bg-indigo-600 px-3 py-1 rounded-full text-sm">
                        <?= htmlspecialchars($salesman['shop_name']) ?>
                    </span>
                </div>

                <div class="flex items-center gap-6">
                    <span class="hidden md:block">👤 <?= htmlspecialchars($salesman['name']) ?></span>
                    <a href="/quickmed/logout.php" class="hover:bg-indigo-600 px-4 py-2 rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Desktop Sidebar -->
    <div class="hidden md:block fixed left-0 top-16 w-64 h-full bg-white shadow-lg">
        <div class="p-6">
            <div class="space-y-2">
                <a href="/quickmed/salesman/dashboard.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-indigo-100 text-indigo-600 font-semibold' : '' ?>">
                    📊 Dashboard
                </a>
                <a href="/quickmed/salesman/pos.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'bg-indigo-100 text-indigo-600 font-semibold' : '' ?>">
                    💳 POS System
                </a>
                <a href="/quickmed/salesman/sales.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'bg-indigo-100 text-indigo-600 font-semibold' : '' ?>">
                    📋 My Sales
                </a>
                <a href="/quickmed/salesman/returns.php" 
                   class="block px-4 py-3 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition
                   <?= basename($_SERVER['PHP_SELF']) === 'returns.php' ? 'bg-indigo-100 text-indigo-600 font-semibold' : '' ?>">
                    🔄 Returns
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white shadow-lg z-50">
        <div class="grid grid-cols-4 gap-1 p-2">
            <a href="/quickmed/salesman/dashboard.php" class="text-center py-2 <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-indigo-600' : '' ?>">
                <div class="text-2xl">📊</div>
                <div class="text-xs">Dashboard</div>
            </a>
            <a href="/quickmed/salesman/pos.php" class="text-center py-2 <?= basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'text-indigo-600' : '' ?>">
                <div class="text-2xl">💳</div>
                <div class="text-xs">POS</div>
            </a>
            <a href="/quickmed/salesman/sales.php" class="text-center py-2 <?= basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'text-indigo-600' : '' ?>">
                <div class="text-2xl">📋</div>
                <div class="text-xs">Sales</div>
            </a>
            <a href="/quickmed/salesman/returns.php" class="text-center py-2 <?= basename($_SERVER['PHP_SELF']) === 'returns.php' ? 'text-indigo-600' : '' ?>">
                <div class="text-2xl">🔄</div>
                <div class="text-xs">Returns</div>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen pb-20 md:pb-4">