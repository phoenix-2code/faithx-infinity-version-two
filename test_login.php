<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

try {
    $auth = new AuthSystem($pdo);
    $result = $auth->login('sysadmin', 'FAITHX2025!');
    echo "Login with FAITHX2025! (ALL CAPS): " . json_encode($result) . "\n";

    $result2 = $auth->login('sysadmin', 'FaithX2025!');
    echo "Login with FaithX2025! (Mixed Case): " . json_encode($result2) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
