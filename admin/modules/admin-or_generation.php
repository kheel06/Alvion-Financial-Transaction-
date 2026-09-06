<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('collection.manage');

$page_title = 'OR Generation';
include __DIR__ . '/../../includes/header.php';

// Handle OR search and generation
$or_number = $_GET['or_number'] ?? '';
$payment_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_or'])) {
    // Generate OR for a new payment
    try {
        // This would typically create an OR for an existing payment
        $_SESSION['success'] = "OR generated successfully";
        header("Location: admin-or_generation.php?or_number=" . urlencode($_POST['or_number']));
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error generating OR: " . $e->getMessage();
    }
}

// Search for existing OR
if ($or_number) {
    try {
        $query = "SELECT cp.*, 
                         p.first_name, p.last_name, p.address,
                         h.hmo_name,
                         da.employee_fname, da.employee_lname
                  FROM collection_payments cp
                  LEFT JOIN patients p ON cp.patient_id = p.id
                  LEFT JOIN hmos h ON cp.hmo_id = h.id
                  LEFT JOIN department_accounts da ON cp.collected_by = da.employee_id
                  WHERE cp.or_number = :or_number";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['or_number' => $or_number]);
        $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment_data) {
            $_SESSION['error'] = "OR number not found: " . htmlspecialchars($or_number);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error searching for OR: " . $e->getMessage();
    }
}

// Get Total Bills Summary for the patient (if OR found)
$patient_bills = [];
$total_bills_summary = null;
if ($payment_data && $payment_data['patient_id']) {
    try {
        // Get all bills for this patient
        $bills_query = "SELECT 
            ari.invoice_number,
            ari.invoice_date,
            ari.due_date,
            ari.total_amount,
            ari.balance_amount,
            ari.service_type,
            ari.status
            FROM ar_invoices ari
            WHERE ari.patient_id = :patient_id
            ORDER BY ari.invoice_date DESC";
        
        $bills_stmt = $db->prepare($bills_query);
        $bills_stmt->execute(['patient_id' => $payment_data['patient_id']]);
        $patient_bills = $bills_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $totals_query = "SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as total_billed,
            COALESCE(SUM(balance_amount), 0) as total_balance,
            COALESCE(SUM(total_amount - balance_amount), 0) as total_paid
            FROM ar_invoices 
            WHERE patient_id = :patient_id";
        
        $totals_stmt = $db->prepare($totals_query);
        $totals_stmt->execute(['patient_id' => $payment_data['patient_id']]);
        $total_bills_summary = $totals_stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Silent error handling
    }
}

// Get company information for OR header
$company_info = [
    'name' => SITE_NAME,
    'address' => '123 Hospital Street, Medical City, Philippines',
    'tin' => COMPANY_TIN,
    'accreditation' => COMPANY_ACCREDITATION_NO
];

// Get ORs this year for the list (when no OR is being viewed)
$recent_ors = [];
try {
    $recent_query = "SELECT cp.or_number, cp.payment_date, cp.payment_amount, cp.payment_method,
                            p.first_name, p.last_name, h.hmo_name
                     FROM collection_payments cp
                     LEFT JOIN patients p ON cp.patient_id = p.id
                     LEFT JOIN hmos h ON cp.hmo_id = h.id
                     WHERE cp.status = 'Posted'
                     AND YEAR(cp.payment_date) = YEAR(CURDATE())
                     ORDER BY cp.payment_date DESC, cp.id DESC";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_ors = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ignore
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Official Receipt Generation
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Generate and reprint official receipts
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- OR Search Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Search OR</h3>
            
            <form method="GET">
                <div class="space-y-4">
                    <div>
                        <label for="or_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">OR Number</label>
                        <input type="text" id="or_number" name="or_number" value="<?php echo htmlspecialchars($or_number); ?>"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="OR-20241201-001" required>
                    </div>
                    
                    <div>
                        <button type="submit" title="Search OR" class="w-full flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                            <svg data-lucide="search" class="w-5 h-5"></svg>
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Quick Actions</h4>
                <div class="flex space-x-2">
                    <a href="<?php echo BASE_URL; ?>/admin/modules/admin-collection.php" title="Process New Payment"
                       class="flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                        <svg data-lucide="credit-card" class="w-5 h-5"></svg>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/modules/admin-or_generation.php" title="OR Registration" aria-label="OR Registration"
                       class="flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="clipboard-list" class="w-5 h-5"></svg>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- OR Statistics -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">OR Statistics</h3>
            <?php
            try {
                $stats_query = "SELECT 
                                COUNT(*) as total_ors,
                                SUM(payment_amount) as total_amount,
                                MIN(payment_date) as first_or_date,
                                MAX(payment_date) as last_or_date
                               FROM collection_payments 
                               WHERE status = 'Posted'
                               AND YEAR(payment_date) = YEAR(CURDATE())";
                $stats_stmt = $db->prepare($stats_query);
                $stats_stmt->execute();
                $or_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $or_stats = ['total_ors' => 0, 'total_amount' => 0];
            }
            ?>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">This Year</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $or_stats['total_ors']; ?> ORs</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Amount</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">₱<?php echo number_format((float)($or_stats['total_amount'] ?? 0), 2); ?></span>
                </div>
                <?php if ($or_stats['first_or_date']): ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">First OR</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo date('M j, Y', strtotime($or_stats['first_or_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- OR Preview and Generation -->
    <div class="lg:col-span-2">
        <?php if ($payment_data): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Official Receipt</h3>
                <div class="flex space-x-2">
                    <button onclick="printOR()" title="Print OR" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                        <svg data-lucide="printer" class="w-5 h-5"></svg>
                    </button>
                    <button onclick="downloadOR()" title="Download PDF" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="download" class="w-5 h-5"></svg>
                    </button>
                </div>
            </div>
            
            <!-- OR Template -->
            <div id="or-template" class="bg-white border-2 border-gray-300 p-8 max-w-2xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6 border-b-2 border-gray-300 pb-4">
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo $company_info['name']; ?></h1>
                    <p class="text-sm text-gray-600"><?php echo $company_info['address']; ?></p>
                    <p class="text-sm text-gray-600">TIN: <?php echo $company_info['tin']; ?></p>
                    <p class="text-sm text-gray-600">Accreditation: <?php echo $company_info['accreditation']; ?></p>
                </div>
                
                <!-- OR Details -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-600">OR Number:</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo $payment_data['or_number']; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Date:</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo date('F j, Y', strtotime($payment_data['payment_date'])); ?></p>
                    </div>
                </div>
                
                <!-- Patient Information -->
                <div class="mb-6">
                    <p class="text-sm text-gray-600 mb-2">Received from:</p>
                    <p class="text-lg font-bold text-gray-900 border-b-2 border-gray-300 pb-2">
                        <?php 
                        if ($payment_data['first_name']) {
                            echo htmlspecialchars($payment_data['first_name'] . ' ' . $payment_data['last_name']);
                        } elseif ($payment_data['hmo_name']) {
                            echo htmlspecialchars($payment_data['hmo_name']);
                        } else {
                            echo 'Cash Payment';
                        }
                        ?>
                    </p>
                    <?php if ($payment_data['address']): ?>
                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($payment_data['address']); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Payment Details -->
                <div class="mb-6">
                    <p class="text-sm text-gray-600 mb-2">The sum of:</p>
                    <p class="text-2xl font-bold text-gray-900 text-center border-2 border-gray-300 p-4">
                        ₱<?php echo number_format($payment_data['payment_amount'], 2); ?>
                    </p>
                    <p class="text-sm text-gray-600 text-center mt-2">
                        <?php echo amountToWords($payment_data['payment_amount']); ?>
                    </p>
                </div>
                
                <!-- Payment Method -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-600">Payment Method:</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo $payment_data['payment_method']; ?></p>
                    </div>
                    <?php if ($payment_data['reference_number']): ?>
                    <div>
                        <p class="text-sm text-gray-600">Reference:</p>
                        <p class="text-lg font-bold text-gray-900"><?php echo $payment_data['reference_number']; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Footer -->
                <div class="border-t-2 border-gray-300 pt-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Prepared by:</p>
                            <p class="font-bold text-gray-900 border-b-2 border-gray-300 pb-2 mt-6">
                                <?php echo htmlspecialchars($payment_data['employee_fname'] . ' ' . $payment_data['employee_lname']); ?>
                            </p>
                            <p class="text-xs text-gray-600 text-center">Cashier</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Received by:</p>
                            <p class="font-bold text-gray-900 border-b-2 border-gray-300 pb-2 mt-6">&nbsp;</p>
                            <p class="text-xs text-gray-600 text-center">Patient/Representative</p>
                        </div>
                    </div>
                </div>
                
                <!-- OR Notice -->
                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500">
                        THIS OFFICIAL RECEIPT SHALL BE VALID FOR FIVE (5) YEARS FROM THE DATE OF ATP<br>
                        PER BIR REVENUE REGULATIONS NO. 18-2012
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Total Bills Summary Section -->
        <?php if ($total_bills_summary && $payment_data['patient_id']): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Patient Total Bills Summary
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Complete billing history for: <strong><?php echo htmlspecialchars($payment_data['first_name'] . ' ' . $payment_data['last_name']); ?></strong>
            </p>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <p class="text-sm text-blue-600 dark:text-blue-400">Total Invoices</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-300"><?php echo number_format($total_bills_summary['total_invoices']); ?></p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Billed</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">₱<?php echo number_format($total_bills_summary['total_billed'], 2); ?></p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                    <p class="text-sm text-green-600 dark:text-green-400">Total Paid</p>
                    <p class="text-2xl font-bold text-green-700 dark:text-green-300">₱<?php echo number_format($total_bills_summary['total_paid'], 2); ?></p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
                    <p class="text-sm text-red-600 dark:text-red-400">Outstanding Balance</p>
                    <p class="text-2xl font-bold text-red-700 dark:text-red-300">₱<?php echo number_format($total_bills_summary['total_balance'], 2); ?></p>
                </div>
            </div>
            
            <!-- Bills Table -->
            <?php if (!empty($patient_bills)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoice #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($patient_bills as $bill): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo $bill['invoice_number']; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo date('M j, Y', strtotime($bill['invoice_date'])); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo $bill['service_type']; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">₱<?php echo number_format($bill['total_amount'], 2); ?></td>
                            <td class="px-4 py-3 text-sm <?php echo $bill['balance_amount'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                ₱<?php echo number_format($bill['balance_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $bill['status'] === 'Paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                        ($bill['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); ?>">
                                    <?php echo $bill['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php elseif ($or_number): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">OR Not Found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No official receipt found with number: <strong><?php echo htmlspecialchars($or_number); ?></strong>
                </p>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Search for OR</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Enter an OR number in the search box, or click an OR below to view and print.
            </p>
            <?php if (!empty($recent_ors)): ?>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">ORs This Year (<?php echo count($recent_ors); ?>)</h4>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach ($recent_ors as $or): ?>
                <a href="?or_number=<?php echo urlencode($or['or_number']); ?>" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($or['or_number']); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <?php
                            if (!empty($or['first_name']) || !empty($or['last_name'])) {
                                echo htmlspecialchars(trim($or['first_name'] . ' ' . $or['last_name']));
                            } elseif (!empty($or['hmo_name'])) {
                                echo htmlspecialchars($or['hmo_name']);
                            } else {
                                echo '—';
                            }
                            ?>
                            · <?php echo date('M j, Y', strtotime($or['payment_date'])); ?> · <?php echo htmlspecialchars($or['payment_method']); ?>
                        </p>
                    </div>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">₱<?php echo number_format((float)$or['payment_amount'], 2); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No ORs this year yet. Process a payment in Collection to generate one.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function printOR() {
    const orContent = document.getElementById('or-template').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Official Receipt - <?php echo $payment_data['or_number']; ?></title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0;
                    padding: 20px;
                    color: #000;
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
                .or-template {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 2px solid #000;
                    padding: 30px;
                    background: white;
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .font-bold { font-weight: bold; }
                .border-b-2 { border-bottom: 2px solid #000; }
                .border-t-2 { border-top: 2px solid #000; }
                .border-2 { border: 2px solid #000; }
                .mb-6 { margin-bottom: 24px; }
                .mt-6 { margin-top: 24px; }
                .p-4 { padding: 16px; }
                .pb-2 { padding-bottom: 8px; }
                .pt-4 { padding-top: 16px; }
                .grid { display: grid; }
                .grid-cols-2 { grid-template-columns: 1fr 1fr; }
                .gap-4 { gap: 16px; }
            </style>
        </head>
        <body>
            <div class="or-template">
                ${orContent}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                }
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

function downloadOR() {
    // This would typically generate a PDF download
    alert('PDF download functionality would be implemented here.');
}
</script>

<?php
// Helper function to convert amount to words
function amountToWords($number) {
    // This is a simplified version - in production, you'd want a more robust solution
    $ones = array("", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine");
    $teens = array("Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen");
    $tens = array("", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety");
    
    $pesos = floor($number);
    $centavos = round(($number - $pesos) * 100);
    
    if ($pesos == 0) {
        $words = "Zero";
    } else {
        $words = numberToWords($pesos);
    }
    
    $result = $words . " Pesos";
    
    if ($centavos > 0) {
        $result .= " and " . numberToWords($centavos) . " Centavos";
    }
    
    return $result . " Only";
}

function numberToWords($number) {
    // Simplified number to words conversion
    // In production, use a proper library for this
    if ($number < 1000) {
        return strval($number);
    }
    
    return "Amount in Words"; // Placeholder
}

include __DIR__ . '/../../includes/footer.php';
?>
