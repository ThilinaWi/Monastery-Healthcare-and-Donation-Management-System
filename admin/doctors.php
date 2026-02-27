<?php
/**
 * Admin - Doctor Management
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

$page_title = 'Doctor Management';
$currentPage = 'doctors.php';

// Get database connection
$db = Database::getInstance();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_doctor'])) {
        // Add new doctor
        try {
            $data = [
                'username' => sanitize_input($_POST['username'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'full_name' => sanitize_input($_POST['full_name'] ?? ''),
                'specialization' => sanitize_input($_POST['specialization'] ?? ''),
                'license_number' => sanitize_input($_POST['license_number'] ?? ''),
                'phone' => sanitize_input($_POST['phone'] ?? ''),
                'qualifications' => sanitize_input($_POST['qualifications'] ?? ''),
                'experience_years' => intval($_POST['experience_years'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // Generate secure password
            $password = 'doctor' . rand(1000, 9999);
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Validate required fields
            if (empty($data['username']) || empty($data['email']) || empty($data['full_name']) || empty($data['specialization'])) {
                throw new Exception('Required fields are missing');
            }
            
            // Check for existing username/email
            $existing = $db->fetchOne("SELECT COUNT(*) as count FROM doctors WHERE username = ? OR email = ?", [$data['username'], $data['email']]);
            if ($existing['count'] > 0) {
                throw new Exception('Username or email already exists');
            }
            
            $db->insert('doctors', $data);
            $success = "Doctor '{$data['full_name']}' added successfully! Login password: {$password}";
            
        } catch (Exception $e) {
            $error = "Failed to add doctor: " . $e->getMessage();
        }
    }
}

// Get doctors list with search and filter
$search = sanitize_input($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$specialization_filter = $_GET['specialization'] ?? 'all';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR specialization LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "is_active = ?";
    $params[] = ($status_filter === 'active') ? 1 : 0;
}

if ($specialization_filter !== 'all') {
    $where_conditions[] = "specialization = ?";
    $params[] = $specialization_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get doctors
    $doctors = $db->fetchAll("
        SELECT d.*, 
               (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.doctor_id) as total_appointments,
               (SELECT COUNT(*) FROM medical_records WHERE doctor_id = d.doctor_id) as total_records
        FROM doctors d 
        {$where_clause}
        ORDER BY d.full_name ASC
    ", $params);
    
    // Get statistics
    $stats = [
        'total_doctors' => $db->fetchOne("SELECT COUNT(*) as count FROM doctors")['count'],
        'active_doctors' => $db->fetchOne("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")['count'],
        'total_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE doctor_id IS NOT NULL")['count'],
        'specializations' => $db->fetchOne("SELECT COUNT(DISTINCT specialization) as count FROM doctors WHERE specialization IS NOT NULL")['count']
    ];
    
    // Get unique specializations for filter
    $specializations = $db->fetchAll("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization");
    
} catch (Exception $e) {
    error_log("Doctor management error: " . $e->getMessage());
    $doctors = [];
    $stats = ['total_doctors' => 0, 'active_doctors' => 0, 'total_appointments' => 0, 'specializations' => 0];
    $specializations = [];
}

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Doctor Management</h1>
            <p class="mb-0 text-muted">Manage medical staff and healthcare providers</p>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                <i class="fas fa-plus me-2"></i>Add Doctor
            </button>
            <a href="../admin/reports.php?type=doctors" class="btn btn-outline-success">
                <i class="fas fa-chart-line me-2"></i>Reports
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Doctors
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_doctors']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-md fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Doctors
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['active_doctors']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Appointments
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_appointments']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Specializations
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['specializations']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-stethoscope fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Doctors</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Name, email, or specialization...">
                </div>
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="specialization" class="form-label">Specialization</label>
                    <select class="form-select" id="specialization" name="specialization">
                        <option value="all">All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialization']); ?>"
                                    <?php echo ($specialization_filter === $spec['specialization']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialization']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Doctors Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h6 class="mb-0">Doctors List (<?php echo count($doctors); ?> found)</h6>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($doctors)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Doctor</th>
                                <th>Specialization</th>
                                <th>Contact</th>
                                <th>Experience</th>
                                <th>Statistics</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php echo strtoupper(substr($doctor['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($doctor['full_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($doctor['username']); ?></small>
                                            <?php if ($doctor['license_number']): ?>
                                                <br><small class="text-info">License: <?php echo htmlspecialchars($doctor['license_number']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                                    <?php if ($doctor['qualifications']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($doctor['qualifications']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope me-1 text-muted"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>">
                                            <?php echo htmlspecialchars($doctor['email']); ?>
                                        </a>
                                    </div>
                                    <?php if ($doctor['phone']): ?>
                                    <div class="mt-1">
                                        <i class="fas fa-phone me-1 text-muted"></i>
                                        <a href="tel:<?php echo htmlspecialchars($doctor['phone']); ?>">
                                            <?php echo htmlspecialchars($doctor['phone']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doctor['experience_years'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $doctor['experience_years']; ?> years</span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><i class="fas fa-calendar-check text-info me-1"></i><?php echo $doctor['total_appointments']; ?> appointments</div>
                                    <div><i class="fas fa-file-medical text-success me-1"></i><?php echo $doctor['total_records']; ?> records</div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $doctor['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="doctor-view.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="doctor-edit.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="toggleStatus(<?php echo $doctor['doctor_id']; ?>, <?php echo $doctor['is_active'] ? 'false' : 'true'; ?>)" 
                                                class="btn btn-outline-<?php echo $doctor['is_active'] ? 'warning' : 'success'; ?>">
                                            <i class="fas fa-<?php echo $doctor['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No doctors found</h5>
                    <p class="text-muted">Try adjusting your search criteria or add new doctors.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization *</label>
                                <select class="form-select" id="specialization" name="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="General Medicine">General Medicine</option>
                                    <option value="Internal Medicine">Internal Medicine</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Neurology">Neurology</option>
                                    <option value="Psychiatry">Psychiatry</option>
                                    <option value="Geriatrics">Geriatrics</option>
                                    <option value="Alternative Medicine">Alternative Medicine</option>
                                    <option value="Ayurveda">Ayurveda</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="experience_years" name="experience_years" min="0" max="50">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="license_number" class="form-label">Medical License Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="qualifications" class="form-label">Qualifications</label>
                                <input type="text" class="form-control" id="qualifications" name="qualifications" 
                                       placeholder="MD, MBBS, etc.">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">
                            Active doctor (can login and manage patients)
                        </label>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        A temporary login password will be generated and displayed after creation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_doctor" class="btn btn-primary">Add Doctor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}
</style>

<script>
// Toggle doctor status
function toggleStatus(doctorId, newStatus) {
    const action = newStatus === 'true' ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this doctor?`)) {
        window.location.href = `doctor-toggle.php?id=${doctorId}&status=${newStatus}`;
    }
}

// Form validation
$(document).ready(function() {
    $('#addDoctorModal form').on('submit', function(e) {
        const fullName = $('#full_name').val().trim();
        const username = $('#username').val().trim();
        const email = $('#email').val().trim();
        const specialization = $('#specialization').val();
        
        if (!fullName || !username || !email || !specialization) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return false;
        }
        
        if (username.length < 3) {
            e.preventDefault();
            alert('Username must be at least 3 characters long');
            return false;
        }
    });
});
</script>

<?php renderPageFooter(); ?>