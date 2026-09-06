<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('ar.manage');

// Handle form submissions before any output (so redirect works)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_invoice'])) {
        try {
            $query = "INSERT INTO ar_invoices 
                     (invoice_number, patient_id, hmo_id, corporate_client_id, invoice_date, due_date, 
                      total_amount, balance_amount, service_type, created_by) 
                     VALUES (:invoice_number, :patient_id, :hmo_id, :corporate_client_id, :invoice_date, :due_date,
                             :total_amount, :balance_amount, :service_type, :created_by)";

            $stmt = $db->prepare($query);
            $stmt->execute([
                'invoice_number' => generateARInvoiceNumber(),
                'patient_id' => $_POST['patient_id'] ?: null,
                'hmo_id' => $_POST['hmo_id'] ?: null,
                'corporate_client_id' => $_POST['corporate_client_id'] ?: null,
                'invoice_date' => $_POST['invoice_date'],
                'due_date' => $_POST['due_date'],
                'total_amount' => $_POST['total_amount'],
                'balance_amount' => $_POST['total_amount'], // Initially balance equals total
                'service_type' => $_POST['service_type'],
                'created_by' => $_SESSION['employee_id']
            ]);

            $_SESSION['success'] = "AR Invoice created successfully.";
            header("Location: admin-accounts_receivable.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating invoice: " . $e->getMessage();
        }
    } elseif (isset($_POST['apply_payment'])) {
        try {
            $invoice_id = $_POST['invoice_id'];
            $payment_amount = floatval($_POST['payment_amount']);

            $balance_query = "SELECT balance_amount, total_amount FROM ar_invoices WHERE id = :id";
            $balance_stmt = $db->prepare($balance_query);
            $balance_stmt->execute(['id' => $invoice_id]);
            $invoice = $balance_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception("Invoice not found");
            }

            if ($payment_amount > $invoice['balance_amount']) {
                throw new Exception("Payment amount cannot exceed balance amount");
            }

            $new_balance = $invoice['balance_amount'] - $payment_amount;
            $new_status = $new_balance > 0 ? 'Partially Paid' : 'Paid';

            $update_query = "UPDATE ar_invoices SET balance_amount = :balance, status = :status WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([
                'balance' => $new_balance,
                'status' => $new_status,
                'id' => $invoice_id
            ]);

            $payment_query = "INSERT INTO collection_payments 
                             (or_number, patient_id, hmo_id, payment_date, payment_amount, 
                              payment_method, reference_number, collected_by) 
                             VALUES (:or_number, :patient_id, :hmo_id, :payment_date, :payment_amount,
                                     :payment_method, :reference_number, :collected_by)";

            $payment_stmt = $db->prepare($payment_query);
            $payment_stmt->execute([
                'or_number' => generateORNumber(),
                'patient_id' => $_POST['patient_id'] ?: null,
                'hmo_id' => $_POST['hmo_id'] ?: null,
                'payment_date' => $_POST['payment_date'],
                'payment_amount' => $payment_amount,
                'payment_method' => $_POST['payment_method'],
                'reference_number' => $_POST['reference_number'],
                'collected_by' => $_SESSION['employee_id']
            ]);

            $_SESSION['success'] = "Payment applied successfully. New balance: ₱" . number_format($new_balance, 2);
        } catch (Exception $e) {
            $_SESSION['error'] = "Error applying payment: " . $e->getMessage();
        }
    }
}

$page_title = 'Accounts Receivable';
include __DIR__ . '/../../includes/header.php';

// Get AR invoices with filters
$status_filter = $_GET['status'] ?? 'all';
$service_filter = $_GET['service_type'] ?? '';

try {
    $query = "SELECT ar.*, 
                     p.first_name as patient_fname, p.last_name as patient_lname,
                     h.hmo_name,
                     NULL as company_name
              FROM ar_invoices ar
              LEFT JOIN patients p ON ar.patient_id = p.id
              LEFT JOIN hmos h ON ar.hmo_id = h.id
              WHERE 1=1";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND ar.status = :status";
        $params['status'] = $status_filter;
    }
    
    if (!empty($service_filter)) {
        $query .= " AND ar.service_type = :service_type";
        $params['service_type'] = $service_filter;
    }
    
    $query .= " ORDER BY ar.invoice_date DESC, ar.due_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoices = [];
}

// Get AR summary
try {
    $summary_query = "SELECT 
                        status,
                        COUNT(*) as count,
                        SUM(total_amount) as total_amount,
                        SUM(balance_amount) as balance_amount
                      FROM ar_invoices 
                      GROUP BY status";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $ar_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ar_summary = [];
}

// Get service types for filter
$service_types = ['OPD', 'IPD', 'ER', 'Lab', 'Pharmacy', 'Other'];

// Get patients for billing selection
try {
    $patients_query = "SELECT id, first_name, last_name
                       FROM patients
                       ORDER BY last_name ASC, first_name ASC";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
}

// Get HMOs for billing selection
try {
    $hmos_query = "SELECT id, hmo_name
                   FROM hmos
                   ORDER BY hmo_name ASC";
    $hmos_stmt = $db->prepare($hmos_query);
    $hmos_stmt->execute();
    $hmos = $hmos_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hmos = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Accounts Receivable
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage patient invoices and collections
    </p>
</div>

<!-- AR Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $status_totals = [];
    foreach ($ar_summary as $summary) {
        $status_totals[$summary['status']] = [
            'count' => $summary['count'],
            'total' => $summary['total_amount'],
            'balance' => $summary['balance_amount']
        ];
    }
    
    $summary_items = [
        'Pending' => [
            'amount' => $status_totals['Pending']['balance'] ?? 0,
            'count' => $status_totals['Pending']['count'] ?? 0,
            'color' => 'yellow',
            'icon' => 'clock'
        ],
        'Partially Paid' => [
            'amount' => $status_totals['Partially Paid']['balance'] ?? 0,
            'count' => $status_totals['Partially Paid']['count'] ?? 0,
            'color' => 'blue',
            'icon' => 'coins'
        ],
        'Paid' => [
            'amount' => $status_totals['Paid']['total'] ?? 0,
            'count' => $status_totals['Paid']['count'] ?? 0,
            'color' => 'green',
            'icon' => 'check-circle'
        ],
        'Total AR' => [
            'amount' => array_sum(array_column($ar_summary, 'balance_amount')),
            'count' => array_sum(array_column($ar_summary, 'count')),
            'color' => 'purple',
            'icon' => 'bar-chart-2'
        ]
    ];
    
    foreach ($summary_items as $label => $data):
        $color_classes = [
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
        ];
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo $label; ?></p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($data['amount'], 2); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $data['count']; ?> invoices</p>
            </div>
            <div class="text-2xl text-gray-400 dark:text-gray-500">
                <svg data-lucide="<?php echo $data['icon']; ?>" class="w-8 h-8"></svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Add Invoice Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create AR Invoice</h3>
            
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Billing Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="setBillingType('patient')" title="Patient" class="billing-type-btn flex justify-center items-center px-3 py-2 text-xs border rounded-md bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-700">
                                <svg data-lucide="user" class="w-5 h-5"></svg>
                            </button>
                            <button type="button" onclick="setBillingType('hmo')" title="HMO" class="billing-type-btn flex justify-center items-center px-3 py-2 text-xs border rounded-md bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                                <svg data-lucide="building" class="w-5 h-5"></svg>
                            </button>
                            <button type="button" onclick="setBillingType('corporate')" title="Corporate" class="billing-type-btn flex justify-center items-center px-3 py-2 text-xs border rounded-md bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                                <svg data-lucide="briefcase" class="w-5 h-5"></svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Patient Field -->
                    <div id="patientField" class="hidden">
                        <label for="patient_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Patient</label>
                        <select id="patient_id" name="patient_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo (int)$patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- HMO Field -->
                    <div id="hmoField" class="hidden">
                        <label for="hmo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">HMO Partner</label>
                        <select id="hmo_id" name="hmo_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select HMO</option>
                            <?php foreach ($hmos as $hmo): ?>
                                <option value="<?php echo (int)$hmo['id']; ?>">
                                    <?php echo htmlspecialchars($hmo['hmo_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Corporate Field -->
                    <div id="corporateField" class="hidden">
                        <label for="corporate_client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Corporate Client</label>
                        <select id="corporate_client_id" name="corporate_client_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Corporate Client</option>
                            <!-- Corporate clients would be loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="invoice_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice Date *</label>
                            <input type="date" id="invoice_date" name="invoice_date" required
                                   value="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date *</label>
                            <input type="date" id="due_date" name="due_date" required
                                   value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <div>
                        <label for="service_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service Type *</label>
                        <select id="service_type" name="service_type" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Service Type</option>
                            <?php foreach ($service_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="total_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total Amount *</label>
                        <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Service description or notes"></textarea>
                    </div>
                    
                    <div>
                        <button type="submit" name="add_invoice" title="Create Invoice" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <svg data-lucide="plus" class="w-5 h-5"></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-2">
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-collection.php" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="landmark" class="w-5 h-5 mr-3 text-green-600"></svg>
                    Collection Center
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ar_reports.php?report=aging" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="clock" class="w-5 h-5 mr-3 text-blue-600"></svg>
                    AR Aging Report
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ar_reports.php?report=collection" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="file-text" class="w-5 h-5 mr-3 text-purple-600"></svg>
                    Collection Report
                </a>
            </div>
        </div>
    </div>
    
    <!-- Invoices List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">AR Invoices</h3>
                
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <!-- Status Filter -->
                    <select id="statusFilter" onchange="applyFilters()" 
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Partially Paid" <?php echo $status_filter === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Overdue" <?php echo $status_filter === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                    
                    <!-- Service Type Filter -->
                    <select id="serviceFilter" onchange="applyFilters()"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">All Services</option>
                        <?php foreach ($service_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $service_filter === $type ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoice #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No invoices found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): 
                                $due_date = new DateTime($invoice['due_date']);
                                $today = new DateTime();
                                $is_overdue = $due_date < $today && $invoice['status'] !== 'Paid';
                                
                                // Determine client name
                                $client_name = 'N/A';
                                if ($invoice['patient_fname']) {
                                    $client_name = $invoice['patient_fname'] . ' ' . $invoice['patient_lname'];
                                } elseif ($invoice['hmo_name']) {
                                    $client_name = $invoice['hmo_name'] . ' (HMO)';
                                } elseif ($invoice['company_name']) {
                                    $client_name = $invoice['company_name'] . ' (Corporate)';
                                }
                            ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($client_name); ?>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo $invoice['service_type']; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white <?php echo $is_overdue ? 'text-red-600 font-semibold' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?>
                                    </div>
                                    <?php if ($is_overdue): ?>
                                        <div class="text-xs text-red-500">Overdue</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                    ₱<?php echo number_format($invoice['total_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-right 
                                    <?php echo $invoice['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                    ₱<?php echo number_format($invoice['balance_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $status_badges = [
                                        'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'Partially Paid' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Paid' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Overdue' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                        'Written Off' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                    ];
                                    $display_status = $invoice['status'];
                                    if ($is_overdue && $invoice['status'] === 'Pending') {
                                        $display_status = 'Overdue';
                                    }
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_badges[$display_status] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $display_status; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" 
                                            class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-3">
                                        View
                                    </button>
                                    <?php if ($invoice['balance_amount'] > 0): ?>
                                        <button onclick="applyPayment(<?php echo $invoice['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                            Pay
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
        
        <!-- AR Aging Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">AR Aging Summary</h3>
            <?php
            $aging_data = calculateARAging($db);
            $aging_summary = [];
            
            foreach ($aging_data as $invoice) {
                $bucket = $invoice['aging_bucket'];
                if (!isset($aging_summary[$bucket])) {
                    $aging_summary[$bucket] = 0;
                }
                $aging_summary[$bucket] += $invoice['balance_amount'];
            }
            
            $aging_buckets = [
                'Current' => ['color' => 'green', 'description' => 'Not due yet'],
                '1-30' => ['color' => 'blue', 'description' => '1-30 days overdue'],
                '31-60' => ['color' => 'yellow', 'description' => '31-60 days overdue'],
                '61-90' => ['color' => 'orange', 'description' => '61-90 days overdue'],
                'Over 90' => ['color' => 'red', 'description' => 'Over 90 days overdue']
            ];
            ?>
            
            <div class="space-y-3">
                <?php foreach ($aging_buckets as $bucket => $info): 
                    $amount = $aging_summary[$bucket] ?? 0;
                    $color_classes = [
                        'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                        'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                    ];
                ?>
                <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg">
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color_classes[$info['color']]; ?> mr-3">
                            <?php echo $bucket; ?>
                        </span>
                        <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo $info['description']; ?></span>
                    </div>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        ₱<?php echo number_format($amount, 2); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Invoice Modal -->
<div id="viewInvoiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invoice Details</h3>
            <div id="invoiceDetails" class="space-y-3">
                <!-- Invoice details will be loaded here via AJAX -->
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button onclick="closeViewModal()" title="Close" class="p-2 text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                    <svg data-lucide="x" class="w-5 h-5"></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Apply Payment Modal -->
<div id="applyPaymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Apply Payment</h3>
            
            <form method="POST" id="paymentForm">
                <input type="hidden" name="invoice_id" id="payment_invoice_id">
                <input type="hidden" name="patient_id" id="payment_patient_id">
                <input type="hidden" name="hmo_id" id="payment_hmo_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice</label>
                        <p id="payment_invoice_info" class="text-sm text-gray-900 dark:text-white"></p>
                    </div>
                    
                    <div>
                        <label for="payment_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Amount *</label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <p id="payment_balance" class="text-xs text-gray-500 dark:text-gray-400 mt-1"></p>
                    </div>
                    
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Date *</label>
                        <input type="date" id="payment_date" name="payment_date" required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Credit/Debit Card</option>
                            <option value="Check">Check</option>
                            <option value="Online">Online Transfer</option>
                            <option value="HMO">HMO</option>
                            <option value="PhilHealth">PhilHealth</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Check #, Transaction ID, etc.">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closePaymentModal()" title="Cancel" class="p-2 text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="x" class="w-5 h-5"></svg>
                    </button>
                    <button type="submit" name="apply_payment" title="Apply Payment" class="p-2 text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700">
                        <svg data-lucide="check" class="w-5 h-5"></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentBillingType = '';

function setBillingType(type) {
    currentBillingType = type;
    
    // Reset all fields
    document.getElementById('patientField').classList.add('hidden');
    document.getElementById('hmoField').classList.add('hidden');
    document.getElementById('corporateField').classList.add('hidden');
    
    // Reset all buttons
    document.querySelectorAll('.billing-type-btn').forEach(btn => {
        btn.classList.remove('bg-blue-100', 'text-blue-700', 'border-blue-300', 
                           'dark:bg-blue-900', 'dark:text-blue-300', 'dark:border-blue-700');
        btn.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-300',
                        'dark:bg-gray-700', 'dark:text-gray-300', 'dark:border-gray-600');
    });
    
    // Activate selected button
    event.target.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-300',
                                'dark:bg-gray-700', 'dark:text-gray-300', 'dark:border-gray-600');
    event.target.classList.add('bg-blue-100', 'text-blue-700', 'border-blue-300',
                             'dark:bg-blue-900', 'dark:text-blue-300', 'dark:border-blue-700');
    
    // Show relevant field
    if (type === 'patient') {
        document.getElementById('patientField').classList.remove('hidden');
    } else if (type === 'hmo') {
        document.getElementById('hmoField').classList.remove('hidden');
    } else if (type === 'corporate') {
        document.getElementById('corporateField').classList.remove('hidden');
    }
}

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const service = document.getElementById('serviceFilter').value;
    
    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (service) params.set('service_type', service);
    
    window.location.href = '?' + params.toString();
}

function viewInvoice(invoiceId) {
    fetch(`../../api/financial/get_ar_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const invoice = data.invoice;
                const details = document.getElementById('invoiceDetails');
                
                // Determine client name
                let clientName = 'N/A';
                if (invoice.patient_fname) {
                    clientName = invoice.patient_fname + ' ' + invoice.patient_lname;
                } else if (invoice.hmo_name) {
                    clientName = invoice.hmo_name + ' (HMO)';
                } else if (invoice.company_name) {
                    clientName = invoice.company_name + ' (Corporate)';
                }
                
                details.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Number</label>
                            <p class="text-sm text-gray-900 dark:text-white">${invoice.invoice_number}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Client</label>
                            <p class="text-sm text-gray-900 dark:text-white">${clientName}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Date</label>
                            <p class="text-sm text-gray-900 dark:text-white">${new Date(invoice.invoice_date).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Due Date</label>
                            <p class="text-sm text-gray-900 dark:text-white">${new Date(invoice.due_date).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Amount</label>
                            <p class="text-sm text-gray-900 dark:text-white">₱${parseFloat(invoice.total_amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Balance Amount</label>
                            <p class="text-sm font-semibold ${invoice.balance_amount > 0 ? 'text-red-600' : 'text-green-600'}">₱${parseFloat(invoice.balance_amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Service Type</label>
                            <p class="text-sm text-gray-900 dark:text-white">${invoice.service_type}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <p class="text-sm text-gray-900 dark:text-white">${invoice.description || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(invoice.status)}">
                            ${invoice.status}
                        </span>
                    </div>
                `;
                
                document.getElementById('viewInvoiceModal').classList.remove('hidden');
            } else {
                alert('Error loading invoice details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading invoice details');
        });
}

function applyPayment(invoiceId) {
    fetch(`../../api/financial/get_ar_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const invoice = data.invoice;
                
                document.getElementById('payment_invoice_id').value = invoice.id;
                document.getElementById('payment_patient_id').value = invoice.patient_id || '';
                document.getElementById('payment_hmo_id').value = invoice.hmo_id || '';
                
                // Determine client name for display
                let clientName = 'N/A';
                if (invoice.patient_fname) {
                    clientName = invoice.patient_fname + ' ' + invoice.patient_lname;
                } else if (invoice.hmo_name) {
                    clientName = invoice.hmo_name + ' (HMO)';
                } else if (invoice.company_name) {
                    clientName = invoice.company_name + ' (Corporate)';
                }
                
                document.getElementById('payment_invoice_info').textContent = 
                    `${invoice.invoice_number} - ${clientName} - ₱${parseFloat(invoice.balance_amount).toFixed(2)} balance`;
                
                document.getElementById('payment_balance').textContent = 
                    `Current balance: ₱${parseFloat(invoice.balance_amount).toFixed(2)}`;
                
                document.getElementById('payment_amount').max = invoice.balance_amount;
                document.getElementById('payment_amount').value = invoice.balance_amount;
                
                document.getElementById('applyPaymentModal').classList.remove('hidden');
            } else {
                alert('Error loading invoice details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading invoice details');
        });
}

function getStatusBadgeClass(status) {
    const classes = {
        'Pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'Partially Paid': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'Paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Overdue': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'Written Off': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

function closeViewModal() {
    document.getElementById('viewInvoiceModal').classList.add('hidden');
}

function closePaymentModal() {
    document.getElementById('applyPaymentModal').classList.add('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const viewModal = document.getElementById('viewInvoiceModal');
    const paymentModal = document.getElementById('applyPaymentModal');
    
    if (event.target === viewModal) {
        closeViewModal();
    }
    if (event.target === paymentModal) {
        closePaymentModal();
    }
}

// Initialize billing type to patient
document.addEventListener('DOMContentLoaded', function() {
    setBillingType('patient');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
