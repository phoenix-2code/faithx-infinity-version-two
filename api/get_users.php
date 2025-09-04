<?php
/**
 * Get Users API Endpoint
 * Returns list of users for dropdowns and selections
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!isLoggedIn() || !in_array($_SESSION['role'], ['chief_finance_officer', 'elder_admin', 'system_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.role, u.parish,
               g.group_name
        FROM users u
        LEFT JOIN members m ON u.user_id = m.user_id
        LEFT JOIN groups g ON m.group_id = g.group_id
        WHERE u.role IN ('member', 'finance_officer')
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch users: ' . $e->getMessage()]);
}
?>