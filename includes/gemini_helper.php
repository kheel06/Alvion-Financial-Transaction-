<?php
/**
 * Google Gemini AI Helper for Hospital Financial System
 * Provides: automatic financial report narratives and KPI explanations.
 */

if (!defined('GEMINI_API_KEY')) {
    require_once __DIR__ . '/../config/gemini.php';
}

/**
 * Call Gemini generateContent API.
 *
 * @param string $prompt User prompt
 * @param string|null $system Optional system instruction
 * @return array{success: bool, response?: string, error?: string}
 */
function geminiGenerate($prompt, $system = null) {
    if (!defined('GEMINI_ENABLED') || !GEMINI_ENABLED) {
        return ['success' => false, 'error' => 'Gemini AI is disabled in configuration.'];
    }

    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($apiKey) || trim($apiKey) === '') {
        return ['success' => false, 'error' => 'Gemini API key is not set. Add your key in config/gemini.php. Get one at https://aistudio.google.com/apikey'];
    }

    $model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

    $fullPrompt = $prompt;
    if ($system !== null && $system !== '') {
        $fullPrompt = $system . "\n\n" . $prompt;
    }

    $payload = [
        'contents' => [['parts' => [['text' => $fullPrompt]]]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1024,
        ],
    ];

    $timeout = defined('GEMINI_TIMEOUT') ? (int) GEMINI_TIMEOUT : 60;

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
        return ['success' => false, 'error' => 'Gemini connection failed: ' . $err];
    }

    if ($httpCode !== 200) {
        $msg = 'Gemini API error (HTTP ' . $httpCode . ')';
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            if (isset($decoded['error']['message'])) {
                $msg .= ': ' . $decoded['error']['message'];
            } else {
                $msg .= ': ' . substr($body, 0, 200);
            }
        }
        return ['success' => false, 'error' => $msg];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => false, 'error' => 'Invalid Gemini response.'];
    }

    return ['success' => true, 'response' => trim($data['candidates'][0]['content']['parts'][0]['text'])];
}

/**
 * Generate a short financial report narrative from KPI data.
 *
 * @param array $kpiData
 * @param string $periodLabel
 * @return array{success: bool, response?: string, error?: string}
 */
function geminiGenerateFinancialReport($kpiData, $periodLabel = '') {
    $json = json_encode($kpiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $period = $periodLabel ?: date('F Y');

    $system = 'You are a concise hospital finance analyst. Write brief, professional summaries. Use PHP peso (₱) for amounts. Output only the report text, no headings like "Report" or markdown. Keep to 2–4 short paragraphs.';

    $prompt = "Write a short executive financial summary for a hospital for {$period} using these KPIs (amounts in PHP):\n\n{$json}\n\nSummarize performance, liquidity, and any notable points. Be factual and neutral.";

    return geminiGenerate($prompt, $system);
}

/**
 * Get a plain-language explanation of a single KPI.
 *
 * @param string $kpiName
 * @param string|float $value
 * @param string $context
 * @return array{success: bool, response?: string, error?: string}
 */
function geminiExplainKPI($kpiName, $value, $context = '') {
    $system = 'You are a hospital finance assistant. Explain this KPI in 1–2 sentences: what it means and why it matters. No bullet points or headings. Use simple language.';

    $prompt = "KPI: {$kpiName}. Value: {$value}. Context: " . ($context ?: 'dashboard') . ". Explain briefly what this metric means for the hospital.";

    return geminiGenerate($prompt, $system);
}
