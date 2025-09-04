<?php
/**
 * Enhanced Registration Page - Secure user registration with validation
 */
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php?page=dashboard');
}

// Get available groups for selection
$stmt = $pdo->prepare("SELECT group_id, group_name, description FROM groups ORDER BY group_name");
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCSRFToken();
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card auth-card">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0 text-center">
                    <i class="bi bi-person-plus"></i> Join FaithX Infinity
                </h4>
            </div>
            <div class="card-body">
                <div id="alert-container"></div>

                <form id="registerForm">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">
                                    <i class="bi bi-person"></i> First Name *
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                                <div class="invalid-feedback">Please enter your first name.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">
                                    <i class="bi bi-person"></i> Last Name *
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                                <div class="invalid-feedback">Please enter your last name.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-at"></i> Username *
                        </label>
                        <input type="text" class="form-control" id="username" name="username" pattern="[a-zA-Z0-9_]{3,20}" required>
                        <div class="form-text">3-20 characters, letters, numbers, and underscores only</div>
                        <div class="invalid-feedback">Please enter a valid username.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i> Email Address (Optional)
                        </label>
                        <input type="email" class="form-control" id="email" name="email">
                        <div class="form-text">Email is optional but recommended for notifications</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    <i class="bi bi-telephone"></i> Phone Number *
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone" pattern="[+]?[0-9\s\-()]{10,15}" required>
                                <div class="invalid-feedback">Please enter a valid phone number.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parish" class="form-label">
                                    <i class="bi bi-geo-alt"></i> Parish/Location *
                                </label>
                                <input type="text" class="form-control" id="parish" name="parish" required>
                                <div class="invalid-feedback">Please enter your parish or location.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="group_id" class="form-label">
                            <i class="bi bi-people"></i> Ministry Group (Optional)
                        </label>
                        <select class="form-select" id="group_id" name="group_id">
                            <option value="">Select a group (can be changed later)</option>
                            <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['group_id'] ?>">
                                <?= htmlspecialchars($group['group_name']) ?>
                                <?php if ($group['description']): ?>
                                - <?= htmlspecialchars($group['description']) ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> Password *
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Minimum 8 characters with uppercase, lowercase, and numbers
                                </div>
                                <div class="invalid-feedback">Password does not meet requirements.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="bi bi-lock-fill"></i> Confirm Password *
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the church's data privacy policy and terms of use *
                        </label>
                        <div class="invalid-feedback">You must agree to the terms.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center bg-light">
                <p class="mb-0">
                    Already have an account? 
                    <a href="index.php?page=login" class="text-decoration-none">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle"></i> Registration Successful</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3">Welcome to FaithX Infinity!</h4>
                <p class="text-muted">Your account has been created successfully. You can now log in with the credentials you provided.</p>
            </div>
            <div class="modal-footer">
                <a href="index.php?page=login" class="btn btn-success">Go to Login</a>
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

.form-control:focus, .form-select:focus {
    border-color: #198754;
    box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

.btn-success {
    background: linear-gradient(135deg, #198754 0%, #157347 100%);
    border: none;
    border-radius: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.4);
}

.input-group .btn-outline-secondary {
    border-color: #ced4da;
}

.form-text {
    font-size: 0.875em;
    color: #6c757d;
}

@media (max-width: 576px) {
    .auth-card .card-body {
        padding: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiEndpoint = 'api/auth_actions.php';

    // Handle Register Form
    const registerForm = document.getElementById('registerForm');
    registerForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(registerForm);
        const data = Object.fromEntries(formData.entries());

        fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            } else {
                showAlert(result.message, 'danger');
            }
        });
    });

    function showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alert-container');
        const alert = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.innerHTML = alert;
    }
});
</script>
