<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('customer');

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get cart count from localStorage (will be managed by JS)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'QuickMed' ?></title>
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
                <a href="/quickmed/customer/index.php" class="text-2xl font-bold text-indigo-600">
                    QuickMed
                </a>

                <!-- Search Bar (Desktop) -->
                <div class="hidden md:block flex-1 max-w-xl mx-8">
                    <div class="relative" x-data="liveSearch()">
                        <input 
                            type="text" 
                            x-model="query"
                            @input.debounce.300ms="search()"
                            placeholder="Search medicines..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        
                        <!-- Search Results Dropdown -->
                        <div x-show="results.length > 0" 
                             x-cloak
                             class="absolute w-full bg-white mt-2 rounded-lg shadow-xl max-h-96 overflow-y-auto">
                            <template x-for="item in results" :key="item.id">
                                <a :href="'product-details.php?id=' + item.medicine_id" 
                                   class="block px-4 py-3 hover:bg-gray-50 border-b">
                                    <div class="flex items-center gap-3">
                                        <img :src="'/quickmed/assets/images/uploads/products/' + item.image" 
                                             class="w-12 h-12 object-cover rounded">
                                        <div>
                                            <p class="font-semibold" x-text="item.medicine_name"></p>
                                            <p class="text-sm text-gray-600" x-text="item.generic_name"></p>
                                            <p class="text-indigo-600 font-semibold" x-text="'৳' + item.selling_price"></p>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Menu -->
                <div class="flex items-center gap-4">
                    <!-- Cart -->
                    <a href="/quickmed/customer/cart.php" class="relative">
                        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span id="cartCount" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </a>

                    <!-- User Menu -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2">
                            <img src="/quickmed/assets/images/uploads/profiles/<?= $user['profile_image'] ?>" 
                                 class="w-8 h-8 rounded-full object-cover"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>'">
                            <span class="hidden md:block font-semibold"><?= htmlspecialchars($user['name']) ?></span>
                        </button>

                        <div x-show="open" 
                             @click.away="open = false"
                             x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2">
                            <a href="/quickmed/customer/dashboard.php" class="block px-4 py-2 hover:bg-gray-100">Dashboard</a>
                            <a href="/quickmed/customer/orders.php" class="block px-4 py-2 hover:bg-gray-100">My Orders</a>
                            <a href="/quickmed/customer/profile.php" class="block px-4 py-2 hover:bg-gray-100">Profile</a>
                            <hr class="my-2">
                            <a href="/quickmed/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Search -->
    <div class="md:hidden bg-white px-4 py-3 shadow">
        <div x-data="liveSearch()">
            <input 
                type="text" 
                x-model="query"
                @input.debounce.300ms="search()"
                placeholder="Search medicines..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            
            <div x-show="results.length > 0" x-cloak class="mt-2 bg-white rounded-lg shadow-lg">
                <template x-for="item in results" :key="item.id">
                    <a :href="'product-details.php?id=' + item.medicine_id" 
                       class="block px-4 py-3 hover:bg-gray-50 border-b">
                        <div class="flex items-center gap-3">
                            <img :src="'/quickmed/assets/images/uploads/products/' + item.image" 
                                 class="w-12 h-12 object-cover rounded">
                            <div>
                                <p class="font-semibold text-sm" x-text="item.medicine_name"></p>
                                <p class="text-indigo-600 font-semibold" x-text="'৳' + item.selling_price"></p>
                            </div>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <script>
        // Live Search Component
        function liveSearch() {
            return {
                query: '',
                results: [],
                async search() {
                    if (this.query.length < 2) {
                        this.results = [];
                        return;
                    }
                    
                    const response = await fetch('/quickmed/api/search.php?q=' + encodeURIComponent(this.query));
                    const data = await response.json();
                    this.results = data.results || [];
                }
            }
        }

        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('quickmed_cart') || '[]');
            document.getElementById('cartCount').textContent = cart.length;
        }
        
        updateCartCount();
        window.addEventListener('cartUpdated', updateCartCount);
    </script>