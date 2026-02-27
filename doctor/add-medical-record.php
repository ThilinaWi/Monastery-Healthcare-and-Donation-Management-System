<?php
/**
 * Doctor - Add Medical Record
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

$page_title = 'Add Medical Record';
$currentPage = 'add-medical-record.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$doctorId = $currentUser['doctor_id'];

$error = '';
$success = '';

// Get appointment ID from URL if provided
$appointmentId = intval($_GET['appointment_id'] ?? 0);
$appointment = null;
$monk = null;

if ($appointmentId > 0) {
    try {
        // Validate appointment belongs to this doctor
        $appointment = $db->fetchOne("
            SELECT a.*, m.full_name as monk_name, m.monk_id, m.date_of_birth, m.phone
            FROM appointments a
            LEFT JOIN monks m ON a.monk_id = m.monk_id
            WHERE a.appointment_id = ? AND a.doctor_id = ?
        ", [$appointmentId, $doctorId]);
        
        if ($appointment) {
            $monk = [
                'monk_id' => $appointment['monk_id'],
                'full_name' => $appointment['monk_name'],
                'date_of_birth' => $appointment['date_of_birth'],
                'phone' => $appointment['phone']
            ];
        }
    } catch (Exception $e) {
        error_log("Appointment lookup error: " . $e->getMessage());
    }
}

// Handle medical record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_record'])) {
        try {
            $data = [
                'monk_id' => intval($_POST['monk_id'] ?? 0),
                'doctor_id' => $doctorId,
                'appointment_id' => !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null,
                'record_date' => $_POST['record_date'] ?? date('Y-m-d'),
                'record_type' => sanitize_input($_POST['record_type'] ?? 'consultation'),
                'symptoms' => sanitize_input($_POST['symptoms'] ?? ''),
                'examination_findings' => sanitize_input($_POST['examination_findings'] ?? ''),
                'diagnosis' => sanitize_input($_POST['diagnosis'] ?? ''),
                'treatment' => sanitize_input($_POST['treatment'] ?? ''),
                'follow_up_instructions' => sanitize_input($_POST['follow_up_instructions'] ?? ''),
                'notes' => sanitize_input($_POST['notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Validate required fields
            if ($data['monk_id'] <= 0) {
                throw new Exception('Please select a monk');
            }
            
            if (empty($data['record_date'])) {
                throw new Exception('Record date is required');
            }
            
            if (empty($data['symptoms']) && empty($data['examination_findings']) && empty($data['diagnosis'])) {
                throw new Exception('Please provide at least symptoms, examination findings, or diagnosis');
            }
            
            // Validate monk exists
            $monkExists = $db->fetchOne("SELECT monk_id FROM monks WHERE monk_id = ?", [$data['monk_id']]);
            if (!$monkExists) {
                throw new Exception('Selected monk does not exist');
            }
            
            // Handle prescriptions if provided
            $prescriptions = [];
            if (!empty($_POST['prescriptions'])) {
                foreach ($_POST['prescriptions'] as $index => $prescription) {
                    if (!empty($prescription['medication'])) {
                        $prescriptions[] = [
                            'medication' => sanitize_input($prescription['medication']),
                            'dosage' => sanitize_input($prescription['dosage'] ?? ''),
                            'frequency' => sanitize_input($prescription['frequency'] ?? ''),
                            'duration' => sanitize_input($prescription['duration'] ?? ''),
                            'instructions' => sanitize_input($prescription['instructions'] ?? '')
                        ];
                    }
                }
            }
            
            if (!empty($prescriptions)) {
                $data['prescriptions'] = json_encode($prescriptions);
            } else {
                $data['prescriptions'] = null;
            }
            
            // Insert medical record
            $recordId = $db->insert('medical_records', $data);
            
            // Update appointment status if linked
            if ($data['appointment_id']) {
                $db->update('appointments', [
                    'status' => 'completed',
                    'updated_at' => date('Y-m-d H:i:s')
                ], "appointment_id = ?", [$data['appointment_id']]);
            }
            
            // Log the record creation
            $db->insert('system_logs', [
                'user_type' => 'doctor',
                'user_id' => $doctorId,
                'action' => 'create',
                'table_affected' => 'medical_records',
                'record_id' => $recordId,
                'new_values' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = "Medical record added successfully for " . ($monk ? $monk['full_name'] : 'the selected monk') . ".";
            
            // Clear form data for next entry
            if (isset($_POST['add_another'])) {
                // Keep the same monk selected for next entry
                header("Location: add-medical-record.php?monk_id=" . $data['monk_id']);
                exit;
            }
            
        } catch (Exception $e) {
            $error = "Failed to add medical record: " . $e->getMessage();
        }
    }
}

// Get monks for selection
try {
    $monks = $db->fetchAll("
        SELECT m.*, 
               COUNT(DISTINCT a.appointment_id) as total_appointments,
               COUNT(DISTINCT mr.record_id) as total_records,
               MAX(mr.record_date) as last_record_date
        FROM monks m
        LEFT JOIN appointments a ON m.monk_id = a.monk_id AND a.doctor_id = ?
        LEFT JOIN medical_records mr ON m.monk_id = mr.monk_id AND mr.doctor_id = ?
        WHERE m.is_active = 1
        GROUP BY m.monk_id
        ORDER BY m.full_name ASC
    ", [$doctorId, $doctorId]);
    
    // Get recent appointments for context
    $recentAppointments = $db->fetchAll("
        SELECT a.*, m.full_name as monk_name
        FROM appointments a
        LEFT JOIN monks m ON a.monk_id = m.monk_id
        WHERE a.doctor_id = ? AND a.status IN ('scheduled', 'in_progress', 'completed')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ", [$doctorId]);
    
    // Get doctor's recent medical records for reference
    $recentRecords = $db->fetchAll("
        SELECT mr.*, m.full_name as monk_name
        FROM medical_records mr
        LEFT JOIN monks m ON mr.monk_id = m.monk_id
        WHERE mr.doctor_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 5
    ", [$doctorId]);
    
} catch (Exception $e) {
    error_log("Add medical record error: " . $e->getMessage());
    $monks = [];
    $recentAppointments = [];
    $recentRecords = [];
}

// Render page
renderPageHeader($page_title, 'doctor');
renderSidebar('doctor', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Add Medical Record</h1>
            <p class="mb-0 text-muted">Create detailed medical records for patient consultations</p>
        </div>
        <div class="btn-group">
            <a href="appointments.php" class="btn btn-outline-primary">
                <i class="fas fa-calendar-alt me-2"></i>My Appointments
            </a>
            <button class="btn btn-outline-info" onclick="loadTemplate()">
                <i class="fas fa-file-medical me-2"></i>Load Template
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
        <!-- Main Form -->
        <div class="col-lg-8">
            <!-- Patient Selection -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user me-2 text-primary"></i>
                        Patient Information
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($appointment): ?>
                        <!-- Pre-filled from appointment -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>
                                Appointment-Based Record
                            </h6>
                            <p class="mb-2">
                                Creating medical record for appointment on 
                                <strong><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                at <strong><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></strong>
                            </p>
                            <p class="mb-0">
                                Patient: <strong><?php echo htmlspecialchars($appointment['monk_name']); ?></strong> 
                                (ID: #<?php echo $appointment['monk_id']; ?>)
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Manual selection -->
                        <div class="row">
                            <?php if (!empty($monks)): ?>
                                <?php foreach (array_slice($monks, 0, 6) as $monk): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="monk-card card h-100 cursor-pointer" 
                                             data-monk-id="<?php echo $monk['monk_id']; ?>"
                                             onclick="selectMonk(<?php echo $monk['monk_id']; ?>, '<?php echo addslashes($monk['full_name']); ?>')">
                                            <div class="card-body text-center">
                                                <div class="avatar-circle mx-auto mb-3">
                                                    <?php echo strtoupper(substr($monk['full_name'], 0, 2)); ?>
                                                </div>
                                                <h6 class="card-title"><?php echo htmlspecialchars($monk['full_name']); ?></h6>
                                                <p class="text-muted small">ID: #<?php echo $monk['monk_id']; ?></p>
                                                
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <div class="small text-muted">Visits</div>
                                                        <div class="fw-bold text-primary"><?php echo $monk['total_appointments']; ?></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="small text-muted">Records</div>
                                                        <div class="fw-bold text-success"><?php echo $monk['total_records']; ?></div>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($monk['last_record_date']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            Last: <?php echo date('M d', strtotime($monk['last_record_date'])); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#monkSelectionModal">
                                <i class="fas fa-search me-2"></i>Search All Monks
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Medical Record Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-file-medical me-2 text-success"></i>
                        Medical Record Details
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="medicalRecordForm">
                        <!-- Hidden fields for appointment data -->
                        <?php if ($appointment): ?>
                            <input type="hidden" name="monk_id" value="<?php echo $appointment['monk_id']; ?>">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="monk_id" id="selected_monk_id" value="">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="record_date" class="form-label">Record Date *</label>
                                    <input type="date" class="form-control" id="record_date" name="record_date" 
                                           value="<?php echo $appointment ? $appointment['appointment_date'] : date('Y-m-d'); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="record_type" class="form-label">Record Type</label>
                                    <select class="form-select" id="record_type" name="record_type">
                                        <option value="consultation" <?php echo ($appointment && $appointment['appointment_type'] === 'consultation') ? 'selected' : ''; ?>>General Consultation</option>
                                        <option value="checkup" <?php echo ($appointment && $appointment['appointment_type'] === 'checkup') ? 'selected' : ''; ?>>Routine Check-up</option>
                                        <option value="follow_up" <?php echo ($appointment && $appointment['appointment_type'] === 'follow_up') ? 'selected' : ''; ?>>Follow-up Visit</option>
                                        <option value="urgent" <?php echo ($appointment && $appointment['appointment_type'] === 'urgent') ? 'selected' : ''; ?>>Urgent Care</option>
                                        <option value="specialist" <?php echo ($appointment && $appointment['appointment_type'] === 'specialist') ? 'selected' : ''; ?>>Specialist Consultation</option>
                                        <option value="procedure">Medical Procedure</option>
                                        <option value="vaccination">Vaccination</option>
                                        <option value="lab_results">Lab Results</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="symptoms" class="form-label">Patient Symptoms & Complaints</label>
                            <textarea class="form-control" id="symptoms" name="symptoms" rows="3"
                                      placeholder="Describe the patient's reported symptoms, complaints, and concerns..."><?php echo $appointment ? htmlspecialchars($appointment['symptoms']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="examination_findings" class="form-label">Physical Examination Findings</label>
                            <textarea class="form-control" id="examination_findings" name="examination_findings" rows="4"
                                      placeholder="Record vital signs, physical examination findings, and observations..."></textarea>
                            <div class="form-text">Include vital signs, physical observations, and examination results</div>
                        </div>

                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"
                                      placeholder="Primary and secondary diagnoses, differential diagnoses..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="treatment" class="form-label">Treatment Plan</label>
                            <textarea class="form-control" id="treatment" name="treatment" rows="3"
                                      placeholder="Describe the treatment plan, procedures performed, recommendations..."></textarea>
                        </div>

                        <!-- Prescriptions Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="fas fa-pills me-2 text-primary"></i>Prescriptions</h6>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPrescription()">
                                    <i class="fas fa-plus me-1"></i>Add Prescription
                                </button>
                            </div>
                            
                            <div id="prescriptions-container">
                                <!-- Prescription entries will be added here -->
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="follow_up_instructions" class="form-label">Follow-up Instructions</label>
                            <textarea class="form-control" id="follow_up_instructions" name="follow_up_instructions" rows="3"
                                      placeholder="Next appointment recommendations, monitoring instructions, lifestyle advice..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any additional observations, patient education provided, or other relevant information..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>Record Guidelines
                            </h6>
                            <ul class="mb-0 small">
                                <li>Provide detailed and accurate information for proper patient care</li>
                                <li>Include all relevant symptoms, findings, and treatments</li>
                                <li>Prescription information should include dosage, frequency, and duration</li>
                                <li>Follow-up instructions help ensure continuity of care</li>
                                <li>All records are confidential and secure</li>
                            </ul>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button type="submit" name="add_record" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Medical Record
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button type="submit" name="add_another" class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-plus me-2"></i>Save & Add Another
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i>Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Recent Appointments -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recent Appointments</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentAppointments)): ?>
                        <?php foreach (array_slice($recentAppointments, 0, 5) as $recent): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($recent['monk_name']); ?></div>
                                    <div class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($recent['appointment_date'])); ?> • 
                                        <?php echo date('g:i A', strtotime($recent['appointment_time'])); ?>
                                    </div>
                                    <span class="badge bg-<?php echo $recent['status'] === 'completed' ? 'success' : 'primary'; ?> small">
                                        <?php echo ucfirst($recent['status']); ?>
                                    </span>
                                </div>
                                <?php if ($recent['status'] !== 'completed'): ?>
                                    <a href="add-medical-record.php?appointment_id=<?php echo $recent['appointment_id']; ?>" 
                                       class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center">
                            <a href="appointments.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-calendar-check me-1"></i>View All
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-calendar fa-2x mb-2"></i>
                            <p class="mb-0">No recent appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Medical Records -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Recent Records</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentRecords)): ?>
                        <?php foreach ($recentRecords as $record): ?>
                            <div class="mb-3 pb-2 border-bottom">
                                <div class="fw-bold small"><?php echo htmlspecialchars($record['monk_name']); ?></div>
                                <div class="text-muted small"><?php echo date('M d, Y', strtotime($record['record_date'])); ?></div>
                                <div class="small"><?php echo ucfirst(str_replace('_', ' ', $record['record_type'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-clipboard fa-2x mb-2"></i>
                            <p class="mb-0">No records yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-lightning-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="loadTemplate('consultation')">
                            <i class="fas fa-clipboard me-2"></i>Consultation Template
                        </button>
                        <button class="btn btn-outline-info" onclick="loadTemplate('checkup')">
                            <i class="fas fa-heartbeat me-2"></i>Checkup Template
                        </button>
                        <button class="btn btn-outline-warning" onclick="loadTemplate('follow_up')">
                            <i class="fas fa-calendar-check me-2"></i>Follow-up Template
                        </button>
                        <button class="btn btn-outline-success" onclick="clearForm()">
                            <i class="fas fa-eraser me-2"></i>Clear Form
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monk Selection Modal -->
<div class="modal fade" id="monkSelectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Monk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="monkSearch" placeholder="Search monks by name or ID...">
                </div>
                <div class="row" id="monkSearchResults">
                    <?php foreach ($monks as $monk): ?>
                        <div class="col-md-6 mb-3 monk-search-item" data-name="<?php echo strtolower($monk['full_name']); ?>">
                            <div class="card cursor-pointer" onclick="selectMonkFromModal(<?php echo $monk['monk_id']; ?>, '<?php echo addslashes($monk['full_name']); ?>')">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php echo strtoupper(substr($monk['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($monk['full_name']); ?></h6>
                                            <small class="text-muted">ID: #<?php echo $monk['monk_id']; ?></small>
                                            <div class="small text-success">
                                                <?php echo $monk['total_records']; ?> records • 
                                                <?php echo $monk['total_appointments']; ?> visits
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.monk-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.monk-card:hover {
    border-color: #007bff;
    box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.15);
    transform: translateY(-2px);
}

.monk-card.selected {
    border-color: #28a745;
    background-color: #f8fff9;
}

.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

.prescription-entry {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.prescription-remove {
    position: absolute;
    top: 10px;
    right: 10px;
}

.cursor-pointer {
    cursor: pointer;
}
</style>

<script>
let prescriptionCount = 0;

function selectMonk(monkId, monkName) {
    // Update form
    $('#selected_monk_id').val(monkId);
    
    // Update visual selection
    $('.monk-card').removeClass('selected');
    $(`[data-monk-id="${monkId}"]`).addClass('selected');
    
    // Show success message
    showAlert('success', `Selected ${monkName} for medical record`);
    
    // Scroll to form
    $('#medicalRecordForm')[0].scrollIntoView({ behavior: 'smooth' });
}

function selectMonkFromModal(monkId, monkName) {
    selectMonk(monkId, monkName);
    $('#monkSelectionModal').modal('hide');
}

function addPrescription() {
    prescriptionCount++;
    const prescriptionHtml = `
        <div class="prescription-entry" id="prescription-${prescriptionCount}">
            <button type="button" class="btn btn-outline-danger btn-sm prescription-remove" 
                    onclick="removePrescription(${prescriptionCount})">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Medication Name *</label>
                        <input type="text" class="form-control" name="prescriptions[${prescriptionCount}][medication]" 
                               placeholder="Enter medication name" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Dosage</label>
                        <input type="text" class="form-control" name="prescriptions[${prescriptionCount}][dosage]" 
                               placeholder="e.g., 500mg, 2 tablets">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Frequency</label>
                        <select class="form-select" name="prescriptions[${prescriptionCount}][frequency]">
                            <option value="">Select frequency</option>
                            <option value="Once daily">Once daily</option>
                            <option value="Twice daily">Twice daily</option>
                            <option value="Three times daily">Three times daily</option>
                            <option value="Four times daily">Four times daily</option>
                            <option value="As needed">As needed</option>
                            <option value="Before meals">Before meals</option>
                            <option value="After meals">After meals</option>
                            <option value="At bedtime">At bedtime</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Duration</label>
                        <input type="text" class="form-control" name="prescriptions[${prescriptionCount}][duration]" 
                               placeholder="e.g., 7 days, 2 weeks">
                    </div>
                </div>
            </div>
            
            <div class="mb-0">
                <label class="form-label">Special Instructions</label>
                <textarea class="form-control" name="prescriptions[${prescriptionCount}][instructions]" rows="2" 
                          placeholder="Special instructions, warnings, or notes..."></textarea>
            </div>
        </div>
    `;
    
    $('#prescriptions-container').append(prescriptionHtml);
}

function removePrescription(id) {
    $(`#prescription-${id}`).remove();
}

function loadTemplate(type = '') {
    const templates = {
        'consultation': {
            examination_findings: 'Vital signs: BP ___/__ mmHg, HR ___ bpm, Temp ___°C, RR ___ /min\nGeneral appearance: \nCardiovascular: \nRespiratory: \nAbdominal: \nNeurological: ',
            follow_up_instructions: 'Return if symptoms worsen or persist\nDiet and lifestyle recommendations\nMedication compliance'
        },
        'checkup': {
            examination_findings: 'Routine physical examination\nVital signs within normal limits\nNo acute distress noted\nAll systems reviewed and normal',
            diagnosis: 'Routine health maintenance',
            follow_up_instructions: 'Continue current health practices\nNext routine checkup in 6-12 months'
        },
        'follow_up': {
            examination_findings: 'Follow-up examination\nProgress since last visit: \nCurrent symptoms: \nTreatment response: ',
            follow_up_instructions: 'Continue current treatment plan\nReturn visit in ___ weeks/months\nMonitor for any changes'
        }
    };
    
    if (type && templates[type]) {
        const template = templates[type];
        
        Object.keys(template).forEach(field => {
            $(`#${field}`).val(template[field]);
        });
        
        showAlert('info', `${type.replace('_', ' ')} template loaded`);
    } else {
        // Show template selection
        showAlert('info', 'Select a specific template type from the sidebar');
    }
}

function clearForm() {
    if (confirm('Are you sure you want to clear all form data?')) {
        $('#medicalRecordForm')[0].reset();
        $('#prescriptions-container').empty();
        prescriptionCount = 0;
        $('.monk-card').removeClass('selected');
        showAlert('success', 'Form cleared');
    }
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert-dismissible').remove();
    
    // Add new alert at top
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-dismiss after 3 seconds
    setTimeout(() => {
        $('.alert-dismissible').fadeOut();
    }, 3000);
}

// Form validation
function validateForm() {
    const monkId = $('#selected_monk_id').val();
    const recordDate = $('#record_date').val();
    const hasContent = $('#symptoms').val() || $('#examination_findings').val() || $('#diagnosis').val();
    
    let isValid = true;
    
    // Reset error states
    $('.is-invalid').removeClass('is-invalid');
    
    if (!monkId) {
        showAlert('warning', 'Please select a monk for this medical record');
        isValid = false;
    }
    
    if (!recordDate) {
        $('#record_date').addClass('is-invalid');
        isValid = false;
    }
    
    if (!hasContent) {
        $('#symptoms, #examination_findings, #diagnosis').addClass('is-invalid');
        showAlert('warning', 'Please provide at least symptoms, examination findings, or diagnosis');
        isValid = false;
    }
    
    return isValid;
}

// Monk search functionality
$('#monkSearch').on('input', function() {
    const searchTerm = $(this).val().toLowerCase();
    
    $('.monk-search-item').each(function() {
        const monkName = $(this).data('name');
        if (monkName.includes(searchTerm)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// Form submission
$('#medicalRecordForm').on('submit', function(e) {
    if (!validateForm()) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    const submitBtn = $('button[name="add_record"], button[name="add_another"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
    
    // Re-enable after 5 seconds (failsafe)
    setTimeout(() => {
        submitBtn.prop('disabled', false).html(originalText);
    }, 5000);
});

// Initialize with one prescription field if none exist
$(document).ready(function() {
    // Add initial prescription field
    addPrescription();
    
    // Auto-select monk if provided in URL
    const urlParams = new URLSearchParams(window.location.search);
    const monkId = urlParams.get('monk_id');
    if (monkId) {
        $(`[data-monk-id="${monkId}"]`).click();
    }
});
</script>

<?php renderPageFooter(); ?>