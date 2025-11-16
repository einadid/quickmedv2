<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role'] ?? 'customer');
    $verification_code = sanitize($_POST['verification_code'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All required fields must be filled';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (getUserByEmail($pdo, $email)) {
        $error = 'Email already registered';
    } else {
        try {
            $user_id = createUser($pdo, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $role,
                'verification_code' => $verification_code
            ]);
            
            // Auto login
            if (autoLogin($pdo, $user_id)) {
                // Redirect based on role
                switch ($role) {
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
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - QuickMed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        [x-cloak] { display: none !important; }
        
        @keyframes blob {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
        
        .animate-blob {
            animation: blob 7s infinite;
        }
        
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        
        .animation-delay-4000 {
            animation-delay: 4s;
        }
        
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .animate-slide-in {
            animation: slideIn 0.6s ease-out;
        }
    </style>
</head>
<body class="min-h-screen relative overflow-hidden bg-gray-50">
    <!-- Animated Background -->
    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50"></div>
        <div class="absolute top-0 left-0 w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-yellow-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-20 w-96 h-96 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center p-4 py-12">
        <div class="w-full max-w-2xl">
            <!-- Logo -->
            <div class="text-center mb-8 animate-fade-in">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-2xl shadow-2xl mb-4">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent mb-2">Join QuickMed</h1>
                <p class="text-gray-600 font-medium">Create your account and get started</p>
            </div>

            <!-- Signup Card -->
            <div class="glass rounded-3xl shadow-2xl p-8 animate-slide-in" x-data="signupForm()">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                            </svg>
                            <p class="text-red-700 font-medium"><?= $error ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <div>
                                <p class="text-green-700 font-medium"><?= $success ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <div class="grid md:grid-cols-2 gap-5">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="name" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition"
                                   placeholder="John Doe">
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition"
                                   placeholder="john@example.com">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                            <input type="tel" name="phone" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition"
                                   placeholder="+880 1XXX-XXXXXX">
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Account Type *</label>
                            <select name="role" x-model="selectedRole" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition">
                                <option value="customer">Customer</option>
                                <option value="salesman">Salesman</option>
                                <option value="shop_admin">Shop Admin</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <!-- Verification Code (conditional) -->
                    <div x-show="selectedRole !== 'customer'" x-cloak>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Company Verification Code *</label>
                        <input type="text" 
                               name="verification_code" 
                               x-model="verificationCode"
                               :required="selectedRole !== 'customer'"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition font-mono"
                               placeholder="e.g., QM-ADMIN-2025">
                        
                        <div class="mt-3 p-4 bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400 rounded-lg">
                            <p class="text-sm font-semibold text-yellow-800 mb-2">🔑 Required Code Format:</p>
                            <ul class="text-xs text-yellow-700 space-y-1">
                                <li x-show="selectedRole === 'admin'">• Admin: <code class="bg-yellow-100 px-2 py-1 rounded">QM-ADMIN-XXXX</code></li>
                                <li x-show="selectedRole === 'shop_admin'">• Shop Admin: <code class="bg-yellow-100 px-2 py-1 rounded">QM-SHOPADMIN-XXXX</code></li>
                                <li x-show="selectedRole === 'salesman'">• Salesman: <code class="bg-yellow-100 px-2 py-1 rounded">QM-SALESMAN-XXXX</code></li>
                            </ul>
                            <p class="text-xs text-yellow-600 mt-2">📞 Contact your company administrator to get a valid code.</p>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-5">
                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Password *</label>
                            <input type="password" name="password" required minlength="6" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition"
                                   placeholder="••••••••">
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password *</label>
                            <input type="password" name="confirm_password" required minlength="6" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-4 rounded-xl font-bold text-base hover:shadow-2xl hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Create Account
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 font-medium">Already have an account?</span>
                    </div>
                </div>

                <!-- Login Link -->
                <a href="login.php" class="block w-full border-2 border-indigo-600 text-indigo-600 py-3 rounded-xl font-bold text-center hover:bg-indigo-50 transition">
                    Sign In Instead
                </a>
            </div>

            <p class="text-center text-sm text-gray-600 mt-8">© 2024 QuickMed. All rights reserved.</p>
        </div>
    </div>

    <script>
        function signupForm() {
            return {
                selectedRole: 'customer',
                verificationCode: ''
            }
        }
    </script>
</body>
</html>