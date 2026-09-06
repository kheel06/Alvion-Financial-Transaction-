<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('disbursement.manage');

// Handle form submissions before any output (so redirects work)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_request'])) {
        try {
            $query = "INSERT INTO disbursement_requests 
                     (request_number, department_id, payee_name, payee_type, amount, purpose, 
                      requested_by, status, current_approver) 
                     VALUES (:request_number, :department_id, :payee_name, :payee_type, :amount, :purpose,
                             :requested_by, 'Pending', :current_approver)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                'request_number' => generateDisbursementNumber(),
                'department_id' => $_POST['department_id'],
                'payee_name' => $_POST['payee_name'],
                'payee_type' => $_POST['payee_type'],
                'amount' => $_POST['amount'],
                'purpose' => $_POST['purpose'],
                'requested_by' => $_SESSION['employee_id'],
                'current_approver' => getNextApprover('dept_head')
            ]);
            
            $_SESSION['success'] = "Disbursement request created successfully.";
            header("Location: admin-disbursement.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating disbursement request: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        try {
            $request_id = $_POST['request_id'];
            $new_status = $_POST['status'];
            $current_user = $_SESSION['employee_id'];
            
            if (!canApproveDisbursement($current_user, $request_id)) {
                $_SESSION['error'] = "You don't have permission to approve this request.";
            } else {
                $query = "UPDATE disbursement_requests 
                         SET status = :status, 
                             current_approver = :next_approver,
                             approved_by = CASE WHEN :status = 'Approved' THEN :approved_by ELSE approved_by END,
                             approved_at = CASE WHEN :status = 'Approved' THEN NOW() ELSE approved_at END
                         WHERE id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'status' => $new_status,
                    'next_approver' => getNextApprover($new_status),
                    'approved_by' => $current_user,
                    'id' => $request_id
                ]);
                
                $_SESSION['success'] = "Disbursement request updated successfully.";
            }
            header("Location: admin-disbursement.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating request: " . $e->getMessage();
        }
    }
}

$page_title = 'Disbursement Management';
include __DIR__ . '/../../includes/header.php';

// Get disbursement requests with filters
$status_filter = $_GET['status'] ?? 'all';
$department_filter = $_GET['department'] ?? '';

try {
    $query = "SELECT dr.*, d.department_name, da.employee_fname, da.employee_lname,
                     da2.employee_fname as approver_fname, da2.employee_lname as approver_lname
              FROM disbursement_requests dr
              INNER JOIN departments d ON dr.department_id = d.id
              INNER JOIN department_accounts da ON dr.requested_by = da.employee_id
              LEFT JOIN department_accounts da2 ON dr.current_approver = da2.employee_id
              WHERE 1=1";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND dr.status = :status";
        $params['status'] = $status_filter;
    }
    
    if (!empty($department_filter)) {
        $query .= " AND dr.department_id = :department_id";
        $params['department_id'] = $department_filter;
    }
    
    // Show only requests user can view/approve
    if (!hasPermission('disbursement.approve_all')) {
        $query .= " AND (dr.requested_by = :current_user OR dr.current_approver = :current_user)";
        $params['current_user'] = $_SESSION['employee_id'];
    }
    
    $query .= " ORDER BY dr.requested_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
}

// Get departments for dropdown
try {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Calculate disbursement summary
try {
    $summary_query = "SELECT 
                        status,
                        COUNT(*) as count,
                        SUM(amount) as total_amount
                      FROM disbursement_requests 
                      GROUP BY status";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $disb_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $disb_summary = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Disbursement Management
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage payment requests and approval workflow
    </p>
</div>

<!-- Disbursement Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $status_totals = [];
    foreach ($disb_summary as $summary) {
        $status_totals[$summary['status']] = [
            'count' => $summary['count'],
            'amount' => $summary['total_amount']
        ];
    }
    
    $summary_items = [
        'Pending' => [
            'count' => $status_totals['Pending']['count'] ?? 0,
            'amount' => $status_totals['Pending']['amount'] ?? 0,
            'color' => 'yellow',
            'icon' => 'clock'
        ],
        'Approved' => [
            'count' => $status_totals['Approved']['count'] ?? 0,
            'amount' => $status_totals['Approved']['amount'] ?? 0,
            'color' => 'blue',
            'icon' => 'check-circle'
        ],
        'Paid' => [
            'count' => $status_totals['Paid']['count'] ?? 0,
            'amount' => $status_totals['Paid']['amount'] ?? 0,
            'color' => 'green',
            'icon' => 'coins'
        ],
        'Total' => [
            'count' => array_sum(array_column($disb_summary, 'count')),
            'amount' => array_sum(array_column($disb_summary, 'total_amount')),
            'color' => 'gray',
            'icon' => 'bar-chart-2'
        ]
    ];
    
    foreach ($summary_items as $label => $data):
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo $label; ?></p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($data['amount'], 2); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $data['count']; ?> requests</p>
            </div>
            <div class="text-gray-400 dark:text-gray-500">
                <svg data-lucide="<?php echo $data['icon']; ?>" class="w-8 h-8"></svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Create Request Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create Disbursement Request</h3>
            
            <form method="POST">
                <div class="space-y-4">
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
                        <label for="payee_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payee Name *</label>
                        <input type="text" id="payee_name" name="payee_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Name of payee">
                    </div>
                    
                    <div>
                        <label for="payee_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payee Type *</label>
                        <select id="payee_type" name="payee_type" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Type</option>
                            <option value="Supplier">Supplier</option>
                            <option value="Employee">Employee</option>
                            <option value="Patient">Patient</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Purpose *</label>
                        <textarea id="purpose" name="purpose" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Detailed purpose of the disbursement"></textarea>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-md">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm text-blue-700 dark:text-blue-300">
                                Request will be routed for approval
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" name="create_request" class="w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                            Submit Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-2">
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-disbursement_reports.php" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Disbursement Reports
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-disbursement_reports.php?report=approval_pending" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 mr-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Pending Approvals
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-disbursement_reports.php?report=cash_flow" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Cash Flow Analysis
                </a>
            </div>
        </div>
    </div>
    
    <!-- Requests List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Disbursement Requests</h3>
                
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <!-- Status Filter -->
                    <select id="statusFilter" onchange="applyFilters()" 
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Draft" <?php echo $status_filter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Request #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payee</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Current Approver</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No disbursement requests found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($request['request_number']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo date('M j, Y', strtotime($request['requested_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($request['department_name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <div><?php echo htmlspecialchars($request['payee_name']); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($request['payee_type']); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                    ₱<?php echo number_format($request['amount'], 2); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $status_badges = [
                                        'Draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                        'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'Approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Paid' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_badges[$request['status']]; ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <?php if ($request['approver_fname']): ?>
                                        <?php echo htmlspecialchars($request['approver_fname'] . ' ' . $request['approver_lname']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewRequest(<?php echo $request['id']; ?>)" title="View Request"
                                            class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-3">
                                        <svg data-lucide="eye" class="w-5 h-5"></svg>
                                    </button>
                                    <?php if (canApproveDisbursement($_SESSION['employee_id'], $request['id'])): ?>
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <button onclick="approveRequest(<?php echo $request['id']; ?>)" title="Approve Request"
                                                    class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-2">
                                                <svg data-lucide="check-circle" class="w-5 h-5"></svg>
                                            </button>
                                            <button onclick="rejectRequest(<?php echo $request['id']; ?>)" title="Reject Request"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                <svg data-lucide="x-circle" class="w-5 h-5"></svg>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Approval Workflow Guide -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Approval Workflow</h3>
            <div class="flex items-center justify-between">
                <?php
                $workflow_steps = [
                    ['step' => 1, 'label' => 'Department Head', 'color' => 'blue'],
                    ['step' => 2, 'label' => 'Finance Manager', 'color' => 'purple'],
                    ['step' => 3, 'label' => 'Hospital Director', 'color' => 'green']
                ];
                
                foreach ($workflow_steps as $index => $step):
                    $color_classes = [
                        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                        'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    ];
                ?>
                <div class="flex flex-col items-center text-center flex-1">
                    <div class="w-10 h-10 rounded-full <?php echo $color_classes[$step['color']]; ?> flex items-center justify-center text-sm font-semibold mb-2">
                        <?php echo $step['step']; ?>
                    </div>
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400"><?php echo $step['label']; ?></span>
                </div>
                <?php if ($index < count($workflow_steps) - 1): ?>
                <div class="flex-1 h-1 bg-gray-200 dark:bg-gray-700 mx-2"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Request Modal -->
<div id="viewRequestModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Disbursement Request Details</h3>
            <div id="requestDetails" class="space-y-3">
                <!-- Request details will be loaded here via AJAX -->
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
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const department = document.getElementById('departmentFilter').value;
    
    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (department) params.set('department', department);
    
    window.location.href = '?' + params.toString();
}

function viewRequest(requestId) {
    fetch(`../../api/financial/get_disbursement_request.php?id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const request = data.request;
                const details = document.getElementById('requestDetails');
                
                details.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Request Number</label>
                            <p class="text-sm text-gray-900 dark:text-white">${request.request_number}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                            <p class="text-sm text-gray-900 dark:text-white">${request.department_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payee Name</label>
                            <p class="text-sm text-gray-900 dark:text-white">${request.payee_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payee Type</label>
                            <p class="text-sm text-gray-900 dark:text-white">${request.payee_type}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Amount</label>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">₱${parseFloat(request.amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(request.status)}">
                                ${request.status}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Purpose</label>
                        <p class="text-sm text-gray-900 dark:text-white mt-1 p-3 bg-gray-50 dark:bg-gray-900 rounded-md">${request.purpose}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Requested By</label>
                            <p class="text-sm text-gray-900 dark:text-white">${request.employee_fname} ${request.employee_lname}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Requested Date</label>
                            <p class="text-sm text-gray-900 dark:text-white">${new Date(request.requested_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                `;
                
                document.getElementById('viewRequestModal').classList.remove('hidden');
            } else {
                alert('Error loading request details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading request details');
        });
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

function closeViewModal() {
    document.getElementById('viewRequestModal').classList.add('hidden');
}

function approveRequest(requestId) {
    if (confirm('Are you sure you want to approve this disbursement request?')) {
        updateRequestStatus(requestId, 'Approved');
    }
}

function rejectRequest(requestId) {
    if (confirm('Are you sure you want to reject this disbursement request?')) {
        updateRequestStatus(requestId, 'Rejected');
    }
}

function updateRequestStatus(requestId, status) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="request_id" value="${requestId}">
        <input type="hidden" name="status" value="${status}">
        <input type="hidden" name="update_status" value="1">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('viewRequestModal');
    if (event.target === modal) {
        closeViewModal();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>