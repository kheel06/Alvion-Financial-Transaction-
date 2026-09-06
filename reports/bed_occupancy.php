<?php
/**
 * Bed Occupancy Report
 * Hospital bed utilization and occupancy statistics with export functionality
 */
require_once '../config/config.php';
require_once '../includes/export_helper.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor', 'billing_staff']);

$page_title = "Bed Occupancy Report";

// Get date range from request or default to current month
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-t');

try {
    // Check if wards table exists
    $check_wards = $db->query("SHOW TABLES LIKE 'wards'");
    $wards_exists = $check_wards->rowCount() > 0;
    
    if ($wards_exists) {
        // Ward occupancy statistics
        $ward_stats_query = "SELECT 
            w.ward_name,
            w.ward_code,
            w.capacity,
            COUNT(b.id) as total_beds,
            COUNT(CASE WHEN b.status = 'occupied' THEN 1 END) as occupied_beds,
            COUNT(CASE WHEN b.status = 'available' THEN 1 END) as available_beds,
            COUNT(CASE WHEN b.status = 'maintenance' THEN 1 END) as maintenance_beds,
            ROUND((COUNT(CASE WHEN b.status = 'occupied' THEN 1 END) / NULLIF(COUNT(b.id), 0)) * 100, 2) as occupancy_rate
            FROM wards w
            LEFT JOIN beds b ON w.id = b.ward_id
            GROUP BY w.id
            ORDER BY w.ward_name";
        $ward_stats_stmt = $db->prepare($ward_stats_query);
        $ward_stats_stmt->execute();
        $ward_stats = $ward_stats_stmt->fetchAll();
    } else {
        // Fallback: Get bed statistics without wards
        $ward_stats_query = "SELECT 
            'General Ward' as ward_name,
            'GEN-ALL' as ward_code,
            COUNT(*) as capacity,
            COUNT(*) as total_beds,
            COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_beds,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_beds,
            COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_beds,
            ROUND((COUNT(CASE WHEN status = 'occupied' THEN 1 END) / NULLIF(COUNT(*), 0)) * 100, 2) as occupancy_rate
            FROM beds";
        $ward_stats_stmt = $db->prepare($ward_stats_query);
        $ward_stats_stmt->execute();
        $ward_stats = $ward_stats_stmt->fetchAll();
    }

    // Daily occupancy trend for the selected period
    // Check if admissions table exists
    $check_admissions = $db->query("SHOW TABLES LIKE 'admissions'");
    $admissions_exists = $check_admissions->rowCount() > 0;
    
    if ($admissions_exists) {
        $trend_query = "SELECT 
            DATE(a.admission_date) as date,
            COUNT(*) as admissions,
            (SELECT COUNT(*) FROM admissions WHERE status = 'admitted' AND DATE(admission_date) <= DATE(a.admission_date)) as total_inpatients
            FROM admissions a
            WHERE a.admission_date BETWEEN :start_date AND :end_date
            GROUP BY DATE(a.admission_date)
            ORDER BY date";
        $trend_stmt = $db->prepare($trend_query);
        $trend_stmt->bindParam(':start_date', $start_date);
        $trend_stmt->bindParam(':end_date', $end_date);
        $trend_stmt->execute();
        $occupancy_trend = $trend_stmt->fetchAll();
    } else {
        // Fallback: use bed status data
        $trend_query = "SELECT 
            DATE(b.updated_at) as date,
            COUNT(CASE WHEN b.status = 'occupied' THEN 1 END) as admissions,
            COUNT(CASE WHEN b.status = 'occupied' THEN 1 END) as total_inpatients
            FROM beds b
            WHERE DATE(b.updated_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(b.updated_at)
            ORDER BY date";
        $trend_stmt = $db->prepare($trend_query);
        $trend_stmt->bindParam(':start_date', $start_date);
        $trend_stmt->bindParam(':end_date', $end_date);
        $trend_stmt->execute();
        $occupancy_trend = $trend_stmt->fetchAll();
    }

    // Current occupancy summary
    $current_occupancy_query = "SELECT 
        COUNT(*) as total_beds,
        COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_beds,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available_beds,
        COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_beds,
        ROUND((COUNT(CASE WHEN status = 'occupied' THEN 1 END) / COUNT(*)) * 100, 2) as overall_occupancy_rate
        FROM beds";
    $current_occupancy_stmt = $db->prepare($current_occupancy_query);
    $current_occupancy_stmt->execute();
    $current_occupancy = $current_occupancy_stmt->fetch();

    // Handle export requests
    if (isset($_GET['export']) && ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin') {
        $export_type = $_GET['export'];
        
        if ($export_type === 'excel' || $export_type === 'csv') {
            $headers = ['Ward', 'Ward Code', 'Capacity', 'Total Beds', 'Occupied', 'Available', 'Maintenance', 'Occupancy Rate %'];
            $export_data = [];
            
            foreach ($ward_stats as $ward) {
                $export_data[] = [
                    $ward['ward_name'],
                    $ward['ward_code'] ?? 'N/A',
                    $ward['capacity'] ?? 0,
                    $ward['total_beds'],
                    $ward['occupied_beds'],
                    $ward['available_beds'],
                    $ward['maintenance_beds'] ?? 0,
                    number_format($ward['occupancy_rate'], 2) . '%'
                ];
            }
            
            if ($export_type === 'csv') {
                exportToCSV($export_data, $headers, 'bed_occupancy_report');
            } else {
                exportToExcel($export_data, $headers, 'bed_occupancy_report');
            }
        } elseif ($export_type === 'pdf') {
            $html = '<div class="header">
                <h1>Bed Occupancy Report</h1>
                <p>Period: ' . formatDate($start_date) . ' to ' . formatDate($end_date) . '</p>
                <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
            </div>';
            
            $html .= '<div style="margin-bottom: 20px;">
                <h2>Summary</h2>
                <p>Total Beds: ' . ($current_occupancy['total_beds'] ?? 0) . '</p>
                <p>Occupied Beds: ' . ($current_occupancy['occupied_beds'] ?? 0) . '</p>
                <p>Available Beds: ' . ($current_occupancy['available_beds'] ?? 0) . '</p>
                <p>Overall Occupancy Rate: ' . ($current_occupancy['overall_occupancy_rate'] ?? 0) . '%</p>
            </div>';
            
            if (count($ward_stats) > 0) {
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>Capacity</th>
                            <th>Total Beds</th>
                            <th>Occupied</th>
                            <th>Available</th>
                            <th>Occupancy Rate</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($ward_stats as $ward) {
                    $html .= '<tr>
                        <td>' . htmlspecialchars($ward['ward_name']) . '</td>
                        <td>' . ($ward['capacity'] ?? 0) . '</td>
                        <td>' . $ward['total_beds'] . '</td>
                        <td>' . $ward['occupied_beds'] . '</td>
                        <td>' . $ward['available_beds'] . '</td>
                        <td>' . number_format($ward['occupancy_rate'], 2) . '%</td>
                    </tr>';
                }
                
                $html .= '</tbody></table>';
            }
            
            generatePDFView($html, 'Bed Occupancy Report');
        }
    }

} catch (PDOException $exception) {
    $_SESSION['error'] = "Error generating report: " . $exception->getMessage();
    $ward_stats = [];
    $occupancy_trend = [];
    $current_occupancy = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">📊 Bed Occupancy Report</h1>
    <p class="text-gray-600 dark:text-gray-400">Hospital bed utilization and occupancy statistics</p>
</div>

<!-- Report Filters -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" 
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" 
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>

            <div class="flex items-end">
                <button type="submit" 
                    class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Current Occupancy Summary -->
<div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 lg:grid-cols-4">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Beds</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $current_occupancy['total_beds'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Available</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $current_occupancy['available_beds'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><circle cx="13" cy="13" r="1"></circle><circle cx="11" cy="13" r="1"></circle></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Occupied</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $current_occupancy['occupied_beds'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Occupancy Rate</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $current_occupancy['overall_occupancy_rate'] ?? 0; ?>%</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Ward-wise Occupancy -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Ward-wise Occupancy
            </h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ward
                            </th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Capacity
                            </th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Occupied
                            </th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rate
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($ward_stats as $ward): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $ward['ward_name']; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $ward['total_beds']; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $ward['occupied_beds']; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $ward['occupancy_rate'] > 90 ? 'bg-red-100 text-red-800' : ''; ?>
                                        <?php echo $ward['occupancy_rate'] > 70 && $ward['occupancy_rate'] <= 90 ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                        <?php echo $ward['occupancy_rate'] <= 70 ? 'bg-green-100 text-green-800' : ''; ?>">
                                        <?php echo $ward['occupancy_rate']; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Occupancy Trend -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Occupancy Trend
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                Daily patient admissions and occupancy
            </p>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($occupancy_trend) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($occupancy_trend as $day): ?>
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-900">
                                <?php echo formatDate($day['date'], 'M j'); ?>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="text-sm text-gray-500">
                                    <?php echo $day['admissions']; ?> admissions
                                </span>
                                <span class="text-sm font-medium text-primary-600">
                                    <?php echo $day['total_inpatients']; ?> inpatients
                                </span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-primary-600 h-2 rounded-full" 
                                style="width: <?php echo min(100, ($day['total_inpatients'] / ($current_occupancy['total_beds'] ?? 1)) * 100); ?>%">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
                    <p class="text-gray-500">No data available for the selected period</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex justify-between items-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Export Report
            </h3>
            <div class="flex space-x-2">
                <?php 
                $is_admin = ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin';
                if ($is_admin): 
                ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>Export Excel
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>Export CSV
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" 
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>Export PDF
                </a>
                <?php endif; ?>
                <button onclick="window.print()" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 9V2a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v7"></path><rect x="6" y="14" width="12" height="8" rx="1"></rect></svg>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden table for export -->
<div id="wardTable" class="hidden">
    <table>
        <thead>
            <tr>
                <th>Ward</th>
                <th>Capacity</th>
                <th>Occupied</th>
                <th>Available</th>
                <th>Occupancy Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ward_stats as $ward): ?>
                <tr>
                    <td><?php echo $ward['ward_name']; ?></td>
                    <td><?php echo $ward['total_beds']; ?></td>
                    <td><?php echo $ward['occupied_beds']; ?></td>
                    <td><?php echo $ward['available_beds']; ?></td>
                    <td><?php echo $ward['occupancy_rate']; ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="reportContent" class="hidden">
    <div class="p-8">
        <h1 class="text-2xl font-bold mb-4">Bed Occupancy Report - <?php echo SITE_NAME; ?></h1>
        <p class="text-gray-600 mb-6">Period: <?php echo formatDate($start_date) . ' to ' . formatDate($end_date); ?></p>
        
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-2">Summary</h2>
            <p>Total Beds: <?php echo $current_occupancy['total_beds'] ?? 0; ?></p>
            <p>Occupied Beds: <?php echo $current_occupancy['occupied_beds'] ?? 0; ?></p>
            <p>Available Beds: <?php echo $current_occupancy['available_beds'] ?? 0; ?></p>
            <p>Overall Occupancy Rate: <?php echo $current_occupancy['overall_occupancy_rate'] ?? 0; ?>%</p>
        </div>

        <h2 class="text-lg font-semibold mb-2">Ward-wise Occupancy</h2>
        <table class="min-w-full border">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border p-2">Ward</th>
                    <th class="border p-2">Capacity</th>
                    <th class="border p-2">Occupied</th>
                    <th class="border p-2">Occupancy Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ward_stats as $ward): ?>
                    <tr>
                        <td class="border p-2"><?php echo $ward['ward_name']; ?></td>
                        <td class="border p-2"><?php echo $ward['total_beds']; ?></td>
                        <td class="border p-2"><?php echo $ward['occupied_beds']; ?></td>
                        <td class="border p-2"><?php echo $ward['occupancy_rate']; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="mt-6 text-sm text-gray-500">Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>