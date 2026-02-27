<?php
/**
 * Donator Dashboard
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

$page_title = 'Donator Dashboard';
$currentPage = 'dashboard.php';

// Get database connection
$db = Database::getInstance();
$currentUser = getCurrentUser();
$donatorId = $currentUser['donator_id'];

// Fetch dashboard data
try {
    // Donator's contribution statistics
    $donationStats = [
        'total_donations' => $db->fetchOne("SELECT COUNT(*) as count FROM donations WHERE donator_id = ?", [$donatorId])['count'],
        'total_amount' => $db->fetchOne("SELECT SUM(amount) as total FROM donations WHERE donator_id = ?", [$donatorId])['total'] ?? 0,
        'this_year_amount' => $db->fetchOne("SELECT SUM(amount) as total FROM donations WHERE donator_id = ? AND YEAR(created_at) = YEAR(CURDATE())", [$donatorId])['total'] ?? 0,
        'this_month_amount' => $db->fetchOne("SELECT SUM(amount) as total FROM donations WHERE donator_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')", [$donatorId])['total'] ?? 0
    ];
    
    // Donation breakdown by category
    $categoryBreakdown = $db->fetchAll("
        SELECT dc.category_name, 
               SUM(d.amount) as total_amount,
               COUNT(d.donation_id) as donation_count,
               dc.description
        FROM donations d
        LEFT JOIN donation_categories dc ON d.category_id = dc.category_id
        WHERE d.donator_id = ?
        GROUP BY dc.category_id, dc.category_name
        ORDER BY total_amount DESC
    ", [$donatorId]);
    
    // Recent donations
    $recentDonations = $db->fetchAll("
        SELECT d.*, dc.category_name
        FROM donations d
        LEFT JOIN donation_categories dc ON d.category_id = dc.category_id
        WHERE d.donator_id = ?
        ORDER BY d.created_at DESC
        LIMIT 5
    ", [$donatorId]);
    
    // Available donation categories
    $availableCategories = $db->fetchAll("
        SELECT *, 
               (SELECT SUM(amount) FROM donations WHERE category_id = dc.category_id) as total_received,
               (SELECT SUM(amount) FROM expenses WHERE category_id = dc.category_id) as total_spent
        FROM donation_categories dc 
        WHERE dc.is_active = 1
        ORDER BY dc.category_name
    ");
    
    // Monthly donation trend (last 6 months)
    $monthlyTrend = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%M %Y') as month_name,
            SUM(amount) as total_amount,
            COUNT(*) as donation_count
        FROM donations 
        WHERE donator_id = ?
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$donatorId]);
    
    // Impact summary - how donations are being used
    $impactSummary = $db->fetchOne("
        SELECT 
            (SELECT SUM(amount) FROM donations) as total_monastery_donations,
            (SELECT SUM(amount) FROM expenses WHERE category_id IN (SELECT category_id FROM donation_categories WHERE category_name LIKE '%Medical%')) as medical_expenses,
            (SELECT SUM(amount) FROM expenses WHERE category_id IN (SELECT category_id FROM donation_categories WHERE category_name LIKE '%Food%')) as food_expenses,
            (SELECT COUNT(*) FROM monks WHERE is_active = 1) as total_monks_helped
    ");
    
} catch (Exception $e) {
    error_log("Donator dashboard error: " . $e->getMessage());
    $donationStats = ['total_donations' => 0, 'total_amount' => 0, 'this_year_amount' => 0, 'this_month_amount' => 0];
    $categoryBreakdown = [];
    $recentDonations = [];
    $availableCategories = [];
    $monthlyTrend = [];
    $impactSummary = ['total_monastery_donations' => 0, 'medical_expenses' => 0, 'food_expenses' => 0, 'total_monks_helped' => 0];
}

// Render page
renderPageHeader($page_title, 'donator');
renderSidebar('donator', $currentPage);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></h1>
            <p class="mb-0 text-muted">Thank you for supporting our monastery community with your generous contributions</p>
        </div>
        <div class="text-end">
            <small class="text-muted">Member since: <?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?></small><br>
            <small class="text-muted">Today: <?php echo date('l, F d, Y'); ?></small>
        </div>
    </div>

    <!-- Donation Statistics Cards -->
    <div class="row mb-4">
        <?php 
        renderStatsCard('Total Donations', $donationStats['total_donations'], 'fas fa-heart', 'primary');
        renderStatsCard('Lifetime Giving', '$' . number_format($donationStats['total_amount'], 2), 'fas fa-hand-holding-heart', 'success');
        renderStatsCard('This Year', '$' . number_format($donationStats['this_year_amount'], 2), 'fas fa-calendar', 'info');
        renderStatsCard('This Month', '$' . number_format($donationStats['this_month_amount'], 2), 'fas fa-calendar-day', 'warning');
        ?>
    </div>

    <!-- Quick Actions & Impact Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="donate.php" class="btn btn-success btn-lg">
                            <i class="fas fa-heart me-2"></i>Make a Donation
                        </a>
                        <a href="donation-history.php" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>Donation History
                        </a>
                        <a href="transparency.php" class="btn btn-outline-info">
                            <i class="fas fa-chart-pie me-2"></i>Transparency Report
                        </a>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user me-2"></i>Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Your Impact Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 class="text-success"><?php echo $impactSummary['total_monks_helped']; ?></h4>
                            <small class="text-muted">Monks Supported</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-info">$<?php echo number_format($impactSummary['medical_expenses'], 0); ?></h4>
                            <small class="text-muted">Medical Care</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-warning">$<?php echo number_format($impactSummary['food_expenses'], 0); ?></h4>
                            <small class="text-muted">Food & Nutrition</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-primary">$<?php echo number_format($impactSummary['total_monastery_donations'], 0); ?></h4>
                            <small class="text-muted">Community Total</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-heart me-2"></i>
                        <strong>Thank you!</strong> Your contributions have directly helped provide healthcare, food, and shelter for our monastic community. 
                        Every donation makes a meaningful difference in the lives of our monks.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Donation Categories & Recent Activity -->
    <div class="row">
        <!-- Available Donation Categories -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Donation Categories</h6>
                    <a href="donate.php" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i>Donate Now
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($availableCategories)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($availableCategories as $category): ?>
                                <?php 
                                $remaining = $category['total_received'] - $category['total_spent'];
                                $percentUsed = $category['total_received'] > 0 ? ($category['total_spent'] / $category['total_received']) * 100 : 0;
                                ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($category['category_name']); ?></h6>
                                            <p class="mb-2 text-muted small"><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
                                            <div class="row text-center small">
                                                <div class="col-4">
                                                    <strong class="text-success">$<?php echo number_format($category['total_received'], 0); ?></strong>
                                                    <br><small class="text-muted">Received</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong class="text-danger">$<?php echo number_format($category['total_spent'], 0); ?></strong>
                                                    <br><small class="text-muted">Spent</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong class="text-info">$<?php echo number_format($remaining, 0); ?></strong>
                                                    <br><small class="text-muted">Remaining</small>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="donate.php?category=<?php echo $category['category_id']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-heart"></i>
                                        </a>
                                    </div>
                                    <?php if ($category['total_received'] > 0): ?>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-info" style="width: <?php echo min(100, $percentUsed); ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($percentUsed, 1); ?>% utilized</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-list fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No donation categories available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Donations -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Donations</h6>
                    <a href="donation-history.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentDonations)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentDonations as $donation): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <h6 class="mb-0 me-3">$<?php echo number_format($donation['amount'], 2); ?></h6>
                                                <span class="badge bg-success">Completed</span>
                                            </div>
                                            <p class="mb-1 text-muted small">
                                                <strong><?php echo htmlspecialchars($donation['category_name']); ?></strong>
                                            </p>
                                            <?php if ($donation['message']): ?>
                                                <p class="mb-1 small text-muted">
                                                    "<?php echo htmlspecialchars(substr($donation['message'], 0, 60)) . (strlen($donation['message']) > 60 ? '...' : ''); ?>"
                                                </p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M d, Y', strtotime($donation['created_at'])); ?>
                                            </small>
                                        </div>
                                        <i class="fas fa-heart text-danger fa-lg"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No donations yet</h6>
                            <p class="text-muted small">Start making a difference today!</p>
                            <a href="donate.php" class="btn btn-success">
                                <i class="fas fa-heart me-2"></i>Make Your First Donation
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Donation History & Personal Impact -->
    <div class="row mt-4">
        <!-- Monthly Donation Trend -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-area me-2"></i>Your Donation Trend (Last 6 Months)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthlyTrend)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Donations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyTrend as $trend): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($trend['month_name']); ?></td>
                                            <td class="text-end">$<?php echo number_format($trend['total_amount'], 2); ?></td>
                                            <td class="text-end"><?php echo $trend['donation_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-area fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No donation history to display</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Personal Category Breakdown -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-pie-chart me-2"></i>Your Donation Categories</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($categoryBreakdown)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($categoryBreakdown as $category): ?>
                                <?php 
                                $percentage = $donationStats['total_amount'] > 0 ? ($category['total_amount'] / $donationStats['total_amount']) * 100 : 0;
                                ?>
                                <div class="list-group-item px-0 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($category['category_name']); ?></h6>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo $category['donation_count']; ?> donations</small>
                                        </div>
                                        <div class="text-end ms-3">
                                            <strong>$<?php echo number_format($category['total_amount'], 2); ?></strong>
                                            <br><small class="text-muted"><?php echo round($percentage, 1); ?>%</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-pie-chart fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No categories to display</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Community Message -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center py-4">
                    <i class="fas fa-lotus fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">From Our Monastery Community</h5>
                    <p class="card-text lead">
                        "Your generous support enables us to maintain our spiritual practice while caring for the health and wellbeing of our monastic community. 
                        Each donation, whether large or small, is received with deep gratitude and used mindfully for the benefit of all beings."
                    </p>
                    <p class="text-muted mb-0">
                        <em>- The Monastery Healthcare Team</em>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderPageFooter(); ?>