<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// If logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('/quickmed/admin/dashboard.php');
        case 'shop_admin':
            redirect('/quickmed/shop-admin/dashboard.php');
        case 'salesman':
            redirect('/quickmed/salesman/pos.php');
        default:
            redirect('/quickmed/customer/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickMed - Online Pharmacy</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-indigo-600 to-blue-500 min-h-screen flex items-center">
        <div class="container mx-auto px-4">
            <div class="text-center text-white">
                <h1 class="text-6xl font-bold mb-6">QuickMed</h1>
                <p class="text-2xl mb-8">Your Trusted Online Pharmacy</p>
                <p class="text-xl mb-12 max-w-2xl mx-auto">
                    Buy medicines online with ease. Fast delivery, genuine products, and professional service.
                </p>
                
                <div class="flex gap-4 justify-center">
                    <a href="signup.php" 
                        class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="login.php" 
                        class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white hover:text-indigo-600 transition">
                        Login
                    </a>
                </div>

                <!-- Features -->
                <div class="grid md:grid-cols-3 gap-8 mt-20">
                    <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">
                        <div class="text-4xl mb-4">🚚</div>
                        <h3 class="text-xl font-semibold mb-2">Fast Delivery</h3>
                        <p>Get your medicines delivered quickly</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">
                        <div class="text-4xl mb-4">💊</div>
                        <h3 class="text-xl font-semibold mb-2">Genuine Products</h3>
                        <p>100% authentic medicines</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">
                        <div class="text-4xl mb-4">🎁</div>
                        <h3 class="text-xl font-semibold mb-2">Reward Points</h3>
                        <p>Earn points on every purchase</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>