<?php
/**
 * Main Configuration File
 * Monastery Healthcare and Donation Management System
 * 
 * This file contains all system configurations and includes database setup.
 * Make sure to update these settings according to your environment.
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// System Configuration
define('SITE_NAME', 'Monastery Healthcare & Donation Management System');
define('SITE_URL', 'http://localhost/monastery-healthcare-system'); // Update with your URL
define('ADMIN_EMAIL', 'admin@monastery.com');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Pagination Configuration
define('RECORDS_PER_PAGE', 10);

// Date and Time Configuration
define('DEFAULT_TIMEZONE', 'Asia/Colombo'); // Update with your timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Error Reporting (Set to 0 in production)
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    // Development environment
    define('ENVIRONMENT', 'development');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production environment
    define('ENVIRONMENT', 'production');
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Application Paths
define('ROOT_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Common utility functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

function format_currency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

function format_date($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function show_error($message) {
    $_SESSION['error'] = $message;
}

function show_success($message) {
    $_SESSION['success'] = $message;
}

function get_flash_message($type) {
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return null;
}

// Initialize database connection (using singleton pattern)
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log("Failed to initialize database: " . $e->getMessage());
    if (ENVIRONMENT === 'development') {
        die("System initialization failed: " . $e->getMessage());
    } else {
        die("System temporarily unavailable. Please try again later.");
    }
}
?>