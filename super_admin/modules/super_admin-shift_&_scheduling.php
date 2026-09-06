<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Master Shift & Scheduling';

$metrics = [
    'active_patterns'   => 0,
    'scheduled_today'   => 0,
    'conflicts_open'    => 0,
    'nd_ot_flags_today' => 0,
];

$rows = [];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'shift_patterns'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT COUNT(*) AS c
                FROM shift_patterns
                WHERE is_active = 1
            ");
            $row = $stmt->fetch();
            $metrics['active_patterns'] = (int)($row['c'] ?? 0);
        }

        $check = $db->query("SHOW TABLES LIKE 'shifts'");
        if ($check && $check->rowCount() > 0) {
            // Shifts scheduled today
            $stmt = $db->query("
                SELECT COUNT(*) AS c
                FROM shifts
                WHERE shift_date = CURDATE()
            ");
            $row = $stmt->fetch();
            $metrics['scheduled_today'] = (int)($row['c'] ?? 0);

            // Conflicts and ND/OT boundary flags via view if available
            $checkView = $db->query("SHOW TABLES LIKE 'vw_shift_conflicts'");
            if ($checkView && $checkView->rowCount() > 0) {
                $stmt = $db->query("
                    SELECT 
                        employee_name,
                        department_name,
                        shift_date,
                        conflict_type,
                        severity,
                        details
                    FROM vw_shift_conflicts
                    ORDER BY shift_date DESC, severity DESC
                    LIMIT 200
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmt = $db->query("
                    SELECT 
                        SUM(CASE WHEN conflict_type = 'overlap' THEN 1 ELSE 0 END) AS overlaps,
                        SUM(CASE WHEN conflict_type = 'missing' THEN 1 ELSE 0 END) AS missing,
                        SUM(CASE WHEN conflict_type = 'nd_ot' THEN 1 ELSE 0 END) AS nd_ot
                    FROM vw_shift_conflicts
                ");
                $row = $stmt->fetch();
                $metrics['conflicts_open']    = (int)(($row['overlaps'] ?? 0) + ($row['missing'] ?? 0));
                $metrics['nd_ot_flags_today'] = (int)($row['nd_ot'] ?? 0);
            }
        }
    } catch (PDOException $e) {
        error_log('Super admin shift & scheduling error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'Master Shift Planner & Scheduling',
    'description' => 'Validate global schedules, monitor coverage, and proactively detect conflicts, overlaps, and night differential boundaries.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Shift & Scheduling'],
    ],
    'metrics' => [
        [
            'label' => 'Active Shift Patterns',
            'value' => $metrics['active_patterns'],
            'sub'   => 'Hospital-wide templates and rotations',
            'icon'  => 'calendar-range',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Shifts Today',
            'value' => $metrics['scheduled_today'],
            'sub'   => 'Scheduled across all departments',
            'icon'  => 'calendar-clock',
            'tone'  => 'info',
        ],
        [
            'label' => 'Open Conflicts',
            'value' => $metrics['conflicts_open'],
            'sub'   => 'Overlaps or missing shift assignments',
            'icon'  => 'alert-octagon',
            'tone'  => 'danger',
        ],
        [
            'label' => 'ND / OT Boundary Flags',
            'value' => $metrics['nd_ot_flags_today'],
            'sub'   => 'Night differential & overtime risk areas',
            'icon'  => 'moon-star',
            'tone'  => 'warning',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'range',
            'label' => 'Schedule Window',
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
            'name'    => 'conflict_type',
            'label'   => 'Conflict Type',
            'options' => [
                ['value' => '',        'label' => 'All'],
                ['value' => 'overlap', 'label' => 'Overlap'],
                ['value' => 'missing', 'label' => 'Missing Shift'],
                ['value' => 'nd_ot',   'label' => 'ND / OT Flag'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'shift_date',      'label' => 'Date',        'class' => 'text-xs'],
            ['key' => 'employee_name',   'label' => 'Employee',    'class' => 'text-xs'],
            ['key' => 'department_name', 'label' => 'Department',  'class' => 'text-xs'],
            ['key' => 'conflict_type',   'label' => 'Issue',       'class' => 'text-xs'],
            ['key' => 'severity',        'label' => 'Severity',    'class' => 'text-xs'],
            ['key' => 'details',         'label' => 'Details',     'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No scheduling conflicts detected',
        'message'      => 'As schedules are created and validated, any overlaps or coverage gaps will surface here for global review.',
        'icon'         => 'calendar-check',
        'action_label' => 'Open timesheets',
        'action_href'  => BASE_URL . '/super_admin/modules/super_admin-timesheets.php',
        'action_icon'  => 'file-axis-3d',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



