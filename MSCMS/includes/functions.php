<?php
/**
 * MSCMS - Common Helper Functions
 */

/**
 * Sanitize output for HTML
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helper
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format number with thousand separators
 */
function formatNumber($number, int $decimals = 0): string {
    return number_format((float)$number, $decimals);
}

/**
 * Format date nicely
 */
function formatDate(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $colors = [
        'Pending'           => 'bg-yellow-100 text-yellow-800',
        'Approved'          => 'bg-green-100 text-green-800',
        'Rejected'          => 'bg-red-100 text-red-800',
        'Draft'             => 'bg-gray-100 text-gray-800',
        'Submitted'         => 'bg-blue-100 text-blue-800',
        'Partially Received'=> 'bg-orange-100 text-orange-800',
        'Received'          => 'bg-green-100 text-green-800',
        'Cancelled'         => 'bg-red-100 text-red-800',
        'Dispatched'        => 'bg-indigo-100 text-indigo-800',
        'Delivered'         => 'bg-green-100 text-green-800',
        'In Transit'        => 'bg-blue-100 text-blue-800',
        'Planned'           => 'bg-purple-100 text-purple-800',
        'Delayed'           => 'bg-red-100 text-red-800',
        'Low Stock'         => 'bg-orange-100 text-orange-800',
        'Expiring'          => 'bg-yellow-100 text-yellow-800',
        'Expired'           => 'bg-red-100 text-red-800',
    ];

    $class = $colors[$status] ?? 'bg-gray-100 text-gray-800';
    return '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium ' . $class . '">' . e($status) . '</span>';
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}
