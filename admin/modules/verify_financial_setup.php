<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);

$page_title = 'Financial System Setup Verification';
include __DIR__ . '/../../includes/header.php';

$tables_to_check = [
    'chart_of_accounts',
    'journal_entries', 
    'journal_entry_lines',
    'ap_invoices',
    'ar_invoices',
    'disbursement_requests',
    'budget_headers',
    'collection_payments',
    'suppliers',
    'departments'
];

$results = [];

foreach ($tables_to_check as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $results[$table] = [
            'exists' => true,
            'count' => $result['count'],
            'status' => 'success'
        ];
    } catch (PDOException $e) {
        $results[$table] = [
            'exists' => false,
            'count' => 0,
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Financial System Setup Verification
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Verify that all financial system components are properly installed
    </p>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Database Tables Status</h3>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Table Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Record Count</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($results as $table => $result): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo $table; ?></td>
                    <td class="px-4 py-3">
                        <?php if ($result['status'] === 'success'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                Active
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                Missing
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-300"><?php echo $result['count']; ?> records</td>
                    <td class="px-4 py-3 text-sm font-medium">
                        <?php if ($result['status'] === 'error'): ?>
                            <a href="<?php echo BASE_URL; ?>/database/setup_financial_tables.php" 
                               class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                                Create Table
                            </a>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- System Requirements -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Requirements</h3>
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                PHP 8.0 or higher
            </li>
            <li class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                MySQL 5.7+ or MariaDB 10.3+
            </li>
            <li class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                PDO Extension enabled
            </li>
        </ul>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Setup</h3>
        <div class="space-y-3">
            <a href="<?php echo BASE_URL; ?>/database/setup_financial_tables.php" 
               class="block w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 text-center">
                Initialize Financial Database
            </a>
            <a href="<?php echo BASE_URL; ?>/database/seed_chart_of_accounts.php" 
               class="block w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 text-center">
                Seed Chart of Accounts
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>