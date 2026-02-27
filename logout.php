<?php
/**
 * Logout Page
 * Monastery Healthcare and Donation Management System
 */

// Define constant and start session
define('INCLUDED', true);
session_start();

// Include required files
require_once 'includes/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$reason = $_GET['reason'] ?? 'logout';

// Store user info for goodbye message
$userInfo = null;
if (isset($_SESSION['user_data'])) {
    $userInfo = [
        'name' => $_SESSION['user_data']['full_name'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

// Perform logout
$auth->logout();

// Set appropriate message based on logout reason
$message = '';
$messageType = 'info';

switch ($reason) {
    case 'timeout':
        $message = 'Your session has expired due to inactivity. Please login again.';
        $messageType = 'warning';
        break;
    case 'security':
        $message = 'You have been logged out for security reasons. Please login again.';
        $messageType = 'danger';
        break;
    case 'admin':
        $message = 'You have been logged out by an administrator.';
        $messageType = 'warning';
        break;
    case 'maintenance':
        $message = 'System is under maintenance. Please try again later.';
        $messageType = 'info';
        break;
    default:
        $message = 'You have been successfully logged out. Thank you for using our system.';
        $messageType = 'success';
        break;
}

$page_title = 'Logged Out - Monastery System';
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            text-align: center;
        }
        .logout-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 40px;
        }
        .logout-body {
            padding: 40px;
        }
        .logout-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .logout-animation {
            animation: fadeInUp 0.8s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .btn-group-custom .btn {
            border-radius: 25px;
            margin: 0 10px;
            padding: 12px 25px;
            font-weight: 500;
        }
        .stats-section {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="logout-card logout-animation">
                    <!-- Header -->
                    <div class="logout-header">
                        <div class="logout-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <h2 class="mb-3">
                            <?php if ($userInfo): ?>
                                Goodbye, <?php echo htmlspecialchars($userInfo['name']); ?>!
                            <?php else: ?>
                                Successfully Logged Out
                            <?php endif; ?>
                        </h2>
                        <p class="lead mb-0">Thank you for using our monastery management system</p>
                    </div>
                    
                    <!-- Body -->
                    <div class="logout-body">
                        <!-- Logout Message -->
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php
                            $iconMap = [
                                'success' => 'check-circle',
                                'info' => 'info-circle',
                                'warning' => 'exclamation-triangle',
                                'danger' => 'exclamation-circle'
                            ];
                            $icon = $iconMap[$messageType] ?? 'info-circle';
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                        
                        <?php if ($userInfo): ?>
                            <!-- User Session Summary -->
                            <div class="stats-section">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Session Summary
                                </h5>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-muted">Role</div>
                                        <strong><?php echo ucfirst(htmlspecialchars($userInfo['role'])); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted">Session Date</div>
                                        <strong><?php echo date('M d, Y'); ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quick Access Buttons -->
                        <div class="mb-4">
                            <h5 class="mb-3">What would you like to do next?</h5>
                            <div class="btn-group-custom d-flex flex-wrap justify-content-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Login Again
                                </a>
                                <a href="index.php" class="btn btn-outline-primary">
                                    <i class="fas fa-home me-2"></i>
                                    Homepage
                                </a>
                                <a href="register.php" class="btn btn-outline-success">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Register
                                </a>
                            </div>
                        </div>
                        
                        <!-- Quick Login Options -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Quick Login by Role</h6>
                                <div class="row">
                                    <div class="col-6 col-lg-3 mb-2">
                                        <a href="login.php?role=admin" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="fas fa-user-shield me-1"></i>
                                            Admin
                                        </a>
                                    </div>
                                    <div class="col-6 col-lg-3 mb-2">
                                        <a href="login.php?role=monk" class="btn btn-outline-primary btn-sm w-100">
                                            <i class="fas fa-user me-1"></i>
                                            Monk
                                        </a>
                                    </div>
                                    <div class="col-6 col-lg-3 mb-2">
                                        <a href="login.php?role=doctor" class="btn btn-outline-success btn-sm w-100">
                                            <i class="fas fa-user-md me-1"></i>
                                            Doctor
                                        </a>
                                    </div>
                                    <div class="col-6 col-lg-3 mb-2">
                                        <a href="login.php?role=donator" class="btn btn-outline-warning btn-sm w-100">
                                            <i class="fas fa-heart me-1"></i>
                                            Donator
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Tips -->
                        <div class="alert alert-light border mt-4" role="alert">
                            <h6 class="alert-heading">
                                <i class="fas fa-shield-alt text-primary me-2"></i>
                                Security Tips
                            </h6>
                            <small class="text-muted">
                                • Always logout when using shared computers<br>
                                • Don't share your account credentials with others<br>
                                • Contact administrator if you notice unusual activity
                            </small>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="text-center mt-4">
                            <p class="text-muted mb-2">
                                <i class="fas fa-question-circle me-2"></i>
                                Need help? Contact our support team
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-envelope me-2"></i>
                                <a href="mailto:<?php echo ADMIN_EMAIL ?? 'admin@monastery.com'; ?>" class="text-decoration-none">
                                    <?php echo ADMIN_EMAIL ?? 'admin@monastery.com'; ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Footer Info -->
                <div class="text-center mt-4">
                    <p class="text-white mb-0">
                        <i class="fas fa-lotus me-2"></i>
                        <?php echo SITE_NAME ?? 'Monastery Healthcare & Donation Management System'; ?>
                    </p>
                    <small class="text-white-50">
                        &copy; <?php echo date('Y'); ?> All rights reserved
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Auto-hide alerts after 8 seconds
            setTimeout(function() {
                $('.alert-dismissible').fadeOut('slow');
            }, 8000);
            
            // Add click tracking for analytics
            $('.btn').on('click', function() {
                const action = $(this).text().trim();
                console.log('User clicked:', action);
            });
            
            // Auto-redirect to homepage after 30 seconds (optional)
            // setTimeout(function() {
            //     if (confirm('Would you like to return to the homepage?')) {
            //         window.location.href = 'index.php';
            //     }
            // }, 30000);
        });
    </script>
</body>
</html>