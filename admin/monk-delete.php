<?php
/**
 * Admin - Delete Monk
 * Monastery Healthcare and Donation Management System
 */

define('INCLUDED', true);
session_start();

// Include required files
require_once '../includes/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/session_check.php';

// Check admin access
requireRole('admin');

// Get database connection
$db = Database::getInstance();

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
        SELECT m.*, r.room_number 
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

// Handle POST request (actual deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'yes') {
        $_SESSION['error'] = 'Deletion not confirmed';
        header('Location: monk-view.php?id=' . $monkId);
        exit;
    }

    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get counts for logging
        $counts = [
            'appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ?", [$monkId])['count'],
            'medical_records' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
            'donations' => $db->fetchOne("SELECT COUNT(*) as count FROM donations WHERE donor_id = ? AND donor_type = 'monk'", [$monkId])['count'] ?? 0
        ];
        
        // Store monk data for logging
        $monkData = [
            'monk_id' => $monk['monk_id'],
            'username' => $monk['username'],
            'email' => $monk['email'],
            'full_name' => $monk['full_name'],
            'room_id' => $monk['room_id'],
            'room_number' => $monk['room_number']
        ];
        
        // 1. Delete related records in correct order (foreign key constraints)
        
        // Delete medical records (this will cascade to medical_record_files if exists)
        if ($counts['medical_records'] > 0) {
            $db->execute("DELETE FROM medical_records WHERE monk_id = ?", [$monkId]);
        }
        
        // Delete appointments
        if ($counts['appointments'] > 0) {
            $db->execute("DELETE FROM appointments WHERE monk_id = ?", [$monkId]);
        }
        
        // Update donations to remove monk reference (set donor_id to null)
        if ($counts['donations'] > 0) {
            $db->execute("UPDATE donations SET donor_id = NULL WHERE donor_id = ? AND donor_type = 'monk'", [$monkId]);
        }
        
        // Delete any system logs related to this monk (optional - for cleanup)
        $db->execute("DELETE FROM system_logs WHERE table_affected = 'monks' AND record_id = ?", [$monkId]);
        
        // 2. Update room occupancy if monk had a room
        if ($monk['room_id']) {
            $db->execute("UPDATE rooms SET current_occupancy = current_occupancy - 1 WHERE room_id = ?", [$monk['room_id']]);
        }
        
        // 3. Delete the monk record
        $db->execute("DELETE FROM monks WHERE monk_id = ?", [$monkId]);
        
        // 4. Log the deletion action
        $db->insert('system_logs', [
            'user_type' => 'admin',
            'user_id' => getCurrentUserId(),
            'action' => 'delete',
            'table_affected' => 'monks',
            'record_id' => $monkId,
            'old_values' => json_encode([
                'monk_data' => $monkData,
                'cascade_deletions' => $counts
            ]),
            'new_values' => json_encode(['deleted' => true]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Commit transaction
        $db->commit();
        
        // Create success message with details
        $message = "Monk '{$monk['full_name']}' has been successfully deleted.";
        if ($counts['appointments'] > 0 || $counts['medical_records'] > 0) {
            $message .= "\n\nThe following related records were also removed:";
            if ($counts['appointments'] > 0) {
                $message .= "\n• {$counts['appointments']} appointment(s)";
            }
            if ($counts['medical_records'] > 0) {
                $message .= "\n• {$counts['medical_records']} medical record(s)";
            }
            if ($counts['donations'] > 0) {
                $message .= "\n• {$counts['donations']} donation record(s) updated";
            }
        }
        if ($monk['room_number']) {
            $message .= "\n• Room {$monk['room_number']} has been made available";
        }
        
        $_SESSION['success'] = $message;
        header('Location: monks.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Delete monk error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete monk. Error: " . $e->getMessage();
        header('Location: monk-view.php?id=' . $monkId);
        exit;
    }
}

// If GET request, show confirmation page
require_once '../includes/layout.php';
$page_title = 'Delete Monk';
$currentPage = 'monks.php';

// Get related record counts for confirmation
try {
    $relatedCounts = [
        'appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE monk_id = ?", [$monkId])['count'],
        'medical_records' => $db->fetchOne("SELECT COUNT(*) as count FROM medical_records WHERE monk_id = ?", [$monkId])['count'],
        'donations' => $db->fetchOne("SELECT COUNT(*) as count FROM donations WHERE donor_id = ? AND donor_type = 'monk'", [$monkId])['count'] ?? 0
    ];
} catch (Exception $e) {
    $relatedCounts = ['appointments' => 0, 'medical_records' => 0, 'donations' => 0];
}

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Delete Monk</h1>
            <p class="mb-0 text-muted">Permanently remove monk from the system</p>
        </div>
        <div class="btn-group">
            <a href="monk-view.php?id=<?php echo $monkId; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Profile
            </a>
            <a href="monks.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-2"></i>All Monks
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Warning Card -->
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Monk Deletion
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Monk Information -->
                    <div class="alert alert-light border-start border-4 border-danger">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <div class="avatar-circle mx-auto mb-2">
                                    <?php echo strtoupper(substr($monk['full_name'], 0, 2)); ?>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <h5 class="mb-2"><?php echo htmlspecialchars($monk['full_name']); ?></h5>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($monk['username']); ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($monk['email']); ?></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong>Age:</strong> <?php echo $monk['age']; ?> years</p>
                                        <?php if ($monk['room_number']): ?>
                                        <p class="mb-1"><strong>Room:</strong> <?php echo htmlspecialchars($monk['room_number']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Message -->
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            This action cannot be undone!
                        </h6>
                        <p class="mb-0">
                            You are about to permanently delete this monk from the system. 
                            This will also affect related records as shown below.
                        </p>
                    </div>

                    <!-- Impact Analysis -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Records that will be DELETED:</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-calendar-alt text-primary me-2"></i>Appointments</span>
                                    <span class="badge bg-danger"><?php echo $relatedCounts['appointments']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-file-medical text-success me-2"></i>Medical Records</span>
                                    <span class="badge bg-danger"><?php echo $relatedCounts['medical_records']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-cog text-muted me-2"></i>System Logs</span>
                                    <span class="badge bg-secondary">All</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Records that will be UPDATED:</h6>
                            <ul class="list-group list-group-flush">
                                <?php if ($relatedCounts['donations'] > 0): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-donate text-warning me-2"></i>Donation Records</span>
                                    <span class="badge bg-warning"><?php echo $relatedCounts['donations']; ?></span>
                                </li>
                                <?php endif; ?>
                                <?php if ($monk['room_number']): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bed text-info me-2"></i>Room <?php echo $monk['room_number']; ?></span>
                                    <span class="badge bg-info">Made Available</span>
                                </li>
                                <?php endif; ?>
                                <?php if ($relatedCounts['donations'] == 0 && !$monk['room_number']): ?>
                                <li class="list-group-item text-muted text-center">
                                    <em>No records to update</em>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Confirmation Form -->
                    <hr class="my-4">
                    <form method="POST" action="" id="deleteForm">
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="confirmDelete" name="confirm_delete" value="yes" required>
                            <label class="form-check-label fw-bold text-danger" for="confirmDelete">
                                I understand that this action is permanent and cannot be undone
                            </label>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirmName" class="form-label">
                                Type the monk's full name to confirm: 
                                <strong><?php echo htmlspecialchars($monk['full_name']); ?></strong>
                            </label>
                            <input type="text" class="form-control" id="confirmName" 
                                   placeholder="Enter the monk's full name exactly as shown above" required>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="monk-view.php?id=<?php echo $monkId; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-danger" id="deleteButton" disabled>
                                <i class="fas fa-trash me-2"></i>Permanently Delete Monk
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>What happens after deletion?</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            The monk's login account will be immediately deactivated
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            All personal and medical information will be permanently removed
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Room assignment will be freed up for other monks
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Donation records will remain but monk reference will be removed
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            This action is logged for audit purposes
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(45deg, #dc3545, #c82333);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
    text-transform: uppercase;
}

.border-start {
    border-left-width: 4px !important;
}
</style>

<script>
$(document).ready(function() {
    const expectedName = '<?php echo addslashes($monk['full_name']); ?>';
    const deleteButton = $('#deleteButton');
    const confirmCheckbox = $('#confirmDelete');
    const confirmNameInput = $('#confirmName');
    
    function checkFormValidity() {
        const nameMatch = confirmNameInput.val().trim() === expectedName;
        const checkboxChecked = confirmCheckbox.is(':checked');
        
        deleteButton.prop('disabled', !(nameMatch && checkboxChecked));
        
        // Visual feedback for name input
        if (confirmNameInput.val().length > 0) {
            if (nameMatch) {
                confirmNameInput.removeClass('is-invalid').addClass('is-valid');
            } else {
                confirmNameInput.removeClass('is-valid').addClass('is-invalid');
            }
        } else {
            confirmNameInput.removeClass('is-valid is-invalid');
        }
    }
    
    confirmCheckbox.on('change', checkFormValidity);
    confirmNameInput.on('input', checkFormValidity);
    
    // Form submission confirmation
    $('#deleteForm').on('submit', function(e) {
        if (!confirm('Are you absolutely sure you want to delete this monk? This action cannot be undone!')) {
            e.preventDefault();
        }
    });
    
    // Auto-focus on name input when checkbox is checked
    confirmCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            confirmNameInput.focus();
        }
    });
});
</script>

<?php renderPageFooter(); ?>