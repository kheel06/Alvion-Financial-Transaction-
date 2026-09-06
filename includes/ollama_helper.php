<?php
/**
 * Ollama (Llama 3) Helper for Hospital Financial System
 * Provides: automatic financial report narratives and KPI explanations.
 */

if (!defined('OLLAMA_BASE_URL')) {
    require_once __DIR__ . '/../config/ollama.php';
}

/**
 * Call Ollama /api/generate (non-streaming).
 *
 * @param string $prompt User prompt
 * @param string|null $system Optional system prompt
 * @return array{success: bool, response?: string, error?: string}
 */
function ollamaGenerate($prompt, $system = null) {
    if (!defined('OLLAMA_ENABLED') || !OLLAMA_ENABLED) {
        return ['success' => false, 'error' => 'Ollama AI is disabled in configuration.'];
    }

    $url = rtrim(OLLAMA_BASE_URL, '/') . '/api/generate';
    $payload = [
        'model' => defined('OLLAMA_MODEL') ? OLLAMA_MODEL : 'llama3',
        'prompt' => $prompt,
        'stream' => false,
    ];
    if ($system !== null && $system !== '') {
        $payload['system'] = $system;
    }

    $timeout = defined('OLLAMA_TIMEOUT') ? (int) OLLAMA_TIMEOUT : 60;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => 'Ollama connection failed: ' . $err . '. Is Ollama running? Run: ollama run llama3'];
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Ollama returned HTTP ' . $httpCode . '. ' . (is_string($body) ? substr($body, 0, 200) : '')];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['response'])) {
        return ['success' => false, 'error' => 'Invalid Ollama response.'];
    }

    return ['success' => true, 'response' => trim($data['response'])];
}

/**
 * Generate a short financial report narrative from KPI data.
 *
 * @param array $kpiData e.g. ['total_revenue' => 1234567, 'total_ar' => 50000, ...]
 * @param string $periodLabel e.g. "January 2025"
 * @return array{success: bool, response?: string, error?: string}
 */
function ollamaGenerateFinancialReport($kpiData, $periodLabel = '') {
    $json = json_encode($kpiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $period = $periodLabel ?: date('F Y');

    $system = 'You are a concise hospital finance analyst. Write brief, professional summaries. Use PHP peso (₱) for amounts. Output only the report text, no headings like "Report" or markdown. Keep to 2–4 short paragraphs.';

    $prompt = "Write a short executive financial summary for a hospital for {$period} using these KPIs (amounts in PHP):\n\n{$json}\n\nSummarize performance, liquidity, and any notable points. Be factual and neutral.";

    return ollamaGenerate($prompt, $system);
}

/**
 * Get a plain-language explanation of a single KPI for the dashboard.
 *
 * @param string $kpiName e.g. "Total Revenue", "Accounts Receivable"
 * @param string|float $value Display value e.g. "1,234,567.00" or 1234567
 * @param string $context Optional e.g. "Current month", "Outstanding"
 * @return array{success: bool, response?: string, error?: string}
 */
function ollamaExplainKPI($kpiName, $value, $context = '') {
    $system = 'You are a hospital finance assistant. Explain this KPI in 1–2 sentences: what it means and why it matters. No bullet points or headings. Use simple language.';

    $prompt = "KPI: {$kpiName}. Value: {$value}. Context: " . ($context ?: 'dashboard') . ". Explain briefly what this metric means for the hospital.";

    return ollamaGenerate($prompt, $system);
}
