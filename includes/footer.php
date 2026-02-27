<?php
/**
 * Common Footer File
 * Monastery Healthcare and Donation Management System
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

$current_role = $_SESSION['role'] ?? null;
?>

<?php if (isset($current_role) && in_array($current_role, ['admin', 'monk', 'doctor', 'donator'])): ?>
    <!-- Dashboard Footer -->
    </div> <!-- Close main-content -->
    
    <footer class="bg-light border-top mt-5 py-3" style="margin-left: 250px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        <i class="fas fa-lotus text-primary me-2"></i>
                        &copy; <?php echo date('Y'); ?> Monastery Healthcare System
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Version 1.0 | Built with ❤️ for the community
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
<?php else: ?>
    <!-- Public Footer -->
    </main> <!-- Close main content -->
    
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5>
                        <i class="fas fa-lotus text-warning me-2"></i>
                        Monastery System
                    </h5>
                    <p class="mb-3">
                        A comprehensive digital platform for managing healthcare services 
                        and donations in our monastery community.
                    </p>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-uppercase mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/login.php" class="text-white-50 text-decoration-none">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/register.php" class="text-white-50 text-decoration-none">Register</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">About Us</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="text-uppercase mb-3">Services</h6>
                    <ul class="list-unstyled">
                        <li><span class="text-white-50">Healthcare Management</span></li>
                        <li><span class="text-white-50">Donation Tracking</span></li>
                        <li><span class="text-white-50">Financial Reports</span></li>
                        <li><span class="text-white-50">Appointment Booking</span></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 mb-4">
                    <h6 class="text-uppercase mb-3">Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt text-warning me-2"></i>
                            <span class="text-white-50">Monastery Address<br>City, Province, Sri Lanka</span>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone text-warning me-2"></i>
                            <span class="text-white-50">+94 XX XXX XXXX</span>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope text-warning me-2"></i>
                            <span class="text-white-50"><?php echo ADMIN_EMAIL ?? 'admin@monastery.com'; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="border-secondary my-4">
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <p class="mb-0 text-white-50">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME ?? 'Monastery Healthcare System'; ?>. 
                        All rights reserved.
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="mb-0">
                        <a href="#" class="text-white-50 text-decoration-none me-3">Privacy Policy</a>
                        <a href="#" class="text-white-50 text-decoration-none">Terms of Service</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (for easier DOM manipulation) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo SITE_URL; ?>/assets/js/common.js"></script>

<?php if (isset($current_role) && in_array($current_role, ['admin', 'monk', 'doctor', 'donator'])): ?>
<!-- Dashboard Specific Scripts -->
<script>
$(document).ready(function() {
    // Sidebar toggle functionality
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('collapsed');
        $('#mainContent').toggleClass('expanded');
        $('.navbar-custom').toggleClass('expanded');
    });
    
    // Mobile sidebar toggle
    if (window.innerWidth <= 768) {
        $('#sidebarToggle').click(function() {
            $('#sidebar').toggleClass('show');
        });
        
        // Close sidebar when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('#sidebar, #sidebarToggle').length) {
                $('#sidebar').removeClass('show');
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Form validation enhancement
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').addClass('disabled').html(
            '<i class="fas fa-spinner fa-spin me-2"></i>Processing...'
        );
    });
    
    // Tooltip initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Confirmation dialogs for delete actions
    $('.delete-btn, .btn-danger[data-confirm]').click(function(e) {
        e.preventDefault();
        const message = $(this).data('confirm') || 'Are you sure you want to delete this item?';
        if (confirm(message)) {
            window.location.href = $(this).attr('href');
        }
    });
});

// Navbar style on scroll (for dashboard)
$(window).scroll(function() {
    if ($(this).scrollTop() > 50) {
        $('.navbar-custom').addClass('scrolled');
    } else {
        $('.navbar-custom').removeClass('scrolled');
    }
});
</script>

<?php else: ?>
<!-- Public Page Scripts -->
<script>
$(document).ready(function() {
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(event) {
        var target = $(this.getAttribute('href'));
        if( target.length ) {
            event.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 70
            }, 1000);
        }
    });
    
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Form validation and loading states
    $('form').on('submit', function() {
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.addClass('disabled').html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
        
        // Restore button after 10 seconds (in case form doesn't redirect)
        setTimeout(function() {
            submitBtn.removeClass('disabled').html(originalText);
        }, 10000);
    });
});

// Add navbar background on scroll
$(window).scroll(function() {
    if ($(this).scrollTop() > 50) {
        $('.navbar').addClass('shadow');
    } else {
        $('.navbar').removeClass('shadow');
    }
});
</script>
<?php endif; ?>

<!-- Additional JavaScript -->
<?php if (isset($additional_js)): ?>
    <?php foreach ($additional_js as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Page-specific JavaScript -->
<?php if (isset($page_js)): ?>
    <script><?php echo $page_js; ?></script>
<?php endif; ?>

</body>
</html>