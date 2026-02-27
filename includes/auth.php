<?php
/**
 * Authentication Functions
 * Monastery Healthcare and Donation Management System
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

/**
 * User Authentication Class
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user with username/email and password
     */
    public function login($username, $password, $role) {
        try {
            // Determine table based on role
            $table = $this->getRoleTable($role);
            if (!$table) {
                return ['success' => false, 'message' => 'Invalid user role'];
            }
            
            // Find user by username or email
            $sql = "SELECT * FROM {$table} WHERE (username = ? OR email = ?) AND is_active = 1";
            $user = $this->db->fetchOne($sql, [$username, $username]);
            
            if (!$user) {
                $this->logLoginAttempt($username, $role, false, 'User not found');
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->logLoginAttempt($username, $role, false, 'Invalid password');
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Create session
            $this->createSession($user, $role);
            
            // Log successful login
            $this->logLoginAttempt($username, $role, true, 'Login successful');
            
            // Update last login
            $this->updateLastLogin($table, $user[$this->getRoleIdField($role)]);
            
            return [
                'success' => true, 
                'message' => 'Login successful',
                'user' => $user,
                'redirect' => $this->getRedirectUrl($role)
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Register new donator (public registration)
     */
    public function registerDonator($data) {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'full_name', 'phone'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '{$field}' is required"];
                }
            }
            
            // Check if username or email already exists
            if ($this->userExists($data['username'], $data['email'], 'donator')) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Validate password strength
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }
            
            // Hash password
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Set default values
            $data['is_active'] = 1;
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Insert user
            $userId = $this->db->insert('donators', $data);
            
            if ($userId) {
                $this->logSystemAction('registration', 'donators', $userId, null, $data);
                return ['success' => true, 'message' => 'Registration successful! You can now login.'];
            } else {
                return ['success' => false, 'message' => 'Registration failed. Please try again.'];
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['session_id'])) {
            // Remove session from database
            $this->db->delete('user_sessions', 'session_id = ?', [$_SESSION['session_id']]);
        }
        
        // Destroy PHP session
        session_unset();
        session_destroy();
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $table = $this->getRoleTable($_SESSION['role']);
        $idField = $this->getRoleIdField($_SESSION['role']);
        
        return $this->db->fetchOne("SELECT * FROM {$table} WHERE {$idField} = ?", [$_SESSION['user_id']]);
    }
    
    /**
     * Update user password
     */
    public function updatePassword($userId, $currentPassword, $newPassword, $role) {
        try {
            $table = $this->getRoleTable($role);
            $idField = $this->getRoleIdField($role);
            
            // Get current user
            $user = $this->db->fetchOne("SELECT password FROM {$table} WHERE {$idField} = ?", [$userId]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Validate new password
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $this->db->update($table, ['password' => $hashedPassword], "{$idField} = ?", [$userId]);
            
            return ['success' => true, 'message' => 'Password updated successfully'];
            
        } catch (Exception $e) {
            error_log("Password update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password update failed'];
        }
    }
    
    /**
     * Check if username or email exists
     */
    private function userExists($username, $email, $role) {
        $table = $this->getRoleTable($role);
        $user = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM {$table} WHERE username = ? OR email = ?",
            [$username, $email]
        );
        return $user['count'] > 0;
    }
    
    /**
     * Create user session
     */
    private function createSession($user, $role) {
        $sessionId = bin2hex(random_bytes(32));
        
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['user_id'] = $user[$this->getRoleIdField($role)];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;
        $_SESSION['user_data'] = $user;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Store session in database
        $this->db->insert('user_sessions', [
            'session_id' => $sessionId,
            'user_type' => $role,
            'user_id' => $user[$this->getRoleIdField($role)],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'login_time' => date('Y-m-d H:i:s'),
            'last_activity' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin($table, $userId) {
        $idField = str_replace('s', '', $table) . '_id'; // Remove 's' and add '_id'
        $this->db->update($table, ['updated_at' => date('Y-m-d H:i:s')], "{$idField} = ?", [$userId]);
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
     * Get redirect URL after login
     */
    private function getRedirectUrl($role) {
        $redirects = [
            'admin' => SITE_URL . '/admin/dashboard.php',
            'monk' => SITE_URL . '/monk/dashboard.php',
            'doctor' => SITE_URL . '/doctor/dashboard.php',
            'donator' => SITE_URL . '/donator/dashboard.php'
        ];
        return $redirects[$role] ?? SITE_URL . '/';
    }
    
    /**
     * Log login attempts
     */
    private function logLoginAttempt($username, $role, $success, $message) {
        try {
            $this->db->insert('system_logs', [
                'user_type' => $role,
                'user_id' => 0, // Unknown user ID for failed attempts
                'action' => $success ? 'login_success' : 'login_failed',
                'table_affected' => 'user_sessions',
                'new_values' => json_encode([
                    'username' => $username,
                    'role' => $role,
                    'message' => $message,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Log system actions for audit trail
     */
    private function logSystemAction($action, $table, $recordId, $oldValues = null, $newValues = null) {
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $userType = $_SESSION['role'] ?? 'system';
            
            $this->db->insert('system_logs', [
                'user_type' => $userType,
                'user_id' => $userId,
                'action' => $action,
                'table_affected' => $table,
                'record_id' => $recordId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log system action: " . $e->getMessage());
        }
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        $expireTime = date('Y-m-d H:i:s', time() - SESSION_TIMEOUT);
        $this->db->delete('user_sessions', 'last_activity < ?', [$expireTime]);
    }
    
    /**
     * Update session activity
     */
    public function updateSessionActivity() {
        if (isset($_SESSION['session_id'])) {
            $_SESSION['last_activity'] = time();
            $this->db->update('user_sessions', 
                ['last_activity' => date('Y-m-d H:i:s')], 
                'session_id = ?', 
                [$_SESSION['session_id']]
            );
        }
    }
}

// Global authentication functions
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . SITE_URL . '/login.php?error=access_denied');
        exit;
    }
}

function redirectIfLoggedIn() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        $auth = new Auth();
        $redirectUrl = '';
        
        switch ($_SESSION['role']) {
            case 'admin':
                $redirectUrl = SITE_URL . '/admin/dashboard.php';
                break;
            case 'monk':
                $redirectUrl = SITE_URL . '/monk/dashboard.php';
                break;
            case 'doctor':
                $redirectUrl = SITE_URL . '/doctor/dashboard.php';
                break;
            case 'donator':
                $redirectUrl = SITE_URL . '/donator/dashboard.php';
                break;
        }
        
        if ($redirectUrl) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

function setFlashMessage($type, $message) {
    $_SESSION[$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return null;
}
?>