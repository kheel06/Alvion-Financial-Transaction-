<?php
require_once 'config/config.php';
// Redirect unauthenticated visitors to employee login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/employee-login.php');
    exit();
}

// Authenticated users continue to the dashboard
requireAuth();

// Redirect to role-specific dashboard
$role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'employee';
$normalized_role = normalizeRoleName($role_name);

// Map roles to dashboard files (relative to project root)
// These point to the dedicated dashboard files per user type.
$dashboard_map = [
    'super admin' => 'super_admin/super_admin-dashboard.php',
    'admin'       => 'admin/admin-dashboard.php',
    'staff'       => 'staff/staff-dashboard.php',
    'employee'    => 'employee/employee-dashboard.php',
];

// Redirect to role-specific dashboard if the file exists
if (isset($dashboard_map[$normalized_role])) {
    $dashboard_path = $dashboard_map[$normalized_role];
    $absolute_path  = __DIR__ . '/' . $dashboard_path;
    if (file_exists($absolute_path)) {
        header('Location: ' . BASE_URL . '/' . $dashboard_path);
        exit;
    }
}

// Fallback to default dashboard
$page_title = "Dashboard";

// Get statistics
try {
    // Patient Statistics
    $patient_query = "SELECT 
        COUNT(*) as total_patients,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_patients
        FROM patients";
    $patient_stmt = $db->prepare($patient_query);
    $patient_stmt->execute();
    $patient_stats = $patient_stmt->fetch();

    // Appointment Statistics
    $appointment_query = "SELECT 
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 END) as today_appointments
        FROM appointments";
    $appointment_stmt = $db->prepare($appointment_query);
    $appointment_stmt->execute();
    $appointment_stats = $appointment_stmt->fetch();

    // ER Triage Statistics
    $er_query = "SELECT 
        COUNT(*) as total_triage,
        COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
        COUNT(CASE WHEN triage_level = 'emergency' THEN 1 END) as emergencies
        FROM er_triage 
        WHERE DATE(created_at) = CURDATE()";
    $er_stmt = $db->prepare($er_query);
    $er_stmt->execute();
    $er_stats = $er_stmt->fetch();

    // Bed Statistics
    $bed_query = "SELECT 
        COUNT(*) as total_beds,
        COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_beds,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available_beds
        FROM beds";
    $bed_stmt = $db->prepare($bed_query);
    $bed_stmt->execute();
    $bed_stats = $bed_stmt->fetch();

    // Today's Appointments
    $today_appointments_query = "SELECT a.*, p.first_name, p.last_name, p.hospital_id, u.first_name as doctor_fname, u.last_name as doctor_lname
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.appointment_date = CURDATE() 
        AND a.status IN ('scheduled', 'confirmed', 'in_progress')
        ORDER BY a.appointment_time ASC 
        LIMIT 5";
    $today_appointments_stmt = $db->prepare($today_appointments_query);
    $today_appointments_stmt->execute();
    $today_appointments = $today_appointments_stmt->fetchAll();

    // Recent Patients
    $recent_patients_query = "SELECT * FROM patients 
        ORDER BY created_at DESC 
        LIMIT 5";
    $recent_patients_stmt = $db->prepare($recent_patients_query);
    $recent_patients_stmt->execute();
    $recent_patients = $recent_patients_stmt->fetchAll();

} catch (PDOException $exception) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $exception->getMessage();
}

include 'includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-600">Welcome back, <?php echo $_SESSION['first_name']; ?>! Here's what's happening today.</p>
</div>

<!-- Statistics Grid -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <!-- Patients Card -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Patients</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $patient_stats['total_patients']; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-green-600 font-medium">+<?php echo $patient_stats['today_patients']; ?> </span>
                <span class="text-gray-500">today</span>
            </div>
        </div>
    </div>

    <!-- Appointments Card -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Today's Appointments</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $appointment_stats['today_appointments']; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-blue-600 font-medium"><?php echo $appointment_stats['scheduled']; ?> scheduled</span>
            </div>
        </div>
    </div>

    <!-- ER Triage Card -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">ER Cases Today</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $er_stats['total_triage']; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-red-600 font-medium"><?php echo $er_stats['waiting']; ?> waiting</span>
            </div>
        </div>
    </div>

    <!-- Bed Occupancy Card -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Bed Occupancy</dt>
                        <dd class="text-lg font-medium text-gray-900">
                            <?php echo $bed_stats['occupied_beds'] . '/' . $bed_stats['total_beds']; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <span class="text-green-600 font-medium"><?php echo $bed_stats['available_beds']; ?> available</span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Today's Appointments -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Today's Appointments
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                Scheduled appointments for today
            </p>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($today_appointments) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($today_appointments as $appointment): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $appointment['hospital_id']; ?> • 
                                        <?php echo formatTime($appointment['appointment_time']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                                <?php if ($appointment['doctor_fname']): ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Dr. <?php echo $appointment['doctor_fname'] . ' ' . $appointment['doctor_lname']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m14 14-4-4"></path><path d="m10 14 4-4"></path></svg>
                    <p class="text-gray-500">No appointments scheduled for today</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="px-4 py-4 sm:px-6 border-t border-gray-200">
            <a href="modules/appointments/schedule.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">
                View all appointments →
            </a>
        </div>
    </div>

    <!-- Recent Patients -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Recent Patients
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                Recently registered patients
            </p>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_patients) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $patient['hospital_id']; ?> • 
                                        Age: <?php echo calculateAge($patient['birth_date']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <?php echo ucfirst($patient['status']); ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo formatDate($patient['created_at']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <p class="text-gray-500">No patients registered yet</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="px-4 py-4 sm:px-6 border-t border-gray-200">
            <a href="modules/registration/register.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">
                Register new patient →
            </a>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-8 bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            Quick Actions
        </h3>
        <p class="mt-1 text-sm text-gray-500">
            Frequently used functions
        </p>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <a href="modules/registration/register.php" class="flex flex-col items-center p-4 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors">
                <div class="w-12 h-12 bg-primary-500 rounded-full flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                </div>
                <span class="text-sm font-medium text-gray-900">Register Patient</span>
            </a>

            <a href="modules/appointments/schedule.php" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M12 6v6"></path><path d="M9 9h6"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-900">Schedule Appointment</span>
            </a>

            <a href="modules/er_triage/triage.php" class="flex flex-col items-center p-4 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-900">ER Triage</span>
            </a>

            <a href="modules/inpatient/bed_management.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-900">Bed Management</span>
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>