<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Hospital Departments';

$metrics = [
    'departments'   => 0,
    'units'         => 0,
    'multi_sup'     => 0,
    'mapped_admins' => 0,
];

$rows = [];

if (isset($db)) {
    try {
        $check = $db->query("SHOW TABLES LIKE 'departments'");
        if ($check && $check->rowCount() > 0) {
            // Department hierarchy metrics
            $stmt = $db->query("
                SELECT 
                    COUNT(*) AS dept_count,
                    SUM(COALESCE(unit_count,0)) AS unit_count,
                    SUM(CASE WHEN supervisor_count > 1 THEN 1 ELSE 0 END) AS multi_sup_count,
                    SUM(CASE WHEN admin_user_id IS NOT NULL THEN 1 ELSE 0 END) AS admin_mapped
                FROM departments
            ");
            $row = $stmt->fetch();
            $metrics['departments']   = (int)($row['dept_count'] ?? 0);
            $metrics['units']         = (int)($row['unit_count'] ?? 0);
            $metrics['multi_sup']     = (int)($row['multi_sup_count'] ?? 0);
            $metrics['mapped_admins'] = (int)($row['admin_mapped'] ?? 0);

            // Detailed listing (if view exists)
            $checkView = $db->query("SHOW TABLES LIKE 'vw_department_hierarchy'");
            if ($checkView && $checkView->rowCount() > 0) {
                $stmt = $db->query("
                    SELECT 
                        department_name,
                        unit_name,
                        supervisor_names,
                        admin_name,
                        headcount
                    FROM vw_department_hierarchy
                    ORDER BY department_name, unit_name
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (PDOException $e) {
        error_log('Super admin department metrics error: ' . $e->getMessage());
    }
}

$moduleConfig = [
    'title'       => 'Hospital Departments & Units',
    'description' => 'Manage the hospital’s department hierarchy, unit structure, and supervisor/admin mappings.',
    'breadcrumbs' => [
        ['label' => 'Super Admin', 'href' => BASE_URL . '/super_admin/super_admin-dashboard.php'],
        ['label' => 'Departments'],
    ],
    'actions' => [
        [
            'label' => 'Add Department',
            'href'  => BASE_URL . '/admin/system/clinic_hours.php',
            'icon'  => 'building-2',
        ],
    ],
    'metrics' => [
        [
            'label' => 'Departments',
            'value' => $metrics['departments'],
            'sub'   => 'Major hospital departments',
            'icon'  => 'building-2',
            'tone'  => 'primary',
        ],
        [
            'label' => 'Units / Wards',
            'value' => $metrics['units'],
            'sub'   => 'Sub-units and service lines',
            'icon'  => 'layout-panel-left',
            'tone'  => 'info',
        ],
        [
            'label' => 'Multi-supervisor Units',
            'value' => $metrics['multi_sup'],
            'sub'   => 'Configured with more than one supervisor',
            'icon'  => 'user-square-2',
            'tone'  => 'warning',
        ],
        [
            'label' => 'Units with Admin Mapping',
            'value' => $metrics['mapped_admins'],
            'sub'   => 'Linked to a department admin/timekeeper',
            'icon'  => 'shield-check',
            'tone'  => 'success',
        ],
    ],
    'filters' => [
        [
            'type'        => 'search',
            'name'        => 'search',
            'label'       => 'Search',
            'placeholder' => 'Search by department, unit, or supervisor',
        ],
    ],
    'table' => [
        'columns' => [
            ['key' => 'department_name',  'label' => 'Department',  'class' => 'text-xs'],
            ['key' => 'unit_name',        'label' => 'Unit / Ward', 'class' => 'text-xs'],
            ['key' => 'supervisor_names', 'label' => 'Supervisors', 'class' => 'text-xs'],
            ['key' => 'admin_name',       'label' => 'Department Admin', 'class' => 'text-xs'],
            ['key' => 'headcount',        'label' => 'Headcount',   'class' => 'text-xs'],
        ],
        'rows' => $rows,
    ],
    'empty_state' => [
        'title'        => 'No departments configured yet',
        'message'      => 'Define the hospital’s departments and units so scheduling and reporting align with your organization structure.',
        'icon'         => 'building-2',
        'action_label' => 'Configure departments',
        'action_href'  => BASE_URL . '/admin/system/system_settings.php',
        'action_icon'  => 'plus',
    ],
];

include __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/hris_module.php';

renderHrisModule($moduleConfig);

include __DIR__ . '/../../includes/footer.php';



