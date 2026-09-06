<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Claims & Reimbursements (Global)';

$metrics = [
    'pending_claims' => 0,
    'approved_claims'=> 0,
    'rejected_claims'=> 0,
    'turnaround_days'=> null,
];

$rows = [];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'claims'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)   AS pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END)  AS approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END)  AS rejected_count,
                    AVG(DATEDIFF(COALESCE(resolved_at, NOW()), submitted_at)) AS avg_days
                FROM claims
            ");
            $row = $stmt->fetch();
            $metrics['pending_claims']  = (int)($row['pending_count'] ?? 0);
            $metrics['approved_claims'] = (int)($row['approved_count'] ?? 0);
            $metrics['rejected_claims'] = (int)($row['rejected_count'] ?? 0);
            if (!empty($row['avg_days'])) {
                $metrics['turnaround_days'] = round($row['avg_days'], 1);
            }

            $checkView = $db->query("SHOW TABLES LIKE 'vw_claims_audit_view'");
            if ($checkView && $checkView->rowCount() > 0) {
                $stmt = $db->query("
                    SELECT 
                        claim_ref,
                        employee_name,
                        department_name,
                        amount,
                        status,
                        submitted_at,
                        last_action_by
                    FROM vw_claims_audit_view
                    ORDER BY submitted_at DESC
                    LIMIT 200
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (PDOException $e) {
        error_log('Super admin claims & reimbursements error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'Claims & Reimbursements (Global)',
    'description' => 'Cross-department view of employee claims, with full approval audit trail and financial oversight.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Claims & Reimbursements'],
    ],
    'metrics' => [
        [
            'label' => 'Pending Claims',
            'value' => $metrics['pending_claims'],
            'sub'   => 'Awaiting verification and HR/finance approval',
            'icon'  => 'badge-alert',
            'tone'  => 'warning',
        ],
        [
            'label' => 'Approved Claims',
            'value' => $metrics['approved_claims'],
            'sub'   => 'Ready for or already processed in payroll',
            'icon'  => 'check-circle-2',
            'tone'  => 'success',
        ],
        [
            'label' => 'Rejected Claims',
            'value' => $metrics['rejected_claims'],
            'sub'   => 'Declined due to policy or validation issues',
            'icon'  => 'x-circle',
            'tone'  => 'danger',
        ],
        [
            'label' => 'Avg. Turnaround (days)',
            'value' => $metrics['turnaround_days'] !== null ? $metrics['turnaround_days'] : '--',
            'sub'   => 'From submission to final decision',
            'icon'  => 'timer',
            'tone'  => 'primary',
        ],
    ],
    'filters' => [
        [
            'type'  => 'date-range',
            'name'  => 'submitted_range',
            'label' => 'Submitted Range',
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
            ],
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'claim_ref',      'label' => 'Reference',   'class' => 'text-xs'],
            ['key' => 'employee_name',  'label' => 'Employee',    'class' => 'text-xs'],
            ['key' => 'department_name','label' => 'Department',  'class' => 'text-xs'],
            ['key' => 'amount',         'label' => 'Amount',      'class' => 'text-xs'],
            ['key' => 'status',         'label' => 'Status',      'class' => 'text-xs'],
            ['key' => 'submitted_at',   'label' => 'Submitted',   'class' => 'text-xs'],
            ['key' => 'last_action_by', 'label' => 'Last Action', 'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No claims recorded yet',
        'message'      => 'Once employees start filing claims, they will be tracked here with detailed timestamps and approvals.',
        'icon'         => 'receipt-text',
        'action_label' => 'Open HR claims module',
        'action_href'  => BASE_URL . '/admin/modules/admin-claims_&_reimbursment.php',
        'action_icon'  => 'folder-kanban',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



