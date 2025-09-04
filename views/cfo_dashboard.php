<?php
/**
 * CFO Dashboard Page
 * Content for the Chief Finance Officer's dashboard.
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Ensure $pdo is available (from config/database.php included in index.php)
global $pdo;

// Get basic statistics for CFO Dashboard
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
    
} catch (Exception $e) {
    $error_message = "Error loading financial data: " . $e->getMessage();
    // In a real application, you'd log this error more robustly
}

// Pagination for Pledges Waiting Payment
$limit = 10; // Items per page
$page = isset($_GET['pledges_page']) ? (int)$_GET['pledges_page'] : 1;
$offset = ($page - 1) * $limit;

$pledges_waiting_payment = [];
$total_pledges_waiting = 0;

try {
    // Count total pledges waiting payment
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM pledges p
        LEFT JOIN (SELECT pledge_id, SUM(amount_paid) as total_paid FROM transactions GROUP BY pledge_id) t ON p.pledge_id = t.pledge_id
        WHERE p.status = 'active' AND COALESCE(t.total_paid, 0) < p.amount
    ");
    $count_stmt->execute();
    $total_pledges_waiting = $count_stmt->fetchColumn();

    // Fetch pledges waiting payment with pagination
    $stmt = $pdo->prepare("
        SELECT 
            p.pledge_id, p.amount, p.due_date, p.status, p.notes as description, p.created_at,
            m.first_name, m.last_name, m.email, m.phone_number,
            COALESCE(t.total_paid, 0) as amount_paid
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN (SELECT pledge_id, SUM(amount_paid) as total_paid FROM transactions GROUP BY pledge_id) t ON p.pledge_id = t.pledge_id
        WHERE p.status = 'active' AND COALESCE(t.total_paid, 0) < p.amount
        ORDER BY p.due_date ASC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pledges_waiting_payment = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message_pledges = "Error loading pledges waiting payment: " . $e->getMessage();
}

$total_pledges_waiting_pages = ceil($total_pledges_waiting / $limit);

?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-speedometer2"></i> CFO Dashboard</h2>
                    <p class="mb-0">Overview and key financial metrics for the Chief Finance Officer</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
    </div>
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

    <!-- Pledges Waiting Payment Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-hourglass-split"></i> Pledges Waiting Payment</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message_pledges)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error_message_pledges) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($pledges_waiting_payment)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Pledge ID</th>
                                    <th>Member</th>
                                    <th>Amount Pledged</th>
                                    <th>Amount Paid</th>
                                    <th>Amount Remaining</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pledges_waiting_payment as $pledge): ?>
                                <?php $remaining = $pledge['amount'] - $pledge['amount_paid']; ?>
                                <tr>
                                    <td><?= htmlspecialchars($pledge['pledge_id']) ?></td>
                                    <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                    <td><?= formatCurrency($pledge['amount']) ?></td>
                                    <td><?= formatCurrency($pledge['amount_paid']) ?></td>
                                    <td><?= formatCurrency($remaining) ?></td>
                                    <td><?= htmlspecialchars($pledge['due_date']) ?></td>
                                    <td><?= getStatusBadge($pledge['status']) ?></td>
                                    <td><?= formatDate($pledge['created_at']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-pledge-btn" data-id="<?= $pledge['pledge_id'] ?>" title="View Details"><i class="bi bi-eye"></i></button>
                                        <button class="btn btn-sm btn-success record-payment-btn" data-id="<?= $pledge['pledge_id'] ?>" title="Record Payment"><i class="bi bi-cash"></i></button>
                                        <button class="btn btn-sm btn-warning edit-pledge-btn" data-id="<?= $pledge['pledge_id'] ?>" title="Edit Pledge"><i class="bi bi-pencil"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Pledges Waiting Payment Pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=cfo_dashboard&pledges_page=<?= $page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pledges_waiting_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=cfo_dashboard&pledges_page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pledges_waiting_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=cfo_dashboard&pledges_page=<?= $page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No pledges currently waiting for payment. All clear!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CFO Notifications/Alerts Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-bell"></i> CFO Notifications & Alerts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Related Party</th>
                                </tr>
                            </thead>
                            <tbody id="cfoAlertsTableBody">
                                <!-- Alerts will be loaded dynamically via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination for CFO Alerts -->
                    <nav aria-label="CFO Alerts Pagination">
                        <ul class="pagination justify-content-center" id="cfoAlertsPagination">
                            <!-- Pagination will be loaded dynamically via JavaScript -->
                        </ul>
                    </nav>
                    <div class="text-center py-4" id="noCFOAlertsMessage" style="display: none;">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No new notifications or alerts.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CRUD Operations Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> CFO CRUD Operations</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Pledge Management</h6>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addPledgeModal"><i class="bi bi-plus-circle"></i> Add New Pledge</button>
                        <button class="btn btn-warning" type="button" onclick="window.location.href='index.php?page=coming_soon'"><i class="bi bi-pencil"></i> Edit Pledge</button>
                        <button class="btn btn-danger" type="button" onclick="window.location.href='index.php?page=coming_soon'"><i class="bi bi-archive"></i> Delete/Archive Pledge</button>
                    </div>

                    <h6 class="mb-3">Payment Management</h6>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                        <button class="btn btn-info text-white" type="button" data-bs-toggle="modal" data-bs-target="#recordPaymentModal"><i class="bi bi-cash-coin"></i> Record Payment</button>
                        <button class="btn btn-warning" type="button" onclick="window.location.href='index.php?page=coming_soon'"><i class="bi bi-pencil"></i> Edit Payment</button>
                        <button class="btn btn-danger" type="button" onclick="window.location.href='index.php?page=coming_soon'"><i class="bi bi-archive"></i> Delete/Archive Payment</button>
                    </div>

                    <h6 class="mb-3">Category Management</h6>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                        <button class="btn btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal"><i class="bi bi-tags"></i> Manage Categories</button>
                        <button class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#editCategoryModal"><i class="bi bi-pencil"></i> Edit Category</button>
                        <button class="btn btn-danger" type="button" onclick="window.location.href='index.php?page=coming_soon'"><i class="bi bi-archive"></i> Delete/Archive Category</button>
                    </div>

                    <h6 class="mb-3">Report Management</h6>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="index.php?page=reports" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph"></i> View All Reports</a>
                        <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#createReportModal"><i class="bi bi-file-earmark-plus"></i> Create Custom Report</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals for CRUD Operations (Place these at the end of the body or in a separate includes file) -->

    <!-- Add New Pledge Modal -->
    <div class="modal fade" id="addPledgeModal" tabindex="-1" aria-labelledby="addPledgeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addPledgeModalLabel">Add New Pledge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPledgeForm">
                        <div class="mb-3">
                            <label for="memberId" class="form-label">Member</label>
                            <select class="form-select" id="memberId" name="member_id" required>
                                <!-- Options will be loaded dynamically via JavaScript -->
                                <option value="">Select Member</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="pledgeAmount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="pledgeAmount" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="pledgeDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="pledgeDueDate" name="due_date">
                        </div>
                        <div class="mb-3">
                            <label for="pledgeCategory" class="form-label">Category</label>
                            <select class="form-select" id="pledgeCategory" name="category_id">
                                <!-- Options will be loaded dynamically via JavaScript -->
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="pledgeDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="pledgeDescription" name="description" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="action" value="add_pledge">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-primary">Submit Pledge</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Pledge Modal -->
    <div class="modal fade" id="editPledgeModal" tabindex="-1" aria-labelledby="editPledgeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editPledgeModalLabel">Edit Pledge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPledgeForm">
                        <input type="hidden" id="editPledgeId" name="pledge_id">
                        <div class="mb-3">
                            <label for="editPledgeMember" class="form-label">Member</label>
                            <input type="text" class="form-control" id="editPledgeMember" disabled>
                        </div>
                        <div class="mb-3">
                            <label for="editPledgeAmount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="editPledgeAmount" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPledgeDueDate" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="editPledgeDueDate" name="due_date">
                        </div>
                        <div class="mb-3">
                            <label for="editPledgeCategory" class="form-label">Category</label>
                            <select class="form-select" id="editPledgeCategory" name="category_id">
                                <!-- Options will be loaded dynamically via JavaScript -->
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editPledgeStatus" class="form-label">Status</label>
                            <select class="form-select" id="editPledgeStatus" name="status">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="overdue">Overdue</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editPledgeDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editPledgeDescription" name="description" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="action" value="update_pledge">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-warning text-white">Update Pledge</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="recordPaymentModalLabel">Record Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="recordPaymentForm">
                        <div class="mb-3">
                            <label for="paymentPledgeId" class="form-label">Pledge ID</label>
                            <input type="text" class="form-control" id="paymentPledgeId" name="pledge_id" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentAmount" class="form-label">Amount Paid</label>
                            <input type="number" class="form-control" id="paymentAmount" name="amount_paid" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentDate" class="form-label">Payment Date</label>
                            <input type="date" class="form-control" id="paymentDate" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentMethod" class="form-label">Payment Method</label>
                            <input type="text" class="form-control" id="paymentMethod" name="payment_method">
                        </div>
                        <input type="hidden" name="action" value="record_payment">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-info text-white">Record Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Categories Modal -->
    <div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-labelledby="manageCategoriesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="manageCategoriesModalLabel">Manage Pledge Categories</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Existing Categories</h6>
                    <ul id="categoryList" class="list-group mb-3">
                        <!-- Categories will be loaded dynamically -->
                    </ul>
                    <h6>Add New Category</h6>
                    <form id="addCategoryForm">
                        <div class="mb-3">
                            <label for="newCategoryName" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="newCategoryName" name="category_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="newCategoryDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="newCategoryDescription" name="description" rows="2"></textarea>
                        </div>
                        <input type="hidden" name="action" value="add_category">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-secondary">Add Category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Pledge Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCategoryForm">
                        <input type="hidden" id="editCategoryId" name="category_id">
                        <div class="mb-3">
                            <label for="editCategoryName" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="editCategoryName" name="category_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editCategoryDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editCategoryDescription" name="description" rows="2"></textarea>
                        </div>
                        <input type="hidden" name="action" value="update_category">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-warning text-white">Update Category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Custom Report Modal -->
    <div class="modal fade" id="createReportModal" tabindex="-1" aria-labelledby="createReportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="createReportModalLabel">Create Custom Report</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createReportForm">
                        <div class="mb-3">
                            <label for="reportType" class="form-label">Report Type</label>
                            <select class="form-select" id="reportType" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="pledge_summary">Pledge Summary</option>
                                <option value="payment_summary">Payment Summary</option>
                                <option value="member_contributions">Member Contributions</option>
                                <!-- Add more report types as needed -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="reportStartDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="reportStartDate" name="start_date">
                        </div>
                        <div class="mb-3">
                            <label for="reportEndDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="reportEndDate" name="end_date">
                        </div>
                        <input type="hidden" name="action" value="create_report">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="btn btn-success">Generate Report</button>
                    </form>
                </div>
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

    // Generic AJAX POST function
    async function postData(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
            },
            body: JSON.stringify(data)
        });
        return response.json();
    }

    // Handle Add New Pledge Form Submission
    const addPledgeForm = document.getElementById('addPledgeForm');
    if (addPledgeForm) {
        addPledgeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const result = await postData('api/cfo_actions.php', data);
                if (result.success) {
                    alert('Pledge added successfully!');
                    location.reload(); // Reload page to show updated data
                } else {
                    alert('Error adding pledge: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while adding the pledge.');
            }
        });
    }

    // Handle Edit Pledge Form Submission
    const editPledgeForm = document.getElementById('editPledgeForm');
    if (editPledgeForm) {
        editPledgeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const result = await postData('api/cfo_actions.php', data);
                if (result.success) {
                    alert('Pledge updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating pledge: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating the pledge.');
            }
        });
    }

    // Handle Record Payment Form Submission
    const recordPaymentForm = document.getElementById('recordPaymentForm');
    if (recordPaymentForm) {
        recordPaymentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const result = await postData('api/cfo_actions.php', data);
                if (result.success) {
                    alert('Payment recorded successfully!');
                    location.reload();
                } else {
                    alert('Error recording payment: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while recording the payment.');
            }
        });
    }

    // Handle Add Category Form Submission
    const addCategoryForm = document.getElementById('addCategoryForm');
    if (addCategoryForm) {
        addCategoryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const result = await postData('api/cfo_actions.php', data);
                if (result.success) {
                    alert('Category added successfully!');
                    loadCategories(); // Reload categories list
                } else {
                    alert('Error adding category: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while adding the category.');
            }
        });
    }

    // Handle Edit Category Form Submission
    const editCategoryForm = document.getElementById('editCategoryForm');
    if (editCategoryForm) {
        editCategoryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const result = await postData('api/cfo_actions.php', data);
                if (result.success) {
                    alert('Category updated successfully!');
                    loadCategories(); // Reload categories list
                    bootstrap.Modal.getInstance(document.getElementById('editCategoryModal')).hide(); // Hide modal
                } else {
                    alert('Error updating category: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating the category.');
            }
        });
    }

    // Function to load categories for management and pledge form
    async function loadCategories() {
        try {
            const result = await postData('api/cfo_actions.php', { action: 'get_categories', csrf_token: '<?= $csrf_token ?>' });
            const categoryList = document.getElementById('categoryList');
            const pledgeCategorySelect = document.getElementById('pledgeCategory');
            const editPledgeCategorySelect = document.getElementById('editPledgeCategory');

            if (result.success && result.categories) {
                // Update management list
                categoryList.innerHTML = '';
                pledgeCategorySelect.innerHTML = '<option value="">Select Category</option>';
                editPledgeCategorySelect.innerHTML = '<option value="">Select Category</option>';

                result.categories.forEach(category => {
                    categoryList.innerHTML += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${htmlspecialchars(category.category_name)}
                        <div>
                            <button class="btn btn-sm btn-warning edit-category-btn me-2" data-id="${category.category_id}" data-name="${htmlspecialchars(category.category_name)}" data-description="${htmlspecialchars(category.description)}" data-bs-toggle="modal" data-bs-target="#editCategoryModal"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-danger delete-category-btn" data-id="${category.category_id}"><i class="bi bi-trash"></i></button>
                        </div>
                    </li>`;
                    pledgeCategorySelect.innerHTML += `<option value="${category.category_id}">${htmlspecialchars(category.category_name)}</option>`;
                    editPledgeCategorySelect.innerHTML += `<option value="${category.category_id}">${htmlspecialchars(category.category_name)}</option>`;
                });

                // Add event listeners for edit/delete buttons
                categoryList.querySelectorAll('.edit-category-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('editCategoryId').value = this.dataset.id;
                        document.getElementById('editCategoryName').value = this.dataset.name;
                        document.getElementById('editCategoryDescription').value = this.dataset.description;
                    });
                });
                categoryList.querySelectorAll('.delete-category-btn').forEach(button => {
                    button.addEventListener('click', async function() {
                        if (confirm('Are you sure you want to delete this category? This cannot be undone if pledges are assigned.')) {
                            const categoryId = this.dataset.id;
                            try {
                                const deleteResult = await postData('api/cfo_actions.php', { action: 'delete_category', category_id: categoryId, csrf_token: '<?= $csrf_token ?>' });
                                if (deleteResult.success) {
                                    alert('Category deleted successfully!');
                                    loadCategories();
                                } else {
                                    alert('Error deleting category: ' + deleteResult.message);
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                alert('An error occurred while deleting the category.');
                            }
                        }
                    });
                });

            } else {
                console.error('Failed to load categories:', result.message);
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    // Load categories when manage categories modal is shown
    const manageCategoriesModal = document.getElementById('manageCategoriesModal');
    if (manageCategoriesModal) {
        manageCategoriesModal.addEventListener('show.bs.modal', loadCategories);
    }

    // Function to load members for pledge form
    async function loadMembers() {
        try {
            const result = await postData('api/cfo_actions.php', { action: 'get_members', csrf_token: '<?= $csrf_token ?>' });
            const memberSelect = document.getElementById('memberId');
            // const editPledgeMemberSelect = document.getElementById('editPledgeMember'); // This is a text input, not select

            if (result.success && result.members) {
                memberSelect.innerHTML = '<option value="">Select Member</option>';
                result.members.forEach(member => {
                    memberSelect.innerHTML += `<option value="${member.member_id}">${htmlspecialchars(member.first_name)} ${htmlspecialchars(member.last_name)}</option>`;
                });
            } else {
                console.error('Failed to load members:', result.message);
            }
        } catch (error) {
            console.error('Error loading members:', error);
        }
    }

    // Load members when add pledge modal is shown
    const addPledgeModal = document.getElementById('addPledgeModal');
    if (addPledgeModal) {
        addPledgeModal.addEventListener('show.bs.modal', loadMembers);
    }

    // Handle Edit Pledge button click
    document.querySelectorAll('.edit-pledge-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const pledgeId = this.dataset.id;
            try {
                const result = await postData('api/cfo_actions.php', { action: 'get_pledge_details', pledge_id: pledgeId, csrf_token: '<?= $csrf_token ?>' });
                if (result.success && result.pledge) {
                    const pledge = result.pledge;
                    document.getElementById('editPledgeId').value = pledge.pledge_id;
                    document.getElementById('editPledgeMember').value = `${pledge.first_name} ${pledge.last_name}`;
                    document.getElementById('editPledgeAmount').value = pledge.amount;
                    document.getElementById('editPledgeDueDate').value = pledge.due_date;
                    document.getElementById('editPledgeCategory').value = pledge.category_id;
                    document.getElementById('editPledgeStatus').value = pledge.status;
                    document.getElementById('editPledgeDescription').value = pledge.description;

                    // Load categories for the edit pledge modal
                    await loadCategories(); // Ensure categories are loaded before setting value
                    document.getElementById('editPledgeCategory').value = pledge.category_id;

                    const editPledgeModal = new bootstrap.Modal(document.getElementById('editPledgeModal'));
                    editPledgeModal.show();
                } else {
                    alert('Error fetching pledge details: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while fetching pledge details.');
            }
        });
    });

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
        return str.replace(/[&<>'""]/g, function(m) { return map[m]; });
    }

    // Helper for formatDate in JS (assuming format is YYYY-MM-DD HH:MM:SS)
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // CFO Alerts specific elements
    const cfoAlertsTableBody = document.getElementById('cfoAlertsTableBody');
    const cfoAlertsPagination = document.getElementById('cfoAlertsPagination');
    const noCFOAlertsMessage = document.getElementById('noCFOAlertsMessage');

    // Function to load CFO Alerts via AJAX
    async function loadCFOAlerts(page = 1) {
        try {
            const result = await postData('api/cfo_actions.php', { action: 'get_cfo_alerts', page: page, csrf_token: '<?= $csrf_token ?>' });
            if (result.success && result.alerts) {
                updateCFOAlertsTable(result.alerts);
                updateCFOAlertsPagination(result.total_pages, result.current_page);
                if (result.alerts.length === 0) {
                    noCFOAlertsMessage.style.display = 'block';
                } else {
                    noCFOAlertsMessage.style.display = 'none';
                }
            } else {
                console.error('Failed to load CFO alerts:', result.message);
                cfoAlertsTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${htmlspecialchars(result.message || 'Error loading alerts.')}</td></tr>`;
                noCFOAlertsMessage.style.display = 'none'; // Hide if there's an error message
            }
        } catch (error) {
            console.error('Error loading CFO alerts:', error);
            cfoAlertsTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">An error occurred while fetching alerts.</td></tr>`;
            noCFOAlertsMessage.style.display = 'none'; // Hide if there's an error message
        }
    }

    // Function to update the CFO Alerts table
    function updateCFOAlertsTable(alerts) {
        cfoAlertsTableBody.innerHTML = ''; // Clear existing rows
        if (alerts.length === 0) {
            return; // Handled by noCFOAlertsMessage
        }

        alerts.forEach(alert => {
            let typeClass = '';
            let icon = '';
            switch (alert.type) {
                case 'overdue_pledge':
                    typeClass = 'text-danger';
                    icon = 'bi-exclamation-triangle';
                    break;
                case 'recent_payment':
                    typeClass = 'text-success';
                    icon = 'bi-cash-coin';
                    break;
                case 'recent_pledge':
                    typeClass = 'text-primary';
                    icon = 'bi-gift';
                    break;
                case 'system_alert':
                    typeClass = 'text-warning';
                    icon = 'bi-info-circle';
                    break;
                default:
                    typeClass = 'text-muted';
                    icon = 'bi-bell';
            }

            const row = `
                <tr>
                    <td><i class="bi ${icon} ${typeClass}"></i> ${htmlspecialchars(alert.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td>
                    <td>${htmlspecialchars(alert.description)}</td>
                    <td>${formatDate(alert.date)}</td>
                    <td>${alert.amount ? formatCurrencyJs(alert.amount) : 'N/A'}</td>
                    <td>${htmlspecialchars(alert.related_party || 'N/A')}</td>
                </tr>
            `;
            cfoAlertsTableBody.insertAdjacentHTML('beforeend', row);
        });
    }

    // Function to update CFO Alerts pagination controls
    function updateCFOAlertsPagination(totalPages, currentPage) {
        cfoAlertsPagination.innerHTML = ''; // Clear existing pagination

        if (totalPages <= 1) return;

        let paginationHtml = '';

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

        cfoAlertsPagination.innerHTML = paginationHtml;

        // Add event listeners to new pagination links
        cfoAlertsPagination.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                loadCFOAlerts(parseInt(this.dataset.page));
            });
        });
    }

    // Initial load of CFO Alerts
    loadCFOAlerts(1);

});
</script>