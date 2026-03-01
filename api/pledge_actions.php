<?php
/**
 * Pledge Actions API Endpoint
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
        case 'create_pledge':
            $category_id = (int)($input['category_id'] ?? 0);
            $amount = (float)($input['amount'] ?? 0);
            $target_date = $input['target_date'] ?? null;
            $due_date = $input['due_date'] ?? null;
            $payment_schedule = $input['payment_schedule'] ?? 'one_time';
            $notes = sanitize($input['notes'] ?? '');

            if (!$category_id || $amount <= 0) {
                throw new Exception('Please fill in all required fields with valid values.');
            }

            // Get member_id for current user
            $stmt = $pdo->prepare("SELECT member_id, group_id FROM members WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                throw new Exception('Member profile not found.');
            }

            // Use provided due_date or calculate based on payment schedule
            if (empty($due_date)) {
                $due_date = date('Y-m-d'); // Default to pledge_date (current date)
                if ($payment_schedule !== 'one_time') {
                    switch ($payment_schedule) {
                        case 'weekly':
                            $due_date = date('Y-m-d', strtotime('+1 week'));
                            break;
                        case 'monthly':
                            $due_date = date('Y-m-d', strtotime('+1 month'));
                            break;
                        case 'quarterly':
                            $due_date = date('Y-m-d', strtotime('+3 months'));
                            break;
                        case 'annually':
                            $due_date = date('Y-m-d', strtotime('+1 year'));
                            break;
                    }
                }
            }

            // Create the pledge
            $stmt = $pdo->prepare("
                INSERT INTO pledges (member_id, group_id, category_id, amount, pledge_date, due_date, target_date, payment_schedule, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt->execute([$member['member_id'], $member['group_id'], $category_id, $amount, date('Y-m-d'), $due_date, $target_date, $payment_schedule, $notes, 'active', $_SESSION['user_id']])) {
                logAction('create_pledge', 'pledges', $pdo->lastInsertId(), ['amount' => $amount]);
                echo json_encode(['success' => true, 'message' => 'Pledge created successfully!']);
            } else {
                throw new Exception('Failed to create pledge.');
            }
            break;

        case 'update_pledge_schedule':
            $pledge_id = (int)($input['pledge_id'] ?? 0);
            $new_schedule = $input['new_payment_schedule'] ?? '';
            $modification_reason = sanitize($input['modification_reason'] ?? '');
            
            if (!$pledge_id || !$new_schedule || !$modification_reason) {
                throw new Exception('All fields are required for pledge modification.');
            }
            
            // Check if user owns this pledge
            $stmt = $pdo->prepare("
                SELECT p.pledge_id FROM pledges p 
                JOIN members m ON p.member_id = m.member_id 
                WHERE p.pledge_id = ? AND m.user_id = ?
            ");
            $stmt->execute([$pledge_id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('You do not have permission to modify this pledge.');
            }
            
            // Update pledge schedule
            $stmt = $pdo->prepare("
                UPDATE pledges 
                SET payment_schedule = ?, 
                    notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] Schedule changed to ', ?, ': ', ?)
                WHERE pledge_id = ?
            ");
            if ($stmt->execute([$new_schedule, $new_schedule, $modification_reason, $pledge_id])) {
                logAction('update_pledge_schedule', 'pledges', $pledge_id, ['new_schedule' => $new_schedule]);
                echo json_encode(['success' => true, 'message' => 'Payment schedule updated successfully.']);
            } else {
                throw new Exception('Failed to update pledge schedule.');
            }
            break;
            
        case 'delete_pledge':
            $pledge_id = (int)($input['pledge_id'] ?? 0);

            if (!$pledge_id) {
                throw new Exception('Pledge ID is required for deletion.');
            }

            // Authorization: Only allow system_admin to delete pledges.
            if (!hasRole('system_admin')) {
                throw new Exception('You are not authorized to delete this pledge.');
            }

            // First, delete related records in pledge_payments
            $stmt = $pdo->prepare("DELETE FROM pledge_payments WHERE pledge_id = ?");
            $stmt->execute([$pledge_id]);

            // Then, delete the pledge itself
            $stmt = $pdo->prepare("DELETE FROM pledges WHERE pledge_id = ?");
            $result = $stmt->execute([$pledge_id]);

            if ($result) {
                logAction('delete_pledge', 'pledges', $pledge_id, ['pledge_id' => $pledge_id]);
                echo json_encode(['success' => true, 'message' => 'Pledge deleted successfully.']);
            } else {
                throw new Exception('Failed to delete pledge.');
            }
            break;        default:            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    $user_message = 'An unexpected error occurred. Please try again. If the problem persists, contact support.';

    // Check if it's a PDOException (database error)
    if ($e instanceof PDOException) {
        // Log the detailed error for developers
        error_log("PDOException in pledge_actions.php: " . $e->getMessage() . " - Code: " . $e->getCode());

        // Provide a more specific user-friendly message for input-related database errors
        $user_message = 'We encountered an issue processing your pledge due to invalid or missing information. Please review your input and try again. If the problem persists, contact support.';
    } else {
        // For other types of exceptions, log and potentially show a more generic message
        error_log("Exception in pledge_actions.php: " . $e->getMessage() . " - File: " . $e->getFile() . " - Line: " . $e->getLine());
    }

    echo json_encode(['success' => false, 'message' => $user_message]);
}


?>
