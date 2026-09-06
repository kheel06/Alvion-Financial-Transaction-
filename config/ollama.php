<?php
/**
 * Ollama Configuration – AI report generation & KPI explanations
 *
 * To run on CyberPanel:
 * - Same server (VPS): install Ollama on the server, use http://127.0.0.1:11434, set OLLAMA_ENABLED true.
 * - Separate server: run Ollama on another VPS, set OLLAMA_BASE_URL to that server (e.g. http://ip:11434), set OLLAMA_ENABLED true.
 * See OLLAMA-CYBERPANEL.md for step-by-step.
 */

// Ollama API URL. Use http://127.0.0.1:11434 if Ollama is on same server; use http://your-ollama-server-ip:11434 if on another VPS.
define('OLLAMA_BASE_URL', '');

// Model name (e.g. llama3, llama3.2). Run: ollama list
define('OLLAMA_MODEL', '');

// Set true when Ollama is running (same server or remote). Set false to hide AI features and avoid connection errors.
define('OLLAMA_ENABLED', false);

// Timeout in seconds for API calls (generation can take 10–30s)
define('OLLAMA_TIMEOUT', 60);
