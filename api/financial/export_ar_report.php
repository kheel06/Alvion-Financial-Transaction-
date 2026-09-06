<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/export_helper.php';
requireAuth();

$report_type = $_GET['report'] ?? 'aging';

switch ($report_type) {
    case 'aging':
        $data = calculateARAging($db);
        $headers = ['Invoice #', 'Client', 'Due Date', 'Amount', 'Aging Bucket', 'Service Type'];
        $rows = [];
        
        foreach ($data as $invoice) {
            // Determine client name
            $client_name = 'N/A';
            if ($invoice['patient_fname']) {
                $client_name = $invoice['patient_fname'] . ' ' . $invoice['patient_lname'];
            } elseif ($invoice['hmo_name']) {
                $client_name = $invoice['hmo_name'] . ' (HMO)';
            } elseif ($invoice['company_name']) {
                $client_name = $invoice['company_name'] . ' (Corporate)';
            }
            
            $rows[] = [
                $invoice['invoice_number'],
                $client_name,
                date('Y-m-d', strtotime($invoice['due_date'])),
                number_format($invoice['balance_amount'], 2),
                $invoice['aging_bucket'],
                $invoice['service_type']
            ];
        }
        
        exportToExcel($rows, $headers, 'ar_aging_report');
        break;
        
    default:
        http_response_code(400);
        echo "Invalid report type";
        exit();
}