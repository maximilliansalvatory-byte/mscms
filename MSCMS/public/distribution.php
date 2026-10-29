<?php
/**
 * MSCMS - Distribution Management
 * Hospital Orders & Dispatches
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$tab = $_GET['tab'] ?? 'orders';   // orders | dispatches
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$orders = [];
$dispatches = [];
$error = null;

try {
    $pdo = db();

    // ========== Hospital Orders ==========
    $sqlOrders = "
        SELECT 
            o.order_id,
            o.order_number,
            o.order_date,
            o.status,
            h.hospital_name,
            w.warehouse_name,
            u.full_name AS ordered_by_name
        FROM orders o
        JOIN hospitals h ON o.hospital_id = h.hospital_id
        JOIN warehouses w ON o.warehouse_id = w.warehouse_id
        JOIN users u ON o.ordered_by = u.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($search !== '') {
        $sqlOrders .= " AND (o.order_number LIKE ? OR h.hospital_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($status !== '') {
        $sqlOrders .= " AND o.status = ?";
        $params[] = $status;
    }
    $sqlOrders .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sqlOrders);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // ========== Dispatches ==========
    $sqlDisp = "
        SELECT 
            d.dispatch_id,
            d.dispatch_date,
            d.driver_name,
            d.route,
            d.status,
            o.order_number,
            h.hospital_name,
            v.plate_number,
            u.full_name AS dispatched_by_name
        FROM dispatches d
        JOIN orders o ON d.order_id = o.order_id
        JOIN hospitals h ON o.hospital_id = h.hospital_id
        LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id
        JOIN users u ON d.dispatched_by = u.user_id
        ORDER BY d.created_at DESC
    ";
    $dispatches = $pdo->query($sqlDisp)->fetchAll();

} catch (Exception $e) {
    $error = "Could not load distribution data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution - MSCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Top Navigation -->
    <nav class="bg-teal-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-6">
                    <a href="dashboard.php" class="flex items-center gap-2 font-bold text-xl">
                        <i class="fas fa-pills"></i> MSCMS
                    </a>
                    <div class="hidden md:flex gap-4 text-sm">
                        <a href="dashboard.php" class="text-teal-200 hover:text-white">Dashboard</a>
                        <a href="inventory.php" class="text-teal-200 hover:text-white">Inventory</a>
                        <a href="procurement.php" class="text-teal-200 hover:text-white">Procurement</a>
                        <a href="distribution.php" class="text-white font-medium border-b-2 border-white">Distribution</a>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-teal-100">
                        <i class="fas fa-user-circle mr-1"></i>
                        <?= e($user['full_name']) ?>
                    </span>
                    <a href="logout.php" class="bg-teal-700 hover:bg-teal-600 px-3 py-1.5 rounded-lg text-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Distribution</h1>
        <p class="text-gray-500 text-sm mt-1">Manage Hospital Orders and Dispatches</p>
    </div>
    <a href="order_create.php" 
       class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Create Hospital Order
    </a>
</div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 border-b border-gray-200">
            <a href="?tab=orders" 
               class="px-4 py-2 text-sm font-medium rounded-t-lg <?= $tab === 'orders' ? 'bg-white border border-b-0 border-gray-200 text-teal-700' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-hospital mr-1"></i> Hospital Orders
            </a>
            <a href="?tab=dispatches" 
               class="px-4 py-2 text-sm font-medium rounded-t-lg <?= $tab === 'dispatches' ? 'bg-white border border-b-0 border-gray-200 text-teal-700' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-truck mr-1"></i> Dispatches
            </a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'orders'): ?>
            <!-- Filters -->
            <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                <input type="hidden" name="tab" value="orders">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <input type="text" name="search" value="<?= e($search) ?>" 
                               placeholder="Order number or hospital..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Dispatched" <?= $status === 'Dispatched' ? 'selected' : '' ?>>Dispatched</option>
                            <option value="Delivered" <?= $status === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-search mr-1"></i> Filter
                        </button>
                        <a href="?tab=orders" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Reset</a>
                    </div>
                </div>
            </form>

            <!-- Orders Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium">Order Number</th>
                                <th class="text-left px-4 py-3 font-medium">Hospital</th>
                                <th class="text-left px-4 py-3 font-medium">Warehouse</th>
                                <th class="text-left px-4 py-3 font-medium">Order Date</th>
                                <th class="text-left px-4 py-3 font-medium">Status</th>
                                <th class="text-left px-4 py-3 font-medium">Ordered By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">No hospital orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $ord): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-teal-700"><?= e($ord['order_number']) ?></td>
                                    <td class="px-4 py-3"><?= e($ord['hospital_name']) ?></td>
                                    <td class="px-4 py-3"><?= e($ord['warehouse_name']) ?></td>
                                    <td class="px-4 py-3"><?= formatDate($ord['order_date']) ?></td>
                                    <td class="px-4 py-3"><?= statusBadge($ord['status']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= e($ord['ordered_by_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-4">Showing <?= count($orders) ?> order(s)</p>

        <?php else: ?>
            <!-- Dispatches Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium">Dispatch Date</th>
                                <th class="text-left px-4 py-3 font-medium">Order Number</th>
                                <th class="text-left px-4 py-3 font-medium">Hospital</th>
                                <th class="text-left px-4 py-3 font-medium">Vehicle</th>
                                <th class="text-left px-4 py-3 font-medium">Driver</th>
                                <th class="text-left px-4 py-3 font-medium">Route</th>
                                <th class="text-left px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($dispatches)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">No dispatches found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dispatches as $d): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><?= formatDate($d['dispatch_date']) ?></td>
                                    <td class="px-4 py-3 font-medium text-teal-700"><?= e($d['order_number']) ?></td>
                                    <td class="px-4 py-3"><?= e($d['hospital_name']) ?></td>
                                    <td class="px-4 py-3"><?= e($d['plate_number'] ?? '-') ?></td>
                                    <td class="px-4 py-3"><?= e($d['driver_name'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= e($d['route'] ?? '-') ?></td>
                                    <td class="px-4 py-3"><?= statusBadge($d['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-4">Showing <?= count($dispatches) ?> dispatch(es)</p>
        <?php endif; ?>

    </div>
</body>
</html>