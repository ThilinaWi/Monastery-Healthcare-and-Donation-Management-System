<?php
/**
 * Monk Dashboard
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

// Check monk access
requireRole('monk');

$page_title = 'Monk Dashboard';
$currentPage = 'dashboard.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$monkId = $currentUser['monk_id'];

// Fetch dashboard data
try {
    // Monk's health summary
    $healthStats = [
        'total_records' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
        'recent_visits' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$monkId])['count'],
        'pending_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()", [$monkId])['count'],
        'completed_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ? AND status = 'completed'", [$monkId])['count']
    ];
    
    // Recent medical records
    $recentMedicalRecords = $db->fetchAll("
        SELECT mr.*, d.full_name as doctor_name
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.monk_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 5
    ", [$monkId]);
    
    // Upcoming appointments
    $upcomingAppointments = $db->fetchAll("
        SELECT a.*, d.full_name as doctor_name, d.specialization
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.monk_id = ? AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ", [$monkId]);
    
    // Room information
    $roomInfo = $db->fetchOne("
        SELECT r.*, 
               COUNT(m.monk_id) as current_occupants,
               GROUP_CONCAT(m.full_name SEPARATOR ', ') as roommates
        FROM rooms r
        LEFT JOIN monks m ON r.room_id = m.room_id AND m.is_active = 1
        WHERE r.room_id = (SELECT room_id FROM monks WHERE monk_id = ?)
        GROUP BY r.room_id
    ", [$monkId]);
    
    // Recent health indicators (if any)
    $lastCheckup = $db->fetchOne("
        SELECT mr.*, d.full_name as doctor_name
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.monk_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 1
    ", [$monkId]);
    
} catch (Exception $e) {
    error_log("Monk dashboard error: " . $e->getMessage());
    $healthStats = ['total_records' => 0, 'recent_visits' => 0, 'pending_appointments' => 0, 'completed_appointments' => 0];
    $recentMedicalRecords = [];
    $upcomingAppointments = [];
    $roomInfo = null;
    $lastCheckup = null;
}

// Render page
renderPageHeader($page_title, 'monk');
renderSidebar('monk', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></h1>
            <p class="mb-0 text-muted">Monk Dashboard - Manage your health and spiritual journey</p>
        </div>
        <div class="text-end">
            <small class="text-muted">Joined: <?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?></small><br>
            <small class="text-muted">Today: <?php echo date('l, F d, Y'); ?></small>
        </div>
    </div>

    <!-- Health Statistics Cards -->
    <div class="row mb-4">
        <?php 
        renderStatsCard('Medical Records', $healthStats['total_records'], 'fas fa-file-medical', 'primary');
        renderStatsCard('Recent Visits (30 days)', $healthStats['recent_visits'], 'fas fa-calendar-check', 'success');
        renderStatsCard('Pending Appointments', $healthStats['pending_appointments'], 'fas fa-clock', 'warning');
        renderStatsCard('Completed Visits', $healthStats['completed_appointments'], 'fas fa-check-circle', 'info');
        ?>
    </div>

    <!-- Health Status Overview -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Health Status Overview</h6>
                </div>
                <div class="card-body">
                    <?php if ($lastCheckup): ?>
                        <div class="row text-center">
                            <div class="col-12 mb-3">
                                <h6 class="text-muted">Last Medical Checkup</h6>
                                <p class="mb-1"><strong>Dr. <?php echo htmlspecialchars($lastCheckup['doctor_name']); ?></strong></p>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($lastCheckup['created_at'])); ?></small>
                            </div>
                        </div>
                        
                        <?php if ($lastCheckup['diagnosis']): ?>
                            <div class="alert alert-info">
                                <strong>Latest Diagnosis:</strong><br>
                                <?php echo htmlspecialchars($lastCheckup['diagnosis']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($lastCheckup['prescription']): ?>
                            <div class="alert alert-warning">
                                <strong>Current Prescription:</strong><br>
                                <?php echo htmlspecialchars($lastCheckup['prescription']); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No medical records found</p>
                            <a href="book-appointment.php" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-2"></i>Book Your First Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Room Information -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-bed me-2"></i>Room Information</h6>
                </div>
                <div class="card-body">
                    <?php if ($roomInfo): ?>
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-muted">Room Number</h6>
                                <h4 class="text-primary"><?php echo htmlspecialchars($roomInfo['room_number']); ?></h4>
                            </div>
                            <div class="col-6">
                                <h6 class="text-muted">Room Type</h6>
                                <p class="mb-0"><?php echo ucfirst($roomInfo['room_type']); ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Capacity: <?php echo $roomInfo['capacity']; ?></small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Occupants: <?php echo $roomInfo['current_occupants']; ?></small>
                            </div>
                        </div>
                        
                        <?php if ($roomInfo['roommates'] && $roomInfo['current_occupants'] > 1): ?>
                            <div class="mt-3">
                                <h6 class="text-muted">Roommates:</h6>
                                <p class="small"><?php echo htmlspecialchars($roomInfo['roommates']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($roomInfo['description']): ?>
                            <div class="mt-3">
                                <h6 class="text-muted">Room Description:</h6>
                                <p class="small"><?php echo htmlspecialchars($roomInfo['description']); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No room assigned</p>
                            <small class="text-muted">Please contact the administrator</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="book-appointment.php" class="btn btn-primary w-100">
                                <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="medical-history.php" class="btn btn-success w-100">
                                <i class="fas fa-file-medical me-2"></i>View Medical History
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="appointments.php" class="btn btn-info w-100">
                                <i class="fas fa-calendar-check me-2"></i>My Appointments
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="profile.php" class="btn btn-warning w-100">
                                <i class="fas fa-user me-2"></i>Update Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <!-- Recent Medical Records -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Recent Medical Records</h6>
                    <a href="medical-history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentMedicalRecords)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentMedicalRecords as $record): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars(substr($record['diagnosis'], 0, 100)) . (strlen($record['diagnosis']) > 100 ? '...' : ''); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
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
                            <i class="fas fa-file-medical fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No medical records yet</p>
                            <small class="text-muted">Your medical records will appear here after visits</small>
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
                    <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcomingAppointments)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                <i class="fas fa-clock ms-2 me-1"></i>
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
                            <a href="book-appointment.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Book Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Reminders -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Health Reminders & Tips</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-water text-info fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-0">Stay Hydrated</h6>
                                    <small class="text-muted">Drink at least 8 glasses of water daily</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-walking text-success fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-0">Regular Exercise</h6>
                                    <small class="text-muted">Daily meditation walks are beneficial</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-moon text-primary fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-0">Adequate Rest</h6>
                                    <small class="text-muted">Ensure 7-8 hours of sleep nightly</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($healthStats['pending_appointments'] == 0 && $healthStats['recent_visits'] == 0): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Health Checkup Reminder:</strong> 
                            It's been a while since your last medical checkup. Consider booking an appointment for a routine health assessment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderPageFooter(); ?>