<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php?page=dashboard');
}

$auth = new AuthSystem($pdo);
$login_error = '';
$account_locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Security token mismatch. Please try again.');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $login_error = 'Both username and password are required.';
        } else {
            $result = $auth->login($username, $password);
            
            if ($result['success']) {
                // Successful login - redirect to appropriate dashboard
                $role = $result['effective_role'];
                if ($role === 'chief_finance_officer') {
                    redirect('index.php?page=cfo_dashboard');
                } elseif ($role === 'system_admin') {
                    redirect('index.php?page=system_admin_dashboard');
                } else {
                    redirect('index.php?page=dashboard');
                }
            } else {
                $login_error = $result['message'];
                $account_locked = $result['locked'] ?? false;
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card auth-card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0 text-center">
                    <i class="bi bi-shield-lock"></i> FaithX Infinity Login
                </h4>
            </div>
            <div class="card-body">
                <?php if ($login_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($login_error) ?>
                    <?php if ($account_locked): ?>
                    <br><small class="text-muted">Account temporarily locked for security. Please try again in 15 minutes.</small>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person"></i> Username
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required 
                               autocomplete="username"
                               <?= $account_locked ? 'disabled' : '' ?>>
                        <div class="invalid-feedback">
                            Please enter your username.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   autocomplete="current-password"
                                   <?= $account_locked ? 'disabled' : '' ?>>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    id="togglePassword"
                                    <?= $account_locked ? 'disabled' : '' ?>>
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">
                            Remember me
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" 
                                class="btn btn-primary btn-lg" 
                                <?= $account_locked ? 'disabled' : '' ?>>
                            <i class="bi bi-box-arrow-in-right"></i> 
                            <?= $account_locked ? 'Account Locked' : 'Sign In' ?>
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">
                     <!--   <i class="bi bi-info-circle"></i> 
                        Session timeout: 30 minutes | Max attempts: 5
                    </small> -->
                </div>
            </div>
            <div class="card-footer text-center bg-light">
                <p class="mb-2">
                    <a href="index.php?page=forgot_password" class="text-decoration-none">
                        <i class="bi bi-question-circle"></i> Forgot Password?
                    </a>
                </p>
                <p class="mb-0">
                    Don't have an account? 
                    <a href="index.php?page=register" class="text-decoration-none">
                        <i class="bi bi-person-plus"></i> Register here
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.auth-card {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-radius: 1rem;
    overflow: hidden;
}

.auth-card .card-header {
    border-bottom: none;
    padding: 1.5rem;
}

.auth-card .card-body {
    padding: 2rem;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    border: none;
    border-radius: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
}

.btn-primary:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.input-group .btn-outline-secondary {
    border-color: #ced4da;
}

.alert {
    border-radius: 0.5rem;
    border: none;
}

.card-footer {
    border-top: 1px solid rgba(0,0,0,.125);
}

@media (max-width: 576px) {
    .auth-card .card-body {
        padding: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    
    if (togglePassword && passwordField) {
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    }

    // Form validation
    const form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }

    // Auto-focus username field
    const usernameField = document.getElementById('username');
    if (usernameField && !usernameField.disabled) {
        usernameField.focus();
    }

    // Clear form on page load if there was an error
    <?php if ($login_error && !$account_locked): ?>
    setTimeout(function() {
        const passwordField = document.getElementById('password');
        if (passwordField) passwordField.value = '';
    }, 100);
    <?php endif; ?>
});
</script>