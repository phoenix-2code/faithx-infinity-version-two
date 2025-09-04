<?php
/**
 * Member Payment Submission Page
 * Allows members to submit their payments for verification
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only members can access this page
if ($role !== 'member') {
    header('Location: index.php?page=dashboard');
    exit;
}

// Get member's active pledges
$pledges = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.pledge_id, p.amount, p.due_date, p.target_date, p.status, p.notes,
            pc.category_name, g.group_name,
            COALESCE(SUM(t.amount_paid), 0) as paid_amount,
            (p.amount - COALESCE(SUM(t.amount_paid), 0)) as remaining_amount
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
        LEFT JOIN groups g ON p.group_id = g.group_id
        LEFT JOIN transactions t ON p.pledge_id = t.pledge_id AND t.verification_status = 'verified'
        WHERE m.user_id = ? AND p.status IN ('active', 'pending')
        GROUP BY p.pledge_id
        ORDER BY p.due_date ASC
    ");
    $stmt->execute([$user_id]);
    $pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error loading pledges: " . $e->getMessage();
}

// Get member's recent payment submissions
$recent_submissions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.transaction_id, t.amount_paid, t.payment_method, t.verification_method,
            t.verification_reference, t.verification_status, t.payment_date,
            t.member_submitted_at, t.verified_at, t.verification_notes,
            p.pledge_id, pc.category_name,
            verifier.first_name as verifier_first_name,
            verifier.last_name as verifier_last_name
        FROM transactions t
        JOIN pledges p ON t.pledge_id = p.pledge_id
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
        LEFT JOIN users verifier ON t.verified_by = verifier.user_id
        WHERE m.user_id = ? AND t.member_submitted_by = ?
        ORDER BY t.member_submitted_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message_submissions = "Error loading recent submissions: " . $e->getMessage();
}

?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-credit-card"></i> Submit Payment for Verification</h2>
                    <p class="mb-0">Submit your payment details for verification by finance officers</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <!-- Payment Submission Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Submit New Payment</h5>
                </div>
                <div class="card-body">
                    <form id="paymentSubmissionForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="pledge_id" class="form-label">Select Pledge <span class="text-danger">*</span></label>
                                    <select class="form-select" id="pledge_id" name="pledge_id" required>
                                        <option value="">Choose a pledge...</option>
                                        <?php foreach ($pledges as $pledge): ?>
                                            <?php if ($pledge['remaining_amount'] > 0): ?>
                                                <option value="<?= $pledge['pledge_id'] ?>" 
                                                        data-remaining="<?= $pledge['remaining_amount'] ?>"
                                                        data-category="<?= htmlspecialchars($pledge['category_name']) ?>">
                                                    <?= htmlspecialchars($pledge['category_name']) ?> - 
                                                    <?= formatCurrency($pledge['remaining_amount']) ?> remaining
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount_paid" class="form-label">Amount Paid <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                                           step="0.01" min="0.01" required>
                                    <div class="form-text">Enter the exact amount you paid</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select payment method...</option>
                                        <option value="mobile_money">M-Pesa (Mobile Money)</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Cheque</option>
                                        <option value="cash">Cash</option>
                                        <option value="online">Online Payment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dynamic verification method section -->
                        <div id="verification_method_section" style="display: none;">
                            <div class="mb-3">
                                <label for="verification_method" class="form-label">Verification Method <span class="text-danger">*</span></label>
                                <select class="form-select" id="verification_method" name="verification_method" required>
                                    <option value="">Select verification method...</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="verification_reference" class="form-label">Reference/Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="verification_reference" name="verification_reference" 
                                       placeholder="Enter your payment reference" required>
                                <div class="form-text" id="verification_help"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any additional information about this payment..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Important:</strong> Your payment will be reviewed by finance officers before being confirmed. 
                            You will receive a notification once it's verified.
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Submit Payment for Verification
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Submissions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Payment Submissions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_submissions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Pledge</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                    <th>Verified By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_submissions as $submission): ?>
                                <tr>
                                    <td><?= formatDate($submission['payment_date']) ?></td>
                                    <td><?= htmlspecialchars($submission['category_name']) ?></td>
                                    <td><?= formatCurrency($submission['amount_paid']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $submission['payment_method'])) ?></td>
                                    <td>
                                        <code><?= htmlspecialchars($submission['verification_reference']) ?></code>
                                    </td>
                                    <td><?= getVerificationStatusBadge($submission['verification_status']) ?></td>
                                    <td>
                                        <?php if ($submission['verification_status'] === 'verified' && $submission['verifier_first_name']): ?>
                                            <?= htmlspecialchars($submission['verifier_first_name'] . ' ' . $submission['verifier_last_name']) ?>
                                        <?php elseif ($submission['verification_status'] === 'rejected'): ?>
                                            <span class="text-muted">Rejected</span>
                                        <?php else: ?>
                                            <span class="text-muted">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No payment submissions yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const verificationSection = document.getElementById('verification_method_section');
    const verificationMethodSelect = document.getElementById('verification_method');
    const verificationReferenceInput = document.getElementById('verification_reference');
    const verificationHelp = document.getElementById('verification_help');
    const pledgeSelect = document.getElementById('pledge_id');
    const amountInput = document.getElementById('amount_paid');
    
    // Handle payment method change
    paymentMethodSelect.addEventListener('change', function() {
        const paymentMethod = this.value;
        
        if (paymentMethod) {
            // Show verification section
            verificationSection.style.display = 'block';
            
            // Load verification methods for this payment method
            fetch('api/payment_verification_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'get_verification_methods',
                    payment_method: paymentMethod
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    verificationMethodSelect.innerHTML = '<option value="">Select verification method...</option>';
                    data.methods.forEach(method => {
                        verificationMethodSelect.innerHTML += `<option value="${method.value}" data-placeholder="${method.placeholder}" data-pattern="${method.pattern}">${method.label}</option>`;
                    });
                }
            })
            .catch(error => {
                console.error('Error loading verification methods:', error);
            });
        } else {
            verificationSection.style.display = 'none';
        }
    });
    
    // Handle verification method change
    verificationMethodSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.dataset.placeholder) {
            verificationReferenceInput.placeholder = selectedOption.dataset.placeholder;
            verificationReferenceInput.pattern = selectedOption.dataset.pattern;
            verificationHelp.textContent = `Format: ${selectedOption.dataset.placeholder}`;
        }
    });
    
    // Handle pledge selection - set max amount
    pledgeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.dataset.remaining) {
            amountInput.max = selectedOption.dataset.remaining;
        }
    });
    
    // Handle form submission
    document.getElementById('paymentSubmissionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // Create a transaction first, then submit for verification
        fetch('api/payment_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'record_payment',
                pledge_id: data.pledge_id,
                amount_paid: data.amount_paid,
                payment_method: data.payment_method,
                payment_date: data.payment_date,
                reference_number: data.verification_reference,
                notes: data.notes
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Now submit for verification
                return fetch('api/payment_verification_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'submit_payment_verification',
                        transaction_id: result.transaction_id,
                        verification_method: data.verification_method,
                        verification_reference: data.verification_reference,
                        notes: data.notes
                    })
                });
            } else {
                throw new Error(result.message);
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Payment submitted for verification successfully!');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting the payment.');
        });
    });
});
</script>