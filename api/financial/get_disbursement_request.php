<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$request_id = $_GET['id'] ?? null;

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request ID required']);
    exit();
}

try {
    $query = "SELECT dr.*, d.department_name, 
                     da.employee_fname, da.employee_lname,
                     da2.employee_fname as approver_fname, da2.employee_lname as approver_lname
              FROM disbursement_requests dr
              INNER JOIN departments d ON dr.department_id = d.id
              INNER JOIN department_accounts da ON dr.requested_by = da.employee_id
              LEFT JOIN department_accounts da2 ON dr.current_approver = da2.employee_id
              WHERE dr.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    echo json_encode(['success' => true, 'request' => $request]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}