<?php
/**
 * MSCMS - Dashboard with Analytics Charts
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();
$pageTitle = 'Dashboard';

$stats = [
    'medicines'   => 0,
    'stock_units' => 0,
    'pending_pos' => 0,
    'low_stock'   => 0,
    'expiring'    => 0,
    'hospitals'   => 0,
    'orders'      => 0,
];
$recentLogs = [];
$topStock = [];
$stockByWarehouse = [];
$orderStatus = [];

try {
    $pdo = db();

    $stats['medicines']   = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
    $stats['stock_units'] = (int)$pdo->query("SELECT COALESCE(SUM(quantity_on_hand), 0) FROM inventory")->fetchColumn();
    $stats['pending_pos'] = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Draft','Submitted','Approved')")->fetchColumn();
    $stats['hospitals']   = (int)$pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_active = 1")->fetchColumn();
    $stats['orders']      = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    $stats['low_stock'] = (int)$pdo->query("
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

    $stats['expiring'] = (int)$pdo->query("
        SELECT COUNT(*) FROM batches 
        WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ")->fetchColumn();

    $topStock = $pdo->query("
        SELECT m.generic_name, SUM(i.quantity_on_hand) AS qty
        FROM inventory i
        JOIN batches b ON i.batch_id = b.batch_id
        JOIN medicines m ON b.medicine_id = m.medicine_id
        GROUP BY m.medicine_id
        ORDER BY qty DESC
        LIMIT 6
    ")->fetchAll();

    $stockByWarehouse = $pdo->query("
        SELECT w.warehouse_name, COALESCE(SUM(i.quantity_on_hand), 0) AS total_qty
        FROM warehouses w
        LEFT JOIN inventory i ON w.warehouse_id = i.warehouse_id
        WHERE w.is_active = 1
        GROUP BY w.warehouse_id
        ORDER BY total_qty DESC
        LIMIT 8
    ")->fetchAll();

    $orderStatus = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM orders
        GROUP BY status
    ")->fetchAll();

    $recentLogs = $pdo->query("
        SELECT a.action, a.module, a.created_at, u.full_name
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT 6
    ")->fetchAll();

} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
        <p class="text-gray-500 mt-1">Welcome, <?= e($user['full_name']) ?> · Updates, Analytics overview and Insights</p>
    </div>
     <!-- Quick Links -->
    <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
       <a href="medicines_manage.php?action=add" class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:border-teal-500 hover:shadow-md transition group">
            <i class="fas fa-pills text-2xl text-teal-600 group-hover:scale-110 transition"></i>
            <p class="mt-2 text-sm font-medium text-gray-700">Add Medicines</p>
        </a>
        <a href="hospitals.php?action=add" class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:border-teal-500 hover:shadow-md transition group">
            <i class="fas fa-shopping-cart text-2xl text-teal-600 group-hover:scale-110 transition"></i>
            <p class="mt-2 text-sm font-medium text-gray-700">Add Hospitals</p>
        </a>
        <a href="suppliers.php?action=add" class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:border-teal-500 hover:shadow-md transition group">
            <i class="fas fa-truck text-2xl text-teal-600 group-hover:scale-110 transition"></i>
            <p class="mt-2 text-sm font-medium text-gray-700">Add Suppliers</p>
        </a>
        <a href="warehouses.php?action=add" class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:border-teal-500 hover:shadow-md transition group">
            <i class="fas fa-chart-line text-2xl text-teal-600 group-hover:scale-110 transition"></i>
            <p class="mt-2 text-sm font-medium text-gray-700">Add Warehouses</p>
        </a>  
    </div>
      <br>
    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Medicines</p>
            <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatNumber($stats['medicines']) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Stock Units</p>
            <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatNumber($stats['stock_units']) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Pending POs</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1"><?= formatNumber($stats['pending_pos']) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Low Stock</p>
            <p class="text-2xl font-bold text-orange-600 mt-1"><?= formatNumber($stats['low_stock']) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Expiring 90d</p>
            <p class="text-2xl font-bold text-red-600 mt-1"><?= formatNumber($stats['expiring']) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Hospitals</p>
            <p class="text-2xl font-bold text-teal-600 mt-1"><?= formatNumber($stats['hospitals']) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500">Orders</p>
            <p class="text-2xl font-bold text-gray-800 mt-1"><?= formatNumber($stats['orders']) ?></p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Stock by Warehouse Chart -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-warehouse text-teal-600 mr-2"></i> Stock by Warehouse
            </h2>
            <canvas id="warehouseChart" height="220"></canvas>
        </div>

        <!-- Top Medicines Chart -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-pills text-teal-600 mr-2"></i> Top Medicines by Stock
            </h2>
            <canvas id="medicineChart" height="220"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Status -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-teal-600 mr-2"></i> Order Status
            </h2>
            <canvas id="orderChart" height="200"></canvas>
        </div>

        <!-- Recent Activity -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-history text-teal-600 mr-2"></i> Recent Activity
            </h2>
            <div class="space-y-3">
                <?php if (!empty($recentLogs)): ?>
                    <?php foreach ($recentLogs as $log): ?>
                    <div class="flex gap-3 text-sm border-b border-gray-50 pb-2">
                        <div class="w-8 h-8 rounded-full bg-teal-50 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-circle-check text-teal-600 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-gray-800 font-medium"><?= e($log['action']) ?> <span class="text-gray-400">· <?= e($log['module']) ?></span></p>
                            <p class="text-gray-500 text-xs"><?= e($log['full_name'] ?? 'System') ?> · <?= formatDate($log['created_at'], 'd M Y H:i') ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm text-center py-6">No recent activity</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Warehouse Chart
new Chart(document.getElementById('warehouseChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($stockByWarehouse, 'warehouse_name') ?: ['No data']) ?>,
        datasets: [{
            label: 'Stock Units',
            data: <?= json_encode(array_column($stockByWarehouse, 'total_qty') ?: [0]) ?>,
            backgroundColor: '#0d9488',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Medicine Chart
new Chart(document.getElementById('medicineChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topStock, 'generic_name') ?: ['No data']) ?>,
        datasets: [{
            label: 'Quantity',
            data: <?= json_encode(array_column($topStock, 'qty') ?: [0]) ?>,
            backgroundColor: '#14b8a6',
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true } }
    }
});

// Order Status Chart
new Chart(document.getElementById('orderChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($orderStatus, 'status') ?: ['No orders']) ?>,
        datasets: [{
            data: <?= json_encode(array_column($orderStatus, 'cnt') ?: [1]) ?>,
            backgroundColor: ['#0d9488', '#14b8a6', '#5eead4', '#f59e0b', '#ef4444', '#6366f1']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

</body>
</html>