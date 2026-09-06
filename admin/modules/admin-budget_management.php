<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('budget.manage');

// Handle form submissions before any output (so redirect works)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_budget'])) {
        try {
            $db->beginTransaction();

            $header_query = "INSERT INTO budget_headers 
                           (fiscal_year, department_id, total_budget, status, created_by) 
                           VALUES (:fiscal_year, :department_id, :total_budget, 'Draft', :created_by)";
            $header_stmt = $db->prepare($header_query);
            $header_stmt->execute([
                'fiscal_year' => $_POST['fiscal_year'],
                'department_id' => $_POST['department_id'],
                'total_budget' => $_POST['total_budget'],
                'created_by' => $_SESSION['employee_id']
            ]);
            $budget_header_id = $db->lastInsertId();

            $line_query = "INSERT INTO budget_lines 
                          (budget_header_id, account_id, notes, total_allocation, monthly_allocations) 
                          VALUES (:budget_header_id, :account_id, :notes, :total_allocation, :monthly_allocations)";
            $line_stmt = $db->prepare($line_query);
            $fiscal_year = $_POST['fiscal_year'];

            foreach ($_POST['account_id'] as $index => $account_id) {
                $amount = floatval($_POST['amount'][$index] ?? 0);
                if ($amount > 0) {
                    $notes = isset($_POST['line_description'][$index]) ? trim($_POST['line_description'][$index]) : null;
                    $monthly = [];
                    for ($month = 1; $month <= 12; $month++) {
                        $month_key = $fiscal_year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
                        $monthly[$month_key] = round($amount / 12, 2);
                    }
                    $monthly_allocations = json_encode($monthly);
                    $line_stmt->execute([
                        'budget_header_id' => $budget_header_id,
                        'account_id' => $account_id,
                        'notes' => $notes,
                        'total_allocation' => $amount,
                        'monthly_allocations' => $monthly_allocations
                    ]);
                }
            }

            $db->commit();
            $_SESSION['success'] = "Budget created successfully.";
            header("Location: admin-budget_management.php");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error creating budget: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        try {
            $query = "UPDATE budget_headers SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'status' => $_POST['status'],
                'id' => $_POST['budget_id']
            ]);
            $_SESSION['success'] = "Budget status updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating budget: " . $e->getMessage();
        }
    }
}

$page_title = 'Budget Management';
include __DIR__ . '/../../includes/header.php';

// Get budgets with filters
$status_filter = $_GET['status'] ?? 'all';
$fiscal_year_filter = $_GET['fiscal_year'] ?? CURRENT_FISCAL_YEAR;
$department_filter = $_GET['department'] ?? '';

try {
    $query = "SELECT bh.*, d.department_name, d.department_code,
                     da.employee_fname, da.employee_lname,
                     COUNT(bl.id) as line_count
              FROM budget_headers bh
              INNER JOIN departments d ON bh.department_id = d.id
              LEFT JOIN department_accounts da ON bh.created_by = da.employee_id
              LEFT JOIN budget_lines bl ON bh.id = bl.budget_header_id
              WHERE 1=1";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND bh.status = :status";
        $params['status'] = $status_filter;
    }
    
    if (!empty($fiscal_year_filter)) {
        $query .= " AND bh.fiscal_year = :fiscal_year";
        $params['fiscal_year'] = $fiscal_year_filter;
    }
    
    if (!empty($department_filter)) {
        $query .= " AND bh.department_id = :department_id";
        $params['department_id'] = $department_filter;
    }
    
    $query .= " GROUP BY bh.id, d.department_name, d.department_code, da.employee_fname, da.employee_lname
                ORDER BY bh.fiscal_year DESC, d.department_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $budgets = [];
}

// Get departments and accounts for dropdowns
try {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

try {
    $accounts_query = "SELECT * FROM chart_of_accounts WHERE account_type = 'Expense' AND is_active = 1 ORDER BY account_code";
    $accounts_stmt = $db->prepare($accounts_query);
    $accounts_stmt->execute();
    $expense_accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expense_accounts = [];
}

// Get budget summary
try {
    $summary_query = "SELECT 
                        status,
                        COUNT(*) as count,
                        SUM(total_budget) as total_amount
                      FROM budget_headers 
                      WHERE fiscal_year = :fiscal_year
                      GROUP BY status";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute(['fiscal_year' => $fiscal_year_filter]);
    $budget_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $budget_summary = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Budget Management
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Plan, track, and manage departmental budgets
    </p>
</div>

<!-- Budget Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $status_totals = [];
    foreach ($budget_summary as $summary) {
        $status_totals[$summary['status']] = [
            'count' => $summary['count'],
            'amount' => $summary['total_amount']
        ];
    }
    
    $summary_items = [
        'Draft' => [
            'count' => $status_totals['Draft']['count'] ?? 0,
            'amount' => $status_totals['Draft']['amount'] ?? 0,
            'color' => 'gray',
            'icon' => 'file-edit'
        ],
        'Submitted' => [
            'count' => $status_totals['Submitted']['count'] ?? 0,
            'amount' => $status_totals['Submitted']['amount'] ?? 0,
            'color' => 'yellow',
            'icon' => 'clock'
        ],
        'Approved' => [
            'count' => $status_totals['Approved']['count'] ?? 0,
            'amount' => $status_totals['Approved']['amount'] ?? 0,
            'color' => 'green',
            'icon' => 'check-circle'
        ],
        'Total' => [
            'count' => array_sum(array_column($budget_summary, 'count')),
            'amount' => array_sum(array_column($budget_summary, 'total_amount')),
            'color' => 'blue',
            'icon' => 'coins'
        ]
    ];
    
    foreach ($summary_items as $label => $data):
        $color_classes = [
            'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
        ];
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo $label; ?></p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($data['amount'], 2); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $data['count']; ?> budgets</p>
            </div>
            <div class="text-gray-400 dark:text-gray-500">
                <svg data-lucide="<?php echo $data['icon']; ?>" class="w-8 h-8"></svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Create Budget Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create New Budget</h3>
            
            <form method="POST" id="budgetForm">
                <div class="space-y-4">
                    <div>
                        <label for="fiscal_year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fiscal Year *</label>
                        <select id="fiscal_year" name="fiscal_year" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <?php for ($year = date('Y'); $year <= date('Y') + 2; $year++): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == CURRENT_FISCAL_YEAR ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Department *</label>
                        <select id="department_id" name="department_id" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="total_budget" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total Budget *</label>
                        <input type="number" id="total_budget" name="total_budget" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="0.00">
                    </div>
                    
                    <!-- Budget Lines -->
                    <div class="border-t border-gray-200 dark:border-gray-600 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Budget Line Items</h4>
                            <button type="button" id="addLine" title="Add Line" class="p-1 text-white bg-primary-600 rounded-md hover:bg-primary-700">
                                <svg data-lucide="plus" class="w-5 h-5"></svg>
                            </button>
                        </div>
                        
                        <div id="budgetLines" class="space-y-3">
                            <!-- Line template will be added here by JavaScript -->
                        </div>
                        
                        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Total Allocated:</span>
                                <span id="totalAllocated" class="font-semibold">₱0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-sm mt-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Remaining:</span>
                                <span id="remainingBudget" class="font-semibold">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" name="create_budget" title="Create Budget" class="flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                            <svg data-lucide="check-circle" class="w-5 h-5"></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-2">
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-budget_reports.php?report=utilization" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="file-text" class="w-5 h-5 mr-3 text-blue-600"></svg>
                    Budget Utilization
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-budget_reports.php?report=variance" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="bar-chart-2" class="w-5 h-5 mr-3 text-green-600"></svg>
                    Variance Analysis
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-budget_reports.php?report=forecast" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="trending-up" class="w-5 h-5 mr-3 text-purple-600"></svg>
                    Budget Forecast
                </a>
            </div>
        </div>
    </div>
    
    <!-- Budgets List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Department Budgets</h3>
                
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <!-- Status Filter -->
                    <select id="statusFilter" onchange="applyFilters()" 
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Draft" <?php echo $status_filter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Submitted" <?php echo $status_filter === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    
                    <!-- Fiscal Year Filter -->
                    <select id="fiscalYearFilter" onchange="applyFilters()"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <?php for ($year = date('Y') - 1; $year <= date('Y') + 2; $year++): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $fiscal_year_filter ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <!-- Department Filter -->
                    <select id="departmentFilter" onchange="applyFilters()"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fiscal Year</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Budget</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Created</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($budgets)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No budgets found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($budgets as $budget): 
                                $utilization = getBudgetUtilization($db, $budget['department_id'], $budget['fiscal_year']);
                            ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($budget['department_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo $budget['line_count']; ?> line items
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo $budget['fiscal_year']; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                    ₱<?php echo number_format($budget['total_budget'], 2); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $status_badges = [
                                        'Draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                        'Submitted' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'Approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_badges[$budget['status']]; ?>">
                                        <?php echo $budget['status']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <?php echo date('M j, Y', strtotime($budget['created_at'])); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewBudget(<?php echo $budget['id']; ?>)" title="View Budget"
                                            class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-3">
                                        <svg data-lucide="eye" class="w-5 h-5"></svg>
                                    </button>
                                    <?php if ($budget['status'] === 'Draft'): ?>
                                        <button onclick="updateStatus(<?php echo $budget['id']; ?>, 'Submitted')" title="Submit Budget"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                            <svg data-lucide="send" class="w-5 h-5"></svg>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Budget Utilization Alerts -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Budget Utilization Alerts</h3>
            <?php
            $alerts = getBudgetAlerts($db);
            if (empty($alerts)):
            ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    No budget alerts at this time.
                </p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($alerts as $alert): 
                        $alert_level = $alert['utilization_percent'] >= 90 ? 'high' : ($alert['utilization_percent'] >= 75 ? 'medium' : 'low');
                        $alert_colors = [
                            'high' => 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800',
                            'medium' => 'bg-yellow-50 border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800',
                            'low' => 'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800'
                        ];
                    ?>
                    <div class="p-4 border rounded-lg <?php echo $alert_colors[$alert_level]; ?>">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($alert['department_name']); ?> - <?php echo $alert['fiscal_year']; ?>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Utilization: <?php echo $alert['utilization_percent']; ?>% 
                                    (₱<?php echo number_format($alert['actual_expenses'], 2); ?> of ₱<?php echo number_format($alert['total_budget'], 2); ?>)
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo $alert_level === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                       ($alert_level === 'medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                       'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'); ?>">
                                <?php echo $alert_level === 'high' ? 'Critical' : ($alert_level === 'medium' ? 'Warning' : 'Info'); ?>
                            </span>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                            <div class="h-2 rounded-full 
                                <?php echo $alert_level === 'high' ? 'bg-red-600' : 
                                       ($alert_level === 'medium' ? 'bg-yellow-600' : 'bg-blue-600'); ?>" 
                                style="width: <?php echo min($alert['utilization_percent'], 100); ?>%">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Budget Modal -->
<div id="viewBudgetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Budget Details</h3>
            <div id="budgetDetails" class="space-y-3">
                <!-- Budget details will be loaded here via AJAX -->
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button onclick="closeViewModal()" title="Close" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                    <svg data-lucide="x" class="w-5 h-5"></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const budgetLines = document.getElementById('budgetLines');
    const addLineBtn = document.getElementById('addLine');
    const totalBudgetInput = document.getElementById('total_budget');
    const totalAllocatedEl = document.getElementById('totalAllocated');
    const remainingBudgetEl = document.getElementById('remainingBudget');
    
    let lineCount = 0;
    
    // Add first line
    addBudgetLine();
    
    addLineBtn.addEventListener('click', addBudgetLine);
    totalBudgetInput.addEventListener('input', updateBudgetSummary);
    
    function addBudgetLine() {
        lineCount++;
        const lineDiv = document.createElement('div');
        lineDiv.className = 'budget-line p-3 border border-gray-200 dark:border-gray-600 rounded-lg';
        lineDiv.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                <div class="md:col-span-6">
                    <select name="account_id[]" class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white" required>
                        <option value="">Select Account</option>
                        <?php foreach ($expense_accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <input type="number" name="amount[]" step="0.01" min="0" required
                           class="line-amount w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white"
                           placeholder="0.00">
                </div>
                <div class="md:col-span-2">
                    <button type="button" class="remove-line w-full inline-flex items-center justify-center px-2 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 dark:bg-red-900 dark:text-red-300 dark:hover:bg-red-800" title="Remove Line">
                        <svg data-lucide="trash-2" class="w-4 h-4"></svg>
                    </button>
                </div>
            </div>
            <div class="mt-2">
                <input type="text" name="line_description[]" placeholder="Line description (optional)" 
                       class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
        `;
        
        budgetLines.appendChild(lineDiv);
        
        // Initialize icons
        if (typeof window !== 'undefined' && typeof window.renderLucideIcons === 'function') {
            window.renderLucideIcons();
        } else if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
        
        // Add event listeners for the new line
        const amountInput = lineDiv.querySelector('.line-amount');
        const removeBtn = lineDiv.querySelector('.remove-line');
        
        amountInput.addEventListener('input', updateBudgetSummary);
        removeBtn.addEventListener('click', function() {
            if (budgetLines.children.length > 1) {
                lineDiv.remove();
                updateBudgetSummary();
            }
        });
    }
    
    function updateBudgetSummary() {
        let totalAllocated = 0;
        
        document.querySelectorAll('.budget-line').forEach(line => {
            const amount = parseFloat(line.querySelector('.line-amount').value) || 0;
            totalAllocated += amount;
        });
        
        const totalBudget = parseFloat(totalBudgetInput.value) || 0;
        const remaining = totalBudget - totalAllocated;
        
        totalAllocatedEl.textContent = '₱' + totalAllocated.toFixed(2);
        remainingBudgetEl.textContent = '₱' + remaining.toFixed(2);
        
        if (Math.abs(remaining) < 0.01) {
            remainingBudgetEl.className = 'font-semibold text-green-600 dark:text-green-400';
        } else if (remaining < 0) {
            remainingBudgetEl.className = 'font-semibold text-red-600 dark:text-red-400';
        } else {
            remainingBudgetEl.className = 'font-semibold text-gray-600 dark:text-gray-400';
        }
    }
    
    // Initialize summary
    updateBudgetSummary();
});

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const fiscalYear = document.getElementById('fiscalYearFilter').value;
    const department = document.getElementById('departmentFilter').value;
    
    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (fiscalYear) params.set('fiscal_year', fiscalYear);
    if (department) params.set('department', department);
    
    window.location.href = '?' + params.toString();
}

function viewBudget(budgetId) {
    fetch(`../../api/financial/get_budget.php?id=${budgetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const budget = data.budget;
                const details = document.getElementById('budgetDetails');
                
                details.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                            <p class="text-sm text-gray-900 dark:text-white">${budget.department_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fiscal Year</label>
                            <p class="text-sm text-gray-900 dark:text-white">${budget.fiscal_year}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Budget</label>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">₱${parseFloat(budget.total_budget).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(budget.status)}">
                                ${budget.status}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created By</label>
                        <p class="text-sm text-gray-900 dark:text-white">${budget.employee_fname} ${budget.employee_lname}</p>
                    </div>
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Budget Line Items</h4>
                        <div id="budgetLineItems">
                            <!-- Line items will be loaded here -->
                        </div>
                    </div>
                `;
                
                // Load line items
                if (data.line_items && data.line_items.length > 0) {
                    const lineItemsContainer = document.getElementById('budgetLineItems');
                    let lineItemsHTML = '<div class="space-y-2">';
                    data.line_items.forEach(line => {
                        lineItemsHTML += `
                            <div class="flex justify-between items-center p-2 border border-gray-200 dark:border-gray-600 rounded">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${line.account_code} - ${line.account_name}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">${line.notes || ''}</p>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">₱${parseFloat(line.total_allocation || 0).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    lineItemsHTML += '</div>';
                    lineItemsContainer.innerHTML = lineItemsHTML;
                }
                
                document.getElementById('viewBudgetModal').classList.remove('hidden');
            } else {
                alert('Error loading budget details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading budget details');
        });
}

function getStatusBadgeClass(status) {
    const classes = {
        'Draft': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        'Submitted': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'Approved': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Rejected': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

function closeViewModal() {
    document.getElementById('viewBudgetModal').classList.add('hidden');
}

function updateStatus(budgetId, status) {
    if (confirm(`Are you sure you want to ${status.toLowerCase()} this budget?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="budget_id" value="${budgetId}">
            <input type="hidden" name="status" value="${status}">
            <input type="hidden" name="update_status" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('viewBudgetModal');
    if (event.target === modal) {
        closeViewModal();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
