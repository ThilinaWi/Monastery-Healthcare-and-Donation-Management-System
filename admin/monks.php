<?php
/**
 * Admin - Monk Management
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

$page_title = 'Monk Management';
$currentPage = 'monks.php';

// Get database connection
$db = Database::getInstance();

// Handle search and filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$room = $_GET['room'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build WHERE clause for filters
$whereClause = "1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (m.full_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status !== 'all') {
    $whereClause .= " AND m.is_active = ?";
    $params[] = ($status === 'active') ? 1 : 0;
}

if ($room !== 'all') {
    if ($room === 'unassigned') {
        $whereClause .= " AND m.room_id IS NULL";
    } else {
        $whereClause .= " AND m.room_id = ?";
        $params[] = $room;
    }
}

try {
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM monks m WHERE $whereClause";
    $totalRecords = $db->fetchOne($countSql, $params)['total'];
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get monks with room information
    $sql = "
        SELECT m.*, 
               r.room_number, 
               r.room_type,
               (SELECT COUNT(*) FROM appointments WHERE monk_id = m.monk_id AND status = 'scheduled' AND appointment_date >= CURDATE()) as pending_appointments,
               (SELECT COUNT(*) FROM medical_records WHERE monk_id = m.monk_id) as total_records
        FROM monks m
        LEFT JOIN rooms r ON m.room_id = r.room_id
        WHERE $whereClause
        ORDER BY m.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    $monks = $db->fetchAll($sql, $params);
    
    // Get available rooms for filter
    $availableRooms = $db->fetchAll("
        SELECT room_id, room_number, room_type, capacity, current_occupancy
        FROM rooms 
        WHERE is_available = 1 
        ORDER BY room_number
    ");
    
    $success = $_SESSION['success'] ?? '';
    $error = $_SESSION['error'] ?? '';
    unset($_SESSION['success'], $_SESSION['error']);
    
} catch (Exception $e) {
    error_log("Monk management error: " . $e->getMessage());
    $error = "Error loading monk data. Please try again.";
    $monks = [];
    $totalRecords = 0;
    $totalPages = 1;
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
            <h1 class="h3 mb-0 text-gray-800">Monk Management</h1>
            <p class="mb-0 text-muted">Manage monastery residents and their information</p>
        </div>
        <div>
            <a href="monk-add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Monk
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($success): ?>
        <?php renderAlert('success', $success); ?>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <?php renderAlert('danger', $error); ?>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="row mb-4">
        <?php
        try {
            $stats = [
                'total' => $db->fetchOne("SELECT COUNT(*) as count FROM monks")['count'],
                'active' => $db->fetchOne("SELECT COUNT(*) as count FROM monks WHERE is_active = 1")['count'],
                'with_rooms' => $db->fetchOne("SELECT COUNT(*) as count FROM monks WHERE room_id IS NOT NULL AND is_active = 1")['count'],
                'recent' => $db->fetchOne("SELECT COUNT(*) as count FROM monks WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")['count']
            ];
            
            renderStatsCard('Total Monks', $stats['total'], 'fas fa-users', 'primary');
            renderStatsCard('Active Monks', $stats['active'], 'fas fa-user-check', 'success');
            renderStatsCard('With Rooms', $stats['with_rooms'], 'fas fa-bed', 'info');
            renderStatsCard('New (30 days)', $stats['recent'], 'fas fa-user-plus', 'warning');
        } catch (Exception $e) {
            echo '<div class="col-12"><div class="alert alert-warning">Unable to load statistics</div></div>';
        }
        ?>
    </div>

    <!-- Search and Filter Panel -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-search me-2"></i>Search & Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Monks</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo ($status === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Room Assignment</label>
                        <select class="form-select" name="room">
                            <option value="all" <?php echo ($room === 'all') ? 'selected' : ''; ?>>All Rooms</option>
                            <option value="unassigned" <?php echo ($room === 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                            <?php foreach ($availableRooms as $availableRoom): ?>
                                <option value="<?php echo $availableRoom['room_id']; ?>" 
                                        <?php echo ($room == $availableRoom['room_id']) ? 'selected' : ''; ?>>
                                    Room <?php echo htmlspecialchars($availableRoom['room_number']); ?> 
                                    (<?php echo $availableRoom['current_occupancy']; ?>/<?php echo $availableRoom['capacity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <a href="monks.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Monks Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="fas fa-list me-2"></i>Monks List 
                <span class="badge bg-primary"><?php echo $totalRecords; ?> total</span>
            </h6>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <button type="button" class="btn btn-outline-success" onclick="exportToCSV()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($monks)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Contact</th>
                                <th>Room</th>
                                <th>Health Status</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monks as $monk): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <?php echo strtoupper(substr($monk['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($monk['full_name']); ?></h6>
                                                <small class="text-muted">ID: <?php echo $monk['monk_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($monk['age'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div>
                                            <small class="d-block"><?php echo htmlspecialchars($monk['email']); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($monk['phone'] ?? 'No phone'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($monk['room_number']): ?>
                                            <span class="badge bg-info">
                                                Room <?php echo htmlspecialchars($monk['room_number']); ?>
                                            </span>
                                            <br><small class="text-muted"><?php echo ucfirst($monk['room_type']); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-medical text-info me-2"></i>
                                            <div>
                                                <small class="d-block"><?php echo $monk['total_records']; ?> records</small>
                                                <?php if ($monk['pending_appointments'] > 0): ?>
                                                    <small class="text-warning">
                                                        <i class="fas fa-clock me-1"></i><?php echo $monk['pending_appointments']; ?> pending
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-success">No pending</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($monk['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($monk['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="monk-view.php?id=<?php echo $monk['monk_id']; ?>" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="monk-edit.php?id=<?php echo $monk['monk_id']; ?>" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-delete" 
                                                    data-monk-id="<?php echo $monk['monk_id']; ?>"
                                                    data-monk-name="<?php echo htmlspecialchars($monk['full_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Monks pagination">
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&room=<?php echo $room; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&room=<?php echo $room; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&room=<?php echo $room; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Showing <?php echo (($page - 1) * $perPage) + 1; ?> to 
                                <?php echo min($totalRecords, $page * $perPage); ?> of <?php echo $totalRecords; ?> monks
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No monks found</h5>
                    <p class="text-muted">
                        <?php if (!empty($search) || $status !== 'all' || $room !== 'all'): ?>
                            Try adjusting your search filters or <a href="monks.php">view all monks</a>
                        <?php else: ?>
                            Start by adding your first monk to the system
                        <?php endif; ?>
                    </p>
                    <a href="monk-add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Monk
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="monkName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action will also delete all associated medical records and appointments. This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="monk-delete.php" class="d-inline">
                    <input type="hidden" name="monk_id" id="deleteMonkId">
                    <button type="submit" class="btn btn-danger">Delete Monk</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Delete confirmation modal
    $('.btn-delete').click(function() {
        const monkId = $(this).data('monk-id');
        const monkName = $(this).data('monk-name');
        
        $('#deleteMonkId').val(monkId);
        $('#monkName').text(monkName);
        $('#deleteModal').modal('show');
    });
    
    // Export to CSV function
    window.exportToCSV = function() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        window.location.href = 'monks.php?' + params.toString();
    };
});

// Print styling
@media print {
    .btn, .pagination, .card-header .btn-group {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
    }
}
</script>

<?php renderPageFooter(); ?>