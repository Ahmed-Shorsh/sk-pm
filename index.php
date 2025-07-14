
<?php
require_once __DIR__ . '/backend/auth.php';  

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SK Group Performance Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">

</head>
<body>
    <!-- Header / Navbar -->
    <header class="navbar navbar-expand-lg navbar-light bg-transparent py-3 border-bottom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">SK PM</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero text-white text-center d-flex align-items-center" style="background-image:url('assets/images/hero-bg.jpg');">
        <div class="container py-5">
            <h1 class="display-4 fw-bold">SK Group Performance Management</h1>
            <p class="lead mb-4">Streamline evaluations, set powerful goals, and track real-time results—all in one dedicated platform.</p>
            <div class="hero-buttons d-flex flex-wrap justify-content-center gap-2">
                <a href="login.php" class="btn btn-lg btn-primary"><i class="fas fa-user-lock me-2"></i>Get Started</a>
                <a href="#about" class="btn btn-lg btn-outline-light"><i class="fas fa-eye me-2"></i>Learn More</a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5">How It Works</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="step-card p-4 text-center h-100">
                        <i class="fas fa-sign-in-alt fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Secure Login</h3>
                        <p>Create your secure account and access role-based dashboards.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card p-4 text-center h-100">
                        <i class="fas fa-bullseye fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Set Goals</h3>
                        <p>Managers define monthly KPIs and departmental objectives.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card p-4 text-center h-100">
                        <i class="fas fa-tasks fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Submit Evaluations</h3>
                        <p>Peers and managers evaluate individual performance seamlessly.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card p-4 text-center h-100">
                        <i class="fas fa-chart-line fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">View Reports</h3>
                        <p>Generate insightful charts and export PDF/CSV reports.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features py-5 bg-light">
        <div class="container">
            <h2 class="section-title text-center mb-5">Key Features</h2>
            <div class="row gy-4">
                <div class="col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <i class="fas fa-users-cog fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">User Management</h3>
                        <p>Admins can create, update, and deactivate user accounts with ease.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <i class="fas fa-chart-pie fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Indicator Control</h3>
                        <p>Define, categorize, and weight performance indicators by role.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <i class="fas fa-calendar-check fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Plan & Actuals</h3>
                        <p>Set targets early and record actual results at month-end.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <i class="fas fa-user-check fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Peer Evaluation</h3>
                        <p>Empower employees to provide feedback for continuous growth.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <i class="fas fa-file-export fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Reporting & Export</h3>
                        <p>Interactive dashboards and exportable files in PDF/CSV formats.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <i class="fas fa-history fa-2x mb-3"></i>
                        <h3 class="h5 mb-2">Activity Log</h3>
                        <p>Track changes and user actions through a comprehensive history.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <h2 class="section-title text-center mb-4">About SK-PM</h2>
            <p class="text-center mx-auto" style="max-width:700px;">
                SK Group Performance Management (SK-PM) is a comprehensive web application designed to digitize and streamline the entire performance evaluation lifecycle. From role-based dashboards for admins, managers, and employees, to flexible KPI setting, peer and manager evaluations, and robust reporting—SK-PM empowers organizations to build a culture of accountability and continuous improvement.
            </p>
            <ul class="list-unstyled mx-auto" style="max-width:700px;">
                <li><i class="fas fa-check-circle me-2 text-primary"></i><strong>Role-Based Control:</strong> Tailored experiences for Admins, Managers, and Employees.</li>
                <li><i class="fas fa-check-circle me-2 text-primary"></i><strong>Flexible Indicators:</strong> Create custom goals or choose from defaults.</li>
                <li><i class="fas fa-check-circle me-2 text-primary"></i><strong>Automated Scoring:</strong> Instant calculation of individual, departmental, and final scores.</li>
                <li><i class="fas fa-check-circle me-2 text-primary"></i><strong>Secure & Auditable:</strong> Full history of actions and secure authentication.
            </ul>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4 bg-dark text-white">
        <div class="container text-center">
            <p class="mb-1">&copy; 2025 SK Group. All rights reserved.</p>
            <small>Powered by Laravel &amp; MySQL</small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>