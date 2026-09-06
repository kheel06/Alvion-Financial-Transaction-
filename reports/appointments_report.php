<?php
/**
 * Appointments Report
 * View appointments by date/doctor with export functionality
 */
require_once '../config/config.php';
require_once '../includes/export_helper.php';
requireAuth();
checkRole(['admin', 'appointment_coordinator']);

$page_title = "Appointments Report";

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'all';
$doctor_id = $_GET['doctor_id'] ?? 'all';

// Build query - Check if tables exist
$room_join = "";
$room_select = "";
$doctor_specialty = "";

try {
    // Check if clinic_rooms table exists
    $check_rooms = $db->query("SHOW TABLES LIKE 'clinic_rooms'");
    if ($check_rooms->rowCount() > 0) {
        $room_join = "LEFT JOIN clinic_rooms r ON a.room_id = r.id";
        $room_select = "r.name as room_name,";
    }
    
    // Check if doctors table exists for specialty
    $check_doctors = $db->query("SHOW TABLES LIKE 'doctors'");
    if ($check_doctors->rowCount() > 0) {
        // Try to join doctors table via user_id or prc_number
        // First check if users table has a link to doctors
        $doctor_specialty = "LEFT JOIN doctors d ON (u.id = d.id OR u.email LIKE CONCAT('%', d.prc_number, '%'))";
        $room_select = $room_select . " d.specialty as specialization,";
    } else {
        $doctor_specialty = "";
        $room_select = $room_select . " NULL as specialization,";
    }
} catch (PDOException $e) {
    // Tables don't exist, skip joins
    $room_join = "";
    $room_select = "NULL as room_name, NULL as specialization,";
    $doctor_specialty = "";
}

// Build query - Check what columns exist in both tables
try {
    // Check patients table columns
    $check_patient_cols = $db->query("SHOW COLUMNS FROM patients");
    $patient_cols = $check_patient_cols->fetchAll(PDO::FETCH_COLUMN);
    $has_first_name = in_array('first_name', $patient_cols);
    $has_hospital_id = in_array('hospital_id', $patient_cols);
    $has_email = in_array('email', $patient_cols);
    $has_contact = in_array('contact_number', $patient_cols);
    
    // Check appointments table columns
    $check_appt_cols = $db->query("SHOW COLUMNS FROM appointments");
    $appt_cols = $check_appt_cols->fetchAll(PDO::FETCH_COLUMN);
    $has_patient_email = in_array('patient_email', $appt_cols);
    $has_patient_contact = in_array('patient_contact', $appt_cols);
    $has_department = in_array('department', $appt_cols);
    $has_is_walkin = in_array('is_walkin', $appt_cols);
    $has_booking_channel = in_array('booking_channel', $appt_cols);
    $has_appointment_number = in_array('appointment_number', $appt_cols);
    $has_appointment_type = in_array('appointment_type', $appt_cols);
    $has_status = in_array('status', $appt_cols);
    $has_appointment_date = in_array('appointment_date', $appt_cols);
    $has_appointment_time = in_array('appointment_time', $appt_cols);
} catch (PDOException $e) {
    // If tables don't exist or error, use safe defaults
    $has_first_name = false;
    $has_hospital_id = false;
    $has_email = false;
    $has_contact = false;
    $has_patient_email = false;
    $has_patient_contact = false;
    $has_department = false;
    $has_is_walkin = false;
    $has_booking_channel = false;
    $has_appointment_number = false;
    $has_appointment_type = false;
    $has_status = false;
    $has_appointment_date = false;
    $has_appointment_time = false;
}

// Build patient select based on what columns exist
if ($has_first_name) {
    // Use first_name and last_name from patients table
    $patient_select = "p.first_name as patient_fname, 
                      p.last_name as patient_lname,";
    $patient_hospital = $has_hospital_id ? "p.hospital_id," : "NULL as hospital_id,";
    $patient_join = "INNER JOIN patients p ON a.patient_id = p.id";
} else {
    // Fallback: use email/contact from patients table if available
    $patient_fname = $has_email ? "COALESCE(p.email, 'N/A')" : "'N/A'";
    $patient_lname = $has_contact ? "COALESCE(p.contact_number, 'N/A')" : "'N/A'";
    $patient_select = $patient_fname . " as patient_fname,
                      " . $patient_lname . " as patient_lname,";
    $patient_hospital = $has_hospital_id ? "COALESCE((SELECT hospital_id FROM patients WHERE id = a.patient_id LIMIT 1), 'N/A') as hospital_id," : "'N/A' as hospital_id,";
    $patient_join = "LEFT JOIN patients p ON a.patient_id = p.id";
}

// Build department select
$department_select = $has_department ? "a.department as appointment_department," : "NULL as appointment_department,";

// Build walkin/booking channel select
$walkin_select = $has_is_walkin ? "a.is_walkin," : ($has_booking_channel ? "CASE WHEN a.booking_channel = 'walkin' THEN 1 ELSE 0 END as is_walkin," : "0 as is_walkin,");

// Build appointment fields select (only select columns that exist)
$appointment_fields = "";
if ($has_appointment_number) {
    $appointment_fields .= "a.appointment_number,";
}
if ($has_appointment_type) {
    $appointment_fields .= "a.appointment_type,";
}
if ($has_status) {
    $appointment_fields .= "a.status,";
}
if ($has_appointment_date) {
    $appointment_fields .= "a.appointment_date,";
}
if ($has_appointment_time) {
    $appointment_fields .= "a.appointment_time,";
}

// Build the complete query
$query = "SELECT a.id,
         a.patient_id,
         a.doctor_id,
         " . $appointment_fields . "
         " . $patient_select . "
         " . $patient_hospital . "
         u.first_name as doctor_fname, 
         u.last_name as doctor_lname,
         " . $room_select . "
         " . $department_select . "
         " . $walkin_select . "
         a.created_at,
         a.updated_at
         FROM appointments a
         " . $patient_join . "
         LEFT JOIN users u ON a.doctor_id = u.id
         " . $doctor_specialty . "
         " . ($room_join ?: "") . "
         WHERE 1=1";

// Build WHERE clause - only use columns that exist
if ($has_appointment_date) {
    $query .= " AND a.appointment_date BETWEEN :start_date AND :end_date";
    $params = [':start_date' => $start_date, ':end_date' => $end_date];
} else {
    // If appointment_date doesn't exist, use created_at as fallback
    $query .= " AND DATE(a.created_at) BETWEEN :start_date AND :end_date";
    $params = [':start_date' => $start_date, ':end_date' => $end_date];
}

if ($status !== 'all' && $has_status) {
    $query .= " AND a.status = :status";
    $params[':status'] = $status;
}

if ($doctor_id !== 'all') {
    $query .= " AND a.doctor_id = :doctor_id";
    $params[':doctor_id'] = (int)$doctor_id;
}

// Build ORDER BY clause
if ($has_appointment_date && $has_appointment_time) {
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
} elseif ($has_appointment_date) {
    $query .= " ORDER BY a.appointment_date DESC";
} else {
    $query .= " ORDER BY a.created_at DESC";
}

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$appointments = $stmt->fetchAll();

// Handle export requests (Admin only)
if (isset($_GET['export']) && ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin') {
    $export_type = $_GET['export'];
    
    if ($export_type === 'excel' || $export_type === 'csv') {
        // Prepare export data
        $headers = ['Date', 'Time', 'Appointment Number', 'Patient Name', 'Hospital ID', 'Doctor', 'Specialization', 'Room', 'Type', 'Status', 'Walk-in'];
        $export_data = [];
        
        foreach ($appointments as $appt) {
            $export_data[] = [
                isset($appt['appointment_date']) ? formatDate($appt['appointment_date']) : 'N/A',
                isset($appt['appointment_time']) ? formatTime($appt['appointment_time']) : 'N/A',
                $appt['appointment_number'] ?? ($appt['id'] ?? 'N/A'),
                $appt['patient_fname'] . ' ' . $appt['patient_lname'],
                $appt['hospital_id'],
                $appt['doctor_fname'] ? 'Dr. ' . $appt['doctor_fname'] . ' ' . $appt['doctor_lname'] : 'Not assigned',
                $appt['specialization'] ?? 'N/A',
                !empty($appt['room_name']) ? $appt['room_name'] : (!empty($appt['appointment_department']) ? $appt['appointment_department'] : 'Unassigned'),
                isset($appt['appointment_type']) ? ucfirst($appt['appointment_type']) : 'N/A',
                isset($appt['status']) ? ucfirst(str_replace('_', ' ', $appt['status'])) : 'N/A',
                (isset($appt['is_walkin']) && $appt['is_walkin']) ? 'Yes' : 'No'
            ];
        }
        
        if ($export_type === 'csv') {
            exportToCSV($export_data, $headers, 'appointments_report');
        } else {
            exportToExcel($export_data, $headers, 'appointments_report');
        }
    } elseif ($export_type === 'pdf') {
        // Generate PDF view
        $html = '<div class="header">
            <h1>Appointments Report</h1>
            <p>Period: ' . formatDate($start_date) . ' to ' . formatDate($end_date) . '</p>
            <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        </div>';
        
        if (count($appointments) > 0) {
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Hospital ID</th>
                        <th>Doctor</th>
                        <th>Room/Department</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($appointments as $appt) {
                $appt_date = isset($appt['appointment_date']) ? formatDate($appt['appointment_date']) : (isset($appt['created_at']) ? formatDate($appt['created_at']) : 'N/A');
                $appt_time = isset($appt['appointment_time']) ? formatTime($appt['appointment_time']) : (isset($appt['created_at']) ? formatTime($appt['created_at']) : 'N/A');
                $room_display = !empty($appt['room_name']) ? htmlspecialchars($appt['room_name']) : (!empty($appt['appointment_department']) ? htmlspecialchars($appt['appointment_department']) : 'Unassigned');
                $html .= '<tr>
                    <td>' . $appt_date . '</td>
                    <td>' . $appt_time . '</td>
                    <td>' . htmlspecialchars($appt['patient_fname'] . ' ' . $appt['patient_lname']) . '</td>
                    <td>' . htmlspecialchars($appt['hospital_id']) . '</td>
                    <td>' . ($appt['doctor_fname'] ? 'Dr. ' . htmlspecialchars($appt['doctor_fname'] . ' ' . $appt['doctor_lname']) : 'Not assigned') . '</td>
                    <td>' . $room_display . '</td>
                    <td>' . (isset($appt['appointment_type']) ? ucfirst($appt['appointment_type']) : 'N/A') . '</td>
                    <td>' . (isset($appt['status']) ? ucfirst(str_replace('_', ' ', $appt['status'])) : 'N/A') . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>No appointments found for the selected period.</p>';
        }
        
        $html .= '<div class="footer">
            <p>Total Appointments: ' . count($appointments) . '</p>
        </div>';
        
        generatePDFView($html, 'Appointments Report');
    }
}

// Get statistics - build query based on available columns
$stats_where = $has_appointment_date ? "appointment_date BETWEEN :start_date AND :end_date" : "DATE(created_at) BETWEEN :start_date AND :end_date";
$stats_walkin = $has_is_walkin ? "SUM(CASE WHEN is_walkin = 1 THEN 1 ELSE 0 END) as walkins," : ($has_booking_channel ? "SUM(CASE WHEN booking_channel = 'walkin' THEN 1 ELSE 0 END) as walkins," : "0 as walkins,");
$stats_status = $has_status ? "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show," : "0 as completed, 0 as cancelled, 0 as no_show,";

$stats_query = "SELECT 
    COUNT(*) as total,
    " . $stats_status . "
    " . $stats_walkin . "
    0 as scheduled
    FROM appointments
    WHERE " . $stats_where;
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':start_date', $start_date);
$stats_stmt->bindParam(':end_date', $end_date);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get doctors for filter
try {
    $doctors_query = "SELECT id, first_name, last_name, specialization FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY first_name";
    $doctors_stmt = $db->prepare($doctors_query);
    $doctors_stmt->execute();
    $doctors = $doctors_stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Appointments Report</h1>
    <p class="text-gray-600">View and analyze appointment data</p>
</div>

<!-- Filters -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="no_show" <?php echo $status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Doctor</label>
                <select name="doctor_id"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="all" <?php echo $doctor_id === 'all' ? 'selected' : ''; ?>>All Doctors</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_id == $doctor['id'] ? 'selected' : ''; ?>>
                            Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?>
                        </option>
                    <?php endforeach; ?>
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
<div class="grid grid-cols-1 gap-6 sm:grid-cols-5 mb-6">
    <div class="bg-white shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Total Appointments</p>
        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
    </div>
    <div class="bg-white shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Completed</p>
        <p class="text-2xl font-bold text-green-600"><?php echo $stats['completed']; ?></p>
    </div>
    <div class="bg-white shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Cancelled</p>
        <p class="text-2xl font-bold text-red-600"><?php echo $stats['cancelled']; ?></p>
    </div>
    <div class="bg-white shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">No Show</p>
        <p class="text-2xl font-bold text-orange-600"><?php echo $stats['no_show']; ?></p>
    </div>
    <div class="bg-white shadow rounded-lg p-4">
        <p class="text-sm text-gray-500">Walk-ins</p>
        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['walkins']; ?></p>
    </div>
</div>

<!-- Report Table -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Appointment Details</h3>
        <div class="flex space-x-2">
            <?php 
            $is_admin = ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin';
            if ($is_admin): 
            ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>Export Excel
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>Export CSV
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" 
               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>Export PDF
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 9V2a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v7"></path><rect x="6" y="14" width="12" height="8" rx="1"></rect></svg>Print
            </button>
        </div>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($appointments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Doctor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php 
                                    if (isset($appt['appointment_date'])) {
                                        echo formatDate($appt['appointment_date']);
                                    } elseif (isset($appt['created_at'])) {
                                        echo formatDate($appt['created_at']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?><br>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php 
                                        if (isset($appt['appointment_time'])) {
                                            echo formatTime($appt['appointment_time']);
                                        } elseif (isset($appt['created_at'])) {
                                            echo formatTime($appt['created_at']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo $appt['patient_fname'] . ' ' . $appt['patient_lname']; ?><br>
                                    <span class="text-xs text-gray-500"><?php echo $appt['hospital_id']; ?></span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php if ($appt['doctor_fname']): ?>
                                        Dr. <?php echo $appt['doctor_fname'] . ' ' . $appt['doctor_lname']; ?><br>
                                        <span class="text-xs text-gray-500"><?php echo $appt['specialization']; ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php 
                                    if (!empty($appt['room_name'])) {
                                        echo htmlspecialchars($appt['room_name']);
                                    } elseif (!empty($appt['appointment_department'])) {
                                        echo htmlspecialchars($appt['appointment_department']);
                                    } else {
                                        echo 'Unassigned';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo isset($appt['appointment_type']) ? ucfirst($appt['appointment_type']) : 'N/A'; ?>
                                    <?php if (isset($appt['is_walkin']) && $appt['is_walkin']): ?>
                                        <span class="ml-1 text-xs text-blue-600 dark:text-blue-400">(Walk-in)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php if (isset($appt['status'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            $status_badge = getStatusBadge($appt['status']);
                                            echo $status_badge == 'blue' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '';
                                            echo $status_badge == 'green' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '';
                                            echo $status_badge == 'red' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '';
                                            echo $status_badge == 'orange' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : '';
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appt['status'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m14 14-4-4"></path><path d="m10 14 4-4"></path></svg>
                <p class="text-gray-500">No appointments found for the selected period</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



