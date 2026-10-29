<?php
/**
 * MSCMS - Common Header / Navigation
 * Include this file on every page after requireLogin()
 */
if (!isset($user)) {
    $user = currentUser();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'MSCMS' ?> - Medical Supply Chain</title>
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
                <div class="hidden md:flex gap-1 text-sm">
                    <?php
                    $menus = [
    'dashboard.php'        => ['Dashboard', 'fa-home'],
    'inventory.php'        => ['Inventory', 'fa-boxes'],
    'medicines.php'        => ['Medicines', 'fa-capsules'],
    'procurement.php'      => ['Procurement', 'fa-shopping-cart'],
    'distribution.php'     => ['Distribution', 'fa-truck'],
    'goods_receipt.php'    => ['Goods Receipt', 'fa-dolly'],
    'quality.php'          => ['Quality', 'fa-clipboard-check'],
    'users.php'            => ['Users', 'fa-users'],
    'reports.php'          => ['Reports', 'fa-chart-bar'],
    'quick_add'            => ['Quick Add', 'fa-plus-circle', [
        'medicines_manage.php' => ['Add Medicines', 'fa-pills'],
        'hospitals.php'        => ['Add Hospitals', 'fa-hospital'],
        'suppliers.php'        => ['Add Suppliers', 'fa-truck'],
        'warehouses.php'       => ['Add Warehouses', 'fa-warehouse'],
    ]],
];

                    foreach ($menus as $file => $info):
                        $active = ($currentPage === $file);
                        $submenu = $info[2] ?? null;
                    ?>
                        <?php if ($submenu): ?>
                            <div class="relative">
                                <button type="button" onclick="toggleQuickAddMenu()" 
                                        class="px-3 py-2 rounded-lg transition text-teal-100 hover:bg-teal-700 hover:text-white flex items-center gap-1">
                                    <i class="fas <?= $info[1] ?> mr-1"></i> <?= $info[0] ?>
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </button>
                                <div id="quickAddMenu" 
                                     class="hidden absolute left-0 mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden z-50">
                                    <?php foreach ($submenu as $subFile => $subInfo): ?>
                                        <a href="<?= $subFile ?>" 
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-50 hover:text-teal-700 border-t border-gray-100 first:border-t-0">
                                            <i class="fas <?= $subInfo[1] ?> text-teal-600 w-5"></i> <?= $subInfo[0] ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="<?= $file ?>" 
                               class="px-3 py-2 rounded-lg transition <?= $active ? 'bg-teal-900 text-white font-medium' : 'text-teal-100 hover:bg-teal-700 hover:text-white' ?>">
                                <i class="fas <?= $info[1] ?> mr-1"></i> <?= $info[0] ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-teal-100 hidden sm:inline">
                    <i class="fas fa-user-circle mr-1"></i>
                    <?= e($user['full_name']) ?>
                    <span class="text-teal-300 text-xs">(<?= e($user['role_name']) ?>)</span>
                </span>
                <a href="logout.php" class="bg-teal-700 hover:bg-teal-600 px-3 py-1.5 rounded-lg text-sm transition">
                    <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleQuickAddMenu() {
    document.getElementById('quickAddMenu').classList.toggle('hidden');
}
document.addEventListener('click', function (e) {
    const menu = document.getElementById('quickAddMenu');
    if (!menu) return;
    const button = e.target.closest('button[onclick="toggleQuickAddMenu()"]');
    if (!button && !menu.contains(e.target)) {
        menu.classList.add('hidden');
    }
});
</script>