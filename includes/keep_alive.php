<?php
require_once __DIR__ . '/../config/config.php';

// The inclusion of config.php automatically updates $_SESSION['last_activity']
// because we will add that logic to config.php

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Session extended']);
?>
