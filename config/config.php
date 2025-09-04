<?php
/**
 * FaithX Infinity Configuration
 */

// Environment Configuration
define('ENVIRONMENT', 'development'); // 'development', 'staging', 'production'
define('DEBUG_MODE', true);

// Application Settings
define('APP_NAME', 'FaithX Infinity');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/faithx-infinity');

// Security Settings
define('SESSION_LIFETIME', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// File Upload Settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Email Settings (configure based on your email provider)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', ''); // Configure in production
define('SMTP_PASSWORD', ''); // Configure in production
define('SMTP_FROM_EMAIL', 'noreply@faithx.org');
define('SMTP_FROM_NAME', 'FaithX Infinity');

// Logging Settings
define('LOG_LEVEL', DEBUG_MODE ? 'DEBUG' : 'ERROR');
define('LOG_PATH', __DIR__ . '/../logs/');

// Cache Settings
define('CACHE_ENABLED', !DEBUG_MODE);
define('CACHE_LIFETIME', 900); // 15 minutes

// Pagination Settings
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Currency Settings
define('CURRENCY_SYMBOL', 'KSh');
define('CURRENCY_CODE', 'KES');

// Date/Time Settings
define('DEFAULT_TIMEZONE', 'Africa/Nairobi');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M d, Y');

// Feature Flags
define('ENABLE_EMAIL_NOTIFICATIONS', true);
define('ENABLE_SMS_NOTIFICATIONS', false);
define('ENABLE_AUDIT_LOGGING', true);
define('ENABLE_PERFORMANCE_MONITORING', DEBUG_MODE);

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Error reporting based on environment
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Session configuration (only if session hasn't started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

// Create necessary directories
$directories = [
    LOG_PATH,
    UPLOAD_PATH,
    UPLOAD_PATH . 'announcements/',
    UPLOAD_PATH . 'profiles/',
    __DIR__ . '/../cache/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}


?>