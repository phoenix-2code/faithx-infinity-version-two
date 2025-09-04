<?php
/**
 * Simplified Reports Page
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Initialize filter variables
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Default to today's date

// Check permissions for reports
$can_view_reports = in_array($role, ['finance_officer', 'chief_finance_officer', 'elder_admin', 'system_admin', 'member']);

if (!$can_view_reports) {
    redirect('index.php?page=dashboard');
}

// Get basic statistics
$stats = [];
try {
    // Get pledge statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(p.pledge_id) as total_pledges,
            COALESCE(SUM(p.amount), 0) as total_pledged,
            COALESCE(SUM(t.amount_paid), 0) as total_paid
        FROM pledges p
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_remaining'] = $stats['total_pledged'] - $stats['total_paid'];
    
    $recent_pledges = []; // Initialize as empty
    $total_rp_pages = 0; // Initialize total pages to 0
    
} catch (Exception $e) {
    $error_message = "Error loading report data. Please try again later.";
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $error_message .= "<br><br><strong>Debug Info:</strong> " . $e->getMessage();
    }
}

// Fetch data for visualizations based on role
$chart_data = [];
if ($role === 'elder_admin') {
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

    $chart_data = [
        'monthly_financial_data' => $monthly_financial_data,
        'category_breakdown' => $category_breakdown,
        'building_fund_projects' => $building_fund_projects,
        'comparison_data' => $comparison_data
    ];
} elseif ($role === 'member') {
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

    $chart_data = [
        'pledge_status_breakdown' => $pledge_status_breakdown
    ];
} elseif ($role === 'finance_officer') {
    // Get FO's group
    $rbac = new RBAC($pdo, $user_id);
    $groups = $rbac->getAccessibleGroups('finance_officer');
    $group = !empty($groups) ? $groups[0] : null;
    $group_id = $group['group_id'] ?? null;

    if ($group_id) {
        // Get group pledge status breakdown
        $group_pledge_status_breakdown = [];
        $stmt = $pdo->prepare("
            SELECT p.status, COUNT(*) as count, COALESCE(SUM(p.amount), 0) as total_amount
            FROM pledges p
            JOIN members m ON p.member_id = m.member_id
            WHERE m.group_id = ?
            GROUP BY p.status
        ");
        $stmt->execute([$group_id]);
        $group_pledge_status_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get monthly group payments
        $monthly_group_payments = [];
        $stmt = $pdo->prepare("
            SELECT
                DATE_FORMAT(t.payment_date, '%Y-%m') as month,
                COALESCE(SUM(t.amount_paid), 0) as total_paid
            FROM transactions t
            JOIN pledges p ON t.pledge_id = p.pledge_id
            JOIN members m ON p.member_id = m.member_id
            WHERE m.group_id = ? AND t.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmt->execute([$group_id]);
        $monthly_group_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chart_data = [
            'group_pledge_status_breakdown' => $group_pledge_status_breakdown,
            'monthly_group_payments' => $monthly_group_payments
        ];
    }
} elseif ($role === 'chief_finance_officer') {
    // Church-wide Pledge Status Breakdown
    $church_pledge_status_breakdown = [];
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount
        FROM pledges
        GROUP BY status
    ");
    $stmt->execute();
    $church_pledge_status_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Pledge Categories
    $top_pledge_categories = [];
    $stmt = $pdo->prepare("
        SELECT pc.category_name, COALESCE(SUM(p.amount), 0) as total_pledged_in_category
        FROM pledge_categories pc
        LEFT JOIN pledges p ON pc.category_id = p.category_id
        GROUP BY pc.category_name
        ORDER BY total_pledged_in_category DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_pledge_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly Church Payments
    $monthly_church_payments = [];
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(t.payment_date, '%Y-%m') as month,
            COALESCE(SUM(t.amount_paid), 0) as total_paid
        FROM transactions t
        WHERE t.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthly_church_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chart_data = [
        'church_pledge_status_breakdown' => $church_pledge_status_breakdown,
        'top_pledge_categories' => $top_pledge_categories,
        'monthly_church_payments' => $monthly_church_payments
    ];
} elseif ($role === 'system_admin') {
    // Initialize variables
    $sysadmin_monthly_financial_data = [];
    $sysadmin_error_trends = [];
    $sysadmin_errors_by_type = [];

    // Overall Financial Progress (using existing $stats)
    // Monthly Financial Activity (Last 12 Months)
    $sysadmin_monthly_financial_data = [];
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
    $sysadmin_monthly_financial_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Error Trends (Last 12 Months)
    $sysadmin_error_trends = [];
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(timestamp, '%Y-%m') as month,
            COUNT(*) as error_count
        FROM error_logs
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute();
    $sysadmin_error_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Errors by Type
    $sysadmin_errors_by_type = [];
    $stmt = $pdo->prepare("
        SELECT
            error_type,
            COUNT(*) as error_count
        FROM error_logs
        GROUP BY error_type
        ORDER BY error_count DESC
    ");
    $stmt->execute();
    $sysadmin_errors_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chart_data = [
        'sysadmin_financial_summary' => [
            'total_pledged' => $stats['total_pledged'] ?? 0,
            'total_paid' => $stats['total_paid'] ?? 0
        ],
        'sysadmin_monthly_financial_data' => $sysadmin_monthly_financial_data,
        'sysadmin_error_trends' => $sysadmin_error_trends,
        'sysadmin_errors_by_type' => $sysadmin_errors_by_type
    ];
}

$csrf_token = generateCSRFToken();
?>



<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-graph-up"></i> Financial Reports</h2>
                    <p class="mb-0">Financial reporting and analytics</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showReportError('<?= addslashes($error_message) ?>');
        });
    </script>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <?php if (!empty($stats)): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Pledges</h6>
                    <h3><?= number_format($stats['total_pledges'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-success text-white">
                <div class="card-body">
                    <h6>Total Pledged</h6>
                    <h3><?= formatCurrency($stats['total_pledged'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-info text-white">
                <div class="card-body">
                    <h6>Total Paid</h6>
                    <h3><?= formatCurrency($stats['total_paid'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-warning text-white">
                <div class="card-body">
                    <h6>Remaining</h6>
                    <h3><?= formatCurrency($stats['total_remaining'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters and Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters & Actions</h5>
                </div>
                <div class="card-body">
                    <form id="filterForm" method="POST" action="api/reports_actions.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search members, groups..." value="<?= htmlspecialchars($search_term) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-select" id="statusFilter" name="status">
                                    <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>All</option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="categoryFilter" class="form-label">Pledge Category</label>
                                <select class="form-select" id="categoryFilter" name="category">
                                    <option value="">All Categories</option>
                                    <option value="tithe">Tithe</option>
                                    <option value="offering">Offering</option>
                                    <option value="building">Building Fund</option>
                                    <option value="mission">Mission</option>
                                    <option value="thanksgiving">Thanksgiving</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="dateFrom" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="dateTo" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="dateTo" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            <div class="col-md-3">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-funnel"></i> Filter
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                        <i class="bi bi-arrow-clockwise"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-success" onclick="downloadCSV()">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Download CSV
                                </button>
                                <button type="button" class="btn btn-danger" onclick="downloadPDF()">
                                    <i class="bi bi-file-earmark-pdf"></i> Download PDF
                                </button>
                            </div>
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
                        <i class="bi bi-table"></i> Download Report
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_pledges)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Group</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody id="recentPledgesTableBody">
                                <?php foreach ($recent_pledges as $pledge): ?>
                                <?php 
                                $remaining = $pledge['amount'] - $pledge['paid_amount'];
                                $progress = $pledge['amount'] > 0 ? ($pledge['paid_amount'] / $pledge['amount']) * 100 : 0;
                                $display_progress = min(100, $progress);
                                $progress_class = $progress >= 100 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : 'bg-warning');
                                ?>
                                <tr class="<?= getOverdueIndicator($pledge) ?>">
                                    <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                    <td><?= htmlspecialchars($pledge['group_name'] ?? 'No Group') ?></td>
                                    <td><?= htmlspecialchars($pledge['category_name'] ?? 'General') ?></td>
                                    <td><?= formatCurrency($pledge['amount']) ?></td>
                                    <td><?= formatCurrency($pledge['paid_amount']) ?></td>
                                    <td><?= formatCurrency($remaining) ?></td>
                                    <td>
                                        <div class="progress" style="width: 100px; height: 20px;">
                                            <div class="progress-bar <?= $progress_class ?>" 
                                                 style="width: <?= $display_progress ?>%">
                                                <?= number_format($progress, 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= getStatusBadge($pledge['status']) ?></td>
                                    <td><?= formatDate($pledge['created_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination for Recent Pledges -->
                    <nav aria-label="Recent Pledges Pagination" id="recentPledgesPagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($rp_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=reports&rp_page=<?= $rp_page - 1 ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_rp_pages; $i++): ?>
                                <li class="page-item <?= $i == $rp_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=reports&rp_page=<?= $i ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($rp_page < $total_rp_pages): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=reports&rp_page=<?= $rp_page + 1 ?>&search=<?= urlencode($search_term) ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No pledge data found.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Visualizations Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Financial Visualizations</h5>
                </div>
                <div class="card-body">
                    <?php if ($role === 'elder_admin'): ?>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Monthly Financial Trends</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="monthlyFinancialChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Pledge Category Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="categoryBreakdownChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Building Fund Progress</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="buildingFundChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Month-over-Month Comparison</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="monthComparisonChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($role === 'member'): ?>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>My Pledge Status Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="memberPledgeStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($role === 'finance_officer'): ?>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Group Pledge Status Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="foPledgeStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Monthly Group Payments</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="foMonthlyPaymentsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($role === 'chief_finance_officer'): ?>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Church Pledge Status</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="cfoPledgeStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Top Pledge Categories</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="cfoTopCategoriesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Monthly Church Payments</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="cfoMonthlyPaymentsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($role === 'system_admin'): ?>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Overall Financial Progress</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="sysAdminFinancialProgressChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Monthly Financial Activity</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="sysAdminMonthlyFinancialChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Error Trends</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="sysAdminErrorTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Errors by Type</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="sysAdminErrorsByTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No visualizations available for your role on this page.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generic Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="errorModalLabel"><i class="bi bi-exclamation-triangle"></i> Error</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="errorModalBody">
                An unexpected error occurred. Please try again.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper function to format currency
    function formatCurrencyJs(amount) {
        return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(amount);
    }

    const role = '<?= $role ?>';
    const chartData = <?= json_encode($chart_data) ?>;
    const apiEndpoint = 'api/reports_actions.php';
    const recentPledgesTableBody = document.getElementById('recentPledgesTableBody');
    const recentPledgesPagination = document.getElementById('recentPledgesPagination');
    const filterForm = document.getElementById('filterForm');
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    const errorModalBody = document.getElementById('errorModalBody');

    // Function to show error modal
    function showReportError(message) {
        errorModalBody.textContent = message;
        errorModal.show();
    }

    // Function to load pledges via AJAX
    async function loadPledges(page = 1) {
        const formData = new FormData(filterForm);
        const data = Object.fromEntries(formData.entries());
        data.action = 'get_reports';
        data.rp_page = page;

        try {
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }

            const result = await response.json();

            if (result.success) {
                updatePledgesTable(result.pledges);
                updatePagination(result.total_pages, result.current_page);
            } else {
                showReportError(result.message || 'Failed to load reports.');
            }
        } catch (error) {
            console.error('Error loading pledges:', error);
            showReportError('Could not load reports. Please try again later.');
        }
    }

    // Function to update the pledges table
    function updatePledgesTable(pledges) {
        recentPledgesTableBody.innerHTML = ''; // Clear existing rows
        if (pledges.length === 0) {
            recentPledgesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i><p class="text-muted mt-2">No pledge data found.</p></td></tr>';
            return;
        }

        pledges.forEach(pledge => {
            const remaining = pledge.amount - pledge.paid_amount;
            const progress = pledge.amount > 0 ? (pledge.paid_amount / pledge.amount) * 100 : 0;
            const display_progress = Math.min(100, progress);
            const progress_class = progress >= 100 ? 'bg-success' : (progress >= 50 ? 'bg-info' : 'bg-warning');

            const row = `
                <tr>
                    <td>${htmlspecialchars(pledge.first_name)} ${htmlspecialchars(pledge.last_name)}</td>
                    <td>${htmlspecialchars(pledge.group_name || 'No Group')}</td>
                    <td>${htmlspecialchars(pledge.category_name || 'General')}</td>
                    <td>${formatCurrencyJs(pledge.amount)}</td>
                    <td>${formatCurrencyJs(pledge.paid_amount)}</td>
                    <td>${formatCurrencyJs(remaining)}</td>
                    <td>
                        <div class="progress" style="width: 100px; height: 20px;">
                            <div class="progress-bar ${progress_class}"
                                 style="width: ${display_progress}%">
                                ${progress.toFixed(1)}%
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-${pledge.status === 'active' ? 'primary' : (pledge.status === 'completed' ? 'success' : 'warning')}">${pledge.status.charAt(0).toUpperCase() + pledge.status.slice(1)}</span></td>
                    <td>${formatDateJs(pledge.created_at)}</td>
                </tr>
            `;
            recentPledgesTableBody.insertAdjacentHTML('beforeend', row);
        });
    }

    // Helper for htmlspecialchars in JS
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return str;
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>'"]/g, function(m) { return map[m]; });
    }

    // Helper for formatDate in JS (assuming format is YYYY-MM-DD HH:MM:SS)
    function formatDateJs(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }


    // Function to update pagination controls
    function updatePagination(totalPages, currentPage) {
        recentPledgesPagination.innerHTML = ''; // Clear existing pagination

        if (totalPages <= 1) return;

        let paginationHtml = '<ul class="pagination justify-content-center">';

        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a></li>`;
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }

        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}">Next</a></li>`;
        }

        paginationHtml += '</ul>';
        recentPledgesPagination.innerHTML = paginationHtml;

        // Add event listeners to new pagination links
        recentPledgesPagination.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                loadPledges(parseInt(this.dataset.page));
            });
        });
    }

    // Filter form submission
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        loadPledges(1); // Load first page with new filters
    });

    // Reset Filters function
    window.resetFilters = function() {
        document.getElementById('search').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = ''; // Clear date to
        loadPledges(1); // Load first page with no filters
    };

    // Download CSV function
    window.downloadCSV = function() {
        const formData = new FormData(filterForm);
        const data = Object.fromEntries(formData.entries());
        data.action = 'download_csv';

        // Construct query string from data
        const queryString = new URLSearchParams(data).toString();
        window.location.href = `${apiEndpoint}?${queryString}`;
    };

    // Download PDF function
    window.downloadPDF = function() {
        const formData = new FormData(filterForm);
        const data = Object.fromEntries(formData.entries());
        data.action = 'download_pdf';

        // Construct query string from data
        const queryString = new URLSearchParams(data).toString();
        window.location.href = `${apiEndpoint}?${queryString}`;
    };


    

    // ... (Existing Chart.js initialization code) ...
    // This part needs to be carefully re-inserted or kept as is.
    // I will assume the existing chart.js code is self-contained and can be appended.
    // If it relies on variables that are now only available via AJAX, it might need adjustment.
    // For now, I'll just put the chart.js code here.

    if (role === 'elder_admin') {
        const monthlyFinancialData = chartData.monthly_financial_data;
        const categoryBreakdown = chartData.category_breakdown;
        const buildingFundProjects = chartData.building_fund_projects;
        const comparisonData = chartData.comparison_data;

        // Monthly Financial Trends Chart
        if (monthlyFinancialData.length > 0) {
            const monthlyLabels = monthlyFinancialData.map(d => d.month);
            const pledgedAmounts = monthlyFinancialData.map(d => d.pledged_amount);
            const paidAmounts = monthlyFinancialData.map(d => d.paid_amount);

            const ctxMonthly = document.getElementById('monthlyFinancialChart').getContext('2d');
            new Chart(ctxMonthly, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Total Pledged',
                            data: pledgedAmounts,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.1
                        },
                        {
                            label: 'Total Paid',
                            data: paidAmounts,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: true,
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Pledge Category Breakdown Chart
        if (categoryBreakdown.length > 0) {
            const categoryLabels = categoryBreakdown.map(d => d.category_name);
            const categoryPledged = categoryBreakdown.map(d => d.total_pledged_in_category);

            const ctxCategory = document.getElementById('categoryBreakdownChart').getContext('2d');
            new Chart(ctxCategory, {
                type: 'pie',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryPledged,
                        backgroundColor: [
                            '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#fd7e14', '#6c757d'
                        ],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += formatCurrencyJs(context.parsed);
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }

        // Building Fund Progress Chart
        if (buildingFundProjects.length > 0) {
            const projectNames = buildingFundProjects.map(p => p.project_name);
            const currentAmounts = buildingFundProjects.map(p => p.current_amount);
            const targetAmounts = buildingFundProjects.map(p => p.target_amount);

            const ctxBuilding = document.getElementById('buildingFundChart').getContext('2d');
            new Chart(ctxBuilding, {
                type: 'bar',
                data: {
                    labels: projectNames,
                    datasets: [
                        {
                            label: 'Current Amount',
                            data: currentAmounts,
                            backgroundColor: '#28a745'
                        },
                        {
                            label: 'Target Amount',
                            data: targetAmounts,
                            backgroundColor: '#007bff'
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Month-over-Month Comparison Chart
        if (comparisonData) {
            const ctxComparison = document.getElementById('monthComparisonChart').getContext('2d');
            new Chart(ctxComparison, {
                type: 'bar',
                data: {
                    labels: ['Pledged', 'Paid'],
                    datasets: [
                        {
                            label: 'Previous Month',
                            data: [comparisonData.previous_month.pledged, comparisonData.previous_month.paid],
                            backgroundColor: '#6c757d'
                        },
                        {
                            label: 'Current Month',
                            data: [comparisonData.current_month.pledged, comparisonData.current_month.paid],
                            backgroundColor: '#007bff'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }
    } else if (role === 'member') {
        const pledgeStatusBreakdown = chartData.pledge_status_breakdown;

        // My Pledge Status Breakdown Chart
        if (pledgeStatusBreakdown.length > 0) {
            const statusLabels = pledgeStatusBreakdown.map(d => d.status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' '));
            const statusCounts = pledgeStatusBreakdown.map(d => d.count);
            const statusColors = {
                'active': '#007bff',
                'completed': '#28a745',
                'pending': '#ffc107',
                'delayed': '#dc3545',
                'cancelled': '#6c757d',
                'overdue': '#fd7e14'
            };
            const backgroundColors = pledgeStatusBreakdown.map(d => statusColors[d.status] || '#6c757d');

            const ctxMemberStatus = document.getElementById('memberPledgeStatusChart').getContext('2d');
            new Chart(ctxMemberStatus, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: backgroundColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed + ' pledges';
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }
    } else if (role === 'finance_officer') {
        const groupPledgeStatusBreakdown = chartData.group_pledge_status_breakdown;
        const monthlyGroupPayments = chartData.monthly_group_payments;

        // Group Pledge Status Breakdown Chart
        if (groupPledgeStatusBreakdown.length > 0) {
            const statusLabels = groupPledgeStatusBreakdown.map(d => d.status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' '));
            const statusCounts = groupPledgeStatusBreakdown.map(d => d.count);
            const statusColors = {
                'active': '#007bff',
                'completed': '#28a745',
                'pending': '#ffc107',
                'delayed': '#dc3545',
                'cancelled': '#6c757d',
                'overdue': '#fd7e14'
            };
            const backgroundColors = groupPledgeStatusBreakdown.map(d => statusColors[d.status] || '#6c757d');

            const ctxFoStatus = document.getElementById('foPledgeStatusChart').getContext('2d');
            new Chart(ctxFoStatus, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: backgroundColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed + ' pledges';
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }

        // Monthly Group Payments Chart
        if (monthlyGroupPayments.length > 0) {
            const monthlyLabels = monthlyGroupPayments.map(d => d.month);
            const totalPaidAmounts = monthlyGroupPayments.map(d => d.total_paid);

            const ctxFoMonthly = document.getElementById('foMonthlyPaymentsChart').getContext('2d');
            new Chart(ctxFoMonthly, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Total Paid',
                        data: totalPaidAmounts,
                        backgroundColor: '#28a745'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }
    } else if (role === 'chief_finance_officer') {
        const churchPledgeStatusBreakdown = chartData.church_pledge_status_breakdown;
        const topPledgeCategories = chartData.top_pledge_categories;
        const monthlyChurchPayments = chartData.monthly_church_payments;

        // Church Pledge Status Breakdown Chart
        if (churchPledgeStatusBreakdown.length > 0) {
            const statusLabels = churchPledgeStatusBreakdown.map(d => d.status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' '));
            const statusCounts = churchPledgeStatusBreakdown.map(d => d.count);
            const statusColors = {
                'active': '#007bff',
                'completed': '#28a745',
                'pending': '#ffc107',
                'delayed': '#dc3545',
                'cancelled': '#6c757d',
                'overdue': '#fd7e14'
            };
            const backgroundColors = churchPledgeStatusBreakdown.map(d => statusColors[d.status] || '#6c757d');

            const ctxCfoStatus = document.getElementById('cfoPledgeStatusChart').getContext('2d');
            new Chart(ctxCfoStatus, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: backgroundColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed + ' pledges';
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }

        // Top Pledge Categories Chart
        if (topPledgeCategories.length > 0) {
            const categoryNames = topPledgeCategories.map(d => d.category_name);
            const pledgedAmounts = topPledgeCategories.map(d => d.total_pledged_in_category);

            const ctxCfoCategories = document.getElementById('cfoTopCategoriesChart').getContext('2d');
            new Chart(ctxCfoCategories, {
                type: 'bar',
                data: {
                    labels: categoryNames,
                    datasets: [{
                        label: 'Total Pledged',
                        data: pledgedAmounts,
                        backgroundColor: '#007bff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Monthly Church Payments Chart
        if (monthlyChurchPayments.length > 0) {
            const monthlyLabels = monthlyChurchPayments.map(d => d.month);
            const totalPaidAmounts = monthlyChurchPayments.map(d => d.total_paid);

            const ctxCfoMonthly = document.getElementById('cfoMonthlyPaymentsChart').getContext('2d');
            new Chart(ctxCfoMonthly, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Total Paid',
                        data: totalPaidAmounts,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }
    } else if (role === 'system_admin') {
        const sysadminFinancialSummary = chartData.sysadmin_financial_summary;
        const sysadminMonthlyFinancialData = chartData.sysadmin_monthly_financial_data;
        const sysadminErrorTrends = chartData.sysadmin_error_trends;
        const sysadminErrorsByType = chartData.sysadmin_errors_by_type;

        // Overall Financial Progress Chart (Doughnut)
        if (sysadminFinancialSummary.total_pledged > 0 || sysadminFinancialSummary.total_paid > 0) {
            const ctxFinancialProgress = document.getElementById('sysAdminFinancialProgressChart').getContext('2d');
            new Chart(ctxFinancialProgress, {
                type: 'doughnut',
                data: {
                    labels: ['Total Paid', 'Total Remaining'],
                    datasets: [{
                        data: [sysadminFinancialSummary.total_paid, sysadminFinancialSummary.total_pledged - sysadminFinancialSummary.total_paid],
                        backgroundColor: ['#28a745', '#007bff'], // Green for paid, Blue for remaining
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += formatCurrencyJs(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Monthly Financial Activity Chart (Line)
        if (sysadminMonthlyFinancialData.length > 0) {
            const monthlyLabels = sysadminMonthlyFinancialData.map(d => d.month);
            const pledgedAmounts = sysadminMonthlyFinancialData.map(d => d.pledged_amount);
            const paidAmounts = sysadminMonthlyFinancialData.map(d => d.paid_amount);

            const ctxMonthlyFinancial = document.getElementById('sysAdminMonthlyFinancialChart').getContext('2d');
            new Chart(ctxMonthlyFinancial, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Total Pledged',
                            data: pledgedAmounts,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.1
                        },
                        {
                            label: 'Total Paid',
                            data: paidAmounts,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: true,
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrencyJs(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrencyJs(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Error Trends Chart (Line)
        if (sysadminErrorTrends.length > 0) {
            const errorTrendLabels = sysadminErrorTrends.map(d => d.month);
            const errorCounts = sysadminErrorTrends.map(d => d.error_count);

            const ctxErrorTrends = document.getElementById('sysAdminErrorTrendsChart').getContext('2d');
            new Chart(ctxErrorTrends, {
                type: 'line',
                data: {
                    labels: errorTrendLabels,
                    datasets: [{
                        label: 'Number of Errors',
                        data: errorCounts,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Errors by Type Chart (Bar)
        if (sysadminErrorsByType.length > 0) {
            const errorTypeLabels = sysadminErrorsByType.map(d => d.error_type);
            const errorTypeCounts = sysadminErrorsByType.map(d => d.error_count);

            const ctxErrorsByType = document.getElementById('sysAdminErrorsByTypeChart').getContext('2d');
            new Chart(ctxErrorsByType, {
                type: 'bar',
                data: {
                    labels: errorTypeLabels,
                    datasets: [{
                        label: 'Error Count',
                        data: errorTypeCounts,
                        backgroundColor: [
                            '#dc3545', '#ffc107', '#007bff', '#6c757d', '#28a745'
                        ],
                        borderColor: [
                            '#dc3545', '#ffc107', '#007bff', '#6c757d', '#28a745'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
