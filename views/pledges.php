<?php
/**
 * Simple Pledges Page - Complete pledge management
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get pledge categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category_id, category_name, description FROM pledge_categories WHERE is_active = 1 ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get user's pledges
$pledges = [];
try {
    $count_sql = "SELECT COUNT(*) FROM pledges p";
    $sql = "SELECT p.*, pc.category_name, m.first_name, m.last_name, g.group_name, COALESCE(SUM(t.amount_paid), 0) as paid_amount, (p.amount - COALESCE(SUM(t.amount_paid), 0)) as remaining_amount FROM pledges p JOIN members m ON p.member_id = m.member_id LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id LEFT JOIN groups g ON p.group_id = g.group_id LEFT JOIN transactions t ON p.pledge_id = t.pledge_id";

    if ($role === 'system_admin') {
        // System admin sees all pledges
    } elseif ($role === 'finance_officer' || $role === 'chief_finance_officer') {
        // Finance officers see all pledges
    } else {
        // Members see only their own pledges
        $count_sql .= " WHERE m.user_id = ?";
        $sql .= " WHERE m.user_id = ?";
    }

    $stmt = $pdo->prepare($count_sql);
    if ($role !== 'system_admin' && $role !== 'finance_officer' && $role !== 'chief_finance_officer') {
        $stmt->execute([$user_id]);
    }
    else {
        $stmt->execute();
    }
    $total_pledges = $stmt->fetchColumn();
    $total_pages = ceil($total_pledges / $limit);

    $sql .= " GROUP BY p.pledge_id ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);

    if ($role !== 'system_admin' && $role !== 'finance_officer' && $role !== 'chief_finance_officer') {
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error loading pledges: " . $e->getMessage();
}

$csrf_token = generateCSRFToken();
?>



<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-gift"></i> Pledge Management</h2>
                    <p class="mb-0">Create and manage your pledges</p>
                </div>
            </div>
        </div>
    </div>

    <div id="alert-container"></div>

    <!-- Create Pledge Button -->
    <div class="row mb-3">
        <div class="col-12">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPledgeModal">
                <i class="bi bi-plus-circle"></i> Create New Pledge
            </button>
        </div>
    </div>

    <!-- Pledges List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-check"></i> 
                        <?= $role === 'member' ? 'My Pledges' : 'All Pledges' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pledges)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <?php if ($role !== 'member'): ?>
                                    <th>Member</th>
                                    <th>Group</th>
                                    <?php endif; ?>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pledges as $pledge): ?>
                                <?php 
                                $pledge_amount = $pledge['amount'] ?? 0;
                                $paid_amount = $pledge['paid_amount'] ?? 0;
                                $remaining_amount = $pledge['remaining_amount'] ?? 0;

                                $progress = $pledge_amount > 0 ? 
                                    ($paid_amount / $pledge_amount) * 100 : 0;
                                $display_progress = min(100, $progress);
                                $progress_class = $progress >= 100 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : 'bg-warning');
                                ?>
                                <tr class="<?= getOverdueIndicator($pledge) ?>">
                                    <?php if ($role !== 'member'): ?>
                                    <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                    <td><?= htmlspecialchars($pledge['group_name'] ?? 'No Group') ?></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($pledge['category_name'] ?? 'General') ?></td>
                                    <td><?= formatCurrency($pledge_amount) ?></td>
                                    <td><?= formatCurrency($paid_amount) ?></td>
                                    <td><?= formatCurrency($remaining_amount) ?></td>
                                    <td>
                                        <div class="progress" style="width: 100px; height: 20px;">
                                            <div class="progress-bar <?= $progress_class ?>" 
                                                 style="width: <?= $display_progress ?>%">
                                                <?= number_format($progress, 1) ?>%
                                            </div>
                                        </div>
                                        <?php if ($progress > 100): ?>
                                        <small class="text-success">Overpaid by <?= number_format($progress - 100, 1) ?>%</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($pledge['status']) ?></td>
                                    <td>
                                        <?php if ($pledge['due_date']): ?>
                                        <?= formatDate($pledge['due_date']) ?>
                                        <?php else: ?>
                                        <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatDate($pledge['created_at']) ?></td>
                                    <td>
                                        <?php if ($role !== 'elder_admin'): // Elder admin should not see action buttons on this page ?>
                                            <?php if ($role === 'member' && $pledge['status'] === 'active'): ?>
                                            <button class="btn btn-sm btn-outline-primary ms-1" 
                                                    onclick="editPledge(<?= $pledge['pledge_id'] ?>, '<?= htmlspecialchars($pledge['payment_schedule']) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php elseif (in_array($role, ['finance_officer', 'chief_finance_officer', 'system_admin'])): // FO, CFO, System Admin can manage pledges from here ?>
                                            <?php if ($role !== 'system_admin'): // Only show edit for FO/CFO ?>
                                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#editPledgeModal" data-pledge='<?= json_encode($pledge) ?>'><i class="bi bi-pencil"></i></button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePledge(<?= $pledge['pledge_id'] ?>)"><i class="bi bi-trash"></i></button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=pledges&p=<?= $page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=pledges&p=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=pledges&p=<?= $page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">
                            <?= $role === 'member' ? 'You have no pledges yet.' : 'No pledges found.' ?>
                        </p>
                        <?php if ($role === 'member'): ?>
                        <p class="text-muted">Create your first pledge using the form above.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Pledge Modal -->
<div class="modal fade" id="createPledgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="createPledgeForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Pledge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_pledge">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_category_id" class="form-label">Category</label>
                                <select class="form-select" id="modal_category_id" name="category_id" required>
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
                                <label for="modal_amount" class="form-label">Pledge Amount</label>
                                <input type="number" class="form-control" id="modal_amount" name="amount" 
                                       step="0.01" min="0.01" required>
                                <div class="invalid-feedback">Please enter a valid amount.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_payment_schedule" class="form-label">Payment Schedule</label>
                                <select class="form-select" id="modal_payment_schedule" name="payment_schedule">
                                    <option value="one_time">One Time</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="annually">Annually</option>
                                </select>
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="2" 
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

<!-- Other Modals -->

<!-- Feedback Modal -->
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const apiEndpoint = 'api/pledge_actions.php';
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

    // Handle Create Pledge Form
    const createPledgeForm = document.getElementById('createPledgeForm');
    createPledgeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(createPledgeForm);
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
            const modal = bootstrap.Modal.getInstance(document.getElementById('createPledgeModal'));
            modal.hide();
            showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }
        });
    });

    // Handle Edit Pledge Form
    const editPledgeForm = document.getElementById('editPledgeForm');
    if(editPledgeForm) {
        editPledgeForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(editPledgeForm);
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
                const modal = bootstrap.Modal.getInstance(document.getElementById('editPledgeModal'));
                modal.hide();
                showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                if (result.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            });
        });
    }

    // Handle Delete Pledge
    window.deletePledge = function(pledgeId) {
        if (confirm('Are you sure you want to delete this pledge? This action cannot be undone.')) {
            fetch(apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'delete_pledge', pledge_id: pledgeId })
            })
            .then(response => response.json())
            .then(result => {
                showFeedback(result.success ? 'Success' : 'Error', result.message, result.success);
                if (result.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                console.error('Error deleting pledge:', error);
                showFeedback('Error', 'An error occurred while trying to delete the pledge.', false);
            });
        }
    };
});
</script>
