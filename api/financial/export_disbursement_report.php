<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/export_helper.php';
requireAuth();

$report_type = $_GET['report'] ?? 'summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

switch ($report_type) {
    case 'summary':
        $data = getDisbursementSummary($db, $start_date, $end_date);
        $headers = ['Request #', 'Department', 'Payee', 'Amount', 'Status', 'Request Date'];
        $rows = [];
        
        foreach ($data as $request) {
            $rows[] = [
                $request['request_number'],
                $request['department_name'],
                $request['payee_name'],
                number_format($request['amount'], 2),
                $request['status'],
                date('Y-m-d', strtotime($request['requested_at']))
            ];
        }
        
        exportToExcel($rows, $headers, 'disbursement_summary');
        break;
        
    default:
        http_response_code(400);
        echo "Invalid report type";
        exit();
}