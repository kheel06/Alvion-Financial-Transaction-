<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('disbursement.manage');

$page_title = 'Disbursement Reports';
include __DIR__ . '/../../includes/header.php';

$report_type = $_GET['report'] ?? 'summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$department_filter = $_GET['department'] ?? '';

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'summary':
        $report_title = 'Disbursement Summary';
        $report_data = getDisbursementSummary($db, $start_date, $end_date, $department_filter);
        break;
        
    case 'approval_pending':
        $report_title = 'Pending Approvals';
        $report_data = getPendingApprovals($db);
        break;
        
    case 'cash_flow':
        $report_title = 'Cash Flow Analysis';
        $report_data = getCashFlowAnalysis($db, $start_date, $end_date);
        break;
        
    case 'department_analysis':
        $report_title = 'Department Disbursement Analysis';
        $report_data = getDepartmentAnalysis($db, $start_date, $end_date);
        break;
}

// Get departments for filtering
try {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Disbursement Reports
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Analyze disbursement patterns and approval workflow
    </p>
</div>

<!-- Report Selection and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex space-x-2">
            <a href="?report=summary" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'summary' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Summary
            </a>
            <a href="?report=approval_pending" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'approval_pending' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Pending Approvals
            </a>
            <a href="?report=cash_flow" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'cash_flow' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Cash Flow
            </a>
            <a href="?report=department_analysis" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'department_analysis' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Department Analysis
            </a>
        </div>
        
        <div class="flex space-x-2">
            <?php if (in_array($report_type, ['summary', 'cash_flow', 'department_analysis'])): ?>
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
            
            <?php if (in_array($report_type, ['summary', 'department_analysis'])): ?>
            <div>
                <label for="department_filter" class="sr-only">Department</label>
                <select id="department_filter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="flex items-end space-x-3">
                <button type="button" onclick="applyFilters()" title="Apply Filters" class="p-2 text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                    <i data-lucide="filter" class="w-5 h-5"></i>
                </button>
                <button type="button" onclick="exportReport()" title="Export Report" class="p-2 text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                    <i data-lucide="download" class="w-5 h-5"></i>
                </button>
            </div>
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
                        <?php if ($report_type === 'summary'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Request #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payee</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Request Date</th>
                        <?php elseif ($report_type === 'approval_pending'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Request #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payee</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Current Approver</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Days Pending</th>
                        <?php elseif ($report_type === 'cash_flow'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                        <?php elseif ($report_type === 'department_analysis'): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Requests</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Avg. Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status Distribution</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $total_amount = 0;
                    $total_requests = 0;
                    
                    foreach ($report_data as $row):
                        $total_amount += $row['amount'] ?? 0;
                        $total_requests++;
                    ?>
                    <tr>
                        <?php if ($report_type === 'summary'): ?>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['request_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['department_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['payee_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($row['amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadgeClass($row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php echo date('M j, Y', strtotime($row['requested_at'])); ?>
                            </td>
                        <?php elseif ($report_type === 'approval_pending'): ?>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['request_number']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['department_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['payee_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($row['amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php echo htmlspecialchars($row['approver_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php 
                                $days_pending = floor((time() - strtotime($row['requested_at'])) / (60 * 60 * 24));
                                echo $days_pending > 0 ? $days_pending . ' days' : 'Today';
                                ?>
                            </td>
                        <?php elseif ($report_type === 'department_analysis'): ?>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['department_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                <?php echo $row['request_count']; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($row['total_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($row['average_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <div class="flex space-x-1">
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">A: <?php echo $row['approved_count']; ?></span>
                                    <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">P: <?php echo $row['pending_count']; ?></span>
                                    <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">R: <?php echo $row['rejected_count']; ?></span>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals -->
                    <?php if (in_array($report_type, ['summary', 'approval_pending', 'department_analysis'])): ?>
                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                            <td colspan="<?php echo $report_type === 'department_analysis' ? 2 : 3; ?>" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                Total (<?php echo $total_requests; ?> requests)
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($total_amount, 2); ?>
                            </td>
                            <td colspan="<?php echo $report_type === 'summary' ? 2 : ($report_type === 'approval_pending' ? 2 : 2); ?>"></td>
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
    
    <?php if (in_array($report_type, ['summary', 'cash_flow', 'department_analysis'])): ?>
        params.set('start_date', document.getElementById('start_date').value);
        params.set('end_date', document.getElementById('end_date').value);
    <?php endif; ?>
    
    <?php if (in_array($report_type, ['summary', 'department_analysis'])): ?>
        const department = document.getElementById('department_filter').value;
        if (department) {
            params.set('department', department);
        }
    <?php endif; ?>
    
    window.location.href = '?' + params.toString();
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    window.open('../../api/financial/export_disbursement_report.php?' + params.toString(), '_blank');
}

function getStatusBadgeClass(status) {
    const classes = {
        'Draft': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        'Pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'Approved': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'Paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Rejected': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>