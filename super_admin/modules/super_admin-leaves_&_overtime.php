<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Leaves & Overtime (Global)';

$metrics = [
    'pending_leaves'   => 0,
    'pending_ot'       => 0,
    'today_on_leave'   => 0,
    'escalated_items'  => 0,
];

if (isset($db)) {
    try {
        // Pending leave requests
        $check = $db->query("SHOW TABLES LIKE 'leave_requests'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) AS escalated
                FROM leave_requests
            ");
            $row = $stmt->fetch();
            $metrics['pending_leaves']  = (int)($row['pending'] ?? 0);
            $metrics['escalated_items'] += (int)($row['escalated'] ?? 0);

            $stmt = $db->query("
                SELECT COUNT(*) AS c
                FROM leave_requests
                WHERE status IN ('approved','ongoing')
                  AND CURDATE() BETWEEN start_date AND end_date
            ");
            $row = $stmt->fetch();
            $metrics['today_on_leave'] = (int)($row['c'] ?? 0);
        }

        // Pending overtime requests
        $check = $db->query("SHOW TABLES LIKE 'overtime_requests'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) AS escalated
                FROM overtime_requests
            ");
            $row = $stmt->fetch();
            $metrics['pending_ot']      = (int)($row['pending'] ?? 0);
            $metrics['escalated_items'] += (int)($row['escalated'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Super admin leaves/OT metrics error: ' . $e->getMessage());
    }
}

// Simple global queue listing (if views exist)
$rows = [];
if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'vw_global_leave_ot_queue'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    request_type,
                    employee_name,
                    department_name,
                    submitted_at,
                    status,
                    current_level
                FROM vw_global_leave_ot_queue
                ORDER BY submitted_at DESC
                LIMIT 100
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('Super admin leaves/OT queue error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'Leaves & Overtime (Global)',
    'description' => 'Monitor and control multi-level leave and overtime approvals across all departments.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Leaves & OT'],
    ],
    'metrics' => [
        [
            'label' => 'Pending Leave Requests',
            'value' => $metrics['pending_leaves'],
            'sub'   => 'Across all approval levels',
            'icon'  => 'calendar-clock',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Pending OT Requests',
            'value' => $metrics['pending_ot'],
            'sub'   => 'Awaiting endorsement or HR decision',
            'icon'  => 'clock-3',
            'tone'  => 'warning',
        ],
        [
            'label' => 'Staff On Leave Today',
            'value' => $metrics['today_on_leave'],
            'sub'   => 'Current day utilization',
            'icon'  => 'plane',
            'tone'  => 'info',
        ],
        [
            'label' => 'Escalated Items',
            'value' => $metrics['escalated_items'],
            'sub'   => 'Beyond normal SLA or multi-level routing',
            'icon'  => 'alert-octagon',
            'tone'  => 'danger',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'range',
            'label' => 'Request Date',
        ],
        [
            'type'    => 'select',
            'name'    => 'type',
            'label'   => 'Type',
            'options' => [
                ['value' => '',         'label' => 'All'],
                ['value' => 'leave',    'label' => 'Leave'],
                ['value' => 'overtime', 'label' => 'Overtime'],
            ],
        ],
        [
            'type'    => 'select',
            'name'    => 'status',
            'label'   => 'Status',
            'options' => [
                ['value' => '',          'label' => 'All'],
                ['value' => 'pending',   'label' => 'Pending'],
                ['value' => 'approved',  'label' => 'Approved'],
                ['value' => 'rejected',  'label' => 'Rejected'],
                ['value' => 'escalated', 'label' => 'Escalated'],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'request_type',    'label' => 'Type',        'class' => 'text-xs'],
            ['key' => 'employee_name',   'label' => 'Employee',    'class' => 'text-xs'],
            ['key' => 'department_name', 'label' => 'Department',  'class' => 'text-xs'],
            ['key' => 'submitted_at',    'label' => 'Submitted',   'class' => 'text-xs'],
            ['key' => 'status',          'label' => 'Status',      'class' => 'text-xs'],
            ['key' => 'current_level',   'label' => 'Level',       'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No leave or OT requests in the queue',
        'message'      => 'As employees file requests, they will appear here with full approval trail and status.',
        'icon'         => 'clipboard-list',
        'action_label' => 'Open reporting',
        'action_href'  => BASE_URL . '/super_admin/modules/super_admin-reporting.php',
        'action_icon'  => 'bar-chart-3',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



