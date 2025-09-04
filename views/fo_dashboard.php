<?php
/**
 * Finance Officer Dashboard
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Ensure user is FO
if ($role !== 'finance_officer') {
    redirect('index.php?page=dashboard');
}

// Get FO's group
$rbac = new RBAC($pdo, $user_id);
$groups = $rbac->getAccessibleGroups('finance_officer');
$group = !empty($groups) ? $groups[0] : null;

if (!$group) {
    $error_message = "You are not assigned to any group as a Finance Officer.";
}

$group_id = $group['group_id'] ?? null;

// Get group financial summary
$group_summary = [];
if ($group_id) {
    $group_summary = $rbac->getFinanceOfficerSummary();

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

    // Prepare data for JavaScript charts
    $chart_data = [
        'group_pledge_status_breakdown' => $group_pledge_status_breakdown,
        'monthly_group_payments' => $monthly_group_payments
    ];
}

// Get group pledges
$pledges = [];
if ($group_id) {
    // Pagination for Group Pledges
    $gp_page = isset($_GET['gp_page']) ? (int)$_GET['gp_page'] : 1;
    $limit_gp = 10;
    $offset_gp = ($gp_page - 1) * $limit_gp;

    $count_gp_stmt = $pdo->prepare("SELECT COUNT(*) FROM pledges p JOIN members m ON p.member_id = m.member_id WHERE m.group_id = ?");
    $count_gp_stmt->execute([$group_id]);
    $total_gp_pledges = $count_gp_stmt->fetchColumn();
    $total_gp_pages = ceil($total_gp_pledges / $limit_gp);

    $stmt = $pdo->prepare("
        SELECT p.*, pc.category_name, m.first_name, m.last_name, g.group_name,
               COALESCE(SUM(t.amount_paid), 0) as paid_amount,
               (p.amount - COALESCE(SUM(t.amount_paid), 0)) as remaining_amount
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
        LEFT JOIN groups g ON p.group_id = g.group_id
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
        WHERE m.group_id = ?
        GROUP BY p.pledge_id
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(1, $group_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit_gp, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset_gp, PDO::PARAM_INT);
    $stmt->execute();
    $pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrf_token = generateCSRFToken();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-person-workspace"></i> Finance Officer Dashboard</h2>
                    <p class="mb-0">Group-level financial management</p>
                </div>
            </div>
        </div>
    </div>

    <div id="alert-container"></div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php elseif (!$group): // Added check for $group ?>
        <div class="alert alert-warning">
            <i class="bi bi-info-circle"></i> You are not assigned to a group or your group information is unavailable. Please contact an administrator.
        </div>
    <?php else: ?>

    <!-- Group Financial Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-graph-up"></i> Group Financial Summary: <?= htmlspecialchars($group['group_name']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body">
                                    <h6>Total Pledges</h6>
                                    <h3><?= number_format($group_summary[0]['pledge_count'] ?? 0) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body">
                                    <h6>Total Pledged</h6>
                                    <h3><?= formatCurrency($group_summary[0]['total_pledged'] ?? 0) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body">
                                    <h6>Total Paid</h6>
                                    <h3><?= formatCurrency($group_summary[0]['total_paid'] ?? 0) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-warning text-white">
                                <div class="card-body">
                                    <h6>Outstanding</h6>
                                    <h3><?= formatCurrency(($group_summary[0]['total_pledged'] ?? 0) - ($group_summary[0]['total_paid'] ?? 0)) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FO CRUD Operations -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-tools"></i> Finance Officer Operations</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createPledgeModal">
                            <i class="bi bi-plus-circle"></i> Add Pledge
                        </button>
                        <a href="index.php?page=payments" class="btn btn-outline-success">
                            <i class="bi bi-credit-card"></i> Record Payments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Group Pledges -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-list-check"></i> Group Pledges</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pledges)): ?>
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
                                            <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
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
                                            </td>
                                            <td><?= getStatusBadge($pledge['status']) ?></td>
                                            <td><?= formatDate($pledge['due_date']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editPledge(<?= $pledge['pledge_id'] ?>)"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deletePledge(<?= $pledge['pledge_id'] ?>)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                    </div>

                    <!-- Pagination for Group Pledges -->
                    <nav aria-label="Group Pledges Pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($gp_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=fo_dashboard&gp_page=<?= $gp_page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_gp_pages; $i++): ?>
                                <li class="page-item <?= $i == $gp_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=fo_dashboard&gp_page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($gp_page < $total_gp_pages): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=fo_dashboard&gp_page=<?= $gp_page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No pledges found for this group.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<!-- Add Pledge Modal -->
<div class="modal fade" id="createPledgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="createPledgeForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Pledge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_pledge">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <!-- Form fields for creating a pledge -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Pledge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Pledge Modal -->
<div class="modal fade" id="editPledgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editPledgeForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pledge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_pledge">
                    <input type="hidden" name="pledge_id" id="edit_pledge_id">
                    <!-- Form fields for editing a pledge -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    const apiEndpoint = 'api/fo_actions.php';
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

    // Handle Delete Pledge
    window.deletePledge = function (pledgeId) {
        if (confirm('Are you sure you want to delete this pledge?')) {
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
            });
        }
    }

    <?php if (isset($chart_data)): ?>
    const foChartData = <?= json_encode($chart_data) ?>;
    const groupPledgeStatusBreakdown = foChartData.group_pledge_status_breakdown;
    const monthlyGroupPayments = foChartData.monthly_group_payments;

    // Helper function to format currency
    function formatCurrencyJs(amount) {
        return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(amount);
    }

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
    <?php endif; ?>
});
</script>