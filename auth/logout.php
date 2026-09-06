<?php
// Use absolute path for config
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Log the logout action (optional)
    $user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . (isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '') : 'Unknown';
    error_log("User logout: " . $user_name . " (ID: " . $_SESSION['user_id'] . ")");
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Start a new session to store logout message (optional)
session_start();
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $_SESSION['error'] = "You have been logged out due to inactivity.";
} else {
    $_SESSION['success'] = "You have been successfully logged out.";
}

// Redirect to login page
header("Location: " . BASE_URL . "/auth/employee-login.php");
exit();
?>