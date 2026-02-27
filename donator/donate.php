<?php
/**
 * Donator - Make Donation
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

// Check donator access
requireRole('donator');

$page_title = 'Make Donation';
$currentPage = 'donate.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$donatorId = $currentUser['donator_id'];

$error = '';
$success = '';

// Handle donation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['make_donation'])) {
        try {
            $data = [
                'donator_id' => $donatorId,
                'category_id' => intval($_POST['category_id'] ?? 0),
                'amount' => floatval($_POST['amount'] ?? 0),
                'donation_type' => sanitize_input($_POST['donation_type'] ?? 'monetary'),
                'donation_method' => sanitize_input($_POST['donation_method'] ?? 'cash'),
                'notes' => sanitize_input($_POST['message'] ?? ''),
                'is_anonymous' => isset($_POST['is_anonymous']) ? 1 : 0,
                'status' => 'pending',
                'donation_date' => date('Y-m-d')
            ];
            
            // Validate required fields
            if ($data['amount'] <= 0) {
                throw new Exception('Donation amount must be greater than 0');
            }
            
            if ($data['category_id'] <= 0) {
                throw new Exception('Please select a donation category');
            }
            
            // Validate category exists and is active
            $category = $db->fetchOne("SELECT * FROM donation_categories WHERE category_id = ? AND is_active = 1", [$data['category_id']]);
            if (!$category) {
                throw new Exception('Selected category is not available');
            }
            
            // Additional validation for donation type
            if (!in_array($data['donation_type'], ['monetary', 'goods', 'service'])) {
                $data['donation_type'] = 'monetary';
            }
            
            // For goods/service donations, amount represents estimated value
            if ($data['donation_type'] !== 'monetary') {
                $goodsDesc = sanitize_input($_POST['goods_description'] ?? '');
                if (empty($goodsDesc)) {
                    throw new Exception('Please describe the goods or services being donated');
                }
                $data['notes'] = ($data['notes'] ? $data['notes'] . ' | ' : '') . 'Items: ' . $goodsDesc;
            }
            
            // Insert donation record
            $donationId = $db->insert('donations', $data);
            
            // Log the donation
            $db->insert('system_logs', [
                'user_type' => 'donator',
                'user_id' => $donatorId,
                'action' => 'create',
                'table_affected' => 'donations',
                'record_id' => $donationId,
                'new_values' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = "Thank you! Your donation of $" . number_format($data['amount'], 2) . " to '{$category['category_name']}' has been recorded and is pending approval.";
            
            // Send notification email to admin (if configured)
            // This would be implemented in a real system
            
        } catch (Exception $e) {
            $error = "Failed to process donation: " . $e->getMessage();
        }
    }
}

// Get available donation categories with progress
try {
    $categories = $db->fetchAll("
        SELECT dc.*,
               COALESCE(SUM(d.amount), 0) as total_received,
               COALESCE(COUNT(d.donation_id), 0) as donation_count
        FROM donation_categories dc
        LEFT JOIN donations d ON dc.category_id = d.category_id AND d.status = 'approved'
        WHERE dc.is_active = 1
        GROUP BY dc.category_id
        ORDER BY dc.priority_level ASC, dc.category_name ASC
    ");
    
    // Get donator's donation history summary
    $donatorStats = [
        'total_donations' => $db->fetchOne("SELECT COUNT(*) as count FROM donations WHERE donator_id = ?", [$donatorId])['count'],
        'total_amount' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE donator_id = ?", [$donatorId])['total'],
        'pending_donations' => $db->fetchOne("SELECT COUNT(*) as count FROM donations WHERE donator_id = ? AND status = 'pending'", [$donatorId])['count'],
        'this_year_amount' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE donator_id = ? AND YEAR(created_at) = YEAR(CURDATE())", [$donatorId])['total']
    ];
    
    // Get recent donations for inspiration
    $recentDonations = $db->fetchAll("
        SELECT d.amount, dc.category_name, d.created_at,
               CASE WHEN d.is_anonymous = 1 THEN 'Anonymous Donor' ELSE don.full_name END as donor_name
        FROM donations d
        LEFT JOIN donation_categories dc ON d.category_id = dc.category_id
        LEFT JOIN donators don ON d.donator_id = don.donator_id
        WHERE d.status = 'approved'
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    error_log("Make donation error: " . $e->getMessage());
    $categories = [];
    $donatorStats = ['total_donations' => 0, 'total_amount' => 0, 'pending_donations' => 0, 'this_year_amount' => 0];
    $recentDonations = [];
}

// Render page
renderPageHeader($page_title, 'donator');
renderSidebar('donator', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Make a Donation</h1>
            <p class="mb-0 text-muted">Support the monastery's mission and help those in need</p>
        </div>
        <div class="btn-group">
            <a href="donation-history.php" class="btn btn-outline-primary">
                <i class="fas fa-history me-2"></i>My Donations
            </a>
            <a href="transparency.php" class="btn btn-outline-success">
                <i class="fas fa-chart-pie me-2"></i>Impact Report
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
        <!-- Main Donation Form -->
        <div class="col-lg-8">
            <!-- Donation Categories -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-holding-heart me-2 text-primary"></i>
                        Choose a Category to Support
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($categories)): ?>
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                                <?php
                                $progress_percentage = ($category['target_amount'] > 0) 
                                    ? min(100, ($category['total_received'] / $category['target_amount']) * 100) 
                                    : 0;
                                $remaining_needed = max(0, $category['target_amount'] - $category['total_received']);
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="category-card card h-100 cursor-pointer" 
                                         data-category-id="<?php echo $category['category_id']; ?>"
                                         data-category-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                         onclick="selectCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['category_name']); ?>')">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-tag me-2 text-primary"></i>
                                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                                </h6>
                                                <span class="badge bg-primary">
                                                    <?php
                                                    $priority_labels = [1 => 'Critical', 2 => 'High', 3 => 'Medium', 4 => 'Low'];
                                                    echo $priority_labels[$category['priority_level']] ?? 'Normal';
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($category['description']): ?>
                                                <p class="card-text text-muted small mb-3">
                                                    <?php echo htmlspecialchars($category['description']); ?>
                                                </p>
                                            <?php endif; ?>

                                            <!-- Progress -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Progress to Target</small>
                                                    <small class="text-muted"><?php echo number_format($progress_percentage, 1); ?>%</small>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php echo ($progress_percentage >= 100) ? 'success' : 'primary'; ?>" 
                                                         style="width: <?php echo $progress_percentage; ?>%"></div>
                                                </div>
                                            </div>

                                            <!-- Financial Info -->
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <div class="small text-muted">Raised</div>
                                                    <div class="fw-bold text-success">
                                                        $<?php echo number_format($category['total_received']); ?>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="small text-muted">Target</div>
                                                    <div class="fw-bold text-primary">
                                                        $<?php echo number_format($category['target_amount']); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if ($remaining_needed > 0): ?>
                                                <div class="alert alert-light mt-3 mb-0 text-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-target me-1"></i>
                                                        $<?php echo number_format($remaining_needed); ?> more needed
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
                            <i class="fas fa-heart-broken fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No active donation categories</h5>
                            <p class="text-muted">Please check back later for opportunities to help.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Donation Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-credit-card me-2 text-success"></i>
                        Donation Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="donationForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Donation Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['category_id']; ?>">
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                                (Target: $<?php echo number_format($cat['target_amount']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="donation_type" class="form-label">Donation Type</label>
                                    <select class="form-select" id="donation_type" name="donation_type">
                                        <option value="monetary">Monetary Donation</option>
                                        <option value="goods">Goods/Supplies</option>
                                        <option value="service">Service/Volunteer Time</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount/Value *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               step="0.01" min="1" required>
                                    </div>
                                    <div class="form-text">For goods/services, enter estimated monetary value</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quick Amount</label>
                                    <div class="btn-group d-flex" role="group">
                                        <button type="button" class="btn btn-outline-primary" onclick="setAmount(25)">$25</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="setAmount(50)">$50</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="setAmount(100)">$100</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="setAmount(250)">$250</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Goods/Service Description (hidden by default) -->
                        <div class="mb-3" id="goodsDescriptionSection" style="display: none;">
                            <label for="goods_description" class="form-label">Description of Goods/Services</label>
                            <textarea class="form-control" id="goods_description" name="goods_description" rows="3"
                                      placeholder="Please describe what you are donating in detail..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Personal Message (Optional)</label>
                            <textarea class="form-control" id="message" name="message" rows="3"
                                      placeholder="Share why this cause is important to you or leave an encouraging message..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous">
                                    <label class="form-check-label" for="is_anonymous">
                                        Make this donation anonymous
                                    </label>
                                    <div class="form-text">Your name will not be displayed publicly</div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>Donation Process
                            </h6>
                            <ul class="mb-0 small">
                                <li>All donations are reviewed by our administrators before approval</li>
                                <li>You will receive email confirmation once your donation is processed</li>
                                <li>Monetary donations help fund essential monastery operations</li>
                                <li>Your generosity directly impacts the well-being of our community</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="make_donation" class="btn btn-success btn-lg">
                                <i class="fas fa-heart me-2"></i>Submit Donation
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
            <!-- Your Impact -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Your Impact</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="display-6 text-success mb-2">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h5 class="text-muted">Thank you for your generosity!</h5>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4 class="text-primary"><?php echo $donatorStats['total_donations']; ?></h4>
                            <small class="text-muted">Total Donations</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-success">$<?php echo number_format($donatorStats['total_amount']); ?></h4>
                            <small class="text-muted">Total Given</small>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <h6 class="text-info">${<?php echo number_format($donatorStats['this_year_amount']); ?></h6>
                            <small class="text-muted">This Year</small>
                        </div>
                        <div class="col-6">
                            <h6 class="text-warning"><?php echo $donatorStats['pending_donations']; ?></h6>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Community Donations -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Recent Community Support</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentDonations)): ?>
                        <?php foreach ($recentDonations as $donation): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($donation['donor_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($donation['category_name']); ?> • 
                                    <?php echo date('M d', strtotime($donation['created_at'])); ?>
                                </small>
                            </div>
                            <div class="text-success fw-bold">
                                $<?php echo number_format($donation['amount']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No recent donations to display</p>
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
                        <a href="donation-history.php" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>View My Donations
                        </a>
                        <a href="transparency.php" class="btn btn-outline-success">
                            <i class="fas fa-chart-pie me-2"></i>See How Funds Are Used
                        </a>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user me-2"></i>Update My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.category-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.category-card:hover {
    border-color: #007bff;
    box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.15);
    transform: translateY(-2px);
}

.category-card.selected {
    border-color: #28a745;
    background-color: #f8fff9;
}

.cursor-pointer {
    cursor: pointer;
}
</style>

<script>
// Select category function
function selectCategory(categoryId, categoryName) {
    // Update form
    $('#category_id').val(categoryId);
    
    // Update visual selection
    $('.category-card').removeClass('selected');
    $(`[data-category-id="${categoryId}"]`).addClass('selected');
    
    // Smooth scroll to form
    $('#donationForm')[0].scrollIntoView({ behavior: 'smooth' });
    
    // Focus on amount field
    setTimeout(() => {
        $('#amount').focus();
    }, 500);
}

// Set quick amount
function setAmount(amount) {
    $('#amount').val(amount);
    validateForm();
}

// Show/hide goods description based on donation type
$('#donation_type').on('change', function() {
    const donationType = $(this).val();
    if (donationType === 'goods' || donationType === 'service') {
        $('#goodsDescriptionSection').show();
        $('#goods_description').prop('required', true);
    } else {
        $('#goodsDescriptionSection').hide();
        $('#goods_description').prop('required', false);
    }
});

// Form validation
function validateForm() {
    const amount = parseFloat($('#amount').val()) || 0;
    const categoryId = $('#category_id').val();
    const donationType = $('#donation_type').val();
    const goodsDescription = $('#goods_description').val().trim();
    
    let isValid = true;
    
    // Reset error states
    $('.is-invalid').removeClass('is-invalid');
    
    if (amount <= 0) {
        $('#amount').addClass('is-invalid');
        isValid = false;
    }
    
    if (!categoryId) {
        $('#category_id').addClass('is-invalid');
        isValid = false;
    }
    
    if ((donationType === 'goods' || donationType === 'service') && !goodsDescription) {
        $('#goods_description').addClass('is-invalid');
        isValid = false;
    }
    
    return isValid;
}

// Form submission
$('#donationForm').on('submit', function(e) {
    if (!validateForm()) {
        e.preventDefault();
        
        // Scroll to first error
        const firstError = $('.is-invalid').first();
        if (firstError.length) {
            firstError[0].scrollIntoView({ behavior: 'smooth' });
            firstError.focus();
        }
        
        // Show error message
        if (!$('.alert-danger').length) {
            const errorAlert = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Please correct the highlighted fields before submitting.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $(errorAlert).insertAfter('.container-fluid > .d-flex').hide().slideDown();
        }
        
        return false;
    }
    
    // Show loading state
    const submitBtn = $('button[name="make_donation"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
    
    // Re-enable after 5 seconds (failsafe)
    setTimeout(() => {
        submitBtn.prop('disabled', false).html(originalText);
    }, 5000);
});

// Real-time validation
$('#amount, #category_id').on('change input', validateForm);

// Initialize
$(document).ready(function() {
    // Auto-select first category if only one exists
    const categories = $('.category-card');
    if (categories.length === 1) {
        const firstCategory = categories.first();
        const categoryId = firstCategory.data('category-id');
        const categoryName = firstCategory.data('category-name');
        selectCategory(categoryId, categoryName);
    }
});
</script>

<?php renderPageFooter(); ?>