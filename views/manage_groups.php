<?php
// Placeholder for Manage Groups page

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and has appropriate role (e.g., system_admin or group_leader)
if (!isLoggedIn() || (!hasRole('system_admin') && !hasRole('group_leader'))) {
    header('Location: index.php?page=login');
    exit();
}

$pageTitle = "Manage Groups";

?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-people"></i> Manage Groups</h2>
            <p class="text-muted">This page is under construction. Group management features will be available soon.</p>
            <a href="index.php?page=system_admin_dashboard" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
</div>
