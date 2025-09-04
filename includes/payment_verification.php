<?php
/**
 * Payment Verification System
 * Handles verification logic for different payment methods
 */

class PaymentVerification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Submit payment for verification (Member action)
     */
    public function submitPaymentForVerification($transaction_id, $verification_method, $verification_reference, $notes = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Validate verification method and reference
            $validation_result = $this->validateVerificationData($verification_method, $verification_reference);
            if (!$validation_result['valid']) {
                throw new Exception($validation_result['message']);
            }
            
            // Update transaction with verification data
            $stmt = $this->pdo->prepare("
                UPDATE transactions 
                SET verification_status = 'pending',
                    verification_method = ?,
                    verification_reference = ?,
                    verification_notes = ?,
                    member_submitted_at = NOW(),
                    member_submitted_by = ?
                WHERE transaction_id = ? AND verification_status = 'pending'
            ");
            
            $stmt->execute([
                $verification_method,
                $verification_reference,
                $notes,
                $_SESSION['user_id'],
                $transaction_id
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Transaction not found or already processed.');
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Payment submitted for verification successfully.'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Verify payment (Finance Officer/CFO action)
     */
    public function verifyPayment($transaction_id, $verification_status, $verification_notes = '', $rejection_reason = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Get transaction details for verification
            $stmt = $this->pdo->prepare("
                SELECT t.*, p.member_id, m.group_id, m.user_id as member_user_id
                FROM transactions t
                JOIN pledges p ON t.pledge_id = p.pledge_id
                JOIN members m ON p.member_id = m.member_id
                WHERE t.transaction_id = ?
            ");
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception('Transaction not found.');
            }
            
            // Check if user has permission to verify this payment
            if (!$this->canVerifyPayment($_SESSION['user_id'], $transaction['group_id'])) {
                throw new Exception('You do not have permission to verify this payment.');
            }
            
            // Update verification status
            $stmt = $this->pdo->prepare("
                UPDATE transactions 
                SET verification_status = ?,
                    verification_notes = ?,
                    verified_by = ?,
                    verified_at = NOW(),
                    rejection_reason = ?
                WHERE transaction_id = ?
            ");
            
            $stmt->execute([
                $verification_status,
                $verification_notes,
                $_SESSION['user_id'],
                $rejection_reason,
                $transaction_id
            ]);
            
            // If verified, update pledge status if fully paid
            if ($verification_status === 'verified') {
                $this->updatePledgeStatusIfFullyPaid($transaction['pledge_id']);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Payment verification updated successfully.'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Validate verification data based on payment method
     */
    private function validateVerificationData($verification_method, $verification_reference) {
        switch ($verification_method) {
            case 'mpesa_code':
                if (empty($verification_reference)) {
                    return ['valid' => false, 'message' => 'M-Pesa transaction code is required.'];
                }
                if (!preg_match('/^[A-Z0-9]{10}$/', $verification_reference)) {
                    return ['valid' => false, 'message' => 'M-Pesa code must be exactly 10 characters (letters and numbers only).'];
                }
                break;
                
            case 'bank_reference':
                if (empty($verification_reference)) {
                    return ['valid' => false, 'message' => 'Bank transfer reference is required.'];
                }
                if (strlen($verification_reference) < 5) {
                    return ['valid' => false, 'message' => 'Bank reference must be at least 5 characters.'];
                }
                break;
                
            case 'cheque_number':
                if (empty($verification_reference)) {
                    return ['valid' => false, 'message' => 'Cheque number is required.'];
                }
                if (!preg_match('/^[0-9]{6,10}$/', $verification_reference)) {
                    return ['valid' => false, 'message' => 'Cheque number must be 6-10 digits.'];
                }
                break;
                
            case 'cash_receipt':
                if (empty($verification_reference)) {
                    return ['valid' => false, 'message' => 'Receipt number is required for cash payments.'];
                }
                if (strlen($verification_reference) < 3) {
                    return ['valid' => false, 'message' => 'Receipt number must be at least 3 characters long.'];
                }
                break;
                
            case 'online_reference':
                if (empty($verification_reference)) {
                    return ['valid' => false, 'message' => 'Online payment reference is required.'];
                }
                break;
                
            default:
                return ['valid' => false, 'message' => 'Invalid verification method.'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check if user can verify payments for a specific group
     */
    private function canVerifyPayment($user_id, $group_id) {
        // Get user role
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_role = $stmt->fetchColumn();
        
        // CFO can verify all payments
        if ($user_role === 'chief_finance_officer') {
            return true;
        }
        
        // Finance officers can verify payments in their groups
        if ($user_role === 'finance_officer') {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_group_access 
                WHERE user_id = ? AND group_id = ? AND access_level = 'finance_officer'
            ");
            $stmt->execute([$user_id, $group_id]);
            return $stmt->fetchColumn() > 0;
        }
        
        return false;
    }
    
    /**
     * Update pledge status if fully paid
     */
    private function updatePledgeStatusIfFullyPaid($pledge_id) {
        // Get total paid amount (only verified payments)
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount_paid), 0) as total_paid 
            FROM transactions 
            WHERE pledge_id = ? AND verification_status = 'verified'
        ");
        $stmt->execute([$pledge_id]);
        $total_paid = $stmt->fetchColumn();
        
        // Get pledge amount
        $stmt = $this->pdo->prepare("SELECT amount FROM pledges WHERE pledge_id = ?");
        $stmt->execute([$pledge_id]);
        $pledge_amount = $stmt->fetchColumn();
        
        // Update status if fully paid
        if ($total_paid >= $pledge_amount) {
            $stmt = $this->pdo->prepare("UPDATE pledges SET status = 'completed' WHERE pledge_id = ?");
            $stmt->execute([$pledge_id]);
        }
    }
    
    /**
     * Get payments pending verification for a user's accessible groups
     */
    public function getPendingVerifications($user_id, $limit = 50, $offset = 0) {
        $user_role = $this->getUserRole($user_id);
        
        if ($user_role === 'chief_finance_officer') {
            // CFO can see all pending verifications
            $sql = "
                SELECT * FROM payment_verification_queue 
                ORDER BY member_submitted_at ASC, created_at ASC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
        } else {
            // Finance officers can see only their group's pending verifications
            $sql = "
                SELECT pvq.* FROM payment_verification_queue pvq
                JOIN user_group_access uga ON pvq.group_id = uga.group_id
                WHERE uga.user_id = ? AND uga.access_level = 'finance_officer'
                ORDER BY pvq.member_submitted_at ASC, pvq.created_at ASC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id, $limit, $offset]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user role
     */
    public function getUserRole($user_id) {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
}
?>