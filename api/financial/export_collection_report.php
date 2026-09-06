<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/export_helper.php';
requireAuth();

$report_type = $_GET['report'] ?? 'daily_summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$payment_method = $_GET['payment_method'] ?? '';

switch ($report_type) {
    case 'daily_summary':
        $data = getDailyCollectionSummary($db, $start_date, $end_date);
        $headers = ['Date', 'Cash', 'Card', 'Check', 'Online', 'HMO', 'PhilHealth', 'Total'];
        $rows = [];
        
        foreach ($data as $row) {
            $check_amt = $row['check_amount'] ?? 0;
            $total = $row['cash'] + $row['card'] + $check_amt + $row['online'] + $row['hmo'] + $row['philhealth'];
            $rows[] = [
                date('Y-m-d', strtotime($row['payment_date'])),
                number_format($row['cash'], 2),
                number_format($row['card'], 2),
                number_format($check_amt, 2),
                number_format($row['online'], 2),
                number_format($row['hmo'], 2),
                number_format($row['philhealth'], 2),
                number_format($total, 2)
            ];
        }
        
        exportToExcel($rows, $headers, 'collection_daily_summary');
        break;
        
    case 'or_register':
        $data = getORRegister($db, $start_date, $end_date, $payment_method);
        $headers = ['OR Number', 'Date', 'Patient/HMO', 'Payment Method', 'Amount', 'Cashier'];
        $rows = [];
        
        foreach ($data as $row) {
            $patient_name = $row['first_name'] ? 
                $row['first_name'] . ' ' . $row['last_name'] : 
                ($row['hmo_name'] ?: 'N/A');
            
            $rows[] = [
                $row['or_number'],
                date('Y-m-d', strtotime($row['payment_date'])),
                $patient_name,
                $row['payment_method'],
                number_format($row['payment_amount'], 2),
                $row['employee_fname'] . ' ' . $row['employee_lname']
            ];
        }
        
        exportToExcel($rows, $headers, 'or_register');
        break;
        
    default:
        http_response_code(400);
        echo "Invalid report type";
        exit();
}