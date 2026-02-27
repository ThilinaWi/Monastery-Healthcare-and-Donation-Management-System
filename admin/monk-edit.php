<?php
/**
 * Admin - Edit Monk
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

$page_title = 'Edit Monk';
$currentPage = 'monks.php';

// Get database connection
$db = Database::getInstance();

$error = '';
$success = '';
$monk = null;

// Get monk ID from URL
$monkId = intval($_GET['id'] ?? 0);

if (!$monkId) {
    $_SESSION['error'] = 'Invalid monk ID';
    header('Location: monks.php');
    exit;
}

// Load monk data
try {
    $monk = $db->fetchOne("
        SELECT m.*, r.room_number, r.room_type 
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get original data for comparison
        $originalData = $monk;
        
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
        
        // Check for duplicate username/email (excluding current monk)
        $existingUser = $db->fetchOne(
            "SELECT monk_id FROM monks WHERE (username = ? OR email = ?) AND monk_id != ?",
            [$data['username'], $data['email'], $monkId]
        );
        
        if ($existingUser) {
            $errors[] = 'Username or email already exists for another monk';
        }
        
        // Validate room assignment change
        $roomChanged = ($data['room_id'] != $monk['room_id']);
        
        if ($data['room_id']) {
            $room = $db->fetchOne("SELECT * FROM rooms WHERE room_id = ?", [$data['room_id']]);
            if (!$room) {
                $errors[] = 'Selected room does not exist';
            } elseif (!$room['is_available']) {
                $errors[] = 'Selected room is not available';
            } elseif ($roomChanged && $room['current_occupancy'] >= $room['capacity']) {
                $errors[] = 'Selected room is at full capacity';
            }
        }
        
        if (empty($errors)) {
            // Handle password update if provided
            if (!empty($_POST['new_password'])) {
                if (strlen($_POST['new_password']) < PASSWORD_MIN_LENGTH) {
                    $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
                } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                    $errors[] = 'Password confirmation does not match';
                } else {
                    $data['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                }
            }
        }
        
        if (empty($errors)) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Update monk record
                $updateData = $data;
                if (!isset($updateData['password'])) {
                    unset($updateData['password']); // Don't update password if not provided
                }
                
                $db->update('monks', $updateData, 'monk_id = ?', [$monkId]);
                
                // Handle room changes
                if ($roomChanged) {
                    // Decrease occupancy of old room
                    if ($monk['room_id']) {
                        $db->update('rooms', 
                            ['current_occupancy' => 'current_occupancy - 1'], 
                            'room_id = ?', 
                            [$monk['room_id']]
                        );
                    }
                    
                    // Increase occupancy of new room
                    if ($data['room_id']) {
                        $db->update('rooms', 
                            ['current_occupancy' => 'current_occupancy + 1'], 
                            'room_id = ?', 
                            [$data['room_id']]
                        );
                    }
                }
                
                // Log the action
                $db->insert('system_logs', [
                    'user_type' => 'admin',
                    'user_id' => getCurrentUserId(),
                    'action' => 'update',
                    'table_affected' => 'monks',
                    'record_id' => $monkId,
                    'old_values' => json_encode($originalData),
                    'new_values' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Commit transaction
                $db->commit();
                
                $_SESSION['success'] = "Monk '{$data['full_name']}' updated successfully!";
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
        error_log("Update monk error: " . $e->getMessage());
        $error = "Failed to update monk. Please try again.";
    }
} else {
    // Pre-populate form with existing data
    $_POST = $monk;
}

// Get available rooms (including current room if assigned)
try {
    $availableRooms = $db->fetchAll("
        SELECT room_id, room_number, room_type, capacity, current_occupancy
        FROM rooms 
        WHERE (is_available = 1 AND current_occupancy < capacity) OR room_id = ?
        ORDER BY room_number
    ", [$monk['room_id']]);
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
            <h1 class="h3 mb-0 text-gray-800">Edit Monk</h1>
            <p class="mb-0 text-muted">Update information for <?php echo htmlspecialchars($monk['full_name']); ?></p>
        </div>
        <div class="btn-group">
            <a href="monk-view.php?id=<?php echo $monkId; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-2"></i>View Details
            </a>
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
                    <h6 class="mb-0"><i class="fas fa-user-edit me-2"></i>Monk Information</h6>
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

                        <!-- Password Change Section -->
                        <div class="alert alert-light border">
                            <h6 class="mb-3">
                                <i class="fas fa-key me-2"></i>Password Management
                                <button type="button" class="btn btn-sm btn-outline-primary float-end" onclick="togglePasswordSection()">
                                    <i class="fas fa-lock me-1"></i>Change Password
                                </button>
                            </h6>
                            <div id="passwordSection" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                            <div class="form-text">Leave blank to keep current password</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
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
                                               <?php echo ($_POST['is_active'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active monk (can login and use system)
                                        </label>
                                    </div>
                                    <div>
                                        <button type="reset" class="btn btn-outline-secondary me-2">Reset Changes</button>
                                        <button type="submit" name="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Monk
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
                    <?php if ($monk['room_number']): ?>
                        <div class="alert alert-info">
                            <strong>Current Room:</strong><br>
                            Room <?php echo htmlspecialchars($monk['room_number']); ?> 
                            (<?php echo ucfirst($monk['room_type']); ?>)
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($availableRooms)): ?>
                        <div class="mb-3">
                            <label for="room_id" class="form-label">
                                <?php echo $monk['room_id'] ? 'Change Room' : 'Assign to Room'; ?>
                            </label>
                            <select class="form-select" id="room_id" name="room_id" form="monkForm">
                                <option value="">No room assignment</option>
                                <?php foreach ($availableRooms as $room): ?>
                                    <?php 
                                    $selected = (($_POST['room_id'] ?? $monk['room_id']) == $room['room_id']) ? 'selected' : '';
                                    $occupancy = $room['current_occupancy'] . '/' . $room['capacity'];
                                    $isCurrent = ($room['room_id'] == $monk['room_id']);
                                    ?>
                                    <option value="<?php echo $room['room_id']; ?>" <?php echo $selected; ?>>
                                        Room <?php echo htmlspecialchars($room['room_number']); ?> 
                                        (<?php echo ucfirst($room['room_type']); ?>) - <?php echo $occupancy; ?>
                                        <?php echo $isCurrent ? ' (Current)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <?php if ($monk['room_id']): ?>
                                    Changing rooms will update occupancy counts
                                <?php else: ?>
                                    Assign monk to an available room
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No rooms available for assignment at the moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stats = [
                            'appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ?", [$monkId])['count'],
                            'medical_records' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
                            'last_appointment' => $db->fetchOne("SELECT MAX(appointment_date) as date FROM appointments WHERE monk_id = ? AND status = 'completed'", [$monkId])['date'] ?? null
                        ];
                        ?>
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?php echo $stats['appointments']; ?></h4>
                                <small class="text-muted">Total Appointments</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?php echo $stats['medical_records']; ?></h4>
                                <small class="text-muted">Medical Records</small>
                            </div>
                        </div>
                        
                        <?php if ($stats['last_appointment']): ?>
                            <div class="text-center">
                                <small class="text-muted">
                                    Last appointment: <?php echo date('M d, Y', strtotime($stats['last_appointment'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                    <?php } catch (Exception $e) { ?>
                        <p class="text-muted">Unable to load statistics</p>
                    <?php } ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../doctor/patient-records.php?monk_id=<?php echo $monkId; ?>" class="btn btn-outline-success">
                            <i class="fas fa-file-medical me-2"></i>View Medical Records
                        </a>
                        <a href="monk-view.php?id=<?php echo $monkId; ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>View Full Profile
                        </a>
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="confirmDelete(<?php echo $monkId; ?>, '<?php echo addslashes($monk['full_name']); ?>')">
                            <i class="fas fa-trash me-2"></i>Delete Monk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation (same as add form)
    $('#monkForm').on('submit', function(e) {
        let isValid = true;
        const fullName = $('#full_name').val().trim();
        const username = $('#username').val().trim();
        const email = $('#email').val().trim();
        const age = $('#age').val();
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
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
        
        // Validate password if provided
        if (newPassword && newPassword !== confirmPassword) {
            $('#confirm_password').addClass('is-invalid');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 100
            }, 300);
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

// Toggle password section
function togglePasswordSection() {
    const section = document.getElementById('passwordSection');
    if (section.style.display === 'none') {
        section.style.display = 'block';
        document.getElementById('new_password').focus();
    } else {
        section.style.display = 'none';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
    }
}

// Delete confirmation
function confirmDelete(monkId, monkName) {
    if (confirm(`Are you sure you want to delete ${monkName}? This action cannot be undone and will also delete all associated medical records and appointments.`)) {
        window.location.href = `monk-delete.php?id=${monkId}`;
    }
}
</script>

<?php renderPageFooter(); ?>