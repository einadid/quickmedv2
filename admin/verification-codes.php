<?php
$pageTitle = 'Verification Codes - Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Generate New Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    $role = sanitize($_POST['role']);
    $custom_code = sanitize($_POST['custom_code']);
    
    if (empty($custom_code)) {
        $error = 'Code cannot be empty';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO verification_codes (code, role) VALUES (?, ?)");
            $stmt->execute([$custom_code, $role]);
            
            logActivity($pdo, $_SESSION['user_id'], 'CODE_GENERATED', 'verification_codes', $pdo->lastInsertId(), "Code: $custom_code");
            
            $success = 'Verification code generated successfully!';
        } catch (PDOException $e) {
            $error = 'Code already exists or invalid';
        }
    }
}

// Delete Code
if (isset($_GET['delete'])) {
    $code_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ? AND is_used = 0");
        $stmt->execute([$code_id]);
        
        if ($stmt->rowCount() > 0) {
            $success = 'Code deleted successfully!';
        } else {
            $error = 'Cannot delete used codes';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete code';
    }
}

// Get all codes
$stmt = $pdo->query("
    SELECT 
        vc.*,
        u.name as used_by_name,
        u.email as used_by_email
    FROM verification_codes vc
    LEFT JOIN users u ON vc.used_by = u.id
    ORDER BY vc.created_at DESC
");
$codes = $stmt->fetchAll();

// Stats
$total_codes = count($codes);
$used_codes = count(array_filter($codes, fn($c) => $c['is_used']));
$available_codes = $total_codes - $used_codes;
?>

<div class="container mx-auto px-4 py-8" x-data="{ showGenerateModal: false, selectedRole: 'salesman' }">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Verification Codes</h1>
        <button @click="showGenerateModal = true" 
                class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700">
            ➕ Generate New Code
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

    <!-- Stats -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Total Codes</p>
            <p class="text-4xl font-bold text-indigo-600"><?= $total_codes ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Available</p>
            <p class="text-4xl font-bold text-green-600"><?= $available_codes ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Used</p>
            <p class="text-4xl font-bold text-red-600"><?= $used_codes ?></p>
        </div>
    </div>

    <!-- Codes Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-6 py-4 text-left font-semibold">Code</th>
                        <th class="px-6 py-4 text-center font-semibold">Role</th>
                        <th class="px-6 py-4 text-center font-semibold">Status</th>
                        <th class="px-6 py-4 text-left font-semibold">Used By</th>
                        <th class="px-6 py-4 text-center font-semibold">Created</th>
                        <th class="px-6 py-4 text-center font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <span class="font-mono font-bold text-lg"><?= htmlspecialchars($code['code']) ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                                    <?= match($code['role']) {
                                        'admin' => 'bg-red-100 text-red-800',
                                        'shop_admin' => 'bg-purple-100 text-purple-800',
                                        'salesman' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    } ?>">
                                    <?= ucfirst(str_replace('_', ' ', $code['role'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                                    <?= $code['is_used'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= $code['is_used'] ? '✓ Used' : '○ Available' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($code['used_by_name']): ?>
                                    <div>
                                        <p class="font-semibold"><?= htmlspecialchars($code['used_by_name']) ?></p>
                                        <p class="text-xs text-gray-600"><?= htmlspecialchars($code['used_by_email']) ?></p>
                                        <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($code['used_at'])) ?></p>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-600">
                                <?= date('M d, Y', strtotime($code['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!$code['is_used']): ?>
                                    <a href="?delete=<?= $code['id'] ?>" 
                                       onclick="return confirm('Delete this code?')"
                                       class="text-red-600 hover:underline text-sm">Delete</a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Generate Code Modal -->
    <div x-show="showGenerateModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Generate New Code</h2>
                    <button @click="showGenerateModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Role *</label>
                        <select name="role" x-model="selectedRole" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                            <option value="admin">Admin</option>
                            <option value="shop_admin">Shop Admin</option>
                            <option value="salesman">Salesman</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Custom Code *</label>
                        <input type="text" 
                               name="custom_code" 
                               required
                               placeholder="e.g., QM-ADMIN-2025"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg font-mono">
                        <p class="text-xs text-gray-500 mt-1">Use format: QM-[ROLE]-[YEAR/NUMBER]</p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-sm text-blue-800 font-semibold mb-1">💡 Suggested Format:</p>
                        <ul class="text-xs text-blue-700 space-y-1">
                            <li x-show="selectedRole === 'admin'">• QM-ADMIN-2025</li>
                            <li x-show="selectedRole === 'shop_admin'">• QM-SHOPADMIN-2025</li>
                            <li x-show="selectedRole === 'salesman'">• QM-SALESMAN-2025</li>
                        </ul>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="generate_code"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Generate Code
                        </button>
                        <button type="button" @click="showGenerateModal = false"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>