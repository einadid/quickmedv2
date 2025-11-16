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
            
            $success = 'Account created successfully! Please login.';
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
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8" x-data="signupForm()">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-indigo-600">QuickMed</h1>
            <p class="text-gray-600 mt-2">Create your account</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $success ?>
                <a href="login.php" class="underline font-semibold">Click here to login</a>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                <input type="tel" name="phone"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                <select name="role" x-model="selectedRole"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="customer">Customer</option>
                    <option value="salesman">Salesman</option>
                    <option value="shop_admin">Shop Admin</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <!-- Verification Code (Only for non-customers) -->
            <div x-show="selectedRole !== 'customer'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Company Verification Code *
                </label>
                <input type="text" 
                       name="verification_code" 
                       x-model="verificationCode"
                       :required="selectedRole !== 'customer'"
                       placeholder="e.g., QM-ADMIN-2025"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                
                <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800 font-semibold mb-1">ℹ️ Required Code Format:</p>
                    <ul class="text-xs text-yellow-700 space-y-1">
                        <li x-show="selectedRole === 'admin'">• Admin: QM-ADMIN-XXXX</li>
                        <li x-show="selectedRole === 'shop_admin'">• Shop Admin: QM-SHOPADMIN-XXXX</li>
                        <li x-show="selectedRole === 'salesman'">• Salesman: QM-SALESMAN-XXXX</li>
                    </ul>
                    <p class="text-xs text-yellow-600 mt-2">Contact your company administrator to get a valid code.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                <input type="password" name="password" required minlength="6"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                <input type="password" name="confirm_password" required minlength="6"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition duration-200">
                Create Account
            </button>
        </form>

        <p class="text-center text-gray-600 mt-6">
            Already have an account? 
            <a href="login.php" class="text-indigo-600 font-semibold hover:underline">Login</a>
        </p>
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