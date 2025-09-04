<?php
/**
 * Payment Verification Actions API Endpoint
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment_verification.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

header('Content-Type: application/json');

$verification = new PaymentVerification($pdo);

try {
    switch ($action) {
        case 'submit_payment_verification':
            // Member submits payment for verification
            $transaction_id = (int)($input['transaction_id'] ?? 0);
            $verification_method = sanitize($input['verification_method'] ?? '');
            $verification_reference = sanitize($input['verification_reference'] ?? '');
            $notes = sanitize($input['notes'] ?? '');
            
            if (!$transaction_id || !$verification_method || !$verification_reference) {
                throw new Exception('All required fields must be provided.');
            }
            
            $result = $verification->submitPaymentForVerification(
                $transaction_id, 
                $verification_method, 
                $verification_reference, 
                $notes
            );
            
            echo json_encode($result);
            break;
            
        case 'verify_payment':
            // Finance Officer/CFO verifies payment
            $transaction_id = (int)($input['transaction_id'] ?? 0);
            $verification_status = sanitize($input['verification_status'] ?? '');
            $verification_notes = sanitize($input['verification_notes'] ?? '');
            $rejection_reason = sanitize($input['rejection_reason'] ?? '');
            
            if (!$transaction_id || !$verification_status) {
                throw new Exception('Transaction ID and verification status are required.');
            }
            
            if (!in_array($verification_status, ['verified', 'rejected'])) {
                throw new Exception('Invalid verification status.');
            }
            
            $result = $verification->verifyPayment(
                $transaction_id, 
                $verification_status, 
                $verification_notes, 
                $rejection_reason
            );
            
            echo json_encode($result);
            break;
            
        case 'get_pending_verifications':
            // Get payments pending verification
            $page = (int)($input['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $pending_verifications = $verification->getPendingVerifications($_SESSION['user_id'], $limit, $offset);
            
            // Get total count for pagination
            $user_role = $verification->getUserRole($_SESSION['user_id']);
            if ($user_role === 'chief_finance_officer') {
                $count_sql = "SELECT COUNT(*) FROM payment_verification_queue";
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute();
            } else {
                $count_sql = "
                    SELECT COUNT(*) FROM payment_verification_queue pvq
                    JOIN user_group_access uga ON pvq.group_id = uga.group_id
                    WHERE uga.user_id = ? AND uga.access_level = 'finance_officer'
                ";
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute([$_SESSION['user_id']]);
            }
            $total_count = $count_stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'verifications' => $pending_verifications,
                'total_count' => $total_count,
                'current_page' => $page,
                'total_pages' => ceil($total_count / $limit)
            ]);
            break;
            
        case 'get_verification_methods':
            // Get available verification methods based on payment method
            $payment_method = sanitize($input['payment_method'] ?? '');
            
            $methods = [];
            switch ($payment_method) {
                case 'mobile_money':
                    $methods = [
                        ['value' => 'mpesa_code', 'label' => 'M-Pesa Transaction Code', 'placeholder' => 'e.g., QGH2X8K9L1', 'pattern' => '[A-Z0-9]{10}']
                    ];
                    break;
                case 'bank_transfer':
                    $methods = [
                        ['value' => 'bank_reference', 'label' => 'Bank Transfer Reference', 'placeholder' => 'e.g., TXN123456789', 'pattern' => '.{5,}']
                    ];
                    break;
                case 'check':
                    $methods = [
                        ['value' => 'cheque_number', 'label' => 'Cheque Number', 'placeholder' => 'e.g., 123456', 'pattern' => '[0-9]{6,10}']
                    ];
                    break;
                case 'cash':
                    $methods = [
                        ['value' => 'cash_receipt', 'label' => 'Church Receipt Number', 'placeholder' => 'e.g., RCP001234 or 2024-001', 'pattern' => '.{3,}']
                    ];
                    break;
                case 'online':
                    $methods = [
                        ['value' => 'online_reference', 'label' => 'Online Payment Reference', 'placeholder' => 'e.g., PAY123456789', 'pattern' => '.{5,}']
                    ];
                    break;
                default:
                    $methods = [
                        ['value' => 'mpesa_code', 'label' => 'M-Pesa Transaction Code', 'placeholder' => 'e.g., QGH2X8K9L1', 'pattern' => '[A-Z0-9]{10}'],
                        ['value' => 'bank_reference', 'label' => 'Bank Transfer Reference', 'placeholder' => 'e.g., TXN123456789', 'pattern' => '.{5,}'],
                        ['value' => 'cheque_number', 'label' => 'Cheque Number', 'placeholder' => 'e.g., 123456', 'pattern' => '[0-9]{6,10}'],
                        ['value' => 'cash_receipt', 'label' => 'Receipt Number', 'placeholder' => 'e.g., RCP001234', 'pattern' => '.{3,}'],
                        ['value' => 'online_reference', 'label' => 'Online Payment Reference', 'placeholder' => 'e.g., PAY123456789', 'pattern' => '.{5,}']
                    ];
            }
            
            echo json_encode(['success' => true, 'methods' => $methods]);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>