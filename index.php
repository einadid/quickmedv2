<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin': redirect('/quickmed/admin/dashboard.php');
        case 'shop_admin': redirect('/quickmed/shop-admin/dashboard.php');
        case 'salesman': redirect('/quickmed/salesman/pos.php');
        default: redirect('/quickmed/customer/index.php');
    }
}

// Get live stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$total_medicines = $pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
$total_shops = $pdo->query("SELECT COUNT(*) FROM shops WHERE is_active = 1")->fetchColumn();

// Get featured medicines
$featured_medicines = $pdo->query("
    SELECT 
        m.*,
        MIN(i.selling_price) as price,
        SUM(i.quantity) as stock
    FROM medicines m
    JOIN inventory i ON m.id = i.medicine_id
    WHERE i.quantity > 0
    GROUP BY m.id
    ORDER BY RAND()
    LIMIT 8
")->fetchAll();

// Get health news
$health_news = $pdo->query("
    SELECT * FROM health_news 
    WHERE is_published = 1 
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickMed - Your Trusted Online Pharmacy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation { animation: float 3s ease-in-out infinite; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeInUp 0.6s ease-out forwards; }
        
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .slide-in-left { animation: slideInLeft 0.6s ease-out forwards; }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.5); }
            50% { box-shadow: 0 0 40px rgba(99, 102, 241, 0.8); }
        }
        .pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Navigation -->
    <nav class="glass-card sticky top-0 z-50 shadow-xl">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-extrabold gradient-text">QuickMed</h1>
                        <p class="text-xs text-gray-500 font-medium">Your Health, Our Priority</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <a href="login.php" class="hidden md:block text-gray-700 font-semibold hover:text-indigo-600 transition">Sign In</a>
                    <a href="signup.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 py-20">
        <!-- Animated Shapes -->
        <div class="absolute top-0 left-0 w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-yellow-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse" style="animation-delay: 2s;"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="slide-in-left">
                    <div class="inline-flex items-center bg-white rounded-full px-4 py-2 shadow-lg mb-6">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                        <span class="text-sm font-semibold text-gray-700">Trusted by <?= number_format($total_users) ?>+ Customers</span>
                    </div>
                    
                    <h1 class="text-5xl lg:text-7xl font-black text-gray-900 mb-6 leading-tight">
                        Your Health,<br>
                        <span class="gradient-text">Just a Click Away</span>
                    </h1>
                    
                    <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                        Order genuine medicines online with lightning-fast delivery. Professional service, competitive prices, and your health is our top priority.
                    </p>
                    
                    <!-- Search Bar -->
                    <div class="relative mb-8 glass-card rounded-2xl shadow-2xl overflow-hidden">
                        <div class="absolute inset-y-0 left-0 pl-6 flex items-center pointer-events-none">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input type="text" 
                               placeholder="Search for medicines, health products..." 
                               class="w-full pl-16 pr-40 py-5 bg-transparent border-none focus:outline-none text-gray-700 font-medium">
                        <button class="absolute right-2 top-1/2 -translate-y-1/2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all">
                            Search
                        </button>
                    </div>

                    <div class="flex items-center gap-6 flex-wrap">
                        <a href="signup.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-4 rounded-xl font-bold shadow-2xl hover:shadow-indigo-500/50 transform hover:scale-105 transition-all inline-flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Shop Now
                        </a>
                        <a href="#how-it-works" class="text-gray-700 font-bold hover:text-indigo-600 inline-flex items-center gap-2 group">
                            Learn More
                            <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="relative float-animation">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=600&h=600&fit=crop" 
                             alt="Pharmacy" 
                             class="rounded-3xl shadow-2xl">
                        
                        <!-- Floating Stats Card -->
                        <div class="absolute -bottom-6 -left-6 glass-card rounded-2xl shadow-2xl p-6 pulse-glow">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-3xl font-black text-gray-900"><?= number_format($total_orders) ?>+</p>
                                    <p class="text-sm font-semibold text-gray-600">Orders Delivered</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Stats Counter -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center transform hover:scale-110 transition-transform">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-4xl font-black text-gray-900 mb-2"><?= number_format($total_users) ?>+</h3>
                    <p class="text-gray-600 font-semibold">Happy Customers</p>
                </div>

                <div class="text-center transform hover:scale-110 transition-transform">
                    <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-4xl font-black text-gray-900 mb-2"><?= number_format($total_medicines) ?>+</h3>
                    <p class="text-gray-600 font-semibold">Medicines Available</p>
                </div>

                <div class="text-center transform hover:scale-110 transition-transform">
                    <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-green-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                    </div>
                    <h3 class="text-4xl font-black text-gray-900 mb-2"><?= number_format($total_orders) ?>+</h3>
                    <p class="text-gray-600 font-semibold">Orders Delivered</p>
                </div>

                <div class="text-center transform hover:scale-110 transition-transform">
                    <div class="w-20 h-20 bg-gradient-to-br from-orange-500 to-orange-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z"/>
                        </svg>
                    </div>
                    <h3 class="text-4xl font-black text-gray-900 mb-2"><?= $total_shops ?></h3>
                    <p class="text-gray-600 font-semibold">Store Locations</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 fade-in-up">
                <div class="inline-block bg-indigo-100 text-indigo-600 px-4 py-2 rounded-full font-bold text-sm mb-4">
                    Simple Process
                </div>
                <h2 class="text-5xl font-black text-gray-900 mb-4">How QuickMed Works</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Get your medicines delivered in 3 simple steps
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="glass-card rounded-3xl p-8 text-center shadow-2xl transform hover:scale-105 hover:shadow-indigo-500/50 transition-all">
                    <div class="relative">
                        <div class="w-24 h-24 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-2xl">
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center font-black text-gray-900 shadow-lg">
                            1
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-3">Search & Find</h3>
                    <p class="text-gray-600 leading-relaxed">Browse through our extensive catalog of genuine medicines and healthcare products.</p>
                </div>

                <!-- Step 2 -->
                <div class="glass-card rounded-3xl p-8 text-center shadow-2xl transform hover:scale-105 hover:shadow-purple-500/50 transition-all">
                    <div class="relative">
                        <div class="w-24 h-24 bg-gradient-to-br from-purple-500 to-purple-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-2xl">
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center font-black text-gray-900 shadow-lg">
                            2
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-3">Order Online</h3>
                    <p class="text-gray-600 leading-relaxed">Add items to cart and place your order securely with multiple payment options.</p>
                </div>

                <!-- Step 3 -->
                <div class="glass-card rounded-3xl p-8 text-center shadow-2xl transform hover:scale-105 hover:shadow-green-500/50 transition-all">
                    <div class="relative">
                        <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-green-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-2xl">
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center font-black text-gray-900 shadow-lg">
                            3
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-3">Fast Delivery</h3>
                    <p class="text-gray-600 leading-relaxed">Get your medicines delivered to your doorstep quickly and safely.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Medicines -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center mb-12">
                <div>
                    <div class="inline-block bg-green-100 text-green-600 px-4 py-2 rounded-full font-bold text-sm mb-2">
                        Popular Products
                    </div>
                    <h2 class="text-5xl font-black text-gray-900">Featured Medicines</h2>
                </div>
                <a href="signup.php" class="hidden md:block bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all">
                    View All →
                </a>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($featured_medicines as $medicine): ?>
                    <div class="glass-card rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transform hover:scale-105 transition-all group">
                        <div class="relative overflow-hidden">
                            <img src="/quickmed/assets/images/uploads/products/<?= $medicine['image'] ?>" 
                                 alt="<?= htmlspecialchars($medicine['name']) ?>"
                                 class="w-full h-56 object-cover group-hover:scale-110 transition-transform duration-500"
                                 onerror="this.src='https://via.placeholder.com/300x200?text=Medicine'">
                            <?php if ($medicine['category']): ?>
                                <span class="absolute top-3 left-3 bg-white px-3 py-1 rounded-full text-xs font-bold text-indigo-600 shadow-lg">
                                    <?= htmlspecialchars($medicine['category']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="p-5">
                            <h3 class="font-black text-lg mb-2 line-clamp-1"><?= htmlspecialchars($medicine['name']) ?></h3>
                            <p class="text-sm text-gray-600 mb-3 line-clamp-1"><?= htmlspecialchars($medicine['generic_name']) ?></p>
                            <div class="flex justify-between items-center">
                                <p class="text-3xl font-black text-indigo-600">৳<?= number_format($medicine['price'], 0) ?></p>
                                <span class="text-sm font-semibold text-green-600 bg-green-50 px-3 py-1 rounded-full">
                                    Stock: <?= $medicine['stock'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-12 md:hidden">
                <a href="signup.php" class="inline-block bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-4 rounded-xl font-bold shadow-lg hover:shadow-xl transform hover:scale-105 transition-all">
                    View All Medicines →
                </a>
            </div>
        </div>
    </section>

    <!-- Health News -->
    <section class="py-20 bg-gradient-to-br from-indigo-50 to-purple-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <div class="inline-block bg-yellow-100 text-yellow-600 px-4 py-2 rounded-full font-bold text-sm mb-2">
                    Stay Informed
                </div>
                <h2 class="text-5xl font-black text-gray-900 mb-4">Health Insights & News</h2>
                <p class="text-xl text-gray-600">Latest health tips and medical updates</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($health_news as $news): ?>
                    <div class="glass-card rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transform hover:scale-105 transition-all group">
                        <img src="/quickmed/assets/images/uploads/news/<?= $news['image'] ?>" 
                             alt="<?= htmlspecialchars($news['title']) ?>"
                             class="w-full h-56 object-cover group-hover:scale-110 transition-transform duration-500"
                             onerror="this.src='https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=400&h=300&fit=crop'">
                        <div class="p-6">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="bg-indigo-100 text-indigo-600 px-3 py-1 rounded-full text-xs font-bold">
                                    <?= date('M d, Y', strtotime($news['created_at'])) ?>
                                </span>
                                <?php if ($news['author']): ?>
                                    <span class="text-sm text-gray-600 font-semibold">By <?= htmlspecialchars($news['author']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="font-black text-xl mb-3 leading-tight"><?= htmlspecialchars($news['title']) ?></h3>
                            <p class="text-gray-600 mb-4 line-clamp-3 leading-relaxed"><?= htmlspecialchars($news['description']) ?></p>
                            <a href="#" class="text-indigo-600 font-bold hover:underline inline-flex items-center gap-2 group">
                                Read More 
                                <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="absolute top-0 left-0 w-full h-full">
            <div class="absolute top-20 left-20 w-72 h-72 bg-white rounded-full opacity-10 blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-white rounded-full opacity-10 blur-3xl"></div>
        </div>
        
        <div class="container mx-auto px-4 text-center relative z-10">
            <h2 class="text-5xl md:text-6xl font-black mb-6">Ready to Get Started?</h2>
            <p class="text-2xl mb-10 opacity-90 max-w-2xl mx-auto">Join thousands of satisfied customers today and experience healthcare made easy</p>
            <div class="flex gap-4 justify-center flex-wrap">
                <a href="signup.php" class="bg-white text-indigo-600 px-10 py-5 rounded-2xl font-black text-lg hover:shadow-2xl transform hover:scale-110 transition-all inline-flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Create Free Account
                </a>
                <a href="login.php" class="border-2 border-white text-white px-10 py-5 rounded-2xl font-black text-lg hover:bg-white hover:text-indigo-600 transform hover:scale-110 transition-all">
                    Sign In
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-16">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>
                        <span class="text-2xl font-black text-white">QuickMed</span>
                    </div>
                    <p class="text-sm leading-relaxed">Your trusted online pharmacy for genuine medicines and healthcare products.</p>
                </div>
                <div>
                    <h3 class="text-white font-black mb-4 text-lg">Quick Links</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">About Us</a></li>
                        <li><a href="#" class="hover:text-white transition">Shop</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white font-black mb-4 text-lg">Support</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">FAQ</a></li>
                        <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms & Conditions</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white font-black mb-4 text-lg">Contact</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                            </svg>
                            support@quickmed.com
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                            </svg>
                            +880 1700-000000
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"/>
                            </svg>
                            Dhaka, Bangladesh
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center">
                <p class="text-sm">© 2024 QuickMed. All rights reserved. Made with ❤️ for better healthcare.</p>
            </div>
        </div>
    </footer>

</body>
</html>