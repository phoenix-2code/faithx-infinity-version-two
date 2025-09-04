<?php
/**
 * Get Groups API Endpoint
 * Returns list of groups for dropdowns and selections
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
        SELECT group_id, group_name, leader_name, description, group_type, is_active
        FROM groups
        WHERE is_active = 1
        ORDER BY group_name
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($groups);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch groups: ' . $e->getMessage()]);
}
?>