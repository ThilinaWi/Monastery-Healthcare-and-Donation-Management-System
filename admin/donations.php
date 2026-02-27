<?php
/**
 * Admin - Donation Categories Management
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

$page_title = 'Donation Categories';
$currentPage = 'donations.php';

// Get database connection
$db = Database::getInstance();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        // Add new donation category
        try {
            $data = [
                'category_name' => sanitize_input($_POST['name'] ?? ''),
                'description' => sanitize_input($_POST['description'] ?? ''),
                'target_amount' => floatval($_POST['target_amount'] ?? 0),
                'priority_level' => sanitize_input($_POST['priority_level'] ?? 'medium'),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // Validate required fields
            if (empty($data['category_name'])) {
                throw new Exception('Category name is required');
            }
            
            if ($data['target_amount'] <= 0) {
                throw new Exception('Target amount must be greater than 0');
            }
            
            // Check for existing category name
            $existing = $db->fetchOne("SELECT COUNT(*) as count FROM donation_categories WHERE category_name = ?", [$data['category_name']]);
            if ($existing['count'] > 0) {
                throw new Exception('Category name already exists');
            }
            
            $db->insert('donation_categories', $data);
            $success = "Donation category '{$data['category_name']}' created successfully!";
            
        } catch (Exception $e) {
            $error = "Failed to create category: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_category'])) {
        // Update existing category
        try {
            $categoryId = intval($_POST['category_id'] ?? 0);
            $data = [
                'category_name' => sanitize_input($_POST['name'] ?? ''),
                'description' => sanitize_input($_POST['description'] ?? ''),
                'target_amount' => floatval($_POST['target_amount'] ?? 0),
                'priority_level' => sanitize_input($_POST['priority_level'] ?? 'medium'),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            if (!$categoryId || empty($data['category_name']) || $data['target_amount'] <= 0) {
                throw new Exception('Invalid data provided');
            }
            
            // Check for existing category name (excluding current)
            $existing = $db->fetchOne("SELECT COUNT(*) as count FROM donation_categories WHERE category_name = ? AND category_id != ?", [$data['category_name'], $categoryId]);
            if ($existing['count'] > 0) {
                throw new Exception('Category name already exists');
            }
            
            $db->update('donation_categories', $data, 'category_id = ?', [$categoryId]);
            $success = "Category updated successfully!";
            
        } catch (Exception $e) {
            $error = "Failed to update category: " . $e->getMessage();
        }
    }
}

// Get categories with statistics
try {
    $categories = $db->fetchAll("
        SELECT dc.*,
               COALESCE(SUM(d.amount), 0) as total_received,
               COALESCE(COUNT(d.donation_id), 0) as donation_count,
               COALESCE(SUM(e.amount), 0) as total_spent,
               COALESCE(COUNT(e.expense_id), 0) as expense_count
        FROM donation_categories dc
        LEFT JOIN donations d ON dc.category_id = d.category_id
        LEFT JOIN expenses e ON dc.category_id = e.category_id
        GROUP BY dc.category_id
        ORDER BY dc.priority_level ASC, dc.category_name ASC
    ");
    
    // Get overall statistics
    $stats = [
        'total_categories' => $db->fetchOne("SELECT COUNT(*) as count FROM donation_categories")['count'],
        'active_categories' => $db->fetchOne("SELECT COUNT(*) as count FROM donation_categories WHERE is_active = 1")['count'],
        'total_donations' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM donations")['total'],
        'total_expenses' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM expenses")['total']
    ];
    
    $remaining_balance = $stats['total_donations'] - $stats['total_expenses'];
    
} catch (Exception $e) {
    error_log("Donation categories error: " . $e->getMessage());
    $categories = [];
    $stats = ['total_categories' => 0, 'active_categories' => 0, 'total_donations' => 0, 'total_expenses' => 0];
    $remaining_balance = 0;
}

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Donation Categories</h1>
            <p class="mb-0 text-muted">Manage donation categories and track targets</p>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
            </button>
            <a href="expenses.php" class="btn btn-outline-secondary">
                <i class="fas fa-receipt me-2"></i>Manage Expenses
            </a>
            <a href="transparency.php" class="btn btn-outline-success">
                <i class="fas fa-chart-pie me-2"></i>Transparency Report
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

    <!-- Overall Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Total Categories
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($stats['total_categories']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-success text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Total Donations
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                $<?php echo number_format($stats['total_donations'], 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding-heart fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-warning text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Total Expenses
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                $<?php echo number_format($stats['total_expenses'], 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-<?php echo ($remaining_balance >= 0) ? 'info' : 'danger'; ?> text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Remaining Balance
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                $<?php echo number_format($remaining_balance, 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="row">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $category): ?>
                <?php
                $progress_percentage = ($category['target_amount'] > 0) 
                    ? min(100, ($category['total_received'] / $category['target_amount']) * 100) 
                    : 0;
                $remaining_needed = max(0, $category['target_amount'] - $category['total_received']);
                $net_balance = $category['total_received'] - $category['total_spent'];
                ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-truncate me-2">
                                <i class="fas fa-tag me-2 text-primary"></i>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </h6>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <span class="badge bg-<?php echo $category['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Description -->
                            <?php if ($category['description']): ?>
                                <p class="text-muted small mb-3">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </p>
                            <?php endif; ?>

                            <!-- Progress Bar -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-sm font-weight-bold">Progress to Target</span>
                                    <span class="text-sm text-muted"><?php echo number_format($progress_percentage, 1); ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo ($progress_percentage >= 100) ? 'success' : 'primary'; ?>" 
                                         style="width: <?php echo $progress_percentage; ?>%"></div>
                                </div>
                            </div>

                            <!-- Financial Summary -->
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="text-xs text-muted text-uppercase">Target</div>
                                    <div class="font-weight-bold text-primary">
                                        $<?php echo number_format($category['target_amount']); ?>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-xs text-muted text-uppercase">Received</div>
                                    <div class="font-weight-bold text-success">
                                        $<?php echo number_format($category['total_received']); ?>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-xs text-muted text-uppercase">Spent</div>
                                    <div class="font-weight-bold text-warning">
                                        $<?php echo number_format($category['total_spent']); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics -->
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="text-xs text-muted">Donations</div>
                                    <div class="font-weight-bold"><?php echo $category['donation_count']; ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-xs text-muted">Net Balance</div>
                                    <div class="font-weight-bold text-<?php echo ($net_balance >= 0) ? 'success' : 'danger'; ?>">
                                        $<?php echo number_format($net_balance); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($remaining_needed > 0): ?>
                                <div class="alert alert-light mt-3 mb-0 text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-target me-1"></i>
                                        $<?php echo number_format($remaining_needed); ?> more needed to reach target
                                    </small>
                                </div>
                            <?php elseif ($progress_percentage >= 100): ?>
                                <div class="alert alert-success mt-3 mb-0 text-center">
                                    <small>
                                        <i class="fas fa-check-circle me-1"></i>
                                        Target achieved! 
                                        <?php if ($category['total_received'] > $category['target_amount']): ?>
                                            (+$<?php echo number_format($category['total_received'] - $category['target_amount']); ?> extra)
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Priority: 
                                    <?php
                                    $priority_labels = [1 => 'Critical', 2 => 'High', 3 => 'Medium', 4 => 'Low'];
                                    $priority_colors = [1 => 'danger', 2 => 'warning', 3 => 'info', 4 => 'secondary'];
                                    ?>
                                    <span class="badge bg-<?php echo $priority_colors[$category['priority_level']] ?? 'secondary'; ?>">
                                        <?php echo $priority_labels[$category['priority_level']] ?? 'Unknown'; ?>
                                    </span>
                                </small>
                                <div class="btn-group btn-group-sm">
                                    <a href="category-donations.php?id=<?php echo $category['category_id']; ?>" 
                                       class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No donation categories found</h5>
                        <p class="text-muted">Create your first donation category to start collecting donations.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>Create First Category
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Donation Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="form-text">e.g., Food, Medical Supplies, Electricity, etc.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brief description of what this category covers..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_amount" class="form-label">Target Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="target_amount" 
                                           name="target_amount" step="0.01" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority_level" class="form-label">Priority Level</label>
                                <select class="form-select" id="priority_level" name="priority_level">
                                    <option value="1">Critical</option>
                                    <option value="2" selected>High</option>
                                    <option value="3">Medium</option>
                                    <option value="4">Low</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">
                            Active category (accept donations)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Donation Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_category_id" name="category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_target_amount" class="form-label">Target Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit_target_amount" 
                                           name="target_amount" step="0.01" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_priority_level" class="form-label">Priority Level</label>
                                <select class="form-select" id="edit_priority_level" name="priority_level">
                                    <option value="1">Critical</option>
                                    <option value="2">High</option>
                                    <option value="3">Medium</option>
                                    <option value="4">Low</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">
                            Active category (accept donations)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit category function
function editCategory(category) {
    $('#edit_category_id').val(category.category_id);
    $('#edit_name').val(category.name);
    $('#edit_description').val(category.description);
    $('#edit_target_amount').val(category.target_amount);
    $('#edit_priority_level').val(category.priority_level);
    $('#edit_is_active').prop('checked', category.is_active == 1);
    
    $('#editCategoryModal').modal('show');
}

// Form validation
$(document).ready(function() {
    $('form').on('submit', function(e) {
        const nameField = $(this).find('input[name="name"]');
        const targetField = $(this).find('input[name="target_amount"]');
        
        if (!nameField.val().trim()) {
            e.preventDefault();
            alert('Category name is required');
            nameField.focus();
            return false;
        }
        
        if (!targetField.val() || parseFloat(targetField.val()) <= 0) {
            e.preventDefault();
            alert('Target amount must be greater than 0');
            targetField.focus();
            return false;
        }
    });
});
</script>

<?php renderPageFooter(); ?>