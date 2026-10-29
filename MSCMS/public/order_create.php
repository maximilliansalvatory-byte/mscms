<?php
/**
 * MSCMS - Create New Hospital Order
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$hospitals = [];
$warehouses = [];
$medicines = [];
$message = '';
$error = '';

try {
    $pdo = db();
    $hospitals = $pdo->query("SELECT hospital_id, hospital_name FROM hospitals WHERE is_active = 1 ORDER BY hospital_name")->fetchAll();
    $warehouses = $pdo->query("SELECT warehouse_id, warehouse_name FROM warehouses WHERE is_active = 1 ORDER BY warehouse_name")->fetchAll();
    $medicines = $pdo->query("SELECT medicine_id, generic_name, brand_name, strength FROM medicines WHERE is_active = 1 ORDER BY generic_name")->fetchAll();
} catch (Exception $e) {
    $error = "Could not load form data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospital_id  = (int)($_POST['hospital_id'] ?? 0);
    $warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
    $order_date   = $_POST['order_date'] ?? date('Y-m-d');
    $medicine_ids = $_POST['medicine_id'] ?? [];
    $quantities   = $_POST['quantity'] ?? [];

    if ($hospital_id <= 0 || $warehouse_id <= 0) {
        $error = "Please select both Hospital and Warehouse.";
    } elseif (empty($medicine_ids)) {
        $error = "Please add at least one medicine.";
    } else {
        try {
            $pdo->beginTransaction();

            // Generate Order number
            $order_number = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (order_number, hospital_id, warehouse_id, status, ordered_by, order_date)
                VALUES (?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->execute([$order_number, $hospital_id, $warehouse_id, $user['user_id'], $order_date]);
            $order_id = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, medicine_id, quantity_ordered)
                VALUES (?, ?, ?)
            ");

            for ($i = 0; $i < count($medicine_ids); $i++) {
                $med_id = (int)$medicine_ids[$i];
                $qty = (int)$quantities[$i];

                if ($med_id > 0 && $qty > 0) {
                    $itemStmt->execute([$order_id, $med_id, $qty]);
                }
            }

            $pdo->commit();
            $message = "Hospital Order <strong>$order_number</strong> created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create Order. " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Hospital Order - MSCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-teal-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-6">
                    <a href="dashboard.php" class="flex items-center gap-2 font-bold text-xl">
                        <i class="fas fa-pills"></i> MSCMS
                    </a>
                    <div class="hidden md:flex gap-4 text-sm">
                        <a href="dashboard.php" class="text-teal-200 hover:text-white">Dashboard</a>
                        <a href="distribution.php" class="text-white font-medium border-b-2 border-white">Distribution</a>
                        <a href="reports.php" class="text-teal-200 hover:text-white">Reports</a>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-teal-100"><?= e($user['full_name']) ?></span>
                    <a href="logout.php" class="bg-teal-700 hover:bg-teal-600 px-3 py-1.5 rounded-lg text-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">

        <div class="mb-6">
            <a href="distribution.php" class="text-teal-700 text-sm hover:underline">
                <i class="fas fa-arrow-left mr-1"></i> Back to Distribution
            </a>
            <h1 class="text-2xl font-bold text-gray-800 mt-2">Create Hospital Order</h1>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                <?= $message ?>
                <div class="mt-2">
                    <a href="distribution.php" class="text-green-700 underline text-sm">View all Orders</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-xl shadow-sm border p-6 space-y-6">

            <!-- Header Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hospital *</label>
                    <select name="hospital_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">Select Hospital</option>
                        <option value="1">Hospital A</option>
                        <option value="2">Hospital B</option>
                        <option value="3">Hospital C</option>
                        <option value="4">Hospital D</option>
                        <?php foreach ($hospitals as $h): ?>
                            <option value="<?= $h['hospital_id'] ?>"><?= e($h['hospital_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplying Warehouse *</label>
                    <select name="warehouse_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">Select Warehouse</option>
                        <option value="">Warehouse A</option>
                        <option value="">Warehouse B</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['warehouse_id'] ?>"><?= e($w['warehouse_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Date *</label>
                    <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
            </div>

            <!-- Items -->
            <div>
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-lg font-semibold text-gray-800">Order Items</h2>
                    <button type="button" onclick="addRow()" class="text-sm bg-teal-50 text-teal-700 px-3 py-1.5 rounded-lg hover:bg-teal-100">
                        <i class="fas fa-plus mr-1"></i> Add Item
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="itemsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2 font-medium">Medicine</th>
                                <th class="text-left px-3 py-2 font-medium w-32">Quantity</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr class="item-row">
                                <td class="px-3 py-2">
                                    <select name="medicine_id[]" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                        <option value="">Select medicine</option>

                                        <?php foreach ($medicines as $m): ?>
                                            <option value="<?= $m['medicine_id'] ?>">
                                                <?= e($m['generic_name']) ?> <?= $m['brand_name'] ? '(' . e($m['brand_name']) . ')' : '' ?> - <?= e($m['strength']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" name="quantity[]" min="1" value="1" required
                                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                </td>
                                <td class="px-3 py-2">
                                    <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="distribution.php" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-6 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-save mr-1"></i> Create Order
                </button>
            </div>
        </form>
    </div>

    <script>
        function addRow() {
            const tbody = document.getElementById('itemsBody');
            const firstRow = tbody.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);
            
            newRow.querySelectorAll('select, input').forEach(el => {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = 1;
            });
            
            tbody.appendChild(newRow);
        }

        function removeRow(btn) {
            const tbody = document.getElementById('itemsBody');
            if (tbody.querySelectorAll('.item-row').length > 1) {
                btn.closest('tr').remove();
            } else {
                alert('At least one item is required.');
            }
        }
    </script>
</body>
</html>