<?php
/**
 * MSCMS - Inventory Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$search   = trim($_GET['search'] ?? '');
$warehouse = $_GET['warehouse'] ?? '';
$status   = $_GET['status'] ?? '';

$inventory = [];
$warehouses = [];
$error = null;

try {
    $pdo = db();

    // Get warehouses for filter
    $warehouses = $pdo->query("SELECT warehouse_id, warehouse_name FROM warehouses WHERE is_active = 1 ORDER BY warehouse_name")->fetchAll();

    // Build query
    $sql = "
        SELECT 
            i.inventory_id,
            i.quantity_on_hand,
            i.last_updated,
            m.medicine_id,
            m.generic_name,
            m.brand_name,
            m.strength,
            m.dosage_form,
            m.reorder_level,
            m.is_controlled_drug,
            b.batch_id,
            b.batch_number,
            b.expiry_date,
            b.quality_status,
            w.warehouse_name,
            wl.shelf_code,
            wl.bin_code,
            wz.zone_name
        FROM inventory i
        JOIN batches b ON i.batch_id = b.batch_id
        JOIN medicines m ON b.medicine_id = m.medicine_id
        JOIN warehouses w ON i.warehouse_id = w.warehouse_id
        LEFT JOIN warehouse_locations wl ON i.location_id = wl.location_id
        LEFT JOIN warehouse_zones wz ON wl.zone_id = wz.zone_id
        WHERE 1=1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (m.generic_name LIKE ? OR m.brand_name LIKE ? OR b.batch_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($warehouse !== '') {
        $sql .= " AND i.warehouse_id = ?";
        $params[] = $warehouse;
    }

    if ($status === 'low') {
        $sql .= " AND i.quantity_on_hand < m.reorder_level";
    } elseif ($status === 'expiring') {
        $sql .= " AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
    } elseif ($status === 'expired') {
        $sql .= " AND b.expiry_date < CURDATE()";
    }

    $sql .= " ORDER BY m.generic_name, b.expiry_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Could not load inventory data. Please check database connection.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - MSCMS</title>
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
                        <a href="inventory.php" class="text-white font-medium border-b-2 border-white">Inventory</a>
                        <a href="procurement.php" class="text-teal-200 hover:text-white">Procurement</a>
                        <a href="distribution.php" class="text-teal-200 hover:text-white">Distribution</a>
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
        <h1 class="text-2xl font-bold text-gray-800">Inventory</h1>
        <p class="text-gray-500 text-sm mt-1">Batch-level stock across all warehouses</p>
    </div>
    <div class="flex gap-2">
        <a href="purchase_order_create.php" 
           class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
            <i class="fas fa-cart-plus"></i> New Purchase Order
        </a>
    </div>
</div>

        <!-- Filters -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" 
                           placeholder="Medicine or batch..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Warehouse</label>
                    <select name="warehouse" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['warehouse_id'] ?>" <?= $warehouse == $w['warehouse_id'] ? 'selected' : '' ?>>
                                <?= e($w['warehouse_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">All</option>
                        <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="expiring" <?= $status === 'expiring' ? 'selected' : '' ?>>Expiring (90 days)</option>
                        <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="inventory.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Inventory Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">Medicine</th>
                            <th class="text-left px-4 py-3 font-medium">Batch</th>
                            <th class="text-left px-4 py-3 font-medium">Warehouse / Location</th>
                            <th class="text-right px-4 py-3 font-medium">Qty on Hand</th>
                            <th class="text-left px-4 py-3 font-medium">Expiry</th>
                            <th class="text-left px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($inventory)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                    No inventory records found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventory as $row): 
                                $isLow = $row['quantity_on_hand'] < $row['reorder_level'];
                                $isExpired = strtotime($row['expiry_date']) < time();
                                $isExpiring = !$isExpired && strtotime($row['expiry_date']) < strtotime('+90 days');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">
                                        <?= e($row['generic_name']) ?>
                                        <?php if ($row['is_controlled_drug']): ?>
                                            <span class="ml-1 text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded">Controlled</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= e($row['brand_name'] ?? '') ?> · <?= e($row['strength']) ?> · <?= e($row['dosage_form']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-700">
                                    <?= e($row['batch_number']) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-800"><?= e($row['warehouse_name']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?= e($row['zone_name'] ?? '') ?> 
                                        <?= $row['shelf_code'] ? '· ' . e($row['shelf_code']) . '/' . e($row['bin_code']) : '' ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold <?= $isLow ? 'text-orange-600' : 'text-gray-800' ?>">
                                        <?= formatNumber($row['quantity_on_hand']) ?>
                                    </span>
                                    <?php if ($isLow): ?>
                                        <div class="text-xs text-orange-500">Below reorder</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="<?= $isExpired ? 'text-red-600 font-medium' : ($isExpiring ? 'text-yellow-600' : 'text-gray-700') ?>">
                                        <?= formatDate($row['expiry_date']) ?>
                                    </span>
                                    <?php if ($isExpired): ?>
                                        <div class="text-xs text-red-500">Expired</div>
                                    <?php elseif ($isExpiring): ?>
                                        <div class="text-xs text-yellow-600">Expiring soon</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?= statusBadge($row['quality_status']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-sm text-gray-500 mt-4">
            Showing <?= count($inventory) ?> record(s)
        </p>
    </div>
</body>
</html>