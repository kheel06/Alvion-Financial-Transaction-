<?php
/**
 * Permission Check Helper Function
 * Use this in PHP pages to check permissions
 */

function hasPermission($permission, $resource = null) {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $role_id = $_SESSION['role_id'] ?? null;
    $role_name = $_SESSION['role_name'] ?? null;
    $normalized_role = function_exists('normalizeRoleName') ? normalizeRoleName($role_name) : strtolower((string)$role_name);
    
    if ($normalized_role === 'super admin') {
        return true;
    }
    
    // Check permissions from database if role_permissions table exists
    try {
        $perm_query = "SELECT rp.* FROM role_permissions rp
                      INNER JOIN roles r ON rp.role_id = r.id
                      WHERE r.id = :role_id AND rp.permission_name = :permission";
        $perm_stmt = $db->prepare($perm_query);
        $perm_stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $perm_stmt->bindParam(':permission', $permission);
        $perm_stmt->execute();
        $perm_data = $perm_stmt->fetch();
        
        if ($perm_data) {
            return true;
        }
    } catch (PDOException $e) {
        // Fallback to hardcoded mapping
    }
    
    if (function_exists('getRolePermissions')) {
        $role_permissions = getRolePermissions($normalized_role);
        return in_array($permission, $role_permissions, true);
    }
    
    // Hardcoded fallback mapping by role
    $role_permissions = [
        'super admin' => ['*'],
        'admin' => ['*'],
        'staff' => [
            'patients.register', 'patients.edit_demographics', 'patients.view_basic',
            'triage.create', 'triage.update', 'triage.view',
            'vitals.create', 'vitals.update',
            'queue.assign', 'queue.manage', 'queue.view',
            'beds.assign', 'beds.update_status', 'beds.view',
            'monitoring.log', 'monitoring.view'
        ],
        'employee' => [
            'profile.view_own', 'profile.edit_own',
            'appointments.view_own', 'appointments.book', 'appointments.cancel_own',
            'records.view_own', 'prescriptions.view_own', 'labs.view_own',
            'ehr.view_own',
            'telehealth.view_own', 'telehealth.schedule',
            'billing.view_own', 'billing.pay_online',
            'consent.submit'
        ],
    ];
    
    $user_perms = $role_permissions[$normalized_role] ?? [];
    return in_array('*', $user_perms, true) || in_array($permission, $user_perms, true);
}

function requirePermission($permission, $resource = null) {
    if (!hasPermission($permission, $resource)) {
        $_SESSION['error'] = "You don't have permission to perform this action.";
        header("Location: ../../index.php");
        exit;
    }
}
?>








