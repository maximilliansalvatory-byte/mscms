<?php
/**
 * MSCMS - Procurement Management
 * Purchase Requisitions & Purchase Orders
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$tab = $_GET['tab'] ?? 'orders';   // orders | requisitions
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$purchaseOrders = [];
$requisitions = [];
$error = null;

try {
    $pdo = db();

    // ========== Purchase Orders ==========
    $sqlPO = "
        SELECT 
            po.po_id,
            po.po_number,
            po.order_date,
            po.expected_delivery_date,
            po.status,
            po.total_amount,
            s.company_name AS supplier_name,
            u.full_name AS created_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        JOIN users u ON po.created_by = u.user_id
        WHERE 1=1
    ";
    $paramsPO = [];

    if ($search !== '') {
        $sqlPO .= " AND (po.po_number LIKE ? OR s.company_name LIKE ?)";
        $paramsPO[] = "%$search%";
        $paramsPO[] = "%$search%";
    }
    if ($status !== '') {
        $sqlPO .= " AND po.status = ?";
        $paramsPO[] = $status;
    }
    $sqlPO .= " ORDER BY po.created_at DESC";

    $stmt = $pdo->prepare($sqlPO);
    $stmt->execute($paramsPO);
    $purchaseOrders = $stmt->fetchAll();

    // ========== Requisitions ==========
    $sqlReq = "
        SELECT 
            pr.requisition_id,
            pr.requisition_no,
            pr.status,
            pr.created_at,
            pr.notes,
            w.warehouse_name,
            u.full_name AS requested_by_name
        FROM purchase_requisitions pr
        JOIN warehouses w ON pr.warehouse_id = w.warehouse_id
        JOIN users u ON pr.requested_by = u.user_id
        ORDER BY pr.created_at DESC
    ";
    $requisitions = $pdo->query($sqlReq)->fetchAll();

} catch (Exception $e) {
    $error = "Could not load procurement data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement - MSCMS</title>
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
                        <a href="procurement.php" class="text-white font-medium border-b-2 border-white">Procurement</a>
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
        <h1 class="text-2xl font-bold text-gray-800">Procurement</h1>
        <p class="text-gray-500 text-sm mt-1">Manage Purchase Requisitions and Purchase Orders</p>
    </div>
    <a href="purchase_order_create.php" 
       class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Create Purchase Order
    </a>
</div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 border-b border-gray-200">
            <a href="?tab=orders" 
               class="px-4 py-2 text-sm font-medium rounded-t-lg <?= $tab === 'orders' ? 'bg-white border border-b-0 border-gray-200 text-teal-700' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-file-invoice mr-1"></i> Purchase Orders
            </a>
            <a href="?tab=requisitions" 
               class="px-4 py-2 text-sm font-medium rounded-t-lg <?= $tab === 'requisitions' ? 'bg-white border border-b-0 border-gray-200 text-teal-700' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-clipboard-list mr-1"></i> Requisitions
            </a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'orders'): ?>
            <!-- Filters for POs -->
            <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                <input type="hidden" name="tab" value="orders">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <input type="text" name="search" value="<?= e($search) ?>" 
                               placeholder="PO number or supplier..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                            <option value="">All Statuses</option>
                            <option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="Submitted" <?= $status === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Partially Received" <?= $status === 'Partially Received' ? 'selected' : '' ?>>Partially Received</option>
                            <option value="Received" <?= $status === 'Received' ? 'selected' : '' ?>>Received</option>
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

            <!-- Purchase Orders Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium">PO Number</th>
                                <th class="text-left px-4 py-3 font-medium">Supplier</th>
                                <th class="text-left px-4 py-3 font-medium">Order Date</th>
                                <th class="text-left px-4 py-3 font-medium">Expected Delivery</th>
                                <th class="text-right px-4 py-3 font-medium">Amount</th>
                                <th class="text-left px-4 py-3 font-medium">Status</th>
                                <th class="text-left px-4 py-3 font-medium">Created By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($purchaseOrders)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">No purchase orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchaseOrders as $po): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-teal-700"><?= e($po['po_number']) ?></td>
                                    <td class="px-4 py-3"><?= e($po['supplier_name']) ?></td>
                                    <td class="px-4 py-3"><?= formatDate($po['order_date']) ?></td>
                                    <td class="px-4 py-3"><?= formatDate($po['expected_delivery_date']) ?></td>
                                    <td class="px-4 py-3 text-right font-semibold"><?= formatNumber($po['total_amount'], 2) ?></td>
                                    <td class="px-4 py-3"><?= statusBadge($po['status']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= e($po['created_by_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-4">Showing <?= count($purchaseOrders) ?> purchase order(s)</p>

        <?php else: ?>
            <!-- Requisitions Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium">Requisition No</th>
                                <th class="text-left px-4 py-3 font-medium">Warehouse</th>
                                <th class="text-left px-4 py-3 font-medium">Requested By</th>
                                <th class="text-left px-4 py-3 font-medium">Date</th>
                                <th class="text-left px-4 py-3 font-medium">Status</th>
                                <th class="text-left px-4 py-3 font-medium">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($requisitions)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">No requisitions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requisitions as $req): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-teal-700"><?= e($req['requisition_no']) ?></td>
                                    <td class="px-4 py-3"><?= e($req['warehouse_name']) ?></td>
                                    <td class="px-4 py-3"><?= e($req['requested_by_name']) ?></td>
                                    <td class="px-4 py-3"><?= formatDate($req['created_at']) ?></td>
                                    <td class="px-4 py-3"><?= statusBadge($req['status']) ?></td>
                                    <td class="px-4 py-3 text-gray-500 text-xs"><?= e($req['notes'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-4">Showing <?= count($requisitions) ?> requisition(s)</p>
        <?php endif; ?>

    </div>
</body>
</html>