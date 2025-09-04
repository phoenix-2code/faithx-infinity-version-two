<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FaithX Infinity - Church Pledge Management</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container">
      <!-- Brand -->
      <a class="navbar-brand" href="index.php">
        <i class="bi bi-infinity"></i> FaithX Infinity
      </a>

      <!-- Toggler -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Nav Content -->
      <div class="collapse navbar-collapse" id="navbarNav">
        <!-- Left-aligned nav -->
        <ul class="navbar-nav me-auto">
          <?php if (isLoggedIn()): ?>
            <?php $user_role = $_SESSION['role'] ?? 'member'; ?>

            <?php if ($user_role === 'member'): ?>
              <!-- MEMBER NAVIGATION -->
              <li class="nav-item">
                <a class="nav-link <?= $page === 'member_dashboard' ? 'active' : '' ?>" href="index.php?page=member_dashboard">
                  <i class="bi bi-speedometer2"></i> Dashboard
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'pledges' ? 'active' : '' ?>" href="index.php?page=pledges">
                  <i class="bi bi-gift"></i> My Pledges
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'member_payment_submission' ? 'active' : '' ?>" href="index.php?page=member_payment_submission">
                  <i class="bi bi-credit-card"></i> Submit Payment
                </a>
              </li>

            <?php elseif ($user_role === 'finance_officer'): ?>
              <!-- FINANCE OFFICER NAVIGATION -->
              <li class="nav-item">
                <a class="nav-link <?= $page === 'fo_dashboard' ? 'active' : '' ?>" href="index.php?page=fo_dashboard">
                  <i class="bi bi-speedometer2"></i> Dashboard
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'pledges' ? 'active' : '' ?>" href="index.php?page=pledges">
                  <i class="bi bi-gift"></i> Group Pledges
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'payments' ? 'active' : '' ?>" href="index.php?page=payments">
                  <i class="bi bi-credit-card"></i> Record Payments
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'payment_verification' ? 'active' : '' ?>" href="index.php?page=payment_verification">
                  <i class="bi bi-shield-check"></i> Verify Payments
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="index.php?page=reports">
                  <i class="bi bi-graph-up"></i> Group Reports
                </a>
              </li>

            <?php elseif ($user_role === 'elder_admin'): ?>
              <!-- ELDER ADMIN NAVIGATION -->
              <li class="nav-item">
                <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                  <i class="bi bi-speedometer2"></i> Dashboard
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="index.php?page=reports">
                  <i class="bi bi-pie-chart"></i> Church Overview
                </a>
              </li>

            <?php elseif ($user_role === 'chief_finance_officer'): ?>
              <!-- CFO NAVIGATION -->
              <li class="nav-item">
                <a class="nav-link <?= $page === 'cfo_dashboard' ? 'active' : '' ?>" href="index.php?page=cfo_dashboard">
                  <i class="bi bi-bank"></i> CFO Dashboard
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'pledges' ? 'active' : '' ?>" href="index.php?page=pledges">
                  <i class="bi bi-gift"></i> Church Pledges
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'payments' ? 'active' : '' ?>" href="index.php?page=payments">
                  <i class="bi bi-credit-card"></i> Payment Management
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'payment_verification' ? 'active' : '' ?>" href="index.php?page=payment_verification">
                  <i class="bi bi-shield-check"></i> Payment Verification
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="index.php?page=reports">
                  <i class="bi bi-graph-up"></i> Financial Reports
                </a>
              </li>

            <?php elseif ($user_role === 'system_admin'): ?>
              <!-- SYSTEM ADMIN NAVIGATION -->
              <li class="nav-item">
                <a class="nav-link <?= $page === 'system_admin_dashboard' ? 'active' : '' ?>" href="index.php?page=system_admin_dashboard">
                  <i class="bi bi-gear-fill"></i> System Admin Dashboard
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'pledges' ? 'active' : '' ?>" href="index.php?page=pledges">
                  <i class="bi bi-gift"></i> All Pledges
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'payments' ? 'active' : '' ?>" href="index.php?page=payments">
                  <i class="bi bi-credit-card"></i> Payments
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="index.php?page=reports">
                  <i class="bi bi-graph-up"></i> Reports
                </a>
              </li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>

        <!-- Right-aligned nav -->
        <ul class="navbar-nav">
          <?php if (isLoggedIn()): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i> <?= sanitize($_SESSION['username']) ?>
             <!--   <small class="badge bg-secondary ms-1"><?= ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'member')) ?></small> -->
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item" href="index.php?page=profile">
                    <i class="bi bi-person"></i> Profile
                  </a>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li>
                  <button class="dropdown-item" id="logoutButton">
                    <i class="bi bi-box-arrow-right"></i> Logout
                  </button>
                </li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link <?= $page === 'login' ? 'active' : '' ?>" href="index.php?page=login">
                <i class="bi bi-box-arrow-in-right"></i> Login
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $page === 'register' ? 'active' : '' ?>" href="index.php?page=register">
                <i class="bi bi-person-plus"></i> Register
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
  <!-- Flash Messages -->
  <?php
  $flash = getFlashMessage();
  if ($flash):
  ?>
    <div class="container mt-3">
      <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= sanitize($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Main Content -->
  <main class="container mt-4">

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="logoutConfirmModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to log out?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="index.php?page=logout" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            const logoutConfirmModal = new bootstrap.Modal(document.getElementById('logoutConfirmModal'));
            logoutConfirmModal.show();
        });
    }
});
</script>