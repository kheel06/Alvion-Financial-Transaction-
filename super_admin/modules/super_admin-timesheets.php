<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Global Timesheets';

$metrics = [
    'open_cutoffs'   => 0,
    'locked_cutoffs' => 0,
    'pending_excepts'=> 0,
    'export_ready'   => 0,
];

$rows = [];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'timesheet_cutoffs'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END)   AS open_cutoffs,
                    SUM(CASE WHEN status = 'locked' THEN 1 ELSE 0 END) AS locked_cutoffs,
                    SUM(CASE WHEN status = 'export_ready' THEN 1 ELSE 0 END) AS export_ready
                FROM timesheet_cutoffs
            ");
            $row = $stmt->fetch();
            $metrics['open_cutoffs']   = (int)($row['open_cutoffs'] ?? 0);
            $metrics['locked_cutoffs'] = (int)($row['locked_cutoffs'] ?? 0);
            $metrics['export_ready']   = (int)($row['export_ready'] ?? 0);
        }

        $check = $db->query("SHOW TABLES LIKE 'vw_timesheet_exception_summary'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    cutoff_label,
                    department_name,
                    exception_count,
                    verifier_status
                FROM vw_timesheet_exception_summary
                ORDER BY cutoff_start DESC, department_name
                LIMIT 200
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $db->query("
                SELECT SUM(exception_count) AS c
                FROM vw_timesheet_exception_summary
                WHERE verifier_status <> 'cleared'
            ");
            $row = $stmt->fetch();
            $metrics['pending_excepts'] = (int)($row['c'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Super admin timesheets error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'Cutoff Timesheets (Global)',
    'description' => 'Monitor cutoff-level timesheet status across all units, validate exceptions, and prepare exports for payroll.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Timesheets'],
    ],
    'metrics' => [
        [
            'label' => 'Open Cutoffs',
            'value' => $metrics['open_cutoffs'],
            'sub'   => 'Currently accepting timesheet entries',
            'icon'  => 'calendar-plus',
            'tone'  => 'info',
        ],
        [
            'label' => 'Locked Cutoffs',
            'value' => $metrics['locked_cutoffs'],
            'sub'   => 'Awaiting export or payroll processing',
            'icon'  => 'lock',
            'tone'  => 'warning',
        ],
        [
            'label' => 'Pending Exceptions',
            'value' => $metrics['pending_excepts'],
            'sub'   => 'Timesheets with unresolved anomalies',
            'icon'  => 'alert-triangle',
            'tone'  => 'danger',
        ],
        [
            'label' => 'Export-Ready Cutoffs',
            'value' => $metrics['export_ready'],
            'sub'   => 'Fully validated and ready for CSV/Excel',
            'icon'  => 'file-down',
            'tone'  => 'primary',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'cutoff_range',
            'label' => 'Cutoff Period',
        ],
        [
            'type'    => 'select',
            'name'    => 'status',
            'label'   => 'Status',
            'options' => [
                ['value' => '',             'label' => 'All'],
                ['value' => 'open',         'label' => 'Open'],
                ['value' => 'locked',       'label' => 'Locked'],
                ['value' => 'export_ready', 'label' => 'Export Ready'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'cutoff_label',    'label' => 'Cutoff',        'class' => 'text-xs'],
            ['key' => 'department_name', 'label' => 'Department',    'class' => 'text-xs'],
            ['key' => 'exception_count', 'label' => 'Exceptions',    'class' => 'text-xs'],
            ['key' => 'verifier_status', 'label' => 'Verification',  'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No cutoff timesheet data yet',
        'message'      => 'Cutoff periods and timesheet validations will appear here as HR opens cycles and supervisors verify hours.',
        'icon'         => 'file-clock',
        'action_label' => 'Go to HR timesheet management',
        'action_href'  => BASE_URL . '/admin/modules/admin-timesheet_management.php',
        'action_icon'  => 'users',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



