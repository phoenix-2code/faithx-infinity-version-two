<?php
// Profile page - View and update user profile
if (!isLoggedIn()) {
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];

try {
    // Get user and member information
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name, u.parish, u.role, u.created_at,
               m.phone, m.group_id, m.join_date,
               g.group_name
        FROM users u
        LEFT JOIN members m ON u.user_id = m.user_id
        LEFT JOIN groups g ON m.group_id = g.group_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's pledge statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_pledges,
            SUM(amount) as total_pledged,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_completed,
            SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as total_active
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        WHERE m.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT p.*, pc.category_name, p.amount as pledged_amount,
               COALESCE(SUM(t.amount_paid), 0) as paid_amount
        FROM pledges p
        JOIN pledge_categories pc ON p.category_id = pc.category_id
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
        WHERE m.user_id = ?
        GROUP BY p.pledge_id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    setFlashMessage('danger', 'Error loading profile data.');
    $profile = [];
    $stats = ['total_pledges' => 0, 'total_pledged' => 0, 'total_completed' => 0, 'total_active' => 0];
    $recent_activity = [];
}
?>

<!-- Back to Dashboard Button -->
<div class="mb-3">
    <a href="index.php?page=dashboard" class="btn btn-outline-primary">
        <i class="bi bi-house"></i> Back to Dashboard
    </a>
</div>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">
            <i class="bi bi-person-circle"></i> My Profile
        </h2>
    </div>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
            </div>
            <div class="card-body">
                <form id="updateProfileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= sanitize($profile['first_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= sanitize($profile['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= sanitize($profile['email'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= sanitize($profile['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parish" class="form-label">Parish</label>
                                <input type="text" class="form-control" id="parish" name="parish" 
                                       value="<?= sanitize($profile['parish'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" 
                                       value="<?= sanitize($profile['username'] ?? '') ?>" readonly>
                                <div class="form-text">Username cannot be changed</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" 
                                       value="<?= ucfirst(str_replace('_', ' ', $profile['role'] ?? '')) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="group" class="form-label">Group</label>
                                <input type="text" class="form-control" id="group" 
                                       value="<?= sanitize($profile['group_name'] ?? 'Not assigned') ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Profile Summary -->
    <div class="col-md-4">
        <!-- Account Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> My Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="text-primary"><?= number_format($stats['total_pledges']) ?></h4>
                        <small class="text-muted">Total Pledges</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-success"><?= formatCurrency($stats['total_pledged']) ?></h4>
                        <small class="text-muted">Total Pledged</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-info"><?= formatCurrency($stats['total_completed']) ?></h4>
                        <small class="text-muted">Completed</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-warning"><?= formatCurrency($stats['total_active']) ?></h4>
                        <small class="text-muted">Active</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Account Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Member Since:</strong><br>
                    <span class="text-muted"><?= $profile['join_date'] ? formatDate($profile['join_date']) : 'N/A' ?></span>
                </div>
                <div class="mb-3">
                    <strong>Account Created:</strong><br>
                    <span class="text-muted"><?= formatDate($profile['created_at'] ?? '') ?></span>
                </div>
                <div class="mb-3">
                    <strong>Last Login:</strong><br>
                    <span class="text-muted"><?= isset($profile['last_login']) && $profile['last_login'] ? formatDate($profile['last_login']) : 'Never' ?></span>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activity)): ?>
                <p class="text-muted">No recent activity</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= sanitize($activity['category_name']) ?></strong><br>
                                <small class="text-muted"><?= formatCurrency($activity['pledged_amount']) ?></small>
                            </div>
                            <div class="text-end">
                                <?= getStatusBadge($activity['status']) ?><br>
                                <small class="text-muted"><?= formatDate($activity['pledge_date']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals for feedback -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="feedbackModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: box-shadow 0.15s ease-in-out;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiEndpoint = 'api/auth_actions.php';

    // Function to show feedback modal
    function showFeedbackModal(title, message, isSuccess) {
        const modalTitle = document.getElementById('feedbackModalTitle');
        const modalBody = document.getElementById('feedbackModalBody');
        const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));

        modalTitle.textContent = title;
        modalBody.innerHTML = `<p>${message}</p>`;
        
        // Optional: Change modal header color based on success/failure
        const modalHeader = feedbackModal._element.querySelector('.modal-header');
        if (isSuccess) {
            modalHeader.classList.remove('bg-danger', 'bg-warning');
            modalHeader.classList.add('bg-success');
        } else {
            modalHeader.classList.remove('bg-success', 'bg-warning');
            modalHeader.classList.add('bg-danger');
        }

        feedbackModal.show();
    }

    // Handle Update Profile Form
    const updateProfileForm = document.getElementById('updateProfileForm');
    updateProfileForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(updateProfileForm);
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
            showFeedbackModal(result.success ? 'Success' : 'Error', result.message, result.success);
            if (result.success) {
                // Reload page to reflect changes
                location.reload();
            }
        });
    });

    // Handle Change Password Form
    const changePasswordForm = document.getElementById('changePasswordForm');
    changePasswordForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(changePasswordForm);
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
            showFeedbackModal(result.success ? 'Success' : 'Error', result.message, result.success);
            if (result.success) {
                // Clear password fields
                document.getElementById('current_password').value = '';
                document.getElementById('new_password').value = '';
                document.getElementById('confirm_password').value = '';
            }
        });
    });
});
</script>