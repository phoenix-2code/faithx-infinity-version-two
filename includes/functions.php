<?php
// Common functions for FaithX Infinity

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Format currency
function formatCurrency($amount) {
    if ($amount === null) {
        return 'KSh 0.00'; // Or any other default representation for null
    }
    return 'KSh ' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Get pledge status badge
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'active' => '<span class="badge bg-primary">Active</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'delayed' => '<span class="badge bg-danger">Delayed</span>',
        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>',
        'overdue' => '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> Overdue</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light">Unknown</span>';
}

// Get overdue indicator
function getOverdueIndicator($pledge) {
    if (isset($pledge['due_date']) && $pledge['due_date'] && strtotime($pledge['due_date']) < time() && $pledge['status'] !== 'completed') {
        return 'table-danger'; // Bootstrap class for row highlighting
    }
    return '';
}

// Calculate pledge progress
function calculateProgress($total_amount, $paid_amount) {
    if ($total_amount <= 0) return 0;
    return min(100, ($paid_amount / $total_amount) * 100);
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// --- Group-based Access Control ---
class AccessControl {
    public static function hasGroupAccess($pdo, $user_id, $group_id, $required_level) {
        $stmt = $pdo->prepare("SELECT access_level FROM user_group_access WHERE user_id = ? AND group_id = ?");
        $stmt->execute([$user_id, $group_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $levels = ['member' => 1, 'finance_officer' => 2, 'elder_admin' => 3];
        return $levels[$row['access_level']] >= $levels[$required_level];
    }
    public static function getUserGroups($pdo, $user_id, $access_level = null) {
        $sql = "SELECT g.* FROM groups g JOIN user_group_access uga ON g.group_id = uga.group_id WHERE uga.user_id = ?";
        $params = [$user_id];
        if ($access_level) {
            $sql .= " AND uga.access_level = ?";
            $params[] = $access_level;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function isSystemAdmin($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['role'] === 'elder_admin';
    }
    public static function getAccessiblePledges($pdo, $user_id, $filters = []) {
        $groups = self::getUserGroups($pdo, $user_id);
        $group_ids = array_column($groups, 'group_id');
        if (empty($group_ids)) return [];
        $where = ["p.group_id IN (" . implode(",", array_fill(0, count($group_ids), '?')) . ")"];
        $params = $group_ids;
        // Add filters
        if (!empty($filters['status'])) {
            $where[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        $sql = "SELECT p.*, c.category_name FROM pledges p LEFT JOIN pledge_categories c ON p.category_id = c.category_id WHERE " . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Pledge Category Management ---
class PledgeCategoryManager {
    public static function createCategory($pdo, $user_id, $group_id, $category_name, $description) {
        $stmt = $pdo->prepare("INSERT INTO pledge_categories (group_id, category_name, description, created_by) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$group_id, $category_name, $description, $user_id]);
    }
    public static function updateCategory($pdo, $user_id, $category_id, $category_name, $description) {
        $stmt = $pdo->prepare("UPDATE pledge_categories SET category_name = ?, description = ? WHERE category_id = ?");
        return $stmt->execute([$category_name, $description, $category_id]);
    }
    public static function deleteCategory($pdo, $user_id, $category_id) {
        // Only allow delete if no active pledges
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pledges WHERE category_id = ? AND status = 'active'");
        $stmt->execute([$category_id]);
        if ($stmt->fetchColumn() > 0) return false;
        $stmt = $pdo->prepare("DELETE FROM pledge_categories WHERE category_id = ?");
        return $stmt->execute([$category_id]);
    }
    public static function getGroupCategories($pdo, $user_id, $group_id) {
        // Only show categories for groups the user has access to
        if (!AccessControl::hasGroupAccess($pdo, $user_id, $group_id, 'finance_officer')) return [];
        $stmt = $pdo->prepare("SELECT * FROM pledge_categories WHERE group_id = ?");
        $stmt->execute([$group_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Overdue Management ---
class OverdueManager {
    public static function markPledgesOverdue($pdo) {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("UPDATE pledges SET status = 'overdue' WHERE due_date < ? AND status != 'completed' AND status != 'overdue'");
        $stmt->execute([$today]);
    }
    public static function getOverduePledges($pdo, $user_id = null) {
        $sql = "SELECT * FROM pledges WHERE status = 'overdue'";
        $params = [];
        if ($user_id) {
            $sql .= " AND member_id = ?";
            $params[] = $user_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function processOverdueReminders($pdo) {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM pledges WHERE status = 'overdue' AND (reminder_sent = 0 OR (last_reminder_date IS NULL OR last_reminder_date < ?))");
        $stmt->execute([$today]);
        $pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pledges as $pledge) {
            self::sendOverdueReminder($pdo, $pledge);
        }
    }
    public static function sendOverdueReminder($pdo, $pledge) {
        // Get user email
        $stmt = $pdo->prepare("SELECT u.email FROM users u JOIN members m ON u.user_id = m.user_id WHERE m.member_id = ?");
        $stmt->execute([$pledge['member_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;
        $email = $user['email'];
        $subject = "Pledge Overdue Reminder";
        $message = "Dear member, your pledge (ID: {$pledge['pledge_id']}) is overdue. Please take action.";
        if (EmailService::send($email, $subject, $message)) {
            // Log notification
            self::logEmailNotification($pdo, $pledge['pledge_id'], $pledge['member_id'], $email, $subject, $message);
            // Update pledge
            $stmt = $pdo->prepare("UPDATE pledges SET reminder_sent = 1, last_reminder_date = ?, reminder_count = reminder_count + 1 WHERE pledge_id = ?");
            $stmt->execute([date('Y-m-d'), $pledge['pledge_id']]);
            return true;
        }
        return false;
    }
    public static function logEmailNotification($pdo, $pledge_id, $user_id, $email, $subject, $message) {
        $stmt = $pdo->prepare("INSERT INTO email_notifications (pledge_id, user_id, email, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$pledge_id, $user_id, $email, $subject, $message]);
    }
}

// --- Email Service ---
class EmailService {
    public static function send($to, $subject, $body) {
        // Use PHPMailer or mail() as fallback
        // This is a placeholder; configure PHPMailer as needed
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            // SMTP config here
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'user@example.com';
            $mail->Password = 'password';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->setFrom('noreply@example.com', 'FaithX Infinity');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            return $mail->send();
        } else {
            // Fallback to PHP mail()
            return mail($to, $subject, $body);
        }
    }
}

function logAction($action_type, $resource_type, $resource_id = null, $details = []) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action_type, resource_type, resource_id, new_values, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $action_type,
        $resource_type,
        $resource_id,
        json_encode($details),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Get verification status badge
function getVerificationStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning"><i class="bi bi-hourglass-split"></i> Pending</span>',
        'verified' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span>',
        'rejected' => '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>',
        'disputed' => '<span class="badge bg-warning"><i class="bi bi-exclamation-triangle"></i> Disputed</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light">Unknown</span>';
}

// Get payment method display name
function getPaymentMethodDisplay($method) {
    $methods = [
        'mobile_money' => 'M-Pesa',
        'bank_transfer' => 'Bank Transfer',
        'check' => 'Cheque',
        'cash' => 'Cash',
        'online' => 'Online Payment'
    ];
    
    return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

// Get verification method display name
function getVerificationMethodDisplay($method) {
    $methods = [
        'mpesa_code' => 'M-Pesa Code',
        'bank_reference' => 'Bank Reference',
        'cheque_number' => 'Cheque Number',
        'cash_receipt' => 'Receipt Number',
        'online_reference' => 'Online Reference'
    ];
    
    return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

?>