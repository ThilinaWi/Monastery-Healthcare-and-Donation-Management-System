<?php
/**
 * Login Page
 * Monastery Healthcare and Donation Management System
 */

// Define constant and start session
define('INCLUDED', true);
session_start();

// Include required files
require_once 'includes/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/session_check.php';

// Redirect if already logged in
redirectIfLoggedIn();

$page_title = 'Login - Monastery System';
$auth = new Auth();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    
    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields';
    } else {
        $result = $auth->login($username, $password, $role);
        
        if ($result['success']) {
            // Check for redirect parameter
            $redirect = $_GET['redirect'] ?? $result['redirect'];
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Get role from URL parameter
$selected_role = $_GET['role'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        .login-left {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        .login-right {
            padding: 60px 40px;
        }
        .role-selector .form-check {
            margin-bottom: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        .role-selector .form-check:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .role-selector .form-check-input:checked + .form-check-label {
            color: #667eea;
            font-weight: 600;
        }
        .role-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .login-left {
                padding: 40px 20px;
            }
            .login-right {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="login-card">
                        <div class="row g-0">
                            <!-- Left Side - Branding -->
                            <div class="col-lg-5 login-left">
                                <div>
                                    <div class="role-icon">
                                        <i class="fas fa-lotus"></i>
                                    </div>
                                    <h2 class="mb-4">Welcome Back</h2>
                                    <p class="lead mb-4">
                                        Access your dashboard to manage healthcare services and donations 
                                        in our monastery community.
                                    </p>
                                    <div class="text-center">
                                        <p class="mb-2"><strong>System Features:</strong></p>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check me-2"></i>Healthcare Management</li>
                                            <li><i class="fas fa-check me-2"></i>Donation Tracking</li>
                                            <li><i class="fas fa-check me-2"></i>Financial Reports</li>
                                            <li><i class="fas fa-check me-2"></i>Appointment Booking</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Side - Login Form -->
                            <div class="col-lg-7 login-right">
                                <div class="text-center mb-4">
                                    <h3 class="fw-bold text-dark">Login to Your Account</h3>
                                    <p class="text-muted">Choose your role and enter your credentials</p>
                                </div>
                                
                                <!-- Flash Messages -->
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php echo htmlspecialchars($error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($_GET['message'])): ?>
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <?php echo htmlspecialchars($_GET['message']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="" id="loginForm">
                                    <!-- Role Selection -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Select Your Role</label>
                                        <div class="role-selector">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="role" value="admin" 
                                                       id="role_admin" <?php echo ($selected_role === 'admin') ? 'checked' : ''; ?>>
                                                <label class="form-check-label d-flex align-items-center" for="role_admin">
                                                    <i class="fas fa-user-shield text-danger me-3" style="font-size: 1.5rem;"></i>
                                                    <div>
                                                        <strong>Administrator</strong>
                                                        <small class="d-block text-muted">Full system management</small>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="role" value="monk" 
                                                       id="role_monk" <?php echo ($selected_role === 'monk') ? 'checked' : ''; ?>>
                                                <label class="form-check-label d-flex align-items-center" for="role_monk">
                                                    <i class="fas fa-user text-primary me-3" style="font-size: 1.5rem;"></i>
                                                    <div>
                                                        <strong>Monk</strong>
                                                        <small class="d-block text-muted">Personal health records</small>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="role" value="doctor" 
                                                       id="role_doctor" <?php echo ($selected_role === 'doctor') ? 'checked' : ''; ?>>
                                                <label class="form-check-label d-flex align-items-center" for="role_doctor">
                                                    <i class="fas fa-user-md text-success me-3" style="font-size: 1.5rem;"></i>
                                                    <div>
                                                        <strong>Doctor</strong>
                                                        <small class="d-block text-muted">Medical records management</small>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="role" value="donator" 
                                                       id="role_donator" <?php echo ($selected_role === 'donator') ? 'checked' : ''; ?>>
                                                <label class="form-check-label d-flex align-items-center" for="role_donator">
                                                    <i class="fas fa-heart text-warning me-3" style="font-size: 1.5rem;"></i>
                                                    <div>
                                                        <strong>Donator</strong>
                                                        <small class="d-block text-muted">Donation management</small>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Username/Email -->
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username or Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   placeholder="Enter your username or email" required
                                                   value="<?php echo htmlspecialchars($username ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- Password -->
                                    <div class="mb-4">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Enter your password" required>
                                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Remember Me & Forgot Password -->
                                    <div class="row mb-4">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="remember">
                                                <label class="form-check-label" for="remember">
                                                    Remember me
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <a href="forgot-password.php" class="text-decoration-none">
                                                Forgot Password?
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Submit Button -->
                                    <div class="d-grid mb-3">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-sign-in-alt me-2"></i>
                                            Login to Dashboard
                                        </button>
                                    </div>
                                    
                                    <!-- Register Link -->
                                    <div class="text-center">
                                        <p class="mb-0">
                                            New donator? 
                                            <a href="register.php" class="text-decoration-none fw-bold">
                                                Register here to start donating
                                            </a>
                                        </p>
                                    </div>
                                </form>
                                
                                <!-- Back to Home -->
                                <div class="text-center mt-4">
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-home me-2"></i>
                                        Back to Homepage
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const icon = $(this).find('i');
                
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Form validation
            $('#loginForm').on('submit', function() {
                const role = $('input[name="role"]:checked').val();
                const username = $('#username').val().trim();
                const password = $('#password').val();
                
                if (!role) {
                    alert('Please select your role');
                    return false;
                }
                
                if (!username || !password) {
                    alert('Please fill in all fields');
                    return false;
                }
                
                // Show loading state
                $(this).find('button[type="submit"]').addClass('disabled').html(
                    '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...'
                );
                
                return true;
            });
            
            // Auto-focus based on selected role
            $('input[name="role"]').change(function() {
                if ($(this).is(':checked')) {
                    $('#username').focus();
                }
            });
            
            // Focus username if role is pre-selected
            if ($('input[name="role"]:checked').length > 0) {
                $('#username').focus();
            }
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>