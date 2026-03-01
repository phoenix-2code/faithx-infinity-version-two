<?php
if (!isLoggedIn() || !hasRole('member')) {
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];

// Get member's basic info
$stmt = $pdo->prepare("
    SELECT u.*, m.group_id 
    FROM users u 
    JOIN members m ON u.user_id = m.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// Get member's group info
$stmt = $pdo->prepare("SELECT * FROM groups WHERE group_id = ?");
$stmt->execute([$member['group_id']]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

// Get member's pledge summary
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_pledges, 
           COALESCE(SUM(p.amount), 0) as total_pledged,
           COALESCE(SUM(COALESCE(t.total_paid, 0)), 0) as total_paid
    FROM pledges p 
    JOIN members m ON p.member_id = m.member_id 
    LEFT JOIN (
        SELECT pledge_id, SUM(amount_paid) as total_paid 
        FROM transactions 
        GROUP BY pledge_id
    ) t ON p.pledge_id = t.pledge_id
    WHERE m.user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent pledges
$stmt = $pdo->prepare("
    SELECT p.*, pc.category_name,
           COALESCE(SUM(t.amount_paid), 0) as amount_paid
    FROM pledges p 
    JOIN members m ON p.member_id = m.member_id 
    JOIN pledge_categories pc ON p.category_id = pc.category_id
    LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
    WHERE m.user_id = ?
    GROUP BY p.pledge_id
    ORDER BY p.pledge_date DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pledge categories for quick pledge
$stmt = $pdo->prepare("SELECT * FROM pledge_categories WHERE is_active = 1 ORDER BY category_name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <?php if (empty($member)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> Your member profile could not be loaded. Please contact an administrator.
        </div>
    <?php else: ?>
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-0">
                        Welcome, <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                    </h4>
                    <p class="text-muted">
                        Group: <?= htmlspecialchars($group['group_name'] ?? 'Not Assigned') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Total Pledges</h6>
                            <h2 class="mb-0"><?= number_format($stats['total_pledges']) ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-list-check fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Total Pledged</h6>
                            <h2 class="mb-0"><?= formatCurrency($stats['total_pledged']) ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-cash-stack fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Total Paid</h6>
                            <h2 class="mb-0"><?= formatCurrency($stats['total_paid']) ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#quickPledgeModal">
                                <i class="bi bi-plus-circle"></i> Make New Pledge
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=pledges" class="btn btn-success w-100 mb-2">
                                <i class="bi bi-list-check"></i> View All Pledges
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=member_payment_submission" class="btn btn-info w-100 mb-2">
                                <i class="bi bi-credit-card"></i> Submit Payment
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=profile" class="btn btn-secondary w-100 mb-2">
                                <i class="bi bi-person"></i> Update Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Pledges -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Pledges</h5>
                    <a href="index.php?page=pledges" class="btn btn-sm btn-outline-light">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_pledges)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-folder text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No pledges found</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickPledgeModal">
                                Make Your First Pledge
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_pledges as $pledge): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pledge['category_name']) ?></td>
                                            <td><?= formatCurrency($pledge['amount']) ?></td>
                                            <td><?= formatCurrency($pledge['amount_paid']) ?></td>
                                            <td>
                                                <?php
                                                $progress = ($pledge['amount'] > 0) ? 
                                                    ($pledge['amount_paid'] / $pledge['amount']) * 100 : 0;
                                                $progress_class = $progress >= 100 ? 'bg-success' : 
                                                    ($progress >= 50 ? 'bg-info' : 'bg-warning');
                                                ?>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar <?= $progress_class ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $progress ?>%" 
                                                         aria-valuenow="<?= $progress ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100"></div>
                                                </div>
                                                <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                            </td>
                                            <td><?= getStatusBadge($pledge['status']) ?></td>
                                            <td><?= formatDate($pledge['pledge_date']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Pledge Modal -->
<div class="modal fade" id="quickPledgeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="quickPledgeForm" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create_pledge">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Make New Pledge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select a category...</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>">
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               min="0.01" step="0.01" required>
                        <div class="invalid-feedback">Please enter a valid amount.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_schedule" class="form-label">Payment Schedule</label>
                        <select class="form-select" id="payment_schedule" name="payment_schedule">
                            <option value="one_time">One Time</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annually">Annually</option>
                        </select>
                    </div>
                    
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Create Pledge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quick pledge form submission
    const quickPledgeForm = document.getElementById('quickPledgeForm');
    if (quickPledgeForm) {
        quickPledgeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (window.FaithXUtils && window.FaithXUtils.showLoading) {
                    window.FaithXUtils.showLoading(submitBtn);
                }

                const response = await fetch('api/pledge_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                if (window.FaithXUtils && window.FaithXUtils.hideLoading) {
                    window.FaithXUtils.hideLoading(submitBtn, '<i class="bi bi-check-circle"></i> Create Pledge');
                }
                
                if (result.success) {
                    alert('Pledge created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while creating the pledge.');
            }
        });
    }
    
    // Form validation for other forms
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        if (form.id !== 'quickPledgeForm') {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        }
    });
});
</script>
