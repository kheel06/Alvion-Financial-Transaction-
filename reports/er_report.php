<?php
/**
 * ER Patient Statistics Report
 * Emergency Room patient statistics and triage reports with export functionality
 */
require_once '../config/config.php';
require_once '../includes/export_helper.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$page_title = "ER Patient Statistics Report";

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$triage_level = $_GET['triage_level'] ?? 'all';
$status = $_GET['status'] ?? 'all';

try {
    // Check if er_triage table exists
    $check_er_triage = $db->query("SHOW TABLES LIKE 'er_triage'");
    $er_triage_exists = $check_er_triage->rowCount() > 0;
    
    // Check patients table columns
    try {
        $check_patient_cols = $db->query("SHOW COLUMNS FROM patients");
        $patient_cols = $check_patient_cols->fetchAll(PDO::FETCH_COLUMN);
        $has_first_name = in_array('first_name', $patient_cols);
        $has_hospital_id = in_array('hospital_id', $patient_cols);
        $has_age = in_array('age', $patient_cols);
        $has_gender = in_array('gender', $patient_cols);
        $has_email = in_array('email', $patient_cols);
        $has_contact = in_array('contact_number', $patient_cols);
    } catch (PDOException $e) {
        $has_first_name = false;
        $has_hospital_id = false;
        $has_age = false;
        $has_gender = false;
        $has_email = false;
        $has_contact = false;
    }
    
    if (!$er_triage_exists) {
        $_SESSION['error'] = "ER Triage table does not exist. Please run the database/er_triage.sql file to create it.";
        $er_cases = [];
        $stats = [
            'total_cases' => 0,
            'resuscitation' => 0,
            'emergency' => 0,
            'urgent' => 0,
            'semi_urgent' => 0,
            'non_urgent' => 0,
            'waiting' => 0,
            'in_progress' => 0,
            'admitted' => 0,
            'discharged' => 0,
            'transferred' => 0
        ];
    } else {
        // Build query for ER triage data
        if ($has_first_name) {
            $patient_select = "p.first_name, p.last_name,";
        } else {
            // Fallback: use email/contact if available, otherwise use N/A
            $first_name_fallback = $has_email ? "COALESCE(p.email, 'N/A')" : "'N/A'";
            $last_name_fallback = $has_contact ? "COALESCE(p.contact_number, 'N/A')" : "'N/A'";
            $patient_select = $first_name_fallback . " as first_name, " . $last_name_fallback . " as last_name,";
        }
        
        $hospital_select = $has_hospital_id 
            ? "p.hospital_id," 
            : "'N/A' as hospital_id,";
        
        $age_select = $has_age 
            ? "p.age," 
            : "NULL as age,";
        
        $gender_select = $has_gender 
            ? "p.gender," 
            : "NULL as gender,";
        
        $query = "SELECT et.*, " . $patient_select . "
                 " . $hospital_select . "
                 " . $age_select . "
                 " . $gender_select . "
                 u.first_name as nurse_fname, u.last_name as nurse_lname
                 FROM er_triage et
                 INNER JOIN patients p ON et.patient_id = p.id
                 LEFT JOIN users u ON et.triage_nurse_id = u.id
                 WHERE DATE(et.created_at) BETWEEN :start_date AND :end_date";

        $params = [':start_date' => $start_date, ':end_date' => $end_date];

        if ($triage_level !== 'all') {
            $query .= " AND et.triage_level = :triage_level";
            $params[':triage_level'] = $triage_level;
        }

        if ($status !== 'all') {
            $query .= " AND et.status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY et.created_at DESC";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $er_cases = $stmt->fetchAll();

        // Get statistics
        $stats_query = "SELECT 
            COUNT(*) as total_cases,
            COALESCE(SUM(CASE WHEN triage_level = 'resuscitation' THEN 1 ELSE 0 END), 0) as resuscitation,
            COALESCE(SUM(CASE WHEN triage_level = 'emergency' THEN 1 ELSE 0 END), 0) as emergency,
            COALESCE(SUM(CASE WHEN triage_level = 'urgent' THEN 1 ELSE 0 END), 0) as urgent,
            COALESCE(SUM(CASE WHEN triage_level = 'semi_urgent' THEN 1 ELSE 0 END), 0) as semi_urgent,
            COALESCE(SUM(CASE WHEN triage_level = 'non_urgent' THEN 1 ELSE 0 END), 0) as non_urgent,
            COALESCE(SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END), 0) as waiting,
            COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress,
            COALESCE(SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END), 0) as admitted,
            COALESCE(SUM(CASE WHEN status = 'discharged' THEN 1 ELSE 0 END), 0) as discharged,
            COALESCE(SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END), 0) as transferred
            FROM er_triage
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindParam(':start_date', $start_date);
        $stats_stmt->bindParam(':end_date', $end_date);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error generating report: " . $e->getMessage();
    $er_cases = [];
    $stats = [
        'total_cases' => 0,
        'resuscitation' => 0,
        'emergency' => 0,
        'urgent' => 0,
        'semi_urgent' => 0,
        'non_urgent' => 0,
        'waiting' => 0,
        'in_progress' => 0,
        'admitted' => 0,
        'discharged' => 0,
        'transferred' => 0
    ];
}

// Handle export requests
if (isset($_GET['export']) && ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin') {
    $export_type = $_GET['export'];
    
    if ($export_type === 'excel' || $export_type === 'csv') {
        $headers = ['Date & Time', 'Patient Name', 'Hospital ID', 'Age', 'Gender', 'Chief Complaint', 'Triage Level', 'Priority Score', 'Status', 'Triage Nurse'];
        $export_data = [];
        
        foreach ($er_cases as $case) {
            $export_data[] = [
                formatDate($case['created_at']) . ' ' . formatTime($case['created_at']),
                $case['first_name'] . ' ' . $case['last_name'],
                $case['hospital_id'],
                $case['age'] ?? 'N/A',
                ucfirst($case['gender'] ?? 'N/A'),
                $case['chief_complaint'],
                ucfirst(str_replace('_', ' ', $case['triage_level'])),
                $case['priority_score'] ?? 'N/A',
                ucfirst(str_replace('_', ' ', $case['status'])),
                $case['nurse_fname'] ? $case['nurse_fname'] . ' ' . $case['nurse_lname'] : 'N/A'
            ];
        }
        
        if ($export_type === 'csv') {
            exportToCSV($export_data, $headers, 'er_patient_statistics');
        } else {
            exportToExcel($export_data, $headers, 'er_patient_statistics');
        }
    } elseif ($export_type === 'pdf') {
        $html = '<div class="header">
            <h1>ER Patient Statistics Report</h1>
            <p>Period: ' . formatDate($start_date) . ' to ' . formatDate($end_date) . '</p>
            <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        </div>';
        
        $html .= '<div style="margin-bottom: 20px;">
            <h2>Summary</h2>
            <p>Total Cases: ' . ($stats['total_cases'] ?? 0) . '</p>
            <p>Resuscitation: ' . ($stats['resuscitation'] ?? 0) . '</p>
            <p>Emergency: ' . ($stats['emergency'] ?? 0) . '</p>
            <p>Urgent: ' . ($stats['urgent'] ?? 0) . '</p>
            <p>Semi-Urgent: ' . ($stats['semi_urgent'] ?? 0) . '</p>
            <p>Non-Urgent: ' . ($stats['non_urgent'] ?? 0) . '</p>
        </div>';
        
        if (count($er_cases) > 0) {
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Chief Complaint</th>
                        <th>Triage Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($er_cases as $case) {
                $html .= '<tr>
                    <td>' . formatDate($case['created_at']) . ' ' . formatTime($case['created_at']) . '</td>
                    <td>' . htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) . '</td>
                    <td>' . htmlspecialchars($case['chief_complaint']) . '</td>
                    <td>' . ucfirst(str_replace('_', ' ', $case['triage_level'])) . '</td>
                    <td>' . ucfirst(str_replace('_', ' ', $case['status'])) . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>No ER cases found for the selected period.</p>';
        }
        
        generatePDFView($html, 'ER Patient Statistics Report');
    }
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">📊 ER Patient Statistics Report</h1>
    <p class="text-gray-600 dark:text-gray-400">Emergency Room patient statistics and triage reports</p>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Triage Level</label>
                <select name="triage_level"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                    <option value="all" <?php echo $triage_level === 'all' ? 'selected' : ''; ?>>All Levels</option>
                    <option value="resuscitation" <?php echo $triage_level === 'resuscitation' ? 'selected' : ''; ?>>Resuscitation</option>
                    <option value="emergency" <?php echo $triage_level === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                    <option value="urgent" <?php echo $triage_level === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="semi_urgent" <?php echo $triage_level === 'semi_urgent' ? 'selected' : ''; ?>>Semi-Urgent</option>
                    <option value="non_urgent" <?php echo $triage_level === 'non_urgent' ? 'selected' : ''; ?>>Non-Urgent</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="waiting" <?php echo $status === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="admitted" <?php echo $status === 'admitted' ? 'selected' : ''; ?>>Admitted</option>
                    <option value="discharged" <?php echo $status === 'discharged' ? 'selected' : ''; ?>>Discharged</option>
                    <option value="transferred" <?php echo $status === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                </select>
            </div>
            <div class="sm:col-span-4">
                <button type="submit"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5 mb-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total Cases</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $stats['total_cases'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Resuscitation</p>
        <p class="text-2xl font-bold text-red-600 dark:text-red-400"><?php echo $stats['resuscitation'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Emergency</p>
        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400"><?php echo $stats['emergency'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Urgent</p>
        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400"><?php echo $stats['urgent'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Waiting</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo $stats['waiting'] ?? 0; ?></p>
    </div>
</div>

<!-- Report Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">ER Patient Cases</h3>
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
            <button onclick="window.print()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 9V2a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v7"></path><rect x="6" y="14" width="12" height="8" rx="1"></rect></svg>Print
            </button>
        </div>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($er_cases) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date & Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Chief Complaint</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Triage Level</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Priority Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($er_cases as $case): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo formatDate($case['created_at']); ?><br>
                                    <span class="text-xs text-gray-500"><?php echo formatTime($case['created_at']); ?></span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?><br>
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars($case['hospital_id']); ?></span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($case['chief_complaint']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        echo $case['triage_level'] === 'resuscitation' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '';
                                        echo $case['triage_level'] === 'emergency' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : '';
                                        echo $case['triage_level'] === 'urgent' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '';
                                        echo $case['triage_level'] === 'semi_urgent' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '';
                                        echo $case['triage_level'] === 'non_urgent' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case['triage_level'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo $case['priority_score'] ?? 'N/A'; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        echo $case['status'] === 'waiting' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '';
                                        echo $case['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '';
                                        echo $case['status'] === 'admitted' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : '';
                                        echo $case['status'] === 'discharged' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '';
                                        echo $case['status'] === 'transferred' ? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' : '';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-2"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                <p class="text-gray-500 dark:text-gray-400">No ER cases found for the selected period</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

