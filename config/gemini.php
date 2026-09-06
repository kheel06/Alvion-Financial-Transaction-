<?php
/**
 * Google Gemini AI Configuration
 * Used for AI-powered financial report generation and KPI explanations.
 * Get your free API key: https://aistudio.google.com/apikey
 */

// Your Gemini API key (required for AI features)
// Get a free key at https://aistudio.google.com/apikey
define('GEMINI_API_KEY', 'AIzaSyDPiKEmSKp480XMpJ4dVeu2U1N8juQQlkk');

// Enable when API key is set. Or set true/false manually.
define('GEMINI_ENABLED', !empty(trim(GEMINI_API_KEY ?? '')));

// Model: gemini-2.5-flash (stable, free tier) or gemini-2.0-flash
define('GEMINI_MODEL', 'gemini-2.5-flash');

// Timeout in seconds for API calls
define('GEMINI_TIMEOUT', 60);
