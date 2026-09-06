<?php
require_once __DIR__ . '/../config/config.php';
requireAuth();
checkRole(['employee']);

$page_title = 'My HR Homepage';

$employeeId = $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? null;

$stats = [
    'upcoming_shift'  => null,
    'pending_requests'=> 0,
    'attendance_rate' => null,
];

if ($employeeId && isset($db)) {
    try {
        // Pending self-service requests
        $check = $db->query("SHOW TABLES LIKE 'self_service_requests'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS c
                FROM self_service_requests
                WHERE employee_id = :emp
                  AND status IN ('pending','in_review')
            ");
            $stmt->bindValue(':emp', $employeeId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stats['pending_requests'] = (int)($row['c'] ?? 0);
        }

        // Attendance rate (last 30 working days)
        $check = $db->query("SHOW TABLES LIKE 'attendance_logs'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN status IN ('present','on_time') THEN 1 ELSE 0 END) AS present_days
                FROM attendance_logs
                WHERE employee_id = :emp
                  AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->bindValue(':emp', $employeeId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            if (!empty($row['total_days'])) {
                $stats['attendance_rate'] = round(($row['present_days'] / $row['total_days']) * 100);
            }
        }

        // Next scheduled shift
        $check = $db->query("SHOW TABLES LIKE 'shifts'");
        if ($check && $check->rowCount() > 0) {
            $stmt = $db->prepare("
                SELECT shift_date, start_time, end_time, unit_name
                FROM shifts
                WHERE employee_id = :emp
                  AND (shift_date > CURDATE() OR (shift_date = CURDATE() AND end_time >= CURTIME()))
                ORDER BY shift_date ASC, start_time ASC
                LIMIT 1
            ");
            $stmt->bindValue(':emp', $employeeId, PDO::PARAM_INT);
            $stmt->execute();
            $stats['upcoming_shift'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (PDOException $e) {
        error_log('Employee dashboard metrics error: ' . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Employee'); ?>
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Your personal HR hub for shifts, attendance, requests, and notifications.
    </p>
</div>

<!-- Personal metrics -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Attendance Rate (30 days)
            </p>
            <p class="mt-2 text-2xl font-semibold text-emerald-600">
                <?php echo $stats['attendance_rate'] !== null ? $stats['attendance_rate'] . '%' : '--'; ?>
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Keep your logs complete for smooth payroll
            </p>
        </div>
        <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <svg data-lucide="activity" class="w-4 h-4"></svg>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Pending Requests
            </p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                <?php echo number_format($stats['pending_requests']); ?>
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Leave, OT, claims, and corrections
            </p>
        </div>
        <div class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">
            <svg data-lucide="inbox" class="w-4 h-4"></svg>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Next Shift
            </p>
            <?php if ($stats['upcoming_shift']): ?>
                <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                    <?php echo htmlspecialchars($stats['upcoming_shift']['shift_date'] ?? ''); ?>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    <?php
                    echo htmlspecialchars(($stats['upcoming_shift']['start_time'] ?? '') . ' - ' . ($stats['upcoming_shift']['end_time'] ?? ''));
                    if (!empty($stats['upcoming_shift']['unit_name'])) {
                        echo ' • ' . htmlspecialchars($stats['upcoming_shift']['unit_name']);
                    }
                    ?>
                </p>
            <?php else: ?>
                <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                    No upcoming shift found
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Check your schedule or contact your supervisor.
                </p>
            <?php endif; ?>
        </div>
        <div class="w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
            <svg data-lucide="calendar-clock" class="w-4 h-4"></svg>
        </div>
    </div>
</div>

<!-- Two-column layout: shift overview & requests -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Personal Shift Overview
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    See your upcoming duties and navigate directly to schedule tools.
                </p>
            </div>
        </div>
        <div class="p-5 text-xs text-gray-500 dark:text-gray-400">
            <div class="h-40 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center">
                Personal calendar placeholder – view details in the "My Schedules" module.
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="<?php echo BASE_URL; ?>/employee/modules/employee-my_schedules.php"
                   class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg data-lucide="calendar-range" class="w-3.5 h-3.5 mr-1.5"></svg>
                    Open My Schedules
                </a>
                <a href="<?php echo BASE_URL; ?>/employee/modules/employee-timeclock.php"
                   class="inline-flex items-center rounded-lg border border-primary-600 bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                    <svg data-lucide="clock-3" class="w-3.5 h-3.5 mr-1.5"></svg>
                    Go to Timeclock
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Notifications & Requests
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Stay updated on approvals, schedule changes, and reminders.
                </p>
            </div>
        </div>
        <div class="p-4 space-y-3 text-xs">
            <div class="flex items-start space-x-2">
                <span class="mt-0.5 w-6 h-6 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">
                    <svg data-lucide="bell" class="w-4 h-4"></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Approvals</p>
                    <p class="text-[11px] text-gray-500">
                        Track the status of your leave, overtime, and claim submissions.
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-2">
                <span class="mt-0.5 w-6 h-6 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg data-lucide="calendar-alert" class="w-4 h-4"></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">Schedule Changes</p>
                    <p class="text-[11px] text-gray-500">
                        Receive alerts when supervisors adjust or swap your assigned shifts.
                    </p>
                </div>
            </div>
            <div class="pt-2">
                <a href="<?php echo BASE_URL; ?>/employee/modules/employee-notification.php"
                   class="inline-flex items-center text-[11px] font-medium text-primary-600 hover:text-primary-700">
                    Open notification center
                    <svg data-lucide="arrow-right" class="w-3 h-3 ml-1.5"></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>



