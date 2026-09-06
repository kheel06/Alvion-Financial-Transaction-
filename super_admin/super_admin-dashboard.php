<?php
require_once __DIR__ . '/../config/config.php';
requireAuth();
checkRole(['super_admin']);

$page_title = 'Super Admin Dashboard';

// High-level HRIS metrics (guarded by table-existence checks)
$stats = [
    'total_employees'      => 0,
    'total_departments'    => 0,
    'open_approvals'       => 0,
    'attendance_issues'    => 0,
    'scheduling_conflicts' => 0,
];

if (isset($db)) {
    try {
        // Employees
        $check = $db->query("SHOW TABLES LIKE 'employees'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM employees");
            $row = $stmt->fetch();
            $stats['total_employees'] = (int)($row['c'] ?? 0);
        }

        // Departments
        $check = $db->query("SHOW TABLES LIKE 'departments'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM departments");
            $row = $stmt->fetch();
            $stats['total_departments'] = (int)($row['c'] ?? 0);
        }

        // Open approvals (generic queue table)
        $check = $db->query("SHOW TABLES LIKE 'approval_requests'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) AS c FROM approval_requests WHERE status = 'pending'");
            $row = $stmt->fetch();
            $stats['open_approvals'] = (int)($row['c'] ?? 0);
        }

        // Attendance issues
        $check = $db->query("SHOW TABLES LIKE 'attendance_logs'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status = 'no_log' THEN 1 ELSE 0 END) AS no_log_count
                FROM attendance_logs
                WHERE log_date = CURDATE()
            ");
            $row = $stmt->fetch();
            $stats['attendance_issues'] =
                (int)($row['late_count'] ?? 0) +
                (int)($row['no_log_count'] ?? 0);
        }

        // Scheduling conflicts
        $check = $db->query("SHOW TABLES LIKE 'shifts'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->query("
                SELECT COUNT(*) AS c 
                FROM shifts 
                WHERE conflict_flag = 1
            ");
            $row = $stmt->fetch();
            $stats['scheduling_conflicts'] = (int)($row['c'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Super admin dashboard metrics error: ' . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Super Admin Overview
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Global visibility across all HR, attendance, and scheduling operations.
    </p>
</div>

<!-- Top Metrics -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Total Employees
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                <?php echo number_format($stats['total_employees']); ?>
            </p>
            <p class="mt-1 text-xs text-gray-500">Across all hospital units</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
            <svg data-lucide="users" class="w-5 h-5"></svg>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Departments
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                <?php echo number_format($stats['total_departments']); ?>
            </p>
            <p class="mt-1 text-xs text-gray-500">Clinical & support units</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <svg data-lucide="building-2" class="w-5 h-5"></svg>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Open Approvals
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                <?php echo number_format($stats['open_approvals']); ?>
            </p>
            <p class="mt-1 text-xs text-gray-500">Leave, OT, claims & exceptions</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
            <svg data-lucide="inbox" class="w-5 h-5"></svg>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Today’s Attendance Issues
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                <?php echo number_format($stats['attendance_issues']); ?>
            </p>
            <p class="mt-1 text-xs text-gray-500">Late & no-log events today</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center">
            <svg data-lucide="alert-triangle" class="w-5 h-5"></svg>
        </div>
    </div>
</div>

<!-- Two-column layout: attendance insights & approvals -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Attendance Insights
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    High-level view of today’s presence across all departments.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-primary-50 text-primary-700 px-2 py-0.5 text-[11px] font-medium">
                Real-time
            </span>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                    <p class="text-gray-500 mb-1">Present</p>
                    <p class="text-lg font-semibold text-emerald-600">--</p>
                </div>
                <div>
                    <p class="text-gray-500 mb-1">Late</p>
                    <p class="text-lg font-semibold text-amber-600">--</p>
                </div>
                <div>
                    <p class="text-gray-500 mb-1">Absent / No Logs</p>
                    <p class="text-lg font-semibold text-rose-600">--</p>
                </div>
                <div>
                    <p class="text-gray-500 mb-1">On Leave / Off</p>
                    <p class="text-lg font-semibold text-sky-600">--</p>
                </div>
            </div>
            <div class="mt-4 h-40 rounded-lg bg-gradient-to-r from-primary-50 via-sky-50 to-emerald-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 flex items-center justify-center text-xs text-gray-400">
                Attendance trend chart placeholder – connect to analytics later.
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Open Approval Queues
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Consolidated leave, OT, and claims awaiting action.
                </p>
            </div>
        </div>
        <div class="flex-1 p-4 space-y-3 text-xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-7 h-7 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">
                        <svg data-lucide="calendar-clock" class="w-4 h-4"></svg>
                    </span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Leave Requests</p>
                        <p class="text-[11px] text-gray-500">Pending across all units</p>
                    </div>
                </div>
                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100">--</span>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg data-lucide="clock-3" class="w-4 h-4"></svg>
                    </span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">OT Requests</p>
                        <p class="text-[11px] text-gray-500">Awaiting HR / finance validation</p>
                    </div>
                </div>
                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100">--</span>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-7 h-7 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                        <svg data-lucide="receipt-text" class="w-4 h-4"></svg>
                    </span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Claims & Reimbursements</p>
                        <p class="text-[11px] text-gray-500">Cross-department submissions</p>
                    </div>
                </div>
                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100">--</span>
            </div>

            <div class="pt-2">
                <a href="<?php echo BASE_URL; ?>/super_admin/modules/super_admin-leaves_&_overtime.php"
                   class="inline-flex items-center text-[11px] font-medium text-primary-600 hover:text-primary-700">
                    Go to approvals
                    <svg data-lucide="arrow-right" class="w-3 h-3 ml-1.5"></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- System health & alerts -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 lg:col-span-2">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Scheduling & Coverage
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Detect potential coverage gaps and conflicting shifts.
                </p>
            </div>
        </div>
        <div class="p-5 text-xs text-gray-500 dark:text-gray-400">
            <p class="mb-2">
                This panel will surface:
            </p>
            <ul class="list-disc list-inside space-y-1">
                <li>Units with understaffing in critical shifts.</li>
                <li>Night differential and OT boundary breaches.</li>
                <li>Overlapping schedules and validation errors.</li>
            </ul>
            <div class="mt-4 h-32 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center">
                Scheduling analytics placeholder – connect to master shift planner.
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    System Warnings & Security
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Authentication, access, and audit risks.
                </p>
            </div>
        </div>
        <div class="p-4 space-y-3 text-xs">
            <div class="flex items-start space-x-2">
                <span class="mt-0.5 w-6 h-6 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center">
                    <svg data-lucide="shield-alert" class="w-4 h-4"></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">
                        Security posture
                    </p>
                    <p class="text-[11px] text-gray-500">
                        Review MFA, password policies, and login anomaly reports regularly.
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-2">
                <span class="mt-0.5 w-6 h-6 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg data-lucide="activity" class="w-4 h-4"></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">
                        Audit log monitoring
                    </p>
                    <p class="text-[11px] text-gray-500">
                        Use the audit log module to trace changes to timesheets, roles, and security settings.
                    </p>
                </div>
            </div>
            <div class="pt-2">
                <a href="<?php echo BASE_URL; ?>/super_admin/modules/super_admin-audit_logs.php"
                   class="inline-flex items-center text-[11px] font-medium text-primary-600 hover:text-primary-700">
                    Open audit logs
                    <svg data-lucide="external-link" class="w-3 h-3 ml-1.5"></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>



