<?php
/**
 * Session Extension Endpoint
 * Monastery Healthcare and Donation Management System
 */

// Define constant and start session
define('INCLUDED', true);
session_start();

// Include required files
require_once 'config.php';
require_once '../config/database.php';
require_once 'session_check.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    // Update session activity
    global $sessionManager;
    $sessionManager->updateActivity();
    
    echo json_encode([
        'success' => true,
        'message' => 'Session extended',
        'remaining_time' => getSessionRemainingTime()
    ]);
    
} catch (Exception $e) {
    error_log("Session extension error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to extend session']);
}
?>