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
    $query = "SELECT ai.*, s.supplier_name, s.supplier_code 
              FROM ap_invoices ai
              INNER JOIN suppliers s ON ai.supplier_id = s.id
              WHERE ai.id = :id";
    
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
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}