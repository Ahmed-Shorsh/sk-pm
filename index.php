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
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">
    
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Merriweather', serif;
            line-height: 1.6;
            color: #333;
        }

        /* Minimal Animation Styles */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Hero Section with Background Image */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('assets/images/hero.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: white;
            padding: 8rem 0 6rem;
            position: relative;
            min-height: 70vh;
            display: flex;
            align-items: center;
        }
        
        .hero-content {
            animation: fadeIn 1s ease-out;
            text-align: left;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            color: white;
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            line-height: 1.7;
            color: #f8f9fa;
            text-align: justify;
        }
        
        .hero-buttons .btn {
            margin-right: 1rem;
            margin-bottom: 0.5rem;
            padding: 0.9rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 0;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .btn-primary {
            background-color: #B91C1C;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #991B1B;
            transform: translateY(-2px);
        }
        
        .btn-outline-light {
            border: 2px solid #ffffff;
            color: #ffffff;
            background: transparent;
        }
        
        .btn-outline-light:hover {
            background: #ffffff;
            color: #333;
        }
        
        /* Card Styles */
        .step-card, .feature-card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 0;
            padding: 2rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .step-card:hover, .feature-card:hover {
            transform: translateY(-0.1px);
            box-shadow: 0 3px 7px rgba(0,0,0,0.05);
            cursor: pointer;
        }
        
        .step-card i, .feature-card i {
            color: #B91C1C;
            margin-bottom: 1rem;
        }
        
        .step-card h3, .feature-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .step-card p, .feature-card p {
            color: #666;
            text-align: justify;
        }
        
        /* Section Titles */
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            color: #333;
            text-align: left;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 3px;
            background: #B91C1C;
        }
        
        /* About Section */
        .about-list {
            list-style: none;
            padding: 0;
        }
        
        .about-list li {
            margin-bottom: 1rem;
            padding-left: 2rem;
            position: relative;
            text-align: justify;
        }
        
        .about-list li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: #28a745;
        }
        
        /* Footer */
        .footer {
            background-color: #f8f9fa;
            color: #333;
            padding: 2rem 0;
            border-top: 1px solid #dee2e6;
        }
        
        .footer p {
            margin-bottom: 0.5rem;
        }
        
        .footer small {
            color: #666;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero {
                padding: 6rem 0 4rem;
                text-align: center;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .hero-buttons .btn {
                display: block;
                width: 100%;
                margin: 0.5rem 0;
            }
            
            .section-title {
                text-align: center;
            }
            
            .section-title::after {
                left: 50%;
                transform: translateX(-50%);
            }
        }
        
        /* Content Sections */
        .content-section {
            padding: 5rem 0;
        }
        
        .content-section p {
            text-align: justify;
            line-height: 1.8;
        }
        
        /* Navbar Styling */
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            margin-left: 1rem;
        }
        
        .navbar-nav .nav-link:hover {
            color:rgb(255, 255, 255) !important;
        }
        
        /* Smooth Scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Loading Animation */
        body {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <!-- Header / Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="./assets/logo/SKE.png" alt="SK Group" class="d-inline-block align-text-top">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold">Transform Your Performance Management</h1>
                        <p class="lead">
                            Elevate your organization's performance with our comprehensive digital platform. 
                            Streamline evaluations, set strategic goals, and drive measurable results through 
                            data-driven insights and collaborative feedback systems designed for modern workplaces.
                        </p>
                        <div class="hero-buttons">
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-2"></i>Get Started Now
                            </a>
                            <a href="#about" class="btn btn-outline-light">
                                <i class="fas fa-info-circle me-2"></i>Learn More
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="content-section">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="h5">Secure Authentication</h3>
                        <p>Access your personalized dashboard through our secure login system. Each user receives role-based access tailored to their organizational responsibilities and permissions.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <i class="fas fa-bullseye fa-3x mb-3"></i>
                        <h3 class="h5">Strategic Goal Setting</h3>
                        <p>Define clear, measurable objectives and key performance indicators. Managers establish monthly targets while aligning individual goals with departmental and organizational objectives.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <i class="fas fa-clipboard-check fa-3x mb-3"></i>
                        <h3 class="h5">Comprehensive Evaluation</h3>
                        <p>Conduct thorough performance assessments through our structured evaluation system. Enable peer reviews, manager evaluations, and self-assessments for complete 360-degree feedback.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <h3 class="h5">Analytics & Reporting</h3>
                        <p>Generate comprehensive performance reports with real-time analytics. Export detailed insights in multiple formats including PDF and CSV for strategic decision-making.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="content-section bg-light">
        <div class="container">
            <h2 class="section-title">Key Features</h2>
            <div class="row gy-4">
                <div class="col-lg-4">
                    <div class="feature-card">
                        <i class="fas fa-users-cog fa-3x mb-3"></i>
                        <h3 class="h5">Advanced User Management</h3>
                        <p>Comprehensive administrative controls for managing user accounts, roles, and permissions across all organizational levels. Streamline onboarding and maintain security protocols.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <h3 class="h5">Performance Indicators</h3>
                        <p>Create and customize performance metrics tailored to specific roles and departments. Configure weighting systems and establish clear measurement criteria for objective evaluation.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                        <h3 class="h5">Planning & Tracking</h3>
                        <p>Set ambitious targets at the beginning of each evaluation period and monitor progress in real-time. Track actual performance against planned objectives with detailed variance analysis.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <i class="fas fa-user-friends fa-3x mb-3"></i>
                        <h3 class="h5">360-Degree Feedback</h3>
                        <p>Foster collaborative development through structured peer evaluation systems. Enable multi-directional feedback for comprehensive performance assessment and professional growth.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <i class="fas fa-download fa-3x mb-3"></i>
                        <h3 class="h5">Export & Integration</h3>
                        <p>Access interactive dashboards with real-time data visualization. Export performance data in various formats for external analysis and seamless integration with existing systems.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <i class="fas fa-history fa-3x mb-3"></i>
                        <h3 class="h5">Audit Trail</h3>
                        <p>Maintain complete transparency with comprehensive activity logging. Track all user actions and system changes for accountability, compliance, and performance improvement insights.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="content-section">
        <div class="container">
            <h2 class="section-title">About SK Performance Management</h2>
            <div class="row">
                <div class="col-lg-8">
                    <p class="lead mb-4">
                        SK Group Performance Management (SK-PM) represents a comprehensive digital transformation solution designed to revolutionize how organizations approach performance evaluation and management. Our platform addresses the complex challenges of modern performance management through intelligent automation, collaborative feedback systems, and data-driven insights.
                    </p>
                    <ul class="about-list">
                        <li>
                            <strong>Role-Based Access Control:</strong> Our platform provides tailored user experiences for administrators, managers, and employees, ensuring that each user has access to the appropriate tools and information relevant to their organizational role and responsibilities.
                        </li>
                        <li>
                            <strong>Flexible Performance Indicators:</strong> Create custom performance metrics that align with your organization's specific goals and values, or leverage our comprehensive library of industry-standard evaluation criteria and templates.
                        </li>
                        <li>
                            <strong>Intelligent Analytics:</strong> Advanced algorithms automatically calculate individual, departmental, and organizational performance scores, providing actionable insights for strategic decision-making and continuous improvement initiatives.
                        </li>
                        <li>
                            <strong>Enterprise Security:</strong> Built with enterprise-grade security protocols and comprehensive audit trails, ensuring data protection, compliance with regulatory requirements, and complete transparency in all performance management activities.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p>&copy; 2025 SK Group Performance Management. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Smooth Scrolling Enhancement -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Simple hover effects for cards
        document.querySelectorAll('.step-card, .feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>