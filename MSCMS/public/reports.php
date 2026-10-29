<?php
/**
 * MSCMS - Advanced Reports & Analytics
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$error = null;
$stats = [
    'total_medicines' => 0,
    'total_stock'     => 0,
    'low_stock'       => 0,
    'expiring_90'     => 0,
    'expired'         => 0,
    'pending_pos'     => 0,
    'pending_orders'  => 0,
    'total_suppliers' => 0,
    'total_hospitals' => 0,
];

$stockByWarehouse = [];
$topMedicines     = [];
$expiringBatches  = [];

try {
    $pdo = db();

    // Summary Stats (safe queries)
    $stats['total_medicines'] = (int)@$pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
    $stats['total_stock']     = (int)@$pdo->query("SELECT COALESCE(SUM(quantity_on_hand), 0) FROM inventory")->fetchColumn();
    $stats['pending_pos']     = (int)@$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Draft','Submitted','Approved')")->fetchColumn();
    $stats['pending_orders']  = (int)@$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('Pending','Approved')")->fetchColumn();
    $stats['total_suppliers'] = (int)@$pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_active = 1")->fetchColumn();
    $stats['total_hospitals'] = (int)@$pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_active = 1")->fetchColumn();

    $stats['low_stock'] = (int)@$pdo->query("
        SELECT COUNT(DISTINCT m.medicine_id)
        FROM medicines m
        LEFT JOIN (
            SELECT b.medicine_id, SUM(i.quantity_on_hand) AS total_qty
            FROM inventory i
            JOIN batches b ON i.batch_id = b.batch_id
            GROUP BY b.medicine_id
        ) inv ON m.medicine_id = inv.medicine_id
        WHERE m.is_active = 1 AND COALESCE(inv.total_qty, 0) < m.reorder_level
    ")->fetchColumn();

    $stats['expiring_90'] = (int)@$pdo->query("
        SELECT COUNT(*) FROM batches 
        WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ")->fetchColumn();

    $stats['expired'] = (int)@$pdo->query("
        SELECT COUNT(*) FROM batches WHERE expiry_date < CURDATE()
    ")->fetchColumn();

    // Stock by Warehouse
    $stockByWarehouse = @$pdo->query("
        SELECT w.warehouse_name, COALESCE(SUM(i.quantity_on_hand), 0) AS total_qty
        FROM warehouses w
        LEFT JOIN inventory i ON w.warehouse_id = i.warehouse_id
        WHERE w.is_active = 1
        GROUP BY w.warehouse_id
        ORDER BY total_qty DESC
    ")->fetchAll() ?: [];

    // Top medicines
    $topMedicines = @$pdo->query("
        SELECT m.generic_name, m.brand_name, SUM(i.quantity_on_hand) AS qty
        FROM inventory i
        JOIN batches b ON i.batch_id = b.batch_id
        JOIN medicines m ON b.medicine_id = m.medicine_id
        GROUP BY m.medicine_id
        ORDER BY qty DESC
        LIMIT 10
    ")->fetchAll() ?: [];

    // Expiring batches
    $expiringBatches = @$pdo->query("
        SELECT 
            m.generic_name,
            b.batch_number,
            b.expiry_date,
            i.quantity_on_hand,
            w.warehouse_name
        FROM batches b
        JOIN medicines m ON b.medicine_id = m.medicine_id
        JOIN inventory i ON b.batch_id = i.batch_id
        JOIN warehouses w ON i.warehouse_id = w.warehouse_id
        WHERE b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY b.expiry_date ASC
        LIMIT 15
    ")->fetchAll() ?: [];

} catch (Exception $e) {
    $error = "Some report data could not be loaded. Please check if all tables have data.";
}

// ========== CSV Download ==========
if (isset($_GET['download']) && $_GET['download'] === 'expiring') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expiring_batches_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Medicine', 'Batch Number', 'Warehouse', 'Quantity', 'Expiry Date']);
    foreach ($expiringBatches as $row) {
        fputcsv($out, [
            $row['generic_name'],
            $row['batch_number'],
            $row['warehouse_name'],
            $row['quantity_on_hand'],
            $row['expiry_date']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - MSCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .print-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Top Navigation -->
    <nav class="bg-teal-800 text-white shadow-lg no-print">
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
                        <a href="distribution.php" class="text-teal-200 hover:text-white">Distribution</a>
                        <a href="reports.php" class="text-white font-medium border-b-2 border-white">Reports</a>
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

        <!-- Header + Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
                <p class="text-gray-500 text-sm mt-1">System overview • Generated on <?= date('d M Y H:i') ?></p>
            </div>
            <div class="flex gap-2 no-print">
                <button onclick="window.print()" 
                        class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <a href="?download=expiring" 
                   class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
                    <i class="fas fa-download"></i> Download CSV
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-6 text-sm">
                <i class="fas fa-info-circle mr-1"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Active Medicines</p>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?= formatNumber($stats['total_medicines']) ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Stock</p>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?= formatNumber($stats['total_stock']) ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Low Stock</p>
                <p class="text-3xl font-bold text-orange-600 mt-2"><?= formatNumber($stats['low_stock']) ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Expiring 90d</p>
                <p class="text-3xl font-bold text-yellow-600 mt-2"><?= formatNumber($stats['expiring_90']) ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Expired</p>
                <p class="text-3xl font-bold text-red-600 mt-2"><?= formatNumber($stats['expired']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Stock by Warehouse -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-warehouse text-teal-600 mr-2"></i> Stock by Warehouse
                </h2>
                <?php if (empty($stockByWarehouse)): ?>
                    <p class="text-gray-400 text-sm py-4">No warehouse stock data available</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php 
                        $maxQty = max(array_column($stockByWarehouse, 'total_qty')) ?: 1;
                        foreach ($stockByWarehouse as $row): 
                            $percent = round(($row['total_qty'] / $maxQty) * 100);
                        ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700"><?= e($row['warehouse_name']) ?></span>
                                <span class="font-semibold"><?= formatNumber($row['total_qty']) ?></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-teal-600 h-2 rounded-full" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Medicines -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-pills text-teal-600 mr-2"></i> Top Medicines by Quantity
                </h2>
                <?php if (empty($topMedicines)): ?>
                    <p class="text-gray-400 text-sm py-4">No medicine stock data available</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($topMedicines as $i => $row): ?>
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-teal-100 text-teal-700 text-xs flex items-center justify-center font-bold">
                                <?= $i + 1 ?>
                            </span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800"><?= e($row['generic_name']) ?></p>
                                <?php if ($row['brand_name']): ?>
                                    <p class="text-xs text-gray-400"><?= e($row['brand_name']) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="font-semibold text-gray-800"><?= formatNumber($row['qty']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expiring Batches Table -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-clock text-yellow-600 mr-2"></i> Batches Expiring in Next 90 Days
                </h2>
                <a href="?download=expiring" class="text-sm text-teal-700 hover:underline no-print">
                    <i class="fas fa-download mr-1"></i> Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">Medicine</th>
                            <th class="text-left px-4 py-3 font-medium">Batch</th>
                            <th class="text-left px-4 py-3 font-medium">Warehouse</th>
                            <th class="text-right px-4 py-3 font-medium">Qty</th>
                            <th class="text-left px-4 py-3 font-medium">Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($expiringBatches)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                    No batches expiring in the next 90 days
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expiringBatches as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?= e($row['generic_name']) ?></td>
                                <td class="px-4 py-3 font-mono text-gray-600"><?= e($row['batch_number']) ?></td>
                                <td class="px-4 py-3"><?= e($row['warehouse_name']) ?></td>
                                <td class="px-4 py-3 text-right"><?= formatNumber($row['quantity_on_hand']) ?></td>
                                <td class="px-4 py-3 text-yellow-700 font-medium"><?= formatDate($row['expiry_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom KPIs -->
        <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border p-5 text-center">
                <p class="text-3xl font-bold text-teal-700"><?= formatNumber($stats['pending_pos']) ?></p>
                <p class="text-xs text-gray-500 mt-1">Pending Purchase Orders</p>
            </div>
            <div class="bg-white rounded-xl border p-5 text-center">
                <p class="text-3xl font-bold text-teal-700"><?= formatNumber($stats['pending_orders']) ?></p>
                <p class="text-xs text-gray-500 mt-1">Pending Hospital Orders</p>
            </div>
            <div class="bg-white rounded-xl border p-5 text-center">
                <p class="text-3xl font-bold text-teal-700"><?= formatNumber($stats['total_suppliers']) ?></p>
                <p class="text-xs text-gray-500 mt-1">Active Suppliers</p>
            </div>
            <div class="bg-white rounded-xl border p-5 text-center">
                <p class="text-3xl font-bold text-teal-700"><?= formatNumber($stats['total_hospitals']) ?></p>
                <p class="text-xs text-gray-500 mt-1">Active Hospitals</p>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-8 no-print">
            MSCMS Report generated for <?= e($user['full_name']) ?> • <?= date('d M Y H:i') ?>
        </p>
    </div>
</body>
</html>