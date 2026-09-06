<?php
/**
 * Billing Report
 * Comprehensive billing and financial reports with export functionality
 */
require_once '../config/config.php';
require_once '../includes/export_helper.php';
requireAuth();
checkRole(['admin', 'billing_staff']);

$page_title = "Billing Report";

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$payment_status = $_GET['payment_status'] ?? 'all';
$payment_method = $_GET['payment_method'] ?? 'all';

try {
    // Check if billing table exists
    $check_billing = $db->query("SHOW TABLES LIKE 'billing'");
    $billing_exists = $check_billing->rowCount() > 0;
    
    if (!$billing_exists) {
        $_SESSION['error'] = "Billing table does not exist. Please run the database/billing.sql file to create it.";
        $bills = [];
        $stats = [
            'total_bills' => 0,
            'total_billed' => 0,
            'total_paid' => 0,
            'total_balance' => 0,
            'paid_amount_total' => 0,
            'pending_amount_total' => 0,
            'partial_amount_total' => 0,
            'overdue_amount_total' => 0
        ];
    } else {
        // Check patients table columns
        try {
            $check_patient_cols = $db->query("SHOW COLUMNS FROM patients");
            $patient_cols = $check_patient_cols->fetchAll(PDO::FETCH_COLUMN);
            $has_first_name = in_array('first_name', $patient_cols);
            $has_hospital_id = in_array('hospital_id', $patient_cols);
            $has_email = in_array('email', $patient_cols);
            $has_contact = in_array('contact_number', $patient_cols);
        } catch (PDOException $e) {
            $has_first_name = false;
            $has_hospital_id = false;
            $has_email = false;
            $has_contact = false;
        }
    // Build query for billing data
    if ($has_first_name) {
        $patient_select = "p.first_name, p.last_name,";
    } else {
        // Fallback: use email/contact if available, otherwise use N/A
        $first_name_fallback = $has_email ? "COALESCE(p.email, 'N/A')" : "'N/A'";
        $last_name_fallback = $has_contact ? "COALESCE(p.contact_number, 'N/A')" : "'N/A'";
        $patient_select = $first_name_fallback . " as first_name, " . $last_name_fallback . " as last_name,";
    }
    
    $hospital_select = $has_hospital_id 
        ? "p.hospital_id," 
        : "'N/A' as hospital_id,";
    
    $query = "SELECT b.*, " . $patient_select . "
             " . $hospital_select . "
             u.first_name as created_by_fname, u.last_name as created_by_lname
             FROM billing b
             INNER JOIN patients p ON b.patient_id = p.id
             LEFT JOIN users u ON b.created_by = u.id
             WHERE b.bill_date BETWEEN :start_date AND :end_date";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if ($payment_status !== 'all') {
        $query .= " AND b.payment_status = :payment_status";
        $params[':payment_status'] = $payment_status;
    }

    if ($payment_method !== 'all') {
        $query .= " AND b.payment_method = :payment_method";
        $params[':payment_method'] = $payment_method;
    }

    $query .= " ORDER BY b.bill_date DESC, b.created_at DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $bills = $stmt->fetchAll();

    // Get statistics
    $stats_query = "SELECT 
        COUNT(*) as total_bills,
        COALESCE(SUM(total_amount), 0) as total_billed,
        COALESCE(SUM(paid_amount), 0) as total_paid,
        COALESCE(SUM(balance_amount), 0) as total_balance,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount_total,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount_total,
        COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN balance_amount ELSE 0 END), 0) as partial_amount_total,
        COALESCE(SUM(CASE WHEN payment_status = 'overdue' THEN balance_amount ELSE 0 END), 0) as overdue_amount_total
        FROM billing
        WHERE bill_date BETWEEN :start_date AND :end_date";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindParam(':start_date', $start_date);
        $stats_stmt->bindParam(':end_date', $end_date);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error generating report: " . $e->getMessage();
    $bills = [];
    $stats = [
        'total_bills' => 0,
        'total_billed' => 0,
        'total_paid' => 0,
        'total_balance' => 0,
        'paid_amount_total' => 0,
        'pending_amount_total' => 0,
        'partial_amount_total' => 0,
        'overdue_amount_total' => 0
    ];
}

// Handle export requests
if (isset($_GET['export']) && ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin') {
    $export_type = $_GET['export'];
    $period_info = formatDate($start_date) . ' to ' . formatDate($end_date);
    
    // CSV requires password verification
    if ($export_type === 'csv') {
        if (isset($_POST['csv_export_password'])) {
            if (!verifyExportPassword($_POST['csv_export_password'])) {
                displayExportPasswordForm($_SERVER['REQUEST_URI'], true);
            }
        } elseif (!requireExportPassword()) {
            displayExportPasswordForm($_SERVER['REQUEST_URI']);
        }
    }
    
    if ($export_type === 'excel' || $export_type === 'csv') {
        $headers = ['Bill Number', 'Date', 'Patient Name', 'Hospital ID', 'Total Amount', 'Paid Amount', 'Balance', 'Payment Status', 'Payment Method', 'Created By'];
        $export_data = [];
        
        foreach ($bills as $bill) {
            $export_data[] = [
                $bill['bill_number'],
                formatDate($bill['bill_date']),
                $bill['first_name'] . ' ' . $bill['last_name'],
                $bill['hospital_id'],
                '₱' . number_format($bill['total_amount'], 2),
                '₱' . number_format($bill['paid_amount'], 2),
                '₱' . number_format($bill['balance_amount'], 2),
                ucfirst($bill['payment_status']),
                ucfirst(str_replace('_', ' ', $bill['payment_method'])),
                $bill['created_by_fname'] ? $bill['created_by_fname'] . ' ' . $bill['created_by_lname'] : 'N/A'
            ];
        }
        
        if ($export_type === 'csv') {
            exportToCSV($export_data, $headers, 'billing_report', 'Billing Report', $period_info);
        } else {
            exportToExcel($export_data, $headers, 'billing_report', 'Billing Report', $period_info);
        }
    } elseif ($export_type === 'pdf') {
        $html = '<div class="header">
            <h1>Billing Report</h1>
            <p>Period: ' . formatDate($start_date) . ' to ' . formatDate($end_date) . '</p>
            <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        </div>';
        
        $html .= '<div style="margin-bottom: 20px;">
            <h2>Summary</h2>
            <p>Total Bills: ' . ($stats['total_bills'] ?? 0) . '</p>
            <p>Total Billed: ₱' . number_format($stats['total_billed'] ?? 0, 2) . '</p>
            <p>Total Paid: ₱' . number_format($stats['total_paid'] ?? 0, 2) . '</p>
            <p>Total Balance: ₱' . number_format($stats['total_balance'] ?? 0, 2) . '</p>
        </div>';
        
        if (count($bills) > 0) {
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Bill Number</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($bills as $bill) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($bill['bill_number']) . '</td>
                    <td>' . formatDate($bill['bill_date']) . '</td>
                    <td>' . htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']) . '</td>
                    <td>₱' . number_format($bill['total_amount'], 2) . '</td>
                    <td>₱' . number_format($bill['paid_amount'], 2) . '</td>
                    <td>₱' . number_format($bill['balance_amount'], 2) . '</td>
                    <td>' . ucfirst($bill['payment_status']) . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>No billing records found for the selected period.</p>';
        }
        
        generatePDFView($html, 'Billing Report');
    }
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Billing Report</h1>
    <p class="text-gray-600 dark:text-gray-400">Hospital Financial Billing and Payment Reports</p>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Status</label>
                <select name="payment_status"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                    <option value="all" <?php echo $payment_status === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="partial" <?php echo $payment_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="overdue" <?php echo $payment_status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
                <select name="payment_method"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                    <option value="all" <?php echo $payment_method === 'all' ? 'selected' : ''; ?>>All Methods</option>
                    <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="credit_card" <?php echo $payment_method === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="debit_card" <?php echo $payment_method === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                    <option value="insurance" <?php echo $payment_method === 'insurance' ? 'selected' : ''; ?>>Insurance</option>
                    <option value="hmo" <?php echo $payment_method === 'hmo' ? 'selected' : ''; ?>>HMO</option>
                    <option value="philhealth" <?php echo $payment_method === 'philhealth' ? 'selected' : ''; ?>>PhilHealth</option>
                </select>
            </div>
            <div class="sm:col-span-4">
                <button type="submit"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total Bills</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $stats['total_bills'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total Billed</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">₱<?php echo number_format($stats['total_billed'] ?? 0, 2); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total Paid</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">₱<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Outstanding Balance</p>
        <p class="text-2xl font-bold text-red-600 dark:text-red-400">₱<?php echo number_format($stats['total_balance'] ?? 0, 2); ?></p>
    </div>
</div>

<!-- Report Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Billing Details</h3>
        <div class="flex space-x-2">
            <?php 
            $is_admin = ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin';
            if ($is_admin): 
            ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>Export Excel
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>Export CSV
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" 
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>Export PDF
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 9V2a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v7"></path><rect x="6" y="14" width="12" height="8" rx="1"></rect></svg>Print
            </button>
        </div>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Bill Number</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Paid</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($bill['bill_number']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo formatDate($bill['bill_date']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?><br>
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars($bill['hospital_id']); ?></span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    ₱<?php echo number_format($bill['total_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">
                                    ₱<?php echo number_format($bill['paid_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-red-600 dark:text-red-400">
                                    ₱<?php echo number_format($bill['balance_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        echo $bill['payment_status'] === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '';
                                        echo $bill['payment_status'] === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '';
                                        echo $bill['payment_status'] === 'partial' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '';
                                        echo $bill['payment_status'] === 'overdue' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '';
                                        ?>">
                                        <?php echo ucfirst($bill['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo ucfirst(str_replace('_', ' ', $bill['payment_method'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v12"></path><path d="M15 9a3 3 0 1 0-6 0"></path></svg>
                <p class="text-gray-500 dark:text-gray-400">No billing records found for the selected period</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

