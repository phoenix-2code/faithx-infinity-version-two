<?php
/**
 * System Admin Actions API Endpoint
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/error_handler.php';

// Check if user is logged in and has System Admin role
if (!isLoggedIn() || $_SESSION['role'] !== 'system_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

header('Content-Type: application/json');

try {
    // Initialize ErrorHandler with PDO connection
    ErrorHandler::init($pdo, DEBUG_MODE);

    switch ($action) {
        case 'create_user':
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $first_name = trim($input['first_name'] ?? '');
            $last_name = trim($input['last_name'] ?? '');
            $role = trim($input['role'] ?? '');
            $password = $input['password'] ?? '';

            if (!$username || !$email || !$first_name || !$last_name || !$role || !$password) {
                throw new Exception('All fields are required to create a user.');
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, role, password) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$username, $email, $first_name, $last_name, $role, $hashed_password]);

            if ($result) {
                logAction('create_user', 'users', $pdo->lastInsertId(), ['username' => $username, 'role' => $role]);
                echo json_encode(['success' => true, 'message' => 'User created successfully.']);
            } else {
                throw new Exception('Failed to create user.');
            }
            break;

        case 'create_group':
            $group_name = trim($input['group_name'] ?? '');
            $description = trim($input['description'] ?? '');
            $leader_name = trim($input['leader_name'] ?? '');

            if (!$group_name) {
                throw new Exception('Group name is required.');
            }

            $stmt = $pdo->prepare("INSERT INTO groups (group_name, description, leader_name) VALUES (?, ?, ?)");
            $result = $stmt->execute([$group_name, $description, $leader_name]);

            if ($result) {
                logAction('create_group', 'groups', $pdo->lastInsertId(), ['group_name' => $group_name]);
                echo json_encode(['success' => true, 'message' => 'Group created successfully.']);
            } else {
                throw new Exception('Failed to create group.');
            }
            break;

        case 'update_system_settings':
            $system_name = trim($input['system_name'] ?? '');
            $maintenance_mode = intval($input['maintenance_mode'] ?? 0);
            $backup_frequency = trim($input['backup_frequency'] ?? '');

            $settings = [
                'system_name' => $system_name,
                'maintenance_mode' => $maintenance_mode,
                'backup_frequency' => $backup_frequency
            ];

            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }

            logAction('update_system_settings', 'system_settings', null, $settings);
            echo json_encode(['success' => true, 'message' => 'System settings updated successfully.']);
            break;

        case 'activate_break_glass':
            $target_user_id = intval($input['target_user_id'] ?? 0);
            $duration_hours = intval($input['duration_hours'] ?? 0);
            $emergency_reason = trim($input['emergency_reason'] ?? '');

            if (!$target_user_id || !$duration_hours || !$emergency_reason) {
                throw new Exception('All fields are required for break glass activation.');
            }

            // This is a simplified implementation. In a real-world scenario, this would
            // grant the user temporary elevated privileges.
            logAction('activate_break_glass', 'break_glass_access', null, [
                'target_user_id' => $target_user_id,
                'duration_hours' => $duration_hours,
                'reason' => $emergency_reason
            ]);

            echo json_encode(['success' => true, 'message' => 'Break glass protocol activated successfully.']);
            break;

        case 'clear_error_log':
            if (ErrorHandler::clearErrorLog()) {
                logAction('clear_error_log', 'system', null, []);
                echo json_encode(['success' => true, 'message' => 'Error log cleared successfully.']);
            } else {
                throw new Exception('Failed to clear error log.');
            }
            break;

        case 'update_error_log':
            $log_id = intval($input['log_id'] ?? 0);
            $update_data = [];
            if (isset($input['status'])) {
                $update_data['status'] = $input['status'];
            }
            if (isset($input['solution'])) {
                $update_data['solution'] = $input['solution'];
            }

            if (!$log_id || empty($update_data)) {
                throw new Exception('Invalid parameters for updating error log.');
            }

            if (ErrorHandler::updateErrorLog($log_id, $update_data)) {
                logAction('update_error_log', 'error_logs', $log_id, $update_data);
                echo json_encode(['success' => true, 'message' => 'Error log updated successfully.']);
            } else {
                throw new Exception('Failed to update error log.');
            }
            break;

        case 'reset_user_password':
            $user_id = intval($input['user_id'] ?? 0);
            $new_password = $input['new_password'] ?? '';

            if (!$user_id || empty($new_password)) {
                throw new Exception('User ID and new password are required.');
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $result = $stmt->execute([$hashed_password, $user_id]);

            if ($result) {
                logAction('reset_user_password', 'users', $user_id, ['user_id' => $user_id]);
                echo json_encode(['success' => true, 'message' => 'User password reset successfully.']);
            } else {
                throw new Exception('Failed to reset user password.');
            }
            break;

        case 'unlock_user_account':
            $user_id = intval($input['user_id'] ?? 0);

            if (!$user_id) {
                throw new Exception('User ID is required.');
            }

            $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = ?");
            $result = $stmt->execute([$user_id]);

            if ($result) {
                logAction('unlock_user_account', 'users', $user_id, ['user_id' => $user_id]);
                echo json_encode(['success' => true, 'message' => 'User account unlocked successfully.']);
            } else {
                throw new Exception('Failed to unlock user account.');
            }
            break;

        case 'update_user_role':
            $user_id = intval($input['user_id'] ?? 0);
            $new_role = trim($input['new_role'] ?? '');

            if (!$user_id || empty($new_role)) {
                throw new Exception('User ID and new role are required.');
            }

            // Validate role against allowed ENUM values if necessary
            $allowed_roles = ['member','group_leader','finance_officer','chief_finance_officer','elder_admin','system_admin'];
            if (!in_array($new_role, $allowed_roles)) {
                throw new Exception('Invalid role specified.');
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $result = $stmt->execute([$new_role, $user_id]);

            if ($result) {
                logAction('update_user_role', 'users', $user_id, ['user_id' => $user_id, 'new_role' => $new_role]);
                echo json_encode(['success' => true, 'message' => 'User role updated successfully.']);
            } else {
                throw new Exception('Failed to update user role.');
            }
            break;

        case 'search_users':
            $search_term = trim($input['search_term'] ?? '');

            if (empty($search_term)) {
                echo json_encode(['success' => true, 'users' => []]); // Return empty if no search term
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT user_id, username, first_name, last_name, role, is_active, failed_login_attempts, account_locked_until
                FROM users
                WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
                LIMIT 10
            ");
            $like_term = '%' . $search_term . '%';
            $stmt->execute([$like_term, $like_term, $like_term]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'filter_error_logs':
            $error_page = (int)($input['error_page'] ?? 1);
            $error_limit = 10;
            $error_offset = ($error_page - 1) * $error_limit;

            $error_filters = [
                'status' => $input['error_status'] ?? '',
                'error_type' => $input['error_type'] ?? '',
                'search' => $input['error_search'] ?? '',
            ];

            $errors = ErrorHandler::getRecentErrors($error_limit, $error_offset, $error_filters);
            $total_errors = ErrorHandler::getTotalErrors($error_filters);
            $total_error_pages = ceil($total_errors / $error_limit);

            echo json_encode([
                'success' => true,
                'errors' => $errors,
                'total_errors' => $total_errors,
                'total_pages' => $total_error_pages,
                'current_page' => $error_page
            ]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


?>
