<?php
/**
 * Comprehensive Financial Reports
 * Hospital Financial System
 * Features: Category Filtering, Date Period, CSV Password Protection, System Checkups
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/export_helper.php';
requireAuth();
checkRole(['admin', 'super admin']);

$page_title = 'Financial Reports';

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$category = $_GET['category'] ?? 'all';
$report_type = $_GET['report_type'] ?? 'summary';
$service_type = $_GET['service_type'] ?? 'all';

// Define hospital-specific categories
$categories = [
    'all' => 'All Categories',
    'inpatient' => 'Inpatient Services (IPD)',
    'outpatient' => 'Outpatient Services (OPD)',
    'laboratory' => 'Laboratory Services',
    'pharmacy' => 'Pharmacy',
    'radiology' => 'Radiology/Imaging',
    'emergency' => 'Emergency Room (ER)',
    'surgery' => 'Surgical Services',
    'checkup' => 'Medical Checkups',
    'consultation' => 'Consultations'
];

// Report types
$report_types = [
    'summary' => 'Financial Summary',
    'detailed' => 'Detailed Transactions',
    'ar_aging' => 'Accounts Receivable Aging',
    'ap_aging' => 'Accounts Payable Aging',
    'collections' => 'Collection Report',
    'revenue' => 'Revenue Analysis',
    'checkups' => 'System Checkups Report'
];

// Handle CSV export with password protection
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Check if password is verified
    if (isset($_POST['csv_export_password'])) {
        if (!verifyExportPassword($_POST['csv_export_password'])) {
            displayExportPasswordForm($_SERVER['REQUEST_URI'], true);
        }
    } elseif (!requireExportPassword()) {
        displayExportPasswordForm($_SERVER['REQUEST_URI']);
    }
}

// Fetch report data based on filters
$report_data = [];
$summary_stats = [];

try {
    $period_info = formatDate($start_date) . ' to ' . formatDate($end_date);
    
    // Build category filter condition
    $category_condition = "";
    if ($category !== 'all') {
        $category_map = [
            'inpatient' => "service_type = 'IPD'",
            'outpatient' => "service_type = 'OPD'",
            'laboratory' => "service_type = 'Lab'",
            'pharmacy' => "service_type = 'Pharmacy'",
            'radiology' => "service_type = 'Radiology'",
            'emergency' => "service_type = 'ER'",
            'surgery' => "service_type = 'Surgery'",
            'checkup' => "service_type = 'Checkup'",
            'consultation' => "service_type = 'Consultation'"
        ];
        $category_condition = isset($category_map[$category]) ? " AND " . $category_map[$category] : "";
    }
    
    if ($report_type === 'summary' || $report_type === 'detailed') {
        // AR Invoices Summary
        $ar_query = "SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_billed,
            COALESCE(SUM(total_amount - balance_amount), 0) as total_collected,
            COALESCE(SUM(balance_amount), 0) as total_balance,
            service_type
            FROM ar_invoices 
            WHERE invoice_date BETWEEN :start_date AND :end_date
            " . ($category !== 'all' ? $category_condition : "") . "
            GROUP BY service_type
            ORDER BY total_billed DESC";
        
        $ar_stmt = $db->prepare($ar_query);
        $ar_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $report_data['ar_summary'] = $ar_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $totals_query = "SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_billed,
            COALESCE(SUM(total_amount - balance_amount), 0) as total_collected,
            COALESCE(SUM(balance_amount), 0) as total_balance
            FROM ar_invoices 
            WHERE invoice_date BETWEEN :start_date AND :end_date
            " . ($category !== 'all' ? $category_condition : "");
        
        $totals_stmt = $db->prepare($totals_query);
        $totals_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $summary_stats = $totals_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($report_type === 'detailed') {
        // Detailed transactions
        $detail_query = "SELECT 
            ari.invoice_number,
            ari.invoice_date,
            ari.due_date,
            ari.total_amount,
            ari.balance_amount,
            ari.service_type,
            ari.status,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            h.hmo_name
            FROM ar_invoices ari
            LEFT JOIN patients p ON ari.patient_id = p.id
            LEFT JOIN hmos h ON ari.hmo_id = h.id
            WHERE ari.invoice_date BETWEEN :start_date AND :end_date
            " . ($category !== 'all' ? str_replace('service_type', 'ari.service_type', $category_condition) : "") . "
            ORDER BY ari.invoice_date DESC";
        
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $report_data['detailed'] = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($report_type === 'collections') {
        // Collection payments
        $collection_query = "SELECT 
            cp.or_number,
            cp.payment_date,
            cp.payment_amount,
            cp.payment_method,
            cp.reference_number,
            cp.status,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            h.hmo_name
            FROM collection_payments cp
            LEFT JOIN patients p ON cp.patient_id = p.id
            LEFT JOIN hmos h ON cp.hmo_id = h.id
            WHERE cp.payment_date BETWEEN :start_date AND :end_date
            AND cp.status = 'Posted'
            ORDER BY cp.payment_date DESC";
        
        $collection_stmt = $db->prepare($collection_query);
        $collection_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $report_data['collections'] = $collection_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Collection totals
        $coll_totals_query = "SELECT 
            COUNT(*) as total_receipts,
            COALESCE(SUM(payment_amount), 0) as total_collected,
            payment_method
            FROM collection_payments 
            WHERE payment_date BETWEEN :start_date AND :end_date
            AND status = 'Posted'
            GROUP BY payment_method";
        
        $coll_totals_stmt = $db->prepare($coll_totals_query);
        $coll_totals_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $report_data['collection_by_method'] = $coll_totals_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($report_type === 'checkups') {
        // System checkups - medical checkups report
        $checkup_query = "SELECT 
            ari.invoice_number,
            ari.invoice_date,
            ari.total_amount,
            ari.balance_amount,
            ari.status,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.gender,
            h.hmo_name
            FROM ar_invoices ari
            LEFT JOIN patients p ON ari.patient_id = p.id
            LEFT JOIN hmos h ON ari.hmo_id = h.id
            WHERE ari.invoice_date BETWEEN :start_date AND :end_date
            AND ari.service_type IN ('Checkup', 'OPD', 'Consultation')
            ORDER BY ari.invoice_date DESC";
        
        $checkup_stmt = $db->prepare($checkup_query);
        $checkup_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $report_data['checkups'] = $checkup_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Checkup totals
        $checkup_totals_query = "SELECT 
            COUNT(*) as total_checkups,
            COALESCE(SUM(total_amount), 0) as total_billed,
            COALESCE(SUM(balance_amount), 0) as total_balance
            FROM ar_invoices 
            WHERE invoice_date BETWEEN :start_date AND :end_date
            AND service_type IN ('Checkup', 'OPD', 'Consultation')";
        
        $checkup_totals_stmt = $db->prepare($checkup_totals_query);
        $checkup_totals_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
        $summary_stats = $checkup_totals_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Handle exports
    if (isset($_GET['export'])) {
        $export_type = $_GET['export'];
        $report_title = $categories[$category] . ' - ' . $report_types[$report_type];
        
        if ($report_type === 'detailed' && isset($report_data['detailed'])) {
            $headers = ['Invoice #', 'Date', 'Due Date', 'Patient', 'Service Type', 'Total Amount', 'Balance', 'Status', 'HMO'];
            $export_data = [];
            foreach ($report_data['detailed'] as $row) {
                $export_data[] = [
                    $row['invoice_number'],
                    formatDate($row['invoice_date']),
                    formatDate($row['due_date']),
                    $row['patient_name'] ?? 'N/A',
                    $row['service_type'],
                    '₱' . number_format($row['total_amount'], 2),
                    '₱' . number_format($row['balance_amount'], 2),
                    $row['status'],
                    $row['hmo_name'] ?? 'Self-Pay'
                ];
            }
        } elseif ($report_type === 'collections' && isset($report_data['collections'])) {
            $headers = ['OR Number', 'Date', 'Patient', 'Amount', 'Payment Method', 'Reference', 'HMO', 'Status'];
            $export_data = [];
            foreach ($report_data['collections'] as $row) {
                $export_data[] = [
                    $row['or_number'],
                    formatDate($row['payment_date']),
                    $row['patient_name'] ?? 'N/A',
                    '₱' . number_format($row['payment_amount'], 2),
                    $row['payment_method'],
                    $row['reference_number'] ?? 'N/A',
                    $row['hmo_name'] ?? 'Self-Pay',
                    $row['status']
                ];
            }
        } elseif ($report_type === 'checkups' && isset($report_data['checkups'])) {
            $headers = ['Invoice #', 'Date', 'Patient', 'Gender', 'Total Amount', 'Balance', 'Status', 'HMO'];
            $export_data = [];
            foreach ($report_data['checkups'] as $row) {
                $export_data[] = [
                    $row['invoice_number'],
                    formatDate($row['invoice_date']),
                    $row['patient_name'] ?? 'N/A',
                    $row['gender'] ?? 'N/A',
                    '₱' . number_format($row['total_amount'], 2),
                    '₱' . number_format($row['balance_amount'], 2),
                    $row['status'],
                    $row['hmo_name'] ?? 'Self-Pay'
                ];
            }
        } else {
            $headers = ['Service Type', 'Total Invoices', 'Total Billed', 'Total Collected', 'Balance'];
            $export_data = [];
            if (isset($report_data['ar_summary'])) {
                foreach ($report_data['ar_summary'] as $row) {
                    $export_data[] = [
                        $row['service_type'],
                        $row['total_invoices'],
                        '₱' . number_format($row['total_billed'], 2),
                        '₱' . number_format($row['total_collected'], 2),
                        '₱' . number_format($row['total_balance'], 2)
                    ];
                }
            }
        }
        
        if ($export_type === 'csv') {
            exportToCSV($export_data, $headers, 'financial_report', $report_title, $period_info);
        } elseif ($export_type === 'excel') {
            exportToExcel($export_data, $headers, 'financial_report', $report_title, $period_info);
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error generating report: " . $e->getMessage();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Financial Reports
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Comprehensive financial reporting with category filtering and date period selection
    </p>
</div>

<!-- Filter Section -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Report Filters</h3>
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Date Period -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>
        
        <!-- Category Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
            <select name="category"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Report Type -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Type</label>
            <select name="report_type"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                <?php foreach ($report_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $report_type === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Submit -->
        <div class="flex items-end">
            <button type="submit"
                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                Generate Report
            </button>
        </div>
    </form>
</div>

<!-- Summary Statistics -->
<?php if (!empty($summary_stats)): ?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">Total Invoices</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($summary_stats['total_invoices'] ?? $summary_stats['total_checkups'] ?? 0); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">Total Billed</p>
        <p class="text-2xl font-bold text-blue-600">₱<?php echo number_format($summary_stats['total_billed'] ?? 0, 2); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">Total Collected</p>
        <p class="text-2xl font-bold text-green-600">₱<?php echo number_format($summary_stats['total_collected'] ?? ($summary_stats['total_billed'] - $summary_stats['total_balance']) ?? 0, 2); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">Outstanding Balance</p>
        <p class="text-2xl font-bold text-red-600">₱<?php echo number_format($summary_stats['total_balance'] ?? 0, 2); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Report Content -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap justify-between items-center gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <?php echo $report_types[$report_type]; ?>
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Period: <?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?>
                <?php if ($category !== 'all'): ?>
                    | Category: <?php echo $categories[$category]; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                Export Excel
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                Export CSV
            </a>
            <button onclick="window.print()"
                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm font-medium">
                Print
            </button>
        </div>
    </div>
    
    <div class="p-6 overflow-x-auto">
        <?php if ($report_type === 'summary' && isset($report_data['ar_summary'])): ?>
            <!-- Summary Report Table -->
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoices</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Billed</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Collected</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($report_data['ar_summary'] as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo $row['service_type']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo number_format($row['total_invoices']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">₱<?php echo number_format($row['total_billed'], 2); ?></td>
                        <td class="px-4 py-3 text-sm text-green-600">₱<?php echo number_format($row['total_collected'], 2); ?></td>
                        <td class="px-4 py-3 text-sm text-red-600">₱<?php echo number_format($row['total_balance'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php elseif ($report_type === 'detailed' && isset($report_data['detailed'])): ?>
            <!-- Detailed Report Table -->
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoice #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($report_data['detailed'] as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo $row['invoice_number']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo formatDate($row['invoice_date']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['patient_name'] ?? 'N/A'; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['service_type']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm text-red-600">₱<?php echo number_format($row['balance_amount'], 2); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php echo $row['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 
                                    ($row['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php elseif ($report_type === 'collections' && isset($report_data['collections'])): ?>
            <!-- Collections Report -->
            <?php if (isset($report_data['collection_by_method'])): ?>
            <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($report_data['collection_by_method'] as $method): ?>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo $method['payment_method']; ?></p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">₱<?php echo number_format($method['total_collected'], 2); ?></p>
                    <p class="text-xs text-gray-500"><?php echo $method['total_receipts']; ?> receipts</p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">OR Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($report_data['collections'] as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo $row['or_number']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo formatDate($row['payment_date']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['patient_name'] ?? $row['hmo_name'] ?? 'N/A'; ?></td>
                        <td class="px-4 py-3 text-sm font-semibold text-green-600">₱<?php echo number_format($row['payment_amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['payment_method']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['reference_number'] ?? '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php elseif ($report_type === 'checkups' && isset($report_data['checkups'])): ?>
            <!-- System Checkups Report -->
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoice #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Gender</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">HMO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($report_data['checkups'] as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo $row['invoice_number']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo formatDate($row['invoice_date']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['patient_name'] ?? 'N/A'; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['gender'] ?? 'N/A'; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm text-red-600">₱<?php echo number_format($row['balance_amount'], 2); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php echo $row['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 
                                    ($row['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $row['hmo_name'] ?? 'Self-Pay'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php else: ?>
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500 dark:text-gray-400">No data available for the selected filters</p>
                <p class="text-sm text-gray-400 mt-2">Try adjusting the date range or category filter</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
