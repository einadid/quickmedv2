<?php
$pageTitle = 'User Management - Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Activate/Deactivate User
if (isset($_GET['toggle'])) {
    $user_id = (int)$_GET['toggle'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$user_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'USER_TOGGLED', 'users', $user_id, 'User status toggled');
        
        $success = 'User status updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update user status';
    }
}

// Delete User
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    if ($user_id === $_SESSION['user_id']) {
        $error = 'You cannot delete yourself!';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'USER_DELETED', 'users', $user_id, 'User deleted');
            
            $success = 'User deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to delete user';
        }
    }
}

// Add New User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
    $password = $_POST['password'];
    
    if (getUserByEmail($pdo, $email)) {
        $error = 'Email already exists';
    } else {
        try {
            createUser($pdo, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $role
            ]);
            
            // Update shop if needed
            if ($shop_id && in_array($role, ['shop_admin', 'salesman'])) {
                $user_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("UPDATE users SET shop_id = ? WHERE id = ?");
                $stmt->execute([$shop_id, $user_id]);
            }
            
            $success = 'User created successfully!';
        } catch (Exception $e) {
            $error = 'Failed to create user';
        }
    }
}

// Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, role = ?, shop_id = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $role, $shop_id, $user_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'USER_UPDATED', 'users', $user_id, 'User details updated');
        
        $success = 'User updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update user';
    }
}

// Filters
$role_filter = sanitize($_GET['role'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');

$where = [];
$params = [];

if ($role_filter !== 'all') {
    $where[] = "role = ?";
    $params[] = $role_filter;
}

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get users
$stmt = $pdo->prepare("
    SELECT u.*, s.name as shop_name 
    FROM users u 
    LEFT JOIN shops s ON u.shop_id = s.id
    $whereClause
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get all shops for dropdowns
$shops = $pdo->query("SELECT * FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll();
?>

<div class="container mx-auto px-4 py-8" x-data="{ showAddModal: false, editUser: null }">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">User Management</h1>
        <button @click="showAddModal = true" 
                class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700">
            ➕ Add New User
        </button>
    </div>

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

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-4 gap-4">
            <input type="text" 
                   name="search" 
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search by name, email, or phone..."
                   class="md:col-span-2 px-4 py-2 border border-gray-300 rounded-lg">
            
            <select name="role" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="all">All Roles</option>
                <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Customer</option>
                <option value="salesman" <?= $role_filter === 'salesman' ? 'selected' : '' ?>>Salesman</option>
                <option value="shop_admin" <?= $role_filter === 'shop_admin' ? 'selected' : '' ?>>Shop Admin</option>
                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>

            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                Filter
            </button>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-6 py-4 text-left font-semibold">User</th>
                        <th class="px-6 py-4 text-left font-semibold">Contact</th>
                        <th class="px-6 py-4 text-center font-semibold">Role</th>
                        <th class="px-6 py-4 text-left font-semibold">Shop</th>
                        <th class="px-6 py-4 text-center font-semibold">Status</th>
                        <th class="px-6 py-4 text-center font-semibold">Joined</th>
                        <th class="px-6 py-4 text-center font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="/quickmed/assets/images/uploads/profiles/<?= $user['profile_image'] ?>" 
                                         class="w-10 h-10 rounded-full"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>'">
                                    <div>
                                        <p class="font-semibold"><?= htmlspecialchars($user['name']) ?></p>
                                        <?php if ($user['role'] === 'customer' && $user['points'] > 0): ?>
                                            <p class="text-xs text-purple-600">🎁 <?= $user['points'] ?> points</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm"><?= htmlspecialchars($user['email']) ?></p>
                                <?php if ($user['phone']): ?>
                                    <p class="text-xs text-gray-600"><?= htmlspecialchars($user['phone']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                                    <?= match($user['role']) {
                                        'admin' => 'bg-red-100 text-red-800',
                                        'shop_admin' => 'bg-purple-100 text-purple-800',
                                        'salesman' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-green-100 text-green-800'
                                    } ?>">
                                    <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?= $user['shop_name'] ? htmlspecialchars($user['shop_name']) : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                                    <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-600">
                                <?= date('d M Y', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex gap-2 justify-center">
                                    <button @click="editUser = <?= htmlspecialchars(json_encode($user)) ?>" 
                                            class="text-blue-600 hover:underline text-sm">Edit</button>
                                    
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <a href="?toggle=<?= $user['id'] ?>" 
                                           class="text-orange-600 hover:underline text-sm">
                                            <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                        <a href="?delete=<?= $user['id'] ?>" 
                                           onclick="return confirm('Delete this user? This cannot be undone!')"
                                           class="text-red-600 hover:underline text-sm">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add User Modal -->
    <div x-show="showAddModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Add New User</h2>
                    <button @click="showAddModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Full Name *</label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Email *</label>
                            <input type="email" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Phone</label>
                            <input type="tel" name="phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Password *</label>
                            <input type="password" name="password" required minlength="6"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Role *</label>
                            <select name="role" required x-model="newUserRole"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="customer">Customer</option>
                                <option value="salesman">Salesman</option>
                                <option value="shop_admin">Shop Admin</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div x-show="['shop_admin', 'salesman'].includes(newUserRole)">
                            <label class="block text-sm font-semibold mb-2">Assign Shop</label>
                            <select name="shop_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">Select Shop</option>
                                <?php foreach ($shops as $shop): ?>
                                    <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_user"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Create User
                        </button>
                        <button type="button" @click="showAddModal = false"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div x-show="editUser" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Edit User</h2>
                    <button @click="editUser = null" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="user_id" x-model="editUser?.id">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Full Name *</label>
                            <input type="text" name="name" x-model="editUser.name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Phone</label>
                            <input type="tel" name="phone" x-model="editUser.phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Role *</label>
                            <select name="role" x-model="editUser.role" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="customer">Customer</option>
                                <option value="salesman">Salesman</option>
                                <option value="shop_admin">Shop Admin</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div x-show="['shop_admin', 'salesman'].includes(editUser?.role)">
                            <label class="block text-sm font-semibold mb-2">Assign Shop</label>
                            <select name="shop_id" x-model="editUser.shop_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="">Select Shop</option>
                                <?php foreach ($shops as $shop): ?>
                                    <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="update_user"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Update User
                        </button>
                        <button type="button" @click="editUser = null"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function userManagement() {
    return {
        newUserRole: 'customer'
    }
}
</script>

<?php include 'includes/footer.php'; ?>