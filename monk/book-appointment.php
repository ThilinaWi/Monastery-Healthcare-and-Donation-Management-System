<?php
/**
 * Monk - Book Appointment
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

$page_title = 'Book Appointment';
$currentPage = 'book-appointment.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$monkId = $currentUser['monk_id'];

$error = '';
$success = '';

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['book_appointment'])) {
        try {
            $data = [
                'monk_id' => $monkId,
                'doctor_id' => intval($_POST['doctor_id'] ?? 0),
                'appointment_date' => $_POST['appointment_date'] ?? '',
                'appointment_time' => $_POST['appointment_time'] ?? '',
                'appointment_type' => sanitize_input($_POST['appointment_type'] ?? 'consultation'),
                'symptoms' => sanitize_input($_POST['symptoms'] ?? ''),
                'notes' => sanitize_input($_POST['notes'] ?? ''),
                'priority_level' => sanitize_input($_POST['priority_level'] ?? 'normal'),
                'status' => 'scheduled',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Validate required fields
            if ($data['doctor_id'] <= 0) {
                throw new Exception('Please select a doctor');
            }
            
            if (empty($data['appointment_date'])) {
                throw new Exception('Please select an appointment date');
            }
            
            if (empty($data['appointment_time'])) {
                throw new Exception('Please select an appointment time');
            }
            
            // Validate date is not in the past
            $appointmentDateTime = new DateTime($data['appointment_date'] . ' ' . $data['appointment_time']);
            $now = new DateTime();
            
            if ($appointmentDateTime <= $now) {
                throw new Exception('Appointment date and time must be in the future');
            }
            
            // Check if doctor exists and is active
            $doctor = $db->fetchOne("SELECT * FROM doctors WHERE doctor_id = ? AND is_active = 1", [$data['doctor_id']]);
            if (!$doctor) {
                throw new Exception('Selected doctor is not available');
            }
            
            // Check for conflicting appointments (same doctor, same time)
            $conflict = $db->fetchOne("
                SELECT COUNT(*) as count FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status = 'scheduled'
            ", [$data['doctor_id'], $data['appointment_date'], $data['appointment_time']]);
            
            if ($conflict['count'] > 0) {
                throw new Exception('This time slot is already booked. Please select a different time.');
            }
            
            // Check if monk has more than 3 pending appointments
            $pending = $db->fetchOne("
                SELECT COUNT(*) as count FROM appointments 
                WHERE monk_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()
            ", [$monkId]);
            
            if ($pending['count'] >= 3) {
                throw new Exception('You can only have 3 pending appointments at a time. Please wait for current appointments to be completed.');
            }
            
            // Insert appointment
            $appointmentId = $db->insert('appointments', $data);
            
            // Log the appointment booking
            $db->insert('system_logs', [
                'user_type' => 'monk',
                'user_id' => $monkId,
                'action' => 'create',
                'table_affected' => 'appointments',
                'record_id' => $appointmentId,
                'new_values' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = "Your appointment with Dr. {$doctor['full_name']} has been scheduled for " . 
                      date('F d, Y', strtotime($data['appointment_date'])) . " at " . 
                      date('g:i A', strtotime($data['appointment_time'])) . ".";
            
        } catch (Exception $e) {
            $error = "Failed to book appointment: " . $e->getMessage();
        }
    }
}

// Get available doctors with their specializations
try {
    $doctors = $db->fetchAll("
        SELECT d.*, 
               COUNT(DISTINCT a.appointment_id) as total_appointments,
               COUNT(DISTINCT mr.record_id) as total_records
        FROM doctors d
        LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
        LEFT JOIN medical_records mr ON d.doctor_id = mr.doctor_id
        WHERE d.is_active = 1
        GROUP BY d.doctor_id
        ORDER BY d.full_name ASC
    ");
    
    // Get monk's appointment history summary
    $appointmentStats = [
        'total_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ?", [$monkId])['count'],
        'completed_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ? AND status = 'completed'", [$monkId])['count'],
        'pending_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ? AND status = 'scheduled' AND appointment_date >= CURDATE()", [$monkId])['count'],
        'last_appointment' => $db->fetchOne("SELECT MAX(appointment_date) as date FROM appointments WHERE monk_id = ? AND status = 'completed'", [$monkId])['date']
    ];
    
    // Get upcoming appointments
    $upcomingAppointments = $db->fetchAll("
        SELECT a.*, d.full_name as doctor_name, d.specialization
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.monk_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ", [$monkId]);
    
    // Get recent medical records for context
    $recentRecords = $db->fetchAll("
        SELECT mr.*, d.full_name as doctor_name
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
        WHERE mr.monk_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 3
    ", [$monkId]);
    
} catch (Exception $e) {
    error_log("Book appointment error: " . $e->getMessage());
    $doctors = [];
    $appointmentStats = ['total_appointments' => 0, 'completed_appointments' => 0, 'pending_appointments' => 0, 'last_appointment' => null];
    $upcomingAppointments = [];
    $recentRecords = [];
}

// Render page
renderPageHeader($page_title, 'monk');
renderSidebar('monk', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Book Medical Appointment</h1>
            <p class="mb-0 text-muted">Schedule a consultation with our healthcare providers</p>
        </div>
        <div class="btn-group">
            <a href="appointments.php" class="btn btn-outline-primary">
                <i class="fas fa-calendar-check me-2"></i>My Appointments
            </a>
            <a href="medical-history.php" class="btn btn-outline-success">
                <i class="fas fa-file-medical me-2"></i>Medical History
            </a>
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
        <!-- Main Booking Form -->
        <div class="col-lg-8">
            <!-- Available Doctors -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-md me-2 text-primary"></i>
                        Available Doctors
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($doctors)): ?>
                        <div class="row">
                            <?php foreach ($doctors as $doctor): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="doctor-card card h-100 cursor-pointer" 
                                         data-doctor-id="<?php echo $doctor['doctor_id']; ?>"
                                         onclick="selectDoctor(<?php echo $doctor['doctor_id']; ?>, '<?php echo addslashes($doctor['full_name']); ?>')">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="avatar-circle me-3">
                                                    <?php echo strtoupper(substr($doctor['full_name'], 0, 2)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($doctor['full_name']); ?></h6>
                                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                                    <?php if ($doctor['qualifications']): ?>
                                                        <span class="badge bg-primary small"><?php echo htmlspecialchars($doctor['qualifications']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <div class="small text-muted">Experience</div>
                                                    <div class="fw-bold">
                                                        <?php echo $doctor['experience_years'] > 0 ? $doctor['experience_years'] . ' years' : 'N/A'; ?>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="small text-muted">Patients Seen</div>
                                                    <div class="fw-bold text-success">
                                                        <?php echo $doctor['total_appointments']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($doctor['phone']): ?>
                                                <div class="mt-3 pt-3 border-top">
                                                    <small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?php echo htmlspecialchars($doctor['phone']); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-md-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No doctors available</h5>
                            <p class="text-muted">Please contact the administration for assistance.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-plus me-2 text-success"></i>
                        Appointment Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="appointmentForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="doctor_id" class="form-label">Select Doctor *</label>
                                    <select class="form-select" id="doctor_id" name="doctor_id" required>
                                        <option value="">Choose a doctor</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['doctor_id']; ?>">
                                                Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> 
                                                (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_type" class="form-label">Appointment Type</label>
                                    <select class="form-select" id="appointment_type" name="appointment_type">
                                        <option value="consultation">General Consultation</option>
                                        <option value="checkup">Routine Check-up</option>
                                        <option value="follow_up">Follow-up Visit</option>
                                        <option value="urgent">Urgent Care</option>
                                        <option value="specialist">Specialist Consultation</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_date" class="form-label">Preferred Date *</label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                           max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_time" class="form-label">Preferred Time *</label>
                                    <select class="form-select" id="appointment_time" name="appointment_time" required>
                                        <option value="">Select time</option>
                                        <?php
                                        // Generate time slots from 8 AM to 5 PM
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

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority_level" class="form-label">Priority Level</label>
                                    <select class="form-select" id="priority_level" name="priority_level">
                                        <option value="normal">Normal</option>
                                        <option value="high">High Priority</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <div class="form-text">Select 'High' or 'Urgent' only for serious health concerns</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="symptoms" class="form-label">Symptoms or Health Concerns</label>
                            <textarea class="form-control" id="symptoms" name="symptoms" rows="4"
                                      placeholder="Please describe your symptoms, health concerns, or reason for the appointment..."></textarea>
                            <div class="form-text">This helps the doctor prepare for your visit</div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any additional information, allergies, or special requirements..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>Appointment Guidelines
                            </h6>
                            <ul class="mb-0 small">
                                <li>Appointments must be scheduled at least 24 hours in advance</li>
                                <li>You can have maximum 3 pending appointments at a time</li>
                                <li>Please arrive 15 minutes early for your appointment</li>
                                <li>For urgent medical issues, contact the monastery immediately</li>
                                <li>Cancellations must be made at least 2 hours before the appointment</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="book_appointment" class="btn btn-success btn-lg">
                                <i class="fas fa-calendar-check me-2"></i>Book Appointment
                            </button>
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
            <!-- Appointment Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Your Health Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $appointmentStats['total_appointments']; ?></h4>
                            <small class="text-muted">Total Appointments</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $appointmentStats['completed_appointments']; ?></h4>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <h5 class="text-warning mb-1"><?php echo $appointmentStats['pending_appointments']; ?></h5>
                        <small class="text-muted">Pending Appointments</small>
                    </div>
                    
                    <?php if ($appointmentStats['last_appointment']): ?>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Last visit: <?php echo date('M d, Y', strtotime($appointmentStats['last_appointment'])); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcomingAppointments)): ?>
                        <?php foreach (array_slice($upcomingAppointments, 0, 3) as $appointment): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?> • 
                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                </small>
                                <div><span class="badge bg-primary small"><?php echo ucfirst($appointment['appointment_type']); ?></span></div>
                            </div>
                            <div>
                                <a href="appointments.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
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
                            <p class="mb-0">No upcoming appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Medical Records -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Recent Medical Records</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentRecords)): ?>
                        <?php foreach ($recentRecords as $record): ?>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-bold small"><?php echo htmlspecialchars($record['doctor_name']); ?></div>
                                    <div class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($record['record_date'])); ?>
                                    </div>
                                    <div class="small"><?php echo ucfirst($record['record_type']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center">
                            <a href="medical-history.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-medical me-1"></i>View All Records
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-clipboard fa-2x mb-2"></i>
                            <p class="mb-0">No medical records yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.doctor-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.doctor-card:hover {
    border-color: #007bff;
    box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.15);
    transform: translateY(-2px);
}

.doctor-card.selected {
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

.cursor-pointer {
    cursor: pointer;
}
</style>

<script>
// Select doctor function
function selectDoctor(doctorId, doctorName) {
    // Update form
    $('#doctor_id').val(doctorId);
    
    // Update visual selection
    $('.doctor-card').removeClass('selected');
    $(`[data-doctor-id="${doctorId}"]`).addClass('selected');
    
    // Smooth scroll to form
    $('#appointmentForm')[0].scrollIntoView({ behavior: 'smooth' });
    
    // Focus on date field
    setTimeout(() => {
        $('#appointment_date').focus();
    }, 500);
}

// Check time slot availability
async function checkTimeAvailability() {
    const doctorId = $('#doctor_id').val();
    const date = $('#appointment_date').val();
    const time = $('#appointment_time').val();
    
    if (doctorId && date && time) {
        // This would make an AJAX call to check availability
        // For now, we'll just validate the basic requirements
        const appointmentDateTime = new Date(date + 'T' + time);
        const now = new Date();
        
        if (appointmentDateTime <= now) {
            alert('Please select a future date and time');
            return false;
        }
    }
    return true;
}

// Form validation
function validateForm() {
    const doctorId = $('#doctor_id').val();
    const date = $('#appointment_date').val();
    const time = $('#appointment_time').val();
    
    let isValid = true;
    
    // Reset error states
    $('.is-invalid').removeClass('is-invalid');
    
    if (!doctorId) {
        $('#doctor_id').addClass('is-invalid');
        isValid = false;
    }
    
    if (!date) {
        $('#appointment_date').addClass('is-invalid');
        isValid = false;
    }
    
    if (!time) {
        $('#appointment_time').addClass('is-invalid');
        isValid = false;
    }
    
    // Check date/time validity
    if (date && time) {
        const appointmentDateTime = new Date(date + 'T' + time);
        const now = new Date();
        
        if (appointmentDateTime <= now) {
            $('#appointment_date, #appointment_time').addClass('is-invalid');
            isValid = false;
        }
    }
    
    return isValid;
}

// Form submission
$('#appointmentForm').on('submit', async function(e) {
    if (!validateForm() || !(await checkTimeAvailability())) {
        e.preventDefault();
        
        // Scroll to first error
        const firstError = $('.is-invalid').first();
        if (firstError.length) {
            firstError[0].scrollIntoView({ behavior: 'smooth' });
            firstError.focus();
        }
        
        return false;
    }
    
    // Show loading state
    const submitBtn = $('button[name="book_appointment"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Booking...');
    
    // Re-enable after 5 seconds (failsafe)
    setTimeout(() => {
        submitBtn.prop('disabled', false).html(originalText);
    }, 5000);
});

// Real-time validation
$('#doctor_id, #appointment_date, #appointment_time').on('change', validateForm);

// Disable past dates
$(document).ready(function() {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const minDate = tomorrow.toISOString().split('T')[0];
    $('#appointment_date').attr('min', minDate);
    
    // Auto-select first doctor if only one exists
    const doctors = $('.doctor-card');
    if (doctors.length === 1) {
        const firstDoctor = doctors.first();
        const doctorId = firstDoctor.data('doctor-id');
        selectDoctor(doctorId, '');
    }
});
</script>

<?php renderPageFooter(); ?>