<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center text-center text-lg-start">
            <!-- Left Content -->
            <div class="col-lg-6 mb-5 mb-lg-0">
                <h1 class="display-4 fw-bold text-white">
                    <i class="bi bi-infinity hero logo"></i> FaithX Infinity
                </h1>
                <p class="lead text-white-50">
                    Empowering faith communities through digital stewardship and transparent pledge management.
                </p>

                <?php if (!isLoggedIn()): ?>
                <div class="d-flex justify-content-center justify-content-lg-start mt-5">
                    <a href="index.php?page=register" class="btn btn-light btn-xl me-3">
                        <i class="bi bi-person-plus"></i> Register
                    </a>
                    <a href="index.php?page=login" class="btn btn-light btn-xl">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-center justify-content-lg-start mt-5">
                    <a href="index.php?page=dashboard" class="btn btn-success btn-xl">
                        <i class="bi bi-speedometer2"></i> Go to Dashboard
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Icon -->
            <div class="col-lg-6 text-center">
                <i class="bi bi-infinity" style="font-size: 15rem; opacity: 0.2; filter: blur(1px);"></i>
            </div>
        </div>
    </div>
</div>


<!-- Features Section -->
<div class="container">
    <div class="row text-center mb-5">
        <div class="col-12">
            <h2 class="mb-4">Why Choose FaithX Infinity?</h2>
            <p class="lead text-muted">Modern pledge management designed specifically for faith communities</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <i class="bi bi-shield-check"></i>
                    <h4>Secure & Reliable</h4>
                    <p>Your financial data is protected with enterprise-grade security and regular backups.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <i class="bi bi-phone"></i>
                    <h4>Mobile Friendly</h4>
                    <p>Access your pledges and make commitments from any device, anywhere, anytime.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <i class="bi bi-graph-up"></i>
                    <h4>Detailed Reports</h4>
                    <p>Generate comprehensive reports to track progress and maintain transparency.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <i class="bi bi-people"></i>
                    <h4>Role-Based Access</h4>
                    <p>Different access levels for members, finance officers, and administrators.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <i class="bi bi-heart"></i>
                    <h4>Category Management</h4>
                    <p>Organize pledges by ministry, project, or campaign for better tracking.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card h-100">
                <div class="card-body">
                    <i class="bi bi-clock-history"></i>
                    <h4>Payment Tracking</h4>
                    <p>Monitor pledge progress with detailed payment history and status updates.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!isLoggedIn()): ?>
    <!-- Call to Action -->
    <div class="row mt-5">
        <div class="cta-card text-white text-center">
        <div class="card-body py-5">
        <h3>Ready to Transform Your Church's Pledge Management?</h3>
        <p class="lead mb-4">
            Join faith communities already using FaithX Infinity to manage their stewardship programs.
        </p>
        <a href="index.php?page=register" class="btn btn-light btn-xl">
            <i class="bi bi-person-plus"></i> Start Your Journey
        </a>
    </div>
</div>

    </div>
    <?php endif; ?>
</div>