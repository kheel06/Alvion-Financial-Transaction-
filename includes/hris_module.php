<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Minimal safety: this file should only be included from pages that already
// bootstrapped `$db`, `BASE_URL`, and included `header.php`.

if (!function_exists('renderHrisModule')) {
    /**
     * Generic renderer for HR / Attendance modules.
     *
     * Expected $module shape (all keys optional but recommended):
     *  - 'title'        : string  Page title (required)
     *  - 'description'  : string  Short description under header
     *  - 'breadcrumbs'  : array[] [ ['label' => '...', 'href' => '...'], ... ]
     *  - 'metrics'      : array[] [ ['label','value','sub','icon','tone'] ]
     *  - 'filters'      : array[] simple filter declarations (type, name, label)
     *  - 'table'        : [
     *        'columns' => [ ['key','label','class' => ''], ... ],
     *        'rows'    => array<int,array<string,mixed>>
     *    ]
     *  - 'empty_state'  : [ 'title','message','action_label','action_href' ]
     *  - 'actions'      : array[] primary page actions (e.g. "New Shift Template")
     */
    function renderHrisModule(array $module): void
    {
        $title       = $module['title'] ?? 'Module';
        $description = $module['description'] ?? '';
        $breadcrumbs = $module['breadcrumbs'] ?? [];
        $metrics     = $module['metrics'] ?? [];
        $filters     = $module['filters'] ?? [];
        $table       = $module['table'] ?? ['columns' => [], 'rows' => []];
        $emptyState  = $module['empty_state'] ?? [];
        $actions     = $module['actions'] ?? [];

        $columns = $table['columns'] ?? [];
        $rows    = $table['rows'] ?? [];
        $hasRows = !empty($rows);
        ?>

        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <?php if (!empty($breadcrumbs)): ?>
                        <nav class="flex text-sm text-gray-500 dark:text-gray-400 mb-1" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-2">
                                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                                    <li class="inline-flex items-center">
                                        <?php if (!empty($crumb['href']) && $i < count($breadcrumbs) - 1): ?>
                                            <a href="<?php echo htmlspecialchars($crumb['href']); ?>"
                                               class="inline-flex items-center hover:text-primary-600 dark:hover:text-primary-400">
                                                <?php if ($i === 0): ?>
                                                    <svg data-lucide="home" class="w-3.5 h-3.5 mr-1.5"></svg>
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($crumb['label']); ?></span>
                                            </a>
                                            <?php if ($i < count($breadcrumbs) - 1): ?>
                                                <svg data-lucide="chevron-right" class="w-3 h-3 mx-1"></svg>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center font-medium text-gray-700 dark:text-gray-200">
                                                <?php if ($i === 0): ?>
                                                    <svg data-lucide="home" class="w-3.5 h-3.5 mr-1.5"></svg>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($crumb['label']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>

                    <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars($title); ?>
                    </h1>
                    <?php if ($description): ?>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
                            <?php echo htmlspecialchars($description); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($actions)): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($actions as $action): ?>
                            <a href="<?php echo htmlspecialchars($action['href'] ?? '#'); ?>"
                               title="<?php echo htmlspecialchars($action['label'] ?? 'Action'); ?>"
                               class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                <?php if (!empty($action['icon'])): ?>
                                    <svg data-lucide="<?php echo htmlspecialchars($action['icon']); ?>" class="w-4 h-4"></svg>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($action['label'] ?? 'Action'); ?>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Metric Cards -->
            <?php if (!empty($metrics)): ?>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($metrics as $metric): ?>
                        <?php
                        $tone  = $metric['tone'] ?? 'primary';
                        $toneMap = [
                            'primary' => 'bg-primary-50 text-primary-600',
                            'success' => 'bg-emerald-50 text-emerald-600',
                            'warning' => 'bg-amber-50 text-amber-600',
                            'danger'  => 'bg-rose-50 text-rose-600',
                            'info'    => 'bg-sky-50 text-sky-600',
                        ];
                        $chipClass = $toneMap[$tone] ?? $toneMap['primary'];
                        ?>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-xl border border-gray-100 dark:border-gray-700">
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($metric['label'] ?? 'Metric'); ?>
                                        </p>
                                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars((string)($metric['value'] ?? '0')); ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full <?php echo $chipClass; ?>">
                                        <?php if (!empty($metric['icon'])): ?>
                                            <svg data-lucide="<?php echo htmlspecialchars($metric['icon']); ?>"
                                                 class="w-5 h-5"></svg>
                                        <?php else: ?>
                                            <svg data-lucide="activity" class="w-5 h-5"></svg>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($metric['sub'])): ?>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($metric['sub']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Filters + Tools -->
            <?php if (!empty($filters)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 px-4 py-3 flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex flex-wrap gap-3 items-center">
                        <?php foreach ($filters as $filter): ?>
                            <?php
                            $type  = $filter['type'] ?? 'select';
                            $name  = $filter['name'] ?? '';
                            $label = $filter['label'] ?? '';
                            ?>
                            <div class="flex flex-col">
                                <?php if ($label): ?>
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1"
                                           for="<?php echo htmlspecialchars($name); ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </label>
                                <?php endif; ?>

                                <?php if ($type === 'search'): ?>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-400">
                                            <svg data-lucide="search" class="w-3.5 h-3.5"></svg>
                                        </span>
                                        <input
                                            id="<?php echo htmlspecialchars($name); ?>"
                                            name="<?php echo htmlspecialchars($name); ?>"
                                            type="search"
                                            class="pl-8 pr-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                            placeholder="<?php echo htmlspecialchars($filter['placeholder'] ?? 'Search'); ?>"
                                        />
                                    </div>
                                <?php elseif ($type === 'date-range'): ?>
                                    <div class="flex items-center space-x-2">
                                        <input
                                            type="date"
                                            id="<?php echo htmlspecialchars($name . '_from'); ?>"
                                            name="<?php echo htmlspecialchars($name . '_from'); ?>"
                                            class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                        />
                                        <span class="text-xs text-gray-400">to</span>
                                        <input
                                            type="date"
                                            id="<?php echo htmlspecialchars($name . '_to'); ?>"
                                            name="<?php echo htmlspecialchars($name . '_to'); ?>"
                                            class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                        />
                                    </div>
                                <?php else: ?>
                                    <select
                                        id="<?php echo htmlspecialchars($name); ?>"
                                        name="<?php echo htmlspecialchars($name); ?>"
                                        class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                    >
                                        <?php foreach (($filter['options'] ?? []) as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt['value']); ?>">
                                                <?php echo htmlspecialchars($opt['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" title="Refresh"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                            <svg data-lucide="refresh-ccw" class="w-4 h-4"></svg>
                        </button>
                        <button type="button" title="Export"
                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-primary-700">
                            <svg data-lucide="download" class="w-4 h-4"></svg>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th scope="col"
                                    class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider <?php echo htmlspecialchars($col['class'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($col['label'] ?? ''); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        <?php if ($hasRows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors">
                                    <?php foreach ($columns as $col): ?>
                                        <?php $key = $col['key'] ?? ''; ?>
                                        <td class="px-4 py-2.5 whitespace-nowrap text-xs text-gray-800 dark:text-gray-100 <?php echo htmlspecialchars($col['class'] ?? ''); ?>">
                                            <?php
                                            $value = $row[$key] ?? '';
                                            echo htmlspecialchars((string)$value);
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo max(1, count($columns)); ?>"
                                    class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-900">
                                            <svg data-lucide="<?php echo htmlspecialchars($emptyState['icon'] ?? 'clipboard-list'); ?>"
                                                 class="w-6 h-6 text-gray-400"></svg>
                                        </div>
                                        <p class="font-medium text-gray-800 dark:text-gray-200">
                                            <?php echo htmlspecialchars($emptyState['title'] ?? 'No records found'); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md">
                                            <?php echo htmlspecialchars($emptyState['message'] ?? 'Adjust filters or try again later.'); ?>
                                        </p>
                                        <?php if (!empty($emptyState['action_href'])): ?>
                                            <a href="<?php echo htmlspecialchars($emptyState['action_href']); ?>"
                                               class="inline-flex items-center mt-2 rounded-lg border border-transparent bg-primary-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-primary-700">
                                                <svg data-lucide="<?php echo htmlspecialchars($emptyState['action_icon'] ?? 'plus-circle'); ?>"
                                                     class="w-3.5 h-3.5 mr-1.5"></svg>
                                                <?php echo htmlspecialchars($emptyState['action_label'] ?? 'Create new'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            // Initialize Lucide icons for this module section
            document.addEventListener('DOMContentLoaded', function () {
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            });
        </script>
        <?php
    }
}



