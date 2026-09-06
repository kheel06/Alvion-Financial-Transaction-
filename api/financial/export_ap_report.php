<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/export_helper.php';
requireAuth();

$report_type = $_GET['report'] ?? 'aging';

switch ($report_type) {
    case 'aging':
        $data = calculateAPAging($db);
        $headers = ['Invoice #', 'Supplier', 'Due Date', 'Amount', 'Aging Bucket', 'Status'];
        $rows = [];
        
        foreach ($data as $invoice) {
            $rows[] = [
                $invoice['invoice_number'],
                $invoice['supplier_name'],
                date('Y-m-d', strtotime($invoice['due_date'])),
                number_format($invoice['balance_amount'], 2),
                $invoice['aging_bucket'],
                'Approved'
            ];
        }
        
        exportToExcel($rows, $headers, 'ap_aging_report');
        break;
        
    default:
        http_response_code(400);
        echo "Invalid report type";
        exit();
}