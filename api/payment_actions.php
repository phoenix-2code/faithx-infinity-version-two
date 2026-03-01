<?php
/**
 * Payment Actions API Endpoint
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$csrf_token = $input['csrf_token'] ?? '';

if (!verifyCSRFToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh the page.']);
    exit;
}

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'record_payment':
            $pledge_id = (int)($input['pledge_id'] ?? 0);
            $amount_paid = (float)($input['amount_paid'] ?? 0);
            $payment_method = sanitize($input['payment_method'] ?? '');
            $payment_date = $input['payment_date'] ?? date('Y-m-d');
            $reference_number = sanitize($input['reference_number'] ?? '');
            $notes = sanitize($input['notes'] ?? '');

            if (!$pledge_id || $amount_paid <= 0 || !$payment_method) {
                throw new Exception('Please fill in all required fields with valid values.');
            }

            $stmt = $pdo->prepare("
                SELECT p.amount,
                COALESCE(SUM(t.amount_paid), 0) as already_paid
                FROM pledges p
                LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
                WHERE p.pledge_id = ?
                GROUP BY p.pledge_id
            ");
            $stmt->execute([$pledge_id]);
            $pledgeData = $stmt->fetch();

            if (!$pledgeData) throw new Exception('Pledge not found.');

            $remaining = $pledgeData['amount'] - $pledgeData['already_paid'];

            if ($amount_paid > $remaining) {
                throw new Exception('Payment exceeds remaining balance of KSh ' . number_format($remaining, 2));
            }

            // Create the transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (pledge_id, amount_paid, payment_method, payment_date, reference_number, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt->execute([$pledge_id, $amount_paid, $payment_method, $payment_date, $reference_number, $notes, $_SESSION['user_id']])) {
                // Determine if fully paid using our previously fetched data
                $new_total_paid = $pledgeData['already_paid'] + $amount_paid;

                if ($new_total_paid >= $pledgeData['amount']) {
                    $stmt = $pdo->prepare("UPDATE pledges SET status = 'completed' WHERE pledge_id = ?");
                    $stmt->execute([$pledge_id]);
                }

                logAction('record_payment', 'transactions', $pdo->lastInsertId(), ['pledge_id' => $pledge_id, 'amount_paid' => $amount_paid]);
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
            } else {
                throw new Exception('Failed to record payment.');
            }
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


?>
