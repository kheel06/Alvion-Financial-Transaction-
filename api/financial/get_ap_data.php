<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'supplier_invoices':
            $supplier_id = $_GET['supplier_id'] ?? null;
            $query = "SELECT ai.*, s.supplier_name 
                      FROM ap_invoices ai
                      INNER JOIN suppliers s ON ai.supplier_id = s.id
                      WHERE ai.status != 'Cancelled'";
            
            if ($supplier_id) {
                $query .= " AND ai.supplier_id = :supplier_id";
            }
            
            $query .= " ORDER BY ai.due_date ASC";
            
            $stmt = $db->prepare($query);
            if ($supplier_id) {
                $stmt->execute(['supplier_id' => $supplier_id]);
            } else {
                $stmt->execute();
            }
            
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'invoices' => $invoices]);
            break;
            
        case 'aging_summary':
            $aging_data = calculateAPAging($db);
            $summary = [
                'Current' => 0,
                '1-30' => 0,
                '31-60' => 0,
                '61-90' => 0,
                'Over 90' => 0
            ];
            
            foreach ($aging_data as $invoice) {
                $summary[$invoice['aging_bucket']] += $invoice['balance_amount'];
            }
            
            echo json_encode(['success' => true, 'summary' => $summary]);
            break;
            
        case 'payment_schedule':
            $start_date = $_GET['start_date'] ?? date('Y-m-d');
            $end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            
            $schedule = getPaymentSchedule($db, $start_date, $end_date);
            echo json_encode(['success' => true, 'schedule' => $schedule]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}