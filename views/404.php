<?php
/**
 * 404 Error Page - User-friendly page not found
 */

// Log 404 for admin review
if (function_exists('ErrorHandler::logCustomError')) {
    ErrorHandler::logCustomError('404 Page Not Found', [
        'requested_page' => $_GET['page'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
    ]);
}
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center" style="min-height: 60vh;">
        <div class="col-md-6 text-center">
            <div class="error-container">
                <div class="error-icon mb-4">
                    <i class="bi bi-compass" style="font-size: 5rem; color: #6c757d;"></i>
                </div>
                
                <h1 class="display-4 fw-bold text-primary mb-3">404</h1>
                <h2 class="h4 mb-3">Page Not Found</h2>
                
                <p class="text-muted mb-4">
                    Sorry, the page you're looking for doesn't exist or has been moved. 
                    Let's get you back on track!
                </p>
                
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Go Back
                    </a>
                    
                    <?php if (isLoggedIn()): ?>
                    <a href="index.php?page=dashboard" class="btn btn-primary">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                    <?php else: ?>
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-house"></i> Home
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if (isLoggedIn()): ?>
                <div class="mt-4">
                    <h6 class="text-muted mb-3">Quick Links:</h6>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="index.php?page=pledges" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-list-check"></i> Pledges
                        </a>
                        <a href="index.php?page=payments" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-credit-card"></i> Payments
                        </a>
                        <a href="index.php?page=reports" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-graph-up"></i> Reports
                        </a>
                        <a href="index.php?page=profile" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-5">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        If you believe this is an error, please contact your system administrator.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.error-container {
    padding: 2rem;
    border-radius: 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.error-icon {
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.btn {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-sm {
    padding: 0.25rem 1rem;
    font-size: 0.875rem;
}

@media (max-width: 576px) {
    .error-container {
        padding: 1.5rem;
        margin: 1rem;
    }
    
    .display-4 {
        font-size: 3rem;
    }
    
    .d-flex.gap-2 {
        gap: 0.5rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add some interactive elements
    const errorIcon = document.querySelector('.error-icon i');
    
    if (errorIcon) {
        errorIcon.addEventListener('click', function() {
            this.style.transform = 'rotate(360deg)';
            this.style.transition = 'transform 0.6s ease';
            
            setTimeout(() => {
                this.style.transform = '';
                this.style.transition = '';
            }, 600);
        });
    }
    
    // Auto-focus on the primary action button
    const primaryBtn = document.querySelector('.btn-primary');
    if (primaryBtn) {
        setTimeout(() => {
            primaryBtn.focus();
        }, 500);
    }
});
</script>