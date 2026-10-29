<?php
/**
 * MSCMS - Manage Medicines (Fixed - works even if categories/manufacturers empty)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

if (!hasRole(['Super Administrator', 'ICT Administrator'])) {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}

$message = '';
$error = '';
$medicines = [];
$categories = [];
$manufacturers = [];

try {
    $pdo = db();

    // Auto-create default category if none exist
    $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount === 0) {
        $pdo->exec("INSERT INTO categories (category_name) VALUES ('General'), ('Antibiotics'), ('Pain Relief'), ('Vitamins')");
    }

    // Auto-create default manufacturer if none exist
    $manCount = (int)$pdo->query("SELECT COUNT(*) FROM manufacturers")->fetchColumn();
    if ($manCount === 0) {
        $pdo->exec("INSERT INTO manufacturers (manufacturer_name, country) VALUES ('Generic Pharma', 'Tanzania'), ('Shelys Pharmaceuticals', 'Tanzania')");
    }

    $categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
    $manufacturers = $pdo->query("SELECT manufacturer_id, manufacturer_name FROM manufacturers ORDER BY manufacturer_name")->fetchAll();

    // ADD Medicine
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
        $generic = trim($_POST['generic_name'] ?? '');
        $brand = trim($_POST['brand_name'] ?? '');
        $strength = trim($_POST['strength'] ?? '');
        $form = trim($_POST['dosage_form'] ?? 'Tablet');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $manufacturer_id = (int)($_POST['manufacturer_id'] ?? 0);
        $reorder = (int)($_POST['reorder_level'] ?? 100);
        $storage = trim($_POST['storage_temperature'] ?? 'Room temperature');

        if ($generic === '') {
            $error = "Generic name is required.";
        } else {
            // Use first category/manufacturer if not selected
            if ($category_id <= 0 && !empty($categories)) {
                $category_id = $categories[0]['category_id'];
            }
            if ($manufacturer_id <= 0 && !empty($manufacturers)) {
                $manufacturer_id = $manufacturers[0]['manufacturer_id'];
            }

            $stmt = $pdo->prepare("
                INSERT INTO medicines 
                (generic_name, brand_name, strength, dosage_form, category_id, manufacturer_id, 
                 unit_of_measure, storage_temperature, reorder_level, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 'Unit', ?, ?, 1)
            ");
            $stmt->execute([
                $generic, 
                $brand ?: null, 
                $strength ?: null, 
                $form, 
                $category_id ?: null, 
                $manufacturer_id ?: null,
                $storage,
                $reorder
            ]);
            $message = "Medicine <strong>" . htmlspecialchars($generic) . "</strong> added successfully.";
        }
    }

    // TOGGLE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
        $id = (int)($_POST['medicine_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE medicines SET is_active = IF(is_active=1,0,1) WHERE medicine_id = ?")->execute([$id]);
            $message = "Medicine status updated.";
        }
    }

    $medicines = $pdo->query("
        SELECT m.*, c.category_name, mf.manufacturer_name
        FROM medicines m
        LEFT JOIN categories c ON m.category_id = c.category_id
        LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.manufacturer_id
        ORDER BY m.generic_name
        LIMIT 100
    ")->fetchAll();

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$pageTitle = 'Manage Medicines';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manage Medicines</h1>
            <p class="text-gray-500 text-sm">Add and manage medicines</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-plus mr-1"></i> Add Medicine
        </button>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Generic Name</th>
                        <th class="text-left px-4 py-3 font-medium">Brand</th>
                        <th class="text-left px-4 py-3 font-medium">Strength / Form</th>
                        <th class="text-left px-4 py-3 font-medium">Category</th>
                        <th class="text-left px-4 py-3 font-medium">Manufacturer</th>
                        <th class="text-center px-4 py-3 font-medium">Status</th>
                        <th class="text-center px-4 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($medicines)): ?>
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No medicines yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($medicines as $m): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium"><?= e($m['generic_name']) ?></td>
                            <td class="px-4 py-3"><?= e($m['brand_name'] ?? '-') ?></td>
                            <td class="px-4 py-3"><?= e($m['strength'] ?? '') ?> · <?= e($m['dosage_form'] ?? '') ?></td>
                            <td class="px-4 py-3"><?= e($m['category_name'] ?? '-') ?></td>
                            <td class="px-4 py-3"><?= e($m['manufacturer_name'] ?? '-') ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($m['is_active']): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Active</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="medicine_id" value="<?= $m['medicine_id'] ?>">
                                    <button type="submit" class="text-orange-600 hover:text-orange-800 text-xs">
                                        <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b px-6 py-4 sticky top-0 bg-white">
            <h2 class="text-lg font-semibold">Add New Medicine</h2>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Generic Name *</label>
                <input type="text" name="generic_name" required placeholder="e.g. Paracetamol"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
                <input type="text" name="brand_name" placeholder="e.g. Panadol"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Strength</label>
                    <input type="text" name="strength" placeholder="e.g. 500mg"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dosage Form</label>
                    <select name="dosage_form" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Syrup">Syrup</option>
                        <option value="Injection">Injection</option>
                        <option value="Cream">Cream</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    <option value="">Select Category (optional)</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Manufacturer</label>
                <select name="manufacturer_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    <option value="">Select Manufacturer (optional)</option>
                    <?php foreach ($manufacturers as $mf): ?>
                        <option value="<?= $mf['manufacturer_id'] ?>"><?= e($mf['manufacturer_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reorder Level</label>
                    <input type="number" name="reorder_level" value="100" min="0"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Storage</label>
                    <input type="text" name="storage_temperature" value="Room temperature"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">Save Medicine</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>