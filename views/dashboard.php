<?php
// Dashboard - Simple and functional
if (!isLoggedIn()) {
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user's basic info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle different dashboard types based on role
if ($role === 'chief_finance_officer') {
    // CFO gets church-wide financial overview
    include 'views/cfo_dashboard.php';
    return;
} elseif ($role === 'finance_officer') {
    // Finance Officer gets group-specific dashboard
    include 'views/fo_dashboard.php';
    return;
} elseif ($role === 'system_admin') {
    // System Admin gets comprehensive system overview
    include 'views/system_admin_dashboard.php';
    return;
} elseif ($role === 'member') {
    // Member gets their personal dashboard
    include 'views/member_dashboard.php';
    return;
} elseif ($role === 'elder_admin') {
    // Elder Admin gets church-wide summary without individual details
    // Church-wide Financial Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.user_id) as total_members,
            COUNT(DISTINCT p.pledge_id) as total_pledges,
            COALESCE(SUM(p.amount), 0) as total_pledged,
            COALESCE(SUM(COALESCE(t.total_paid, 0)), 0) as total_paid,
            COALESCE(SUM(p.amount - COALESCE(t.total_paid, 0)), 0) as total_outstanding
        FROM users u
        LEFT JOIN members m ON u.user_id = m.user_id
        LEFT JOIN pledges p ON m.member_id = p.member_id
        LEFT JOIN (
            SELECT pledge_id, SUM(amount_paid) as total_paid 
            FROM transactions 
            GROUP BY pledge_id
        ) t ON p.pledge_id = t.pledge_id
        WHERE u.role = 'member'
    ");
    $stmt->execute();
    $church_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Historical Financial Data (Last 12 Months)
    $monthly_financial_data = [];
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(p.pledge_date, '%Y-%m') as month,
            COALESCE(SUM(p.amount), 0) as pledged_amount,
            COALESCE(SUM(t.amount_paid), 0) as paid_amount
        FROM pledges p
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
        WHERE p.pledge_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthly_financial_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pledge Category Breakdown
    $category_breakdown = [];
    $stmt = $pdo->prepare("
        SELECT
            pc.category_name,
            COALESCE(SUM(p.amount), 0) as total_pledged_in_category,
            COALESCE(SUM(t.amount_paid), 0) as total_paid_in_category
        FROM pledge_categories pc
        LEFT JOIN pledges p ON pc.category_id = p.category_id
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
        GROUP BY pc.category_name
        ORDER BY total_pledged_in_category DESC
    ");
    $stmt->execute();
    $category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Building Fund Projects Progress
    $building_fund_projects = [];
    $stmt = $pdo->prepare("
        SELECT
            project_name,
            target_amount,
            current_amount,
            status
        FROM building_fund_projects
        WHERE status = 'active' OR status = 'planning'
        ORDER BY target_amount DESC
    ");
    $stmt->execute();
    $building_fund_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Comparison Data (Current Month vs. Previous Month)
    $current_month = date('Y-m');
    $previous_month = date('Y-m', strtotime('first day of last month'));

    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(p.pledge_date, '%Y-%m') as month,
            COALESCE(SUM(p.amount), 0) as pledged_amount,
            COALESCE(SUM(t.amount_paid), 0) as paid_amount
        FROM pledges p
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
        WHERE DATE_FORMAT(p.pledge_date, '%Y-%m') IN (?, ?)
        GROUP BY month
    ");
    $stmt->execute([$current_month, $previous_month]);
    $comparison_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $comparison_data = [
        'current_month' => ['pledged' => 0, 'paid' => 0],
        'previous_month' => ['pledged' => 0, 'paid' => 0]
    ];
    foreach ($comparison_data_raw as $data) {
        if ($data['month'] === $current_month) {
            $comparison_data['current_month']['pledged'] = $data['pledged_amount'];
            $comparison_data['current_month']['paid'] = $data['paid_amount'];
        } elseif ($data['month'] === $previous_month) {
            $comparison_data['previous_month']['pledged'] = $data['pledged_amount'];
            $comparison_data['previous_month']['paid'] = $data['paid_amount'];
        }
    }

    $stats = $church_stats;
    $recent_pledges = []; // Elder admin doesn't see individual pledges

    // Prepare data for JavaScript charts
    $chart_data = [
        'monthly_financial_data' => $monthly_financial_data,
        'category_breakdown' => $category_breakdown,
        'building_fund_projects' => $building_fund_projects,
        'comparison_data' => $comparison_data
    ];
    
} elseif ($role === 'member') {
    // Member dashboard - show only their pledges
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

    // Get pledge status breakdown for member
    $pledge_status_breakdown = [];
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        WHERE m.user_id = ?
        GROUP BY status
    ");
    $stmt->execute([$user_id]);
    $pledge_status_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for JavaScript charts
    $chart_data = [
        'pledge_status_breakdown' => $pledge_status_breakdown
    ];
    
    // Get recent pledges
    $stmt = $pdo->prepare("
        SELECT p.pledge_id, pc.category_name, p.amount as pledged_amount, 
               COALESCE(t.total_paid, 0) as paid_amount,
               p.pledge_date, p.status, p.notes
        FROM pledges p 
        JOIN members m ON p.member_id = m.member_id 
        JOIN pledge_categories pc ON p.category_id = pc.category_id
        LEFT JOIN (
            SELECT pledge_id, SUM(amount_paid) as total_paid 
            FROM transactions 
            GROUP BY pledge_id
        ) t ON p.pledge_id = t.pledge_id
        WHERE m.user_id = ? 
        ORDER BY p.pledge_date DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Finance Officer/System Admin dashboard - show all pledges
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_pledges, 
               COALESCE(SUM(p.amount), 0) as total_pledged,
               COALESCE(SUM(COALESCE(t.total_paid, 0)), 0) as total_paid
        FROM pledges p
        LEFT JOIN (
            SELECT pledge_id, SUM(amount_paid) as total_paid 
            FROM transactions 
            GROUP BY pledge_id
        ) t ON p.pledge_id = t.pledge_id
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent pledges with member info
    $stmt = $pdo->prepare("
        SELECT p.pledge_id, pc.category_name, p.amount as pledged_amount, 
               COALESCE(t.total_paid, 0) as paid_amount,
               p.pledge_date, p.status, p.notes, u.first_name, u.last_name
        FROM pledges p 
        JOIN members m ON p.member_id = m.member_id
        JOIN users u ON m.user_id = u.user_id
        JOIN pledge_categories pc ON p.category_id = pc.category_id
        LEFT JOIN (
            SELECT pledge_id, SUM(amount_paid) as total_paid 
            FROM transactions 
            GROUP BY pledge_id
        ) t ON p.pledge_id = t.pledge_id
        ORDER BY p.pledge_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2>Welcome, <?= sanitize($user['first_name']) ?>!</h2>
            <p class="text-muted">Role: <?= ucfirst(str_replace('_', ' ', $role)) ?></p>
        </div>
    </div>

    <?php if ($role === 'elder_admin'): ?>
    <!-- Elder Admin Church-wide Stats -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?= number_format($stats['total_members']) ?></h4>
                    <small>Total Members</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?= number_format($stats['total_pledges']) ?></h4>
                    <small>Total Pledges</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?= formatCurrency($stats['total_pledged']) ?></h4>
                    <small>Total Pledged</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h4><?= formatCurrency($stats['total_paid']) ?></h4>
                    <small>Total Paid</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h4><?= formatCurrency($stats['total_outstanding']) ?></h4>
                    <small>Outstanding</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Church Summary for Elder Admin -->
    <?php if (!empty($group_summaries)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart"></i> Church Financial Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Group</th>
                                    <th>Active Members</th>
                                    <th>Total Pledges</th>
                                    <th>Amount Pledged</th>
                                    <th>Amount Paid</th>
                                    <th>Remaining</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group_summaries as $group): ?>
                                <tr>
                                    <td><?= sanitize($group['group_name']) ?></td>
                                    <td><?= number_format($group['active_members']) ?></td>
                                    <td><?= number_format($group['total_pledges']) ?></td>
                                    <td><?= formatCurrency($group['total_pledged']) ?></td>
                                    <td><?= formatCurrency($group['total_paid']) ?></td>
                                    <td><?= formatCurrency($group['remaining_amount']) ?></td>
                                    <td>
                                        <?php 
                                        $progress = $group['total_pledged'] > 0 ? ($group['total_paid'] / $group['total_pledged']) * 100 : 0;
                                        ?>
                                        <div class="progress" style="width: 100px;">
                                            <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Regular Stats Cards for other roles -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5>Total Pledges</h5>
                            <h3><?= number_format($stats['total_pledges']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-list-check" style="font-size: 2rem;"></i>
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
                            <h5>Total Pledged</h5>
                            <h3><?= formatCurrency($stats['total_pledged']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar" style="font-size: 2rem;"></i>
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
                            <h5>Total Paid</h5>
                            <h3><?= formatCurrency($stats['total_paid']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0">
                        <?php if ($role === 'elder_admin'): ?>
                        Administrative Actions
                        <?php elseif ($role === 'chief_finance_officer'): ?>
                        CFO Actions
                        <?php else: ?>
                        Quick Actions
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($role === 'member'): ?>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#quickPledgeModal">
                                <i class="bi bi-plus-circle"></i> Make Pledge
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=pledges" class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-list-ul"></i> View My Pledges
                            </a>
                        </div>
                        
                        <?php elseif ($role === 'elder_admin'): ?>
                        <div class="col-md-3">
                            <a href="index.php?page=reports" class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-graph-up"></i> Church Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=pledges" class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-list-check"></i> Review Pledges
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=payments" class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-credit-card"></i> Payment Overview
                            </a>
                        </div>
                        
                        <?php elseif ($role === 'chief_finance_officer'): ?>
                        <div class="col-md-3">
                            <a href="index.php?page=reports" class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-graph-up"></i> Financial Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=pledges" class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-list-check"></i> All Church Pledges
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=payments" class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-credit-card"></i> Payment Management
                            </a>
                        </div>
                        
                        <?php else: ?>
                        <!-- Finance Officer / System Admin -->
                        <div class="col-md-3">
                            <a href="index.php?page=pledges" class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-list-check"></i> Manage Pledges
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=payments" class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-credit-card"></i> Record Payments
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="index.php?page=reports" class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-graph-up"></i> View Reports
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <a href="index.php?page=profile" class="btn btn-outline-secondary w-100 mb-2">
                                <i class="bi bi-person"></i> My Profile
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
                <div class="card-header gradient-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history"></i> 
                        <?= $role === 'member' ? 'My Recent Pledges' : 'Recent Pledges' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_pledges)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                        <p class="text-muted mt-3">No pledges found.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <?php if ($role !== 'member'): ?>
                                    <th>Member</th>
                                    <?php endif; ?>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_pledges as $pledge): ?>
                                <tr class="<?= getOverdueIndicator($pledge) ?>">
                                    <td><?= sanitize($pledge['category_name']) ?></td>
                                    <?php if ($role !== 'member'): ?>
                                    <td><?= sanitize(($pledge['first_name'] ?? '') . ' ' . ($pledge['last_name'] ?? '')) ?></td>
                                    <?php endif; ?>
                                    <td><?= formatCurrency($pledge['pledged_amount']) ?></td>
                                    <td><?= formatCurrency($pledge['paid_amount']) ?></td>
                                    <td>
                                        <?php 
                                        $progress = $pledge['pledged_amount'] > 0 ? ($pledge['paid_amount'] / $pledge['pledged_amount']) * 100 : 0;
                                        $progress = min($progress, 100);
                                        $progress_class = $progress >= 100 ? 'bg-success' : ($progress >= 50 ? 'bg-warning' : 'bg-info');
                                        ?>
                                        <div class="progress" style="width: 100px;">
                                            <div class="progress-bar <?= $progress_class ?>" style="width: <?= $progress ?>%"></div>
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

<!-- Quick Pledge Modal for Members -->
<?php if ($role === 'member'): ?>
<?php
// Get pledge categories for the modal
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category_id, category_name, description FROM pledge_categories WHERE is_active = 1 ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}
$csrf_token = generateCSRFToken();
?>

<div class="modal fade" id="quickPledgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="index.php?page=pledges" class="needs-validation" novalidate>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-gift"></i> Make a New Pledge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="create_pledge">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Create a new pledge commitment quickly from your dashboard.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quick_category_id" class="form-label">Category</label>
                                <select class="form-select" id="quick_category_id" name="category_id" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>">
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quick_amount" class="form-label">Pledge Amount</label>
                                <input type="number" class="form-control" id="quick_amount" name="amount" 
                                       step="0.01" min="0.01" required>
                                <div class="invalid-feedback">Please enter a valid amount.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quick_payment_schedule" class="form-label">Payment Schedule</label>
                                <select class="form-select" id="quick_payment_schedule" name="payment_schedule">
                                    <option value="one_time">One Time</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly" selected>Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="annually">Annually</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quick_target_date" class="form-label">Target Completion Date</label>
                                <input type="date" class="form-control" id="quick_target_date" name="target_date">
                                <small class="text-muted">Optional: When you plan to complete this pledge</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="quick_notes" name="notes" rows="2" 
                                  placeholder="Optional notes about this pledge"></textarea>
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
    // Form validation for quick pledge modal
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>