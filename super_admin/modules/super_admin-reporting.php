<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'HR Analytics & Reporting';

// Reporting is analytics-heavy; here we compute high-level KPIs if data is available
$metrics = [
    'headcount'         => 0,
    'avg_attendance_rt' => null,
    'leave_utilization' => null,
    'ot_hours_30d'      => null,
];

$rows = [];

if (isset($db)) {
    try {
        // Headcount from employees
        $check = $db->query("SHOW TABLES LIKE 'employees'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM employees");
            $row = $stmt->fetch();
            $metrics['headcount'] = (int)($row['c'] ?? 0);
        }

        // Attendance rate (last 30 days) from a view if present
        $check = $db->query("SHOW TABLES LIKE 'vw_department_attendance_kpi'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    AVG(attendance_rate_30d) AS avg_rate,
                    AVG(leave_utilization_30d) AS avg_leave,
                    SUM(ot_hours_30d) AS total_ot_hours
                FROM vw_department_attendance_kpi
            ");
            $row = $stmt->fetch();
            if (!empty($row['avg_rate'])) {
                $metrics['avg_attendance_rt'] = round($row['avg_rate'], 1);
            }
            if (!empty($row['avg_leave'])) {
                $metrics['leave_utilization'] = round($row['avg_leave'], 1);
            }
            if (!empty($row['total_ot_hours'])) {
                $metrics['ot_hours_30d'] = round($row['total_ot_hours'], 1);
            }

            // Department breakdown rows
            $stmt = $db->query("
                SELECT 
                    department_name,
                    attendance_rate_30d,
                    leave_utilization_30d,
                    ot_hours_30d
                FROM vw_department_attendance_kpi
                ORDER BY department_name
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('Super admin reporting error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'HR Analytics & Reporting',
    'description' => 'Master view of hospital unit performance, attendance behavior, leave utilization, and staffing levels – exportable for compliance and executive review.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Reporting'],
    ],
    'metrics' => [
        [
            'label' => 'Total Headcount',
            'value' => $metrics['headcount'],
            'sub'   => 'All active employees in the system',
            'icon'  => 'users',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Avg. Attendance Rate (30d)',
            'value' => $metrics['avg_attendance_rt'] !== null ? $metrics['avg_attendance_rt'] . '%' : '--',
            'sub'   => 'Average across all departments',
            'icon'  => 'activity',
            'tone'  => 'success',
        ],
        [
            'label' => 'Avg. Leave Utilization (30d)',
            'value' => $metrics['leave_utilization'] !== null ? $metrics['leave_utilization'] . '%' : '--',
            'sub'   => 'Portion of allocated leave actually used',
            'icon'  => 'calendar-days',
            'tone'  => 'info',
        ],
        [
            'label' => 'OT Hours (30d)',
            'value' => $metrics['ot_hours_30d'] !== null ? $metrics['ot_hours_30d'] : '--',
            'sub'   => 'Total overtime hours logged last 30 days',
            'icon'  => 'clock-3',
            'tone'  => 'warning',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'analysis_range',
            'label' => 'Analysis Range',
        ],
        [
            'type'    => 'select',
            'name'    => 'dimension',
            'label'   => 'Dimension',
            'options' => [
                ['value' => 'department', 'label' => 'By Department'],
                ['value' => 'unit',       'label' => 'By Unit'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'department_name',       'label' => 'Department',            'class' => 'text-xs'],
            ['key' => 'attendance_rate_30d',   'label' => 'Attendance (30d %)',    'class' => 'text-xs'],
            ['key' => 'leave_utilization_30d', 'label' => 'Leave Utilization (%)', 'class' => 'text-xs'],
            ['key' => 'ot_hours_30d',          'label' => 'OT Hours (30d)',        'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No analytics data available yet',
        'message'      => 'Once attendance, leave, and OT records accumulate, department-level analytics will populate this dashboard.',
        'icon'         => 'bar-chart-3',
        'action_label' => 'Export baseline templates',
        'action_href'  => BASE_URL . '/reports/appointments_report.php',
        'action_icon'  => 'file-down',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



