<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('ap.manage');

$page_title = 'AP Reports';
include __DIR__ . '/../../includes/header.php';

$report_type = $_GET['report'] ?? 'aging';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'aging':
        $report_title = 'AP Aging Report';
        $report_data = calculateAPAging($db);
        break;
        
    case 'payment_schedule':
        $report_title = 'Payment Schedule';
        // We'll create a function for payment schedule
        break;
        
    case 'supplier_ledger':
        $report_title = 'Supplier Ledger';
        // We'll create a function for supplier ledger
        break;
}

// Get suppliers for filtering
try {
    $suppliers_query = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY supplier_name";
    $suppliers_stmt = $db->prepare($suppliers_query);
    $suppliers_stmt->execute();
    $suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $suppliers = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Accounts Payable Reports
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Generate AP reports and analytics
    </p>
</div>

<!-- Report Selection and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex space-x-2">
            <a href="?report=aging" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'aging' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                AP Aging
            </a>
            <a href="?report=payment_schedule" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'payment_schedule' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Payment Schedule
            </a>
            <a href="?report=supplier_ledger" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'supplier_ledger' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Supplier Ledger
            </a>
        </div>
        
        <div class="flex space-x-2">
            <?php if (in_array($report_type, ['payment_schedule', 'supplier_ledger'])): ?>
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
            <?php endif; ?>
            
            <?php if ($report_type === 'supplier_ledger'): ?>
            <div>
                <label for="supplier_filter" class="sr-only">Supplier</label>
                <select id="supplier_filter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['supplier_name']); ?></option>
                    <?php endforeach; ?>
                </select>
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
    
    <?php if ($report_type === 'aging'): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoice #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Aging</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $aging_totals = [
                        'Current' => 0,
                        '1-30' => 0,
                        '31-60' => 0,
                        '61-90' => 0,
                        'Over 90' => 0
                    ];
                    
                    foreach ($report_data as $invoice):
                        $aging_totals[$invoice['aging_bucket']] += $invoice['balance_amount'];
                        $due_date = new DateTime($invoice['due_date']);
                        $today = new DateTime();
                        $days_overdue = $today->diff($due_date)->days;
                        $is_overdue = $due_date < $today;
                    ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($invoice['supplier_name']); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                            ₱<?php echo number_format($invoice['balance_amount'], 2); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php
                            $aging_badges = [
                                'Current' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                '1-30' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                '31-60' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                '61-90' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                'Over 90' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                            ];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $aging_badges[$invoice['aging_bucket']]; ?>">
                                <?php echo $invoice['aging_bucket']; ?>
                                <?php if ($is_overdue && $invoice['aging_bucket'] !== 'Current'): ?>
                                    (<?php echo $days_overdue; ?> days)
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            Approved
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Aging Summary -->
                    <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                        <td colspan="3" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">Total by Aging Bucket</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                            ₱<?php echo number_format(array_sum($aging_totals), 2); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                    <?php foreach ($aging_totals as $bucket => $total): ?>
                    <tr class="bg-gray-50 dark:bg-gray-700">
                        <td colspan="3" class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 pl-8">
                            <?php echo $bucket; ?> Days
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-right">
                            ₱<?php echo number_format($total, 2); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            Report type "<?php echo $report_type; ?>" is coming soon.
        </p>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const params = new URLSearchParams(window.location.search);
    params.set('report', '<?php echo $report_type; ?>');
    
    <?php if (in_array($report_type, ['payment_schedule', 'supplier_ledger'])): ?>
        params.set('start_date', document.getElementById('start_date').value);
        params.set('end_date', document.getElementById('end_date').value);
    <?php endif; ?>
    
    <?php if ($report_type === 'supplier_ledger'): ?>
        const supplier = document.getElementById('supplier_filter').value;
        if (supplier) {
            params.set('supplier', supplier);
        }
    <?php endif; ?>
    
    window.location.href = '?' + params.toString();
}

function exportReport() {
    // This would typically generate an Excel or PDF export
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    window.open('../../api/financial/export_ap_report.php?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>