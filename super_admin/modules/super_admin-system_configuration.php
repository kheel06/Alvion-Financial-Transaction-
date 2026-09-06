<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'System Configuration';

// Read some configuration stats where possible
$metrics = [
    'attendance_policies' => 0,
    'shift_patterns'      => 0,
    'cutoff_profiles'     => 0,
    'security_policies'   => 0,
];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'attendance_policies'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM attendance_policies");
            $row = $stmt->fetch();
            $metrics['attendance_policies'] = (int)($row['c'] ?? 0);
        }

        $check = $db->query("SHOW TABLES LIKE 'shift_patterns'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM shift_patterns");
            $row = $stmt->fetch();
            $metrics['shift_patterns'] = (int)($row['c'] ?? 0);
        }

        $check = $db->query("SHOW TABLES LIKE 'payroll_cutoff_profiles'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM payroll_cutoff_profiles");
            $row = $stmt->fetch();
            $metrics['cutoff_profiles'] = (int)($row['c'] ?? 0);
        }

        $check = $db->query("SHOW TABLES LIKE 'security_policies'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM security_policies");
            $row = $stmt->fetch();
            $metrics['security_policies'] = (int)($row['c'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Super admin system config metrics error: ' . $e->getMessage());
    }
}

// Config profiles listing (if generic view exists)
$rows = [];
if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'vw_system_configuration_profiles'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    profile_name,
                    profile_type,
                    scope,
                    is_active,
                    updated_at
                FROM vw_system_configuration_profiles
                ORDER BY profile_type, profile_name
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('Super admin system config listing error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'System Configuration',
    'description' => 'Centralize attendance, shifts, payroll cutoffs, and security policies for the entire hospital.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'System Configuration'],
    ],
    'metrics' => [
        [
            'label' => 'Attendance Policies',
            'value' => $metrics['attendance_policies'],
            'sub'   => 'Grace periods, rounding, and exceptions',
            'icon'  => 'clipboard-check',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Shift Patterns',
            'value' => $metrics['shift_patterns'],
            'sub'   => 'Standard and rotation templates',
            'icon'  => 'calendar-range',
            'tone'  => 'info',
        ],
        [
            'label' => 'Payroll Cutoff Profiles',
            'value' => $metrics['cutoff_profiles'],
            'sub'   => 'Cutoff dates & payroll cycles',
            'icon'  => 'wallet-cards',
            'tone'  => 'success',
        ],
        [
            'label' => 'Security Policies',
            'value' => $metrics['security_policies'],
            'sub'   => 'Password, MFA, and login rules',
            'icon'  => 'shield-lock',
            'tone'  => 'danger',
        ],
    ],
    'filters' => [
        [
            'type'    => 'select',
            'name'    => 'type',
            'label'   => 'Profile Type',
            'options' => [
                ['value' => '',                    'label' => 'All'],
                ['value' => 'attendance_policy',   'label' => 'Attendance'],
                ['value' => 'shift_pattern',       'label' => 'Shift'],
                ['value' => 'payroll_cutoff',      'label' => 'Payroll Cutoff'],
                ['value' => 'security',            'label' => 'Security'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'profile_name', 'label' => 'Profile',  'class' => 'text-xs'],
            ['key' => 'profile_type', 'label' => 'Type',     'class' => 'text-xs'],
            ['key' => 'scope',        'label' => 'Scope',    'class' => 'text-xs'],
            ['key' => 'is_active',    'label' => 'Active?',  'class' => 'text-xs'],
            ['key' => 'updated_at',   'label' => 'Updated',  'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No configuration profiles defined yet',
        'message'      => 'Define attendance rules, shift patterns, and cutoff profiles so HR and supervisors share a single source of truth.',
        'icon'         => 'settings-2',
        'action_label' => 'Go to system settings',
        'action_href'  => BASE_URL . '/admin/system/system_settings.php',
        'action_icon'  => 'sliders-vertical',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



