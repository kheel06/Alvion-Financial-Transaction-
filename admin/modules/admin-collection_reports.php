<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('collection.manage');

$page_title = 'Collection Reports';
include __DIR__ . '/../../includes/header.php';

$report_type = $_GET['report'] ?? 'daily_summary';
// Default to year-to-date so reports show data (e.g. ORs from earlier in the year)
$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$payment_method = $_GET['payment_method'] ?? '';

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'daily_summary':
        $report_title = 'Daily Collection Summary';
        $report_data = getDailyCollectionSummary($db, $start_date, $end_date);
        break;
        
    case 'or_register':
        $report_title = 'OR Register';
        $report_data = getORRegister($db, $start_date, $end_date, $payment_method);
        break;
        
    case 'payment_method_analysis':
        $report_title = 'Payment Method Analysis';
        $report_data = getPaymentMethodAnalysis($db, $start_date, $end_date);
        break;
}

// Get payment methods for filtering
$payment_methods = ['Cash', 'Card', 'Check', 'Online', 'HMO', 'PhilHealth'];
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Collection Reports
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Generate collection reports and analytics
    </p>
</div>

<!-- Report Selection and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex space-x-2">
            <?php
            $base_params = 'start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date);
            if ($report_type === 'or_register' && $payment_method) {
                $base_params .= '&payment_method=' . urlencode($payment_method);
            }
            ?>
            <a href="?report=daily_summary&amp;<?php echo $base_params; ?>" title="Daily Summary" class="flex items-center justify-center w-10 h-10 rounded-md transition-colors <?php echo $report_type === 'daily_summary' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300'; ?>">
                <svg data-lucide="file-text" class="w-5 h-5"></svg>
            </a>
            <a href="?report=or_register&amp;<?php echo $base_params; ?>" title="OR Register" class="flex items-center justify-center w-10 h-10 rounded-md transition-colors <?php echo $report_type === 'or_register' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300'; ?>">
                <svg data-lucide="clipboard-list" class="w-5 h-5"></svg>
            </a>
            <a href="?report=payment_method_analysis&amp;<?php echo $base_params; ?>" title="Payment Analysis" class="flex items-center justify-center w-10 h-10 rounded-md transition-colors <?php echo $report_type === 'payment_method_analysis' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300'; ?>">
                <svg data-lucide="pie-chart" class="w-5 h-5"></svg>
            </a>
        </div>
        
        <div class="flex space-x-2">
            <div>
                <label for="start_date" class="sr-only">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" 
                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <div>
                <label for="end_date" class="sr-only">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" 
                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            
            <?php if ($report_type === 'or_register'): ?>
            <div>
                <label for="payment_method" class="sr-only">Payment Method</label>
                <select id="payment_method" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Methods</option>
                    <?php foreach ($payment_methods as $method): ?>
                        <option value="<?php echo $method; ?>" <?php echo $payment_method === $method ? 'selected' : ''; ?>>
                            <?php echo $method; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <button type="button" onclick="applyFilters()" title="Apply Filters" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                <svg data-lucide="filter" class="w-5 h-5"></svg>
            </button>
            
            <button type="button" onclick="exportReport()" title="Export Report" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                <svg data-lucide="download" class="w-5 h-5"></svg>
            </button>
        </div>
    </div>
</div>

<!-- Report Content -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"><?php echo $report_title; ?></h3>
    
    <?php if (empty($report_data)): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            No data available for the selected report for <?php echo date('M j', strtotime($start_date)); ?>–<?php echo date('M j, Y', strtotime($end_date)); ?>.
            Try a wider date range (e.g. year to date) or confirm that payments exist in this period.
        </p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <?php if ($report_type === 'daily_summary'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cash</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Card</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Check</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Online</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">HMO</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">PhilHealth</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                        <?php elseif ($report_type === 'or_register'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">OR Number</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient/HMO</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payment Method</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cashier</th>
                        <?php elseif ($report_type === 'payment_method_analysis'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payment Method</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Count</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Percentage</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $grand_total = 0;
                    
                    foreach ($report_data as $row):
                        if ($report_type === 'daily_summary'):
                            $day_total = $row['cash'] + $row['card'] + ($row['check_amount'] ?? 0) + $row['online'] + $row['hmo'] + $row['philhealth'];
                            $grand_total += $day_total;
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo date('M j, Y', strtotime($row['payment_date'])); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['cash'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['card'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['check_amount'] ?? 0, 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['online'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['hmo'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['philhealth'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($day_total, 2); ?>
                            </td>
                        </tr>
                    <?php
                        elseif ($report_type === 'or_register'):
                            $grand_total += $row['payment_amount'];
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['or_number']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php echo date('M j, Y', strtotime($row['payment_date'])); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php 
                                if ($row['first_name']) {
                                    echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                                } elseif ($row['hmo_name']) {
                                    echo htmlspecialchars($row['hmo_name']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo getPaymentMethodBadgeClass($row['payment_method']); ?>">
                                    <?php echo $row['payment_method']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($row['payment_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php echo htmlspecialchars($row['employee_fname'] . ' ' . $row['employee_lname']); ?>
                            </td>
                        </tr>
                    <?php
                        elseif ($report_type === 'payment_method_analysis'):
                            $grand_total += $row['total_amount'];
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo $row['payment_method']; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                <?php echo $row['payment_count']; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['total_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                <?php echo $grand_total > 0 ? number_format(($row['total_amount'] / $grand_total) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Grand Total -->
                    <?php if ($report_type !== 'payment_method_analysis' && $grand_total > 0): ?>
                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                            <td colspan="<?php echo $report_type === 'daily_summary' ? 7 : 5; ?>" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                Grand Total
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($grand_total, 2); ?>
                            </td>
                            <?php if ($report_type === 'or_register'): ?>
                                <td></td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const params = new URLSearchParams();
    params.set('report', '<?php echo $report_type; ?>');
    params.set('start_date', document.getElementById('start_date').value);
    params.set('end_date', document.getElementById('end_date').value);
    
    <?php if ($report_type === 'or_register'): ?>
        const paymentMethod = document.getElementById('payment_method').value;
        if (paymentMethod) {
            params.set('payment_method', paymentMethod);
        }
    <?php endif; ?>
    
    window.location.href = '?' + params.toString();
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    window.open('../../api/financial/export_collection_report.php?' + params.toString(), '_blank');
}
</script>

<?php
// Helper function for payment method badges
function getPaymentMethodBadgeClass($method) {
    $classes = [
        'Cash' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Card' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'Check' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'Online' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        'HMO' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        'PhilHealth' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    ];
    
    return $classes[$method] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

include __DIR__ . '/../../includes/footer.php';
?>