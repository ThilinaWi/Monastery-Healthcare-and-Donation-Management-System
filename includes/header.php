<?php
/**
 * Common Header File
 * Monastery Healthcare and Donation Management System
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

// Get page title and current user role
$page_title = $page_title ?? 'Monastery System';
$current_role = $_SESSION['role'] ?? null;
$current_user = $_SESSION['user_data'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- Additional CSS -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<?php if (isset($current_role) && in_array($current_role, ['admin', 'monk', 'doctor', 'donator'])): ?>
    <!-- Dashboard Layout with Sidebar -->
    
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top" style="z-index: 1001; margin-left: 250px;">
        <div class="container-fluid">
            <!-- Sidebar Toggle -->
            <button class="btn btn-outline-light me-3" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Page Title -->
            <span class="navbar-brand mb-0 h1"><?php echo htmlspecialchars($page_title); ?></span>
            
            <!-- Right Side Menu -->
            <div class="navbar-nav ms-auto">
                <!-- Notifications -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger badge-notification">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle me-2"></i>New appointment request</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-donate me-2"></i>New donation received</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">View All</a></li>
                    </ul>
                </div>
                
                <!-- User Profile -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.png" 
                             alt="Profile" class="user-avatar me-2">
                        <span><?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">
                            <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?><br>
                            <small class="text-muted"><?php echo ucfirst($current_role); ?></small>
                        </h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/<?php echo $current_role; ?>/profile.php">
                            <i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/<?php echo $current_role; ?>/settings.php">
                            <i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="p-3">
            <!-- Logo/Brand -->
            <div class="text-center mb-4">
                <h4 class="text-white">
                    <i class="fas fa-lotus text-warning"></i><br>
                    <small>Monastery System</small>
                </h4>
            </div>
            
            <!-- Navigation Menu -->
            <ul class="nav nav-pills flex-column">
                <?php
                // Define menu items based on role
                $menu_items = [];
                
                switch ($current_role) {
                    case 'admin':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/admin/dashboard.php'],
                            ['icon' => 'fas fa-users', 'label' => 'Monks', 'url' => '/admin/monks/'],
                            ['icon' => 'fas fa-user-md', 'label' => 'Doctors', 'url' => '/admin/doctors/'],
                            ['icon' => 'fas fa-bed', 'label' => 'Rooms', 'url' => '/admin/rooms/'],
                            ['icon' => 'fas fa-donate', 'label' => 'Donations', 'url' => '/admin/donations/'],
                            ['icon' => 'fas fa-receipt', 'label' => 'Expenses', 'url' => '/admin/expenses/'],
                            ['icon' => 'fas fa-chart-bar', 'label' => 'Reports', 'url' => '/admin/reports/'],
                        ];
                        break;
                        
                    case 'monk':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/monk/dashboard.php'],
                            ['icon' => 'fas fa-calendar-check', 'label' => 'Appointments', 'url' => '/monk/appointments/'],
                            ['icon' => 'fas fa-file-medical', 'label' => 'Medical History', 'url' => '/monk/medical-history.php'],
                            ['icon' => 'fas fa-user', 'label' => 'Profile', 'url' => '/monk/profile.php'],
                        ];
                        break;
                        
                    case 'doctor':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/doctor/dashboard.php'],
                            ['icon' => 'fas fa-calendar-alt', 'label' => 'Appointments', 'url' => '/doctor/appointments/'],
                            ['icon' => 'fas fa-file-medical-alt', 'label' => 'Medical Records', 'url' => '/doctor/medical-records/'],
                            ['icon' => 'fas fa-users', 'label' => 'Patients', 'url' => '/doctor/patients/'],
                        ];
                        break;
                        
                    case 'donator':
                        $menu_items = [
                            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/donator/dashboard.php'],
                            ['icon' => 'fas fa-heart', 'label' => 'Make Donation', 'url' => '/donator/donate.php'],
                            ['icon' => 'fas fa-history', 'label' => 'Donation History', 'url' => '/donator/history.php'],
                            ['icon' => 'fas fa-list', 'label' => 'Categories', 'url' => '/donator/categories.php'],
                        ];
                        break;
                }
                
                foreach ($menu_items as $item):
                    $is_active = (strpos($_SERVER['REQUEST_URI'], $item['url']) !== false) ? 'active' : '';
                ?>
                    <li class="nav-item">
                        <a class="nav-link text-white <?php echo $is_active; ?>" 
                           href="<?php echo SITE_URL . $item['url']; ?>">
                            <i class="<?php echo $item['icon']; ?> me-2"></i>
                            <?php echo $item['label']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent" style="padding-top: 80px;">
        
<?php else: ?>
    <!-- Public Layout (Login, Register, etc.) -->
    
    <!-- Public Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>/">
                <i class="fas fa-lotus text-primary me-2"></i>
                <strong>Monastery System</strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-3 ms-2" 
                           href="<?php echo SITE_URL; ?>/register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content for Public Pages -->
    <main>
        
<?php endif; ?>

<!-- Flash Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mx-3" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mx-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning alert-dismissible fade show mx-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['info'])): ?>
    <div class="alert alert-info alert-dismissible fade show mx-3" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['info']); unset($_SESSION['info']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>