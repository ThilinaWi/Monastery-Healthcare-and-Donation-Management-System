/**
 * Common JavaScript Functions
 * Monastery Healthcare and Donation Management System
 */

// Global configuration
const Config = {
    siteUrl: window.location.origin,
    sessionTimeout: 3600000, // 1 hour in milliseconds
    warningTime: 300000, // 5 minutes warning
    debug: window.location.hostname === 'localhost'
};

// Utility Functions
const Utils = {
    
    /**
     * Show notification toast
     */
    showToast: function(message, type = 'info', duration = 5000) {
        // Remove existing toasts
        $('.toast-custom').remove();
        
        const iconMap = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        
        const toast = $(`
            <div class="toast-custom alert alert-${type} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="fas fa-${iconMap[type]} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.fadeOut(() => toast.remove());
        }, duration);
    },
    
    /**
     * Confirm dialog with custom styling
     */
    confirm: function(message, callback, title = 'Confirm Action') {
        if (confirm(message)) {
            if (typeof callback === 'function') {
                callback();
            }
            return true;
        }
        return false;
    },
    
    /**
     * Format currency
     */
    formatCurrency: function(amount) {
        return 'Rs. ' + Number(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    
    /**
     * Format date
     */
    formatDate: function(date, format = 'MMM DD, YYYY') {
        if (!date) return '';
        const d = new Date(date);
        const options = {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        };
        return d.toLocaleDateString('en-US', options);
    },
    
    /**
     * Debounce function
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },
    
    /**
     * Loading overlay
     */
    showLoading: function(message = 'Loading...') {
        if ($('#loadingOverlay').length === 0) {
            const overlay = $(`
                <div id="loadingOverlay" class="position-fixed w-100 h-100 d-flex align-items-center justify-content-center"
                     style="top: 0; left: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
                    <div class="text-center text-white">
                        <div class="spinner-border mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div>${message}</div>
                    </div>
                </div>
            `);
            $('body').append(overlay);
        }
    },
    
    hideLoading: function() {
        $('#loadingOverlay').fadeOut(() => {
            $('#loadingOverlay').remove();
        });
    },
    
    /**
     * AJAX helper with error handling
     */
    ajax: function(options) {
        const defaults = {
            method: 'POST',
            dataType: 'json',
            beforeSend: function() {
                Utils.showLoading();
            },
            complete: function() {
                Utils.hideLoading();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                Utils.showToast('Request failed. Please try again.', 'error');
            }
        };
        
        return $.ajax($.extend(defaults, options));
    },
    
    /**
     * Form validation helpers
     */
    validateForm: function(formId) {
        const form = $(formId);
        let isValid = true;
        
        form.find('[required]').each(function() {
            const field = $(this);
            if (!field.val().trim()) {
                field.addClass('is-invalid');
                isValid = false;
            } else {
                field.removeClass('is-invalid');
            }
        });
        
        return isValid;
    },
    
    /**
     * Password strength checker
     */
    checkPasswordStrength: function(password) {
        let strength = 0;
        const tests = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^a-zA-Z0-9]/.test(password)
        };
        
        Object.values(tests).forEach(test => {
            if (test) strength++;
        });
        
        if (strength < 3) return { strength: 'weak', score: strength, class: 'danger' };
        if (strength < 5) return { strength: 'medium', score: strength, class: 'warning' };
        return { strength: 'strong', score: strength, class: 'success' };
    }
};

// Session Management
const SessionManager = {
    warningTimer: null,
    logoutTimer: null,
    warningShown: false,
    
    init: function() {
        this.resetTimers();
        this.bindActivityEvents();
        
        // Check session status every 5 minutes
        setInterval(() => {
            this.checkSessionStatus();
        }, 300000);
    },
    
    resetTimers: function() {
        clearTimeout(this.warningTimer);
        clearTimeout(this.logoutTimer);
        this.warningShown = false;
        
        // Set warning timer (5 minutes before logout)
        this.warningTimer = setTimeout(() => {
            this.showWarning();
        }, Config.sessionTimeout - Config.warningTime);
        
        // Set logout timer
        this.logoutTimer = setTimeout(() => {
            this.forceLogout();
        }, Config.sessionTimeout);
    },
    
    bindActivityEvents: function() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, Utils.debounce(() => {
                if (!this.warningShown) {
                    this.extendSession();
                }
            }, 30000), true);
        });
    },
    
    showWarning: function() {
        if (this.warningShown) return;
        
        this.warningShown = true;
        
        const confirmed = confirm(
            'Your session will expire in 5 minutes due to inactivity. ' +
            'Click OK to extend your session, or Cancel to logout now.'
        );
        
        if (confirmed) {
            this.extendSession();
        } else {
            this.logout();
        }
    },
    
    extendSession: function() {
        Utils.ajax({
            url: Config.siteUrl + '/includes/extend_session.php',
            method: 'POST',
            success: (response) => {
                if (response.success) {
                    this.resetTimers();
                } else {
                    this.forceLogout();
                }
            },
            error: () => {
                // Session might be expired, check status
                this.checkSessionStatus();
            }
        });
    },
    
    checkSessionStatus: function() {
        Utils.ajax({
            url: Config.siteUrl + '/includes/check_session.php',
            method: 'POST',
            success: (response) => {
                if (!response.valid) {
                    this.forceLogout();
                }
            },
            error: () => {
                console.warn('Could not check session status');
            }
        });
    },
    
    logout: function() {
        window.location.href = Config.siteUrl + '/logout.php';
    },
    
    forceLogout: function() {
        alert('Your session has expired. You will be logged out.');
        window.location.href = Config.siteUrl + '/logout.php?reason=timeout';
    }
};

// Form Helpers
const FormHelpers = {
    
    /**
     * Initialize form enhancements
     */
    init: function() {
        this.setupFormValidation();
        this.setupPasswordFields();
        this.setupFileUploads();
        this.setupDateFields();
    },
    
    setupFormValidation: function() {
        // Real-time validation
        $('form input, form textarea, form select').on('blur', function() {
            FormHelpers.validateField($(this));
        });
        
        // Form submission handling
        $('form').on('submit', function(e) {
            const form = $(this);
            
            if (!FormHelpers.validateForm(form)) {
                e.preventDefault();
                Utils.showToast('Please correct the errors in the form.', 'error');
                return false;
            }
            
            // Show loading state
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html(
                '<i class="fas fa-spinner fa-spin me-2"></i>Processing...'
            );
            
            // Restore button after 30 seconds (timeout)
            setTimeout(() => {
                submitBtn.prop('disabled', false).html(originalText);
            }, 30000);
        });
    },
    
    validateField: function(field) {
        const value = field.val().trim();
        const type = field.attr('type');
        let isValid = true;
        
        // Required field check
        if (field.prop('required') && !value) {
            isValid = false;
        }
        
        // Type-specific validation
        if (value && type) {
            switch (type) {
                case 'email':
                    isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                    break;
                case 'tel':
                    isValid = /^[\+]?[0-9\s\-\(\)]{7,15}$/.test(value);
                    break;
                case 'url':
                    isValid = /^https?:\/\/.+/.test(value);
                    break;
            }
        }
        
        // Update field appearance
        field.toggleClass('is-invalid', !isValid);
        field.toggleClass('is-valid', isValid && value);
        
        return isValid;
    },
    
    validateForm: function(form) {
        let isValid = true;
        
        form.find('input, textarea, select').each(function() {
            if (!FormHelpers.validateField($(this))) {
                isValid = false;
            }
        });
        
        return isValid;
    },
    
    setupPasswordFields: function() {
        // Password strength indicator
        $('input[type="password"][name="password"]').on('input', function() {
            const password = $(this).val();
            const strengthInfo = Utils.checkPasswordStrength(password);
            
            let strengthBar = $(this).siblings('.password-strength');
            if (strengthBar.length === 0) {
                strengthBar = $('<div class="password-strength progress mt-2" style="height: 5px;"><div class="progress-bar"></div></div>');
                $(this).after(strengthBar);
            }
            
            const progressBar = strengthBar.find('.progress-bar');
            const percentage = (strengthInfo.score / 5) * 100;
            
            progressBar
                .removeClass('bg-danger bg-warning bg-success')
                .addClass('bg-' + strengthInfo.class)
                .css('width', percentage + '%');
        });
        
        // Password confirmation
        $('input[type="password"][name="confirm_password"]').on('input', function() {
            const password = $('input[name="password"]').val();
            const confirm = $(this).val();
            
            if (confirm && password !== confirm) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Toggle password visibility
        $('.toggle-password').on('click', function() {
            const target = $($(this).data('target'));
            const type = target.attr('type') === 'password' ? 'text' : 'password';
            target.attr('type', type);
            
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
    },
    
    setupFileUploads: function() {
        $('input[type="file"]').on('change', function() {
            const file = this.files[0];
            if (file) {
                // File size check (5MB default)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    Utils.showToast('File size must be less than 5MB', 'error');
                    $(this).val('');
                    return;
                }
                
                // Show file info
                const info = `${file.name} (${Math.round(file.size / 1024)}KB)`;
                $(this).siblings('.file-info').text(info);
            }
        });
    },
    
    setupDateFields: function() {
        // Set max date for date inputs (today)
        $('input[type="date"].max-today').attr('max', new Date().toISOString().split('T')[0]);
        
        // Set min date for date inputs (today)
        $('input[type="date"].min-today').attr('min', new Date().toISOString().split('T')[0]);
    }
};

// Data Tables Enhancement
const DataTables = {
    
    init: function() {
        if (typeof $.fn.DataTable !== 'undefined') {
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 10,
                lengthChange: true,
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                language: {
                    search: "Search records:",
                    lengthMenu: "Show _MENU_ records per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        }
    }
};

// Initialize everything when document is ready
$(document).ready(function() {
    // Initialize all modules
    FormHelpers.init();
    DataTables.init();
    
    // Initialize session management for logged-in users
    if ($('body').hasClass('logged-in') || $('.main-content').length > 0) {
        SessionManager.init();
    }
    
    // Initialize tooltips and popovers
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    // Auto-hide alerts
    setTimeout(() => {
        $('.alert-dismissible').fadeOut();
    }, 5000);
    
    // Confirmation dialogs
    $('.confirm-action').on('click', function(e) {
        e.preventDefault();
        const message = $(this).data('confirm') || 'Are you sure you want to perform this action?';
        const url = $(this).attr('href') || $(this).data('url');
        
        Utils.confirm(message, () => {
            if (url) {
                window.location.href = url;
            }
        });
    });
    
    // Loading states for buttons
    $('.btn-loading').on('click', function() {
        const btn = $(this);
        const originalText = btn.html();
        
        btn.prop('disabled', true).html(
            '<i class="fas fa-spinner fa-spin me-2"></i>Loading...'
        );
        
        // Restore after 30 seconds
        setTimeout(() => {
            btn.prop('disabled', false).html(originalText);
        }, 30000);
    });
    
    // Sidebar toggle for mobile
    $('#sidebarToggle').on('click', function() {
        $('#sidebar').toggleClass('show');
        $('.main-content').toggleClass('shifted');
    });
    
    // Close sidebar on outside click (mobile)
    $(document).on('click', function(e) {
        if ($(window).width() < 768) {
            if (!$(e.target).closest('#sidebar, #sidebarToggle').length) {
                $('#sidebar').removeClass('show');
                $('.main-content').removeClass('shifted');
            }
        }
    });
});