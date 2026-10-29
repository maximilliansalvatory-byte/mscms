<?php
/**
 * MSCMS - Create Purchase Order (Fixed)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$suppliers = [];
$medicines = [];
$message = '';
$error = '';

try {
    $pdo = db();
    $suppliers = $pdo->query("SELECT supplier_id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name")->fetchAll();
    $medicines = $pdo->query("SELECT medicine_id, generic_name, brand_name, strength FROM medicines WHERE is_active = 1 ORDER BY generic_name")->fetchAll();
} catch (Exception $e) {
    $error = "Could not load form data.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id   = (int)($_POST['supplier_id'] ?? 0);
    $order_date    = $_POST['order_date'] ?? date('Y-m-d');
    $expected_date = $_POST['expected_delivery_date'] ?? null;
    $medicine_ids  = $_POST['medicine_id'] ?? [];
    $quantities    = $_POST['quantity'] ?? [];
    $unit_prices   = $_POST['unit_price'] ?? [];

    // Count valid items
    $validItems = 0;
    for ($i = 0; $i < count($medicine_ids); $i++) {
        if ((int)($medicine_ids[$i] ?? 0) > 0 && (int)($quantities[$i] ?? 0) > 0) {
            $validItems++;
        }
    }

    if ($supplier_id <= 0) {
        $error = "Please select a supplier.";
    } elseif ($validItems === 0) {
        $error = "Please select at least one medicine and set quantity greater than 0.";
    } else {
        try {
            $pdo->beginTransaction();

            $po_number = 'PO-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders 
                (po_number, supplier_id, order_date, expected_delivery_date, status, total_amount, created_by, approved_by)
                VALUES (?, ?, ?, ?, 'Draft', 0, ?, ?)
            ");
            $stmt->execute([
                $po_number, 
                $supplier_id, 
                $order_date, 
                $expected_date ?: null, 
                $user['user_id'], 
                $user['user_id']
            ]);
            $po_id = $pdo->lastInsertId();

            $total = 0;
            $itemStmt = $pdo->prepare("
                INSERT INTO purchase_order_items (po_id, medicine_id, quantity_ordered, unit_price)
                VALUES (?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($medicine_ids); $i++) {
                $med_id = (int)($medicine_ids[$i] ?? 0);
                $qty    = (int)($quantities[$i] ?? 0);
                $price  = (float)($unit_prices[$i] ?? 0);

                if ($med_id > 0 && $qty > 0) {
                    $itemStmt->execute([$po_id, $med_id, $qty, $price]);
                    $total += $qty * $price;
                }
            }

            $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE po_id = ?")->execute([$total, $po_id]);
            $pdo->commit();

            $message = "Purchase Order <strong>$po_number</strong> created successfully!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Failed to create Purchase Order. Please try again.";
        }
    }
}

$pageTitle = 'Create Purchase Order';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 py-8">

    <div class="mb-6">
        <a href="procurement.php" class="text-teal-700 text-sm hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> Back to Procurement
        </a>
        <h1 class="text-2xl font-bold text-gray-800 mt-2">Create Purchase Order</h1>
        <p class="text-gray-500 text-sm">You can add as many medicines as you want</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
            <?= $message ?>
            <div class="mt-2">
                <a href="procurement.php" class="text-green-700 underline text-sm">View all Purchase Orders</a> · 
                <a href="goods_receipt.php" class="text-green-700 underline text-sm">Go to Goods Receipt</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border p-6 space-y-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                <select name="supplier_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    <option value="">Select Supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['supplier_id'] ?>"><?= e($s['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Order Date *</label>
                <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery</label>
                <input type="date" name="expected_delivery_date"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
        </div>

        <div>
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-gray-800">Order Items</h2>
                <button type="button" onclick="addRow()" 
                        class="bg-teal-50 hover:bg-teal-100 text-teal-700 px-3 py-1.5 rounded-lg text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> Add Another Medicine
                </button>
            </div>

            <div class="overflow-x-auto border rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-3 py-2 font-medium">Medicine *</th>
                            <th class="text-left px-3 py-2 font-medium w-28">Quantity *</th>
                            <th class="text-left px-3 py-2 font-medium w-32">Unit Price</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row border-t">
                            <td class="px-3 py-2">
                                <select name="medicine_id[]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                    <option value="">Select medicine</option>
                                    <?php foreach ($medicines as $m): ?>
                                        <option value="<?= (int)$m['medicine_id'] ?>">
                                            <?= e($m['generic_name']) ?>
                                            <?= $m['brand_name'] ? '(' . e($m['brand_name']) . ')' : '' ?>
                                            <?= $m['strength'] ? '- ' . e($m['strength']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" name="quantity[]" min="1" value="1"
                                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" name="unit_price[]" min="0" step="0.01" value="0"
                                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-2">Select medicine + quantity, then click Create. You can add more rows.</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
            <a href="procurement.php" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-6 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-save mr-1"></i> Create Purchase Order
            </button>
        </div>
    </form>
</div>

<script>
function addRow() {
    const tbody = document.getElementById('itemsBody');
    const firstRow = tbody.querySelector('.item-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
    newRow.querySelectorAll('input[name="quantity[]"]').forEach(el => el.value = 1);
    newRow.querySelectorAll('input[name="unit_price[]"]').forEach(el => el.value = 0);
    tbody.appendChild(newRow);
}
function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.querySelectorAll('.item-row').length > 1) {
        btn.closest('tr').remove();
    } else {
        alert('At least one row is required.');
    }
}
</script>

</body>
</html>