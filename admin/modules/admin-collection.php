<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('collection.manage');

$page_title = 'Collection System';
include __DIR__ . '/../../includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        try {
            $allowed_payment_methods = ['Cash', 'Card', 'Check', 'Online', 'HMO', 'PhilHealth'];
            $payment_method = trim($_POST['payment_method'] ?? '');
            $payment_date = trim($_POST['payment_date'] ?? '');
            $payment_amount = round((float)($_POST['payment_amount'] ?? 0), 2);
            $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
            $hmo_id = !empty($_POST['hmo_id']) ? (int)$_POST['hmo_id'] : null;
            $reference_number = trim($_POST['reference_number'] ?? '') ?: null;

            $selected_invoices = [];
            if (isset($_POST['ar_invoices']) && is_array($_POST['ar_invoices'])) {
                $selected_invoices = array_values(array_unique(array_filter(array_map('intval', $_POST['ar_invoices']), function ($v) {
                    return $v > 0;
                })));
            }

            if (!in_array($payment_method, $allowed_payment_methods, true)) {
                throw new Exception('Invalid payment method.');
            }
            if ($payment_date === '') {
                throw new Exception('Payment date is required.');
            }
            if ($payment_amount <= 0) {
                throw new Exception('Payment amount must be greater than zero.');
            }
            if (empty($selected_invoices)) {
                throw new Exception('Select at least one AR invoice to apply this payment.');
            }

            $db->beginTransaction();
            
            // Generate OR Number
            $or_number = generateORNumber();

            $placeholders = implode(',', array_fill(0, count($selected_invoices), '?'));
            $invoice_params = $selected_invoices;
            $invoice_query = "SELECT id, patient_id, hmo_id, balance_amount, due_date
                              FROM ar_invoices
                              WHERE id IN ($placeholders)
                              AND balance_amount > 0
                              ORDER BY due_date ASC, id ASC
                              FOR UPDATE";

            $invoice_stmt = $db->prepare($invoice_query);
            $invoice_stmt->execute($invoice_params);
            $invoices = $invoice_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($invoices) !== count($selected_invoices)) {
                throw new Exception('One or more selected invoices are invalid.');
            }

            $unique_patient_ids = array_values(array_unique(array_filter(array_map(function ($v) {
                return (int)$v;
            }, array_column($invoices, 'patient_id')))));
            $unique_hmo_ids = array_values(array_unique(array_filter(array_map(function ($v) {
                return (int)$v;
            }, array_column($invoices, 'hmo_id')))));

            if (count($unique_patient_ids) > 1) {
                throw new Exception('Selected invoices must belong to a single patient.');
            }
            if (count($unique_hmo_ids) > 1) {
                throw new Exception('Selected invoices must belong to a single HMO.');
            }

            // Use patient and HMO from the selected invoices (source of truth)
            if (count($unique_patient_ids) === 1) {
                $patient_id = (int)$unique_patient_ids[0];
            }
            if (count($unique_hmo_ids) === 1) {
                $hmo_id = (int)$unique_hmo_ids[0];
            }
            if ($payment_method === 'HMO' && !$hmo_id) {
                throw new Exception('HMO is required for HMO payments.');
            }

            $total_balance = 0.0;
            foreach ($invoices as $inv) {
                $total_balance += (float)$inv['balance_amount'];
            }
            if ($payment_amount - $total_balance > 0.00001) {
                throw new Exception('Payment amount exceeds the selected invoice balance.');
            }

            $query = "INSERT INTO collection_payments 
                     (or_number, patient_id, hmo_id, payment_date, payment_amount, 
                      payment_method, reference_number, collected_by) 
                     VALUES (:or_number, :patient_id, :hmo_id, :payment_date, :payment_amount,
                             :payment_method, :reference_number, :collected_by)";

            $stmt = $db->prepare($query);
            $stmt->execute([
                'or_number' => $or_number,
                'patient_id' => $patient_id,
                'hmo_id' => $hmo_id,
                'payment_date' => $payment_date,
                'payment_amount' => $payment_amount,
                'payment_method' => $payment_method,
                'reference_number' => $reference_number,
                'collected_by' => $_SESSION['employee_id']
            ]);

            $payment_id = $db->lastInsertId();

            $remaining = $payment_amount;
            $update_ar_query = "UPDATE ar_invoices
                                SET balance_amount = GREATEST(balance_amount - :apply_amount, 0),
                                    status = CASE
                                        WHEN (balance_amount - :apply_amount) <= 0 THEN 'Paid'
                                        ELSE 'Partially Paid'
                                    END
                                WHERE id = :invoice_id";
            $update_ar_stmt = $db->prepare($update_ar_query);

            foreach ($invoices as $inv) {
                if ($remaining <= 0) {
                    break;
                }
                $balance = (float)$inv['balance_amount'];
                $apply = round(min($remaining, $balance), 2);
                if ($apply <= 0) {
                    continue;
                }
                $update_ar_stmt->execute([
                    'apply_amount' => $apply,
                    'invoice_id' => (int)$inv['id']
                ]);
                $remaining = round($remaining - $apply, 2);
            }

            if ($remaining > 0.00001) {
                throw new Exception('Payment amount could not be fully applied to the selected invoices.');
            }
            
            // Post to General Ledger
            $journal_data = [
                'journal_number' => generateJournalNumber(),
                'entry_date' => $payment_date,
                'reference' => $or_number,
                'description' => "Payment received - OR " . $or_number,
                'total_debit' => $payment_amount,
                'total_credit' => $payment_amount,
                'module_source' => 'Collection',
                'source_id' => $payment_id,
                'created_by' => $_SESSION['employee_id']
            ];
            
            $line_items = [
                [
                    'account_id' => getAccountIdByCode($db, '101'), // Cash on Hand
                    'debit_amount' => $payment_amount,
                    'credit_amount' => 0,
                    'description' => "Payment received"
                ],
                [
                    'account_id' => getAccountIdByCode($db, '103'), // Accounts Receivable
                    'debit_amount' => 0,
                    'credit_amount' => $payment_amount,
                    'description' => "Reduction in AR"
                ]
            ];
            
            postJournalEntry($db, $journal_data, $line_items);
            
            // Update payment as posted
            $update_payment_query = "UPDATE collection_payments 
                                   SET status = 'Posted', posted_by = :posted_by, posted_at = NOW() 
                                   WHERE id = :id";
            $update_payment_stmt = $db->prepare($update_payment_query);
            $update_payment_stmt->execute([
                'posted_by' => $_SESSION['employee_id'],
                'id' => $payment_id
            ]);
            
            $db->commit();
            
            $_SESSION['success'] = "Payment processed successfully. OR Number: " . $or_number;
            header("Location: admin-collection.php");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
        }
    }
}

// Get recent payments
try {
    $payments_query = "SELECT cp.*, 
                              p.first_name as patient_fname, p.last_name as patient_lname,
                              h.hmo_name,
                              da.employee_fname, da.employee_lname
                       FROM collection_payments cp
                       LEFT JOIN patients p ON cp.patient_id = p.id
                       LEFT JOIN hmos h ON cp.hmo_id = h.id
                       LEFT JOIN department_accounts da ON cp.collected_by = da.employee_id
                       ORDER BY cp.created_at DESC 
                       LIMIT 50";
    $payments_stmt = $db->prepare($payments_query);
    $payments_stmt->execute();
    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payments = [];
}

// Get AR invoices for patient selection
try {
    $ar_query = "SELECT ai.*, p.first_name, p.last_name 
                 FROM ar_invoices ai
                 LEFT JOIN patients p ON ai.patient_id = p.id
                 WHERE ai.balance_amount > 0 
                 AND ai.status IN ('Pending', 'Partially Paid')
                 ORDER BY ai.due_date ASC";
    $ar_stmt = $db->prepare($ar_query);
    $ar_stmt->execute();
    $ar_invoices = $ar_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ar_invoices = [];
}

// Get patients for selection
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

// Get HMOs for selection
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

// Get collection summary
try {
    $summary_query = "SELECT 
                        payment_method,
                        COUNT(*) as count,
                        SUM(payment_amount) as total_amount
                      FROM collection_payments 
                      WHERE payment_date = CURDATE()
                      AND status = 'Posted'
                      GROUP BY payment_method";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $collection_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $collection_summary = [];
}

// Calculate daily total
$daily_total = 0;
foreach ($collection_summary as $summary) {
    $daily_total += $summary['total_amount'];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Collection System
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Process payments and manage collections
    </p>
</div>

<!-- Collection Summary -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <!-- Daily Total -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4 md:col-span-2">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Collection</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($daily_total, 2); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo date('F j, Y'); ?></p>
            </div>
            <div class="text-primary-600 dark:text-primary-400">
                <svg data-lucide="coins" class="w-8 h-8"></svg>
            </div>
        </div>
    </div>
    
    <!-- Payment Method Breakdown -->
    <?php
    $method_icons = [
        'Cash' => 'banknote',
        'Card' => 'credit-card',
        'Check' => 'file-check',
        'Online' => 'globe',
        'HMO' => 'building-2',
        'PhilHealth' => 'heart-pulse'
    ];
    
    foreach ($collection_summary as $summary):
        $method = $summary['payment_method'];
        $icon = $method_icons[$method] ?? 'circle-dollar-sign';
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo $method; ?></p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($summary['total_amount'], 2); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $summary['count']; ?> payments</p>
            </div>
            <div class="text-gray-400 dark:text-gray-500">
                <svg data-lucide="<?php echo $icon; ?>" class="w-6 h-6"></svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Process Payment Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Process Payment</h3>
            
            <form method="POST" id="paymentForm">
                <div class="space-y-4">
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                onchange="toggleReferenceNumber()">
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Credit/Debit Card</option>
                            <option value="Check">Check</option>
                            <option value="Online">Online Transfer</option>
                            <option value="HMO">HMO</option>
                            <option value="PhilHealth">PhilHealth</option>
                        </select>
                    </div>
                    
                    <div id="reference_number_field" class="hidden">
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Check no., Transaction ID, etc.">
                    </div>
                    
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Date *</label>
                        <input type="date" id="payment_date" name="payment_date" required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="payment_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Amount *</label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Apply to AR Invoices (Optional)</label>
                        <div class="max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md">
                            <?php if (empty($ar_invoices)): ?>
                                <p class="p-3 text-sm text-gray-500 dark:text-gray-400 text-center">No outstanding invoices</p>
                            <?php else: ?>
                                <?php foreach ($ar_invoices as $invoice): ?>
                                <label class="flex items-center p-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <input type="checkbox" name="ar_invoices[]" value="<?php echo $invoice['id']; ?>" 
                                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?> - 
                                        <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?> - 
                                        ₱<?php echo number_format($invoice['balance_amount'], 2); ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label for="patient_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Patient (Optional)</label>
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
                    
                    <div>
                        <label for="hmo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">HMO (Optional)</label>
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
                    
                    <div>
                        <button type="submit" name="process_payment" title="Process Payment" class="flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                            <svg data-lucide="check-circle" class="w-5 h-5"></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Quick Stats -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Collection Stats</h3>
            <div class="space-y-3">
                <?php
                try {
                    $stats_query = "SELECT 
                                    COUNT(*) as total_payments,
                                    SUM(payment_amount) as total_collected,
                                    AVG(payment_amount) as average_payment
                                   FROM collection_payments 
                                   WHERE status = 'Posted'
                                   AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    $stats_stmt = $db->prepare($stats_query);
                    $stats_stmt->execute();
                    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $stats = ['total_payments' => 0, 'total_collected' => 0, 'average_payment' => 0];
                }
                ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Last 30 Days</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_payments']; ?> payments</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Collected</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">₱<?php echo number_format((float)($stats['total_collected'] ?? 0), 2); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Average Payment</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">₱<?php echo number_format((float)($stats['average_payment'] ?? 0), 2); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Payments -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Payments</h3>
                
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <a href="<?php echo BASE_URL; ?>/admin/modules/admin-or_generation.php" title="Generate OR"
                       class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                        <svg data-lucide="file-plus" class="w-5 h-5"></svg>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/modules/admin-collection_reports.php" title="View Reports"
                       class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="bar-chart-2" class="w-5 h-5"></svg>
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">OR Number</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Method</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Collected By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No payments found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($payment['or_number']); ?>
                                    </div>
                                    <?php if ($payment['patient_fname']): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($payment['patient_fname'] . ' ' . $payment['patient_lname']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                <?php echo getPaymentMethodBadgeClass($payment['payment_method']); ?>">
                                        <?php echo $payment['payment_method']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                    ₱<?php echo number_format($payment['payment_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <?php echo htmlspecialchars($payment['employee_fname'] . ' ' . $payment['employee_lname']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($payment['status'] === 'Posted'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Posted
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- AR Aging Overview -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Accounts Receivable Overview</h3>
            <?php
            $ar_aging = calculateARAging($db);
            $ar_summary = [
                'Current' => 0,
                '1-30' => 0,
                '31-60' => 0,
                '61-90' => 0,
                'Over 90' => 0
            ];
            
            foreach ($ar_aging as $invoice) {
                $ar_summary[$invoice['aging_bucket']] += $invoice['balance_amount'];
            }
            
            $total_ar = array_sum($ar_summary);
            ?>
            
            <div class="space-y-4">
                <!-- Total AR -->
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Outstanding AR</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_ar, 2); ?></span>
                </div>
                
                <!-- Aging Breakdown -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                    <?php
                    $aging_colors = [
                        'Current' => 'bg-green-500',
                        '1-30' => 'bg-blue-500', 
                        '31-60' => 'bg-yellow-500',
                        '61-90' => 'bg-orange-500',
                        'Over 90' => 'bg-red-500'
                    ];
                    
                    foreach ($ar_summary as $bucket => $amount):
                        $percentage = $total_ar > 0 ? ($amount / $total_ar) * 100 : 0;
                    ?>
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo $bucket; ?></div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-1">
                            <div class="<?php echo $aging_colors[$bucket]; ?> h-2 rounded-full" 
                                 style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">₱<?php echo number_format($amount, 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleReferenceNumber() {
    const method = document.getElementById('payment_method').value;
    const referenceField = document.getElementById('reference_number_field');
    
    // Show reference field for non-cash payments
    if (method && method !== 'Cash') {
        referenceField.classList.remove('hidden');
    } else {
        referenceField.classList.add('hidden');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleReferenceNumber();
});
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
