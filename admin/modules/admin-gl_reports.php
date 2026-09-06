<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('gl.manage');

$page_title = 'General Ledger Reports';
include __DIR__ . '/../../includes/header.php';

$report_type = $_GET['report'] ?? 'trial_balance';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'trial_balance':
        $report_title = 'Trial Balance';
        $report_data = generateTrialBalance($db, date('Y-m', strtotime($start_date)));
        break;
        
    case 'income_statement':
        $report_title = 'Income Statement';
        $report_data = generateIncomeStatement($db, $start_date, $end_date);
        break;
        
    case 'balance_sheet':
        $report_title = 'Balance Sheet';
        $report_data = generateBalanceSheet($db, $as_of_date);
        break;
        
    case 'general_ledger':
        $report_title = 'General Ledger';
        // We would create a function to get general ledger data
        break;
}

?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Financial Reports
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Generate and view financial reports
    </p>
</div>

<!-- Report Selection and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex space-x-2">
            <a href="?report=trial_balance" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'trial_balance' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Trial Balance
            </a>
            <a href="?report=income_statement" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'income_statement' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Income Statement
            </a>
            <a href="?report=balance_sheet" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'balance_sheet' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Balance Sheet
            </a>
            <a href="?report=general_ledger" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'general_ledger' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                General Ledger
            </a>
        </div>
        
        <div class="flex space-x-2">
            <?php if (in_array($report_type, ['income_statement', 'general_ledger'])): ?>
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
            <?php elseif ($report_type === 'balance_sheet'): ?>
            <div>
                <label for="as_of_date" class="sr-only">As of Date</label>
                <input type="date" id="as_of_date" name="as_of_date" value="<?php echo $as_of_date; ?>" 
                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <?php endif; ?>
            
            <button type="button" onclick="applyFilters()" title="Apply Filters" class="p-2 text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                <svg data-lucide="filter" class="w-5 h-5"></svg>
            </button>
            
            <button type="button" onclick="exportReport()" title="Export Report" class="p-2 text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                <svg data-lucide="download" class="w-5 h-5"></svg>
            </button>
        </div>
    </div>
</div>

<!-- Report Content -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"><?php echo $report_title; ?></h3>
    
    <?php if (empty($report_data)): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No data available for the selected report.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <?php if ($report_type === 'trial_balance'): ?>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Period Debit</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Period Credit</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Debit</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">YTD Credit</th>
                        <?php elseif ($report_type === 'income_statement'): ?>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                        <?php elseif ($report_type === 'balance_sheet'): ?>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $total_debit = 0;
                    $total_credit = 0;
                    $total_ytd_debit = 0;
                    $total_ytd_credit = 0;
                    $total_amount = 0;
                    
                    foreach ($report_data as $row):
                        if ($report_type === 'trial_balance'):
                            $total_debit += $row['period_debit'];
                            $total_credit += $row['period_credit'];
                            $total_ytd_debit += $row['ytd_debit'];
                            $total_ytd_credit += $row['ytd_credit'];
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['account_code'] . ' - ' . $row['account_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['period_debit'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['period_credit'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['ytd_debit'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['ytd_credit'], 2); ?>
                            </td>
                        </tr>
                    <?php
                        elseif ($report_type === 'income_statement'):
                            $total_amount += $row['amount'];
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['account_code'] . ' - ' . $row['account_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['amount'], 2); ?>
                            </td>
                        </tr>
                    <?php
                        elseif ($report_type === 'balance_sheet'):
                            $total_amount += $row['balance'];
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['account_code'] . ' - ' . $row['account_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['balance'], 2); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Totals -->
                    <?php if ($report_type === 'trial_balance'): ?>
                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">Total</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">₱<?php echo number_format($total_debit, 2); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">₱<?php echo number_format($total_credit, 2); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">₱<?php echo number_format($total_ytd_debit, 2); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">₱<?php echo number_format($total_ytd_credit, 2); ?></td>
                        </tr>
                    <?php elseif (in_array($report_type, ['income_statement', 'balance_sheet'])): ?>
                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">Total</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">₱<?php echo number_format($total_amount, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const params = new URLSearchParams(window.location.search);
    params.set('report', '<?php echo $report_type; ?>');
    
    <?php if (in_array($report_type, ['income_statement', 'general_ledger'])): ?>
        params.set('start_date', document.getElementById('start_date').value);
        params.set('end_date', document.getElementById('end_date').value);
    <?php elseif ($report_type === 'balance_sheet'): ?>
        params.set('as_of_date', document.getElementById('as_of_date').value);
    <?php endif; ?>
    
    window.location.href = '?' + params.toString();
}

function exportReport() {
    // This would typically generate an Excel or PDF export
    alert('Export functionality would be implemented here.');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>