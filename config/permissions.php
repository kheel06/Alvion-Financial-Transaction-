<?php
/**
 * Enhanced Role-Based Permissions System
 * Implements all role processes as defined in ROLE_PROCESSES.md
 */

if (!function_exists('normalizeRoleName')) {
    function normalizeRoleName(?string $role_name): string {
        return strtolower(trim((string)$role_name));
    }
}

if (!function_exists('getRoleDefinitions')) {
    function getRoleDefinitions(): array {
        static $definitions = null;
        if ($definitions !== null) {
            return $definitions;
        }

        $definitions = [
            'super admin' => [
                'label' => 'Super Admin',
                'description' => 'Full platform access including configuration, security, and compliance controls.',
                'inherits' => ['admin'],
                'permissions' => [
                    'system.settings', 'system.clinic_hours', 'system.rooms', 'system.services',
                    'system.pricing', 'system.departments',
                    'maintenance.backup', 'maintenance.restore', 'maintenance.monitor',
                    'audit.view_all', 'audit.export', 'audit.monitor',
                    'roles.manage', 'roles.assign',
                    'gl.manage', 'gl.reports', 'ap.manage', 'ar.manage', 'disbursement.manage', 'budget.manage', 'collection.manage'
                ],
            ],
            'admin' => [
                'label' => 'Admin',
                'description' => 'Operations administrator with broad access to HR, clinical, and reporting modules.',
                'inherits' => ['staff'],
                'permissions' => [
                    'users.create', 'users.edit', 'users.delete', 'users.view_all',
                    'roles.manage', 'roles.assign',
                    'insurance.create', 'insurance.edit', 'insurance.approve', 'insurance.view_all',
                    'reports.all', 'reports.patients', 'reports.appointments', 'reports.billing',
                    'reports.er', 'reports.beds', 'reports.users', 'reports.system',
                    'patients.view_all', 'appointments.view_all', 'billing.view_all',
                    'triage.view_all', 'admissions.view_all', 'prescriptions.view_all',
                    'labs.view_all',
                    'gl.manage', 'gl.reports', 'ap.manage', 'ar.manage', 'disbursement.manage', 'budget.manage', 'collection.manage'
                ],
            ],
            'staff' => [
                'label' => 'Staff',
                'description' => 'Frontline staff responsible for patient intake and coordination.',
                'inherits' => [],
                'permissions' => [
                    'patients.register', 'patients.edit_demographics',
                    'patients.view_basic',
                    'triage.create', 'triage.update', 'triage.view',
                    'vitals.create', 'vitals.update',
                    'queue.assign', 'queue.manage', 'queue.view',
                    'beds.assign', 'beds.update_status', 'beds.view',
                    'monitoring.log', 'monitoring.view'
                ],
            ],
            'employee' => [
                'label' => 'Employee',
                'description' => 'Self-service access for employees to manage their own records.',
                'inherits' => [],
                'permissions' => [
                    'profile.view_own', 'profile.edit_own',
                    'appointments.view_own', 'appointments.book', 'appointments.cancel_own',
                    'records.view_own', 'prescriptions.view_own', 'labs.view_own',
                    'ehr.view_own',
                    'telehealth.view_own', 'telehealth.schedule',
                    'billing.view_own', 'billing.pay_online',
                    'consent.submit'
                ],
            ],
        ];

        return $definitions;
    }
}

if (!function_exists('getAllowedRoleNames')) {
    function getAllowedRoleNames(): array {
        return array_keys(getRoleDefinitions());
    }
}

if (!function_exists('isAllowedRoleName')) {
    function isAllowedRoleName($role_name): bool {
        $key = normalizeRoleName($role_name);
        return in_array($key, getAllowedRoleNames(), true);
    }
}

if (!function_exists('collectRolePermissions')) {
    function collectRolePermissions(string $role_key, array $definitions, array &$visited): array {
        if (isset($visited[$role_key])) {
            return [];
        }
        $visited[$role_key] = true;

        $role = $definitions[$role_key] ?? [];
        $permissions = $role['permissions'] ?? [];

        foreach ($role['inherits'] ?? [] as $parent_role) {
            $parent_key = normalizeRoleName($parent_role);
            if (isset($definitions[$parent_key])) {
                $permissions = array_merge($permissions, collectRolePermissions($parent_key, $definitions, $visited));
            }
        }

        return $permissions;
    }
}

/**
 * Get all permissions for a role
 */
function getRolePermissions($role_name) {
    $role_key = normalizeRoleName($role_name);
    $definitions = getRoleDefinitions();

    if (!isset($definitions[$role_key])) {
        return [];
    }

    $visited = [];
    $permissions = collectRolePermissions($role_key, $definitions, $visited);

    return array_values(array_unique($permissions));
}

/**
 * Check if user has permission
 */
function hasPermission($permission, $role_name = null) {
    if ($role_name === null) {
        $role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null;
    }
    
    if (!$role_name) {
        return false;
    }

    $normalized_role = normalizeRoleName($role_name);

    // Super admin has all permissions
    if ($normalized_role === 'super admin') {
        return true;
    }
    
    $role_permissions = getRolePermissions($normalized_role);
    return in_array($permission, $role_permissions);
}

/**
 * Require permission or redirect
 */
function requirePermission($permission, $redirect_url = '../index.php') {
    if (!hasPermission($permission)) {
        $_SESSION['error'] = "You don't have permission to perform this action.";
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Check data access based on role
 */
function canAccessData($data_type, $data_id = null, $user_id = null) {
    $role_name = normalizeRoleName($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null);
    $current_user_id = $user_id ?? $_SESSION['user_id'] ?? null;
    
    if (!$role_name || !$current_user_id) {
        return false;
    }
    
    // Admin can access all
    if (in_array($role_name, ['admin', 'super admin'], true)) {
        return true;
    }
    
    // Role-specific access rules
    switch ($data_type) {
        case 'patient':
            if ($role_name === 'doctor') {
                // Doctor can access assigned patients
                return canDoctorAccessPatient($data_id, $current_user_id);
            } elseif (in_array($role_name, ['patient', 'employee'], true)) {
                // Patient/Employee can only access own record
                return $data_id == $current_user_id;
            } elseif (in_array($role_name, ['staff', 'receptionist', 'finance staff'])) {
                // Staff, receptionist, finance can access for their functions
                return true;
            }
            break;
            
        case 'appointment':
            if ($role_name === 'doctor') {
                // Doctor can access own appointments
                return canDoctorAccessAppointment($data_id, $current_user_id);
            } elseif (in_array($role_name, ['patient', 'employee'], true)) {
                // Patient/Employee can access own appointments
                return canPatientAccessAppointment($data_id, $current_user_id);
            } elseif (in_array($role_name, ['receptionist', 'staff'])) {
                // Receptionist and staff can access for management
                return true;
            }
            break;
            
        case 'billing':
            if (in_array($role_name, ['patient', 'employee'], true)) {
                // Patient/Employee can only access own bills
                return canPatientAccessBilling($data_id, $current_user_id);
            } elseif ($role_name === 'finance staff') {
                // Finance can access all
                return true;
            }
            break;
            
        case 'prescription':
        case 'lab':
            if (in_array($role_name, ['patient', 'employee'], true)) {
                // Patient/Employee can access own records
                return canPatientAccessRecord($data_type, $data_id, $current_user_id);
            } elseif ($role_name === 'doctor') {
                // Doctor can access own prescriptions/labs
                return canDoctorAccessRecord($data_type, $data_id, $current_user_id);
            } elseif (in_array($role_name, ['staff', 'receptionist'])) {
                // Staff can view for coordination
                return true;
            }
            break;
    }
    
    return false;
}

/**
 * Helper: Check if doctor can access patient
 */
function canDoctorAccessPatient($patient_id, $doctor_id) {
    global $db;
    try {
        // Check if patient has appointment with doctor
        $query = "SELECT COUNT(*) FROM appointments 
                  WHERE patient_id = :patient_id AND doctor_id = :doctor_id
                  AND status != 'cancelled'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if doctor can access appointment
 */
function canDoctorAccessAppointment($appointment_id, $doctor_id) {
    global $db;
    try {
        $query = "SELECT doctor_id FROM appointments WHERE id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch();
        return $appointment && $appointment['doctor_id'] == $doctor_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if patient can access appointment
 */
function canPatientAccessAppointment($appointment_id, $patient_id) {
    global $db;
    try {
        $query = "SELECT patient_id FROM appointments WHERE id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch();
        return $appointment && $appointment['patient_id'] == $patient_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if patient can access billing
 */
function canPatientAccessBilling($billing_id, $patient_id) {
    global $db;
    try {
        $query = "SELECT patient_id FROM billing WHERE id = :billing_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':billing_id', $billing_id);
        $stmt->execute();
        $billing = $stmt->fetch();
        return $billing && $billing['patient_id'] == $patient_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if patient can access record (prescription/lab)
 */
function canPatientAccessRecord($record_type, $record_id, $patient_id) {
    global $db;
    try {
        $table = ($record_type === 'prescription') ? 'e_prescriptions' : 'e_lab_orders';
        $query = "SELECT patient_id FROM {$table} WHERE id = :record_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->execute();
        $record = $stmt->fetch();
        return $record && $record['patient_id'] == $patient_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper: Check if doctor can access record
 */
function canDoctorAccessRecord($record_type, $record_id, $doctor_id) {
    global $db;
    try {
        $table = ($record_type === 'prescription') ? 'e_prescriptions' : 'e_lab_orders';
        $query = "SELECT doctor_id FROM {$table} WHERE id = :record_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->execute();
        $record = $stmt->fetch();
        return $record && $record['doctor_id'] == $doctor_id;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log action for audit trail
 */
function logAction($action, $module, $record_id = null, $old_values = null, $new_values = null) {
    global $db;
    
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $query = "INSERT INTO audit_logs 
                  (user_id, action, module, record_id, old_values, new_values, ip_address, user_agent)
                  VALUES (:user_id, :action, :module, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':module', $module);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindValue(':old_values', $old_values ? json_encode($old_values) : null);
        $stmt->bindValue(':new_values', $new_values ? json_encode($new_values) : null);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Get role display name
 */
function getRoleDisplayName($role_name) {
    $definitions = getRoleDefinitions();
    $key = normalizeRoleName($role_name);
    
    if (isset($definitions[$key]['label'])) {
        return $definitions[$key]['label'];
    }
    
    return ucfirst($role_name);
}

if (!function_exists('ensureCoreRoles')) {
    function ensureCoreRoles(PDO $db): void {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $ensured = true;

        try {
            $db->query("SELECT 1 FROM roles LIMIT 1");
        } catch (PDOException $e) {
            // Roles table does not exist yet
            return;
        }

        $definitions = getRoleDefinitions();
        $existing = [];

        try {
            $roles_stmt = $db->query("SELECT id, role_name FROM roles");
            $role_rows = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return;
        }

        foreach ($role_rows as $row) {
            $normalized = normalizeRoleName($row['role_name']);
            if (!$normalized) {
                continue;
            }

            $existing[$normalized] = true;

            // Normalize stored name to canonical format if needed
            if ($row['role_name'] !== $normalized) {
                try {
                    $update_stmt = $db->prepare("UPDATE roles SET role_name = :role_name, updated_at = NOW() WHERE id = :id");
                    $update_stmt->bindValue(':role_name', $normalized);
                    $update_stmt->bindValue(':id', $row['id'], PDO::PARAM_INT);
                    $update_stmt->execute();
                } catch (PDOException $e) {
                    // Ignore normalization failure
                }
            }
        }

        foreach ($definitions as $key => $definition) {
            if (isset($existing[$key])) {
                continue;
            }

            try {
                $insert_stmt = $db->prepare("INSERT INTO roles (role_name, role_description, created_at, updated_at) VALUES (:role_name, :role_description, NOW(), NOW())");
                $insert_stmt->bindValue(':role_name', $key);
                $insert_stmt->bindValue(':role_description', $definition['description'] ?? null);
                $insert_stmt->execute();
            } catch (PDOException $e) {
                // Ignore insert failure to avoid fatal errors on deployment
            }
        }
    }
}

?>

