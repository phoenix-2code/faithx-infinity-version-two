<?php
/**
 * Simplified Payments Page - Record and manage payments
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user can record payments (finance officers and above)
$can_record_payments = in_array($role, ['finance_officer', 'chief_finance_officer']);

// Get pledges for payment recording
$pledges = [];
try {
    // Pagination for Pledges Awaiting Payment
    $pap_page = isset($_GET['pap_page']) ? (int)$_GET['pap_page'] : 1;
    $limit_pap = 10;
    $offset_pap = ($pap_page - 1) * $limit_pap;

    $count_sql = "SELECT COUNT(*) FROM pledges p JOIN members m ON p.member_id = m.member_id WHERE p.status IN ('active', 'pending')";
    $sql = "SELECT p.*, pc.category_name, m.first_name, m.last_name, g.group_name,
                   COALESCE(SUM(t.amount_paid), 0) as paid_amount,
                   (p.amount - COALESCE(SUM(t.amount_paid), 0)) as remaining_amount
            FROM pledges p
            JOIN members m ON p.member_id = m.member_id
            LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
            LEFT JOIN groups g ON p.group_id = g.group_id
            LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
            WHERE p.status IN ('active', 'pending')";

    if ($role === 'system_admin') {
        // System admin can see all pledges
    } else {
        // Finance officers see their group pledges, members see their own
        if ($role === 'finance_officer' || $role === 'chief_finance_officer') {
            // No additional WHERE clause needed for FO/CFO to see all active/pending pledges
        } else {
            // Members see only their own pledges
            $count_sql .= " AND m.user_id = ?";
            $sql .= " AND m.user_id = ?";
        }
    }

    $count_stmt = $pdo->prepare($count_sql);
    if ($role !== 'system_admin' && $role !== 'finance_officer' && $role !== 'chief_finance_officer') {
        $count_stmt->execute([$user_id]);
    } else {
        $count_stmt->execute();
    }
    $total_pap_pledges = $count_stmt->fetchColumn();
    $total_pap_pages = ceil($total_pap_pledges / $limit_pap);

    $sql .= " GROUP BY p.pledge_id ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);

    if ($role !== 'system_admin' && $role !== 'finance_officer' && $role !== 'chief_finance_officer') {
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit_pap, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset_pap, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit_pap, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset_pap, PDO::PARAM_INT);
    }

    $stmt->execute();
    $pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error loading pledges: " . $e->getMessage();
}

// Get recent payments
$recent_payments = [];
try {
    // Pagination for Recent Payments
    $rp_page = isset($_GET['rp_page']) ? (int)$_GET['rp_page'] : 1;
    $limit_rp = 10;
    $offset_rp = ($rp_page - 1) * $limit_rp;

    $count_rp_stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
    $total_rp_payments = $count_rp_stmt->fetchColumn();
    $total_rp_pages = ceil($total_rp_payments / $limit_rp);

    $stmt = $pdo->prepare("
        SELECT t.*, p.amount as pledge_amount, m.first_name, m.last_name, g.group_name, u.username AS recorded_by_username
        FROM transactions t
        JOIN pledges p ON t.pledge_id = p.pledge_id
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN groups g ON p.group_id = g.group_id
        LEFT JOIN users u ON t.recorded_by = u.user_id
        ORDER BY t.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit_rp, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset_rp, PDO::PARAM_INT);
    $stmt->execute();
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

$csrf_token = generateCSRFToken();
?>



<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-credit-card"></i> Payment Management</h2>
                    <p class="mb-0">Record and track pledge payments</p>
                </div>
            </div>
        </div>
    </div>

    <div id="alert-container"></div>

    <!-- Record Payment Button -->
    <?php if ($can_record_payments): ?>
    <div class="row mb-3">
        <div class="col-12">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                <i class="bi bi-plus-circle"></i> Record New Payment
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($role !== 'system_admin'): ?>
    <div class="row">
        <!-- Pledges Awaiting Payment -->
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Pledges Awaiting Payment</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pledges)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Group</th>
                                    <th>Category</th>
                                    <th>Pledge Amount</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <?php if ($can_record_payments): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
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
                                    <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                    <td><?= htmlspecialchars($pledge['group_name'] ?? 'No Group') ?></td>
                                    <td><?= htmlspecialchars($pledge['category_name'] ?? 'General') ?></td>
                                    <td><?= formatCurrency($pledge_amount) ?></td>
                                    <td><?= formatCurrency($paid_amount) ?></td>
                                    <td><?= formatCurrency($remaining_amount) ?></td>
                                    <td>
                                        <div class="progress" style="width: 100px; height: 20px;">
                                            <div class="progress-bar <?= $progress_class ?>" 
                                                 style="width: <?= $display_progress ?>%">
                                                <?= number_format($progress, 0) ?>%
                                            </div>
                                        </div>
                                        <?php if ($progress > 100): ?>
                                        <small class="text-success">Overpaid by <?= number_format($progress - 100, 1) ?>%</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($pledge['status']) ?></td>
                                    <?php if ($can_record_payments): ?>
                                    <td>
                                        <?php if ($remaining_amount > 0): ?>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="recordPayment(<?= $pledge['pledge_id'] ?>, '<?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?>', <?= $pledge_amount ?>, <?= $paid_amount ?>, <?= $remaining_amount ?>)">
                                            <i class="bi bi-credit-card"></i> Record Payment
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination for Pledges Awaiting Payment -->
                    <nav aria-label="Pledges Awaiting Payment Pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($pap_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=payments&pap_page=<?= $pap_page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pap_pages; $i++): ?>
                                <li class="page-item <?= $i == $pap_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=payments&pap_page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($pap_page < $total_pap_pages): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=payments&pap_page=<?= $pap_page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No pledges awaiting payment.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Payments -->
    <?php if (!empty($recent_payments)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Payments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Group</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?= formatDate($payment['payment_date']) ?></td>
                                    <td><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></td>
                                    <td><?= htmlspecialchars($payment['group_name'] ?? 'No Group') ?></td>
                                    <td><?= formatCurrency($payment['amount_paid']) ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($payment['recorded_by_username'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination for Recent Payments -->
                    <nav aria-label="Recent Payments Pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($rp_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=payments&rp_page=<?= $rp_page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_rp_pages; $i++): ?>
                                <li class="page-item <?= $i == $rp_page ? 'active' : '' ?>"><a class="page-link" href="index.php?page=payments&rp_page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($rp_page < $total_rp_pages): ?>
                                <li class="page-item"><a class="page-link" href="index.php?page=payments&rp_page=<?= $rp_page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Record Payment Modal -->
<?php if ($can_record_payments): ?>
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="recordPaymentForm">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-credit-card"></i> Record Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="pledge_id" id="modal_pledge_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_pledge_select" class="form-label">Select Pledge</label>
                                <select class="form-select" id="modal_pledge_select" name="pledge_id_select" onchange="updatePledgeInfo()">
                                    <option value="">Choose a pledge...</option>
                                    <?php foreach ($pledges as $pledge): ?>
                                    <option value="<?= $pledge['pledge_id'] ?>" 
                                            data-amount="<?= $pledge['amount'] ?>"
                                            data-paid="<?= $pledge['paid_amount'] ?>"
                                            data-remaining="<?= $pledge['remaining_amount'] ?>"
                                            data-member="<?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?>">
                                        <?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?> - 
                                        <?= formatCurrency($pledge['amount']) ?> 
                                        (<?= htmlspecialchars($pledge['group_name'] ?? 'No Group') ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="modal-pledge-info" class="alert alert-info d-none">
                                <small>
                                    <strong>Member:</strong> <span id="modal-member-name">-</span><br>
                                    <strong>Pledge Amount:</strong> <span id="modal-pledge-amount">-</span><br>
                                    <strong>Already Paid:</strong> <span id="modal-amount-paid">-</span><br>
                                    <strong>Remaining:</strong> <span id="modal-amount-remaining">-</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_amount_paid" class="form-label">Payment Amount</label>
                                <input type="number" class="form-control" id="modal_amount_paid" name="amount_paid" 
                                       step="0.01" min="0.01" required>
                                <div class="invalid-feedback">Please enter a valid payment amount.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="modal_payment_method" name="payment_method" required>
                                    <option value="">Select method...</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="online">Online Payment</option>
                                </select>
                                <div class="invalid-feedback">Please select a payment method.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_payment_date" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="modal_payment_date" name="payment_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Please select a payment date.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_reference_number" class="form-label">Reference Number</label>
                                <input type="text" class="form-control" id="modal_reference_number" name="reference_number" 
                                       placeholder="Optional reference number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="2" 
                                  placeholder="Optional payment notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiEndpoint = 'api/payment_actions.php';

    // Handle Record Payment Form
    const recordPaymentForm = document.getElementById('recordPaymentForm');
    if(recordPaymentForm) {
        recordPaymentForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(recordPaymentForm);
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
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal'));
                    modal.hide();
                    location.reload();
                }
            });
        });
    }

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

function recordPayment(pledgeId, memberName, pledgeAmount, paidAmount, remainingAmount) {
    // Set the hidden pledge ID
    document.getElementById('modal_pledge_id').value = pledgeId;
    
    // Update the select dropdown
    const selectElement = document.getElementById('modal_pledge_select');
    selectElement.value = pledgeId;
    
    // Update pledge info
    updatePledgeInfo();
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('recordPaymentModal'));
    modal.show();
}

function updatePledgeInfo() {
    const selectElement = document.getElementById('modal_pledge_select');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const pledgeInfo = document.getElementById('modal-pledge-info');
    const amountInput = document.getElementById('modal_amount_paid');
    
    if (selectedOption.value) {
        const amount = parseFloat(selectedOption.dataset.amount);
        const paid = parseFloat(selectedOption.dataset.paid);
        const remaining = parseFloat(selectedOption.dataset.remaining);
        const member = selectedOption.dataset.member;
        
        document.getElementById('modal-member-name').textContent = member;
        document.getElementById('modal-pledge-amount').textContent = formatCurrency(amount);
        document.getElementById('modal-amount-paid').textContent = formatCurrency(paid);
        document.getElementById('modal-amount-remaining').textContent = formatCurrency(remaining);
        
        pledgeInfo.classList.remove('d-none');
        amountInput.max = remaining;
        amountInput.value = remaining;
        
        // Update hidden field
        document.getElementById('modal_pledge_id').value = selectedOption.value;
    } else {
        pledgeInfo.classList.add('d-none');
        amountInput.max = '';
        amountInput.value = '';
        document.getElementById('modal_pledge_id').value = '';
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES'
    }).format(amount);
}
</script>