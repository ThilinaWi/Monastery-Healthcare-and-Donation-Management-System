<?php
/**
 * Admin - Expense Management
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

$page_title = 'Expense Management';
$currentPage = 'expenses.php';

// Get database connection
$db = Database::getInstance();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_expense'])) {
        // Add new expense
        try {
            $data = [
                'category_id' => intval($_POST['category_id'] ?? 0),
                'amount' => floatval($_POST['amount'] ?? 0),
                'description' => sanitize_input($_POST['description'] ?? ''),
                'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
                'vendor' => sanitize_input($_POST['vendor_name'] ?? ''),
                'receipt_number' => sanitize_input($_POST['receipt_number'] ?? ''),
                'admin_id' => getCurrentUserId(),
            ];
            
            // Validate required fields
            if ($data['amount'] <= 0) {
                throw new Exception('Expense amount must be greater than 0');
            }
            
            if (empty($data['description'])) {
                throw new Exception('Expense description is required');
            }
            
            if ($data['category_id'] <= 0) {
                throw new Exception('Please select a valid donation category');
            }
            
            // Validate category exists
            $category = $db->fetchOne("SELECT * FROM donation_categories WHERE category_id = ?", [$data['category_id']]);
            if (!$category) {
                throw new Exception('Selected category does not exist');
            }
            
            // Handle file upload if present
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/receipts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['receipt_file']['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $filePath)) {
                    $data['receipt_file'] = 'uploads/receipts/' . $fileName;
                }
            }
            
            $expenseId = $db->insert('expenses', $data);
            
            // Log the expense creation
            $db->insert('system_logs', [
                'user_type' => 'admin',
                'user_id' => getCurrentUserId(),
                'action' => 'create',
                'table_affected' => 'expenses',
                'record_id' => $expenseId,
                'new_values' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = "Expense of $" . number_format($data['amount'], 2) . " recorded successfully!";
            
        } catch (Exception $e) {
            $error = "Failed to record expense: " . $e->getMessage();
        }
    }
}

// Get expenses with search and filter
$search = sanitize_input($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.description LIKE ? OR e.vendor_name LIKE ? OR e.receipt_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_filter > 0) {
    $where_conditions[] = "e.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "e.expense_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "e.expense_date <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get expenses
    $expenses = $db->fetchAll("
        SELECT e.*, 
               dc.category_name,
               a.full_name as created_by_name
        FROM expenses e
        LEFT JOIN donation_categories dc ON e.category_id = dc.category_id
        LEFT JOIN admins a ON e.admin_id = a.admin_id
        {$where_clause}
        ORDER BY e.expense_date DESC, e.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ", $params);
    
    // Get total count for pagination
    $totalExpenses = $db->fetchOne("SELECT COUNT(*) as count FROM expenses e {$where_clause}", $params)['count'];
    $totalPages = ceil($totalExpenses / $limit);
    
    // Get statistics
    $stats = [
        'total_expenses' => $db->fetchOne("SELECT COUNT(*) as count FROM expenses")['count'],
        'total_amount' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM expenses")['total'],
        'this_month_amount' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
            WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        ")['total'],
        'categories_used' => $db->fetchOne("SELECT COUNT(DISTINCT category_id) as count FROM expenses WHERE category_id IS NOT NULL")['count']
    ];
    
    // Get categories for dropdown
    $categories = $db->fetchAll("SELECT * FROM donation_categories WHERE is_active = 1 ORDER BY name");
    
    // Monthly expense trend (last 6 months)
    $monthlyExpenses = $db->fetchAll("
        SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') as month,
            DATE_FORMAT(expense_date, '%M %Y') as month_name,
            SUM(amount) as total_amount,
            COUNT(*) as expense_count
        FROM expenses 
        WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
        ORDER BY month DESC
    ");
    
} catch (Exception $e) {
    error_log("Expense management error: " . $e->getMessage());
    $expenses = [];
    $totalExpenses = 0;
    $totalPages = 1;
    $stats = ['total_expenses' => 0, 'total_amount' => 0, 'this_month_amount' => 0, 'categories_used' => 0];
    $categories = [];
    $monthlyExpenses = [];
}

// Render page
renderPageHeader($page_title, 'admin');
renderSidebar('admin', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Expense Management</h1>
            <p class="mb-0 text-muted">Track and manage monastery expenses</p>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus me-2"></i>Add Expense
            </button>
            <a href="donations.php" class="btn btn-outline-secondary">
                <i class="fas fa-tags me-2"></i>Manage Categories
            </a>
            <a href="reports.php?type=expenses" class="btn btn-outline-success">
                <i class="fas fa-chart-line me-2"></i>Expense Reports
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
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                Total Expenses
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($stats['total_expenses']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-receipt fa-2x opacity-50"></i>
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
                                Total Amount
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                $<?php echo number_format($stats['total_amount'], 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-info text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                This Month
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                $<?php echo number_format($stats['this_month_amount'], 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x opacity-50"></i>
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
                                Categories Used
                            </div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($stats['categories_used']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Search and Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Description, vendor...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                            <?php echo ($category_filter == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Expenses Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Expenses (<?php echo number_format($totalExpenses); ?> total)</h6>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="exportExpenses()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($expenses)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Vendor</th>
                                        <th>Receipt</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></div>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($expense['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($expense['description']); ?></div>
                                            <?php if ($expense['notes']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($expense['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($expense['category_name']): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($expense['category_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No category</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-danger">$<?php echo number_format($expense['amount'], 2); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($expense['vendor_name']): ?>
                                                <div><?php echo htmlspecialchars($expense['vendor_name']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                            <?php if ($expense['receipt_number']): ?>
                                                <small class="text-muted">Receipt: <?php echo htmlspecialchars($expense['receipt_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($expense['receipt_file']): ?>
                                                <a href="../<?php echo htmlspecialchars($expense['receipt_file']); ?>" 
                                                   target="_blank" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-file-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="viewExpense(<?php echo $expense['expense_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editExpense(<?php echo $expense['expense_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
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
                            <nav aria-label="Expenses pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Previous</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No expenses found</h5>
                            <p class="text-muted">Start by adding your first expense record.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Monthly Trend -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Expense Trend</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthlyExpenses)): ?>
                        <?php foreach (array_slice($monthlyExpenses, 0, 6) as $month): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-bold"><?php echo $month['month_name']; ?></div>
                                <small class="text-muted"><?php echo $month['expense_count']; ?> expenses</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-danger">$<?php echo number_format($month['total_amount']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No expense data available</p>
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
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                            <i class="fas fa-plus me-2"></i>Add New Expense
                        </button>
                        <a href="donations.php" class="btn btn-outline-primary">
                            <i class="fas fa-tags me-2"></i>Manage Categories
                        </a>
                        <a href="transparency.php" class="btn btn-outline-success">
                            <i class="fas fa-chart-pie me-2"></i>Transparency Report
                        </a>
                        <button class="btn btn-outline-secondary" onclick="exportExpenses()">
                            <i class="fas fa-download me-2"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <input type="text" class="form-control" id="description" name="description" 
                                       placeholder="What was purchased?" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expense_date" class="form-label">Expense Date</label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="vendor_name" class="form-label">Vendor/Supplier</label>
                                <input type="text" class="form-control" id="vendor_name" name="vendor_name" 
                                       placeholder="Who was paid?">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="receipt_number" class="form-label">Receipt Number</label>
                                <input type="text" class="form-control" id="receipt_number" name="receipt_number" 
                                       placeholder="Invoice/receipt number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="receipt_file" class="form-label">Upload Receipt/Bill</label>
                        <input type="file" class="form-control" id="receipt_file" name="receipt_file" 
                               accept="image/*,application/pdf">
                        <div class="form-text">Upload image or PDF of the receipt (optional)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Any additional details about this expense..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-primary">Record Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Export function
function exportExpenses() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'expense-export.php?' + params.toString();
}

// View expense details
function viewExpense(expenseId) {
    // Implement expense details modal or redirect
    window.location.href = `expense-view.php?id=${expenseId}`;
}

// Edit expense
function editExpense(expenseId) {
    // Implement expense edit modal or redirect
    window.location.href = `expense-edit.php?id=${expenseId}`;
}

// Form validation
$(document).ready(function() {
    $('#addExpenseModal form').on('submit', function(e) {
        const description = $('#description').val().trim();
        const amount = parseFloat($('#amount').val());
        const categoryId = $('#category_id').val();
        
        if (!description) {
            e.preventDefault();
            alert('Please enter expense description');
            $('#description').focus();
            return false;
        }
        
        if (!amount || amount <= 0) {
            e.preventDefault();
            alert('Please enter a valid amount');
            $('#amount').focus();
            return false;
        }
        
        if (!categoryId) {
            e.preventDefault();
            alert('Please select a category');
            $('#category_id').focus();
            return false;
        }
    });
    
    // Auto-calculate and show running totals
    $('#amount').on('input', function() {
        const amount = parseFloat($(this).val()) || 0;
        // Could add real-time calculation features here
    });
});
</script>

<?php renderPageFooter(); ?>