<?php
/**
 * Common Layout Template
 * Monastery Healthcare and Donation Management System
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

/**
 * Render page header
 */
function renderPageHeader($title, $role, $additionalCSS = '') {
    $roleColors = [
        'admin' => '#dc3545',
        'monk' => '#0d6efd', 
        'doctor' => '#198754',
        'donator' => '#fd7e14'
    ];
    
    $roleIcons = [
        'admin' => 'fas fa-user-shield',
        'monk' => 'fas fa-user',
        'doctor' => 'fas fa-user-md', 
        'donator' => 'fas fa-heart'
    ];
    
    $currentColor = $roleColors[$role] ?? '#6c757d';
    $currentIcon = $roleIcons[$role] ?? 'fas fa-user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- Custom Role-based Styling -->
    <style>
        :root {
            --role-color: <?php echo $currentColor; ?>;
        }
        
        .navbar-brand i {
            color: var(--role-color);
        }
        
        .role-badge {
            background-color: var(--role-color);
            color: white;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--role-color), rgba(var(--role-color), 0.8));
        }
        
        .btn-primary {
            background-color: var(--role-color);
            border-color: var(--role-color);
        }
        
        .btn-primary:hover {
            background-color: rgba(var(--role-color), 0.8);
            border-color: rgba(var(--role-color), 0.8);
        }
        
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }
        
        .card-header {
            border-left: 4px solid var(--role-color);
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .main-content {
            min-height: calc(100vh - 60px);
        }
        
        .sidebar {
            min-height: calc(100vh - 60px); 
            position: fixed;
            top: 60px;
            left: 0;
            width: 250px;
            z-index: 1000;
        }
        
        .content-area {
            margin-left: 250px;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
                transition: margin-left 0.3s;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .content-area {
                margin-left: 0;
            }
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--role-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
    
    <?php echo $additionalCSS; ?>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler d-md-none" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <a class="navbar-brand d-flex align-items-center" href="<?php echo SITE_URL; ?>">
                <i class="<?php echo $currentIcon; ?> me-2"></i>
                <span>Monastery System</span>
            </a>
            
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center">
                <!-- Role Badge -->
                <span class="badge role-badge me-3">
                    <i class="<?php echo $currentIcon; ?> me-1"></i>
                    <?php echo ucfirst($role); ?>
                </span>
                
                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" 
                            type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <div class="user-avatar me-2">
                            <?php echo strtoupper(substr(getCurrentUser()['full_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <span class="d-none d-md-inline">
                            <?php echo htmlspecialchars(getCurrentUser()['username'] ?? 'User'); ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <div class="dropdown-item-text">
                                <div class="fw-bold"><?php echo htmlspecialchars(getCurrentUser()['full_name'] ?? 'User'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars(getCurrentUser()['email'] ?? ''); ?></small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout.php" 
                               onclick="return confirm('Are you sure you want to logout?')">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="d-flex main-content">
<?php
}

/**
 * Render sidebar navigation
 */
function renderSidebar($role, $currentPage = '') {
    $sidebarMenus = [
        'admin' => [
            ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard.php'],
            ['title' => 'Manage Monks', 'icon' => 'fas fa-users', 'url' => 'monks.php'],
            ['title' => 'Manage Doctors', 'icon' => 'fas fa-user-md', 'url' => 'doctors.php'],
            ['title' => 'Room Management', 'icon' => 'fas fa-bed', 'url' => 'rooms.php'],
            ['title' => 'Donations', 'icon' => 'fas fa-hand-holding-heart', 'url' => 'donations.php'],
            ['title' => 'Expenses', 'icon' => 'fas fa-receipt', 'url' => 'expenses.php'],
            ['title' => 'Reports', 'icon' => 'fas fa-chart-bar', 'url' => 'reports.php'],
            ['title' => 'Settings', 'icon' => 'fas fa-cog', 'url' => 'settings.php']
        ],
        'monk' => [
            ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard.php'],
            ['title' => 'My Health Records', 'icon' => 'fas fa-file-medical', 'url' => 'medical-history.php'],
            ['title' => 'Appointments', 'icon' => 'fas fa-calendar-check', 'url' => 'appointments.php'],
            ['title' => 'Book Appointment', 'icon' => 'fas fa-plus-circle', 'url' => 'book-appointment.php'],
            ['title' => 'My Room', 'icon' => 'fas fa-bed', 'url' => 'room-info.php'],
            ['title' => 'Profile', 'icon' => 'fas fa-user', 'url' => 'profile.php']
        ],
        'doctor' => [
            ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard.php'],
            ['title' => 'My Schedule', 'icon' => 'fas fa-calendar', 'url' => 'schedule.php'],
            ['title' => 'Appointments', 'icon' => 'fas fa-calendar-check', 'url' => 'appointments.php'],
            ['title' => 'Patient Records', 'icon' => 'fas fa-file-medical-alt', 'url' => 'patient-records.php'],
            ['title' => 'Add Medical Record', 'icon' => 'fas fa-plus-square', 'url' => 'add-medical-record.php'],
            ['title' => 'Profile', 'icon' => 'fas fa-user', 'url' => 'profile.php']
        ],
        'donator' => [
            ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard.php'],
            ['title' => 'Make Donation', 'icon' => 'fas fa-heart', 'url' => 'donate.php'],
            ['title' => 'My Donations', 'icon' => 'fas fa-history', 'url' => 'donation-history.php'],
            ['title' => 'Transparency Report', 'icon' => 'fas fa-chart-pie', 'url' => 'transparency.php'],
            ['title' => 'Profile', 'icon' => 'fas fa-user', 'url' => 'profile.php']
        ]
    ];
    
    $menu = $sidebarMenus[$role] ?? []; 
?>
        <!-- Sidebar -->
        <nav class="sidebar bg-dark text-white p-3" id="sidebar">
            <div class="sidebar-header mb-4">
                <h5 class="text-center">
                    <i class="fas fa-lotus me-2"></i>
                    <?php echo ucfirst($role); ?> Panel
                </h5>
            </div>
            
            <ul class="nav nav-pills flex-column">
                <?php foreach ($menu as $item): ?>
                    <li class="nav-item mb-2">
                        <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                           class="nav-link text-white d-flex align-items-center
                                  <?php echo ($currentPage === $item['url']) ? 'active' : ''; ?>">
                            <i class="<?php echo $item['icon']; ?> me-3"></i>
                            <?php echo htmlspecialchars($item['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <!-- Session Info -->
            <div class="mt-auto pt-4 border-top">
                <small class="text-muted d-block">Session Time</small>
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock me-2 text-warning"></i>
                    <span id="sessionTimer" class="small">--:--</span>
                </div>
                <small class="text-muted mt-2 d-block">
                    Last Activity: <span id="lastActivity">Just now</span>
                </small>
            </div>
        </nav>
        
        <!-- Main Content Area -->
        <main class="content-area flex-fill">
<?php
}

/**
 * Render page footer
 */
function renderPageFooter($includeSessionTimeout = true) {
?>
        </main>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/common.js"></script>
    
    <!-- Common Dashboard Scripts -->
    <script>
        $(document).ready(function() {
            // Sidebar toggle for mobile
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('show');
            });
            
            // Session timer
            <?php if ($includeSessionTimeout): ?>
                updateSessionTimer();
                setInterval(updateSessionTimer, 60000); // Update every minute
            <?php endif; ?>
            
            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Confirm delete actions
            $('.btn-delete').click(function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
            
            // Data tables initialization
            if (typeof $('.data-table').DataTable === 'function') {
                $('.data-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        search: "Search records:",
                        lengthMenu: "Show _MENU_ records per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ records",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            }
        });
        
        function updateSessionTimer() {
            const sessionTime = <?php echo getSessionRemainingTime(); ?>;
            if (sessionTime > 0) {
                const hours = Math.floor(sessionTime / 3600);
                const minutes = Math.floor((sessionTime % 3600) / 60);
                $('#sessionTimer').text(
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0')
                );
                
                // Warning at 5 minutes
                if (sessionTime <= 300) {
                    $('#sessionTimer').addClass('text-danger');
                }
            }
        }
        
        // Extend session on activity
        let activityTimer;
        function resetActivityTimer() {
            clearTimeout(activityTimer);
            activityTimer = setTimeout(function() {
                $.post('<?php echo SITE_URL; ?>/includes/extend_session.php');
            }, 300000); // 5 minutes of inactivity
        }
        
        $(document).on('click keypress scroll', resetActivityTimer);
        resetActivityTimer();
    </script>
    
    <?php echo getAutoLogoutScript(5); ?>
    
</body>
</html>
<?php
}

/**
 * Render alert messages
 */
function renderAlert($type, $message, $dismissible = true) {
    $alertClass = 'alert-' . $type;
    $icon = '';
    
    switch ($type) {
        case 'success':
            $icon = 'fas fa-check-circle';
            break;
        case 'error':
        case 'danger':
            $icon = 'fas fa-exclamation-circle';
            break;
        case 'warning':
            $icon = 'fas fa-exclamation-triangle';
            break;
        case 'info':
            $icon = 'fas fa-info-circle';
            break;
    }
?>
    <div class="alert <?php echo $alertClass; ?> <?php echo $dismissible ? 'alert-dismissible fade show' : ''; ?>" role="alert">
        <i class="<?php echo $icon; ?> me-2"></i>
        <?php echo $message; ?>
        <?php if ($dismissible): ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <?php endif; ?>
    </div>
<?php
}

/**
 * Render stats card
 */
function renderStatsCard($title, $value, $icon, $color = 'primary', $subtitle = '') {
?>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-<?php echo $color; ?> text-white rounded-circle p-3">
                            <i class="<?php echo $icon; ?> fa-lg"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($value); ?></h5>
                        <p class="card-text text-muted mb-0"><?php echo htmlspecialchars($title); ?></p>
                        <?php if ($subtitle): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($subtitle); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}
?>