<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('budget.manage');

$page_title = 'Budget Reports';
include __DIR__ . '/../../includes/header.php';

$report_type = $_GET['report'] ?? 'utilization';
$fiscal_year = $_GET['fiscal_year'] ?? CURRENT_FISCAL_YEAR;
$department_id = $_GET['department'] ?? '';

// Generate reports based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'utilization':
        $report_title = 'Budget Utilization Report';
        if ($department_id) {
            $report_data = getMonthlyBudgetUtilization($db, $department_id, $fiscal_year);
        } else {
            $report_data = getBudgetVsActual($db, $fiscal_year);
        }
        break;
        
    case 'variance':
        $report_title = 'Budget Variance Analysis';
        $report_data = getBudgetVsActual($db, $fiscal_year, $department_id);
        break;
        
    case 'forecast':
        $report_title = 'Budget Forecast';
        // Implementation for forecasting would go here
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
        Budget Reports & Analytics
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Comprehensive budget analysis and performance tracking
    </p>
</div>

<!-- Report Selection and Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex space-x-2">
            <a href="?report=utilization" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'utilization' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Utilization
            </a>
            <a href="?report=variance" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'variance' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Variance Analysis
            </a>
            <a href="?report=forecast" class="px-4 py-2 text-sm font-medium rounded-md <?php echo $report_type === 'forecast' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'; ?>">
                Forecast
            </a>
        </div>
        
        <div class="flex space-x-2">
            <div>
                <label for="fiscal_year" class="sr-only">Fiscal Year</label>
                <select id="fiscal_year" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                    <?php for ($year = date('Y') - 1; $year <= date('Y') + 1; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $fiscal_year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div>
                <label for="department_filter" class="sr-only">Department</label>
                <select id="department_filter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
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
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            No data available for the selected report.
        </p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <?php if ($report_type === 'utilization' && $department_id): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Month</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Allocated</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Utilized</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Utilization %</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        <?php elseif ($report_type === 'variance' || ($report_type === 'utilization' && !$department_id)): ?>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Budget</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actual</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Variance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Variance %</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php
                    $total_budget = 0;
                    $total_actual = 0;
                    $total_variance = 0;
                    
                    foreach ($report_data as $row):
                        if ($report_type === 'utilization' && $department_id):
                            $utilization_percent = $row['utilization_percent'];
                            $status = $utilization_percent >= 90 ? 'Overutilized' : 
                                     ($utilization_percent >= 75 ? 'Warning' : 'Normal');
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo date('F Y', strtotime($row['month'] . '-01')); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['allocated_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['utilized_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                <?php echo $utilization_percent; ?>%
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php
                                $status_badges = [
                                    'Normal' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'Warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                    'Overutilized' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ];
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_badges[$status]; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                        </tr>
                    <?php
                        elseif ($report_type === 'variance' || ($report_type === 'utilization' && !$department_id)):
                            $total_budget += $row['budget_amount'];
                            $total_actual += $row['actual_amount'];
                            $total_variance += $row['variance_amount'];
                            
                            $variance_percent = $row['variance_percent'];
                            $status = $variance_percent < -10 ? 'Over Budget' : 
                                     ($variance_percent > 10 ? 'Under Budget' : 'On Track');
                    ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($row['department_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['budget_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['actual_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($row['variance_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                <?php echo $variance_percent; ?>%
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php
                                $status_badges = [
                                    'On Track' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'Under Budget' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'Over Budget' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ];
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_badges[$status]; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Totals for variance report -->
                    <?php if (($report_type === 'variance' || ($report_type === 'utilization' && !$department_id)) && !empty($report_data)): ?>
                        <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">Total</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($total_budget, 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($total_actual, 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                ₱<?php echo number_format($total_variance, 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                <?php echo $total_budget > 0 ? round(($total_variance / $total_budget) * 100, 2) : 0; ?>%
                            </td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const report = '<?php echo $report_type; ?>';
    const fiscalYear = document.getElementById('fiscal_year').value;
    const department = document.getElementById('department_filter').value;
    
    const params = new URLSearchParams();
    params.set('report', report);
    params.set('fiscal_year', fiscalYear);
    if (department) params.set('department', department);
    
    window.location.href = '?' + params.toString();
}

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    window.open('../../api/financial/export_budget_report.php?' + params.toString(), '_blank');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>