<?php
/**
 * Role-Based Helper Functions
 * Provides data access functions based on role permissions
 */

require_once __DIR__ . '/../config/permissions.php';

/**
 * Retrieve the core system roles filtered to the allowed list
 */
function getSystemRoles(PDO $db): array {
    $allowed_roles = getAllowedRoleNames();
    if (empty($allowed_roles)) {
        return [];
    }

    $allowed_map = array_flip(array_map('normalizeRoleName', $allowed_roles));

    try {
        $stmt = $db->query("SELECT id, role_name, role_description FROM roles");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error loading roles: " . $e->getMessage());
        return [];
    }

    $filtered = [];
    foreach ($roles as $role) {
        $normalized = normalizeRoleName($role['role_name']);
        if (!isset($allowed_map[$normalized])) {
            continue;
        }

        $filtered[] = [
            'id' => $role['id'],
            'role_name' => $normalized,
            'role_description' => $role['role_description'] ?? null,
            'display_name' => getRoleDisplayName($normalized),
        ];
    }

    usort($filtered, function ($a, $b) use ($allowed_map) {
        $a_index = $allowed_map[$a['role_name']] ?? PHP_INT_MAX;
        $b_index = $allowed_map[$b['role_name']] ?? PHP_INT_MAX;
        return $a_index <=> $b_index;
    });

    return $filtered;
}

/**
 * Get patients based on role access
 */
function getPatientsByRole($filters = []) {
    global $db;
    $role_name = normalizeRoleName($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null);
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$role_name || !$user_id) {
        return [];
    }
    
    $query = "SELECT p.* FROM patients p WHERE 1=1";
    $params = [];
    
    // Role-based filtering
    if ($role_name === 'doctor') {
        // Doctor can only see patients with appointments
        $query .= " AND EXISTS (
            SELECT 1 FROM appointments a 
            WHERE a.patient_id = p.id 
            AND a.doctor_id = :doctor_id 
            AND a.status != 'cancelled'
        )";
        $params[':doctor_id'] = $user_id;
    } elseif (in_array($role_name, ['patient', 'employee'], true)) {
        // Patient/Employee can only see own record
        $query .= " AND p.id = :patient_id";
        $params[':patient_id'] = $user_id;
    }
    // Admin, staff, receptionist, finance can see all
    
    // Apply additional filters
    if (!empty($filters['search'])) {
        $query .= " AND (p.first_name LIKE :search OR p.last_name LIKE :search OR p.hospital_id LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND p.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    $query .= " ORDER BY p.created_at DESC LIMIT 100";
    
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting patients: " . $e->getMessage());
        return [];
    }
}

/**
 * Get appointments based on role access
 */
function getAppointmentsByRole($filters = []) {
    global $db;
    $role_name = normalizeRoleName($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null);
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$role_name || !$user_id) {
        return [];
    }
    
    $query = "SELECT a.*, p.first_name, p.last_name, p.hospital_id,
              u.first_name as doctor_first_name, u.last_name as doctor_last_name
              FROM appointments a
              LEFT JOIN patients p ON a.patient_id = p.id
              LEFT JOIN users u ON a.doctor_id = u.id
              WHERE 1=1";
    $params = [];
    
    // Role-based filtering
    if ($role_name === 'doctor') {
        $query .= " AND a.doctor_id = :doctor_id";
        $params[':doctor_id'] = $user_id;
    } elseif (in_array($role_name, ['patient', 'employee'], true)) {
        $query .= " AND a.patient_id = :patient_id";
        $params[':patient_id'] = $user_id;
    }
    // Admin, receptionist, staff can see all
    
    // Apply filters
    if (!empty($filters['date'])) {
        $query .= " AND a.appointment_date = :date";
        $params[':date'] = $filters['date'];
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND a.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 100";
    
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting appointments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get billing records based on role access
 */
function getBillingByRole($filters = []) {
    global $db;
    $role_name = normalizeRoleName($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null);
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$role_name || !$user_id) {
        return [];
    }
    
    $query = "SELECT b.*, p.first_name, p.last_name, p.hospital_id
              FROM billing b
              LEFT JOIN patients p ON b.patient_id = p.id
              WHERE 1=1";
    $params = [];
    
    // Role-based filtering
    if (in_array($role_name, ['patient', 'employee'], true)) {
        $query .= " AND b.patient_id = :patient_id";
        $params[':patient_id'] = $user_id;
    }
    // Admin and finance can see all
    
    // Apply filters
    if (!empty($filters['status'])) {
        $query .= " AND b.payment_status = :status";
        $params[':status'] = $filters['status'];
    }
    
    $query .= " ORDER BY b.created_at DESC LIMIT 100";
    
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting billing: " . $e->getMessage());
        return [];
    }
}

/**
 * Get today's schedule for doctor
 */
function getDoctorSchedule($doctor_id, $date = null) {
    global $db;
    
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    try {
        $query = "SELECT a.*, p.first_name, p.last_name, p.hospital_id
                  FROM appointments a
                  LEFT JOIN patients p ON a.patient_id = p.id
                  WHERE a.doctor_id = :doctor_id
                  AND a.appointment_date = :date
                  AND a.status IN ('scheduled', 'confirmed', 'in_progress')
                  ORDER BY a.appointment_time ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting doctor schedule: " . $e->getMessage());
        return [];
    }
}

/**
 * Get queue for department/doctor
 */
function getQueue($filters = []) {
    global $db;
    
    $query = "SELECT q.*, p.first_name, p.last_name, p.hospital_id,
              u.first_name as doctor_first_name, u.last_name as doctor_last_name
              FROM queue q
              LEFT JOIN patients p ON q.patient_id = p.id
              LEFT JOIN users u ON q.doctor_id = u.id
              WHERE q.status = 'waiting'";
    $params = [];
    
    if (!empty($filters['doctor_id'])) {
        $query .= " AND q.doctor_id = :doctor_id";
        $params[':doctor_id'] = $filters['doctor_id'];
    }
    
    if (!empty($filters['department'])) {
        $query .= " AND q.department = :department";
        $params[':department'] = $filters['department'];
    }
    
    $query .= " ORDER BY 
                CASE q.priority_level 
                    WHEN 'emergency' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                q.created_at ASC";
    
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting queue: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate queue number
 */
function generateQueueNumber($department = null) {
    global $db;
    
    $prefix = $department ? strtoupper(substr($department, 0, 3)) : 'Q';
    $date = date('Ymd');
    
    try {
        // Get last queue number for today
        $query = "SELECT queue_number FROM queue 
                  WHERE queue_number LIKE :pattern 
                  ORDER BY id DESC LIMIT 1";
        $pattern = $prefix . '-' . $date . '-%';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pattern', $pattern);
        $stmt->execute();
        $last = $stmt->fetch();
        
        if ($last) {
            $last_num = intval(substr($last['queue_number'], -4));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }
        
        return $prefix . '-' . $date . '-' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Error generating queue number: " . $e->getMessage());
        return $prefix . '-' . $date . '-0001';
    }
}

/**
 * Check if user can modify data
 */
function canModifyData($data_type, $data_id) {
    $role_name = normalizeRoleName($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null);
    
    if (in_array($role_name, ['admin', 'super admin'], true)) {
        return true;
    }
    
    // Check specific permissions
    switch ($data_type) {
        case 'patient':
            return hasPermission('patients.edit', $role_name);
        case 'appointment':
            return hasPermission('appointments.edit', $role_name);
        case 'billing':
            return hasPermission('billing.edit', $role_name);
        case 'prescription':
            return hasPermission('prescriptions.create', $role_name);
        default:
            return false;
    }
}

/**
 * Get role-specific dashboard data
 */
function getDashboardData($role_name, $user_id) {
    global $db;
    $data = [];
    $role_name = normalizeRoleName($role_name);
    
    switch ($role_name) {
        case 'doctor':
            $data['today_appointments'] = count(getDoctorSchedule($user_id));
            $data['pending_consultations'] = count(getAppointmentsByRole(['status' => 'scheduled']));
            break;
            
        case 'receptionist':
            $data['today_appointments'] = count(getAppointmentsByRole(['date' => date('Y-m-d')]));
            $data['queue_waiting'] = count(getQueue());
            break;
            
        case 'finance staff':
            $data['pending_bills'] = count(getBillingByRole(['status' => 'pending']));
            $data['today_payments'] = 0; // TODO: Implement
            break;
            
        case 'super admin':
        case 'admin':
            // Admin gets all stats
            $data['total_patients'] = count(getPatientsByRole());
            $data['today_appointments'] = count(getAppointmentsByRole(['date' => date('Y-m-d')]));
            break;
        
        case 'employee':
            $data['upcoming_appointments'] = count(getAppointmentsByRole(['status' => 'scheduled']));
            $data['open_billing'] = count(getBillingByRole(['status' => 'pending']));
            break;
    }
    
    return $data;
}

?>

