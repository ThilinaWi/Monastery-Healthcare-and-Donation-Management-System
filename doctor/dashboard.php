<?php
/**
 * Doctor Dashboard
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

// Check doctor access
requireRole('doctor');

$page_title = 'Doctor Dashboard';
$currentPage = 'dashboard.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$doctorId = $currentUser['doctor_id'];

// Fetch dashboard data
try {
    // Doctor's work statistics
    $workStats = [
        'total_patients' => $db->fetchOne("SELECT COUNT(DISTINCT monk_id) as count FROM appointments WHERE doctor_id = ?", [$doctorId])['count'],
        'total_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$doctorId])['count'],
        'completed_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'", [$doctorId])['count'],
        'pending_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()", [$doctorId])['count']
    ];
    
    // Today's appointments
    $todaysAppointments = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name, m.age, r.room_number
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        LEFT JOIN rooms r ON m.room_id = r.room_id
        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
        ORDER BY a.appointment_time ASC
    ", [$doctorId]);
    
    // Upcoming appointments (next 7 days)
    $upcomingAppointments = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name, m.age, r.room_number
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        LEFT JOIN rooms r ON m.room_id = r.room_id
        WHERE a.doctor_id = ? 
        AND a.appointment_date > CURDATE() 
        AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ", [$doctorId]);
    
    // Recent medical records added by this doctor
    $recentMedicalRecords = $db->fetchAll("
        SELECT mr.*, m.full_name as monk_name
        FROM medical_records mr
        LEFT JOIN monks m ON mr.monk_id = m.monk_id
        WHERE mr.doctor_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 5
    ", [$doctorId]);
    
    // Patients requiring follow-up (example logic)
    $followUpPatients = $db->fetchAll("
        SELECT DISTINCT m.*, 
               mr.diagnosis, 
               mr.created_at as last_visit,
               DATEDIFF(CURDATE(), mr.created_at) as days_since_visit
        FROM monks m
        INNER JOIN medical_records mr ON m.monk_id = mr.monk_id
        WHERE mr.doctor_id = ?
        AND mr.follow_up_required = 1
        AND mr.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY mr.created_at DESC
        LIMIT 5
    ", [$doctorId]);
    
    // Monthly appointment statistics
    $monthlyStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_this_month,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_this_month,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_this_month
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE_FORMAT(appointment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    ", [$doctorId]);
    
} catch (Exception $e) {
    error_log("Doctor dashboard error: " . $e->getMessage());
    $workStats = ['total_patients' => 0, 'total_appointments' => 0, 'completed_appointments' => 0, 'pending_appointments' => 0];
    $todaysAppointments = [];
    $upcomingAppointments = [];
    $recentMedicalRecords = [];
    $followUpPatients = [];
    $monthlyStats = ['total_this_month' => 0, 'completed_this_month' => 0, 'cancelled_this_month' => 0];
}

// Render page
renderPageHeader($page_title, 'doctor');
renderSidebar('doctor', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Welcome, Dr. <?php echo htmlspecialchars($currentUser['full_name']); ?></h1>
            <p class="mb-0 text-muted">
                <?php echo htmlspecialchars($currentUser['specialization'] ?? 'General Medicine'); ?> • 
                License: <?php echo htmlspecialchars($currentUser['license_number'] ?? 'N/A'); ?>
            </p>
        </div>
        <div class="text-end">
            <small class="text-muted">Joined: <?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?></small><br>
            <small class="text-muted">Today: <?php echo date('l, F d, Y'); ?></small>
        </div>
    </div>

    <!-- Work Statistics Cards -->
    <div class="row mb-4">
        <?php 
        renderStatsCard('Total Patients', $workStats['total_patients'], 'fas fa-users', 'primary');
        renderStatsCard('Total Appointments', $workStats['total_appointments'], 'fas fa-calendar', 'success');
        renderStatsCard('Completed Visits', $workStats['completed_appointments'], 'fas fa-check-circle', 'info');
        renderStatsCard('Pending Appointments', $workStats['pending_appointments'], 'fas fa-clock', 'warning');
        ?>
    </div>

    <!-- Today's Schedule & Monthly Overview -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Schedule (<?php echo date('M d, Y'); ?>)</h6>
                    <span class="badge bg-light text-dark"><?php echo count($todaysAppointments); ?> appointments</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($todaysAppointments)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($todaysAppointments as $appointment): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <h6 class="mb-0 me-3"><?php echo htmlspecialchars($appointment['monk_name']); ?></h6>
                                                <small class="text-muted">Age: <?php echo htmlspecialchars($appointment['age'] ?? 'N/A'); ?></small>
                                                <?php if ($appointment['room_number']): ?>
                                                    <small class="text-muted ms-3">Room: <?php echo htmlspecialchars($appointment['room_number']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-clock text-muted me-2"></i>
                                                <span class="fw-bold text-success"><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></span>
                                                <?php if ($appointment['notes']): ?>
                                                    <small class="text-muted ms-3"><?php echo htmlspecialchars(substr($appointment['notes'], 0, 50)) . (strlen($appointment['notes']) > 50 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $appointment['status'] === 'completed' ? 'success' : 'info'; ?> mb-2">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <br>
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <a href="patient-records.php?monk_id=<?php echo $appointment['monk_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No appointments scheduled for today</h6>
                            <p class="text-muted small">Enjoy your free day or catch up on patient records!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Monthly Statistics -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>This Month's Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-12 mb-3">
                            <h4 class="text-info"><?php echo $monthlyStats['total_this_month']; ?></h4>
                            <small class="text-muted">Total Appointments</small>
                        </div>
                        <div class="col-6">
                            <h6 class="text-success"><?php echo $monthlyStats['completed_this_month']; ?></h6>
                            <small class="text-muted">Completed</small>
                        </div>
                        <div class="col-6">
                            <h6 class="text-danger"><?php echo $monthlyStats['cancelled_this_month']; ?></h6>
                            <small class="text-muted">Cancelled</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="add-medical-record.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add Medical Record
                        </a>
                        <a href="schedule.php" class="btn btn-primary">
                            <i class="fas fa-calendar me-2"></i>View Full Schedule
                        </a>
                        <a href="patient-records.php" class="btn btn-info">
                            <i class="fas fa-file-medical-alt me-2"></i>Patient Records
                        </a>
                        <a href="appointments.php" class="btn btn-warning">
                            <i class="fas fa-calendar-check me-2"></i>Manage Appointments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Follow-up Patients & Recent Records -->
    <div class="row">
        <!-- Patients Requiring Follow-up -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Follow-up Required</h6>
                    <span class="badge bg-dark"><?php echo count($followUpPatients); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($followUpPatients)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($followUpPatients as $patient): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($patient['full_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars(substr($patient['diagnosis'], 0, 80)) . (strlen($patient['diagnosis']) > 80 ? '...' : ''); ?>
                                            </p>
                                            <small class="text-muted">
                                                Last visit: <?php echo $patient['days_since_visit']; ?> days ago
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <a href="patient-records.php?monk_id=<?php echo $patient['monk_id']; ?>" 
                                               class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted">All patients are up to date!</p>
                            <small class="text-muted">No follow-ups required at this time</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Medical Records -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Recent Medical Records</h6>
                    <a href="patient-records.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentMedicalRecords)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentMedicalRecords as $record): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($record['monk_name']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars(substr($record['diagnosis'], 0, 80)) . (strlen($record['diagnosis']) > 80 ? '...' : ''); ?>
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
                            <p class="text-muted">No recent medical records</p>
                            <small class="text-muted">Records you create will appear here</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments (Next 7 Days) -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments (Next 7 Days)</h6>
                    <a href="schedule.php" class="btn btn-sm btn-outline-primary">Full Schedule</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcomingAppointments)): ?>
                        <div class="row">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card border border-info">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($appointment['monk_name']); ?></h6>
                                                <small class="text-muted">Room <?php echo $appointment['room_number'] ?? 'N/A'; ?></small>
                                            </div>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                <br>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                            </p>
                                            <?php if ($appointment['notes']): ?>
                                                <p class="small text-muted mb-0">
                                                    <?php echo htmlspecialchars(substr($appointment['notes'], 0, 50)) . (strlen($appointment['notes']) > 50 ? '...' : ''); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No upcoming appointments in the next 7 days</p>
                            <small class="text-muted">You have a clear schedule ahead!</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderPageFooter(); ?>