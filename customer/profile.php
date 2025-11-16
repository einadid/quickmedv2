<?php
$pageTitle = 'My Profile - QuickMed';
include 'includes/header.php';

$success = '';
$error = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $_SESSION['user_id']]);
        
        $_SESSION['name'] = $name;
        $success = 'Profile updated successfully!';
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Failed to update profile';
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $success = 'Password changed successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to change password';
        }
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">My Profile</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Profile Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Update Profile -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Profile Information</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100">
                        <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Phone</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <button type="submit" name="update_profile"
                            class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Change Password</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Current Password</label>
                        <input type="password" name="current_password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">New Password</label>
                        <input type="password" name="new_password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Confirm New Password</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    </div>

                    <button type="submit" name="change_password"
                            class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                        Change Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Profile Stats -->
            <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-xl shadow-lg p-6">
                <div class="text-center mb-4">
                    <img src="/quickmed/assets/images/uploads/profiles/<?= $user['profile_image'] ?>" 
                         class="w-24 h-24 rounded-full mx-auto border-4 border-white object-cover"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&size=200'">
                </div>
                <h3 class="text-xl font-bold text-center mb-4"><?= htmlspecialchars($user['name']) ?></h3>
                
                <div class="space-y-3">
                    <div class="bg-white/20 rounded-lg p-3">
                        <p class="text-indigo-100 text-sm">Member Since</p>
                        <p class="font-bold"><?= date('M Y', strtotime($user['created_at'])) ?></p>
                    </div>
                    <div class="bg-white/20 rounded-lg p-3">
                        <p class="text-indigo-100 text-sm">Reward Points</p>
                        <p class="font-bold text-2xl"><?= $user['points'] ?> 🎁</p>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">Quick Links</h3>
                <div class="space-y-2">
                    <a href="dashboard.php" class="block text-indigo-600 hover:underline">Dashboard</a>
                    <a href="orders.php" class="block text-indigo-600 hover:underline">My Orders</a>
                    <a href="addresses.php" class="block text-indigo-600 hover:underline">Manage Addresses</a>
                    <a href="/quickmed/logout.php" class="block text-red-600 hover:underline">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>