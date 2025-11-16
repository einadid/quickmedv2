<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('/quickmed/login.php');
}

// Check if user is customer
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'QuickMed - Customer' ?></title>
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
                <!-- Logo -->
                <a href="/quickmed/customer/index.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">💊</span>
                    </div>
                    <span class="text-2xl font-bold text-gray-800">QuickMed</span>
                </a>

                <!-- Search (Desktop) -->
                <div class="hidden md:block flex-1 max-w-xl mx-8" x-data="liveSearch()">
                    <div class="relative">
                        <input type="text" 
                               x-model="query"
                               @input.debounce.300ms="search()"
                               placeholder="Search medicines..."
                               class="w-full px-4 py-2 pl-10 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:outline-none">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Right Menu -->
                <div class="flex items-center gap-4">
                    <!-- Cart -->
                    <a href="/quickmed/customer/cart.php" class="relative">
                        <svg class="w-6 h-6 text-gray-700 hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span id="cartCount" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">0</span>
                    </a>

                    <!-- User Menu -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 hover:bg-gray-100 rounded-lg px-3 py-2">
                            <img src="/quickmed/assets/images/uploads/profiles/<?= $user['profile_image'] ?>" 
                                 class="w-8 h-8 rounded-full object-cover"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=6366f1&color=fff'">
                            <span class="hidden md:block font-semibold text-gray-700"><?= htmlspecialchars($user['name']) ?></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" 
                             @click.away="open = false"
                             x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 border border-gray-200">
                            <a href="/quickmed/customer/dashboard.php" class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                                <span class="mr-2">📊</span> Dashboard
                            </a>
                            <a href="/quickmed/customer/orders.php" class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                                <span class="mr-2">📦</span> My Orders
                            </a>
                            <a href="/quickmed/customer/profile.php" class="block px-4 py-2 hover:bg-gray-100 text-gray-700">
                                <span class="mr-2">👤</span> Profile
                            </a>
                            <hr class="my-2">
                            <a href="/quickmed/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                <span class="mr-2">🚪</span> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Search -->
    <div class="md:hidden bg-white px-4 py-3 shadow">
        <input type="text" 
               placeholder="Search medicines..."
               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:outline-none">
    </div>

    <script>
        // Live Search
        function liveSearch() {
            return {
                query: '',
                results: [],
                async search() {
                    if (this.query.length < 2) {
                        this.results = [];
                        return;
                    }
                    // Add search logic here
                }
            }
        }

        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('quickmed_cart') || '[]');
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                cartCount.textContent = cart.length;
            }
        }
        
        updateCartCount();
        window.addEventListener('cartUpdated', updateCartCount);
    </script>