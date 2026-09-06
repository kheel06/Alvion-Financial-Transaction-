<?php
/**
 * API: Generate AI financial report narrative via Gemini or Ollama.
 * POST or GET with KPI data; returns JSON { success, report?, error? }.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../config/ollama.php';
require_once __DIR__ . '/../../includes/gemini_helper.php';
require_once __DIR__ . '/../../includes/ollama_helper.php';

requireAuth();
checkRole(['admin', 'super admin']);

$aiEnabled = (defined('GEMINI_ENABLED') && GEMINI_ENABLED && !empty(trim(GEMINI_API_KEY ?? '')))
    || (defined('OLLAMA_ENABLED') && OLLAMA_ENABLED);
if (!$aiEnabled) {
    echo json_encode(['success' => false, 'error' => 'AI report generation is disabled. Set GEMINI_API_KEY in config/gemini.php or enable Ollama.']);
    exit;
}

$input = $_POST['kpi_data'] ?? $_GET['kpi_data'] ?? null;
$period = $_POST['period'] ?? $_GET['period'] ?? date('F Y');

if ($input !== null && !is_array($input)) {
    $decoded = json_decode($input, true);
    $kpiData = is_array($decoded) ? $decoded : [];
} else {
    $kpiData = is_array($input) ? $input : [];
}

if (empty($kpiData)) {
    echo json_encode(['success' => false, 'error' => 'Missing kpi_data. Send JSON object with metrics (e.g. total_revenue, total_ar, total_ap).']);
    exit;
}

if (defined('GEMINI_ENABLED') && GEMINI_ENABLED && !empty(trim(GEMINI_API_KEY ?? ''))) {
    $result = geminiGenerateFinancialReport($kpiData, $period);
} else {
    $result = ollamaGenerateFinancialReport($kpiData, $period);
}

if ($result['success']) {
    echo json_encode(['success' => true, 'report' => $result['response']]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Unknown error']);
}
