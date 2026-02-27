<?php
/**
 * Doctor - Appointment Management
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

$page_title = 'My Appointments';
$currentPage = 'appointments.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$doctorId = $currentUser['doctor_id'];

$error = '';
$success = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_status'])) {
            $appointmentId = intval($_POST['appointment_id']);
            $newStatus = sanitize_input($_POST['status']);
            $notes = sanitize_input($_POST['notes'] ?? '');
            
            // Validate appointment belongs to this doctor
            $appointment = $db->fetchOne("SELECT * FROM appointments WHERE appointment_id = ? AND doctor_id = ?", [$appointmentId, $doctorId]);
            
            if (!$appointment) {
                throw new Exception('Appointment not found or access denied');
            }
            
            // Validate status
            $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Invalid status provided');
            }
            
            // Update appointment
            $updateData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (!empty($notes)) {
                $updateData['notes'] = $notes;
            }
            
            $db->update('appointments', $updateData, "appointment_id = ?", [$appointmentId]);
            
            // Log the action
            $db->insert('system_logs', [
                'user_type' => 'doctor',
                'user_id' => $doctorId,
                'action' => 'update',
                'table_affected' => 'appointments',
                'record_id' => $appointmentId,
                'old_values' => json_encode(['status' => $appointment['status']]),
                'new_values' => json_encode($updateData),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = "Appointment status updated successfully.";
            
        } elseif (isset($_POST['reschedule'])) {
            $appointmentId = intval($_POST['appointment_id']);
            $newDate = $_POST['new_date'];
            $newTime = $_POST['new_time'];
            
            // Validate appointment belongs to this doctor
            $appointment = $db->fetchOne("SELECT * FROM appointments WHERE appointment_id = ? AND doctor_id = ?", [$appointmentId, $doctorId]);
            
            if (!$appointment) {
                throw new Exception('Appointment not found or access denied');
            }
            
            // Validate new date/time is in future
            $newDateTime = new DateTime($newDate . ' ' . $newTime);
            if ($newDateTime <= new DateTime()) {
                throw new Exception('New appointment time must be in the future');
            }
            
            // Check for conflicts
            $conflict = $db->fetchOne("
                SELECT COUNT(*) as count FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                AND appointment_id != ? AND status = 'scheduled'
            ", [$doctorId, $newDate, $newTime, $appointmentId]);
            
            if ($conflict['count'] > 0) {
                throw new Exception('Time slot conflict. Please choose a different time.');
            }
            
            // Update appointment
            $updateData = [
                'appointment_date' => $newDate,
                'appointment_time' => $newTime,
                'status' => 'scheduled',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('appointments', $updateData, "appointment_id = ?", [$appointmentId]);
            
            $success = "Appointment rescheduled successfully.";
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filter parameters
$status = sanitize_input($_GET['status'] ?? '');
$dateFilter = sanitize_input($_GET['date_filter'] ?? 'upcoming');
$searchTerm = sanitize_input($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$whereConditions = ["a.doctor_id = ?"];
$params = [$doctorId];

if (!empty($status)) {
    $whereConditions[] = "a.status = ?";
    $params[] = $status;
}

switch ($dateFilter) {
    case 'today':
        $whereConditions[] = "a.appointment_date = CURDATE()";
        break;
    case 'tomorrow':
        $whereConditions[] = "a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $whereConditions[] = "a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'upcoming':
        $whereConditions[] = "a.appointment_date >= CURDATE()";
        break;
    case 'past':
        $whereConditions[] = "a.appointment_date < CURDATE()";
        break;
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(m.full_name LIKE ? OR a.symptoms LIKE ? OR a.notes LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get appointments with monk information
    $appointments = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name, m.monk_id, m.phone as monk_phone,
               COUNT(DISTINCT mr.record_id) as medical_records_count
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        LEFT JOIN medical_records mr ON a.monk_id = mr.monk_id
        WHERE {$whereClause}
        GROUP BY a.appointment_id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT {$limit} OFFSET {$offset}
    ", $params);
    
    // Get total count
    $totalAppointments = $db->fetchOne("
        SELECT COUNT(DISTINCT a.appointment_id) as count
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        WHERE {$whereClause}
    ", $params)['count'];
    
    $totalPages = ceil($totalAppointments / $limit);
    
    // Get appointment statistics
    $stats = [
        'total' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?", [$doctorId])['count'],
        'today' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()", [$doctorId])['count'],
        'upcoming' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled'", [$doctorId])['count'],
        'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'", [$doctorId])['count'],
        'cancelled' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'cancelled'", [$doctorId])['count']
    ];
    
    // Get today's schedule
    $todaysSchedule = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ", [$doctorId]);
    
    // Get upcoming urgent appointments
    $urgentAppointments = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE() 
        AND a.priority_level IN ('high', 'urgent') AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ", [$doctorId]);
    
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
    $appointments = [];
    $totalAppointments = 0;
    $totalPages = 0;
    $stats = ['total' => 0, 'today' => 0, 'upcoming' => 0, 'completed' => 0, 'cancelled' => 0];
    $todaysSchedule = [];
    $urgentAppointments = [];
}

// Helper function for status badges
function getStatusBadge($status) {
    $badges = [
        'scheduled' => 'bg-primary',
        'in_progress' => 'bg-warning',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
        'no_show' => 'bg-secondary'
    ];
    
    return $badges[$status] ?? 'bg-secondary';
}

function getPriorityBadge($priority) {
    $badges = [
        'normal' => 'bg-info',
        'high' => 'bg-warning',
        'urgent' => 'bg-danger'
    ];
    
    return $badges[$priority] ?? 'bg-info';
}

// Render page
renderPageHeader($page_title, 'doctor');
renderSidebar('doctor', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">My Appointments</h1>
            <p class="mb-0 text-muted">Manage your patient appointments and schedule</p>
        </div>
        <div class="btn-group">
            <a href="add-medical-record.php" class="btn btn-outline-success">
                <i class="fas fa-plus-circle me-2"></i>Add Medical Record
            </a>
            <button class="btn btn-outline-info" onclick="refreshAppointments()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($error): ?>
        <?php renderAlert('danger', $error); ?>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <?php renderAlert('success', $success); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Main Appointments List -->
        <div class="col-lg-9">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white-75">Today's Appointments</div>
                                    <div class="h2 mb-0"><?php echo $stats['today']; ?></div>
                                </div>
                                <i class="fas fa-calendar-day fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-warning text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white-75">Upcoming</div>
                                    <div class="h2 mb-0"><?php echo $stats['upcoming']; ?></div>
                                </div>
                                <i class="fas fa-clock fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white-75">Completed</div>
                                    <div class="h2 mb-0"><?php echo $stats['completed']; ?></div>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-info text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white-75">Total</div>
                                    <div class="h2 mb-0"><?php echo $stats['total']; ?></div>
                                </div>
                                <i class="fas fa-users fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Appointments</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="no_show" <?php echo $status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_filter" class="form-label">Date Range</label>
                            <select class="form-select" id="date_filter" name="date_filter">
                                <option value="upcoming" <?php echo $dateFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?php echo $dateFilter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="this_week" <?php echo $dateFilter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="past" <?php echo $dateFilter === 'past' ? 'selected' : ''; ?>>Past</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search monk name, symptoms..." 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="appointments.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Appointments (<?php echo number_format($totalAppointments); ?> total)
                    </h6>
                    <?php if ($totalPages > 1): ?>
                        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($appointments)): ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-item card mb-3 border-left-<?php echo str_replace('bg-', '', getStatusBadge($appointment['status'])); ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="avatar-circle me-3">
                                                    <?php echo strtoupper(substr($appointment['monk_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($appointment['monk_name']); ?></h6>
                                                    <small class="text-muted">Monk ID: #<?php echo $appointment['monk_id']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="mb-1">
                                                <i class="fas fa-calendar me-2 text-primary"></i>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                            </div>
                                            <div class="mb-1">
                                                <i class="fas fa-clock me-2 text-info"></i>
                                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </div>
                                            <div>
                                                <span class="badge <?php echo getPriorityBadge($appointment['priority_level']); ?> small">
                                                    <?php echo ucfirst($appointment['priority_level']); ?> Priority
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="mb-2">
                                                <span class="badge <?php echo getStatusBadge($appointment['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted">
                                                <?php echo ucfirst(str_replace('_', ' ', $appointment['appointment_type'])); ?>
                                            </div>
                                            <?php if ($appointment['medical_records_count'] > 0): ?>
                                                <div class="small text-success">
                                                    <i class="fas fa-file-medical me-1"></i>
                                                    <?php echo $appointment['medical_records_count']; ?> records
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-2 text-end">
                                            <div class="btn-group-vertical">
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="viewAppointmentDetails(<?php echo $appointment['appointment_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($appointment['status'] === 'scheduled'): ?>
                                                    <button class="btn btn-outline-warning btn-sm" 
                                                            onclick="startAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php elseif ($appointment['status'] === 'in_progress'): ?>
                                                    <button class="btn btn-outline-success btn-sm" 
                                                            onclick="completeAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (in_array($appointment['status'], ['scheduled', 'in_progress'])): ?>
                                                    <button class="btn btn-outline-secondary btn-sm" 
                                                            onclick="rescheduleAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($appointment['symptoms']): ?>
                                        <div class="mt-3 pt-3 border-top">
                                            <h6 class="text-info mb-2"><i class="fas fa-stethoscope me-2"></i>Symptoms/Concerns</h6>
                                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($appointment['symptoms'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['notes']): ?>
                                        <div class="mt-2">
                                            <h6 class="text-muted mb-2"><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Appointments navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    ?>
                                    
                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                            <h5 class="text-muted">No appointments found</h5>
                            <p class="text-muted">
                                <?php if (!empty($status) || !empty($searchTerm) || $dateFilter !== 'upcoming'): ?>
                                    No appointments match your current filters.
                                <?php else: ?>
                                    You don't have any upcoming appointments yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Today's Schedule -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Schedule</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($todaysSchedule)): ?>
                        <?php foreach ($todaysSchedule as $today): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div>
                                    <div class="fw-bold small"><?php echo date('g:i A', strtotime($today['appointment_time'])); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($today['monk_name']); ?></div>
                                    <span class="badge <?php echo getStatusBadge($today['status']); ?> small">
                                        <?php echo ucfirst($today['status']); ?>
                                    </span>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" 
                                        onclick="viewAppointmentDetails(<?php echo $today['appointment_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <p class="mb-0">No appointments today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Urgent Appointments -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Urgent Appointments</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($urgentAppointments)): ?>
                        <?php foreach ($urgentAppointments as $urgent): ?>
                            <div class="mb-3 pb-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?php echo htmlspecialchars($urgent['monk_name']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo date('M d, Y g:i A', strtotime($urgent['appointment_date'] . ' ' . $urgent['appointment_time'])); ?>
                                        </div>
                                        <span class="badge <?php echo getPriorityBadge($urgent['priority_level']); ?> small">
                                            <?php echo ucfirst($urgent['priority_level']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($urgent['symptoms']): ?>
                                    <div class="small text-muted mt-1">
                                        <?php echo htmlspecialchars(substr($urgent['symptoms'], 0, 50)) . (strlen($urgent['symptoms']) > 50 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-heart fa-2x mb-2"></i>
                            <p class="mb-0">No urgent appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="add-medical-record.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Add Medical Record
                        </a>
                        <button class="btn btn-outline-info" onclick="viewSchedule()">
                            <i class="fas fa-calendar me-2"></i>View Full Schedule
                        </button>
                        <button class="btn btn-outline-warning" onclick="markAllComplete()">
                            <i class="fas fa-check-double me-2"></i>Mark All Complete
                        </button>
                        <button class="btn btn-outline-success" onclick="exportAppointments()">
                            <i class="fas fa-download me-2"></i>Export List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Action Modals -->
<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Appointment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="status_appointment_id">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">New Status</label>
                        <select class="form-select" name="status" id="status_select" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Add any additional notes about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reschedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_date" class="form-label">New Date</label>
                                <input type="date" class="form-control" name="new_date" required
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_time" class="form-label">New Time</label>
                                <select class="form-select" name="new_time" required>
                                    <?php
                                    for ($hour = 8; $hour <= 17; $hour++) {
                                        for ($minute = 0; $minute < 60; $minute += 30) {
                                            $time = sprintf('%02d:%02d', $hour, $minute);
                                            $display = date('g:i A', strtotime($time));
                                            echo "<option value=\"{$time}\">{$display}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reschedule" class="btn btn-warning">Reschedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary { border-left: 4px solid #007bff !important; }
.border-left-warning { border-left: 4px solid #ffc107 !important; }
.border-left-success { border-left: 4px solid #28a745 !important; }
.border-left-danger { border-left: 4px solid #dc3545 !important; }
.border-left-secondary { border-left: 4px solid #6c757d !important; }

.appointment-item {
    transition: all 0.3s ease;
}

.appointment-item:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.15);
    transform: translateY(-2px);
}

.avatar-circle {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}
</style>

<script>
function viewAppointmentDetails(appointmentId) {
    // Show modal with loading
    $('#appointmentDetailsModal').modal('show');
    $('#appointmentDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    
    // In a real implementation, this would make an AJAX call to get appointment details
    // For now, we'll show the modal with basic functionality
    setTimeout(() => {
        $('#appointmentDetailsContent').html(`
            <div class="alert alert-info">
                <h6>Appointment Details</h6>
                <p>This would show full appointment details, medical history, and allow quick actions.</p>
            </div>
        `);
    }, 500);
}

function startAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, 'in_progress');
}

function completeAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, 'completed');
}

function updateAppointmentStatus(appointmentId, status) {
    $('#status_appointment_id').val(appointmentId);
    $('#status_select').val(status);
    $('#statusModal').modal('show');
}

function rescheduleAppointment(appointmentId) {
    $('#reschedule_appointment_id').val(appointmentId);
    $('#rescheduleModal').modal('show');
}

function refreshAppointments() {
    location.reload();
}

function viewSchedule() {
    // This would open a calendar view
    alert('Calendar view would be implemented here');
}

function markAllComplete() {
    if (confirm('Mark all today\'s completed appointments as finished?')) {
        // This would make an AJAX call to update multiple appointments
        alert('Feature would be implemented to mark multiple appointments complete');
    }
}

function exportAppointments() {
    // This would export appointments to CSV/PDF
    alert('Export functionality would be implemented here');
}

// Real-time updates (if needed)
$(document).ready(function() {
    // Auto-refresh every 5 minutes for today's appointments
    if ($('#date_filter').val() === 'today') {
        setInterval(refreshAppointments, 300000); // 5 minutes
    }
    
    // Quick status update from cards
    $('.appointment-item').on('click', '.btn-outline-primary', function(e) {
        e.preventDefault();
        const appointmentId = $(this).closest('.appointment-item').find('[data-appointment-id]').data('appointment-id') || 
                             $(this).attr('onclick').match(/\d+/)[0];
        viewAppointmentDetails(appointmentId);
    });
});
</script>

<?php renderPageFooter(); ?>