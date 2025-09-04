<?php
/**
 * User-Friendly Error Handling System for FaithX Infinity
 * Prevents sensitive information from being displayed to users
 */

class ErrorHandler {
    private static $log_file = __DIR__ . '/../logs/error.log';
    private static $debug_mode = false;
    private static $pdo;
    
    /**
     * Initialize error handling
     */
    public static function init($pdo, $debug_mode = false) {
        self::$pdo = $pdo;
        self::$debug_mode = $debug_mode;
        
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname(self::$log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_info = [
            'type' => 'PHP Error',
            'severity' => self::getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        self::logError($error_info);
        
        // For fatal errors, show user-friendly message
        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            self::showUserFriendlyError();
            exit();
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $error_info = [
            'type' => 'Uncaught Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        self::logError($error_info);
        self::showUserFriendlyError();
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $error_info = [
                'type' => 'Fatal Error',
                'severity' => self::getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $_SESSION['user_id'] ?? 'anonymous',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ];
            
            self::logError($error_info);
            self::showUserFriendlyError();
        }
    }
    
    /**
     * Log error to file and database
     */
    private static function logError($error_info) {
        $log_entry = json_encode($error_info, JSON_PRETTY_PRINT) . "\n" . str_repeat('-', 80) . "\n";
        
        // Attempt to write to log file
        if (is_writable(dirname(self::$log_file))) {
            file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
        
        // Also log to system error log as fallback
        error_log("FaithX Error: " . $error_info['message'] . " in " . ($error_info['file'] ?? 'unknown') . " on line " . ($error_info['line'] ?? 'unknown'));

        // Log to database
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO error_logs (
                    error_type, message, file, line, user_id, username, page, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'anonymous';
            $page = $_SERVER['REQUEST_URI'] ?? 'unknown';

            $stmt->execute([
                $error_info['type'] ?? 'Unknown',
                $error_info['message'] ?? 'No message',
                $error_info['file'] ?? null,
                $error_info['line'] ?? null,
                $user_id,
                $username,
                $page,
                $error_info['timestamp'] ?? date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            // Fallback to file logging if database logging fails
            file_put_contents(self::$log_file, "Database logging failed: " . $e->getMessage() . "\n" . $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Show user-friendly error page
     */
    private static function showUserFriendlyError() {
        // Clear any output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        http_response_code(500);
        
        // If in debug mode and user is admin, show detailed error
        if (self::$debug_mode && isset($_SESSION['role']) && $_SESSION['role'] === 'system_admin') {
            self::showDetailedError();
            return;
        }
        
        // Show user-friendly error page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Error - FaithX Infinity</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                .error-container {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    padding: 3rem;
                    text-align: center;
                    max-width: 500px;
                    width: 90%;
                }
                .error-icon {
                    font-size: 4rem;
                    color: #dc3545;
                    margin-bottom: 1rem;
                }
                .error-title {
                    color: #333;
                    margin-bottom: 1rem;
                    font-weight: 600;
                }
                .error-message {
                    color: #666;
                    margin-bottom: 2rem;
                    line-height: 1.6;
                }
                .btn-home {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    padding: 12px 30px;
                    border-radius: 25px;
                    color: white;
                    text-decoration: none;
                    display: inline-block;
                    transition: transform 0.3s ease;
                }
                .btn-home:hover {
                    transform: translateY(-2px);
                    color: white;
                }
                .error-id {
                    font-size: 0.8rem;
                    color: #999;
                    margin-top: 2rem;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <i class="bi bi-exclamation-triangle-fill error-icon"></i>
                <h2 class="error-title">Oops! Something went wrong</h2>
                <p class="error-message">
                    We're sorry, but something unexpected happened. Our technical team has been notified 
                    and is working to resolve the issue. Please try again in a few moments.
                </p>
                <a href="<?= $_SERVER['HTTP_REFERER'] ?? 'index.php' ?>" class="btn-home">
                    <i class="bi bi-arrow-left"></i> Go Back
                </a>
                <a href="index.php" class="btn-home ms-2">
                    <i class="bi bi-house"></i> Home
                </a>
                <div class="error-id">
                    Error ID: <?= uniqid() ?> | Time: <?= date('Y-m-d H:i:s') ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
    
    /**
     * Show detailed error for debugging (admin only)
     */
    private static function showDetailedError() {
        $last_error = error_get_last();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Debug Error - FaithX Infinity</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                .debug-container { margin: 2rem; }
                .error-details { background: #f8f9fa; padding: 1rem; border-radius: 5px; }
                pre { background: #fff; padding: 1rem; border: 1px solid #ddd; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="debug-container">
                <div class="alert alert-danger">
                    <h4><i class="bi bi-bug"></i> Debug Mode - System Administrator View</h4>
                    <p>This detailed error information is only visible to system administrators in debug mode.</p>
                </div>
                
                <?php if ($last_error): ?>
                <div class="card mb-3">
                    <div class="card-header bg-danger text-white">
                        <h5>Error Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="error-details">
                            <p><strong>Type:</strong> <?= self::getSeverityName($last_error['type']) ?></p>
                            <p><strong>Message:</strong> <?= htmlspecialchars($last_error['message']) ?></p>
                            <p><strong>File:</strong> <?= htmlspecialchars($last_error['file']) ?></p>
                            <p><strong>Line:</strong> <?= $last_error['line'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5>Request Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>URL:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown') ?></p>
                        <p><strong>Method:</strong> <?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'unknown') ?></p>
                        <p><strong>User:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'anonymous') ?></p>
                        <p><strong>Role:</strong> <?= htmlspecialchars($_SESSION['role'] ?? 'none') ?></p>
                        <p><strong>IP:</strong> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown') ?></p>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary">Return to Dashboard</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
    
    /**
     * Get human-readable severity name
     */
    private static function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $severities[$severity] ?? 'Unknown Error';
    }
    
    /**
     * Log custom application error
     */
    public static function logCustomError($message, $context = []) {
        $error_info = [
            'type' => 'Application Error',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        self::logError($error_info);
    }
    
    /**
     * Get recent errors from database (admin only)
     */
    public static function getRecentErrors($limit = 50, $offset = 0, $filters = []) {
        $sql = "SELECT el.log_id, el.error_type, el.message, el.file, el.line, el.timestamp, el.user_id, el.page, el.status, el.solution, u.first_name, u.last_name FROM error_logs el LEFT JOIN users u ON el.user_id = u.user_id";
        $where_clauses = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_clauses[] = "el.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['error_type'])) {
            $where_clauses[] = "el.error_type = ?";
            $params[] = $filters['error_type'];
        }
        if (!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $where_clauses[] = "(el.message LIKE ? OR el.file LIKE ? OR el.username LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (count($where_clauses) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY el.timestamp DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to retrieve error logs from database: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of errors for pagination
     */
    public static function getTotalErrors($filters = []) {
        $sql = "SELECT COUNT(*) FROM error_logs el LEFT JOIN users u ON el.user_id = u.user_id";
        $where_clauses = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_clauses[] = "el.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['error_type'])) {
            $where_clauses[] = "el.error_type = ?";
            $params[] = $filters['error_type'];
        }
        if (!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $where_clauses[] = "(el.message LIKE ? OR el.file LIKE ? OR el.username LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (count($where_clauses) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Failed to get total error count from database: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of unique users affected by errors for pagination
     */
    public static function getUniqueAffectedUsersCount($filters = []) {
        $sql = "SELECT COUNT(DISTINCT el.user_id) FROM error_logs el";
        $where_clauses = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_clauses[] = "el.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['error_type'])) {
            $where_clauses[] = "el.error_type = ?";
            $params[] = $filters['error_type'];
        }
        if (!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $where_clauses[] = "(el.message LIKE ? OR el.file LIKE ? OR el.username LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (count($where_clauses) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Failed to get unique affected users count from database: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Clear error log in database (admin only)
     */
    public static function clearErrorLog() {
        try {
            $stmt = self::$pdo->prepare("TRUNCATE TABLE error_logs");
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Failed to clear error logs in database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update error log status or solution
     */
    public static function updateErrorLog($log_id, $data) {
        $set_clauses = [];
        $params = [];

        if (isset($data['status'])) {
            $set_clauses[] = "status = ?";
            $params[] = $data['status'];
        }
        if (isset($data['solution'])) {
            $set_clauses[] = "solution = ?";
            $params[] = $data['solution'];
        }

        if (empty($set_clauses)) {
            return false; // Nothing to update
        }

        $sql = "UPDATE error_logs SET " . implode(", ", $set_clauses) . " WHERE log_id = ?";
        $params[] = $log_id;

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Failed to update error log in database: " . $e->getMessage());
            return false;
        }
    }
}

?>