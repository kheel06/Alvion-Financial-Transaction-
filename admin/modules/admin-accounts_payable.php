<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('ap.manage');

// Handle form submissions before any output (so redirect works)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_invoice'])) {
        try {
            $invoice_amount = floatval($_POST['invoice_amount']);
            $vat_amount = calculateVAT($invoice_amount);
            $ewt_amount = calculateEWT($invoice_amount);
            $net_amount = $invoice_amount - $ewt_amount;

            $query = "INSERT INTO ap_invoices 
                     (invoice_number, supplier_id, invoice_date, due_date, invoice_amount, 
                      vat_amount, ewt_amount, net_amount, description, payment_terms, created_by) 
                     VALUES (:invoice_number, :supplier_id, :invoice_date, :due_date, :invoice_amount,
                             :vat_amount, :ewt_amount, :net_amount, :description, :payment_terms, :created_by)";

            $stmt = $db->prepare($query);
            $stmt->execute([
                'invoice_number' => generateAPInvoiceNumber(),
                'supplier_id' => $_POST['supplier_id'],
                'invoice_date' => $_POST['invoice_date'],
                'due_date' => $_POST['due_date'],
                'invoice_amount' => $invoice_amount,
                'vat_amount' => $vat_amount,
                'ewt_amount' => $ewt_amount,
                'net_amount' => $net_amount,
                'description' => $_POST['description'],
                'payment_terms' => $_POST['payment_terms'],
                'created_by' => $_SESSION['employee_id']
            ]);

            $_SESSION['success'] = "AP Invoice added successfully.";
            header("Location: admin-accounts_payable.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding invoice: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        try {
            $query = "UPDATE ap_invoices SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'status' => $_POST['status'],
                'id' => $_POST['invoice_id']
            ]);
            $_SESSION['success'] = "Invoice status updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating invoice: " . $e->getMessage();
        }
    }
}

$page_title = 'Accounts Payable';
include __DIR__ . '/../../includes/header.php';

// Get AP invoices with filters
$status_filter = $_GET['status'] ?? 'all';
$supplier_filter = $_GET['supplier'] ?? '';

try {
    $query = "SELECT ai.*, s.supplier_name, s.supplier_code 
              FROM ap_invoices ai
              INNER JOIN suppliers s ON ai.supplier_id = s.id
              WHERE 1=1";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND ai.status = :status";
        $params['status'] = $status_filter;
    }
    
    if (!empty($supplier_filter)) {
        $query .= " AND ai.supplier_id = :supplier_id";
        $params['supplier_id'] = $supplier_filter;
    }
    
    $query .= " ORDER BY ai.invoice_date DESC, ai.due_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoices = [];
}

// Get suppliers for dropdown
try {
    $suppliers_query = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY supplier_name";
    $suppliers_stmt = $db->prepare($suppliers_query);
    $suppliers_stmt->execute();
    $suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $suppliers = [];
}

// Calculate AP summary
try {
    $summary_query = "SELECT 
                        status,
                        COUNT(*) as count,
                        SUM(net_amount) as total_amount
                      FROM ap_invoices 
                      GROUP BY status";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $ap_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ap_summary = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Accounts Payable
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage supplier invoices and payments
    </p>
</div>

<!-- AP Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $status_totals = [];
    foreach ($ap_summary as $summary) {
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
            'icon' => 'dollar-sign'
        ],
        'Total' => [
            'count' => array_sum(array_column($ap_summary, 'count')),
            'amount' => array_sum(array_column($ap_summary, 'total_amount')),
            'color' => 'gray',
            'icon' => 'bar-chart-2'
        ]
    ];
    
    foreach ($summary_items as $label => $data):
        $color_classes = [
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
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
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Supplier Invoice</h3>
            
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier *</label>
                        <select id="supplier_id" name="supplier_id" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>">
                                    <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                </option>
                            <?php endforeach; ?>
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
                        <label for="invoice_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice Amount *</label>
                        <input type="number" id="invoice_amount" name="invoice_amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label for="payment_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Terms</label>
                        <input type="text" id="payment_terms" name="payment_terms"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Net 30, COD, etc.">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                  placeholder="Invoice description or notes"></textarea>
                    </div>
                    
                    <!-- Calculated amounts (display only) -->
                    <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-md">
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-gray-600 dark:text-gray-400">VAT (12%):</div>
                            <div id="vat_display" class="text-right font-medium">₱0.00</div>
                            <div class="text-gray-600 dark:text-gray-400">EWT (1%):</div>
                            <div id="ewt_display" class="text-right font-medium">₱0.00</div>
                            <div class="text-gray-600 dark:text-gray-400 font-semibold">Net Amount:</div>
                            <div id="net_display" class="text-right font-semibold text-primary-600">₱0.00</div>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" name="add_invoice" title="Add Invoice" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
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
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-suppliers.php" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="users" class="w-5 h-5 mr-3 text-blue-600"></svg>
                    Manage Suppliers
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ap_reports.php?report=aging" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="clock" class="w-5 h-5 mr-3 text-green-600"></svg>
                    AP Aging Report
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ap_reports.php?report=payment_schedule" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="calendar" class="w-5 h-5 mr-3 text-purple-600"></svg>
                    Payment Schedule
                </a>
            </div>
        </div>
    </div>
    
    <!-- Invoices List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Supplier Invoices</h3>
                
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <!-- Status Filter -->
                    <select id="statusFilter" onchange="applyFilters()" 
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    
                    <!-- Supplier Filter -->
                    <select id="supplierFilter" onchange="applyFilters()"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_filter == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['supplier_name']); ?>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No invoices found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): 
                                $due_date = new DateTime($invoice['due_date']);
                                $today = new DateTime();
                                $is_overdue = $due_date < $today && $invoice['status'] !== 'Paid';
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
                                    <?php echo htmlspecialchars($invoice['supplier_name']); ?>
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
                                    ₱<?php echo number_format($invoice['net_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $status_badges = [
                                        'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'Approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Paid' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_badges[$invoice['status']]; ?>">
                                        <?php echo $invoice['status']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" title="View Invoice"
                                            class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-3">
                                        <svg data-lucide="eye" class="w-5 h-5"></svg>
                                    </button>
                                    <?php if ($invoice['status'] === 'Pending'): ?>
                                        <button onclick="updateStatus(<?php echo $invoice['id']; ?>, 'Approved')" title="Approve Invoice"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                            <svg data-lucide="check" class="w-5 h-5"></svg>
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
        
        <!-- AP Aging Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">AP Aging Summary</h3>
            <?php
            $aging_data = calculateAPAging($db);
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
                <button onclick="closeViewModal()" title="Close" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    <svg data-lucide="x" class="w-4 h-4"></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Calculate and display tax amounts in real-time
document.getElementById('invoice_amount').addEventListener('input', function() {
    const amount = parseFloat(this.value) || 0;
    const vat = amount * <?php echo VAT_RATE; ?>;
    const ewt = amount * <?php echo EWT_RATE; ?>;
    const net = amount - ewt;
    
    document.getElementById('vat_display').textContent = '₱' + vat.toFixed(2);
    document.getElementById('ewt_display').textContent = '₱' + ewt.toFixed(2);
    document.getElementById('net_display').textContent = '₱' + net.toFixed(2);
});

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const supplier = document.getElementById('supplierFilter').value;
    
    const params = new URLSearchParams();
    if (status !== 'all') params.set('status', status);
    if (supplier) params.set('supplier', supplier);
    
    window.location.href = '?' + params.toString();
}

function viewInvoice(invoiceId) {
    fetch(`../../api/financial/get_ap_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const invoice = data.invoice;
                const details = document.getElementById('invoiceDetails');
                
                details.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Number</label>
                            <p class="text-sm text-gray-900 dark:text-white">${invoice.invoice_number}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Supplier</label>
                            <p class="text-sm text-gray-900 dark:text-white">${invoice.supplier_name}</p>
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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Amount</label>
                            <p class="text-sm text-gray-900 dark:text-white">₱${parseFloat(invoice.invoice_amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">VAT Amount</label>
                            <p class="text-sm text-gray-900 dark:text-white">₱${parseFloat(invoice.vat_amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">EWT Amount</label>
                            <p class="text-sm text-gray-900 dark:text-white">₱${parseFloat(invoice.ewt_amount).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Net Amount</label>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">₱${parseFloat(invoice.net_amount).toFixed(2)}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <p class="text-sm text-gray-900 dark:text-white">${invoice.description || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Terms</label>
                        <p class="text-sm text-gray-900 dark:text-white">${invoice.payment_terms || 'N/A'}</p>
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

function getStatusBadgeClass(status) {
    const classes = {
        'Pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'Approved': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'Paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Cancelled': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

function closeViewModal() {
    document.getElementById('viewInvoiceModal').classList.add('hidden');
}

function updateStatus(invoiceId, status) {
    if (confirm(`Are you sure you want to mark this invoice as ${status}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="invoice_id" value="${invoiceId}">
            <input type="hidden" name="status" value="${status}">
            <input type="hidden" name="update_status" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('viewInvoiceModal');
    if (event.target === modal) {
        closeViewModal();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>