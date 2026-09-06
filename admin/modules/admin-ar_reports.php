<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('ar.manage');

$page_title = 'AR Reports';
include __DIR__ . '/../../includes/header.php';

$report_type = $_GET['report'] ?? 'aging';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$service_filter = $_GET['service_type'] ?? '';

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'aging':
        $report_title = 'AR Aging Report';
        $report_data = calculateARAging($db);
        break;
        
    case 'collection':
        $report_title = 'Collection Report';
        $report_data = getCollectionReport($db, $start_date, $end_date);
        break;
        
    case 'revenue':
        $report_title = 'Revenue by Service Type';
        $report_data = getRevenueByService($db, $start_date, $end_date, $service_filter);
        break;
}

// Get service types for filter
$service_types = ['OPD', 'IPD', 'ER', 'Lab', 'Pharmacy', 'Other'];
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Accounts Receivable Reports
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Generate AR reports and analytics
    </p>
</div>

<!-- Report Selection and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex space-x-2">
            <a href="?report=aging" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'aging' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                AR Aging
            </a>
            <a href="?report=collection" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'collection' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Collection Report
            </a>
            <a href="?report=revenue" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'revenue' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Revenue Analysis
            </a>
        </div>
        
        <div class="flex space-x-2">
            <?php if (in_array($report_type, ['collection', 'revenue'])): ?>
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
            
            <?php if ($report_type === 'revenue'): ?>
            <div>
                <label for="service_filter" class="sr-only">Service Type</label>
                <select id="service_filter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Services</option>
                    <?php foreach ($service_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $service_filter === $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Aging</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service Type</th>
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
                        $aging_bucket = $invoice['aging_bucket'] ?? 'Current';
                        $balance_amount = (float) ($invoice['balance_amount'] ?? 0);
                        if (isset($aging_totals[$aging_bucket])) {
                            $aging_totals[$aging_bucket] += $balance_amount;
                        }
                        
                        // Determine client name
                        $client_name = 'N/A';
                        $patient_fname = $invoice['patient_fname'] ?? '';
                        $patient_lname = $invoice['patient_lname'] ?? '';
                        $hmo_name = $invoice['hmo_name'] ?? '';
                        $company_name = $invoice['company_name'] ?? '';
                        if (trim($patient_fname) !== '' || trim($patient_lname) !== '') {
                            $client_name = trim($patient_fname . ' ' . $patient_lname);
                        } elseif (trim($hmo_name) !== '') {
                            $client_name = $hmo_name . ' (HMO)';
                        } elseif (trim($company_name) !== '') {
                            $client_name = $company_name . ' (Corporate)';
                        }
                    ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($invoice['invoice_number'] ?? '—'); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($client_name); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <?php echo !empty($invoice['due_date']) ? date('M j, Y', strtotime($invoice['due_date'])) : '—'; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                            ₱<?php echo number_format($balance_amount, 2); ?>
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
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $aging_badges[$aging_bucket] ?? $aging_badges['Current']; ?>">
                                <?php echo htmlspecialchars($aging_bucket); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <?php echo htmlspecialchars($invoice['service_type'] ?? '—'); ?>
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
        
    <?php elseif ($report_type === 'collection'): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">OR Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payment Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Client</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $total_collections = 0;
                    $method_totals = [];
                    
                    foreach ($report_data as $payment):
                        $total_collections += $payment['payment_amount'];
                        
                        if (!isset($method_totals[$payment['payment_method']])) {
                            $method_totals[$payment['payment_method']] = 0;
                        }
                        $method_totals[$payment['payment_method']] += $payment['payment_amount'];
                        
                        // Determine client name
                        $client_name = 'N/A';
                        if ($payment['patient_fname']) {
                            $client_name = $payment['patient_fname'] . ' ' . $payment['patient_lname'];
                        } elseif ($payment['hmo_name']) {
                            $client_name = $payment['hmo_name'] . ' (HMO)';
                        }
                    ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($payment['or_number']); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($client_name); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                            ₱<?php echo number_format($payment['payment_amount'], 2); ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <?php echo $payment['payment_method']; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <?php echo $payment['reference_number'] ?: 'N/A'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Collection Summary -->
                    <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                        <td colspan="3" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">Total Collections</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                            ₱<?php echo number_format($total_collections, 2); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                    <?php foreach ($method_totals as $method => $total): ?>
                    <tr class="bg-gray-50 dark:bg-gray-700">
                        <td colspan="3" class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 pl-8">
                            <?php echo $method; ?>
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
    
    <?php if (in_array($report_type, ['collection', 'revenue'])): ?>
        params.set('start_date', document.getElementById('start_date').value);
        params.set('end_date', document.getElementById('end_date').value);
    <?php endif; ?>
    
    <?php if ($report_type === 'revenue'): ?>
        const service = document.getElementById('service_filter').value;
        if (service) {
            params.set('service_type', service);
        }
    <?php endif; ?>
    
    window.location.href = '?' + params.toString();
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    window.open('../../api/financial/export_ar_report.php?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>