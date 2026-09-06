<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invoice ID required']);
    exit();
}

try {
    $query = "SELECT ar.*, 
                     p.first_name as patient_fname, p.last_name as patient_lname,
                     h.hmo_name,
                     NULL as company_name
              FROM ar_invoices ar
              LEFT JOIN patients p ON ar.patient_id = p.id
              LEFT JOIN hmos h ON ar.hmo_id = h.id
              WHERE ar.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit();
    }
    
    echo json_encode(['success' => true, 'invoice' => $invoice]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
