<?php
/**
 * Financial System Core Functions
 * Philippine COA Compliant
 */

/**
 * Generate financial document numbers
 */
function generateJournalNumber() {
    return 'JE-' . date('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

function generateDisbursementNumber() {
    return 'DV-' . date('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

function generateORNumber() {
    return 'OR-' . date('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

function generateAPInvoiceNumber() {
    return 'API-' . date('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

function generateARInvoiceNumber() {
    return 'ARI-' . date('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Calculate VAT and EWT amounts
 */
function calculateVAT($amount) {
    return $amount * VAT_RATE;
}

function calculateEWT($amount) {
    return $amount * EWT_RATE;
}

function calculateNetAmount($amount, $vat_included = false, $apply_ewt = false) {
    if ($vat_included) {
        $base_amount = $amount / (1 + VAT_RATE);
    } else {
        $base_amount = $amount;
    }
    
    if ($apply_ewt) {
        $base_amount -= calculateEWT($base_amount);
    }
    
    return $base_amount;
}

/**
 * Post journal entry to General Ledger
 */
function postJournalEntry($db, $entry_data, $line_items) {
    try {
        $db->beginTransaction();
        
        // Insert journal header
        $journal_query = "INSERT INTO journal_entries 
                         (journal_number, entry_date, reference, description, total_debit, total_credit, status, module_source, source_id, created_by) 
                         VALUES (:journal_number, :entry_date, :reference, :description, :total_debit, :total_credit, 'Posted', :module_source, :source_id, :created_by)";
        
        $journal_stmt = $db->prepare($journal_query);
        $journal_stmt->execute($entry_data);
        $journal_id = $db->lastInsertId();
        
        // Insert journal lines
        $line_query = "INSERT INTO journal_entry_lines 
                      (journal_entry_id, account_id, debit_amount, credit_amount, description, department_id, cost_center) 
                      VALUES (:journal_entry_id, :account_id, :debit_amount, :credit_amount, :description, :department_id, :cost_center)";
        
        $line_stmt = $db->prepare($line_query);
        
        foreach ($line_items as $line) {
            $line['journal_entry_id'] = $journal_id;
            $line_stmt->execute($line);
        }
        
        // Update journal entry with posted info
        $update_query = "UPDATE journal_entries SET posted_by = :posted_by, posted_at = NOW() WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            'posted_by' => $entry_data['created_by'],
            'id' => $journal_id
        ]);
        
        $db->commit();
        return $journal_id;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Get account balance
 */
function getAccountBalance($db, $account_id, $as_of_date = null) {
    $as_of_condition = $as_of_date ? "AND je.entry_date <= :as_of_date" : "";
    
    $query = "SELECT 
                SUM(jel.debit_amount) as total_debit,
                SUM(jel.credit_amount) as total_credit,
                coa.normal_balance
              FROM journal_entry_lines jel
              INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
              INNER JOIN chart_of_accounts coa ON jel.account_id = coa.id
              WHERE jel.account_id = :account_id 
              AND je.status = 'Posted'
              {$as_of_condition}
              GROUP BY coa.normal_balance";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':account_id', $account_id, PDO::PARAM_INT);
    
    if ($as_of_date) {
        $stmt->bindValue(':as_of_date', $as_of_date);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return 0.00;
    }
    
    $debit_balance = $result['total_debit'] ?? 0;
    $credit_balance = $result['total_credit'] ?? 0;
    
    if ($result['normal_balance'] === 'Debit') {
        return $debit_balance - $credit_balance;
    } else {
        return $credit_balance - $debit_balance;
    }
}

/**
 * Calculate AR Aging
 */
function calculateARAging($db, $as_of_date = null) {
    $as_of_date = $as_of_date ?: date('Y-m-d');
    
    $query = "SELECT 
                id,
                invoice_number,
                due_date,
                balance_amount,
                CASE 
                    WHEN DATEDIFF(:as_of_date, due_date) <= 0 THEN 'Current'
                    WHEN DATEDIFF(:as_of_date, due_date) BETWEEN 1 AND 30 THEN '1-30'
                    WHEN DATEDIFF(:as_of_date, due_date) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN DATEDIFF(:as_of_date, due_date) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE 'Over 90'
                END as aging_bucket
              FROM ar_invoices 
              WHERE balance_amount > 0 
              AND status IN ('Pending', 'Partially Paid')";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['as_of_date' => $as_of_date]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate AP Aging
 */
function calculateAPAging($db, $as_of_date = null) {
    $as_of_date = $as_of_date ?: date('Y-m-d');
    
    $query = "SELECT 
                ai.id,
                ai.invoice_number,
                ai.due_date,
                ai.net_amount as balance_amount,
                s.supplier_name,
                CASE 
                    WHEN DATEDIFF(:as_of_date, ai.due_date) <= 0 THEN 'Current'
                    WHEN DATEDIFF(:as_of_date, ai.due_date) BETWEEN 1 AND 30 THEN '1-30'
                    WHEN DATEDIFF(:as_of_date, ai.due_date) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN DATEDIFF(:as_of_date, ai.due_date) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE 'Over 90'
                END as aging_bucket
              FROM ap_invoices ai
              INNER JOIN suppliers s ON ai.supplier_id = s.id
              WHERE ai.status = 'Approved'";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['as_of_date' => $as_of_date]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate Trial Balance
 */
function generateTrialBalance($db, $period = null) {
    $period = $period ?: CURRENT_PERIOD;
    $start_date = date('Y-m-01', strtotime($period));
    $end_date = date('Y-m-t', strtotime($period));
    
    $query = "SELECT 
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.account_type,
                coa.normal_balance,
                COALESCE(SUM(CASE WHEN je.entry_date BETWEEN :start_date AND :end_date THEN jel.debit_amount ELSE 0 END), 0) as period_debit,
                COALESCE(SUM(CASE WHEN je.entry_date BETWEEN :start_date AND :end_date THEN jel.credit_amount ELSE 0 END), 0) as period_credit,
                COALESCE(SUM(jel.debit_amount), 0) as ytd_debit,
                COALESCE(SUM(jel.credit_amount), 0) as ytd_credit
              FROM chart_of_accounts coa
              LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.status = 'Posted'
              WHERE coa.is_active = 1
              GROUP BY coa.id, coa.account_code, coa.account_name, coa.account_type, coa.normal_balance
              ORDER BY coa.account_code";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check budget utilization
 */
function getBudgetUtilization($db, $department_id, $fiscal_year = null) {
    $fiscal_year = $fiscal_year ?: CURRENT_FISCAL_YEAR;
    
    $query = "SELECT 
                bh.total_budget,
                COALESCE(SUM(jel.debit_amount), 0) as actual_expenses,
                (bh.total_budget - COALESCE(SUM(jel.debit_amount), 0)) as remaining_budget,
                ROUND((COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100, 2) as utilization_percent
              FROM budget_headers bh
              LEFT JOIN journal_entry_lines jel ON bh.department_id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.status = 'Posted'
              WHERE bh.department_id = :department_id 
              AND bh.fiscal_year = :fiscal_year
              AND bh.status = 'Approved'
              GROUP BY bh.id, bh.total_budget";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'department_id' => $department_id,
        'fiscal_year' => $fiscal_year
    ]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Validate three-way matching for disbursements
 */
function validateThreeWayMatch($db, $po_number, $dr_number, $si_number) {
    // Implementation for three-way matching
    // This would typically check PO against Delivery Receipt and Sales Invoice
    return true; // Simplified for this example
}

/**
 * Generate financial statements
 */
function generateIncomeStatement($db, $start_date, $end_date) {
    $query = "SELECT 
                coa.account_code,
                coa.account_name,
                SUM(jel.debit_amount) as debit_amount,
                SUM(jel.credit_amount) as credit_amount,
                CASE 
                    WHEN coa.account_type = 'Revenue' THEN SUM(jel.credit_amount) - SUM(jel.debit_amount)
                    WHEN coa.account_type = 'Expense' THEN SUM(jel.debit_amount) - SUM(jel.credit_amount)
                    ELSE 0
                END as amount
              FROM journal_entry_lines jel
              INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
              INNER JOIN chart_of_accounts coa ON jel.account_id = coa.id
              WHERE je.entry_date BETWEEN :start_date AND :end_date
              AND je.status = 'Posted'
              AND coa.account_type IN ('Revenue', 'Expense')
              GROUP BY coa.id, coa.account_code, coa.account_name, coa.account_type
              ORDER BY coa.account_type, coa.account_code";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateBalanceSheet($db, $as_of_date) {
    $query = "SELECT 
                coa.account_type,
                coa.account_code,
                coa.account_name,
                CASE 
                    WHEN coa.normal_balance = 'Debit' THEN 
                        COALESCE(SUM(jel.debit_amount), 0) - COALESCE(SUM(jel.credit_amount), 0)
                    ELSE 
                        COALESCE(SUM(jel.credit_amount), 0) - COALESCE(SUM(jel.debit_amount), 0)
                END as balance
              FROM chart_of_accounts coa
              LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.entry_date <= :as_of_date 
                AND je.status = 'Posted'
              WHERE coa.is_active = 1
              GROUP BY coa.account_type, coa.id, coa.account_code, coa.account_name, coa.normal_balance
              ORDER BY coa.account_type, coa.account_code";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['as_of_date' => $as_of_date]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get general ledger data for a specific account
 */
function getGeneralLedgerData($db, $account_id, $start_date, $end_date) {
    $query = "SELECT 
                je.entry_date,
                je.journal_number,
                je.description,
                jel.debit_amount,
                jel.credit_amount,
                jel.description as line_description,
                CASE 
                    WHEN coa.normal_balance = 'Debit' THEN
                        COALESCE(SUM(jel.debit_amount) OVER (PARTITION BY jel.account_id ORDER BY je.entry_date, je.id) - 
                        COALESCE(SUM(jel.credit_amount) OVER (PARTITION BY jel.account_id ORDER BY je.entry_date, je.id), 0)
                    ELSE
                        COALESCE(SUM(jel.credit_amount) OVER (PARTITION BY jel.account_id ORDER BY je.entry_date, je.id) - 
                        COALESCE(SUM(jel.debit_amount) OVER (PARTITION BY jel.account_id ORDER BY je.entry_date, je.id), 0)
                END as running_balance
              FROM journal_entry_lines jel
              INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
              INNER JOIN chart_of_accounts coa ON jel.account_id = coa.id
              WHERE jel.account_id = :account_id
              AND je.entry_date BETWEEN :start_date AND :end_date
              AND je.status = 'Posted'
              ORDER BY je.entry_date, je.id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'account_id' => $account_id,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Validate if account code already exists
 */
function isAccountCodeExists($db, $account_code, $exclude_id = null) {
    $query = "SELECT COUNT(*) as count FROM chart_of_accounts WHERE account_code = :account_code";
    
    if ($exclude_id) {
        $query .= " AND id != :exclude_id";
    }
    
    $stmt = $db->prepare($query);
    $params = ['account_code' => $account_code];
    
    if ($exclude_id) {
        $params['exclude_id'] = $exclude_id;
    }
    
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Get financial period status
 */
function getFinancialPeriodStatus($db, $period) {
    $query = "SELECT 
                COUNT(*) as total_entries,
                SUM(total_debit) as total_debit,
                SUM(total_credit) as total_credit
              FROM journal_entries 
              WHERE DATE_FORMAT(entry_date, '%Y-%m') = :period
              AND status = 'Posted'";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['period' => $period]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get supplier balance summary
 */
function getSupplierBalanceSummary($db, $supplier_id = null) {
    $query = "SELECT 
                s.id,
                s.supplier_name,
                s.supplier_code,
                COUNT(ai.id) as invoice_count,
                COALESCE(SUM(ai.net_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN ai.status = 'Pending' THEN ai.net_amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN ai.status = 'Approved' THEN ai.net_amount ELSE 0 END), 0) as approved_amount
              FROM suppliers s
              LEFT JOIN ap_invoices ai ON s.id = ai.supplier_id AND ai.status != 'Cancelled'";
    
    if ($supplier_id) {
        $query .= " WHERE s.id = :supplier_id";
    }
    
    $query .= " GROUP BY s.id, s.supplier_name, s.supplier_code
                ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($query);
    
    if ($supplier_id) {
        $stmt->execute(['supplier_id' => $supplier_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get payment schedule
 */
function getPaymentSchedule($db, $start_date = null, $end_date = null) {
    $start_date = $start_date ?: date('Y-m-d');
    $end_date = $end_date ?: date('Y-m-d', strtotime('+30 days'));
    
    $query = "SELECT 
                ai.*,
                s.supplier_name,
                s.supplier_code,
                DATEDIFF(ai.due_date, CURDATE()) as days_until_due
              FROM ap_invoices ai
              INNER JOIN suppliers s ON ai.supplier_id = s.id
              WHERE ai.status = 'Approved'
              AND ai.due_date BETWEEN :start_date AND :end_date
              ORDER BY ai.due_date ASC, ai.net_amount DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Process AP invoice payment and create journal entry
 */
function processAPPayment($db, $invoice_id, $payment_date, $payment_method, $reference_number, $posted_by) {
    try {
        $db->beginTransaction();
        
        // Get invoice details
        $invoice_query = "SELECT * FROM ap_invoices WHERE id = :id AND status = 'Approved'";
        $invoice_stmt = $db->prepare($invoice_query);
        $invoice_stmt->execute(['id' => $invoice_id]);
        $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            throw new Exception("Invoice not found or not approved");
        }
        
        // Update invoice status
        $update_query = "UPDATE ap_invoices SET status = 'Paid' WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute(['id' => $invoice_id]);
        
        // Create journal entry for payment
        $journal_data = [
            'journal_number' => generateJournalNumber(),
            'entry_date' => $payment_date,
            'reference' => $reference_number,
            'description' => "Payment for invoice " . $invoice['invoice_number'],
            'total_debit' => $invoice['net_amount'],
            'total_credit' => $invoice['net_amount'],
            'module_source' => 'AP',
            'source_id' => $invoice_id,
            'created_by' => $posted_by
        ];
        
        $line_items = [
            [
                'account_id' => getAccountIdByCode($db, '201'), // Accounts Payable
                'debit_amount' => 0,
                'credit_amount' => $invoice['net_amount'],
                'description' => "Payment to supplier"
            ],
            [
                'account_id' => getAccountIdByCode($db, '102'), // Cash in Bank
                'debit_amount' => $invoice['net_amount'],
                'credit_amount' => 0,
                'description' => "Bank payment"
            ]
        ];
        
        postJournalEntry($db, $journal_data, $line_items);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Helper function to get account ID by code
 */
function getAccountIdByCode($db, $account_code) {
    $query = "SELECT id FROM chart_of_accounts WHERE account_code = :code AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['code' => $account_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['id'] : null;
}

/**
 * Get AP dashboard statistics
 */
function getAPDashboardStats($db) {
    $stats = [];
    
    try {
        // Total AP
        $total_query = "SELECT COALESCE(SUM(net_amount), 0) as total_ap 
                       FROM ap_invoices 
                       WHERE status IN ('Pending', 'Approved')";
        $total_stmt = $db->prepare($total_query);
        $total_stmt->execute();
        $stats['total_ap'] = $total_stmt->fetch(PDO::FETCH_ASSOC)['total_ap'];
        
        // Overdue
        $overdue_query = "SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as amount 
                         FROM ap_invoices 
                         WHERE due_date < CURDATE() 
                         AND status IN ('Pending', 'Approved')";
        $overdue_stmt = $db->prepare($overdue_query);
        $overdue_stmt->execute();
        $overdue = $overdue_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['overdue_count'] = $overdue['count'];
        $stats['overdue_amount'] = $overdue['amount'];
        
        // Due this week
        $week_start = date('Y-m-d');
        $week_end = date('Y-m-d', strtotime('+7 days'));
        $week_query = "SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as amount 
                      FROM ap_invoices 
                      WHERE due_date BETWEEN :start AND :end 
                      AND status IN ('Pending', 'Approved')";
        $week_stmt = $db->prepare($week_query);
        $week_stmt->execute(['start' => $week_start, 'end' => $week_end]);
        $week_due = $week_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['week_due_count'] = $week_due['count'];
        $stats['week_due_amount'] = $week_due['amount'];
        
        // Supplier count
        $supplier_query = "SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1";
        $supplier_stmt = $db->prepare($supplier_query);
        $supplier_stmt->execute();
        $stats['supplier_count'] = $supplier_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
    } catch (PDOException $e) {
        // Set default values on error
        $stats = [
            'total_ap' => 0,
            'overdue_count' => 0,
            'overdue_amount' => 0,
            'week_due_count' => 0,
            'week_due_amount' => 0,
            'supplier_count' => 0
        ];
    }
    
    return $stats;
}

/**
 * Get recent AP activity
 */
function getRecentAPActivity($db, $limit = 10) {
    try {
        $query = "SELECT ai.*, s.supplier_name 
                 FROM ap_invoices ai
                 INNER JOIN suppliers s ON ai.supplier_id = s.id
                 ORDER BY ai.created_at DESC 
                 LIMIT :limit";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Validate supplier data
 */
function validateSupplierData($data) {
    $errors = [];
    
    if (empty($data['supplier_code'])) {
        $errors[] = "Supplier code is required";
    }
    
    if (empty($data['supplier_name'])) {
        $errors[] = "Supplier name is required";
    }
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (!empty($data['tin']) && !preg_match('/^\d{3}-\d{3}-\d{3}-\d{3}$/', $data['tin'])) {
        $errors[] = "TIN must be in format: 123-456-789-000";
    }
    
    return $errors;
}

/**
 * Check if supplier code exists
 */
function isSupplierCodeExists($db, $supplier_code, $exclude_id = null) {
    $query = "SELECT COUNT(*) as count FROM suppliers WHERE supplier_code = :supplier_code";
    
    if ($exclude_id) {
        $query .= " AND id != :exclude_id";
    }
    
    $stmt = $db->prepare($query);
    $params = ['supplier_code' => $supplier_code];
    
    if ($exclude_id) {
        $params['exclude_id'] = $exclude_id;
    }
    
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Get supplier performance metrics
 */
function getSupplierPerformance($db, $supplier_id = null, $period_months = 12) {
    $start_date = date('Y-m-01', strtotime("-$period_months months"));
    
    $query = "SELECT 
                s.id,
                s.supplier_name,
                COUNT(ai.id) as total_invoices,
                COALESCE(SUM(ai.net_amount), 0) as total_amount,
                AVG(DATEDIFF(ai.due_date, ai.invoice_date)) as avg_payment_terms,
                COUNT(CASE WHEN ai.due_date < CURDATE() AND ai.status IN ('Pending', 'Approved') THEN 1 END) as overdue_count
              FROM suppliers s
              LEFT JOIN ap_invoices ai ON s.id = ai.supplier_id 
                AND ai.invoice_date >= :start_date
                AND ai.status != 'Cancelled'";
    
    if ($supplier_id) {
        $query .= " WHERE s.id = :supplier_id";
    }
    
    $query .= " GROUP BY s.id, s.supplier_name
                ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($query);
    $params = ['start_date' => $start_date];
    
    if ($supplier_id) {
        $params['supplier_id'] = $supplier_id;
    }
    
    $stmt->execute($params);
    
    if ($supplier_id) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}   

/**
 * Get collection report data
 */
function getCollectionReport($db, $start_date, $end_date) {
    $query = "SELECT 
                cp.*,
                p.first_name as patient_fname, p.last_name as patient_lname,
                h.hmo_name
              FROM collection_payments cp
              LEFT JOIN patients p ON cp.patient_id = p.id
              LEFT JOIN hmos h ON cp.hmo_id = h.id
              WHERE cp.payment_date BETWEEN :start_date AND :end_date
              AND cp.status = 'Posted'
              ORDER BY cp.payment_date DESC, cp.payment_amount DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get revenue by service type
 */
function getRevenueByService($db, $start_date, $end_date, $service_type = null) {
    $query = "SELECT 
                service_type,
                COUNT(*) as invoice_count,
                SUM(total_amount) as total_revenue,
                SUM(balance_amount) as outstanding_balance,
                AVG(total_amount) as average_invoice
              FROM ar_invoices 
              WHERE invoice_date BETWEEN :start_date AND :end_date";
    
    if ($service_type) {
        $query .= " AND service_type = :service_type";
    }
    
    $query .= " GROUP BY service_type
                ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    if ($service_type) {
        $params['service_type'] = $service_type;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Process AR collection and create journal entry
 */
function processARCollection($db, $invoice_id, $payment_data) {
    try {
        $db->beginTransaction();
        
        // Record payment in collection_payments
        $payment_query = "INSERT INTO collection_payments 
                         (or_number, patient_id, hmo_id, payment_date, payment_amount, 
                          payment_method, reference_number, collected_by, status) 
                         VALUES (:or_number, :patient_id, :hmo_id, :payment_date, :payment_amount,
                                 :payment_method, :reference_number, :collected_by, 'Posted')";
        
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute($payment_data);
        $payment_id = $db->lastInsertId();
        
        // Update AR invoice balance
        $invoice_query = "UPDATE ar_invoices 
                         SET balance_amount = balance_amount - :payment_amount,
                             status = CASE 
                                 WHEN (balance_amount - :payment_amount) <= 0 THEN 'Paid'
                                 ELSE 'Partially Paid'
                             END
                         WHERE id = :invoice_id";
        
        $invoice_stmt = $db->prepare($invoice_query);
        $invoice_stmt->execute([
            'payment_amount' => $payment_data['payment_amount'],
            'invoice_id' => $invoice_id
        ]);
        
        // Create journal entry for collection
        $journal_data = [
            'journal_number' => generateJournalNumber(),
            'entry_date' => $payment_data['payment_date'],
            'reference' => $payment_data['or_number'],
            'description' => "Collection for invoice " . $payment_data['or_number'],
            'total_debit' => $payment_data['payment_amount'],
            'total_credit' => $payment_data['payment_amount'],
            'module_source' => 'AR',
            'source_id' => $payment_id,
            'created_by' => $payment_data['collected_by']
        ];
        
        $line_items = [
            [
                'account_id' => getAccountIdByCode($db, '101'), // Cash on Hand / Cash in Bank
                'debit_amount' => $payment_data['payment_amount'],
                'credit_amount' => 0,
                'description' => "Collection receipt"
            ],
            [
                'account_id' => getAccountIdByCode($db, '103'), // Accounts Receivable
                'debit_amount' => 0,
                'credit_amount' => $payment_data['payment_amount'],
                'description' => "Reduce AR balance"
            ]
        ];
        
        postJournalEntry($db, $journal_data, $line_items);
        
        $db->commit();
        return $payment_id;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Get AR dashboard statistics
 */
function getARDashboardStats($db) {
    $stats = [];
    
    // Total AR Balance
    $ar_balance_query = "SELECT SUM(balance_amount) as total_ar FROM ar_invoices WHERE status IN ('Pending', 'Partially Paid')";
    $ar_balance_stmt = $db->prepare($ar_balance_query);
    $ar_balance_stmt->execute();
    $stats['total_ar_balance'] = $ar_balance_stmt->fetch(PDO::FETCH_ASSOC)['total_ar'] ?? 0;
    
    // Overdue Invoices
    $overdue_query = "SELECT COUNT(*) as count, SUM(balance_amount) as amount 
                     FROM ar_invoices 
                     WHERE due_date < CURDATE() AND status IN ('Pending', 'Partially Paid')";
    $overdue_stmt = $db->prepare($overdue_query);
    $overdue_stmt->execute();
    $overdue_result = $overdue_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['overdue_count'] = $overdue_result['count'] ?? 0;
    $stats['overdue_amount'] = $overdue_result['amount'] ?? 0;
    
    // Monthly Collections
    $monthly_query = "SELECT SUM(payment_amount) as amount 
                     FROM collection_payments 
                     WHERE payment_date BETWEEN :month_start AND :month_end 
                     AND status = 'Posted'";
    $monthly_stmt = $db->prepare($monthly_query);
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $monthly_stmt->execute(['month_start' => $month_start, 'month_end' => $month_end]);
    $stats['monthly_collections'] = $monthly_stmt->fetch(PDO::FETCH_ASSOC)['amount'] ?? 0;
    
    // Average Collection Period
    $collection_period_query = "SELECT AVG(DATEDIFF(payment_date, invoice_date)) as avg_days
                               FROM collection_payments cp
                               INNER JOIN ar_invoices ai ON cp.patient_id = ai.patient_id 
                               WHERE cp.status = 'Posted'
                               AND cp.payment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND CURDATE()";
    $collection_period_stmt = $db->prepare($collection_period_query);
    $collection_period_stmt->execute();
    $stats['avg_collection_period'] = $collection_period_stmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?? 0;
    
    return $stats;
}

/**
 * Get next approver in the workflow
 */
function getNextApprover($current_status) {
    // This would typically query the database for the next approver based on workflow rules
    // For now, return a placeholder
    return 'F-2025-01'; // Default finance manager
}

/**
 * Check if user can approve a disbursement request
 */
function canApproveDisbursement($user_id, $request_id) {
    global $db;
    
    try {
        $query = "SELECT current_approver FROM disbursement_requests WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $request && $request['current_approver'] === $user_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get disbursement summary for reports
 */
function getDisbursementSummary($db, $start_date, $end_date, $department_id = null) {
    $query = "SELECT dr.*, d.department_name 
              FROM disbursement_requests dr
              INNER JOIN departments d ON dr.department_id = d.id
              WHERE dr.requested_at BETWEEN :start_date AND :end_date";
    
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    if ($department_id) {
        $query .= " AND dr.department_id = :department_id";
        $params['department_id'] = $department_id;
    }
    
    $query .= " ORDER BY dr.requested_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get pending approvals
 */
function getPendingApprovals($db) {
    $query = "SELECT dr.*, d.department_name,
                     CONCAT(da2.employee_fname, ' ', da2.employee_lname) as approver_name
              FROM disbursement_requests dr
              INNER JOIN departments d ON dr.department_id = d.id
              LEFT JOIN department_accounts da2 ON dr.current_approver = da2.employee_id
              WHERE dr.status = 'Pending'
              ORDER BY dr.requested_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get department analysis
 */
function getDepartmentAnalysis($db, $start_date, $end_date) {
    $query = "SELECT 
                d.department_name,
                COUNT(dr.id) as request_count,
                COALESCE(SUM(dr.amount), 0) as total_amount,
                COALESCE(AVG(dr.amount), 0) as average_amount,
                SUM(CASE WHEN dr.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN dr.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN dr.status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
              FROM departments d
              LEFT JOIN disbursement_requests dr ON d.id = dr.department_id 
                AND dr.requested_at BETWEEN :start_date AND :end_date
              WHERE d.is_active = 1
              GROUP BY d.id, d.department_name
              ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Process disbursement payment and create journal entry
 */
function processDisbursementPayment($db, $request_id, $payment_method, $reference_number, $posted_by) {
    try {
        $db->beginTransaction();
        
        // Get request details
        $request_query = "SELECT * FROM disbursement_requests WHERE id = :id AND status = 'Approved'";
        $request_stmt = $db->prepare($request_query);
        $request_stmt->execute(['id' => $request_id]);
        $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception("Request not found or not approved");
        }
        
        // Update request status
        $update_query = "UPDATE disbursement_requests 
                         SET status = 'Paid', payment_method = :payment_method 
                         WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            'payment_method' => $payment_method,
            'id' => $request_id
        ]);
        
        // Create journal entry for disbursement
        $journal_data = [
            'journal_number' => generateJournalNumber(),
            'entry_date' => date('Y-m-d'),
            'reference' => $reference_number,
            'description' => "Disbursement payment - " . $request['purpose'],
            'total_debit' => $request['amount'],
            'total_credit' => $request['amount'],
            'module_source' => 'Disbursement',
            'source_id' => $request_id,
            'created_by' => $posted_by
        ];
        
        // Determine account based on payee type
        $expense_account = null;
        switch ($request['payee_type']) {
            case 'Supplier':
                $expense_account = getAccountIdByCode($db, '501'); // Professional Fees
                break;
            case 'Employee':
                $expense_account = getAccountIdByCode($db, '501'); // Salaries and Wages
                break;
            case 'Patient':
                $expense_account = getAccountIdByCode($db, '508'); // Other Expenses
                break;
            default:
                $expense_account = getAccountIdByCode($db, '508'); // Other Expenses
        }
        
        $line_items = [
            [
                'account_id' => $expense_account,
                'debit_amount' => $request['amount'],
                'credit_amount' => 0,
                'description' => "Payment to " . $request['payee_name'],
                'department_id' => $request['department_id']
            ],
            [
                'account_id' => getAccountIdByCode($db, '102'), // Cash in Bank
                'debit_amount' => 0,
                'credit_amount' => $request['amount'],
                'description' => "Bank payment"
            ]
        ];
        
        postJournalEntry($db, $journal_data, $line_items);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Get daily collection summary
 */
function getDailyCollectionSummary($db, $start_date, $end_date) {
    $query = "SELECT 
                payment_date,
                COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN payment_amount ELSE 0 END), 0) as cash,
                COALESCE(SUM(CASE WHEN payment_method = 'Card' THEN payment_amount ELSE 0 END), 0) as card,
                COALESCE(SUM(CASE WHEN payment_method = 'Check' THEN payment_amount ELSE 0 END), 0) as check_amount,
                COALESCE(SUM(CASE WHEN payment_method = 'Online' THEN payment_amount ELSE 0 END), 0) as online,
                COALESCE(SUM(CASE WHEN payment_method = 'HMO' THEN payment_amount ELSE 0 END), 0) as hmo,
                COALESCE(SUM(CASE WHEN payment_method = 'PhilHealth' THEN payment_amount ELSE 0 END), 0) as philhealth
              FROM collection_payments 
              WHERE payment_date BETWEEN :start_date AND :end_date
              AND status = 'Posted'
              GROUP BY payment_date
              ORDER BY payment_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get OR register
 */
function getORRegister($db, $start_date, $end_date, $payment_method = '') {
    $query = "SELECT cp.*, 
                     p.first_name, p.last_name,
                     h.hmo_name,
                     da.employee_fname, da.employee_lname
              FROM collection_payments cp
              LEFT JOIN patients p ON cp.patient_id = p.id
              LEFT JOIN hmos h ON cp.hmo_id = h.id
              LEFT JOIN department_accounts da ON cp.collected_by = da.employee_id
              WHERE cp.payment_date BETWEEN :start_date AND :end_date
              AND cp.status = 'Posted'";
    
    if ($payment_method) {
        $query .= " AND cp.payment_method = :payment_method";
    }
    
    $query .= " ORDER BY cp.payment_date DESC, cp.or_number DESC";
    
    $stmt = $db->prepare($query);
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    if ($payment_method) {
        $params['payment_method'] = $payment_method;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get payment method analysis
 */
function getPaymentMethodAnalysis($db, $start_date, $end_date) {
    $query = "SELECT 
                payment_method,
                COUNT(*) as payment_count,
                SUM(payment_amount) as total_amount
              FROM collection_payments 
              WHERE payment_date BETWEEN :start_date AND :end_date
              AND status = 'Posted'
              GROUP BY payment_method
              ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Process HMO/PhilHealth claims
 */
function processInsuranceClaim($db, $claim_data) {
    try {
        $db->beginTransaction();
        
        // Create claim record
        $query = "INSERT INTO insurance_claims 
                 (claim_number, patient_id, hmo_id, claim_date, total_amount, 
                  status, submitted_by, submitted_at) 
                 VALUES (:claim_number, :patient_id, :hmo_id, :claim_date, :total_amount,
                         'Submitted', :submitted_by, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute($claim_data);
        $claim_id = $db->lastInsertId();
        
        // Update related AR invoices
        if (isset($claim_data['ar_invoices']) && is_array($claim_data['ar_invoices'])) {
            foreach ($claim_data['ar_invoices'] as $invoice_id) {
                $update_query = "UPDATE ar_invoices 
                               SET hmo_id = :hmo_id, claim_status = 'Submitted'
                               WHERE id = :invoice_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    'hmo_id' => $claim_data['hmo_id'],
                    'invoice_id' => $invoice_id
                ]);
            }
        }
        
        $db->commit();
        return $claim_id;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Bank reconciliation functions
 */
function getUnreconciledPayments($db, $bank_account_id, $start_date, $end_date) {
    $query = "SELECT cp.* 
              FROM collection_payments cp
              LEFT JOIN bank_reconciliation br ON cp.id = br.payment_id
              WHERE cp.payment_date BETWEEN :start_date AND :end_date
              AND cp.status = 'Posted'
              AND br.id IS NULL
              AND cp.payment_method IN ('Cash', 'Check', 'Online')
              ORDER BY cp.payment_date, cp.or_number";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reconcilePayment($db, $payment_id, $bank_statement_date, $bank_reference, $reconciled_by) {
    $query = "INSERT INTO bank_reconciliation 
             (payment_id, bank_statement_date, bank_reference, reconciled_by, reconciled_at) 
             VALUES (:payment_id, :bank_statement_date, :bank_reference, :reconciled_by, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'payment_id' => $payment_id,
        'bank_statement_date' => $bank_statement_date,
        'bank_reference' => $bank_reference,
        'reconciled_by' => $reconciled_by
    ]);
    
    return $db->lastInsertId();
}

/**
 * Get budget alerts for high utilization
 */
function getBudgetAlerts($db, $threshold_percent = 75) {
    $query = "SELECT 
                d.department_name,
                bh.fiscal_year,
                bh.total_budget,
                COALESCE(SUM(jel.debit_amount), 0) as actual_expenses,
                ROUND((COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100, 2) as utilization_percent
              FROM budget_headers bh
              INNER JOIN departments d ON bh.department_id = d.id
              LEFT JOIN journal_entry_lines jel ON bh.department_id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.status = 'Posted'
                AND YEAR(je.entry_date) = bh.fiscal_year
              WHERE bh.status = 'Approved'
              GROUP BY d.department_name, bh.fiscal_year, bh.total_budget
              HAVING utilization_percent >= :threshold_percent
              ORDER BY utilization_percent DESC";

    $stmt = $db->prepare($query);
    $stmt->execute(['threshold_percent' => $threshold_percent]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get budget vs actual comparison
 */
function getBudgetVsActual($db, $fiscal_year, $department_id = null) {
    $query = "SELECT * FROM (
                SELECT 
                    d.department_name,
                    bh.total_budget as budget_amount,
                    COALESCE(SUM(jel.debit_amount), 0) as actual_amount,
                    (bh.total_budget - COALESCE(SUM(jel.debit_amount), 0)) as variance_amount,
                    CASE 
                        WHEN bh.total_budget > 0 THEN 
                            ROUND(((bh.total_budget - COALESCE(SUM(jel.debit_amount), 0)) / bh.total_budget) * 100, 2)
                        ELSE 0
                    END as variance_percent
                FROM budget_headers bh
                INNER JOIN departments d ON bh.department_id = d.id
                LEFT JOIN journal_entry_lines jel ON bh.department_id = jel.department_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                    AND je.status = 'Posted'
                    AND YEAR(je.entry_date) = :fiscal_year
                WHERE bh.fiscal_year = :fiscal_year
                AND bh.status = 'Approved'";

    if ($department_id) {
        $query .= " AND bh.department_id = :department_id";
    }

    $query .= " GROUP BY d.department_name, bh.total_budget
            ) AS subquery
            ORDER BY ABS(variance_percent) DESC";

    $stmt = $db->prepare($query);
    $params = ['fiscal_year' => $fiscal_year];
    if ($department_id) {
        $params['department_id'] = $department_id;
    }
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get monthly budget utilization
 */
function getMonthlyBudgetUtilization($db, $department_id, $fiscal_year) {
    $query = "SELECT 
                DATE_FORMAT(ba.month_year, '%Y-%m') as month,
                SUM(ba.allocated_amount) as allocated_amount,
                SUM(ba.utilized_amount) as utilized_amount,
                CASE 
                    WHEN SUM(ba.allocated_amount) > 0 THEN
                        ROUND((SUM(ba.utilized_amount) / SUM(ba.allocated_amount)) * 100, 2)
                    ELSE 0
                END as utilization_percent
              FROM budget_allocations ba
              INNER JOIN budget_lines bl ON ba.budget_line_id = bl.id
              INNER JOIN budget_headers bh ON bl.budget_header_id = bh.id
              WHERE bh.department_id = :department_id
              AND bh.fiscal_year = :fiscal_year
              AND bh.status = 'Approved'
              GROUP BY DATE_FORMAT(ba.month_year, '%Y-%m')
              ORDER BY ba.month_year";

    $stmt = $db->prepare($query);
    $stmt->execute([
        'department_id' => $department_id,
        'fiscal_year' => $fiscal_year
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update budget utilization from journal entries
 */
function updateBudgetUtilization($db, $journal_entry_id) {
    try {
        $db->beginTransaction();
        
        // Get journal entry details
        $je_query = "SELECT jel.department_id, jel.debit_amount, jel.credit_amount, 
                            je.entry_date, jel.account_id
                     FROM journal_entry_lines jel
                     INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
                     WHERE jel.journal_entry_id = :journal_entry_id
                     AND je.status = 'Posted'";
        
        $je_stmt = $db->prepare($je_query);
        $je_stmt->execute(['journal_entry_id' => $journal_entry_id]);
        $journal_lines = $je_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($journal_lines as $line) {
            if ($line['department_id'] && $line['debit_amount'] > 0) {
                $expense_amount = $line['debit_amount'];
                $month_year = date('Y-m-01', strtotime($line['entry_date']));
                $fiscal_year = date('Y', strtotime($line['entry_date']));
                
                // Find matching budget allocation
                $allocation_query = "SELECT ba.id
                                   FROM budget_allocations ba
                                   INNER JOIN budget_lines bl ON ba.budget_line_id = bl.id
                                   INNER JOIN budget_headers bh ON bl.budget_header_id = bh.id
                                   WHERE bh.department_id = :department_id
                                   AND bh.fiscal_year = :fiscal_year
                                   AND bl.account_id = :account_id
                                   AND ba.month_year = :month_year
                                   AND bh.status = 'Approved'";
                
                $allocation_stmt = $db->prepare($allocation_query);
                $allocation_stmt->execute([
                    'department_id' => $line['department_id'],
                    'fiscal_year' => $fiscal_year,
                    'account_id' => $line['account_id'],
                    'month_year' => $month_year
                ]);
                
                $allocation = $allocation_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($allocation) {
                    // Update utilization
                    $update_query = "UPDATE budget_allocations 
                                    SET utilized_amount = utilized_amount + :amount
                                    WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([
                        'amount' => $expense_amount,
                        'id' => $allocation['id']
                    ]);
                }
            }
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>