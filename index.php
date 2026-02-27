<?php
/**
 * Main Entry Point - Monastery Healthcare and Donation Management System
 * This is the landing page that provides information about the system and login options
 */

// Define a constant to prevent direct access to included files
define('INCLUDED', true);

// Start session
session_start();

// Include configuration
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-box {
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        .feature-box:hover {
            transform: translateY(-5px);
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 20px;
            transition: transform 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
        }
        .role-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-lotus text-primary me-2"></i>
                <strong>Monastery System</strong>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-3 ms-2" href="auth/register.php">
                            Donate Now
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h1 class="display-4 fw-bold mb-4">
                        <i class="fas fa-lotus me-3"></i>
                        <?php echo SITE_NAME; ?>
                    </h1>
                    <p class="lead mb-5">
                        A comprehensive digital platform for managing healthcare services and donations 
                        in our monastery community. Promoting transparency, efficiency, and care.
                    </p>
                    <a href="#login" class="btn btn-light btn-lg me-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Access System
                    </a>
                    <a href="auth/register.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-heart me-2"></i>Make a Donation
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">System Features</h2>
                    <p class="lead text-muted">Comprehensive management tools for monastery operations</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-box text-center bg-light">
                        <i class="fas fa-user-md text-primary" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Healthcare Management</h4>
                        <p>Complete medical record keeping, appointment scheduling, and health monitoring for monks.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-box text-center bg-light">
                        <i class="fas fa-donate text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Donation Tracking</h4>
                        <p>Transparent donation management across multiple categories with detailed reporting.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-box text-center bg-light">
                        <i class="fas fa-chart-line text-info" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Financial Transparency</h4>
                        <p>Real-time financial reports showing donations received and expenses by category.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-box text-center bg-light">
                        <i class="fas fa-users text-warning" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">User Management</h4>
                        <p>Role-based access control for administrators, monks, doctors, and donators.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section id="login" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">System Access</h2>
                    <p class="lead text-muted">Choose your role to access the appropriate dashboard</p>
                </div>
            </div>
            
            <div class="row">
                <!-- Admin Login -->
                <div class="col-lg-3 col-md-6">
                    <div class="login-card text-center p-4">
                        <div class="role-icon text-danger">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4>Administrator</h4>
                        <p class="text-muted mb-4">Full system management and oversight</p>
                        <a href="auth/login.php?role=admin" class="btn btn-danger btn-lg w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                        </a>
                    </div>
                </div>

                <!-- Monk Login -->
                <div class="col-lg-3 col-md-6">
                    <div class="login-card text-center p-4">
                        <div class="role-icon text-primary">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4>Monk</h4>
                        <p class="text-muted mb-4">Medical appointments and personal records</p>
                        <a href="auth/login.php?role=monk" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Monk Login
                        </a>
                    </div>
                </div>

                <!-- Doctor Login -->
                <div class="col-lg-3 col-md-6">
                    <div class="login-card text-center p-4">
                        <div class="role-icon text-success">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h4>Doctor</h4>
                        <p class="text-muted mb-4">Medical records and appointment management</p>
                        <a href="auth/login.php?role=doctor" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Doctor Login
                        </a>
                    </div>
                </div>

                <!-- Donator Login/Register -->
                <div class="col-lg-3 col-md-6">
                    <div class="login-card text-center p-4">
                        <div class="role-icon text-warning">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Donator</h4>
                        <p class="text-muted mb-4">Make donations and view contribution history</p>
                        <a href="auth/login.php?role=donator" class="btn btn-outline-warning btn-lg w-100 mb-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Donator Login
                        </a>
                        <a href="auth/register.php" class="btn btn-warning btn-lg w-100">
                            <i class="fas fa-user-plus me-2"></i>Register to Donate
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="p-4">
                        <i class="fas fa-users text-primary" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 fw-bold text-primary">50+</h3>
                        <p class="lead">Monks Served</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="p-4">
                        <i class="fas fa-user-md text-success" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 fw-bold text-success">5+</h3>
                        <p class="lead">Medical Professionals</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="p-4">
                        <i class="fas fa-donate text-warning" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 fw-bold text-warning">100+</h3>
                        <p class="lead">Generous Donors</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="p-4">
                        <i class="fas fa-chart-line text-info" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 fw-bold text-info">100%</h3>
                        <p class="lead">Financial Transparency</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">Contact Information</h2>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <i class="fas fa-map-marker-alt text-primary mb-3" style="font-size: 2rem;"></i>
                            <h5>Location</h5>
                            <p class="text-muted">Monastery Address<br>City, Province<br>Sri Lanka</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <i class="fas fa-phone text-primary mb-3" style="font-size: 2rem;"></i>
                            <h5>Phone</h5>
                            <p class="text-muted">+94 XX XXX XXXX</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <i class="fas fa-envelope text-primary mb-3" style="font-size: 2rem;"></i>
                            <h5>Email</h5>
                            <p class="text-muted"><?php echo ADMIN_EMAIL; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <h5>
                        <i class="fas fa-lotus me-2"></i>
                        <?php echo SITE_NAME; ?>
                    </h5>
                    <p class="mb-0">
                        Dedicated to serving our monastery community through modern technology 
                        while maintaining traditional values of compassion and transparency.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <p class="mb-0">
                        <small>&copy; <?php echo date('Y'); ?> Monastery System. All rights reserved.</small>
                    </p>
                    <p class="mb-0">
                        <small>Built with ❤️ for the community</small>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('shadow');
            } else {
                navbar.classList.remove('shadow');
            }
        });
    </script>
</body>
</html>