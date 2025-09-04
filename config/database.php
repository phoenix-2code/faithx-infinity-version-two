<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'faithx_infinity');

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $dbh;
    private $error;

    public function __construct() {
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
        
        // Set options
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        // Create a new PDO instance
        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function getConnection() {
        return $this->dbh;
    }
}

// Create database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Failed to establish database connection");
    }
} catch(Exception $e) {
    // Log error for debugging (in production, log to file)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Log error for debugging
    if (class_exists('ErrorHandler')) {
        ErrorHandler::logCustomError('Database connection failed: ' . $e->getMessage());
    }
    
    // Show user-friendly error message
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        // Show user-friendly error page
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Service Unavailable - FaithX Infinity</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
        </head>
        <body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
            <div class="text-center">
                <div class="card shadow-lg" style="max-width: 500px;">
                    <div class="card-body p-5">
                        <i class="bi bi-database-x text-danger" style="font-size: 4rem;"></i>
                        <h2 class="mt-3 mb-3">Service Temporarily Unavailable</h2>
                        <p class="text-muted mb-4">
                            We're experiencing technical difficulties with our database. 
                            Our technical team has been notified and is working to resolve the issue.
                        </p>
                        <p class="text-muted">
                            Please try again in a few minutes.
                        </p>
                        <button onclick="location.reload()" class="btn btn-primary mt-3">
                            <i class="bi bi-arrow-clockwise"></i> Try Again
                        </button>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}
?>