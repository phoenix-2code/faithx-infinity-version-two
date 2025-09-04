<?php
// Logout controller
if (session_status() === PHP_SESSION_NONE) session_start();

// Set flash message before destroying session
setFlashMessage('success', 'You have been logged out successfully.');

// Destroy session
session_destroy();

// Start new session for flash message
session_start();
setFlashMessage('success', 'You have been logged out successfully.');

redirect('index.php');
?>