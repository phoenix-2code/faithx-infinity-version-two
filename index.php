<?php
// Ensure no whitespace or BOM before this tag

// Load configuration first
require_once 'config/config.php';

// Set session ini settings before starting session
ini_set('session.gc_maxlifetime', 1800); // 30 minutes

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load core includes
require_once 'config/database.php';
require_once 'includes/error_handler.php'; // Includes the class definition

// Initialize error handling with PDO
ErrorHandler::init($pdo, DEBUG_MODE);

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/rbac.php';

// Initialize authentication system
$auth = new AuthSystem($pdo);

// Validate session for logged-in users
if (isset($_SESSION['user_id'])) {
    if (!$auth->validateSession()) {
        // Session expired, redirect to login
        header('Location: index.php?page=login');
        exit();
    }
}

// Simple routing with enhanced security
$page = sanitize($_GET['page'] ?? 'home');

// Define page access requirements
$page_access = [
    'home' => 'public',
    'login' => 'public',
    'register' => 'public',
    'dashboard' => 'authenticated',
    'member_dashboard' => 'member',
    'cfo_dashboard' => 'chief_finance_officer',
    'fo_dashboard' => 'finance_officer',
    'elder_admin_dashboard' => 'elder_admin',
    'system_admin_dashboard' => 'system_admin',
    'test_system' => 'system_admin',
    'debug' => 'system_admin',
    'pledges' => 'authenticated',
    'payments' => 'authenticated',
    'member_payment_submission' => 'member',
    'payment_verification' => 'finance_officer',
    'reports' => 'authenticated',
    'profile' => 'authenticated',
    'logout' => 'authenticated',
    '404' => 'public',
    'coming_soon' => 'public'
];

// Check access permissions
$required_access = $page_access[$page] ?? 'public';

if ($required_access === 'authenticated' && !isLoggedIn()) {
    $page = 'login';
} elseif ($required_access !== 'public' && $required_access !== 'authenticated') {
    // Specific role required
    if (!hasRole($required_access)) {
        if (!isLoggedIn()) {
            $page = 'login';
        } else {
            $page = 'dashboard'; // Redirect to appropriate dashboard
        }
    }
}

// Include header
include 'includes/header.php';

// Route to appropriate view with error handling
try {
    switch($page) {
        case 'home':
            // Redirect logged-in users to appropriate dashboard
            if (isLoggedIn()) {
                $role = getCurrentRole();
                if ($role === 'chief_finance_officer') {
                    header('Location: index.php?page=cfo_dashboard');
                } elseif ($role === 'system_admin') {
                    header('Location: index.php?page=system_admin_dashboard');
                } else {
                    header('Location: index.php?page=dashboard');
                }
                exit();
            }
            include 'views/home.php';
            break;
            
        case 'login':
            // Redirect if already logged in
            if (isLoggedIn()) {
                header('Location: index.php?page=dashboard');
                exit();
            }
            include 'views/login.php';
            break;
            
        case 'register':
            // Redirect if already logged in
            if (isLoggedIn()) {
                header('Location: index.php?page=dashboard');
                exit();
            }
            include 'views/register.php';
            break;
            
        case 'dashboard':
            // Route to appropriate dashboard based on role
            $role = $_SESSION['role'] ?? 'member';
            if ($role === 'chief_finance_officer') {
                header('Location: index.php?page=cfo_dashboard');
                exit();
            } elseif ($role === 'system_admin') {
                header('Location: index.php?page=system_admin_dashboard');
                exit();
            } elseif ($role === 'member') {
                header('Location: index.php?page=member_dashboard');
                exit();
            } elseif ($role === 'finance_officer') {
                header('Location: index.php?page=fo_dashboard');
                exit();
            } elseif ($role === 'elder_admin') {
                header('Location: index.php?page=elder_admin_dashboard');
                exit();
            }
            // Fallback to member dashboard if role is not recognized
            header('Location: index.php?page=member_dashboard');
            exit();
            break;
            
        case 'member_dashboard':
            include 'views/member_dashboard.php';
            break;
            
        case 'cfo_dashboard':
            include 'views/cfo_dashboard.php';
            break;
        case 'fo_dashboard':
            include 'views/fo_dashboard.php';
            break;
            
        case 'elder_admin_dashboard':
            include 'views/dashboard.php'; // Elder admin uses the main dashboard view
            break;
            
        case 'system_admin_dashboard':
            include 'views/system_admin_dashboard.php';
            break;
            
        case 'pledges':
            include 'views/pledges.php';
            break;
            
        case 'payments':
            include 'views/payments.php';
            break;
            
        case 'member_payment_submission':
            include 'views/member_payment_submission.php';
            break;
            
        case 'payment_verification':
            include 'views/payment_verification.php';
            break;
            
        case 'reports':
            include 'views/reports.php';
            break;
            
        case 'profile':
            include 'views/profile.php';
            break;
            
        case 'test_system':
            include 'test_system.php';
            break;
            
        case 'debug':
            include 'debug.php';
            break;
            
        case 'logout':
            $auth->logout();
            header('Location: index.php'); // Redirect to home page after logout
            exit();
            break;
        case 'coming_soon':
            include 'views/coming_soon.php';
            break;
            
        default:
            http_response_code(404);
            include 'views/404.php';
    }
} catch (Exception $e) {
    // Log the error
    ErrorHandler::logCustomError('Page routing error: ' . $e->getMessage(), [
        'page' => $page,
        'user_id' => $_SESSION['user_id'] ?? null,
        'trace' => $e->getTraceAsString()
    ]);
    
    // Show user-friendly error
    if (DEBUG_MODE && hasRole('system_admin')) {
        echo '<div class="alert alert-danger m-3">';
        echo '<h4>Debug Error (Admin View)</h4>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning m-3">';
        echo '<h4><i class="bi bi-exclamation-triangle"></i> Page Temporarily Unavailable</h4>';
        echo '<p>We\'re experiencing technical difficulties with this page. Please try again later.</p>';
        echo '<a href="index.php" class="btn btn-primary">Return Home</a>';
        echo '</div>';
    }
}

include 'includes/footer.php';
?>