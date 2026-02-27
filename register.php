<?php
/**
 * Registration Page - Donator Registration
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

$page_title = 'Register - Monastery System';
$auth = new Auth();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => sanitize_input($_POST['username'] ?? ''),
        'email' => sanitize_input($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => sanitize_input($_POST['full_name'] ?? ''),
        'phone' => sanitize_input($_POST['phone'] ?? ''),
        'address' => sanitize_input($_POST['address'] ?? ''),
        'organization' => sanitize_input($_POST['organization'] ?? ''),
        'preferred_contact' => sanitize_input($_POST['preferred_contact'] ?? 'email'),
        'is_anonymous' => isset($_POST['is_anonymous']) ? 1 : 0
    ];
    
    // Validation
    $errors = [];
    
    if (empty($data['username'])) {
        $errors[] = 'Username is required';
    } elseif (strlen($data['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!validate_email($data['email'])) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($data['full_name'])) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($data['phone'])) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^[\+]?[0-9\s\-\(\)]{7,15}$/', $data['phone'])) {
        $errors[] = 'Please enter a valid phone number';
    }
    
    if (!isset($_POST['terms'])) {
        $errors[] = 'You must accept the terms and conditions';
    }
    
    if (empty($errors)) {
        // Remove confirm_password from data
        unset($data['confirm_password']);
        
        $result = $auth->registerDonator($data);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
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
            padding: 40px 0;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 800px;
        }
        .register-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            color: white;
            padding: 40px;
            text-align: center;
        }
        .register-body {
            padding: 40px;
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .benefits-list {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="register-card">
                    <!-- Header -->
                    <div class="register-header">
                        <i class="fas fa-heart" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <h2 class="mb-3">Join Our Community</h2>
                        <p class="lead mb-0">Register as a donator to support our monastery</p>
                    </div>
                    
                    <!-- Registration Form -->
                    <div class="register-body">
                        <!-- Flash Messages -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center">
                                <a href="login.php?role=donator" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Login Now
                                </a>
                            </div>
                        <?php else: ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Benefits Section -->
                            <div class="benefits-list">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-gift me-2"></i>
                                    Benefits of Registering
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Track your donation history</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Receive donation receipts</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>View impact reports</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Get updates on projects</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Anonymous donation option</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Financial transparency</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="" id="registerForm" novalidate>
                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-6">
                                        <h5 class="mb-3 text-primary">
                                            <i class="fas fa-user me-2"></i>
                                            Personal Information
                                        </h5>
                                        
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                   placeholder="Full Name" required 
                                                   value="<?php echo htmlspecialchars($data['full_name'] ?? ''); ?>">
                                            <label for="full_name">Full Name *</label>
                                        </div>
                                        
                                        <div class="form-floating">
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="Email Address" required 
                                                   value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>">
                                            <label for="email">Email Address *</label>
                                        </div>
                                        
                                        <div class="form-floating">
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   placeholder="Phone Number" required 
                                                   value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>">
                                            <label for="phone">Phone Number *</label>
                                        </div>
                                        
                                        <div class="form-floating">
                                            <textarea class="form-control" id="address" name="address" 
                                                      style="height: 100px" placeholder="Address"><?php echo htmlspecialchars($data['address'] ?? ''); ?></textarea>
                                            <label for="address">Address</label>
                                        </div>
                                    </div>
                                    
                                    <!-- Account Information -->
                                    <div class="col-md-6">
                                        <h5 class="mb-3 text-primary">
                                            <i class="fas fa-key me-2"></i>
                                            Account Information
                                        </h5>
                                        
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   placeholder="Username" required 
                                                   value="<?php echo htmlspecialchars($data['username'] ?? ''); ?>">
                                            <label for="username">Username *</label>
                                            <div class="form-text">Only letters, numbers, and underscores allowed</div>
                                        </div>
                                        
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Password" required>
                                            <label for="password">Password *</label>
                                            <div class="password-strength" id="passwordStrength"></div>
                                            <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                                        </div>
                                        
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                   placeholder="Confirm Password" required>
                                            <label for="confirm_password">Confirm Password *</label>
                                        </div>
                                        
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="organization" name="organization" 
                                                   placeholder="Organization (Optional)" 
                                                   value="<?php echo htmlspecialchars($data['organization'] ?? ''); ?>">
                                            <label for="organization">Organization (Optional)</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Preferences -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h5 class="mb-3 text-primary">
                                            <i class="fas fa-cog me-2"></i>
                                            Preferences
                                        </h5>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Preferred Contact Method</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="preferred_contact" 
                                                           value="email" id="contact_email" 
                                                           <?php echo ($data['preferred_contact'] ?? 'email') === 'email' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="contact_email">
                                                        <i class="fas fa-envelope me-2"></i>Email
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="preferred_contact" 
                                                           value="phone" id="contact_phone"
                                                           <?php echo ($data['preferred_contact'] ?? '') === 'phone' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="contact_phone">
                                                        <i class="fas fa-phone me-2"></i>Phone
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="preferred_contact" 
                                                           value="both" id="contact_both"
                                                           <?php echo ($data['preferred_contact'] ?? '') === 'both' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="contact_both">
                                                        <i class="fas fa-address-book me-2"></i>Both Email & Phone
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label">Privacy Options</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_anonymous" 
                                                           id="is_anonymous" value="1"
                                                           <?php echo ($data['is_anonymous'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="is_anonymous">
                                                        <i class="fas fa-user-secret me-2"></i>
                                                        Make my donations anonymous by default
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                                            <label class="form-check-label" for="terms">
                                                I agree to the <a href="#" class="text-decoration-none">Terms and Conditions</a> 
                                                and <a href="#" class="text-decoration-none">Privacy Policy</a> *
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="newsletter" id="newsletter">
                                            <label class="form-check-label" for="newsletter">
                                                Subscribe to newsletter for updates about monastery activities
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Create Donator Account
                                    </button>
                                </div>
                                
                                <!-- Login Link -->
                                <div class="text-center mt-4">
                                    <p class="mb-0">
                                        Already have an account? 
                                        <a href="login.php?role=donator" class="text-decoration-none fw-bold">
                                            Login here
                                        </a>
                                    </p>
                                </div>
                            </form>
                        <?php endif; ?>
                        
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Password strength indicator
            $('#password').on('input', function() {
                const password = $(this).val();
                const strengthBar = $('#passwordStrength');
                let strength = 0;
                
                if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                strengthBar.removeClass('strength-weak strength-medium strength-strong');
                
                if (strength < 3) {
                    strengthBar.addClass('strength-weak').css('width', '33%');
                } else if (strength < 4) {
                    strengthBar.addClass('strength-medium').css('width', '66%');
                } else {
                    strengthBar.addClass('strength-strong').css('width', '100%');
                }
            });
            
            // Password confirmation validation
            $('#confirm_password').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                
                if (password !== confirmPassword && confirmPassword.length > 0) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Username validation
            $('#username').on('input', function() {
                const username = $(this).val();
                const pattern = /^[a-zA-Z0-9_]+$/;
                
                if (!pattern.test(username) && username.length > 0) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Form validation
            $('#registerForm').on('submit', function(e) {
                const password = $('#password').val();
                const confirmPassword = $('#confirm_password').val();
                const terms = $('#terms').is(':checked');
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                if (!terms) {
                    e.preventDefault();
                    alert('You must accept the terms and conditions');
                    return false;
                }
                
                // Show loading state
                $(this).find('button[type="submit"]').addClass('disabled').html(
                    '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...'
                );
                
                return true;
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>