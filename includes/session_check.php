<?php
/**
 * Session Check and Management
 * Monastery Healthcare and Donation Management System
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

/**
 * Session Management Class
 */
class SessionManager {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Initialize session management
     */
    public function init() {
        // Start session with secure settings
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => SESSION_TIMEOUT,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict'
            ]);
        }
        
        // Check session validity
        $this->validateSession();
        
        // Clean expired sessions periodically
        if (rand(1, 100) <= 5) { // 5% chance
            $this->cleanExpiredSessions();
        }
    }
    
    /**
     * Validate current session
     */
    public function validateSession() {
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return true; // Not logged in, allow to continue
        }
        
        try {
            // Check if session exists in database
            $session = $this->db->fetchOne(
                "SELECT * FROM user_sessions WHERE session_id = ? AND is_active = 1",
                [$_SESSION['session_id']]
            );
            
            if (!$session) {
                $this->destroySession('Invalid session');
                return false;
            }
            
            // Check session timeout
            $lastActivity = strtotime($session['last_activity']);
            if (time() - $lastActivity > SESSION_TIMEOUT) {
                $this->destroySession('Session expired');
                return false;
            }
            
            // Check if user is still active
            $table = $this->getRoleTable($_SESSION['role']);
            $idField = $this->getRoleIdField($_SESSION['role']);
            
            $user = $this->db->fetchOne(
                "SELECT is_active FROM {$table} WHERE {$idField} = ?",
                [$_SESSION['user_id']]
            );
            
            if (!$user || !$user['is_active']) {
                $this->destroySession('User account deactivated');
                return false;
            }
            
            // Update session activity
            $this->updateActivity();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            $this->destroySession('Session validation failed');
            return false;
        }
    }
    
    /**
     * Check if user has required role for current page
     */
    public function checkPageAccess($requiredRole = null) {
        // If no role required, allow access
        if (!$requiredRole) {
            return true;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['role'])) {
            $this->redirectToLogin('Please login to access this page');
            return false;
        }
        
        // Check if user has required role
        if ($_SESSION['role'] !== $requiredRole) {
            $this->redirectToLogin('Access denied for your role');
            return false;
        }
        
        return true;
    }
    
    /**
     * Update session activity
     */
    public function updateActivity() {
        if (isset($_SESSION['session_id'])) {
            $_SESSION['last_activity'] = time();
            
            try {
                $this->db->update('user_sessions', 
                    ['last_activity' => date('Y-m-d H:i:s')], 
                    'session_id = ?', 
                    [$_SESSION['session_id']]
                );
            } catch (Exception $e) {
                error_log("Failed to update session activity: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Destroy current session
     */
    public function destroySession($reason = 'Session ended') {
        if (isset($_SESSION['session_id'])) {
            try {
                // Mark session as inactive in database
                $this->db->update('user_sessions', 
                    ['is_active' => 0], 
                    'session_id = ?', 
                    [$_SESSION['session_id']]
                );
                
                // Log session destruction
                $this->logSessionEvent('session_destroyed', $reason);
            } catch (Exception $e) {
                error_log("Failed to update session in database: " . $e->getMessage());
            }
        }
        
        // Clear PHP session
        session_unset();
        session_destroy();
        
        // Set session ended message
        session_start();
        $_SESSION['info'] = $reason;
    }
    
    /**
     * Clean expired sessions from database
     */
    public function cleanExpiredSessions() {
        try {
            $expireTime = date('Y-m-d H:i:s', time() - SESSION_TIMEOUT);
            $this->db->update('user_sessions', 
                ['is_active' => 0], 
                'last_activity < ? AND is_active = 1', 
                [$expireTime]
            );
        } catch (Exception $e) {
            error_log("Failed to clean expired sessions: " . $e->getMessage());
        }
    }
    
    /**
     * Get active sessions for a user
     */
    public function getUserSessions($userId, $userType) {
        return $this->db->fetchAll(
            "SELECT * FROM user_sessions WHERE user_id = ? AND user_type = ? AND is_active = 1 ORDER BY last_activity DESC",
            [$userId, $userType]
        );
    }
    
    /**
     * Terminate specific session
     */
    public function terminateSession($sessionId) {
        try {
            $this->db->update('user_sessions', 
                ['is_active' => 0], 
                'session_id = ?', 
                [$sessionId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Failed to terminate session: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Terminate all sessions for a user except current
     */
    public function terminateOtherSessions($userId, $userType, $currentSessionId) {
        try {
            $this->db->update('user_sessions', 
                ['is_active' => 0], 
                'user_id = ? AND user_type = ? AND session_id != ? AND is_active = 1', 
                [$userId, $userType, $currentSessionId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Failed to terminate other sessions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for concurrent sessions
     */
    public function checkConcurrentSessions($userId, $userType) {
        $sessions = $this->getUserSessions($userId, $userType);
        return count($sessions);
    }
    
    /**
     * Get session information
     */
    public function getSessionInfo() {
        if (!isset($_SESSION['session_id'])) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM user_sessions WHERE session_id = ?",
            [$_SESSION['session_id']]
        );
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin($message = '') {
        if ($message) {
            session_start();
            $_SESSION['error'] = $message;
        }
        
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        $redirectUrl = SITE_URL . '/login.php';
        
        if ($currentUrl && $currentUrl !== '/login.php') {
            $redirectUrl .= '?redirect=' . urlencode($currentUrl);
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    /**
     * Get table name based on role
     */
    private function getRoleTable($role) {
        $tables = [
            'admin' => 'admins',
            'monk' => 'monks',
            'doctor' => 'doctors',
            'donator' => 'donators'
        ];
        return $tables[$role] ?? null;
    }
    
    /**
     * Get ID field name based on role
     */
    private function getRoleIdField($role) {
        return $role . '_id';
    }
    
    /**
     * Log session events
     */
    private function logSessionEvent($action, $message = '') {
        try {
            $this->db->insert('system_logs', [
                'user_type' => $_SESSION['role'] ?? 'unknown',
                'user_id' => $_SESSION['user_id'] ?? 0,
                'action' => $action,
                'table_affected' => 'user_sessions',
                'new_values' => json_encode([
                    'session_id' => $_SESSION['session_id'] ?? '',
                    'message' => $message,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log session event: " . $e->getMessage());
        }
    }
}

/**
 * Global session functions
 */

// Initialize session manager
$sessionManager = new SessionManager();
$sessionManager->init();

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    global $sessionManager;
    if (!isLoggedIn()) {
        $sessionManager->checkPageAccess('any');
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    global $sessionManager;
    $sessionManager->checkPageAccess($role);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    return $_SESSION['user_data'] ?? null;
}

/**
 * Check if current user has role
 */
function hasRole($role) {
    return getCurrentUserRole() === $role;
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Get session remaining time in seconds
 */
function getSessionRemainingTime() {
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Extend session (update activity)
 */
function extendSession() {
    global $sessionManager;
    $sessionManager->updateActivity();
}

/**
 * Auto logout warning (JavaScript helper)
 */
function getAutoLogoutScript($warningMinutes = 5) {
    $warningTime = $warningMinutes * 60 * 1000; // Convert to milliseconds
    $sessionTime = SESSION_TIMEOUT * 1000;
    
    return "
    <script>
    let sessionWarningTimer;
    let sessionLogoutTimer;
    let warningShown = false;
    
    function resetSessionTimers() {
        clearTimeout(sessionWarningTimer);
        clearTimeout(sessionLogoutTimer);
        warningShown = false;
        
        // Set warning timer
        sessionWarningTimer = setTimeout(function() {
            if (!warningShown && confirm('Your session will expire in {$warningMinutes} minutes. Click OK to extend your session.')) {
                extendSession();
            } else {
                warningShown = true;
            }
        }, " . ($sessionTime - $warningTime) . ");
        
        // Set logout timer
        sessionLogoutTimer = setTimeout(function() {
            alert('Your session has expired. You will be logged out.');
            window.location.href = '" . SITE_URL . "/logout.php?reason=timeout';
        }, {$sessionTime});
    }
    
    function extendSession() {
        fetch('" . SITE_URL . "/includes/extend_session.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        }).then(function() {
            resetSessionTimers();
        });
    }
    
    // Initialize timers
    resetSessionTimers();
    
    // Reset timers on user activity
    document.addEventListener('click', resetSessionTimers);
    document.addEventListener('keypress', resetSessionTimers);
    </script>";
}
?>