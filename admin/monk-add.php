<?php
/**
 * Admin - Add New Monk
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

$page_title = 'Add New Monk';
$currentPage = 'monks.php';

// Get database connection
$db = Database::getInstance();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and validate form data
        $data = [
            'username' => sanitize_input($_POST['username'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? ''),
            'full_name' => sanitize_input($_POST['full_name'] ?? ''),
            'age' => intval($_POST['age'] ?? 0),
            'phone' => sanitize_input($_POST['phone'] ?? ''),
            'emergency_contact' => sanitize_input($_POST['emergency_contact'] ?? ''),
            'emergency_phone' => sanitize_input($_POST['emergency_phone'] ?? ''),
            'room_id' => !empty($_POST['room_id']) ? intval($_POST['room_id']) : null,
            'health_conditions' => sanitize_input($_POST['health_conditions'] ?? ''),
            'medications' => sanitize_input($_POST['medications'] ?? ''),
            'allergies' => sanitize_input($_POST['allergies'] ?? ''),
            'blood_type' => sanitize_input($_POST['blood_type'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'ordination_date' => $_POST['ordination_date'] ?? '',
            'bio' => sanitize_input($_POST['bio'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validation
        $errors = [];
        
        // Required field validations
        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($data['full_name'])) {
            $errors[] = 'Full name is required';
        }
        
        if ($data['age'] < 1 || $data['age'] > 120) {
            $errors[] = 'Please enter a valid age (1-120)';
        }
        
        if (!empty($data['phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,15}$/', $data['phone'])) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        if (!empty($data['emergency_phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,15}$/', $data['emergency_phone'])) {
            $errors[] = 'Please enter a valid emergency phone number';
        }
        
        if (!empty($data['date_of_birth'])) {
            $birthDate = new DateTime($data['date_of_birth']);
            $today = new DateTime();
            if ($birthDate > $today) {
                $errors[] = 'Birth date cannot be in the future';
            }
        }
        
        if (!empty($data['ordination_date'])) {
            $ordinationDate = new DateTime($data['ordination_date']);
            $today = new DateTime();
            if ($ordinationDate > $today) {
                $errors[] = 'Ordination date cannot be in the future';
            }
        }
        
        // Check for duplicate username/email
        $existingUser = $db->fetchOne(
            "SELECT monk_id FROM monks WHERE username = ? OR email = ?",
            [$data['username'], $data['email']]
        );
        
        if ($existingUser) {
            $errors[] = 'Username or email already exists';
        }
        
        // Validate room assignment
        if ($data['room_id']) {
            $room = $db->fetchOne("SELECT * FROM rooms WHERE room_id = ?", [$data['room_id']]);
            if (!$room) {
                $errors[] = 'Selected room does not exist';
            } elseif (!$room['is_available']) {
                $errors[] = 'Selected room is not available';
            } elseif ($room['current_occupancy'] >= $room['capacity']) {
                $errors[] = 'Selected room is at full capacity';
            }
        }
        
        if (empty($errors)) {
            // Generate a secure temporary password
            $tempPassword = 'Monk' . rand(1000, 9999) . '!';
            $data['password'] = password_hash($tempPassword, PASSWORD_DEFAULT);
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Insert monk
                $monkId = $db->insert('monks', $data);
                
                // Update room occupancy if room assigned
                if ($data['room_id']) {
                    $db->update('rooms', 
                        ['current_occupancy' => 'current_occupancy + 1'], 
                        'room_id = ?', 
                        [$data['room_id']]
                    );
                }
                
                // Log the action
                $db->insert('system_logs', [
                    'user_type' => 'admin',
                    'user_id' => getCurrentUserId(),
                    'action' => 'create',
                    'table_affected' => 'monks',
                    'record_id' => $monkId,
                    'new_values' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Commit transaction
                $db->commit();
                
                $_SESSION['success'] = "Monk '{$data['full_name']}' added successfully! Temporary password: <strong>$tempPassword</strong> (Please provide this to the monk securely)";
                header('Location: monks.php');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        } else {
            $error = implode('<br>', $errors);
        }
        
    } catch (Exception $e) {
        error_log("Add monk error: " . $e->getMessage());
        $error = "Failed to add monk. Please try again.";
    }
}

// Get available rooms
try {
    $availableRooms = $db->fetchAll("
        SELECT room_id, room_number, room_type, capacity, current_occupancy
        FROM rooms 
        WHERE is_available = 1 AND current_occupancy < capacity
        ORDER BY room_number
    ");
} catch (Exception $e) {
    $availableRooms = [];
}

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Add New Monk</h1>
            <p class="mb-0 text-muted">Register a new monk in the monastery system</p>
        </div>
        <div>
            <a href="monks.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Monks
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($error): ?>
        <?php renderAlert('danger', $error); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user-plus me-2"></i>Monk Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="monkForm">
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                    <div class="form-text">Used for login (letters, numbers, underscores only)</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Personal Details -->
                        <h6 class="mb-3 mt-4">Personal Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="age" name="age" min="1" max="120"
                                           value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="blood_type" class="form-label">Blood Type</label>
                                    <select class="form-select" id="blood_type" name="blood_type">
                                        <option value="">Select Blood Type</option>
                                        <?php
                                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        foreach ($bloodTypes as $type) {
                                            $selected = (($_POST['blood_type'] ?? '') === $type) ? 'selected' : '';
                                            echo "<option value=\"$type\" $selected>$type</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ordination_date" class="form-label">Ordination Date</label>
                                    <input type="date" class="form-control" id="ordination_date" name="ordination_date" 
                                           value="<?php echo htmlspecialchars($_POST['ordination_date'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <h6 class="mb-3 mt-4">Emergency Contact</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact" class="form-label">Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Health Information -->
                        <h6 class="mb-3 mt-4">Health Information</h6>
                        <div class="mb-3">
                            <label for="health_conditions" class="form-label">Current Health Conditions</label>
                            <textarea class="form-control" id="health_conditions" name="health_conditions" rows="3"
                                      placeholder="Any ongoing health conditions or concerns..."><?php echo htmlspecialchars($_POST['health_conditions'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="medications" class="form-label">Current Medications</label>
                                    <textarea class="form-control" id="medications" name="medications" rows="3"
                                              placeholder="List any medications currently taking..."><?php echo htmlspecialchars($_POST['medications'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="allergies" class="form-label">Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="3"
                                              placeholder="Food allergies, drug allergies, etc..."><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Biography -->
                        <h6 class="mb-3 mt-4">Additional Information</h6>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Biography/Notes</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"
                                      placeholder="Background, interests, roles within the monastery..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                        </div>

                        <!-- Form Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo (isset($_POST['is_active']) || !isset($_POST['submit'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active monk (can login and use system)
                                        </label>
                                    </div>
                                    <div>
                                        <button type="reset" class="btn btn-outline-secondary me-2">Reset Form</button>
                                        <button type="submit" name="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Add Monk
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Room Assignment -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bed me-2"></i>Room Assignment</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($availableRooms)): ?>
                        <div class="mb-3">
                            <label for="room_id" class="form-label">Assign to Room</label>
                            <select class="form-select" id="room_id" name="room_id" form="monkForm">
                                <option value="">No room assignment</option>
                                <?php foreach ($availableRooms as $room): ?>
                                    <?php 
                                    $selected = (($_POST['room_id'] ?? '') == $room['room_id']) ? 'selected' : '';
                                    $occupancy = $room['current_occupancy'] . '/' . $room['capacity'];
                                    ?>
                                    <option value="<?php echo $room['room_id']; ?>" <?php echo $selected; ?>>
                                        Room <?php echo htmlspecialchars($room['room_number']); ?> 
                                        (<?php echo ucfirst($room['room_type']); ?>) - <?php echo $occupancy; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Room can be assigned later if preferred</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No available rooms at the moment. Rooms can be assigned later.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Help Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h6>
                </div>
                <div class="card-body">
                    <h6>Password Generation</h6>
                    <p class="small text-muted mb-3">
                        A temporary password will be automatically generated for the new monk. 
                        Please provide this password securely to the monk after creation.
                    </p>
                    
                    <h6>Required Fields</h6>
                    <p class="small text-muted mb-3">
                        Fields marked with <span class="text-danger">*</span> are required. 
                        Additional information can be updated later.
                    </p>
                    
                    <h6>Room Assignment</h6>
                    <p class="small text-muted mb-0">
                        Monks can be assigned to rooms during creation or later through the edit function. 
                        Only rooms with available capacity are shown.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $('#monkForm').on('submit', function(e) {
        let isValid = true;
        const fullName = $('#full_name').val().trim();
        const username = $('#username').val().trim();
        const email = $('#email').val().trim();
        const age = $('#age').val();
        
        // Reset previous error states
        $('.is-invalid').removeClass('is-invalid');
        
        // Validate required fields
        if (!fullName) {
            $('#full_name').addClass('is-invalid');
            isValid = false;
        }
        
        if (!username || username.length < 3) {
            $('#username').addClass('is-invalid');
            isValid = false;
        }
        
        if (!email || !isValidEmail(email)) {
            $('#email').addClass('is-invalid');
            isValid = false;
        }
        
        if (!age || age < 1 || age > 120) {
            $('#age').addClass('is-invalid');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 100
            }, 300);
        }
    });
    
    // Auto-suggest username from full name
    $('#full_name').on('input', function() {
        if (!$('#username').val()) {
            const fullName = $(this).val();
            const username = fullName.toLowerCase()
                                   .replace(/[^a-z0-9\s]/g, '')
                                   .replace(/\s+/g, '_')
                                   .substring(0, 20);
            $('#username').val(username);
        }
    });
    
    // Age calculation from birth date
    $('#date_of_birth').on('change', function() {
        const birthDate = new Date($(this).val());
        if (birthDate) {
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age >= 0 && age <= 120) {
                $('#age').val(age);
            }
        }
    });
    
    // Email validation helper
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php renderPageFooter(); ?>