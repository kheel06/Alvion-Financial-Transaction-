<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('gl.manage');

$page_title = 'General Ledger';
include __DIR__ . '/../../includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_journal'])) {
        try {
            $journal_data = [
                'journal_number' => generateJournalNumber(),
                'entry_date' => $_POST['entry_date'],
                'reference' => $_POST['reference'],
                'description' => $_POST['description'],
                'total_debit' => 0,
                'total_credit' => 0,
                'module_source' => 'GL',
                'source_id' => null,
                'created_by' => $_SESSION['employee_id']
            ];
            
            $line_items = [];
            $total_debit = 0;
            $total_credit = 0;
            $account_ids = isset($_POST['account_id']) && is_array($_POST['account_id']) ? $_POST['account_id'] : [];
            $debit_amounts = isset($_POST['debit_amount']) && is_array($_POST['debit_amount']) ? $_POST['debit_amount'] : [];
            $credit_amounts = isset($_POST['credit_amount']) && is_array($_POST['credit_amount']) ? $_POST['credit_amount'] : [];

            foreach ($account_ids as $index => $account_id) {
                $debit = isset($debit_amounts[$index]) ? floatval($debit_amounts[$index]) : 0;
                $credit = isset($credit_amounts[$index]) ? floatval($credit_amounts[$index]) : 0;

                if ($debit > 0 || $credit > 0) {
                    $line_items[] = [
                        'account_id' => $account_id,
                        'debit_amount' => $debit,
                        'credit_amount' => $credit,
                        'description' => isset($_POST['line_description'][$index]) ? $_POST['line_description'][$index] : '',
                        'department_id' => isset($_POST['department_id'][$index]) && $_POST['department_id'][$index] !== '' ? $_POST['department_id'][$index] : null,
                        'cost_center' => isset($_POST['cost_center'][$index]) && $_POST['cost_center'][$index] !== '' ? $_POST['cost_center'][$index] : null
                    ];
                    $total_debit += $debit;
                    $total_credit += $credit;
                }
            }

            if (count($line_items) === 0) {
                $_SESSION['error'] = "Add at least one journal line with a debit or credit amount.";
            } elseif (abs($total_debit - $total_credit) > 0.01) {
                $_SESSION['error'] = "Journal entry must balance. Debit: ₱" . number_format($total_debit, 2) . " Credit: ₱" . number_format($total_credit, 2) . ". Add or adjust lines so total debits equal total credits.";
            } else {
                $journal_data['total_debit'] = $total_debit;
                $journal_data['total_credit'] = $total_credit;
                
                $journal_id = postJournalEntry($db, $journal_data, $line_items);
                $_SESSION['success'] = "Journal entry " . $journal_data['journal_number'] . " posted successfully.";
                header("Location: admin-general_ledger.php");
                exit();
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating journal entry: " . $e->getMessage();
        }
    }
}

// Get chart of accounts
try {
    $coa_query = "SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code";
    $coa_stmt = $db->prepare($coa_query);
    $coa_stmt->execute();
    $chart_of_accounts = $coa_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $chart_of_accounts = [];
}

// Get departments
try {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Get recent journal entries
try {
    $journals_query = "SELECT je.*, da.employee_fname, da.employee_lname 
                      FROM journal_entries je
                      LEFT JOIN department_accounts da ON je.created_by = da.employee_id
                      ORDER BY je.created_at DESC 
                      LIMIT 50";
    $journals_stmt = $db->prepare($journals_query);
    $journals_stmt->execute();
    $journal_entries = $journals_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $journal_entries = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        General Ledger
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage journal entries and chart of accounts
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Create Journal Entry -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create Journal Entry</h3>
            
            <form method="POST" id="journalForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="entry_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entry Date</label>
                        <input type="date" id="entry_date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                    <div>
                        <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference</label>
                        <input type="text" id="reference" name="reference" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                               placeholder="Optional reference number">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea id="description" name="description" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                              placeholder="Journal entry description" required></textarea>
                </div>
                
                <!-- Journal Lines -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Journal Lines</h4>
                        <button type="button" id="addLine" title="Add Line" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                        <svg data-lucide="plus" class="w-4 h-4"></svg>
                                    </button>
                    </div>
                    
                    <div id="journalLines" class="space-y-3">
                        <!-- Line template will be added here by JavaScript -->
                    </div>
                    
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Total Debit:</span>
                            <span id="totalDebit" class="font-semibold">₱0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-sm mt-1">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Total Credit:</span>
                            <span id="totalCredit" class="font-semibold">₱0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-sm mt-1">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Balance:</span>
                            <span id="balance" class="font-semibold">₱0.00</span>
                        </div>
                        <p id="balanceError" class="mt-2 text-sm text-red-600 dark:text-red-400 hidden" role="alert">Debits must equal credits before posting.</p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="reset" title="Reset Form" class="p-2 text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="refresh-cw" class="w-5 h-5"></svg>
                    </button>
                    <button type="submit" name="create_journal" title="Post Journal Entry" class="p-2 text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                        <svg data-lucide="check-circle" class="w-5 h-5"></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Recent Journal Entries -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Journal Entries</h3>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php if (empty($journal_entries)): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No journal entries found</p>
                <?php else: ?>
                    <?php foreach ($journal_entries as $journal): ?>
                        <div class="p-3 border border-gray-200 dark:border-gray-600 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($journal['journal_number']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($journal['description']); ?>
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">
                                    Posted
                                </span>
                            </div>
                            <div class="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>₱<?php echo number_format($journal['total_debit'], 2); ?></span>
                                <span><?php echo date('M j, Y', strtotime($journal['entry_date'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-gl_reports.php" 
                   title="View All Entries"
                   class="flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-primary-600 bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/30 rounded-md">
                    <svg data-lucide="list" class="w-5 h-5"></svg>
                </a>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-2">
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-chart_of_accounts.php" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="list-tree" class="w-5 h-5 mr-3 text-blue-600"></svg>
                    Chart of Accounts
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-gl_reports.php?report=trial_balance" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="scale" class="w-5 h-5 mr-3 text-green-600"></svg>
                    Trial Balance
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-gl_reports.php?report=income_statement" 
                   class="flex items-center p-3 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg data-lucide="file-bar-chart-2" class="w-5 h-5 mr-3 text-purple-600"></svg>
                    Income Statement
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const journalLines = document.getElementById('journalLines');
    const addLineBtn = document.getElementById('addLine');
    const totalDebitEl = document.getElementById('totalDebit');
    const totalCreditEl = document.getElementById('totalCredit');
    const balanceEl = document.getElementById('balance');
    
    let lineCount = 0;
    
    // Add first line
    addJournalLine();
    
    addLineBtn.addEventListener('click', addJournalLine);
    
    function addJournalLine() {
        lineCount++;
        const lineDiv = document.createElement('div');
        lineDiv.className = 'journal-line p-3 border border-gray-200 dark:border-gray-600 rounded-lg';
        lineDiv.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                <div class="md:col-span-4">
                    <select name="account_id[]" class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white" required>
                        <option value="">Select Account</option>
                        <?php foreach ($chart_of_accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <input type="number" name="debit_amount[]" step="0.01" min="0" placeholder="0.00" 
                           class="debit-amount w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="md:col-span-3">
                    <input type="number" name="credit_amount[]" step="0.01" min="0" placeholder="0.00" 
                           class="credit-amount w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="md:col-span-2 flex items-center justify-center">
                    <button type="button" title="Remove Line" class="remove-line inline-flex items-center justify-center p-2 text-red-600 hover:text-red-900 focus:outline-none">
                        <svg data-lucide="trash-2" class="w-4 h-4"></svg>
                    </button>
                </div>
            </div>
            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                <div>
                    <input type="text" name="line_description[]" placeholder="Line description" 
                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select name="department_id[]" class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Dept (Optional)</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="cost_center[]" placeholder="Cost Center" 
                           class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>
        `;
        
        journalLines.appendChild(lineDiv);
        
        // Re-initialize icons for the new row
        if (typeof window !== 'undefined' && typeof window.renderLucideIcons === 'function') {
            window.renderLucideIcons();
        } else if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }

        // Add event listeners for the new line
        const debitInput = lineDiv.querySelector('.debit-amount');
        const creditInput = lineDiv.querySelector('.credit-amount');
        const removeBtn = lineDiv.querySelector('.remove-line');
        
        debitInput.addEventListener('input', updateTotals);
        creditInput.addEventListener('input', updateTotals);
        removeBtn.addEventListener('click', function() {
            if (journalLines.children.length > 1) {
                lineDiv.remove();
                updateTotals();
            }
        });
        
        // Auto-clear other amount when one is entered
        debitInput.addEventListener('input', function() {
            if (this.value > 0) {
                creditInput.value = '';
            }
        });
        
        creditInput.addEventListener('input', function() {
            if (this.value > 0) {
                debitInput.value = '';
            }
        });
    }
    
    function updateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        
        document.querySelectorAll('.journal-line').forEach(line => {
            const debit = parseFloat(line.querySelector('.debit-amount').value) || 0;
            const credit = parseFloat(line.querySelector('.credit-amount').value) || 0;
            
            totalDebit += debit;
            totalCredit += credit;
        });
        
        totalDebitEl.textContent = '₱' + totalDebit.toFixed(2);
        totalCreditEl.textContent = '₱' + totalCredit.toFixed(2);
        
        const balance = totalDebit - totalCredit;
        balanceEl.textContent = '₱' + Math.abs(balance).toFixed(2) + (balance < 0 ? ' (Credit)' : balance > 0 ? ' (Debit)' : '');
        
        if (Math.abs(balance) < 0.01) {
            balanceEl.className = 'font-semibold text-green-600 dark:text-green-400';
        } else {
            balanceEl.className = 'font-semibold text-red-600 dark:text-red-400';
        }
        
        const balanceErrorEl = document.getElementById('balanceError');
        if (balanceErrorEl) {
            balanceErrorEl.classList.toggle('hidden', Math.abs(balance) < 0.01);
        }
    }
    
    function getTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        document.querySelectorAll('.journal-line').forEach(line => {
            const debit = parseFloat(line.querySelector('.debit-amount').value) || 0;
            const credit = parseFloat(line.querySelector('.credit-amount').value) || 0;
            totalDebit += debit;
            totalCredit += credit;
        });
        return { totalDebit, totalCredit };
    }
    
    document.getElementById('journalForm').addEventListener('submit', function(e) {
        const { totalDebit, totalCredit } = getTotals();
        const hasAmounts = totalDebit > 0 || totalCredit > 0;
        const balanced = Math.abs(totalDebit - totalCredit) < 0.01;
        
        if (!hasAmounts) {
            e.preventDefault();
            const balanceErrorEl = document.getElementById('balanceError');
            if (balanceErrorEl) {
                balanceErrorEl.textContent = 'Add at least one line with a debit or credit amount.';
                balanceErrorEl.classList.remove('hidden');
            }
            return;
        }
        if (!balanced) {
            e.preventDefault();
            const balanceErrorEl = document.getElementById('balanceError');
            if (balanceErrorEl) {
                balanceErrorEl.textContent = 'Debits must equal credits before posting. Add or adjust lines so totals match.';
                balanceErrorEl.classList.remove('hidden');
                balanceErrorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }
        document.getElementById('balanceError').classList.add('hidden');
    });
    
    // Initialize totals
    updateTotals();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
