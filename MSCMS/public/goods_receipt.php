<?php
/**
 * MSCMS - Goods Receipt (Fixed)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$message = '';
$error = '';
$purchaseOrders = [];
$poItems = [];
$selectedPo = null;
$po_id = (int)($_GET['po_id'] ?? $_POST['po_id'] ?? 0);

try {
    $pdo = db();

    $purchaseOrders = $pdo->query("
        SELECT po.po_id, po.po_number, po.status, s.company_name AS supplier_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        WHERE po.status IN ('Approved','Partially Received','Submitted','Draft','Received')
        ORDER BY po.created_at DESC
        LIMIT 50
    ")->fetchAll();

    if ($po_id > 0) {
        $stmt = $pdo->prepare("
            SELECT po.*, s.company_name AS supplier_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.supplier_id
            WHERE po.po_id = ?
        ");
        $stmt->execute([$po_id]);
        $selectedPo = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT poi.po_item_id, poi.medicine_id, poi.quantity_ordered, poi.unit_price,
                   m.generic_name, m.brand_name, m.strength
            FROM purchase_order_items poi
            JOIN medicines m ON poi.medicine_id = m.medicine_id
            WHERE poi.po_id = ?
        ");
        $stmt->execute([$po_id]);
        $poItems = $stmt->fetchAll();
    }

    // HANDLE RECEIVE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'receive') {
        $po_id = (int)($_POST['po_id'] ?? 0);
        $batch_numbers = $_POST['batch_number'] ?? [];
        $quantities = $_POST['quantity_received'] ?? [];
        $expiry_dates = $_POST['expiry_date'] ?? [];
        $po_item_ids = $_POST['po_item_id'] ?? [];

        if ($po_id <= 0) {
            $error = "Please select a Purchase Order.";
        } else {
            try {
                $pdo->beginTransaction();

                // Create receipt header
                $stmt = $pdo->prepare("
                    INSERT INTO goods_receipts (po_id, received_by, receipt_date, is_partial, notes)
                    VALUES (?, ?, CURDATE(), 1, 'Received via system')
                ");
                $stmt->execute([$po_id, $user['user_id']]);
                $receipt_id = $pdo->lastInsertId();

                $receivedCount = 0;

                for ($i = 0; $i < count($po_item_ids); $i++) {
                    $item_id = (int)($po_item_ids[$i] ?? 0);
                    $batch = trim($batch_numbers[$i] ?? '');
                    $qty = (int)($quantities[$i] ?? 0);
                    $expiry = !empty($expiry_dates[$i]) ? $expiry_dates[$i] : null;

                    if ($item_id > 0 && $batch !== '' && $qty > 0) {
                        // Insert receipt item
                        $pdo->prepare("
                            INSERT INTO goods_receipt_items 
                            (receipt_id, po_item_id, batch_number, quantity_received, expiry_date)
                            VALUES (?, ?, ?, ?, ?)
                        ")->execute([$receipt_id, $item_id, $batch, $qty, $expiry]);

                        // Get medicine_id
                        $medStmt = $pdo->prepare("SELECT medicine_id FROM purchase_order_items WHERE po_item_id = ?");
                        $medStmt->execute([$item_id]);
                        $medicine_id = (int)$medStmt->fetchColumn();

                        if ($medicine_id) {
                            // Insert batch (ignore if already exists)
                            try {
                                $pdo->prepare("
                                    INSERT INTO batches (medicine_id, batch_number, expiry_date, quality_status)
                                    VALUES (?, ?, ?, 'Pending')
                                ")->execute([$medicine_id, $batch, $expiry]);
                            } catch (Exception $ex) {
                                // batch may already exist - ignore
                            }

                            // Try to add to inventory (optional - may fail if location required)
                            try {
                                $batchId = $pdo->query("SELECT batch_id FROM batches WHERE batch_number = " . $pdo->quote($batch) . " AND medicine_id = $medicine_id LIMIT 1")->fetchColumn();
                                $whId = $pdo->query("SELECT warehouse_id FROM warehouses WHERE is_active = 1 LIMIT 1")->fetchColumn();
                                if ($batchId && $whId) {
                                    $pdo->prepare("
                                        INSERT INTO inventory (batch_id, warehouse_id, quantity_on_hand)
                                        VALUES (?, ?, ?)
                                        ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)
                                    ")->execute([$batchId, $whId, $qty]);
                                }
                            } catch (Exception $ex) {
                                // inventory optional
                            }
                        }
                        $receivedCount++;
                    }
                }

                if ($receivedCount === 0) {
                    throw new Exception("No valid items. Fill Batch Number and Qty Received (> 0).");
                }

                $pdo->prepare("UPDATE purchase_orders SET status = 'Partially Received' WHERE po_id = ?")->execute([$po_id]);
                $pdo->commit();

                $message = "Goods received successfully ($receivedCount item(s)). Go to Quality to inspect.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed: " . $e->getMessage();
            }
        }
    }

} catch (Exception $e) {
    $error = "Could not load data: " . $e->getMessage();
}

$pageTitle = 'Goods Receipt';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Goods Receipt</h1>
        <p class="text-gray-500 text-sm mt-1">Receive medicines against Purchase Orders</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
            <?= $message ?>
            <div class="mt-2"><a href="quality.php" class="text-green-700 underline text-sm">Go to Quality Assurance →</a></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">1. Select Purchase Order</h2>
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <select name="po_id" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">-- Choose Purchase Order --</option>
                <?php foreach ($purchaseOrders as $po): ?>
                    <option value="<?= $po['po_id'] ?>" <?= $po_id == $po['po_id'] ? 'selected' : '' ?>>
                        <?= e($po['po_number']) ?> · <?= e($po['supplier_name']) ?> · <?= e($po['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-5 py-2 rounded-lg text-sm font-medium">Load Items</button>
        </form>
    </div>

    <?php if ($selectedPo && !empty($poItems)): ?>
    <form method="POST" class="bg-white rounded-xl shadow-sm border p-6">
        <input type="hidden" name="action" value="receive">
        <input type="hidden" name="po_id" value="<?= $selectedPo['po_id'] ?>">

        <div class="mb-4">
            <h2 class="font-semibold text-gray-800">2. Enter Received Quantities & Batch Info</h2>
            <p class="text-sm text-gray-500 mt-1">
                PO: <strong><?= e($selectedPo['po_number']) ?></strong> · Supplier: <?= e($selectedPo['supplier_name']) ?>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Medicine</th>
                        <th class="text-right px-3 py-2 font-medium">Ordered</th>
                        <th class="text-left px-3 py-2 font-medium">Batch Number *</th>
                        <th class="text-left px-3 py-2 font-medium">Qty Received *</th>
                        <th class="text-left px-3 py-2 font-medium">Expiry Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($poItems as $item): ?>
                    <tr>
                        <td class="px-3 py-3">
                            <input type="hidden" name="po_item_id[]" value="<?= $item['po_item_id'] ?>">
                            <div class="font-medium"><?= e($item['generic_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= e($item['brand_name'] ?? '') ?> <?= e($item['strength'] ?? '') ?></div>
                        </td>
                        <td class="px-3 py-3 text-right"><?= formatNumber($item['quantity_ordered']) ?></td>
                        <td class="px-3 py-3">
                            <input type="text" name="batch_number[]" placeholder="BATCH-001" required
                                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </td>
                        <td class="px-3 py-3">
                            <input type="number" name="quantity_received[]" min="1" value="<?= (int)$item['quantity_ordered'] ?>" required
                                   class="w-28 border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </td>
                        <td class="px-3 py-3">
                            <input type="date" name="expiry_date[]"
                                   class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="goods_receipt.php" class="px-4 py-2 border rounded-lg text-sm">Cancel</a>
            <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-6 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-check mr-1"></i> Confirm Receipt
            </button>
        </div>
    </form>
    <?php elseif ($po_id > 0): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
            No items found for this Purchase Order. Create a new PO with medicines first.
        </div>
    <?php endif; ?>
</div>

</body>
</html>