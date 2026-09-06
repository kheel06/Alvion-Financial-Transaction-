<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/gemini.php';
require_once __DIR__ . '/../config/ollama.php';
requireAuth();
checkRole(['admin', 'super admin']);

$aiEnabled = (defined('GEMINI_ENABLED') && GEMINI_ENABLED && !empty(trim(GEMINI_API_KEY ?? '')))
    || (defined('OLLAMA_ENABLED') && OLLAMA_ENABLED);

$page_title = 'Financial Dashboard';

include __DIR__ . '/../includes/header.php';

// Get financial KPIs
try {
    // Total Revenue (Current Month)
    $revenue_query = "SELECT COALESCE(SUM(credit_amount), 0) as total_revenue 
                     FROM journal_entry_lines jel
                     INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
                     INNER JOIN chart_of_accounts coa ON jel.account_id = coa.id
                     WHERE coa.account_type = 'Revenue'
                     AND je.entry_date BETWEEN :month_start AND :month_end
                     AND je.status = 'Posted'";
    
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    
    $revenue_stmt = $db->prepare($revenue_query);
    $revenue_stmt->execute(['month_start' => $month_start, 'month_end' => $month_end]);
    $total_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];
    
    // Accounts Receivable
    $ar_query = "SELECT COALESCE(SUM(balance_amount), 0) as total_ar 
                FROM ar_invoices 
                WHERE status IN ('Pending', 'Partially Paid')";
    $ar_stmt = $db->prepare($ar_query);
    $ar_stmt->execute();
    $total_ar = $ar_stmt->fetch(PDO::FETCH_ASSOC)['total_ar'];
    
    // Accounts Payable
    $ap_query = "SELECT COALESCE(SUM(net_amount), 0) as total_ap 
                FROM ap_invoices 
                WHERE status = 'Approved'";
    $ap_stmt = $db->prepare($ap_query);
    $ap_stmt->execute();
    $total_ap = $ap_stmt->fetch(PDO::FETCH_ASSOC)['total_ap'];
    
    // Pending Disbursements
    $disb_query = "SELECT COALESCE(SUM(amount), 0) as total_pending 
                  FROM disbursement_requests 
                  WHERE status = 'Pending'";
    $disb_stmt = $db->prepare($disb_query);
    $disb_stmt->execute();
    $total_pending_disb = $disb_stmt->fetch(PDO::FETCH_ASSOC)['total_pending'];

    // Revenue vs Expenses (Last 6 Months)
    $rev_exp_query = "SELECT 
                        DATE_FORMAT(je.entry_date, '%Y-%m') as month,
                        SUM(CASE WHEN coa.account_type = 'Revenue' THEN jel.credit_amount - jel.debit_amount ELSE 0 END) as revenue,
                        SUM(CASE WHEN coa.account_type = 'Expense' THEN jel.debit_amount - jel.credit_amount ELSE 0 END) as expense
                    FROM journal_entry_lines jel
                    JOIN journal_entries je ON jel.journal_entry_id = je.id
                    JOIN chart_of_accounts coa ON jel.account_id = coa.id
                    WHERE je.status = 'Posted'
                      AND je.entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                      AND coa.account_type IN ('Revenue', 'Expense')
                    GROUP BY month
                    ORDER BY month ASC";
    $rev_exp_stmt = $db->prepare($rev_exp_query);
    $rev_exp_stmt->execute();
    $rev_exp_data = $rev_exp_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for Chart.js
    $chart_labels = [];
    $chart_revenue = [];
    $chart_expense = [];
    foreach ($rev_exp_data as $row) {
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $chart_revenue[] = $row['revenue'];
        $chart_expense[] = $row['expense'];
    }

    // AR Aging Summary
    $aging_query = "SELECT
                        SUM(CASE WHEN due_date >= CURDATE() THEN balance_amount ELSE 0 END) as current,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 30 THEN balance_amount ELSE 0 END) as days_1_30,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN balance_amount ELSE 0 END) as days_31_60,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN balance_amount ELSE 0 END) as days_61_90,
                        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN balance_amount ELSE 0 END) as days_over_90
                    FROM ar_invoices
                    WHERE status IN ('Pending', 'Partially Paid', 'Overdue')
                      AND balance_amount > 0";
    $aging_stmt = $db->prepare($aging_query);
    $aging_stmt->execute();
    $aging_data = $aging_stmt->fetch(PDO::FETCH_ASSOC);

    $aging_labels = ['Current', '1-30 Days', '31-60 Days', '61-90 Days', '> 90 Days'];
    $aging_values = [
        $aging_data['current'] ?? 0,
        $aging_data['days_1_30'] ?? 0,
        $aging_data['days_31_60'] ?? 0,
        $aging_data['days_61_90'] ?? 0,
        $aging_data['days_over_90'] ?? 0
    ];

} catch (PDOException $e) {
    // Handle database errors gracefully
    $total_revenue = 0;
    $total_ar = 0;
    $total_ap = 0;
    $total_pending_disb = 0;
    $chart_labels = [];
    $chart_revenue = [];
    $chart_expense = [];
    $aging_labels = [];
    $aging_values = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Financial Dashboard
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Comprehensive overview of hospital financial performance
    </p>
</div>

<!-- Financial KPIs -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Total Revenue -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 relative">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_revenue, 2); ?></p>
                <p class="text-xs text-green-600 dark:text-green-400">Current Month</p>
            </div>
            <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-lg">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
            </div>
        </div>
        <?php if ($aiEnabled): ?>
        <button type="button" onclick="explainKPI('Total Revenue', '₱<?php echo number_format($total_revenue, 2); ?>', 'Current month')" class="absolute top-3 right-3 p-1.5 rounded-md text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20" title="Explain with AI"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></button>
        <?php endif; ?>
    </div>

    <!-- Accounts Receivable -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 relative">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Accounts Receivable</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_ar, 2); ?></p>
                <p class="text-xs text-amber-600 dark:text-amber-400">Outstanding</p>
            </div>
            <div class="p-3 bg-amber-100 dark:bg-amber-900/20 rounded-lg">
                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
        </div>
        <?php if ($aiEnabled): ?>
        <button type="button" onclick="explainKPI('Accounts Receivable', '₱<?php echo number_format($total_ar, 2); ?>', 'Outstanding')" class="absolute top-3 right-3 p-1.5 rounded-md text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20" title="Explain with AI"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></button>
        <?php endif; ?>
    </div>

    <!-- Accounts Payable -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 relative">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Accounts Payable</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_ap, 2); ?></p>
                <p class="text-xs text-blue-600 dark:text-blue-400">Pending Payment</p>
            </div>
            <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
            </div>
        </div>
        <?php if ($aiEnabled): ?>
        <button type="button" onclick="explainKPI('Accounts Payable', '₱<?php echo number_format($total_ap, 2); ?>', 'Pending payment')" class="absolute top-3 right-3 p-1.5 rounded-md text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20" title="Explain with AI"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></button>
        <?php endif; ?>
    </div>

    <!-- Pending Disbursements -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 relative">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Disbursements</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($total_pending_disb, 2); ?></p>
                <p class="text-xs text-purple-600 dark:text-purple-400">Awaiting Approval</p>
            </div>
            <div class="p-3 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
            </div>
        </div>
        <?php if ($aiEnabled): ?>
        <button type="button" onclick="explainKPI('Pending Disbursements', '₱<?php echo number_format($total_pending_disb, 2); ?>', 'Awaiting approval')" class="absolute top-3 right-3 p-1.5 rounded-md text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20" title="Explain with AI"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></button>
        <?php endif; ?>
    </div>
</div>

<?php if ($aiEnabled): ?>
<!-- AI Financial Report & KPI Explanations -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
        </span>
        AI Insights
    </h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Generate an executive summary from current KPIs or ask for an explanation on any metric.</p>
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" id="btnGenerateReport" onclick="generateAIReport()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
            <span id="btnGenerateReportText">Generate AI report</span>
            <span id="btnGenerateReportSpinner" class="hidden ml-2 inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        </button>
    </div>
    <div id="aiReportOutput" class="mt-4 hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
        <p id="aiReportText" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap"></p>
    </div>
    <div id="aiReportError" class="mt-4 hidden p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm"></div>
</div>

<!-- Modal: KPI Explanation -->
<div id="kpiExplainModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" onclick="closeKPIModal()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6 border border-gray-200 dark:border-gray-700">
            <h4 id="kpiExplainTitle" class="text-lg font-semibold text-gray-900 dark:text-white mb-2"></h4>
            <p id="kpiExplainBody" class="text-sm text-gray-600 dark:text-gray-300 mb-4"></p>
            <div id="kpiExplainSpinner" class="hidden py-4 flex justify-center"><span class="w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></span></div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeKPIModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Financial Modules -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Financial Modules</h3>
        <div class="space-y-3">
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-general_ledger.php" class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <svg class="w-5 h-5 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                General Ledger
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-accounts_payable.php" class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <svg class="w-5 h-5 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
                Accounts Payable
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-accounts_receivable.php" class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600">
                <svg class="w-5 h-5 mr-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Accounts Receivable
            </a>
        </div>
    </div>

    <!-- Recent Financial Activity -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>
        <div class="space-y-4">
            <?php
            try {
                $activity_query = "SELECT je.journal_number, je.entry_date, je.description, je.total_debit 
                                 FROM journal_entries je 
                                 WHERE je.status = 'Posted' 
                                 ORDER BY je.created_at DESC 
                                 LIMIT 5";
                $activity_stmt = $db->prepare($activity_query);
                $activity_stmt->execute();
                $activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($activities as $activity) {
                    echo '<div class="flex items-start">';
                    echo '<div class="flex-shrink-0 w-2 h-2 mt-2 bg-green-500 rounded-full"></div>';
                    echo '<div class="ml-3">';
                    echo '<p class="text-sm font-medium text-gray-900 dark:text-white">' . htmlspecialchars($activity['description']) . '</p>';
                    echo '<p class="text-xs text-gray-500 dark:text-gray-400">' . $activity['journal_number'] . ' • ₱' . number_format($activity['total_debit'], 2) . ' • ' . date('M j, Y', strtotime($activity['entry_date'])) . '</p>';
                    echo '</div></div>';
                }
            } catch (PDOException $e) {
                echo '<p class="text-sm text-gray-500 dark:text-gray-400">No recent activity</p>';
            }
            ?>
        </div>
    </div>

    <!-- System Alerts -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Alerts</h3>
        <div class="space-y-3">
            <?php
            // Check for overdue AR
            try {
                $overdue_query = "SELECT COUNT(*) as count FROM ar_invoices 
                                WHERE due_date < CURDATE() 
                                AND status IN ('Pending', 'Partially Paid') 
                                AND balance_amount > 0";
                $overdue_stmt = $db->prepare($overdue_query);
                $overdue_stmt->execute();
                $overdue_count = $overdue_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($overdue_count > 0) {
                    echo '<div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">';
                    echo '<div>';
                    echo '<p class="text-sm font-medium text-gray-900 dark:text-white">Overdue Invoices</p>';
                    echo '<p class="text-xs text-gray-500 dark:text-gray-400">' . $overdue_count . ' invoices need attention</p>';
                    echo '</div>';
                    echo '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">Alert</span>';
                    echo '</div>';
                }
            } catch (PDOException $e) {
                // Silence error
            }
            ?>
            
            <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">System Status</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">All modules operational</p>
                </div>
                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">OK</span>
            </div>
        </div>
    </div>
</div>

<!-- Financial Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Revenue vs Expense Trend -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue vs Expenses</h3>
        <div class="h-64 relative">
            <canvas id="revExpChart"></canvas>
        </div>
    </div>

    <!-- AR Aging Summary -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">AR Aging Summary</h3>
        <div class="h-64 relative">
            <canvas id="arAgingChart"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Revenue vs Expenses Chart
    const revExpCtx = document.getElementById('revExpChart').getContext('2d');
    new Chart(revExpCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Revenue',
                    data: <?php echo json_encode($chart_revenue); ?>,
                    borderColor: '#10b981', // green-500
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($chart_expense); ?>,
                    borderColor: '#ef4444', // red-500
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        color: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280',
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280'
                    }
                }
            }
        }
    });

    // AR Aging Chart
    const arAgingCtx = document.getElementById('arAgingChart').getContext('2d');
    new Chart(arAgingCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($aging_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($aging_values); ?>,
                backgroundColor: [
                    '#10b981', // Current - green-500
                    '#f59e0b', // 1-30 - amber-500
                    '#f97316', // 31-60 - orange-500
                    '#ef4444', // 61-90 - red-500
                    '#7f1d1d'  // >90 - red-900
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151'
                    }
                }
            }
        }
    });

<?php if ($aiEnabled): ?>
    const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
    const dashboardKpiData = {
        total_revenue: <?php echo json_encode($total_revenue); ?>,
        total_ar: <?php echo json_encode($total_ar); ?>,
        total_ap: <?php echo json_encode($total_ap); ?>,
        total_pending_disbursements: <?php echo json_encode($total_pending_disb); ?>,
        period: '<?php echo date('F Y'); ?>'
    };

    function generateAIReport() {
        var btn = document.getElementById('btnGenerateReport');
        var btnText = document.getElementById('btnGenerateReportText');
        var spinner = document.getElementById('btnGenerateReportSpinner');
        var out = document.getElementById('aiReportOutput');
        var text = document.getElementById('aiReportText');
        var errEl = document.getElementById('aiReportError');
        if (!btn || !out || !text) return;
        errEl.classList.add('hidden');
        out.classList.add('hidden');
        btn.disabled = true;
        btnText.textContent = 'Generating…';
        spinner.classList.remove('hidden');
        fetch(BASE_URL + '/api/ai/generate_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'kpi_data=' + encodeURIComponent(JSON.stringify(dashboardKpiData)) + '&period=' + encodeURIComponent(dashboardKpiData.period)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                text.textContent = data.report || '';
                out.classList.remove('hidden');
            } else {
                errEl.textContent = data.error || 'Failed to generate report';
                errEl.classList.remove('hidden');
            }
        })
        .catch(function(e) {
            errEl.textContent = 'Request failed: ' + (e.message || 'Network error');
            errEl.classList.remove('hidden');
        })
        .finally(function() {
            btn.disabled = false;
            btnText.textContent = 'Generate AI report';
            spinner.classList.add('hidden');
        });
    }

    function explainKPI(kpiName, value, context) {
        var modal = document.getElementById('kpiExplainModal');
        var title = document.getElementById('kpiExplainTitle');
        var body = document.getElementById('kpiExplainBody');
        var spinner = document.getElementById('kpiExplainSpinner');
        if (!modal || !title || !body) return;
        title.textContent = kpiName;
        body.textContent = '';
        body.classList.remove('hidden');
        spinner.classList.remove('hidden');
        modal.classList.remove('hidden');
        var params = new URLSearchParams({ kpi_name: kpiName, value: value, context: context || '' });
        fetch(BASE_URL + '/api/ai/explain_kpi.php?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            spinner.classList.add('hidden');
            if (data.success) {
                body.textContent = data.explanation || '';
                body.classList.remove('hidden');
            } else {
                body.textContent = 'Error: ' + (data.error || 'Could not get explanation');
                body.classList.remove('hidden');
            }
        })
        .catch(function(e) {
            spinner.classList.add('hidden');
            body.textContent = 'Request failed: ' + (e.message || 'Network error');
            body.classList.remove('hidden');
        });
    }

    function closeKPIModal() {
        var modal = document.getElementById('kpiExplainModal');
        if (modal) modal.classList.add('hidden');
    }
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
