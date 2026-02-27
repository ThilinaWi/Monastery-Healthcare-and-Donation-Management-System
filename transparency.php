<?php
/**
 * Transparency Dashboard - Public View
 * Monastery Healthcare and Donation Management System
 */

define('INCLUDED', true);
session_start();

// Include required files
require_once 'includes/config.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

$page_title = 'Transparency Dashboard';

// Get database connection
$db = Database::getInstance();

// Get current year and month for filtering
$currentYear = intval($_GET['year'] ?? date('Y'));
$currentMonth = intval($_GET['month'] ?? date('n'));

try {
    // Get donation statistics
    $donationStats = [
        'total_received' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM donations 
            WHERE status = 'confirmed'
        ")['total'] ?? 0,
        
        'this_month' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM donations 
            WHERE status = 'confirmed' 
            AND YEAR(donation_date) = ? AND MONTH(donation_date) = ?
        ", [$currentYear, $currentMonth])['total'] ?? 0,
        
        'this_year' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM donations 
            WHERE status = 'confirmed' 
            AND YEAR(donation_date) = ?
        ", [$currentYear])['total'] ?? 0,
        
        'total_donators' => $db->fetchOne("
            SELECT COUNT(DISTINCT donator_id) as count 
            FROM donations 
            WHERE status = 'confirmed'
        ")['count'] ?? 0
    ];
    
    // Get expense statistics
    $expenseStats = [
        'total_expenses' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE status = 'approved'
        ")['total'] ?? 0,
        
        'this_month_expenses' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE status = 'approved' 
            AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?
        ", [$currentYear, $currentMonth])['total'] ?? 0,
        
        'this_year_expenses' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE status = 'approved' 
            AND YEAR(expense_date) = ?
        ", [$currentYear])['total'] ?? 0
    ];
    
    // Calculate balance
    $currentBalance = $donationStats['total_received'] - $expenseStats['total_expenses'];
    
    // Get donation breakdown by category
    $donationsByCategory = $db->fetchAll("
        SELECT dc.category_name, 
               COALESCE(SUM(d.amount), 0) as total_amount,
               COUNT(d.donation_id) as donation_count,
               dc.target_amount,
               dc.description
        FROM donation_categories dc
        LEFT JOIN donations d ON dc.category_id = d.category_id AND d.status = 'confirmed'
        WHERE dc.is_active = 1
        GROUP BY dc.category_id
        ORDER BY total_amount DESC
    ");
    
    // Get expense breakdown by category
    $expensesByCategory = $db->fetchAll("
        SELECT dc.category_name as category, 
               COALESCE(SUM(e.amount), 0) as total_amount,
               COUNT(*) as expense_count
        FROM expenses e
        LEFT JOIN donation_categories dc ON e.category_id = dc.category_id
        WHERE e.status = 'approved' 
        AND YEAR(e.expense_date) = ?
        GROUP BY e.category_id, dc.category_name
        ORDER BY total_amount DESC
    ", [$currentYear]);
    
    // Get monthly trends for the current year
    $monthlyTrends = $db->fetchAll("
        SELECT 
            MONTH(d.donation_date) as month,
            COALESCE(SUM(d.amount), 0) as donations,
            COALESCE(SUM(e.amount), 0) as expenses
        FROM (
            SELECT DISTINCT MONTH(donation_date) as month FROM donations 
            WHERE YEAR(donation_date) = ? AND status = 'confirmed'
            UNION 
            SELECT DISTINCT MONTH(expense_date) as month FROM expenses 
            WHERE YEAR(expense_date) = ? AND status = 'approved'
        ) months
        LEFT JOIN donations d ON MONTH(d.donation_date) = months.month 
            AND YEAR(d.donation_date) = ? AND d.status = 'confirmed'
        LEFT JOIN expenses e ON MONTH(e.expense_date) = months.month 
            AND YEAR(e.expense_date) = ? AND e.status = 'approved'
        GROUP BY months.month
        ORDER BY months.month
    ", [$currentYear, $currentYear, $currentYear, $currentYear]);
    
    // Get recent major donations (public)
    $recentDonations = $db->fetchAll("
        SELECT d.amount, d.donation_date, d.donation_type,
               dc.category_name,
               CASE 
                   WHEN dn.is_anonymous = 0 THEN dn.full_name 
                   ELSE 'Anonymous Donor' 
               END as donor_name
        FROM donations d
        LEFT JOIN donation_categories dc ON d.category_id = dc.category_id
        LEFT JOIN donators dn ON d.donator_id = dn.donator_id
        WHERE d.status = 'confirmed' AND d.amount >= 500
        ORDER BY d.donation_date DESC
        LIMIT 10
    ");
    
    // Get impact statistics
    $impactStats = [
        'monastics_helped' => $db->fetchOne("
            SELECT COUNT(DISTINCT monk_id) as count 
            FROM medical_records 
            WHERE YEAR(record_date) = ?
        ", [$currentYear])['count'] ?? 0,
        
        'medical_consultations' => $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE status = 'completed' AND YEAR(appointment_date) = ?
        ", [$currentYear])['count'] ?? 0,
        
        'healthcare_expenses' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE category IN ('medical', 'healthcare', 'medicine') 
            AND status = 'approved' AND YEAR(expense_date) = ?
        ", [$currentYear])['total'] ?? 0
    ];
    
    // Get upcoming projects/needs
    $upcomingProjects = $db->fetchAll("
        SELECT dc.category_name, dc.description, dc.target_amount,
               COALESCE(SUM(d.amount), 0) as raised_amount
        FROM donation_categories dc
        LEFT JOIN donations d ON dc.category_id = d.category_id AND d.status = 'confirmed'
        WHERE dc.is_active = 1 AND dc.target_amount > 0
        GROUP BY dc.category_id
        HAVING raised_amount < target_amount
        ORDER BY (raised_amount / target_amount) DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    error_log("Transparency dashboard error: " . $e->getMessage());
    
    // Initialize empty data on error
    $donationStats = ['total_received' => 0, 'this_month' => 0, 'this_year' => 0, 'total_donators' => 0];
    $expenseStats = ['total_expenses' => 0, 'this_month_expenses' => 0, 'this_year_expenses' => 0];
    $currentBalance = 0;
    $donationsByCategory = [];
    $expensesByCategory = [];
    $monthlyTrends = [];
    $recentDonations = [];
    $impactStats = ['monastics_helped' => 0, 'medical_consultations' => 0, 'healthcare_expenses' => 0];
    $upcomingProjects = [];
}

// Helper function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Helper function to calculate percentage
function calculatePercentage($current, $total) {
    return $total > 0 ? round(($current / $total) * 100, 1) : 0;
}

// Generate chart data for JavaScript
$chartColors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#6c757d'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Monastery Healthcare System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 4rem 0;
        }
        
        .hero-section h1 {
            font-size: 3rem;
            font-weight: 300;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .stat-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .section-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            color: #495057;
        }
        
        .progress-modern {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            background-color: #e9ecef;
        }
        
        .category-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .impact-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .donation-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #007bff;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .navbar-brand {
            font-weight: bold;
        }
        
        .contribution-level {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .level-bronze { background-color: #cd7f32; color: white; }
        .level-silver { background-color: #c0c0c0; color: white; }
        .level-gold { background-color: #ffd700; color: black; }
        .level-platinum { background-color: #e5e4e2; color: black; }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href=".">
                <i class="fas fa-dharma me-2"></i>
                Monastery Healthcare System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="transparency.php">
                            <i class="fas fa-eye me-1"></i>Transparency
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donate.php">
                            <i class="fas fa-heart me-1"></i>Donate Now
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Staff Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1><i class="fas fa-eye me-3"></i>Financial Transparency</h1>
            <p class="lead">See how your donations are making a real difference in our community healthcare mission</p>
            <div class="row mt-4">
                <div class="col-md-3 mb-3">
                    <div class="text-center">
                        <div class="h2 mb-0"><?php echo formatCurrency($donationStats['total_received']); ?></div>
                        <small>Total Donations Received</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center">
                        <div class="h2 mb-0"><?php echo formatCurrency($expenseStats['total_expenses']); ?></div>
                        <small>Total Funds Used</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center">
                        <div class="h2 mb-0"><?php echo formatCurrency($currentBalance); ?></div>
                        <small>Current Balance</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="text-center">
                        <div class="h2 mb-0"><?php echo number_format($donationStats['total_donators']); ?></div>
                        <small>Generous Donators</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Year/Month Filter -->
        <div class="row mb-4">
            <div class="col-md-6 offset-md-3">
                <form method="GET" class="card p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Year</label>
                            <select class="form-select" name="year" onchange="this.form.submit()">
                                <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year === $currentYear ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Month</label>
                            <select class="form-select" name="month" onchange="this.form.submit()">
                                <option value="">All Months</option>
                                <?php for ($month = 1; $month <= 12; $month++): ?>
                                    <option value="<?php echo $month; ?>" <?php echo $month === $currentMonth ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $month, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <i class="fas fa-hand-holding-heart text-success"></i>
                    <div class="stat-value text-success"><?php echo formatCurrency($donationStats['this_year']); ?></div>
                    <div class="text-muted">Donations This Year</div>
                    <small class="text-success">
                        <i class="fas fa-arrow-up me-1"></i>
                        <?php echo formatCurrency($donationStats['this_month']); ?> this month
                    </small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <i class="fas fa-receipt text-warning"></i>
                    <div class="stat-value text-warning"><?php echo formatCurrency($expenseStats['this_year_expenses']); ?></div>
                    <div class="text-muted">Expenses This Year</div>
                    <small class="text-warning">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo formatCurrency($expenseStats['this_month_expenses']); ?> this month
                    </small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <i class="fas fa-heartbeat text-danger"></i>
                    <div class="stat-value text-danger"><?php echo number_format($impactStats['medical_consultations']); ?></div>
                    <div class="text-muted">Medical Consultations</div>
                    <small class="text-success">
                        <i class="fas fa-user-friends me-1"></i>
                        <?php echo $impactStats['monastics_helped']; ?> monastics helped
                    </small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <i class="fas fa-balance-scale text-info"></i>
                    <div class="stat-value text-<?php echo $currentBalance >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo formatCurrency($currentBalance); ?>
                    </div>
                    <div class="text-muted">Current Balance</div>
                    <small class="text-muted">
                        <i class="fas fa-percent me-1"></i>
                        <?php echo calculatePercentage($expenseStats['total_expenses'], $donationStats['total_received']); ?>% utilized
                    </small>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-5">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Monthly Trends (<?php echo $currentYear; ?>)</h5>
                    <canvas id="trendsChart" height="100"></canvas>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Expense Distribution</h5>
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Donation Categories -->
        <h2 class="section-title">
            <i class="fas fa-tags me-2"></i>Donation Categories & Impact
        </h2>
        
        <div class="row mb-5">
            <?php foreach ($donationsByCategory as $category): ?>
                <div class="col-lg-6 mb-4">
                    <div class="category-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </h5>
                                <p class="text-muted small mb-0">
                                    <?php echo htmlspecialchars($category['description'] ?? ''); ?>
                                </p>
                            </div>
                            <span class="badge bg-primary"><?php echo $category['donation_count']; ?> donations</span>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Raised: <?php echo formatCurrency($category['total_amount']); ?></span>
                                <?php if ($category['target_amount'] > 0): ?>
                                    <span>Goal: <?php echo formatCurrency($category['target_amount']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($category['target_amount'] > 0): ?>
                                <?php $progress = calculatePercentage($category['total_amount'], $category['target_amount']); ?>
                                <div class="progress progress-modern">
                                    <div class="progress-bar" 
                                         style="width: <?php echo min(100, $progress); ?>%; background-color: <?php echo $category['color'] ?? '#007bff'; ?>" 
                                         role="progressbar">
                                        <?php echo $progress; ?>%
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="progress progress-modern">
                                    <div class="progress-bar bg-success" style="width: 100%" role="progressbar">
                                        Ongoing Support
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Major Donations -->
        <div class="row mb-5">
            <div class="col-lg-8">
                <h3><i class="fas fa-star me-2"></i>Recent Major Contributions</h3>
                <p class="text-muted mb-4">Recognizing our generous supporters (donations $500+)</p>
                
                <?php if (!empty($recentDonations)): ?>
                    <?php foreach ($recentDonations as $donation): ?>
                        <div class="donation-item">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <span class="h5 text-success"><?php echo formatCurrency($donation['amount']); ?></span>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-bold"><?php echo htmlspecialchars($donation['donor_name']); ?></div>
                                    <small class="text-muted">
                                        <?php echo ucfirst(str_replace('_', ' ', $donation['donation_type'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <span class="badge" style="background-color: #007bff;">
                                        <?php echo htmlspecialchars($donation['category_name'] ?? 'General'); ?>
                                    </span>
                                </div>
                                <div class="col-md-2 text-end">
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No major donations recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <h3><i class="fas fa-bullseye me-2"></i>Priority Needs</h3>
                <p class="text-muted mb-4">Categories that need your support most</p>
                
                <?php if (!empty($upcomingProjects)): ?>
                    <?php foreach ($upcomingProjects as $project): ?>
                        <?php $progress = calculatePercentage($project['raised_amount'], $project['target_amount']); ?>
                        <div class="category-card mb-3">
                            <h6 style="color: #007bff">
                                <?php echo htmlspecialchars($project['category_name']); ?>
                            </h6>
                            <p class="small text-muted mb-2">
                                <?php echo htmlspecialchars(substr($project['description'], 0, 100)) . (strlen($project['description']) > 100 ? '...' : ''); ?>
                            </p>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span><?php echo formatCurrency($project['raised_amount']); ?></span>
                                    <span><?php echo formatCurrency($project['target_amount']); ?></span>
                                </div>
                                <div class="progress progress-modern">
                                    <div class="progress-bar" 
                                         style="width: <?php echo $progress; ?>%" 
                                         role="progressbar">
                                        <?php echo $progress; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <a href="donate.php?category=<?php echo urlencode($project['category_name']); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-heart me-1"></i>Donate Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted">All current goals are being met!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="text-center py-5" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 15px;">
            <div class="text-white">
                <h3><i class="fas fa-hands-helping me-2"></i>Join Our Mission</h3>
                <p class="lead mb-4">Your donation directly supports healthcare for our monastic community</p>
                <a href="donate.php" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-heart me-2"></i>Make a Donation
                </a>
                <a href="register.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Become a Regular Supporter
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-dharma me-2"></i>Monastery Healthcare System</h6>
                    <p class="small">Providing compassionate healthcare services to our monastic community through your generous support.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6>Financial Commitment</h6>
                    <p class="small">
                        We are committed to 100% transparency in how donations are used. 
                        All financial records are maintained with the highest standards of accountability.
                    </p>
                    <div class="small text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Secure • Transparent • Accountable
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> Monastery Healthcare System. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($trend) { 
                    return date('M', mktime(0, 0, 0, $trend['month'], 1)); 
                }, $monthlyTrends)); ?>,
                datasets: [{
                    label: 'Donations',
                    data: <?php echo json_encode(array_column($monthlyTrends, 'donations')); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Expenses',
                    data: <?php echo json_encode(array_column($monthlyTrends, 'expenses')); ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Expense Distribution Chart
        const expenseCtx = document.getElementById('expenseChart').getContext('2d');
        new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($expensesByCategory, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($expensesByCategory, 'total_amount')); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($chartColors, 0, count($expensesByCategory))); ?>,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return context.label + ': $' + context.raw.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Auto-refresh every 5 minutes to show live updates
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>