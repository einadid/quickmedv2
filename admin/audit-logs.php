<?php
$pageTitle = 'Audit Logs - Admin';
include 'includes/header.php';

// Filters
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$action_filter = sanitize($_GET['action'] ?? '');
$date_from = sanitize($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
$date_to = sanitize($_GET['to'] ?? date('Y-m-d'));

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$where = ["DATE(al.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($user_filter) {
    $where[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where[] = "al.action = ?";
    $params[] = $action_filter;
}

$whereClause = implode(' AND ', $where);

// Get logs
$stmt = $pdo->prepare("
    SELECT 
        al.*,
        u.name as user_name,
        u.email as user_email,
        u.role as user_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $whereClause
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get total count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM audit_logs al WHERE $whereClause
");
$stmt->execute($params);
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Get users for filter
$users = $pdo->query("
    SELECT id, name, email, role 
    FROM users 
    WHERE role IN ('admin', 'shop_admin', 'salesman')
    ORDER BY name
")->fetchAll();

// Get unique actions
$actions = $pdo->query("
    SELECT DISTINCT action 
    FROM audit_logs 
    ORDER BY action
")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Audit Logs</h1>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-2">User</label>
                <select name="user" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $user_filter === $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name']) ?> (<?= ucfirst($user['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Action</label>
                <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= $action ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                            <?= htmlspecialchars($action) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">From Date</label>
                <input type="date" name="from" value="<?= $date_from ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">To Date</label>
                <input type="date" name="to" value="<?= $date_to ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Total Logs</p>
            <p class="text-3xl font-bold text-red-600"><?= number_format($total_logs) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Date Range</p>
            <p class="text-lg font-bold"><?= date('M d', strtotime($date_from)) ?> - <?= date('M d', strtotime($date_to)) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Current Page</p>
            <p class="text-3xl font-bold text-blue-600"><?= $page ?> / <?= $total_pages ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-gray-600 mb-2">Showing</p>
            <p class="text-3xl font-bold text-green-600"><?= count($logs) ?></p>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Timestamp</th>
                        <th class="px-4 py-3 text-left font-semibold">User</th>
                        <th class="px-4 py-3 text-left font-semibold">Action</th>
                        <th class="px-4 py-3 text-left font-semibold">Table</th>
                        <th class="px-4 py-3 text-left font-semibold">Details</th>
                        <th class="px-4 py-3 text-left font-semibold">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div>
                                    <p class="font-semibold"><?= date('M d, Y', strtotime($log['created_at'])) ?></p>
                                    <p class="text-xs text-gray-600"><?= date('h:i:s A', strtotime($log['created_at'])) ?></p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($log['user_name']): ?>
                                    <div>
                                        <p class="font-semibold"><?= htmlspecialchars($log['user_name']) ?></p>
                                        <p class="text-xs text-gray-600"><?= htmlspecialchars($log['user_email']) ?></p>
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                            <?= match($log['user_role']) {
                                                'admin' => 'bg-red-100 text-red-800',
                                                'shop_admin' => 'bg-purple-100 text-purple-800',
                                                'salesman' => 'bg-blue-100 text-blue-800',
                                                default => 'bg-green-100 text-green-800'
                                            } ?>">
                                            <?= ucfirst($log['user_role'] ?? 'Unknown') ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-500">System</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold
                                    <?php
                                    $action_color = match(true) {
                                        str_contains($log['action'], 'CREATE') || str_contains($log['action'], 'ADD') => 'bg-green-100 text-green-800',
                                        str_contains($log['action'], 'UPDATE') || str_contains($log['action'], 'EDIT') => 'bg-blue-100 text-blue-800',
                                        str_contains($log['action'], 'DELETE') || str_contains($log['action'], 'REMOVE') => 'bg-red-100 text-red-800',
                                        str_contains($log['action'], 'LOGIN') => 'bg-purple-100 text-purple-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    echo $action_color;
                                    ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                <?= htmlspecialchars($log['table_name']) ?>
                                <?php if ($log['record_id']): ?>
                                    <span class="text-gray-500">#<?= $log['record_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="text-xs text-gray-700 truncate" title="<?= htmlspecialchars($log['details']) ?>">
                                    <?= htmlspecialchars($log['details']) ?>
                                </p>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($logs) === 0): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📝</div>
                <p class="text-gray-600">No audit logs found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="flex justify-center gap-2 mt-8">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&user=<?= $user_filter ?>&action=<?= $action_filter ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" 
                   class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100">
                    ← Previous
                </a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?= $i ?>&user=<?= $user_filter ?>&action=<?= $action_filter ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" 
                   class="px-4 py-2 rounded-lg <?= $i === $page ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&user=<?= $user_filter ?>&action=<?= $action_filter ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" 
                   class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100">
                    Next →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>