<?php
require_once __DIR__ . '/../config/config.php';
requireAuth();
checkRole(['staff', 'staff supervisor', 'super admin']);

$page_title = 'Department Supervisor Dashboard';

$stats = [
    'team_size'        => 0,
    'present_today'    => 0,
    'late_today'       => 0,
    'pending_approvals'=> 0,
];

$departmentId = $_SESSION['department_id'] ?? null;

if ($departmentId && isset($db)) {
    try {
        // Team size
        $check = $db->query("SHOW TABLES LIKE 'employees'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS c 
                FROM employees 
                WHERE department_id = :dept
            ");
            $stmt->bindValue(':dept', $departmentId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stats['team_size'] = (int)($row['c'] ?? 0);
        }

        // Attendance for today (present / late)
        $check = $db->query("SHOW TABLES LIKE 'attendance_logs'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->prepare("
                SELECT 
                    SUM(CASE WHEN status IN ('present','on_time') THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count
                FROM attendance_logs al
                JOIN employees e ON e.id = al.employee_id
                WHERE al.log_date = CURDATE()
                  AND e.department_id = :dept
            ");
            $stmt->bindValue(':dept', $departmentId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stats['present_today'] = (int)($row['present_count'] ?? 0);
            $stats['late_today']    = (int)($row['late_count'] ?? 0);
        }

        // Pending approvals assigned to this supervisor
        $check = $db->query("SHOW TABLES LIKE 'approval_requests'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS c
                FROM approval_requests
                WHERE status = 'pending'
                  AND department_id = :dept
                  AND approver_role IN ('staff','supervisor')
            ");
            $stmt->bindValue(':dept', $departmentId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stats['pending_approvals'] = (int)($row['c'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Staff dashboard metrics error: ' . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Department Overview
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Track your team’s presence, quickly resolve attendance exceptions, and stay ahead of approvals.
    </p>
</div>

<!-- Team presence snapshot -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Team Size
            </p>
            <span class="w-7 h-7 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
                <svg data-lucide="users" class="w-4 h-4"></svg>
            </span>
        </div>
        <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">
            <?php echo number_format($stats['team_size']); ?>
        </p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Present Today
            </p>
            <span class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg data-lucide="check-circle-2" class="w-4 h-4"></svg>
            </span>
        </div>
        <p class="mt-3 text-2xl font-semibold text-emerald-600">
            <?php echo number_format($stats['present_today']); ?>
        </p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Late Today
            </p>
            <span class="w-7 h-7 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                <svg data-lucide="clock-alert" class="w-4 h-4"></svg>
            </span>
        </div>
        <p class="mt-3 text-2xl font-semibold text-amber-600">
            <?php echo number_format($stats['late_today']); ?>
        </p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Pending Approvals
            </p>
            <span class="w-7 h-7 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">
                <svg data-lucide="inbox" class="w-4 h-4"></svg>
            </span>
        </div>
        <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">
            <?php echo number_format($stats['pending_approvals']); ?>
        </p>
    </div>
</div>

<!-- Team presence & schedule snapshot -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Team Presence Tracking
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Quickly identify late, absent, and at-risk staffing for the current shift.
                </p>
            </div>
        </div>
        <div class="p-5 text-xs text-gray-500 dark:text-gray-400">
            <div class="h-40 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center">
                Department presence grid placeholder – link to team attendance module.
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Pending Approvals Summary
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Approvals at your endorsement level before HR processing.
                </p>
            </div>
        </div>
        <div class="p-4 space-y-3 text-xs">
            <div class="flex items-start space-x-2">
                <span class="mt-0.5 w-6 h-6 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">
                    <svg data-lucide="calendar-clock" class="w-4 h-4"></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Leave Requests</p>
                    <p class="text-[11px] text-gray-500">
                        First-level approval for team leave balancing.
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-2">
                <span class="mt-0.5 w-6 h-6 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg data-lucide="clock-3" class="w-4 h-4"></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Overtime Requests</p>
                    <p class="text-[11px] text-gray-500">
                        Validate workload and fairness before endorsing to HR.
                    </p>
                </div>
            </div>
            <div class="pt-2">
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-team_attendance.php"
                   class="inline-flex items-center text-[11px] font-medium text-primary-600 hover:text-primary-700">
                    Open team attendance
                    <svg data-lucide="arrow-right" class="w-3 h-3 ml-1.5"></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>



