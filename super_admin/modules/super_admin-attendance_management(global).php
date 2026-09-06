<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Global Attendance Management';

$metrics = [
    'present_today'  => 0,
    'late_today'     => 0,
    'no_logs_today'  => 0,
    'exceptions_open'=> 0,
];

$rows = [];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'attendance_logs'");
        if ($check && $check->rowCount() > 0) {
            // Today presence summary
            $stmt = $db->query("
                SELECT 
                    SUM(CASE WHEN status IN ('present','on_time') THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status = 'no_log' THEN 1 ELSE 0 END) AS no_log_count
                FROM attendance_logs
                WHERE log_date = CURDATE()
            ");
            $row = $stmt->fetch();
            $metrics['present_today'] = (int)($row['present_count'] ?? 0);
            $metrics['late_today']    = (int)($row['late_count'] ?? 0);
            $metrics['no_logs_today'] = (int)($row['no_log_count'] ?? 0);

            // Exception tracking (late/undertime/no logs) via a view if available
            $checkView = $db->query("SHOW TABLES LIKE 'vw_attendance_exceptions'");
            if ($checkView && $checkView->rowCount() > 0) {
                $stmt = $db->query("
                    SELECT 
                        employee_name,
                        department_name,
                        log_date,
                        exception_type,
                        status,
                        schedule_label
                    FROM vw_attendance_exceptions
                    ORDER BY log_date DESC
                    LIMIT 200
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmt = $db->query("
                    SELECT COUNT(*) AS c
                    FROM vw_attendance_exceptions
                    WHERE status = 'open'
                ");
                $row = $stmt->fetch();
                $metrics['exceptions_open'] = (int)($row['c'] ?? 0);
            }
        }
    } catch (PDOException $e) {
        error_log('Super admin global attendance error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'Global Attendance Management',
    'description' => 'Real-time visibility of daily logs, late and undertime exceptions, and schedule mismatches across the hospital.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Global Attendance'],
    ],
    'metrics' => [
        [
            'label' => 'Present Today',
            'value' => $metrics['present_today'],
            'sub'   => 'Employees with valid logs today',
            'icon'  => 'check-circle-2',
            'tone'  => 'success',
        ],
        [
            'label' => 'Late Today',
            'value' => $metrics['late_today'],
            'sub'   => 'Marked as late vs schedule',
            'icon'  => 'clock-alert',
            'tone'  => 'warning',
        ],
        [
            'label' => 'No Logs Today',
            'value' => $metrics['no_logs_today'],
            'sub'   => 'Absent or missing time entries',
            'icon'  => 'alert-triangle',
            'tone'  => 'danger',
        ],
        [
            'label' => 'Open Exceptions',
            'value' => $metrics['exceptions_open'],
            'sub'   => 'Pending validation/justification',
            'icon'  => 'badge-alert',
            'tone'  => 'primary',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'range',
            'label' => 'Date Range',
        ],
        [
            'type'    => 'select',
            'name'    => 'department',
            'label'   => 'Department',
            'options' => [
                ['value' => '', 'label' => 'All'],
            ],
        ],
        [
            'type'    => 'select',
            'name'    => 'exception_type',
            'label'   => 'Exception Type',
            'options' => [
                ['value' => '',          'label' => 'All'],
                ['value' => 'late',      'label' => 'Late'],
                ['value' => 'undertime', 'label' => 'Undertime'],
                ['value' => 'no_log',    'label' => 'No Logs'],
                ['value' => 'mismatch',  'label' => 'Schedule Mismatch'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'log_date',       'label' => 'Date',        'class' => 'text-xs'],
            ['key' => 'employee_name',  'label' => 'Employee',    'class' => 'text-xs'],
            ['key' => 'department_name','label' => 'Department',  'class' => 'text-xs'],
            ['key' => 'schedule_label', 'label' => 'Schedule',    'class' => 'text-xs'],
            ['key' => 'exception_type', 'label' => 'Exception',   'class' => 'text-xs'],
            ['key' => 'status',         'label' => 'Status',      'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No attendance exceptions found',
        'message'      => 'When late, undertime, and schedule mismatch events occur, they will appear here for centralized monitoring.',
        'icon'         => 'clipboard-list',
        'action_label' => 'View reporting',
        'action_href'  => BASE_URL . '/super_admin/modules/super_admin-reporting.php',
        'action_icon'  => 'bar-chart-3',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



