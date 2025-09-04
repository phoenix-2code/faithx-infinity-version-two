<?php
/**
 * Payment Verification Page
 * For Finance Officers and CFOs to verify member payment submissions
 */
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if user can verify payments
$can_verify_payments = in_array($role, ['finance_officer', 'chief_finance_officer']);

if (!$can_verify_payments) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Get user's accessible groups for verification
$accessible_groups = [];
try {
    if ($role === 'chief_finance_officer') {
        // CFO can verify all groups
        $stmt = $pdo->prepare("SELECT group_id, group_name FROM groups ORDER BY group_name");
        $stmt->execute();
        $accessible_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Finance officers can verify their assigned groups
        $stmt = $pdo->prepare("
            SELECT g.group_id, g.group_name 
            FROM groups g 
            JOIN user_group_access uga ON g.group_id = uga.group_id 
            WHERE uga.user_id = ? AND uga.access_level = 'finance_officer'
            ORDER BY g.group_name
        ");
        $stmt->execute([$user_id]);
        $accessible_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error_message = "Error loading accessible groups: " . $e->getMessage();
}

?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-shield-check"></i> Payment Verification</h2>
                    <p class="mb-0">Review and verify member payment submissions</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <!-- Verification Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card bg-warning text-white">
                <div class="card-body">
                    <h6>Pending Verification</h6>
                    <h3 id="pending-count">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-success text-white">
                <div class="card-body">
                    <h6>Verified Today</h6>
                    <h3 id="verified-today">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-danger text-white">
                <div class="card-body">
                    <h6>Rejected Today</h6>
                    <h3 id="rejected-today">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-info text-white">
                <div class="card-body">
                    <h6>Total Processed</h6>
                    <h3 id="total-processed">-</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Verifications -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-hourglass-split"></i> Pending Verifications</h5>
                    <button class="btn btn-outline-light btn-sm" onclick="loadPendingVerifications()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div id="pending-verifications-container">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading pending verifications...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Verification History</h5>
                </div>
                <div class="card-body">
                    <div id="verification-history-container">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading verification history...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1" aria-labelledby="verificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="verificationModalLabel">Verify Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="verificationForm">
                    <input type="hidden" id="verification_transaction_id" name="transaction_id">
                    
                    <!-- Payment Details -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Member:</strong> <span id="verification_member_name"></span><br>
                            <strong>Group:</strong> <span id="verification_group_name"></span><br>
                            <strong>Pledge:</strong> <span id="verification_pledge_category"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Amount:</strong> <span id="verification_amount"></span><br>
                            <strong>Method:</strong> <span id="verification_payment_method"></span><br>
                            <strong>Date:</strong> <span id="verification_payment_date"></span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Verification Details -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Verification Method:</strong> <span id="verification_method"></span><br>
                            <strong>Reference:</strong> <code id="verification_reference"></code>
                        </div>
                        <div class="col-md-6">
                            <strong>Submitted:</strong> <span id="verification_submitted_at"></span><br>
                            <strong>Notes:</strong> <span id="verification_notes"></span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Verification Decision -->
                    <div class="mb-3">
                        <label class="form-label"><strong>Verification Decision</strong></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="verification_status" id="verify_approved" value="verified" required>
                            <label class="form-check-label text-success" for="verify_approved">
                                <i class="bi bi-check-circle"></i> Approve Payment
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="verification_status" id="verify_rejected" value="rejected" required>
                            <label class="form-check-label text-danger" for="verify_rejected">
                                <i class="bi bi-x-circle"></i> Reject Payment
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_notes_input" class="form-label">Verification Notes</label>
                        <textarea class="form-control" id="verification_notes_input" name="verification_notes" rows="3" 
                                  placeholder="Add any notes about this verification..."></textarea>
                    </div>
                    
                    <div class="mb-3" id="rejection_reason_section" style="display: none;">
                        <label for="rejection_reason" class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="2" 
                                  placeholder="Explain why this payment is being rejected..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitVerification()">
                    <i class="bi bi-check-circle"></i> Submit Verification
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadPendingVerifications();
    loadVerificationHistory();
    
    // Handle verification status change
    document.querySelectorAll('input[name="verification_status"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const rejectionSection = document.getElementById('rejection_reason_section');
            if (this.value === 'rejected') {
                rejectionSection.style.display = 'block';
                document.getElementById('rejection_reason').required = true;
            } else {
                rejectionSection.style.display = 'none';
                document.getElementById('rejection_reason').required = false;
            }
        });
    });
});

function loadPendingVerifications() {
    fetch('api/payment_verification_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'get_pending_verifications',
            page: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPendingVerifications(data.verifications);
            updateStatistics(data.verifications);
        } else {
            document.getElementById('pending-verifications-container').innerHTML = 
                `<div class="alert alert-danger">Error loading verifications: ${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('pending-verifications-container').innerHTML = 
            `<div class="alert alert-danger">An error occurred while loading verifications.</div>`;
    });
}

function displayPendingVerifications(verifications) {
    const container = document.getElementById('pending-verifications-container');
    
    if (verifications.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No pending verifications. All caught up!</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>';
    html += '<th>Member</th><th>Group</th><th>Amount</th><th>Method</th><th>Reference</th><th>Submitted</th><th>Actions</th>';
    html += '</tr></thead><tbody>';
    
    verifications.forEach(verification => {
        html += `<tr>
            <td>${verification.first_name} ${verification.last_name}</td>
            <td>${verification.group_name}</td>
            <td>${formatCurrency(verification.amount_paid)}</td>
            <td>${verification.payment_method.replace('_', ' ')}</td>
            <td><code>${verification.verification_reference}</code></td>
            <td>${formatDate(verification.member_submitted_at)}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="openVerificationModal(${verification.transaction_id})">
                    <i class="bi bi-eye"></i> Review
                </button>
            </td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function openVerificationModal(transactionId) {
    // Load transaction details and populate modal
    // This would typically fetch from an API endpoint
    // For now, we'll use the data from the table
    
    const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
    document.getElementById('verification_transaction_id').value = transactionId;
    modal.show();
}

function submitVerification() {
    const form = document.getElementById('verificationForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('api/payment_verification_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'verify_payment',
            transaction_id: data.transaction_id,
            verification_status: data.verification_status,
            verification_notes: data.verification_notes,
            rejection_reason: data.rejection_reason
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Payment verification submitted successfully!');
            bootstrap.Modal.getInstance(document.getElementById('verificationModal')).hide();
            loadPendingVerifications();
            loadVerificationHistory();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting verification.');
    });
}

function loadVerificationHistory() {
    // This would load recent verification history
    // Implementation depends on your specific requirements
    document.getElementById('verification-history-container').innerHTML = `
        <div class="text-center py-4">
            <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-2">Verification history will be displayed here.</p>
        </div>
    `;
}

function updateStatistics(verifications) {
    document.getElementById('pending-count').textContent = verifications.length;
    // Additional statistics would be calculated here
}

// Helper functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(amount);
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>