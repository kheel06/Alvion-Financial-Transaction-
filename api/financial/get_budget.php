<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$budget_id = $_GET['id'] ?? null;

if (!$budget_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Budget ID required']);
    exit();
}

try {
    // Get budget header
    $query = "SELECT bh.*, d.department_name, d.department_code,
                     da.employee_fname, da.employee_lname
              FROM budget_headers bh
              INNER JOIN departments d ON bh.department_id = d.id
              LEFT JOIN department_accounts da ON bh.created_by = da.employee_id
              WHERE bh.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $budget_id]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$budget) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Budget not found']);
        exit();
    }
    
    // Get budget line items
    $line_query = "SELECT bl.*, coa.account_code, coa.account_name
                   FROM budget_lines bl
                   INNER JOIN chart_of_accounts coa ON bl.account_id = coa.id
                   WHERE bl.budget_header_id = :budget_header_id
                   ORDER BY coa.account_code";
    
    $line_stmt = $db->prepare($line_query);
    $line_stmt->execute(['budget_header_id' => $budget_id]);
    $line_items = $line_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'budget' => $budget,
        'line_items' => $line_items
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}