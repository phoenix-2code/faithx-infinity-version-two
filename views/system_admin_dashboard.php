<?php
// Simplified System Admin Dashboard - Basic functionality without complex RBAC
// Start session and load basic requirements
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and is system admin
if (!isLoggedIn() || !hasRole('system_admin')) {
    header('Location: index.php?page=login');
    exit();
}

// Get basic system statistics
try {
    // Get user counts
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
    $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // Get pledge counts
    $stmt = $pdo->query("SELECT COUNT(*) as total_pledges FROM pledges WHERE status = 'active'");
    $pledge_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_pledges'];
    
    // Get transaction counts
    $stmt = $pdo->query("SELECT COUNT(*) as total_transactions FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $transaction_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_transactions'];
    
    // Get group counts
    $stmt = $pdo->query("SELECT COUNT(*) as total_groups FROM groups");
    $group_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_groups'];
    
    // Get recent users with pagination
    $user_page = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
    $user_limit = 10;
    $user_offset = ($user_page - 1) * $user_limit;

    // Count total users
    $count_users_stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users_count = $count_users_stmt->fetchColumn();
    $total_user_pages = ceil($total_users_count / $user_limit);

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.first_name, u.last_name, u.role, u.is_active,
               u.last_login_attempt, u.failed_login_attempts, u.created_at
        FROM users u
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, (int)$user_limit, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$user_offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent pledges with pagination
    $pledge_page = isset($_GET['pledge_page']) ? (int)$_GET['pledge_page'] : 1;
    $pledge_limit = 10;
    $pledge_offset = ($pledge_page - 1) * $pledge_limit;

    // Count total pledges
    $count_pledges_stmt = $pdo->query("SELECT COUNT(*) FROM pledges");
    $total_pledges_count = $count_pledges_stmt->fetchColumn();
    $total_pledge_pages = ceil($total_pledges_count / $pledge_limit);

    $stmt = $pdo->prepare("
        SELECT p.pledge_id, p.amount, p.status, p.created_at,
               m.first_name, m.last_name, g.group_name
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN groups g ON p.group_id = g.group_id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, (int)$pledge_limit, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$pledge_offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard data. Please try again later.";
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $error_message .= "<br><br><strong>Debug Info:</strong> " . $e->getMessage();
    }
}
?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-gear-fill"></i> System Administrator Dashboard</h2>
                <p class="text-muted">System overview and management</p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showDashboardError('<?= addslashes($error_message) ?>');
            });
        </script>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Active Users</h6>
                                <h3><?= number_format($user_count ?? 0) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Active Pledges</h6>
                                <h3><?= number_format($pledge_count ?? 0) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-gift" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>24h Transactions</h6>
                                <h3><?= number_format($transaction_count ?? 0) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-credit-card" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Total Groups</h6>
                                <h3><?= number_format($group_count ?? 0) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-collection" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-plus"></i> Recent Users</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_users)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= formatDate($user['created_at']) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination for Recent Users -->
                        <nav aria-label="Recent Users Pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($user_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=system_admin_dashboard&user_page=<?= $user_page - 1 ?>">Previous</a></li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_user_pages; $i++): ?>
                                    <li class="page-item <?= $i == $user_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=system_admin_dashboard&user_page=<?= $i ?>"><?= $i ?></a></li>
                                <?php endfor; ?>
                                <?php if ($user_page < $total_user_pages): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=system_admin_dashboard&user_page=<?= $user_page + 1 ?>">Next</a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <?php else: ?>
                        <p class="text-muted">No users found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Pledges -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-gift"></i> Recent Pledges</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_pledges)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Group</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_pledges as $pledge): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                        <td><?= formatCurrency($pledge['amount']) ?></td>
                                        <td><?= htmlspecialchars($pledge['group_name'] ?? 'N/A') ?></td>
                                        <td><?= getStatusBadge($pledge['status']) ?></td>
                                        <td><small><?= formatDate($pledge['created_at']) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination for Recent Pledges -->
                        <nav aria-label="Recent Pledges Pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($pledge_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=system_admin_dashboard&pledge_page=<?= $pledge_page - 1 ?>">Previous</a></li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pledge_pages; $i++): ?>
                                    <li class="page-item <?= $i == $pledge_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=system_admin_dashboard&pledge_page=<?= $i ?>"><?= $i ?></a></li>
                                <?php endfor; ?>
                                <?php if ($pledge_page < $total_pledge_pages): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=system_admin_dashboard&pledge_page=<?= $pledge_page + 1 ?>">Next</a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <?php else: ?>
                        <p class="text-muted">No pledges found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Admin CRUD -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-pencil-square"></i> System Admin CRUD</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="index.php?page=coming_soon" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="bi bi-person-plus"></i> Create User
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php?page=coming_soon" class="btn btn-outline-success w-100 mb-2">
                                    <i class="bi bi-people"></i> Create Group
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php?page=coming_soon" class="btn btn-outline-secondary w-100 mb-2">
                                    <i class="bi bi-people"></i> Manage Groups
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php?page=coming_soon" class="btn btn-outline-secondary w-100 mb-2">
                                    <i class="bi bi-person-gear"></i> Manage Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="bi bi-exclamation-octagon"></i> Emergency Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="index.php?page=coming_soon" class="btn btn-outline-danger w-100 mb-2">
                                    <i class="bi bi-shield-lock"></i> Activate Break Glass
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php?page=coming_soon" class="btn btn-outline-info w-100 mb-2">
                                    <i class="bi bi-gear"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Logs -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="bi bi-bug"></i> Error Logs</h5>
                    </div>
                    <div class="card-body">
                        

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="card bg-light text-dark">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-0">Total Errors: <?= number_format($total_errors) ?></h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light text-dark">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-0">Unique Affected Users: <?= number_format($unique_affected_users) ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form class="mb-3" id="errorFilterForm" method="POST" action="api/admin_actions.php">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="error_search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="error_search" name="error_search" value="<?= htmlspecialchars($error_filters['search']) ?>" placeholder="Message, file, user...">
                                </div>
                                <div class="col-md-3">
                                    <label for="error_status" class="form-label">Status</label>
                                    <select class="form-select" id="error_status" name="error_status">
                                        <option value="" <?= $error_filters['status'] === '' ? 'selected' : '' ?>>All</option>
                                        <option value="new" <?= $error_filters['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                        <option value="in_progress" <?= $error_filters['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="resolved" <?= $error_filters['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="error_type" class="form-label">Type</label>
                                    <select class="form-select" id="error_type" name="error_type">
                                        <option value="" <?= $error_filters['error_type'] === '' ? 'selected' : '' ?>>All</option>
                                        <option value="PHP Error" <?= $error_filters['error_type'] === 'PHP Error' ? 'selected' : '' ?>>PHP Error</option>
                                        <option value="Uncaught Exception" <?= $error_filters['error_type'] === 'Uncaught Exception' ? 'selected' : '' ?>>Uncaught Exception</option>
                                        <option value="Fatal Error" <?= $error_filters['error_type'] === 'Fatal Error' ? 'selected' : '' ?>>Fatal Error</option>
                                        <option value="Application Error" <?= $error_filters['error_type'] === 'Application Error' ? 'selected' : '' ?>>Application Error</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($errors)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="errorLogsTable">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Message</th>
                                        <th>File:Line</th>
                                        <th>User</th>
                                        <th>Page</th>
                                        <th>Status</th>
                                        <th>Solution</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($errors as $error): ?>
                                    <tr>
                                        <td><?= formatDate($error['timestamp']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($error['error_type']) ?></span></td>
                                        <td><?= htmlspecialchars(substr($error['message'], 0, 100)) ?><?= strlen($error['message']) > 100 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars(basename($error['file'])) ?>:<?= htmlspecialchars($error['line']) ?></td>
                                        <td><?= htmlspecialchars($error['username'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($error['page'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-<?= $error['status'] === 'new' ? 'danger' : ($error['status'] === 'in_progress' ? 'warning' : 'success') ?>"><?= ucfirst(str_replace('_', ' ', $error['status'])) ?></span></td>
                                        <td><?= htmlspecialchars(substr($error['solution'] ?? 'N/A', 0, 50)) ?><?= strlen($error['solution'] ?? '') > 50 ? '...' : '' ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#errorDetailModal" data-error='<?= json_encode($error) ?>'><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-sm btn-success" onclick="updateErrorStatus(<?= $error['log_id'] ?>, 'resolved')"><i class="bi bi-check-circle"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination for Error Logs -->
                        <nav aria-label="Error Logs Pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($error_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=system_admin_dashboard&error_page=<?= $error_page - 1 ?>&error_status=<?= urlencode($error_filters['status']) ?>&error_type=<?= urlencode($error_filters['error_type']) ?>&error_search=<?= urlencode($error_filters['search']) ?>">Previous</a></li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_error_pages; $i++): ?>
                                    <li class="page-item <?= $i == $error_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=system_admin_dashboard&error_page=<?= $i ?>&error_status=<?= urlencode($error_filters['status']) ?>&error_type=<?= urlencode($error_filters['error_type']) ?>&error_search=<?= urlencode($error_filters['search']) ?>"><?= $i ?></a></li>
                                <?php endfor; ?>
                                <?php if ($error_page < $total_error_pages): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=system_admin_dashboard&error_page=<?= $error_page + 1 ?>&error_status=<?= urlencode($error_filters['status']) ?>&error_type=<?= urlencode($error_filters['error_type']) ?>&error_search=<?= urlencode($error_filters['search']) ?>">Next</a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No errors found matching your criteria.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Detail Modal -->
    <div class="modal fade" id="errorDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-bug"></i> Error Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Timestamp:</strong> <span id="detail_timestamp"></span></p>
                    <p><strong>Type:</strong> <span id="detail_type"></span></p>
                    <p><strong>Message:</strong> <span id="detail_message"></span></p>
                    <p><strong>File:</strong> <span id="detail_file"></span></p>
                    <p><strong>Line:</strong> <span id="detail_line"></span></p>
                    <p><strong>User:</strong> <span id="detail_user"></span></p>
                    <p><strong>Page:</strong> <span id="detail_page"></span></p>
                    <p><strong>Status:</strong> <span id="detail_status"></span></p>
                    <div class="mb-3">
                        <label for="detail_solution" class="form-label"><strong>Solution:</strong></label>
                        <textarea class="form-control" id="detail_solution" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveSolutionBtn">Save Solution</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage User Modal -->
    <div class="modal fade" id="manageUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-gear"></i> Manage User: <span id="manage_username"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="manage_user_id">
                    <div class="mb-3">
                        <label for="user_search_input" class="form-label">Search User by Username</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="user_search_input" placeholder="Enter username">
                            <button class="btn btn-primary" type="button" id="searchUserBtn"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </div>
                    <div id="user_search_results" class="list-group mb-3" style="max-height: 200px; overflow-y: auto; display: none;">
                        <!-- Search results will be appended here -->
                    </div>
                    <hr> <!-- Separator -->
                    
                    <div class="mb-3">
                        <label for="manage_new_password" class="form-label">Reset Password</label>
                        <input type="password" class="form-control" id="manage_new_password" placeholder="Enter new password">
                        <button type="button" class="btn btn-warning btn-sm mt-2" id="resetPasswordBtn">Reset Password</button>
                    </div>

                    <div class="mb-3">
                        <label for="manage_user_role" class="form-label">Modify Role</label>
                        <select class="form-select" id="manage_user_role">
                            <option value="member">Member</option>
                            <option value="group_leader">Group Leader</option>
                            <option value="finance_officer">Finance Officer</option>
                            <option value="chief_finance_officer">Chief Finance Officer</option>
                            <option value="elder_admin">Elder Admin</option>
                            <option value="system_admin">System Admin</option>
                        </select>
                        <button type="button" class="btn btn-info btn-sm mt-2" id="updateRoleBtn">Update Role</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Status</label>
                        <p>Failed Login Attempts: <span id="manage_failed_attempts"></span></p>
                        <p>Account Locked Until: <span id="manage_locked_until"></span></p>
                        <button type="button" class="btn btn-success btn-sm" id="unlockAccountBtn">Unlock Account</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

<!-- Generic Error Modal for Dashboard -->
<div class="modal fade" id="dashboardErrorModal" tabindex="-1" aria-labelledby="dashboardErrorModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="dashboardErrorModalLabel"><i class="bi bi-exclamation-triangle"></i> Error</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="dashboardErrorModalBody">
        An unexpected error occurred. Please try again.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

    <?php require_once __DIR__ . '/../includes/modals.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const apiEndpoint = 'api/admin_actions.php';
            const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));

            function showFeedback(title, message, isSuccess) {
                const modalTitle = document.getElementById('feedbackModalTitle');
                const modalBody = document.getElementById('feedbackModalBody');
                modalTitle.textContent = title;
                modalBody.textContent = message;
                if (isSuccess) {
                    modalTitle.classList.remove('text-danger');
                    modalTitle.classList.add('text-success');
                } else {
                    modalTitle.classList.remove('text-success');
                    modalTitle.classList.add('text-danger');
                }
                feedbackModal.show();
            }

            function handleFormSubmit(formId, modalId) {
                const form = document.getElementById(formId);
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
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
                        const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                        modal.hide();
                        showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                        if (result.success) {
                            setTimeout(() => location.reload(), 2000);
                        }
                    });
                });
            }

            handleFormSubmit('createUserForm', 'createUserModal');
            handleFormSubmit('createGroupForm', 'createGroupModal');
            handleFormSubmit('updateSystemSettingsForm', 'updateSystemSettingsModal');

            // Event listener for error filter form submission
            errorFilterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                loadErrorLogs(1); // Load first page with new filters
            });

            // Manage User Modal - Search Functionality
            const userSearchInput = document.getElementById('user_search_input');
            const searchUserBtn = document.getElementById('searchUserBtn');
            const userSearchResults = document.getElementById('user_search_results');
            const manageUserId = document.getElementById('manage_user_id');
            const manageUsername = document.getElementById('manage_username');
            const manageUserRole = document.getElementById('manage_user_role');
            const manageFailedAttempts = document.getElementById('manage_failed_attempts');
            const manageLockedUntil = document.getElementById('manage_locked_until');
            const manageNewPassword = document.getElementById('manage_new_password');

            // Function to clear user details in the modal
            function clearUserDetails() {
                manageUserId.value = '';
                manageUsername.textContent = '';
                manageUserRole.value = 'member'; // Default role
                manageFailedAttempts.textContent = '';
                manageLockedUntil.textContent = '';
                manageNewPassword.value = ''; // Clear password field
                // Hide the action buttons until a user is selected
                document.getElementById('resetPasswordBtn').style.display = 'none';
                document.getElementById('updateRoleBtn').style.display = 'none';
                document.getElementById('unlockAccountBtn').style.display = 'none';
            }

            // Function to populate user details in the modal
            function populateUserDetails(user) {
                manageUserId.value = user.user_id;
                manageUsername.textContent = user.username;
                manageUserRole.value = user.role;
                manageFailedAttempts.textContent = user.failed_login_attempts;
                manageLockedUntil.textContent = user.account_locked_until || 'N/A';
                // Show the action buttons
                document.getElementById('resetPasswordBtn').style.display = 'inline-block';
                document.getElementById('updateRoleBtn').style.display = 'inline-block';
                document.getElementById('unlockAccountBtn').style.display = 'inline-block';
            }

            // Clear modal on show
            manageUserModal.addEventListener('show.bs.modal', function () {
                clearUserDetails();
                userSearchInput.value = '';
                userSearchResults.innerHTML = '';
                userSearchResults.style.display = 'none';
            });

            searchUserBtn.addEventListener('click', function() {
                const searchTerm = userSearchInput.value.trim();
                if (searchTerm.length < 2) { // Require at least 2 characters for search
                    userSearchResults.innerHTML = '<div class="list-group-item text-muted">Please enter at least 2 characters.</div>';
                    userSearchResults.style.display = 'block';
                    return;
                }

                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'search_users', search_term: searchTerm })
                })
                .then(response => response.json())
                .then(result => {
                    userSearchResults.innerHTML = ''; // Clear previous results
                    if (result.success && result.users.length > 0) {
                        result.users.forEach(user => {
                            const userItem = document.createElement('a');
                            userItem.href = '#';
                            userItem.classList.add('list-group-item', 'list-group-item-action');
                            userItem.textContent = `${user.username} (${user.first_name} ${user.last_name})`;
                            userItem.addEventListener('click', function(e) {
                                e.preventDefault();
                                populateUserDetails(user);
                                userSearchResults.style.display = 'none'; // Hide results after selection
                            });
                            userSearchResults.appendChild(userItem);
                        });
                        userSearchResults.style.display = 'block';
                    } else {
                        userSearchResults.innerHTML = '<div class="list-group-item text-muted">No users found.</div>';
                        userSearchResults.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error searching users:', error);
                    userSearchResults.innerHTML = '<div class="list-group-item text-danger">Error searching users.</div>';
                    userSearchResults.style.display = 'block';
                });
            });

            // Error Log Details Modal
            const errorDetailModal = document.getElementById('errorDetailModal');
            errorDetailModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const error = JSON.parse(button.getAttribute('data-error'));

                document.getElementById('detail_timestamp').textContent = error.timestamp;
                document.getElementById('detail_type').textContent = error.error_type;
                document.getElementById('detail_message').textContent = error.message;
                document.getElementById('detail_file').textContent = error.file;
                document.getElementById('detail_line').textContent = error.line;
                document.getElementById('detail_user').textContent = error.username || 'N/A';
                document.getElementById('detail_page').textContent = error.page || 'N/A';
                document.getElementById('detail_status').textContent = error.status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
                document.getElementById('detail_solution').value = error.solution || '';
                
                // Store log_id for saving solution
                errorDetailModal.setAttribute('data-log-id', error.log_id);
            });

            document.getElementById('saveSolutionBtn').addEventListener('click', function() {
                const logId = errorDetailModal.getAttribute('data-log-id');
                const solution = document.getElementById('detail_solution').value;

                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'update_error_log', log_id: logId, solution: solution })
                })
                .then(response => response.json())
                .then(result => {
                    const modal = bootstrap.Modal.getInstance(errorDetailModal);
                    modal.hide();
                    showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                    if (result.success) {
                        setTimeout(() => location.reload(), 2000);
                    }
                });
            });

            window.updateErrorStatus = function(logId, status) {
                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'update_error_log', log_id: logId, status: status })
                })
                .then(response => response.json())
                .then(result => {
                    showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                    if (result.success) {
                        setTimeout(() => location.reload(), 2000);
                    }
                });
            };

            

            document.getElementById('resetPasswordBtn').addEventListener('click', function() {
                const userId = document.getElementById('manage_user_id').value;
                const newPassword = document.getElementById('manage_new_password').value;

                if (!newPassword) {
                    showFeedback('Error', 'Please enter a new password.', false);
                    return;
                }

                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'reset_user_password', user_id: userId, new_password: newPassword })
                })
                .then(response => response.json())
                .then(result => {
                    const modal = bootstrap.Modal.getInstance(manageUserModal);
                    modal.hide();
                    showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                    if (result.success) {
                        setTimeout(() => location.reload(), 2000);
                    }
                });
            });

            document.getElementById('updateRoleBtn').addEventListener('click', function() {
                const userId = document.getElementById('manage_user_id').value;
                const newRole = document.getElementById('manage_user_role').value;

                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'update_user_role', user_id: userId, new_role: newRole })
                })
                .then(response => response.json())
                .then(result => {
                    const modal = bootstrap.Modal.getInstance(manageUserModal);
                    modal.hide();
                    showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                    if (result.success) {
                        setTimeout(() => location.reload(), 2000);
                    }
                });
            });

            document.getElementById('unlockAccountBtn').addEventListener('click', function() {
                const userId = document.getElementById('manage_user_id').value;

                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'unlock_user_account', user_id: userId })
                })
                .then(response => response.json())
                .then(result => {
                    const modal = bootstrap.Modal.getInstance(manageUserModal);
                    modal.hide();
                    showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                    if (result.success) {
                        setTimeout(() => location.reload(), 2000);
                    }
                });
            });

            // Initial load of error logs when page loads
            loadErrorLogs(1); // Load first page of error logs

            // Event listener for error filter form submission
            errorFilterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                loadErrorLogs(1); // Load first page with new filters
            });
        });
    </script>
