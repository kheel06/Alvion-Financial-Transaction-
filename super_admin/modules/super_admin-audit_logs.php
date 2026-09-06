<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'System Audit Logs';

$metrics = [
    'events_24h'   => 0,
    'logins_24h'   => 0,
    'failed_logins'=> 0,
    'security_events'=> 0,
];

$rows = [];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'audit_logs'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    COUNT(*) AS total_events,
                    SUM(CASE WHEN module = 'auth' AND action = 'login' THEN 1 ELSE 0 END) AS logins,
                    SUM(CASE WHEN module = 'auth' AND action = 'login_failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN module = 'security' THEN 1 ELSE 0 END) AS security_events
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $row = $stmt->fetch();
            $metrics['events_24h']      = (int)($row['total_events'] ?? 0);
            $metrics['logins_24h']      = (int)($row['logins'] ?? 0);
            $metrics['failed_logins']   = (int)($row['failed'] ?? 0);
            $metrics['security_events'] = (int)($row['security_events'] ?? 0);

            $stmt = $db->query("
                SELECT 
                    created_at,
                    user_id,
                    action,
                    module,
                    record_id,
                    ip_address
                FROM audit_logs
                ORDER BY created_at DESC
                LIMIT 200
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('Super admin audit logs error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'System Audit Logs',
    'description' => 'Track system-wide activity, monitor login attempts, and review security-related configuration changes.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Audit Logs'],
    ],
    'metrics' => [
        [
            'label' => 'Events (Last 24h)',
            'value' => $metrics['events_24h'],
            'sub'   => 'All recorded actions across modules',
            'icon'  => 'list-tree',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Logins (Last 24h)',
            'value' => $metrics['logins_24h'],
            'sub'   => 'Successful authentication events',
            'icon'  => 'log-in',
            'tone'  => 'success',
        ],
        [
            'label' => 'Failed Logins',
            'value' => $metrics['failed_logins'],
            'sub'   => 'Failed or blocked attempts',
            'icon'  => 'shield-alert',
            'tone'  => 'danger',
        ],
        [
            'label' => 'Security Events',
            'value' => $metrics['security_events'],
            'sub'   => 'Changes to roles, policies, and MFA settings',
            'icon'  => 'shield-check',
            'tone'  => 'warning',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'log_range',
            'label' => 'Log Range',
        ],
        [
            'type'    => 'select',
            'name'    => 'module',
            'label'   => 'Module',
            'options' => [
                ['value' => '',          'label' => 'All'],
                ['value' => 'auth',      'label' => 'Authentication'],
                ['value' => 'security',  'label' => 'Security'],
                ['value' => 'timesheet', 'label' => 'Timesheets'],
                ['value' => 'roles',     'label' => 'Roles & Permissions'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'created_at', 'label' => 'Timestamp',   'class' => 'text-xs'],
            ['key' => 'user_id',    'label' => 'User ID',     'class' => 'text-xs'],
            ['key' => 'module',     'label' => 'Module',      'class' => 'text-xs'],
            ['key' => 'action',     'label' => 'Action',      'class' => 'text-xs'],
            ['key' => 'record_id',  'label' => 'Record ID',   'class' => 'text-xs'],
            ['key' => 'ip_address', 'label' => 'IP Address',  'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No audit events to display',
        'message'      => 'As users interact with the system, a tamper-resistant trail of actions will be captured here.',
        'icon'         => 'list-tree',
        'action_label' => 'Review security policies',
        'action_href'  => BASE_URL . '/super_admin/modules/super_admin-system_configuration.php',
        'action_icon'  => 'shield',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



