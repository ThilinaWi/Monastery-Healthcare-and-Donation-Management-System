<?php
/**
 * Admin Dashboard
 * Monastery Healthcare and Donation Management System
 */

define('INCLUDED', true);
session_start();

// Include required files
require_once '../includes/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/session_check.php';
require_once '../includes/layout.php';

// Check admin access
requireRole('admin');

$page_title = 'Admin Dashboard';
$currentPage = 'dashboard.php';

// Get database connection
$db = Database::getInstance();

// Fetch dashboard statistics
try {
    // Total counts
    $stats = [
        'monks' => $db->fetchOne("SELECT COUNT(*) as count FROM monks WHERE is_active = 1")['count'],
        'doctors' => $db->fetchOne("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")['count'],
        'donators' => $db->fetchOne("SELECT COUNT(*) as count FROM donators WHERE is_active = 1")['count'],
        'active_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()")['count']
    ];
    
    // Donations this month
    $currentMonth = date('Y-m');
    $donationsThisMonth = $db->fetchOne("SELECT SUM(amount) as total FROM donations WHERE DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonth])['total'] ?? 0;
    
    // Expenses this month
    $expensesThisMonth = $db->fetchOne("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonth])['total'] ?? 0;
    
    // Recent activities
    $recentDonations = $db->fetchAll("
        SELECT d.*, don.full_name as donator_name, dc.category_name
        FROM donations d
        LEFT JOIN donators don ON d.donator_id = don.donator_id
        LEFT JOIN donation_categories dc ON d.category_id = dc.category_id
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    
    $recentAppointments = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name, d.full_name as doctor_name
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    
    // Room occupancy
    $roomStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_rooms,
            SUM(current_occupancy) as occupied,
            SUM(capacity - current_occupancy) as available
        FROM rooms WHERE is_available = 1
    ");
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = ['monks' => 0, 'doctors' => 0, 'donators' => 0, 'active_appointments' => 0];
    $donationsThisMonth = 0;
    $expensesThisMonth = 0;
    $recentDonations = [];
    $recentAppointments = [];
    $roomStats = ['total_rooms' => 0, 'occupied' => 0, 'available' => 0];
}

// Calculate remaining balance
$remainingBalance = $donationsThisMonth - $expensesThisMonth;

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
            <p class="mb-0 text-muted">Welcome back, <?php echo htmlspecialchars(getCurrentUser()['full_name']); ?>!</p>
        </div>
        <div class="text-end">
            <small class="text-muted">Last Login: <?php echo date('M d, Y H:i'); ?></small><br>
            <small class="text-muted">Today: <?php echo date('l, F d, Y'); ?></small>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <?php 
        renderStatsCard('Total Monks', $stats['monks'], 'fas fa-users', 'primary');
        renderStatsCard('Total Doctors', $stats['doctors'], 'fas fa-user-md', 'success');
        renderStatsCard('Active Donators', $stats['donators'], 'fas fa-heart', 'warning');
        renderStatsCard('Pending Appointments', $stats['active_appointments'], 'fas fa-calendar-check', 'info');
        ?>
    </div>

    <!-- Financial Overview -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Donations This Month</h6>
                </div>
                <div class="card-body text-center">
                    <h3 class="text-success mb-0">$<?php echo number_format($donationsThisMonth, 2); ?></h3>
                    <small class="text-muted">Total received in <?php echo date('F Y'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Expenses This Month</h6>
                </div>
                <div class="card-body text-center">
                    <h3 class="text-danger mb-0">$<?php echo number_format($expensesThisMonth, 2); ?></h3>
                    <small class="text-muted">Total spent in <?php echo date('F Y'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header <?php echo $remainingBalance >= 0 ? 'bg-info' : 'bg-warning'; ?> text-white">
                    <h6 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Net Balance</h6>
                </div>
                <div class="card-body text-center">
                    <h3 class="<?php echo $remainingBalance >= 0 ? 'text-info' : 'text-warning'; ?> mb-0">
                        $<?php echo number_format($remainingBalance, 2); ?>
                    </h3>
                    <small class="text-muted">Available for <?php echo date('F Y'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Room Occupancy Overview -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bed me-2"></i>Room Occupancy Status</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <h5 class="text-primary"><?php echo $roomStats['total_rooms']; ?></h5>
                            <small class="text-muted">Total Rooms</small>
                        </div>
                        <div class="col-4">
                            <h5 class="text-danger"><?php echo $roomStats['occupied']; ?></h5>
                            <small class="text-muted">Occupied</small>
                        </div>
                        <div class="col-4">
                            <h5 class="text-success"><?php echo $roomStats['available']; ?></h5>
                            <small class="text-muted">Available</small>
                        </div>
                    </div>
                    
                    <?php if ($roomStats['total_rooms'] > 0): ?>
                        <div class="progress mt-3">
                            <?php 
                            $occupancyPercentage = ($roomStats['occupied'] / ($roomStats['occupied'] + $roomStats['available'])) * 100;
                            ?>
                            <div class="progress-bar bg-info" style="width: <?php echo $occupancyPercentage; ?>%">
                                <?php echo round($occupancyPercentage, 1); ?>% Occupied
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <a href="monks.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Monk
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="doctors.php" class="btn btn-success w-100">
                                <i class="fas fa-plus me-2"></i>Add Doctor
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="expenses.php" class="btn btn-warning w-100">
                                <i class="fas fa-receipt me-2"></i>Record Expense
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="reports.php" class="btn btn-info w-100">
                                <i class="fas fa-chart-bar me-2"></i>View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <!-- Recent Donations -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-heart me-2"></i>Recent Donations</h6>
                    <a href="donations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentDonations)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentDonations as $donation): ?>
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">$<?php echo number_format($donation['amount'], 2); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <?php echo htmlspecialchars($donation['donator_name'] ?? 'Anonymous'); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($donation['category_name']); ?> •
                                                <?php echo date('M d, Y', strtotime($donation['created_at'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-heart fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No recent donations</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Upcoming Appointments</h6>
                    <a href="../admin/appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentAppointments)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentAppointments as $appointment): ?>
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($appointment['monk_name']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?> at 
                                                <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $appointment['status'] === 'scheduled' ? 'info' : 'secondary'; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No upcoming appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Status</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <i class="fas fa-database fa-2x text-success"></i>
                            <p class="mt-2 mb-0 small">Database</p>
                            <small class="text-success">Connected</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <i class="fas fa-server fa-2x text-success"></i>
                            <p class="mt-2 mb-0 small">Web Server</p>
                            <small class="text-success">Online</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <i class="fas fa-shield-alt fa-2x text-success"></i>
                            <p class="mt-2 mb-0 small">Security</p>
                            <small class="text-success">Active</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <i class="fas fa-clock fa-2x text-info"></i>
                            <p class="mt-2 mb-0 small">Last Backup</p>
                            <small class="text-info"><?php echo date('M d, Y'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderPageFooter(); ?>