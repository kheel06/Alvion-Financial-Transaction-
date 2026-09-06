<?php
/**
 * Export Helper Functions
 * Provides Excel, CSV (with password protection), and PDF export functionality for reports
 * Hospital Financial System - Formal Export Module
 */

// CSV Export Password - Change this to your desired password
define('CSV_EXPORT_PASSWORD', 'hospital2024');

/**
 * Verify CSV export password
 * 
 * @param string $password Password to verify
 * @return bool True if password is correct
 */
function verifyExportPassword($password) {
    return $password === CSV_EXPORT_PASSWORD;
}

/**
 * Export data to Excel (CSV format)
 * 
 * @param array $data Array of data rows
 * @param array $headers Column headers
 * @param string $filename Output filename
 * @param string $report_title Report title for header
 * @param string $period_info Period information
 */
function exportToExcel($data, $headers, $filename = 'report', $report_title = '', $period_info = '') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html><head><meta charset="UTF-8"></head><body>';
    
    // Hospital Header
    echo '<table border="0" style="margin-bottom: 20px;">';
    echo '<tr><td colspan="' . count($headers) . '" style="text-align: center; font-size: 18px; font-weight: bold;">HOSPITAL FINANCIAL SYSTEM</td></tr>';
    if ($report_title) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align: center; font-size: 14px; font-weight: bold;">' . htmlspecialchars($report_title) . '</td></tr>';
    }
    if ($period_info) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align: center; font-size: 12px;">Period: ' . htmlspecialchars($period_info) . '</td></tr>';
    }
    echo '<tr><td colspan="' . count($headers) . '" style="text-align: center; font-size: 10px;">Generated: ' . date('F j, Y g:i A') . '</td></tr>';
    echo '</table>';
    
    echo '<table border="1">';
    
    // Headers
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th style="background-color: #1e40af; color: white; padding: 8px; font-weight: bold;">' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    // Data rows
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td style="padding: 5px;">' . htmlspecialchars($cell ?? '') . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

/**
 * Export data to CSV with password protection verification
 * 
 * @param array $data Array of data rows
 * @param array $headers Column headers
 * @param string $filename Output filename
 * @param string $report_title Report title
 * @param string $period_info Period information
 */
function exportToCSV($data, $headers, $filename = 'report', $report_title = '', $period_info = '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Hospital Header Information
    fputcsv($output, ['HOSPITAL FINANCIAL SYSTEM']);
    if ($report_title) {
        fputcsv($output, [$report_title]);
    }
    if ($period_info) {
        fputcsv($output, ['Period: ' . $period_info]);
    }
    fputcsv($output, ['Generated: ' . date('F j, Y g:i A')]);
    fputcsv($output, []); // Empty row for spacing
    
    // Headers
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

/**
 * Check if CSV export requires password verification
 * Returns HTML form if password not verified
 * 
 * @param string $export_type Type of export
 * @return bool True if verified, false if needs verification
 */
function requireExportPassword($export_type = 'csv') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if password was already verified in this session
    if (isset($_SESSION['csv_export_verified']) && $_SESSION['csv_export_verified'] === true) {
        return true;
    }
    
    // Check if password is being submitted
    if (isset($_POST['csv_export_password'])) {
        if (verifyExportPassword($_POST['csv_export_password'])) {
            $_SESSION['csv_export_verified'] = true;
            return true;
        } else {
            return false; // Wrong password
        }
    }
    
    return false; // Not verified
}

/**
 * Display password verification form for CSV export
 */
function displayExportPasswordForm($return_url = '', $error = false) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Verification - Hospital Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Export Verification</h2>
            <p class="text-gray-600 mt-2">Enter the export password to download the CSV file</p>
        </div>
        
        ' . ($error ? '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            <p class="text-sm font-medium">Invalid password. Please try again.</p>
        </div>' : '') . '
        
        <form method="POST" action="' . htmlspecialchars($return_url) . '">
            <div class="mb-4">
                <label for="csv_export_password" class="block text-sm font-medium text-gray-700 mb-2">Export Password</label>
                <input type="password" id="csv_export_password" name="csv_export_password" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter password">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                Verify & Download
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="javascript:history.back()" class="text-sm text-gray-600 hover:text-gray-900">← Go Back</a>
        </div>
    </div>
</body>
</html>';
    exit();
}

/**
 * Generate PDF export (using browser print to PDF)
 * This creates a print-friendly version that can be saved as PDF
 * 
 * @param string $html HTML content to print
 * @param string $title Document title
 */
function generatePDFView($html, $title = 'Report') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @media print {
            @page { margin: 1cm; }
            body { margin: 0; }
            .no-print { display: none; }
        }
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4F46E5; color: white; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #4F46E5; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px;">
            Close
        </button>
    </div>
    ' . $html . '
</body>
</html>';
    exit();
}

