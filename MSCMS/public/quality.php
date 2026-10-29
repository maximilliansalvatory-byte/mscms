<?php
/**
 * MSCMS - Quality Assurance (List-based Inspection)
 * Inspect only items that were actually received
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

if (!hasRole(['Super Administrator', 'Quality Assurance Officer'])) {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}

$message = '';
$error = '';
$pending = [];
$history = [];
$search = trim($_GET['search'] ?? '');

try {
    $pdo = db();

    // ========== HANDLE ACTION (Approve / Reject / Quarantine) ==========
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'inspect') {
        $receipt_item_id = (int)($_POST['receipt_item_id'] ?? 0);
        $result = trim($_POST['result'] ?? '');
        $lab_ref = trim($_POST['lab_ref'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($receipt_item_id > 0 && in_array($result, ['Approved', 'Rejected', 'Quarantined'])) {
            try {
                // Check not already inspected
                $exists = $pdo->prepare("SELECT inspection_id FROM quality_inspections WHERE receipt_item_id = ?");
                $exists->execute([$receipt_item_id]);
                if ($exists->fetch()) {
                    $error = "This item was already inspected.";
                } else {
                    $pdo->prepare("
                        INSERT INTO quality_inspections 
                        (receipt_item_id, inspected_by, inspection_date, result, lab_test_reference, remarks)
                        VALUES (?, ?, CURDATE(), ?, ?, ?)
                    ")->execute([$receipt_item_id, $user['user_id'], $result, $lab_ref ?: null, $remarks ?: null]);

                    // Update batch quality status
                    $pdo->prepare("
                        UPDATE batches b
                        JOIN goods_receipt_items gri ON b.batch_number = gri.batch_number
                        SET b.quality_status = ?
                        WHERE gri.receipt_item_id = ?
                    ")->execute([$result, $receipt_item_id]);

                    $message = "Item marked as <strong>$result</strong> successfully.";
                }
            } catch (Exception $e) {
                $error = "Failed: " . $e->getMessage();
            }
        } else {
            $error = "Invalid action.";
        }
    }

    // ========== PENDING ITEMS (not yet inspected) ==========
    $sql = "
        SELECT 
            gri.receipt_item_id,
            gri.batch_number,
            gri.quantity_received,
            gri.expiry_date,
            gri.manufacture_date,
            m.generic_name,
            m.brand_name,
            m.strength,
            po.po_number,
            s.company_name AS supplier_name,
            gr.receipt_date
        FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gri.receipt_id = gr.receipt_id
        JOIN purchase_order_items poi ON gri.po_item_id = poi.po_item_id
        JOIN medicines m ON poi.medicine_id = m.medicine_id
        JOIN purchase_orders po ON gr.po_id = po.po_id
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN quality_inspections qi ON gri.receipt_item_id = qi.receipt_item_id
        WHERE qi.inspection_id IS NULL
    ";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (po.po_number LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ? OR gri.batch_number LIKE ? OR s.company_name LIKE ?)";
        $like = "%$search%";
        $params = [$like, $like, $like, $like, $like];
    }

    $sql .= " ORDER BY gr.receipt_date DESC, gri.receipt_item_id DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pending = $stmt->fetchAll();

    // ========== HISTORY ==========
    $history = $pdo->query("
        SELECT 
            qi.inspection_id, qi.inspection_date, qi.result, qi.lab_test_reference, qi.remarks,
            gri.batch_number, gri.quantity_received,
            m.generic_name, m.brand_name,
            po.po_number,
            u.full_name AS inspected_by_name
        FROM quality_inspections qi
        JOIN goods_receipt_items gri ON qi.receipt_item_id = gri.receipt_item_id
        JOIN purchase_order_items poi ON gri.po_item_id = poi.po_item_id
        JOIN medicines m ON poi.medicine_id = m.medicine_id
        JOIN goods_receipts gr ON gri.receipt_id = gr.receipt_id
        JOIN purchase_orders po ON gr.po_id = po.po_id
        JOIN users u ON qi.inspected_by = u.user_id
        ORDER BY qi.inspection_id DESC
        LIMIT 30
    ")->fetchAll();

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

$pageTitle = 'Quality Assurance';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Quality Assurance</h1>
        <p class="text-gray-500 text-sm mt-1">Inspect received goods only — Approve / Reject / Quarantine</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-xs text-gray-500 uppercase">Pending Inspection</p>
            <p class="text-3xl font-bold text-orange-600 mt-1"><?= count($pending) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-xs text-gray-500 uppercase">Total Inspected</p>
            <p class="text-3xl font-bold text-gray-800 mt-1"><?= count($history) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-xs text-gray-500 uppercase">Approved (recent)</p>
            <p class="text-3xl font-bold text-green-600 mt-1">
                <?= count(array_filter($history, fn($h) => ($h['result'] ?? '') === 'Approved')) ?>
            </p>
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border p-4 mb-6">
        <div class="flex gap-3">
            <input type="text" name="search" value="<?= e($search) ?>" 
                   placeholder="Search PO number, medicine, batch, supplier..."
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="quality.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Reset</a>
        </div>
    </form>

    <!-- PENDING LIST -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
        <div class="px-6 py-4 border-b bg-orange-50">
            <h2 class="font-semibold text-gray-800">
                <i class="fas fa-clock text-orange-600 mr-2"></i> Pending Inspection
                <span class="text-sm font-normal text-gray-500">(only received items)</span>
            </h2>
        </div>

        <?php if (empty($pending)): ?>
            <div class="px-6 py-12 text-center text-gray-400">
                <i class="fas fa-check-circle text-4xl text-green-300 mb-3"></i>
                <p>No pending items to inspect.</p>
                <p class="text-sm mt-1">Receive goods first via <a href="goods_receipt.php" class="text-teal-600 underline">Goods Receipt</a></p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">PO Number</th>
                            <th class="text-left px-4 py-3 font-medium">Medicine</th>
                            <th class="text-left px-4 py-3 font-medium">Batch</th>
                            <th class="text-right px-4 py-3 font-medium">Qty</th>
                            <th class="text-left px-4 py-3 font-medium">Expiry</th>
                            <th class="text-left px-4 py-3 font-medium">Supplier</th>
                            <th class="text-left px-4 py-3 font-medium">Received</th>
                            <th class="text-center px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pending as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-teal-700"><?= e($item['po_number']) ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium"><?= e($item['generic_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= e($item['brand_name'] ?? '') ?> <?= e($item['strength'] ?? '') ?></div>
                            </td>
                            <td class="px-4 py-3 font-mono"><?= e($item['batch_number']) ?></td>
                            <td class="px-4 py-3 text-right font-medium"><?= formatNumber($item['quantity_received']) ?></td>
                            <td class="px-4 py-3"><?= $item['expiry_date'] ? formatDate($item['expiry_date']) : '-' ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= e($item['supplier_name']) ?></td>
                            <td class="px-4 py-3 text-xs text-gray-500"><?= formatDate($item['receipt_date']) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 justify-center">
                                    <!-- Approve -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="inspect">
                                        <input type="hidden" name="receipt_item_id" value="<?= $item['receipt_item_id'] ?>">
                                        <input type="hidden" name="result" value="Approved">
                                        <button type="submit" class="bg-green-100 hover:bg-green-200 text-green-800 text-xs px-2.5 py-1 rounded-full font-medium"
                                                onclick="return confirm('Approve this batch?')">
                                            Approve
                                        </button>
                                    </form>
                                    <!-- Reject -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="inspect">
                                        <input type="hidden" name="receipt_item_id" value="<?= $item['receipt_item_id'] ?>">
                                        <input type="hidden" name="result" value="Rejected">
                                        <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-800 text-xs px-2.5 py-1 rounded-full font-medium"
                                                onclick="return confirm('Reject this batch?')">
                                            Reject
                                        </button>
                                    </form>
                                    <!-- Quarantine -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="inspect">
                                        <input type="hidden" name="receipt_item_id" value="<?= $item['receipt_item_id'] ?>">
                                        <input type="hidden" name="result" value="Quarantined">
                                        <button type="submit" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 text-xs px-2.5 py-1 rounded-full font-medium"
                                                onclick="return confirm('Quarantine this batch?')">
                                            Quarantine
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- HISTORY -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h2 class="font-semibold text-gray-800">
                <i class="fas fa-clipboard-check text-teal-600 mr-2"></i> Inspection History
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Date</th>
                        <th class="text-left px-4 py-3 font-medium">PO</th>
                        <th class="text-left px-4 py-3 font-medium">Medicine</th>
                        <th class="text-left px-4 py-3 font-medium">Batch</th>
                        <th class="text-right px-4 py-3 font-medium">Qty</th>
                        <th class="text-left px-4 py-3 font-medium">Result</th>
                        <th class="text-left px-4 py-3 font-medium">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($history)): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No inspections yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><?= formatDate($h['inspection_date']) ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= e($h['po_number']) ?></td>
                            <td class="px-4 py-3 font-medium"><?= e($h['generic_name']) ?></td>
                            <td class="px-4 py-3 font-mono"><?= e($h['batch_number']) ?></td>
                            <td class="px-4 py-3 text-right"><?= formatNumber($h['quantity_received']) ?></td>
                            <td class="px-4 py-3">
                                <?php
                                $r = $h['result'] ?? '';
                                $badge = $r === 'Approved' ? 'bg-green-100 text-green-800' : ($r === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                                ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badge ?>"><?= e($r) ?></span>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= e($h['inspected_by_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>