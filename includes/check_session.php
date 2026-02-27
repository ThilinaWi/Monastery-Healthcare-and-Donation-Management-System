<?php
/**
 * Session Status Check Endpoint
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

try {
    $response = [
        'valid' => isLoggedIn(),
        'remaining_time' => 0,
        'user_role' => null
    ];
    
    if (isLoggedIn()) {
        $response['remaining_time'] = getSessionRemainingTime();
        $response['user_role'] = getCurrentUserRole();
        
        // Validate session in database
        global $sessionManager;
        if (!$sessionManager->validateSession()) {
            $response['valid'] = false;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Session check error: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'error' => 'Session check failed'
    ]);
}
?>