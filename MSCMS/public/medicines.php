<?php
/**
 * MSCMS - Medicines Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$user = currentUser();

$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';

$medicines = [];
$categories = [];
$error = null;

try {
    $pdo = db();

    $categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();

    $sql = "
        SELECT 
            m.medicine_id,
            m.generic_name,
            m.brand_name,
            m.strength,
            m.dosage_form,
            m.unit_of_measure,
            m.storage_temperature,
            m.reorder_level,
            m.is_controlled_drug,
            m.is_active,
            c.category_name,
            mf.manufacturer_name
        FROM medicines m
        JOIN categories c ON m.category_id = c.category_id
        JOIN manufacturers mf ON m.manufacturer_id = mf.manufacturer_id
        WHERE 1=1
    ";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (m.generic_name LIKE ? OR m.brand_name LIKE ? OR m.barcode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($category !== '') {
        $sql .= " AND m.category_id = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY m.generic_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Could not load medicines data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines - MSCMS</title>
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
                        <a href="medicines.php" class="text-white font-medium border-b-2 border-white">Medicines</a>
                        <a href="procurement.php" class="text-teal-200 hover:text-white">Procurement</a>
                        <a href="distribution.php" class="text-teal-200 hover:text-white">Distribution</a>
                        <a href="reports.php" class="text-teal-200 hover:text-white">Reports</a>
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
                <h1 class="text-2xl font-bold text-gray-800">Medicines</h1>
                <p class="text-gray-500 text-sm mt-1">Master list of all medicines in the system</p>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                    <input type="text" name="search" value="<?= e($search) ?>" 
                           placeholder="Generic name, brand or barcode..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $category == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= e($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="medicines.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">Reset</a>
                </div>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Medicines Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium">Generic Name</th>
                            <th class="text-left px-4 py-3 font-medium">Brand</th>
                            <th class="text-left px-4 py-3 font-medium">Strength / Form</th>
                            <th class="text-left px-4 py-3 font-medium">Category</th>
                            <th class="text-left px-4 py-3 font-medium">Manufacturer</th>
                            <th class="text-left px-4 py-3 font-medium">Storage</th>
                            <th class="text-center px-4 py-3 font-medium">Controlled</th>
                            <th class="text-center px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($medicines)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                                    No medicines found. Please seed the medicines table.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medicines as $med): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800"><?= e($med['generic_name']) ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= e($med['brand_name'] ?? '-') ?></td>
                                <td class="px-4 py-3">
                                    <?= e($med['strength']) ?> · <?= e($med['dosage_form']) ?>
                                </td>
                                <td class="px-4 py-3"><?= e($med['category_name']) ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= e($med['manufacturer_name']) ?></td>
                                <td class="px-4 py-3 text-xs"><?= e($med['storage_temperature']) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($med['is_controlled_drug']): ?>
                                        <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">Yes</span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($med['is_active']): ?>
                                        <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-sm text-gray-500 mt-4">
            Showing <?= count($medicines) ?> medicine(s)
        </p>
    </div>
</body>
</html>