<?php
/**
 * MSCMS - Manage Hospitals (Admin)
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
$hospitals = [];
$regions = [];

try {
    $pdo = db();
    $regions = $pdo->query("SELECT region_id, region_name FROM regions ORDER BY region_name")->fetchAll();

    // ADD Hospital
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
        $name = trim($_POST['hospital_name'] ?? '');
        $region_id = (int)($_POST['region_id'] ?? 0);
        $level = trim($_POST['hospital_level'] ?? 'District');
        $contact = trim($_POST['contact_person'] ?? '');

        if ($name === '') {
            $error = "Hospital name is required.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO hospitals (hospital_name, region_id, hospital_level, contact_person, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$name, $region_id ?: null, $level, $contact]);
            $message = "Hospital <strong>" . e($name) . "</strong> added successfully.";
        }
    }

    // TOGGLE status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE hospitals SET is_active = IF(is_active=1,0,1) WHERE hospital_id = ?")->execute([$id]);
            $message = "Hospital status updated.";
        }
    }

    $hospitals = $pdo->query("
        SELECT h.*, r.region_name 
        FROM hospitals h 
        LEFT JOIN regions r ON h.region_id = r.region_id 
        ORDER BY h.hospital_name
    ")->fetchAll();

} catch (Exception $e) {
    $error = "Database error.";
}

$pageTitle = 'Hospitals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Hospitals</h1>
            <p class="text-gray-500 text-sm">Add and manage hospitals</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-plus mr-1"></i> Add Hospital
        </button>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3 font-medium">Hospital Name</th>
                    <th class="text-left px-4 py-3 font-medium">Region</th>
                    <th class="text-left px-4 py-3 font-medium">Level</th>
                    <th class="text-left px-4 py-3 font-medium">Contact</th>
                    <th class="text-center px-4 py-3 font-medium">Status</th>
                    <th class="text-center px-4 py-3 font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($hospitals)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No hospitals yet</td></tr>
                <?php else: ?>
                    <?php foreach ($hospitals as $h): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= e($h['hospital_name']) ?></td>
                        <td class="px-4 py-3"><?= e($h['region_name'] ?? '-') ?></td>
                        <td class="px-4 py-3"><?= e($h['hospital_level'] ?? '-') ?></td>
                        <td class="px-4 py-3"><?= e($h['contact_person'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($h['is_active']): ?>
                                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Active</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                                <button type="submit" class="text-orange-600 hover:text-orange-800 text-xs">
                                    <?= $h['is_active'] ? 'Deactivate' : 'Activate' ?>
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

<!-- ADD MODAL -->
<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h2 class="text-lg font-semibold">Add New Hospital</h2>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hospital Name *</label>
                <input type="text" name="hospital_name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                <select name="region_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    <option value="">Select Region</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= $r['region_id'] ?>"><?= e($r['region_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
                <select name="hospital_level" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                    <option value="National">National</option>
                    <option value="Regional">Regional</option>
                    <option value="District" selected>District</option>
                    <option value="Health Centre">Health Centre</option>
                    <option value="Dispensary">Dispensary</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                <input type="text" name="contact_person" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg text-sm font-medium">Save Hospital</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>