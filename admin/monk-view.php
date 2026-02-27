<?php
/**
 * Admin - View Monk Details
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

$page_title = 'Monk Details';
$currentPage = 'monks.php';

// Get database connection
$db = Database::getInstance();

$monk = null;
$appointments = [];
$medicalRecords = [];
$stats = [];

// Get monk ID from URL
$monkId = intval($_GET['id'] ?? 0);

if (!$monkId) {
    $_SESSION['error'] = 'Invalid monk ID';
    header('Location: monks.php');
    exit;
}

// Load monk data with room information
try {
    $monk = $db->fetchOne("
        SELECT m.*, r.room_number, r.room_type, r.capacity
        FROM monks m 
        LEFT JOIN rooms r ON m.room_id = r.room_id 
        WHERE m.monk_id = ?
    ", [$monkId]);
    
    if (!$monk) {
        $_SESSION['error'] = 'Monk not found';
        header('Location: monks.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Load monk error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading monk data';
    header('Location: monks.php');
    exit;
}

// Load related data
try {
    // Get recent appointments
    $appointments = $db->fetchAll("
        SELECT a.*, d.full_name as doctor_name 
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.monk_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ", [$monkId]);
    
    // Get recent medical records
    $medicalRecords = $db->fetchAll("
        SELECT mr.*, d.full_name as doctor_name
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.monk_id = ?
        ORDER BY mr.record_date DESC
        LIMIT 5
    ", [$monkId]);
    
    // Get statistics
    $stats = [
        'total_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ?", [$monkId])['count'],
        'completed_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ? AND status = 'completed'", [$monkId])['count'],
        'pending_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()", [$monkId])['count'],
        'medical_records' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
        'last_appointment' => $db->fetchOne("SELECT MAX(appointment_date) as date FROM appointments WHERE monk_id = ? AND status = 'completed'", [$monkId])['date'] ?? null,
        'next_appointment' => $db->fetchOne("SELECT MIN(appointment_date) as date FROM appointments WHERE monk_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()", [$monkId])['date'] ?? null
    ];
    
} catch (Exception $e) {
    error_log("Load monk related data error: " . $e->getMessage());
    // Continue with empty data
}

// Helper function to format dates
function formatDate($date, $format = 'M d, Y') {
    return $date ? date($format, strtotime($date)) : 'Not specified';
}

// Helper function to format age
function calculateAge($birthDate) {
    if (!$birthDate) return null;
    
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birth);
    
    return $age->y;
}

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Monk Details</h1>
            <p class="mb-0 text-muted">Complete profile for <?php echo htmlspecialchars($monk['full_name']); ?></p>
        </div>
        <div class="btn-group">
            <a href="monk-edit.php?id=<?php echo $monkId; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit Monk
            </a>
            <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-plus me-2"></i>Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="../doctor/appointment-new.php?monk_id=<?php echo $monkId; ?>">
                    <i class="fas fa-calendar-plus me-2"></i>Schedule Appointment
                </a></li>
                <li><a class="dropdown-item" href="../doctor/medical-record-new.php?monk_id=<?php echo $monkId; ?>">
                    <i class="fas fa-file-medical me-2"></i>Add Medical Record
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="monks.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Monks
                </a></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <!-- Main Profile Information -->
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex align-items-center">
                    <h6 class="mb-0 me-auto"><i class="fas fa-user me-2"></i>Basic Information</h6>
                    <span class="badge bg-<?php echo $monk['is_active'] ? 'success' : 'secondary'; ?>">
                        <?php echo $monk['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Full Name:</td>
                                    <td><strong><?php echo htmlspecialchars($monk['full_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Username:</td>
                                    <td><?php echo htmlspecialchars($monk['username']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email:</td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($monk['email']); ?>">
                                            <?php echo htmlspecialchars($monk['email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Phone:</td>
                                    <td>
                                        <?php if ($monk['phone']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($monk['phone']); ?>">
                                                <?php echo htmlspecialchars($monk['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Age:</td>
                                    <td><?php echo $monk['age']; ?> years old</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Date of Birth:</td>
                                    <td>
                                        <?php echo formatDate($monk['date_of_birth']); ?>
                                        <?php if ($monk['date_of_birth'] && calculateAge($monk['date_of_birth']) != $monk['age']): ?>
                                            <small class="text-warning">(Age mismatch)</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Blood Type:</td>
                                    <td>
                                        <?php if ($monk['blood_type']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($monk['blood_type']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Ordination Date:</td>
                                    <td><?php echo formatDate($monk['ordination_date']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($monk['bio']): ?>
                        <div class="mt-3">
                            <h6 class="text-muted mb-2">Biography</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($monk['bio'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Emergency Contact -->
            <?php if ($monk['emergency_contact'] || $monk['emergency_phone']): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-friends text-muted me-3"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($monk['emergency_contact'] ?: 'Not specified'); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-phone text-muted me-3"></i>
                                <div>
                                    <?php if ($monk['emergency_phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($monk['emergency_phone']); ?>">
                                            <?php echo htmlspecialchars($monk['emergency_phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Health Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Health Information</h6>
                </div>
                <div class="card-body">
                    <?php if ($monk['health_conditions'] || $monk['medications'] || $monk['allergies']): ?>
                        <div class="row">
                            <?php if ($monk['health_conditions']): ?>
                            <div class="col-md-4 mb-3">
                                <h6 class="text-muted mb-2">Health Conditions</h6>
                                <div class="alert alert-light">
                                    <?php echo nl2br(htmlspecialchars($monk['health_conditions'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($monk['medications']): ?>
                            <div class="col-md-4 mb-3">
                                <h6 class="text-muted mb-2">Current Medications</h6>
                                <div class="alert alert-light">
                                    <?php echo nl2br(htmlspecialchars($monk['medications'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($monk['allergies']): ?>
                            <div class="col-md-4 mb-3">
                                <h6 class="text-muted mb-2">Allergies</h6>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo nl2br(htmlspecialchars($monk['allergies'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                            <p>No health information recorded</p>
                            <a href="monk-edit.php?id=<?php echo $monkId; ?>" class="btn btn-outline-primary btn-sm">
                                Add Health Information
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Medical Records -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex align-items-center">
                    <h6 class="mb-0 me-auto"><i class="fas fa-file-medical me-2"></i>Recent Medical Records</h6>
                    <a href="../doctor/patient-records.php?monk_id=<?php echo $monkId; ?>" class="btn btn-outline-primary btn-sm">
                        View All Records
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($medicalRecords)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Type</th>
                                        <th>Diagnosis</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicalRecords as $record): ?>
                                    <tr>
                                        <td><?php echo formatDate($record['record_date']); ?></td>
                                        <td><?php echo htmlspecialchars($record['doctor_name'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($record['record_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $diagnosis = htmlspecialchars($record['diagnosis']);
                                            echo strlen($diagnosis) > 50 ? substr($diagnosis, 0, 50) . '...' : $diagnosis;
                                            ?>
                                        </td>
                                        <td>
                                            <a href="../doctor/medical-record-view.php?id=<?php echo $record['record_id']; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-clipboard fa-2x mb-2"></i>
                            <p>No medical records found</p>
                            <a href="../doctor/medical-record-new.php?monk_id=<?php echo $monkId; ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus me-2"></i>Add First Record
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h6 class="mb-0 me-auto"><i class="fas fa-calendar-alt me-2"></i>Recent Appointments</h6>
                    <a href="../doctor/appointments.php?monk_id=<?php echo $monkId; ?>" class="btn btn-outline-primary btn-sm">
                        View All Appointments
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Doctor</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                        <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['doctor_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo ucfirst($appointment['appointment_type']); ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'scheduled' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                'no_show' => 'warning'
                                            ];
                                            $color = $statusColors[$appointment['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../doctor/appointment-view.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-calendar fa-2x mb-2"></i>
                            <p>No appointments found</p>
                            <a href="../doctor/appointment-new.php?monk_id=<?php echo $monkId; ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus me-2"></i>Schedule Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Account Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user-check me-2"></i>Account Status</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle mx-auto mb-3">
                            <?php echo strtoupper(substr($monk['full_name'], 0, 2)); ?>
                        </div>
                        <h6><?php echo htmlspecialchars($monk['full_name']); ?></h6>
                        <span class="badge bg-<?php echo $monk['is_active'] ? 'success' : 'secondary'; ?> mb-2">
                            <?php echo $monk['is_active'] ? 'Active Account' : 'Inactive Account'; ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Member Since:</span>
                            <span><?php echo formatDate($monk['created_at']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Last Updated:</span>
                            <span><?php echo formatDate($monk['updated_at']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Last Login:</span>
                            <span><?php echo formatDate($monk['last_login'], 'M d, Y g:i A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Room Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bed me-2"></i>Room Assignment</h6>
                </div>
                <div class="card-body">
                    <?php if ($monk['room_number']): ?>
                        <div class="text-center">
                            <div class="display-6 text-primary mb-2">
                                <i class="fas fa-door-open"></i>
                            </div>
                            <h5>Room <?php echo htmlspecialchars($monk['room_number']); ?></h5>
                            <p class="text-muted mb-2"><?php echo ucfirst($monk['room_type']); ?> Room</p>
                            <small class="text-muted">
                                Capacity: <?php echo $monk['capacity']; ?> person(s)
                            </small>
                            <hr>
                            <a href="monk-edit.php?id=<?php echo $monkId; ?>#room_id" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-2"></i>Change Room
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-bed fa-2x mb-2"></i>
                            <p>No room assigned</p>
                            <a href="monk-edit.php?id=<?php echo $monkId; ?>#room_id" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus me-2"></i>Assign Room
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Health Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-primary mb-1"><?php echo $stats['total_appointments']; ?></h4>
                            <small class="text-muted">Total Appointments</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success mb-1"><?php echo $stats['completed_appointments']; ?></h4>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                    
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-info mb-1"><?php echo $stats['pending_appointments']; ?></h4>
                            <small class="text-muted">Pending</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-warning mb-1"><?php echo $stats['medical_records']; ?></h4>
                            <small class="text-muted">Medical Records</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <?php if ($stats['last_appointment']): ?>
                    <div class="small text-muted text-center mb-2">
                        <i class="fas fa-clock me-1"></i>
                        Last visit: <?php echo formatDate($stats['last_appointment']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['next_appointment']): ?>
                    <div class="small text-primary text-center">
                        <i class="fas fa-calendar-plus me-1"></i>
                        Next visit: <?php echo formatDate($stats['next_appointment']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="monk-edit.php?id=<?php echo $monkId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Information
                        </a>
                        <a href="../doctor/appointment-new.php?monk_id=<?php echo $monkId; ?>" class="btn btn-success">
                            <i class="fas fa-calendar-plus me-2"></i>Schedule Appointment
                        </a>
                        <a href="../doctor/medical-record-new.php?monk_id=<?php echo $monkId; ?>" class="btn btn-info">
                            <i class="fas fa-file-medical me-2"></i>Add Medical Record
                        </a>
                        <hr>
                        <button type="button" class="btn btn-danger" 
                                onclick="confirmDelete(<?php echo $monkId; ?>, '<?php echo addslashes($monk['full_name']); ?>')">
                            <i class="fas fa-trash me-2"></i>Delete Monk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    text-transform: uppercase;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.alert-light {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #495057;
    font-size: 0.9em;
}

.card-header h6 {
    color: #495057;
    font-weight: 600;
}
</style>

<script>
// Delete confirmation
function confirmDelete(monkId, monkName) {
    if (confirm(`Are you sure you want to delete ${monkName}? This action cannot be undone and will also delete all associated medical records and appointments.`)) {
        window.location.href = `monk-delete.php?id=${monkId}`;
    }
}

// Auto-refresh appointment status
$(document).ready(function() {
    // Highlight upcoming appointments
    const today = new Date().toISOString().split('T')[0];
    $('table tbody tr').each(function() {
        const dateCell = $(this).find('td:first');
        const appointmentDate = new Date(dateCell.text()).toISOString().split('T')[0];
        const statusBadge = $(this).find('.badge');
        
        if (appointmentDate === today && statusBadge.text().trim() === 'Scheduled') {
            $(this).addClass('table-warning');
        }
    });
});
</script>

<?php renderPageFooter(); ?>