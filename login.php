<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// If already logged in, redirect based on role
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $result = login($pdo, $email, $password);
        
        if ($result['success']) {
            // Redirect based on role
            switch ($result['role']) {
                case 'admin':
                    redirect('/quickmed/admin/dashboard.php');
                case 'shop_admin':
                    redirect('/quickmed/shop-admin/dashboard.php');
                case 'salesman':
                    redirect('/quickmed/salesman/pos.php');
                default:
                    redirect('/quickmed/customer/index.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QuickMed</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-indigo-600">QuickMed</h1>
            <p class="text-gray-600 mt-2">Welcome back!</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition duration-200">
                Login
            </button>
        </form>

        <p class="text-center text-gray-600 mt-6">
            Don't have an account? 
            <a href="signup.php" class="text-indigo-600 font-semibold hover:underline">Sign Up</a>
        </p>

        <div class="mt-6 pt-6 border-t border-gray-200">
            <p class="text-xs text-gray-500 text-center">Demo Accounts:</p>
            <p class="text-xs text-gray-500 text-center">Admin: admin@quickmed.com / admin123</p>
        </div>
    </div>
</body>
</html>