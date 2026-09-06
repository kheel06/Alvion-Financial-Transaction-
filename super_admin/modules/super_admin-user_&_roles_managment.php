<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'User & Roles Management';

// Aggregate counts
$metrics = [
    'admin_users'    => 0,
    'staff_users'    => 0,
    'employee_users' => 0,
    'roles_defined'  => 0,
];

if (isset($db)) {
    try {
        // Count users by normalized role
        $check = $db->query("SHOW TABLES LIKE 'users'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT LOWER(TRIM(role_name)) AS role_key, COUNT(*) AS c
                FROM users
                GROUP BY role_key
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $roleKey = $row['role_key'] ?? '';
                if ($roleKey === 'super admin' || $roleKey === 'admin') {
                    $metrics['admin_users'] += (int)$row['c'];
                } elseif ($roleKey === 'staff' || $roleKey === 'supervisor') {
                    $metrics['staff_users'] += (int)$row['c'];
                } elseif ($roleKey === 'employee') {
                    $metrics['employee_users'] += (int)$row['c'];
                }
            }
        }

        // Roles defined
        $check = $db->query("SHOW TABLES LIKE 'roles'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM roles");
            $row = $stmt->fetch();
            $metrics['roles_defined'] = (int)($row['c'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Super admin user/role metrics error: ' . $e->getMessage());
    }
}

// List of users with role & department (if view exists)
$rows = [];
if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'vw_user_role_summary'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    full_name,
                    username,
                    role_label,
                    department_name,
                    status
                FROM vw_user_role_summary
                ORDER BY role_label, full_name
                LIMIT 200
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('Super admin user/role listing error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'User & Roles Management',
    'description' => 'Administer administrator accounts, staff supervisors, and employee access levels with granular permissions.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Users & Roles'],
    ],
    'actions' => [
        [
            'label' => 'Create Admin User',
            'href'  => BASE_URL . '/admin/users/create_user.php',
            'icon'  => 'user-plus',
        ],
        [
            'label' => 'Manage Role Templates',
            'href'  => BASE_URL . '/admin/roles/manage_roles.php',
            'icon'  => 'key-round',
        ],
    ],
    'metrics' => [
        [
            'label' => 'Admin & HR Accounts',
            'value' => $metrics['admin_users'],
            'sub'   => 'Super Admin, HR, and timekeepers',
            'icon'  => 'shield-check',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Supervisors / Staff Leads',
            'value' => $metrics['staff_users'],
            'sub'   => 'Department heads & shift supervisors',
            'icon'  => 'user-cog',
            'tone'  => 'info',
        ],
        [
            'label' => 'Employees with Self-Service',
            'value' => $metrics['employee_users'],
            'sub'   => 'Portal-enabled staff',
            'icon'  => 'user-round',
            'tone'  => 'success',
        ],
        [
            'label' => 'Role Definitions',
            'value' => $metrics['roles_defined'],
            'sub'   => 'Permission templates in the system',
            'icon'  => 'key-round',
            'tone'  => 'warning',
        ],
    ],
    'filters' => [
        [
            'type'        => 'search',
            'name'        => 'search',
            'label'       => 'Search users',
            'placeholder' => 'Search by name, username, or department',
        ],
        [
            'type'    => 'select',
            'name'    => 'role',
            'label'   => 'Role',
            'options' => [
                ['value' => '',            'label' => 'All'],
                ['value' => 'super admin', 'label' => 'Super Admin'],
                ['value' => 'admin',       'label' => 'Admin / HR'],
                ['value' => 'staff',       'label' => 'Staff Supervisor'],
                ['value' => 'employee',    'label' => 'Employee'],
            ],
        ],
        [
            'type'    => 'select',
            'name'    => 'status',
            'label'   => 'Status',
            'options' => [
                ['value' => '',         'label' => 'All'],
                ['value' => 'active',   'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'locked',   'label' => 'Locked'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'full_name',      'label' => 'Name',        'class' => 'text-xs'],
            ['key' => 'username',       'label' => 'Username',    'class' => 'text-xs'],
            ['key' => 'role_label',     'label' => 'Role',        'class' => 'text-xs'],
            ['key' => 'department_name','label' => 'Department',  'class' => 'text-xs'],
            ['key' => 'status',         'label' => 'Status',      'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No user records to display yet',
        'message'      => 'Once administrator, staff, and employee accounts are provisioned, they will appear here for central management.',
        'icon'         => 'users',
        'action_label' => 'Create first admin',
        'action_href'  => BASE_URL . '/admin/users/create_user.php',
        'action_icon'  => 'user-plus',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



