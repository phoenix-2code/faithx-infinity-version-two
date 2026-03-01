<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_error.log');

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'register':
            $username = sanitize($input['username'] ?? '');
            $first_name = sanitize($input['first_name'] ?? '');
            $last_name = sanitize($input['last_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = sanitize($input['phone'] ?? '');
            $parish = sanitize($input['parish'] ?? '');
            $password = $input['password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';
            $group_id = (int)($input['group_id'] ?? 0);

            // Validation
            if (!$username || !$first_name || !$last_name || !$phone || !$parish || !$password) {
                throw new Exception('All required fields must be filled.');
            }

            if ($email && !validateEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }

            if ($password !== $confirm_password) {
                throw new Exception('Passwords do not match.');
            }

            // Password strength validation
            if (strlen($password) < 8 || 
                !preg_match('/[A-Z]/', $password) || 
                !preg_match('/[a-z]/', $password) || 
                !preg_match('/[0-9]/', $password)) {
                throw new Exception('Password must be at least 8 characters with uppercase, lowercase, and numbers.');
            }

            // Check for existing username or email
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR (email = ? AND email != '')");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Username or email already exists.');
            }

            // Check for duplicate phone
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Phone number already registered.');
            }

            // Begin transaction
            try {
                $pdo->beginTransaction();

            // Insert into users table (default role: member)
            $stmt = $pdo->prepare("
                INSERT INTO users (username, first_name, last_name, email, phone, parish, password, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'member')
            ");
            $stmt->execute([
                $username,
                $first_name,
                $last_name,
                $email ?: null,
                $phone,
                $parish,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $user_id = $pdo->lastInsertId();

            // Insert into members table
            $stmt = $pdo->prepare("
                INSERT INTO members (user_id, first_name, last_name, phone, group_id, join_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $first_name,
                $last_name,
                $phone,
                $group_id ?: null,
                date('Y-m-d')
            ]);

            // Insert into user_group_access table if a group was selected
            if ($group_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_group_access (user_id, group_id, access_level) 
                    VALUES (?, ?, 'member')
                ");
                $stmt->execute([$user_id, $group_id]);
            }

            // Log the registration
            logAction('user_registration', 'users', $user_id, ['username' => $username]);

            // Commit transaction
            $pdo->commit();

            // Notify admins of new registration
            notifyAdminsNewRegistration($pdo, $user_id, $username, $first_name . ' ' . $last_name);

            echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in with your credentials.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        break;

        case 'update_profile':
            if (!isLoggedIn()) {
                throw new Exception('User not logged in.');
            }
            $user_id = $_SESSION['user_id'];

            $first_name = sanitize($input['first_name'] ?? '');
            $last_name = sanitize($input['last_name'] ?? '');
            $phone = sanitize($input['phone'] ?? '');
            $email = sanitize($input['email'] ?? '');
            $parish = sanitize($input['parish'] ?? '');
            
            if (empty($first_name) || empty($last_name) || empty($email)) {
                throw new Exception('Please fill in all required fields.');
            } elseif (!validateEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }

            try {
                $pdo->beginTransaction();
                // Update user email and parish
                $stmt = $pdo->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, parish = ? WHERE user_id = ?");
                $stmt->execute([$email, $first_name, $last_name, $parish, $user_id]);
                
                // Update member profile
                $stmt = $pdo->prepare("
                    UPDATE members 
                    SET first_name = ?, last_name = ?, phone = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $user_id]);
                
                logAction('update_profile', 'users', $user_id, ['first_name' => $first_name, 'last_name' => $last_name]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw new Exception('Error updating profile: ' . $e->getMessage());
            }
            break;

        case 'change_password':
            if (!isLoggedIn()) {
                throw new Exception('User not logged in.');
            }
            $user_id = $_SESSION['user_id'];

            $current_password = $input['current_password'] ?? '';
            $new_password = $input['new_password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('Please fill in all password fields.');
            } elseif ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }

            try {
                $auth = new AuthSystem($pdo);
                $result = $auth->changePassword($user_id, $current_password, $new_password);

                if ($result['success']) {
                    echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
                } else {
                    echo json_encode($result);
                }
            } catch (Exception $e) {
                throw new Exception('Error changing password: ' . $e->getMessage());
            }
            break;

        case 'login':
            require_once __DIR__ . '/../includes/auth.php';
            try {
                $auth = new AuthSystem($pdo);

                $username = trim($input['username'] ?? '');
                $password = $input['password'] ?? '';

                if (!$username || !$password) {
                    throw new Exception('Both username and password are required.');
                }

                $result = $auth->login($username, $password);

                if ($result['success']) {
                    $role = $result['effective_role'];
                    $redirect_url = 'index.php?page=dashboard';
                    if ($role === 'chief_finance_officer') {
                        $redirect_url = 'index.php?page=cfo_dashboard';
                    } elseif ($role === 'system_admin') {
                        $redirect_url = 'index.php?page=system_admin_dashboard';
                    }
                    echo json_encode(['success' => true, 'message' => 'Login successful!', 'redirect' => $redirect_url]);
                } else {
                    echo json_encode($result);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Login error: ' . $e->getMessage()]);
            }
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


?>
