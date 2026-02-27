<?php
/**
 * Monk - Medical History Viewer
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

$page_title = 'Medical History';
$currentPage = 'medical-history.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$monkId = $currentUser['monk_id'];

// Filter parameters
$recordType = sanitize_input($_GET['type'] ?? '');
$dateFrom = sanitize_input($_GET['date_from'] ?? '');
$dateTo = sanitize_input($_GET['date_to'] ?? '');
$doctorId = intval($_GET['doctor_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query filters
$whereConditions = ["mr.monk_id = ?"];
$params = [$monkId];

if (!empty($recordType)) {
    $whereConditions[] = "mr.record_type = ?";
    $params[] = $recordType;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "mr.record_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "mr.record_date <= ?";
    $params[] = $dateTo;
}

if ($doctorId > 0) {
    $whereConditions[] = "mr.doctor_id = ?";
    $params[] = $doctorId;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get medical records with doctor information
    $medicalRecords = $db->fetchAll("
        SELECT mr.*, d.full_name as doctor_name, d.specialization,
               a.appointment_date, a.appointment_time
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
        LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
        WHERE {$whereClause}
        ORDER BY mr.record_date DESC, mr.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ", $params);
    
    // Get total count for pagination
    $totalRecords = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM medical_records mr
        WHERE {$whereClause}
    ", $params)['count'];
    
    $totalPages = ceil($totalRecords / $limit);
    
    // Get filter options
    $doctors = $db->fetchAll("
        SELECT DISTINCT d.doctor_id, d.full_name, d.specialization
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.monk_id = ?
        ORDER BY d.full_name ASC
    ", [$monkId]);
    
    $recordTypes = $db->fetchAll("
        SELECT DISTINCT record_type, COUNT(*) as count
        FROM medical_records
        WHERE monk_id = ?
        GROUP BY record_type
        ORDER BY record_type ASC
    ", [$monkId]);
    
    // Get health statistics
    $healthStats = [
        'total_records' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
        'total_visits' => $db->fetchOne("SELECT COUNT(DISTINCT record_date) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
        'unique_doctors' => $db->fetchOne("SELECT COUNT(DISTINCT doctor_id) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
        'last_visit' => $db->fetchOne("SELECT MAX(record_date) as date FROM medical_records WHERE monk_id = ?", [$monkId])['date'],
        'first_visit' => $db->fetchOne("SELECT MIN(record_date) as date FROM medical_records WHERE monk_id = ?", [$monkId])['date']
    ];
    
    // Get recent prescriptions
    $recentPrescriptions = $db->fetchAll("
        SELECT mr.record_id, mr.record_date, mr.prescriptions, d.full_name as doctor_name
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.monk_id = ? AND mr.prescriptions IS NOT NULL AND mr.prescriptions != ''
        ORDER BY mr.record_date DESC
        LIMIT 5
    ", [$monkId]);
    
    // Get common diagnoses
    $commonDiagnoses = $db->fetchAll("
        SELECT diagnosis, COUNT(*) as count
        FROM medical_records
        WHERE monk_id = ? AND diagnosis IS NOT NULL AND diagnosis != ''
        GROUP BY diagnosis
        ORDER BY count DESC, diagnosis ASC
        LIMIT 10
    ", [$monkId]);
    
} catch (Exception $e) {
    error_log("Medical history error: " . $e->getMessage());
    $medicalRecords = [];
    $totalRecords = 0;
    $totalPages = 0;
    $doctors = [];
    $recordTypes = [];
    $healthStats = ['total_records' => 0, 'total_visits' => 0, 'unique_doctors' => 0, 'last_visit' => null, 'first_visit' => null];
    $recentPrescriptions = [];
    $commonDiagnoses = [];
}

// Render page
renderPageHeader($page_title, 'monk');
renderSidebar('monk', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Medical History</h1>
            <p class="mb-0 text-muted">Your complete healthcare records and visit history</p>
        </div>
        <div class="btn-group">
            <a href="book-appointment.php" class="btn btn-outline-primary">
                <i class="fas fa-calendar-plus me-2"></i>Book Appointment
            </a>
            <a href="appointments.php" class="btn btn-outline-success">
                <i class="fas fa-calendar-check me-2"></i>My Appointments
            </a>
            <button class="btn btn-outline-info" onclick="printMedicalHistory()">
                <i class="fas fa-print me-2"></i>Print History
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Medical Records List -->
        <div class="col-lg-9">
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Records</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Record Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <?php foreach ($recordTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['record_type']); ?>" 
                                            <?php echo $recordType === $type['record_type'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type['record_type'])); ?> 
                                        (<?php echo $type['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="doctor_id" class="form-label">Doctor</label>
                            <select class="form-select" id="doctor_id" name="doctor_id">
                                <option value="">All Doctors</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['doctor_id']; ?>" 
                                            <?php echo $doctorId === $doctor['doctor_id'] ? 'selected' : ''; ?>>
                                        Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="medical-history.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Medical Records Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-file-medical me-2"></i>
                        Medical Records (<?php echo number_format($totalRecords); ?> records)
                    </h6>
                    <?php if ($totalPages > 1): ?>
                        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($medicalRecords)): ?>
                        <div class="medical-timeline">
                            <?php foreach ($medicalRecords as $index => $record): ?>
                                <div class="timeline-item" data-record-id="<?php echo $record['record_id']; ?>">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?php echo ucfirst(str_replace('_', ' ', $record['record_type'])); ?>
                                                        </h6>
                                                        <div class="text-muted small mb-2">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo date('F d, Y', strtotime($record['record_date'])); ?>
                                                            <?php if ($record['appointment_time']): ?>
                                                                <i class="fas fa-clock ms-3 me-1"></i>
                                                                <?php echo date('g:i A', strtotime($record['appointment_time'])); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-user-md me-1"></i>
                                                            Dr. <?php echo htmlspecialchars($record['doctor_name']); ?>
                                                            <?php if ($record['specialization']): ?>
                                                                <span class="badge bg-primary ms-2 small">
                                                                    <?php echo htmlspecialchars($record['specialization']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="toggleRecordDetails(<?php echo $record['record_id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                </div>
                                                
                                                <!-- Quick Summary -->
                                                <?php if ($record['diagnosis']): ?>
                                                    <div class="mb-2">
                                                        <strong class="text-danger">Diagnosis:</strong>
                                                        <span><?php echo htmlspecialchars($record['diagnosis']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['symptoms']): ?>
                                                    <div class="mb-2">
                                                        <strong class="text-info">Symptoms:</strong>
                                                        <span><?php echo htmlspecialchars(substr($record['symptoms'], 0, 100)) . (strlen($record['symptoms']) > 100 ? '...' : ''); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Detailed Information (Initially Hidden) -->
                                                <div id="record-details-<?php echo $record['record_id']; ?>" class="collapse">
                                                    <hr>
                                                    
                                                    <?php if ($record['symptoms']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-info"><i class="fas fa-stethoscope me-2"></i>Symptoms</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['symptoms'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['examination_findings']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-warning"><i class="fas fa-search me-2"></i>Examination Findings</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['examination_findings'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['diagnosis']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-danger"><i class="fas fa-diagnoses me-2"></i>Diagnosis</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['treatment']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-success"><i class="fas fa-procedures me-2"></i>Treatment</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['prescriptions']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-primary"><i class="fas fa-pills me-2"></i>Prescriptions</h6>
                                                            <div class="prescription-list">
                                                                <?php
                                                                $prescriptions = json_decode($record['prescriptions'], true);
                                                                if (is_array($prescriptions) && !empty($prescriptions)):
                                                                ?>
                                                                    <ul class="list-unstyled">
                                                                        <?php foreach ($prescriptions as $prescription): ?>
                                                                            <li class="mb-2 p-2 bg-light rounded">
                                                                                <strong><?php echo htmlspecialchars($prescription['medication'] ?? ''); ?></strong>
                                                                                <?php if (!empty($prescription['dosage'])): ?>
                                                                                    <br><small class="text-muted">Dosage: <?php echo htmlspecialchars($prescription['dosage']); ?></small>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($prescription['frequency'])): ?>
                                                                                    <br><small class="text-muted">Frequency: <?php echo htmlspecialchars($prescription['frequency']); ?></small>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($prescription['duration'])): ?>
                                                                                    <br><small class="text-muted">Duration: <?php echo htmlspecialchars($prescription['duration']); ?></small>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($prescription['instructions'])): ?>
                                                                                    <br><small class="text-info">Instructions: <?php echo htmlspecialchars($prescription['instructions']); ?></small>
                                                                                <?php endif; ?>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php else: ?>
                                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['prescriptions'])); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['follow_up_instructions']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-secondary"><i class="fas fa-calendar-check me-2"></i>Follow-up Instructions</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['follow_up_instructions'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['notes']): ?>
                                                        <div class="mb-3">
                                                            <h6 class="text-muted"><i class="fas fa-sticky-note me-2"></i>Additional Notes</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="row text-center mt-3 pt-3 border-top">
                                                        <div class="col-6">
                                                            <small class="text-muted">
                                                                <i class="fas fa-user-plus me-1"></i>
                                                                Created: <?php echo date('M d, Y g:i A', strtotime($record['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <?php if ($record['updated_at'] !== $record['created_at']): ?>
                                                            <div class="col-6">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-edit me-1"></i>
                                                                    Updated: <?php echo date('M d, Y g:i A', strtotime($record['updated_at'])); ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Medical records navigation" class="mt-4">
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
                            <i class="fas fa-file-medical fa-4x text-muted mb-4"></i>
                            <h5 class="text-muted">No medical records found</h5>
                            <p class="text-muted mb-4">
                                <?php if (!empty($recordType) || !empty($dateFrom) || !empty($dateTo) || $doctorId > 0): ?>
                                    No records match your current filters. Try adjusting your search criteria.
                                <?php else: ?>
                                    You haven't had any medical consultations yet. Book an appointment to start building your medical history.
                                <?php endif; ?>
                            </p>
                            <a href="book-appointment.php" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-2"></i>Book Your First Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Health Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Health Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $healthStats['total_records']; ?></h4>
                            <small class="text-muted">Total Records</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $healthStats['total_visits']; ?></h4>
                            <small class="text-muted">Unique Visits</small>
                        </div>
                    </div>
                    
                    <div class="text-center mb-3">
                        <h5 class="text-info"><?php echo $healthStats['unique_doctors']; ?></h5>
                        <small class="text-muted">Doctors Consulted</small>
                    </div>
                    
                    <?php if ($healthStats['first_visit']): ?>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar-plus me-1"></i>
                                First Visit: <?php echo date('M Y', strtotime($healthStats['first_visit'])); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($healthStats['last_visit']): ?>
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                <i class="fas fa-calendar-check me-1"></i>
                                Last Visit: <?php echo date('M d, Y', strtotime($healthStats['last_visit'])); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Prescriptions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-pills me-2"></i>Recent Prescriptions</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentPrescriptions)): ?>
                        <?php foreach ($recentPrescriptions as $prescription): ?>
                            <div class="mb-3 pb-2 border-bottom">
                                <div class="small fw-bold"><?php echo htmlspecialchars($prescription['doctor_name']); ?></div>
                                <div class="text-muted small"><?php echo date('M d, Y', strtotime($prescription['record_date'])); ?></div>
                                <div class="small mt-1">
                                    <?php
                                    $prescriptions = json_decode($prescription['prescriptions'], true);
                                    if (is_array($prescriptions) && !empty($prescriptions)) {
                                        echo htmlspecialchars($prescriptions[0]['medication'] ?? 'Medication prescribed');
                                        if (count($prescriptions) > 1) {
                                            echo ' <span class="text-muted">(+' . (count($prescriptions) - 1) . ' more)</span>';
                                        }
                                    } else {
                                        echo 'Prescription available';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center">
                            <small class="text-muted">View full records for complete prescription details</small>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-pills fa-2x mb-2"></i>
                            <p class="mb-0">No prescriptions yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Common Diagnoses -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-diagnoses me-2"></i>Common Conditions</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($commonDiagnoses)): ?>
                        <?php foreach ($commonDiagnoses as $diagnosis): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="small"><?php echo htmlspecialchars($diagnosis['diagnosis']); ?></div>
                                </div>
                                <div class="text-muted">
                                    <span class="badge bg-secondary"><?php echo $diagnosis['count']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-heartbeat fa-2x mb-2"></i>
                            <p class="mb-0">No diagnoses recorded</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    .btn, .navbar, .sidebar, .card-header button, .pagination { display: none !important; }
    .container-fluid { margin: 0; padding: 0; }
    .card { border: 1px solid #ddd !important; margin-bottom: 1rem; }
    .timeline-content { margin-left: 0 !important; }
    .timeline-marker { display: none !important; }
</style>

<style>
.medical-timeline {
    position: relative;
    padding-left: 30px;
}

.medical-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #007bff;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 15px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline-content {
    margin-left: 0;
}

.prescription-list ul {
    margin-bottom: 0;
}

.prescription-list li {
    border-left: 4px solid #007bff;
}

.medical-record-card {
    transition: all 0.3s ease;
}

.medical-record-card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.15);
}
</style>

<script>
function toggleRecordDetails(recordId) {
    const details = $(`#record-details-${recordId}`);
    const button = $(`[onclick="toggleRecordDetails(${recordId})"]`);
    
    details.toggleClass('show');
    
    if (details.hasClass('show')) {
        button.html('<i class="fas fa-eye-slash me-1"></i>Hide Details');
    } else {
        button.html('<i class="fas fa-eye me-1"></i>View Details');
    }
}

function printMedicalHistory() {
    // Show all collapsed details before printing
    $('.collapse').addClass('show');
    
    // Update page title for print
    const originalTitle = document.title;
    document.title = 'Medical History - <?php echo htmlspecialchars($currentUser['full_name']); ?>';
    
    // Print
    window.print();
    
    // Restore original title
    document.title = originalTitle;
    
    // Hide details after printing (optional)
    setTimeout(() => {
        $('.collapse').removeClass('show');
        // Reset button texts
        $('[onclick*="toggleRecordDetails"]').html('<i class="fas fa-eye me-1"></i>View Details');
    }, 1000);
}

// Auto-set date ranges
$(document).ready(function() {
    // Set max date to today
    const today = new Date().toISOString().split('T')[0];
    $('#date_from, #date_to').attr('max', today);
    
    // Quick date filters
    $('.card-header').append(`
        <div class="btn-group btn-group-sm ms-auto">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-clock me-1"></i>Quick Filters
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="setDateRange('last-month')">Last Month</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('last-3-months')">Last 3 Months</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('last-6-months')">Last 6 Months</a></li>
                <li><a class="dropdown-item" href="#" onclick="setDateRange('this-year')">This Year</a></li>
            </ul>
        </div>
    `);
});

function setDateRange(period) {
    const today = new Date();
    let startDate = new Date();
    
    switch(period) {
        case 'last-month':
            startDate.setMonth(today.getMonth() - 1);
            break;
        case 'last-3-months':
            startDate.setMonth(today.getMonth() - 3);
            break;
        case 'last-6-months':
            startDate.setMonth(today.getMonth() - 6);
            break;
        case 'this-year':
            startDate = new Date(today.getFullYear(), 0, 1);
            break;
    }
    
    $('#date_from').val(startDate.toISOString().split('T')[0]);
    $('#date_to').val(today.toISOString().split('T')[0]);
    
    // Auto-submit form
    $('form').submit();
}
</script>

<?php renderPageFooter(); ?>