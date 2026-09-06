<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('ap.manage');

$page_title = 'AP Dashboard';
include __DIR__ . '/../../includes/header.php';

// Get AP summary data
try {
    // Total AP
    $total_ap_query = "SELECT COALESCE(SUM(net_amount), 0) as total_ap 
                      FROM ap_invoices 
                      WHERE status IN ('Pending', 'Approved')";
    $total_ap_stmt = $db->prepare($total_ap_query);
    $total_ap_stmt->execute();
    $total_ap = $total_ap_stmt->fetch(PDO::FETCH_ASSOC)['total_ap'];
    
    // Overdue invoices
    $overdue_query = "SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as amount 
                     FROM ap_invoices 
                     WHERE due_date < CURDATE() 
                     AND status IN ('Pending', 'Approved')";
    $overdue_stmt = $db->prepare($overdue_query);
    $overdue_stmt->execute();
    $overdue = $overdue_stmt->fetch(PDO::FETCH_ASSOC);
    
    // This week payments due
    $week_start = date('Y-m-d');
    $week_end = date('Y-m-d', strtotime('+7 days'));
    $week_due_query = "SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as amount 
                      FROM ap_invoices 
                      WHERE due_date BETWEEN :start AND :end 
                      AND status IN ('Pending', 'Approved')";
    $week_due_stmt = $db->prepare($week_due_query);
    $week_due_stmt->execute(['start' => $week_start, 'end' => $week_end]);
    $week_due = $week_due_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent invoices
    $recent_query = "SELECT ai.*, s.supplier_name 
                    FROM ap_invoices ai
                    INNER JOIN suppliers s ON ai.supplier_id = s.id
                    ORDER BY ai.created_at DESC 
                    LIMIT 5";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_invoices = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $total_ap = 0;
    $overdue = ['count' => 0, 'amount' => 0];
    $week_due = ['count' => 0, 'amount' => 0];
    $recent_invoices = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Accounts Payable Dashboard
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Overview of accounts payable status and metrics
    </p>
</div>

<!-- AP Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Total AP -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total AP</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_ap, 2); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">All pending invoices</p>
            </div>
            <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Overdue -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Overdue</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($overdue['amount'], 2); ?></p>
                <p class="text-xs text-red-500 dark:text-red-400"><?php echo $overdue['count']; ?> invoices</p>
            </div>
            <div class="p-3 bg-red-100 dark:bg-red-900/20 rounded-lg">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Due This Week -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Due This Week</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($week_due['amount'], 2); ?></p>
                <p class="text-xs text-amber-500 dark:text-amber-400"><?php echo $week_due['count']; ?> invoices</p>
            </div>
            <div class="p-3 bg-amber-100 dark:bg-amber-900/20 rounded-lg">
                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Suppliers -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Suppliers</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                    <?php
                    try {
                        $supplier_count_query = "SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1";
                        $supplier_count_stmt = $db->prepare($supplier_count_query);
                        $supplier_count_stmt->execute();
                        $supplier_count = $supplier_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        echo $supplier_count;
                    } catch (PDOException $e) {
                        echo '0';
                    }
                    ?>
                </p>
                <p class="text-xs text-green-500 dark:text-green-400">Active vendors</p>
            </div>
            <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-lg">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Invoices -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Invoices</h3>
        
        <div class="space-y-3">
            <?php if (empty($recent_invoices)): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No recent invoices</p>
            <?php else: ?>
                <?php foreach ($recent_invoices as $invoice): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($invoice['supplier_name']); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                ₱<?php echo number_format($invoice['net_amount'], 2); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-accounts_payable.php" 
               class="block text-center w-full px-4 py-2 text-sm font-medium text-primary-600 bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/30 rounded-md">
                View All Invoices
            </a>
        </div>
    </div>

    <!-- AP Aging Chart -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">AP Aging Summary</h3>
        
        <div class="space-y-4">
            <?php
            $aging_data = calculateAPAging($db);
            $aging_summary = [
                'Current' => 0,
                '1-30' => 0,
                '31-60' => 0,
                '61-90' => 0,
                'Over 90' => 0
            ];
            
            foreach ($aging_data as $invoice) {
                $aging_summary[$invoice['aging_bucket']] += $invoice['balance_amount'];
            }
            
            $total_aging = array_sum($aging_summary);
            $aging_colors = [
                'Current' => 'bg-green-500',
                '1-30' => 'bg-blue-500', 
                '31-60' => 'bg-yellow-500',
                '61-90' => 'bg-orange-500',
                'Over 90' => 'bg-red-500'
            ];
            
            foreach ($aging_summary as $bucket => $amount):
                if ($total_aging > 0) {
                    $percentage = ($amount / $total_aging) * 100;
                } else {
                    $percentage = 0;
                }
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600 dark:text-gray-400"><?php echo $bucket; ?></span>
                    <span class="font-medium text-gray-900 dark:text-white">₱<?php echo number_format($amount, 2); ?></span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="<?php echo $aging_colors[$bucket]; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-6 grid grid-cols-2 gap-4 text-center">
            <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Aging</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_aging, 2); ?></p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400">Invoices</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo count($aging_data); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
    <!-- Top Suppliers -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Suppliers</h3>
        
        <?php
        try {
            $top_suppliers_query = "SELECT s.supplier_name, COUNT(ai.id) as invoice_count, COALESCE(SUM(ai.net_amount), 0) as total_amount
                                   FROM suppliers s
                                   LEFT JOIN ap_invoices ai ON s.id = ai.supplier_id AND ai.status != 'Cancelled'
                                   GROUP BY s.id, s.supplier_name
                                   ORDER BY total_amount DESC
                                   LIMIT 5";
            $top_suppliers_stmt = $db->prepare($top_suppliers_query);
            $top_suppliers_stmt->execute();
            $top_suppliers = $top_suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $top_suppliers = [];
        }
        ?>
        
        <div class="space-y-3">
            <?php if (empty($top_suppliers)): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No supplier data</p>
            <?php else: ?>
                <?php foreach ($top_suppliers as $supplier): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($supplier['supplier_name']); ?></span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">₱<?php echo number_format($supplier['total_amount'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Status -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Status</h3>
        
        <?php
        try {
            $status_query = "SELECT status, COUNT(*) as count, COALESCE(SUM(net_amount), 0) as amount
                            FROM ap_invoices 
                            GROUP BY status";
            $status_stmt = $db->prepare($status_query);
            $status_stmt->execute();
            $status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $status_data = [];
        }
        ?>
        
        <div class="space-y-3">
            <?php foreach ($status_data as $status): ?>
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <?php
                        $status_dots = [
                            'Pending' => 'bg-yellow-400',
                            'Approved' => 'bg-blue-400',
                            'Paid' => 'bg-green-400',
                            'Cancelled' => 'bg-red-400'
                        ];
                        ?>
                        <span class="w-2 h-2 <?php echo $status_dots[$status['status']] ?? 'bg-gray-400'; ?> rounded-full mr-2"></span>
                        <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo $status['status']; ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo $status['count']; ?></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">invoices</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        
        <div class="space-y-2">
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-accounts_payable.php" 
               class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <svg class="w-5 h-5 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add New Invoice
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-suppliers.php" 
               class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <svg class="w-5 h-5 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Manage Suppliers
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ap_reports.php" 
               class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <svg class="w-5 h-5 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Generate Reports
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>