<?php
/**
 * Finance Officer Actions API Endpoint
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rbac.php';

// Check if user is logged in and has FO role
if (!isLoggedIn() || !hasRole('finance_officer')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$rbac = new RBAC($pdo, $user_id);
$groups = $rbac->getAccessibleGroups('finance_officer');
$group_id = !empty($groups) ? $groups[0]['group_id'] : null;

if (!$group_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not assigned to any group as a Finance Officer.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'create_pledge':
            $member_id = intval($input['member_id'] ?? 0);
            $category_id = intval($input['category_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $schedule = trim($input['payment_schedule'] ?? 'one_time');
            $notes = trim($input['notes'] ?? '');

            // Check if member belongs to the FO's group
            if (!$rbac->isMemberOfGroup($member_id, $group_id)) {
                throw new Exception('You can only create pledges for members of your own group.');
            }

            if (!$member_id || !$category_id || $amount <= 0) {
                throw new Exception('Member, category, and a valid amount are required');
            }

            $stmt = $pdo->prepare("INSERT INTO pledges (member_id, group_id, category_id, amount, payment_schedule, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $result = $stmt->execute([$member_id, $group_id, $category_id, $amount, $schedule, $notes]);

            if ($result) {
                logAction('create_pledge', 'pledges', $pdo->lastInsertId(), ['member_id' => $member_id, 'amount' => $amount]);
                echo json_encode(['success' => true, 'message' => 'Pledge created successfully.']);
            } else {
                throw new Exception('Failed to create pledge.');
            }
            break;

        case 'update_pledge':
            $pledge_id = intval($input['pledge_id'] ?? 0);
            $amount = floatval($input['amount'] ?? 0);
            $schedule = trim($input['payment_schedule'] ?? '');
            $notes = trim($input['notes'] ?? '');

            if (!$pledge_id || $amount <= 0) {
                throw new Exception('Pledge ID and a valid amount are required.');
            }

            // Check if pledge belongs to the FO's group
            if (!$rbac->canAccessPledge($pledge_id)) {
                throw new Exception('You do not have permission to update this pledge.');
            }

            $stmt = $pdo->prepare("UPDATE pledges SET amount = ?, payment_schedule = ?, notes = ? WHERE pledge_id = ?");
            $result = $stmt->execute([$amount, $schedule, $notes, $pledge_id]);

            if ($result) {
                logAction('update_pledge', 'pledges', $pledge_id, ['amount' => $amount, 'schedule' => $schedule]);
                echo json_encode(['success' => true, 'message' => 'Pledge updated successfully.']);
            } else {
                throw new Exception('Failed to update pledge.');
            }
            break;

        case 'delete_pledge':
            $pledge_id = intval($input['pledge_id'] ?? 0);

            // Check if pledge belongs to the FO's group
            if (!$rbac->canAccessPledge($pledge_id)) {
                throw new Exception('You do not have permission to delete this pledge.');
            }

            $stmt = $pdo->prepare("DELETE FROM pledges WHERE pledge_id = ?");
            $result = $stmt->execute([$pledge_id]);

            if ($result) {
                logAction('delete_pledge', 'pledges', $pledge_id);
                echo json_encode(['success' => true, 'message' => 'Pledge deleted successfully.']);
            } else {
                throw new Exception('Failed to delete pledge.');
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