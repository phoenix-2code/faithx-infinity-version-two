<?php

/**
 * Enhanced Authentication System for FaithX Infinity
 * Implements security features: account lockout, session management, audit logging
 */

class AuthSystem
{
    private $pdo;
    private $max_attempts;
    private $lockout_duration;
    private $session_timeout;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings()
    {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('max_login_attempts', 'lockout_duration', 'session_timeout')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->max_attempts = (int)($settings['max_login_attempts'] ?? 5);
        $this->lockout_duration = (int)($settings['lockout_duration'] ?? 900); // 15 minutes
        $this->session_timeout = (int)($settings['session_timeout'] ?? 1800); // 30 minutes
    }

    /**
     * Authenticate user with enhanced security
     */
    public function login($username, $password)
    {
        // Check if account is locked
        if ($this->isAccountLocked($username)) {
            $this->logSecurityEvent('login_attempt_locked_account', $username);
            return [
                'success' => false,
                'message' => 'Account is temporarily locked. Please try again later.',
                'locked' => true
            ];
        }

        // Get user data
        $stmt = $this->pdo->prepare("
            SELECT user_id, username, password, role, first_name, last_name, is_active, failed_login_attempts
            FROM users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($username);
            $this->logSecurityEvent('login_failed', $username);
            return [
                'success' => false,
                'message' => 'Invalid credentials',
                'locked' => false
            ];
        }

        // Check for temporary roles
        $effective_role = $this->getEffectiveRole($user['user_id'], $user['role']);

        // Successful login
        $this->resetFailedAttempts($username);
        $this->createSession($user, $effective_role);
        $this->logSecurityEvent('login_success', $username, $user['user_id']);

        return [
            'success' => true,
            'user' => $user,
            'effective_role' => $effective_role,
            'message' => 'Login successful'
        ];
    }

    /**
     * Check if account is locked
     */
    private function isAccountLocked($username)
    {
        $stmt = $this->pdo->prepare("
            SELECT failed_login_attempts, account_locked_until 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        // Check if lockout period has expired
        if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
            return true;
        }

        // Check if max attempts reached
        if ($user['failed_login_attempts'] >= $this->max_attempts) {
            $this->lockAccount($username);
            return true;
        }

        return false;
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($username)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_login_attempt = NOW()
            WHERE username = ?
        ");
        $stmt->execute([$username]);

        // Check if we need to lock the account
        $stmt = $this->pdo->prepare("SELECT failed_login_attempts FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $attempts = $stmt->fetchColumn();

        if ($attempts >= $this->max_attempts) {
            $this->lockAccount($username);
        }
    }

    /**
     * Lock user account
     */
    private function lockAccount($username)
    {
        $lockout_until = date('Y-m-d H:i:s', time() + $this->lockout_duration);

        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET account_locked_until = ?
            WHERE username = ?
        ");
        $stmt->execute([$lockout_until, $username]);

        $this->logSecurityEvent('account_locked', $username);
    }

    /**
     * Reset failed attempts after successful login
     */
    private function resetFailedAttempts($username)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0,
                account_locked_until = NULL,
                last_login_attempt = NOW()
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    }

    /**
     * Get effective role (including temporary roles)
     */
    private function getEffectiveRole($user_id, $base_role)
    {
        $stmt = $this->pdo->prepare("
            SELECT temporary_role 
            FROM temporary_role_assignments 
            WHERE user_id = ? AND is_active = 1 AND end_date > NOW()
            ORDER BY assignment_id DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $temp_role = $stmt->fetchColumn();

        return $temp_role ?: $base_role;
    }

    /**
     * Create user session
     */
    private function createSession($user, $effective_role)
    {
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $effective_role;
        $_SESSION['base_role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Set session timeout
        ini_set('session.gc_maxlifetime', $this->session_timeout);
    }

    /**
     * Validate session and check timeout
     */
    public function validateSession()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $this->session_timeout) {
            $this->logout();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        // Check if temporary role has expired
        if (isset($_SESSION['role']) && $_SESSION['role'] !== $_SESSION['base_role']) {
            $current_role = $this->getEffectiveRole($_SESSION['user_id'], $_SESSION['base_role']);
            if ($current_role !== $_SESSION['role']) {
                $_SESSION['role'] = $current_role;
                $this->logSecurityEvent('temporary_role_expired', $_SESSION['username'], $_SESSION['user_id']);
            }
        }

        return true;
    }

    /**
     * Logout user
     */
    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            $this->logSecurityEvent('logout', $_SESSION['username'], $_SESSION['user_id']);
        }

        session_unset();
        session_destroy();

        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }

    /**
     * Change password with validation
     */
    public function changePassword($user_id, $current_password, $new_password)
    {
        // Validate current password
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_hash = $stmt->fetchColumn();

        if (!password_verify($current_password, $current_hash)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }

        // Validate new password strength
        if (!$this->validatePasswordStrength($new_password)) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters with mixed case and numbers'
            ];
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $result = $stmt->execute([$new_hash, $user_id]);

        if ($result) {
            $this->logSecurityEvent('password_changed', null, $user_id);
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update password'
        ];
    }

    /**
     * Validate password strength
     */
    private function validatePasswordStrength($password)
    {
        // Minimum 8 characters, at least one uppercase, one lowercase, one number
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    /**
     * Log security events
     */
    private function logSecurityEvent($event_type, $username = null, $user_id = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_trail (user_id, action, table_name, record_id, new_values, ip_address, user_agent, severity)
            VALUES (?, 'security_event', ?, ?, ?, ?, ?, 'low')
        ");

        $stmt->execute([
            $user_id,
            $event_type,
            null,
            json_encode([
                'username' => $username,
                'event_type' => $event_type,
                'timestamp' => date('Y-m-d H:i:s')
            ]),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Get login statistics for monitoring
     */
    public function getLoginStats($days = 7)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(CASE WHEN action = 'security_event' AND JSON_EXTRACT(new_values, '$.event_type') = 'login_success' THEN 1 END) as successful_logins,
                COUNT(CASE WHEN action = 'security_event' AND JSON_EXTRACT(new_values, '$.event_type') = 'login_failed' THEN 1 END) as failed_logins,
                COUNT(CASE WHEN action = 'security_event' AND JSON_EXTRACT(new_values, '$.event_type') = 'account_locked' THEN 1 END) as locked_accounts
            FROM audit_trail 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND action = 'security_event'
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user role
     */
    public static function getCurrentRole()
    {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role)
    {
        return self::getCurrentRole() === $role;
    }

    /**
     * Require login (redirect if not logged in)
     */
    public static function requireLogin($redirect_url = 'index.php?page=login')
    {
        if (!self::isLoggedIn()) {
            header("Location: $redirect_url");
            exit();
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($required_role, $redirect_url = 'index.php?page=dashboard')
    {
        self::requireLogin();

        if (!self::hasRole($required_role)) {
            header("Location: $redirect_url");
            exit();
        }
    }
}

// Helper functions for backward compatibility (only if not already defined)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return AuthSystem::isLoggedIn();
    }
}

if (!function_exists('hasRole')) {
    function hasRole($role)
    {
        return AuthSystem::hasRole($role);
    }
}

if (!function_exists('getCurrentRole')) {
    function getCurrentRole()
    {
        return AuthSystem::getCurrentRole();
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin($redirect_url = 'index.php?page=login')
    {
        AuthSystem::requireLogin($redirect_url);
    }
}

if (!function_exists('requireRole')) {
    function requireRole($required_role, $redirect_url = 'index.php?page=dashboard')
    {
        AuthSystem::requireRole($required_role, $redirect_url);
    }
}