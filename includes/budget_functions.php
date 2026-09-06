<?php
/**
 * Budget Management Core Functions
 * Real-time budget tracking and analysis
 */

/**
 * Get budget utilization for a department
 */
function getBudgetUtilization($db, $department_id, $fiscal_year) {
    $query = "SELECT 
                bh.total_budget,
                COALESCE(SUM(jel.debit_amount), 0) as actual_expenses,
                (bh.total_budget - COALESCE(SUM(jel.debit_amount), 0)) as remaining_budget,
                ROUND((COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100, 2) as utilization_percent
              FROM budget_headers bh
              LEFT JOIN journal_entry_lines jel ON bh.department_id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.status = 'Posted'
                AND YEAR(je.entry_date) = bh.fiscal_year
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
 * Generate budget vs actual report
 */
function generateBudgetVsActualReport($db, $fiscal_year, $department_id = null) {
    $department_condition = $department_id ? "AND bh.department_id = :department_id" : "";
    
    $query = "SELECT 
                d.id as department_id,
                d.department_name,
                bh.total_budget as budget_amount,
                COALESCE(SUM(jel.debit_amount), 0) as actual_amount
              FROM departments d
              INNER JOIN budget_headers bh ON d.id = bh.department_id
              LEFT JOIN journal_entry_lines jel ON d.id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.status = 'Posted'
                AND YEAR(je.entry_date) = bh.fiscal_year
              WHERE bh.fiscal_year = :fiscal_year
              AND bh.status = 'Approved'
              {$department_condition}
              GROUP BY d.id, d.department_name, bh.total_budget
              ORDER BY d.department_name";
    
    $stmt = $db->prepare($query);
    $params = ['fiscal_year' => $fiscal_year];
    
    if ($department_id) {
        $params['department_id'] = $department_id;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate variance analysis report
 */
function generateVarianceAnalysis($db, $fiscal_year, $department_id = null) {
    $department_condition = $department_id ? "AND bh.department_id = :department_id" : "";
    
    $query = "SELECT 
                coa.account_code,
                coa.account_name,
                SUM(bl.total_allocation) as budget_amount,
                COALESCE(SUM(jel.debit_amount), 0) as actual_amount
              FROM budget_headers bh
              INNER JOIN budget_lines bl ON bh.id = bl.budget_header_id
              INNER JOIN chart_of_accounts coa ON bl.account_id = coa.id
              LEFT JOIN journal_entry_lines jel ON bl.account_id = jel.account_id 
                AND bh.department_id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.status = 'Posted'
                AND YEAR(je.entry_date) = bh.fiscal_year
              WHERE bh.fiscal_year = :fiscal_year
              AND bh.status = 'Approved'
              {$department_condition}
              GROUP BY coa.id, coa.account_code, coa.account_name
              HAVING budget_amount > 0
              ORDER BY ABS((COALESCE(SUM(jel.debit_amount), 0) - SUM(bl.total_allocation)) / SUM(bl.total_allocation)) DESC";
    
    $stmt = $db->prepare($query);
    $params = ['fiscal_year' => $fiscal_year];
    
    if ($department_id) {
        $params['department_id'] = $department_id;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate utilization report
 */
function generateUtilizationReport($db, $fiscal_year, $department_id = null) {
    $department_condition = $department_id ? "AND bh.department_id = :department_id" : "";
    
    $query = "SELECT 
                d.id as department_id,
                d.department_name,
                bh.total_budget as budget_amount,
                COALESCE(SUM(jel.debit_amount), 0) as actual_amount,
                (bh.total_budget - COALESCE(SUM(jel.debit_amount), 0)) as remaining_budget,
                ROUND((COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100, 2) as utilization_percent
              FROM departments d
              INNER JOIN budget_headers bh ON d.id = bh.department_id
              LEFT JOIN journal_entry_lines jel ON d.id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.status = 'Posted'
                AND YEAR(je.entry_date) = bh.fiscal_year
              WHERE bh.fiscal_year = :fiscal_year
              AND bh.status = 'Approved'
              {$department_condition}
              GROUP BY d.id, d.department_name, bh.total_budget
              ORDER BY utilization_percent DESC";
    
    $stmt = $db->prepare($query);
    $params = ['fiscal_year' => $fiscal_year];
    
    if ($department_id) {
        $params['department_id'] = $department_id;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get budget alerts for high utilization
 */
function getBudgetAlerts($db, $fiscal_year, $threshold_high = 90, $threshold_medium = 75) {
    $query = "SELECT 
                d.department_name,
                bh.total_budget,
                COALESCE(SUM(jel.debit_amount), 0) as actual_amount,
                ROUND((COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100, 2) as utilization_percent,
                CASE 
                    WHEN (COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100 >= :threshold_high THEN 'high'
                    WHEN (COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100 >= :threshold_medium THEN 'medium'
                    ELSE 'low'
                END as alert_level,
                CASE 
                    WHEN (COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100 >= 100 THEN 'Budget Overrun'
                    WHEN (COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100 >= :threshold_high THEN 'High Utilization'
                    WHEN (COALESCE(SUM(jel.debit_amount), 0) / bh.total_budget) * 100 >= :threshold_medium THEN 'Medium Utilization'
                    ELSE 'Normal'
                END as alert_type
              FROM budget_headers bh
              INNER JOIN departments d ON bh.department_id = d.id
              LEFT JOIN journal_entry_lines jel ON d.id = jel.department_id
              LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
                AND je.status = 'Posted'
                AND YEAR(je.entry_date) = bh.fiscal_year
              WHERE bh.fiscal_year = :fiscal_year
              AND bh.status = 'Approved'
              GROUP BY d.id, d.department_name, bh.total_budget
              HAVING utilization_percent >= :threshold_medium
              ORDER BY utilization_percent DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        'fiscal_year' => $fiscal_year,
        'threshold_high' => $threshold_high,
        'threshold_medium' => $threshold_medium
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if department has available budget
 */
function hasAvailableBudget($db, $department_id, $amount, $fiscal_year = null) {
    $fiscal_year = $fiscal_year ?: CURRENT_FISCAL_YEAR;
    
    $utilization = getBudgetUtilization($db, $department_id, $fiscal_year);
    
    if (!$utilization) {
        return false; // No approved budget for this department
    }
    
    return $utilization['remaining_budget'] >= $amount;
}

/**
 * Get monthly budget breakdown
 */
function getMonthlyBudgetBreakdown($db, $budget_header_id) {
    $query = "SELECT monthly_allocations FROM budget_lines WHERE budget_header_id = :budget_header_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['budget_header_id' => $budget_header_id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $monthly_totals = array_fill(0, 12, 0);
    
    foreach ($lines as $line) {
        $allocations = json_decode($line['monthly_allocations'], true);
        if ($allocations) {
            foreach ($allocations as $month => $amount) {
                $monthly_totals[$month] += $amount;
            }
        }
    }
    
    return $monthly_totals;
}
?>