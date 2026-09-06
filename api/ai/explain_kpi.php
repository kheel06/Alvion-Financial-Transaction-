<?php
/**
 * API: Get AI explanation for a single KPI via Gemini or Ollama.
 * GET params: kpi_name, value, context (optional). Returns JSON { success, explanation?, error? }.
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
    echo json_encode(['success' => false, 'error' => 'AI explanations are disabled. Set GEMINI_API_KEY in config/gemini.php or enable Ollama.']);
    exit;
}

$kpiName = trim($_GET['kpi_name'] ?? $_POST['kpi_name'] ?? '');
$value   = $_GET['value'] ?? $_POST['value'] ?? '';
$context = trim($_GET['context'] ?? $_POST['context'] ?? '');

if ($kpiName === '' || $value === '') {
    echo json_encode(['success' => false, 'error' => 'Missing kpi_name or value.']);
    exit;
}

if (defined('GEMINI_ENABLED') && GEMINI_ENABLED && !empty(trim(GEMINI_API_KEY ?? ''))) {
    $result = geminiExplainKPI($kpiName, $value, $context);
} else {
    $result = ollamaExplainKPI($kpiName, $value, $context);
}

if ($result['success']) {
    echo json_encode(['success' => true, 'explanation' => $result['response']]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Unknown error']);
}
