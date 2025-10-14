<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Balaytigue National High School</title>
  <link rel="icon" type="image/png" href="img/logo.png"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #214b8aff;
      --primary-dark: #082a5cff;
      --secondary: #6c757d;
      --light: #f8f9fa;
      --dark: #212529;
      --success: #198754;
      --warning: #ffc107;
      --danger: #dc3545;
    }
    
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f9fa;
      line-height: 1.6;
      scroll-behavior: smooth;
    }
    
    .hero {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 100px 20px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('img/bg.png') no-repeat center center;
      background-size: cover;
      background-position: bottom;
    }
    
    .hero-content {
      position: relative;
      z-index: 1;
    }
    
    .logo-container {
      animation: pulse 5s infinite;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.5); }
      100% { transform: scale(1); }
    }
    
    .features .card {
      height: 100%;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: none;
      border-radius: 12px;
      overflow: hidden;
    }
    
    .features .card:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    
    .feature-icon {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 15px;
    }
    
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
      padding: 12px 30px;
      border-radius: 50px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(13, 110, 253, 0.3);
    }
    
    .section-title {
      position: relative;
      margin-bottom: 40px;
      padding-bottom: 15px;
    }
    
    .section-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: var(--primary);
      border-radius: 2px;
    }
    
    .benefits {
      background-color: white;
      padding: 80px 0;
    }
    
    .benefit-item {
      text-align: center;
      padding: 20px;
    }
    
    .benefit-icon {
      font-size: 2rem;
      color: var(--primary);
      margin-bottom: 15px;
      background: rgba(13, 110, 253, 0.1);
      width: 70px;
      height: 70px;
      line-height: 70px;
      border-radius: 50%;
      display: inline-block;
    }
    
    .stats {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 60px 0;
    }
    
    .stat-number {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    
    .testimonial-card {
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      margin: 15px 0;
      transition: transform 0.3s ease;
    }
    
    .testimonial-card:hover {
      transform: translateY(-5px);
    }
    
    .testimonial-text {
      font-style: italic;
      margin-bottom: 20px;
    }
    
    .testimonial-author {
      font-weight: 600;
      color: var(--primary);
    }
    
    .testimonial-role {
      color: var(--secondary);
      font-size: 0.9rem;
    }
    
    .cta-section {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
      padding: 80px 0;
      text-align: center;
    }
    
    footer {
      background-color: var(--dark);
      color: white;
      padding: 40px 0 20px;
    }
    
    .footer-links a {
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      transition: color 0.3s ease;
    }
    
    .footer-links a:hover {
      color: white;
    }
    
    .social-icons a {
      color: white;
      font-size: 1.2rem;
      margin: 0 10px;
      transition: transform 0.3s ease;
    }
    
    .social-icons a:hover {
      transform: translateY(-5px);
    }
    
    .scroll-top {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: var(--primary);
      color: white;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
      z-index: 1000;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .scroll-top.active {
      opacity: 1;
      visibility: visible;
    }
    
    .scroll-top:hover {
      background: var(--primary-dark);
      transform: translateY(-3px);
    }
    
    /* New styles for improvements */
    .navbar {
      background-color: rgba(255, 255, 255, 0.95);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      transition: all 0.3s ease;
    }
    
    .navbar-brand {
      font-weight: 700;
      color: var(--primary) !important;
    }
    
    .nav-link {
      font-weight: 500;
      transition: color 0.3s ease;
    }
    
    .nav-link:hover {
      color: var(--primary) !important;
    }
    
    .hero {
      margin-top: 76px; /* Account for fixed navbar */
    }
    
    .image-gallery {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 15px;
      margin: 30px 0;
    }
    
    .image-gallery img {
      width: 100%;
      max-width: 300px;
      height: 200px;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    
    .image-gallery img:hover {
      transform: scale(1.03);
    }
    
    .feature-highlight {
      background-color: rgba(13, 110, 253, 0.05);
      border-radius: 12px;
      padding: 20px;
      margin: 20px 0;
    }
    
    .progress-container {
      margin: 40px 0;
    }
    
    .progress-bar {
      background-color: var(--primary);
    }
    
    .timeline {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .timeline::after {
      content: '';
      position: absolute;
      width: 6px;
      background-color: var(--primary);
      top: 0;
      bottom: 0;
      left: 50%;
      margin-left: -3px;
    }
    
    .timeline-item {
      padding: 10px 40px;
      position: relative;
      width: 50%;
      box-sizing: border-box;
    }
    
    .timeline-item::after {
      content: '';
      position: absolute;
      width: 25px;
      height: 25px;
      background-color: white;
      border: 4px solid var(--primary);
      top: 15px;
      border-radius: 50%;
      z-index: 1;
    }
    
    .left {
      left: 0;
    }
    
    .right {
      left: 50%;
    }
    
    .left::after {
      right: -12.5px;
    }
    
    .right::after {
      left: -12.5px;
    }
    
    .timeline-content {
      padding: 20px 30px;
      background-color: white;
      position: relative;
      border-radius: 6px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    @media screen and (max-width: 768px) {
      .timeline::after {
        left: 31px;
      }
      
      .timeline-item {
        width: 100%;
        padding-left: 70px;
        padding-right: 25px;
      }
      
      .timeline-item::after {
        left: 18px;
      }
      
      .right {
        left: 0;
      }
    }
    
    .fade-in {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.6s ease, transform 0.6s ease;
    }
    
    .fade-in.visible {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
      <a class="navbar-brand" href="#">
        <img src="img/logo.png" alt="BNHS Logo" width="40" class="me-2">
        BANAHIS
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="#home">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#features">Features</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#benefits">Benefits</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#testimonials">Testimonials</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="login.php" style="color: var(--primary) !important;">Login</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <header id="home" class="hero">
    <div class="hero-content">
      <div class="logo-container">
        <img src="img/logo.png" alt="BNHS Logo" width="80"/>
      </div>
      <h1 class="display-4 fw-bold">BALAYTIGUE NATIONAL HIGH SCHOOL</h1>
      <p class="lead fs-4">SmartCard: A Student Academic Performance Management System with  Report Card Generation</p>
      <a href="login.php" class="btn btn-light btn-lg mt-3 px-4 py-2">
        <i class="fas fa-sign-in-alt me-2"></i>Login to Portal
      </a>
    </div>
  </header>

  <main>
    <!-- Welcome Section -->
    <section class="container my-5 py-5">
      <div class="row align-items-center fade-in">
        <div class="col-lg-6">
          <h2 class="section-title">Welcome to the BANAHIS</h2>
          <p class="fs-5">Empowering educators, students, and administrators through streamlined digital recordkeeping and grading automation.</p>
          <p>Our web-based grading system transforms traditional academic management into a seamless digital experience, saving time and improving accuracy across all educational processes.</p>
          <div class="mt-4">
            <a href="#features" class="btn btn-outline-primary me-2">Explore Features</a>
            <a href="#benefits" class="btn btn-outline-secondary">Learn More</a>
          </div>
        </div>
        <div class="col-lg-6 text-center">
          <img src="img/flag.jpg" alt="BNHS Campus" class="img-fluid rounded shadow" style="max-height: 400px;">
        </div>
      </div>
      
      <!-- Image Gallery -->
      <div class="image-gallery fade-in">
        <img src="img/learning.jpg" alt="Students learning">
        <img src="img/learning2.jpg" alt="Classroom activities">
        <img src="img/learning3.jpg" alt="School environment">
        <img src="img/flag.jpg" alt="School flag ceremony">
        <img src="img/class.jpg" alt="Classroom setting">
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="container my-5 py-5">
      <h2 class="section-title text-center">System Features</h2>
      <p class="text-center mb-5 fs-5">Designed to meet the needs of all stakeholders in the educational process</p>
      
      <div class="features row text-center">
        <div class="col-md-4 mb-4 fade-in">
          <div class="card shadow">
            <div class="card-body p-4">
              <div class="feature-icon">
                <i class="fas fa-chalkboard-teacher"></i>
              </div>
              <h5 class="card-title">For Teachers</h5>
              <p class="card-text">Attendance tracking, report card generation, and real-time academic records with intuitive dashboards.</p>
              <ul class="list-unstyled mt-3 text-start">
                <li><i class="fas fa-check text-success me-2"></i>Automated report card generation</li>
                <li><i class="fas fa-check text-success me-2"></i>Attendance management</li>
                <li><i class="fas fa-check text-success me-2"></i>Progress report generation</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4 fade-in">
          <div class="card shadow">
            <div class="card-body p-4">
              <div class="feature-icon">
                <i class="fas fa-user-graduate"></i>
              </div>
              <h5 class="card-title">For Students</h5>
              <p class="card-text">Access your grades, monitor progress, and track quarterly performance with full transparency and security.</p>
              <ul class="list-unstyled mt-3 text-start">
                <li><i class="fas fa-check text-success me-2"></i>Real-time grade viewing</li>
                <li><i class="fas fa-check text-success me-2"></i>Performance analytics</li>
                <li><i class="fas fa-check text-success me-2"></i>Assignment tracking</li>
                <li><i class="fas fa-check text-success me-2"></i>Mobile-friendly access</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4 fade-in">
          <div class="card shadow">
            <div class="card-body p-4">
              <div class="feature-icon">
                <i class="fas fa-user-tie"></i>
              </div>
              <h5 class="card-title">For Admin</h5>
              <p class="card-text">Centralized academic data, user management, report generation, system oversight, and secure recordkeeping.</p>
              <ul class="list-unstyled mt-3 text-start">
                <li><i class="fas fa-check text-success me-2"></i>Centralized data management</li>
                <li><i class="fas fa-check text-success me-2"></i>User access controls</li>
                <li><i class="fas fa-check text-success me-2"></i>Advanced reporting tools</li>
                <li><i class="fas fa-check text-success me-2"></i>System analytics</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="benefits">
      <div class="container">
        <h2 class="section-title text-center">Why Choose Digital Grading?</h2>
        <p class="text-center mb-5 fs-5">Manual grading delays progress. Our system automates the process—boosting efficiency, accuracy, and academic transparency.</p>
        
        <div class="row">
          <div class="col-md-6 col-lg-3 benefit-item fade-in">
            <div class="benefit-icon">
              <i class="fas fa-bolt"></i>
            </div>
            <h5>Efficiency</h5>
            <p>Reduce grading time by up to 70% with automated calculations and reporting.</p>
          </div>
          <div class="col-md-6 col-lg-3 benefit-item fade-in">
            <div class="benefit-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <h5>Accuracy</h5>
            <p>Minimize human errors with automated grade computation and validation.</p>
          </div>
          <div class="col-md-6 col-lg-3 benefit-item fade-in">
            <div class="benefit-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h5>Security</h5>
            <p>Role-based access ensures data privacy and protection of academic records.</p>
          </div>
          <div class="col-md-6 col-lg-3 benefit-item fade-in">
            <div class="benefit-icon">
              <i class="fas fa-mobile-alt"></i>
            </div>
            <h5>Accessibility</h5>
            <p>Access the system anytime, anywhere with our responsive web design.</p>
          </div>
        </div>
      </div>
    </section>


    <!-- Testimonials Section -->
    <section id="testimonials" class="container my-5 py-5">
      <h2 class="section-title text-center">What Our Users Say</h2>
      <p class="text-center mb-5 fs-5">Hear from educators and students who use our system</p>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <h2 class="mb-4">Ready to Transform Academic Management?</h2>
        <p class="fs-5 mb-4">Join hundreds of educators and students already using our system</p>
        <a href="login.php" class="btn btn-light btn-lg px-5 py-3">
          <i class="fas fa-rocket me-2"></i>Get Started Now
        </a>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer>
    <div class="container">
      <div class="row">
        <div class="col-lg-4 mb-4">
          <h5>BNHS Grading System</h5>
          <p>Empowering education through technology at Balaytigue National High School.</p>
          <div class="social-icons mt-3">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
          </div>
        </div>
        <div class="col-lg-2 col-md-6 mb-4">
          <h5>Quick Links</h5>
          <div class="footer-links d-flex flex-column">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#benefits">Benefits</a>
            <a href="login.php">Login</a>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
          <h5>Resources</h5>
          <div class="footer-links d-flex flex-column">
            <a href="#">User Guide</a>
            <a href="#">FAQ</a>
            <a href="#">Support</a>
            <a href="#">Privacy Policy</a>
          </div>
        </div>
        <div class="col-lg-3 mb-4">
          <h5>Contact Us</h5>
          <p><i class="fas fa-map-marker-alt me-2"></i> Balaytigue National High School</p>
          <p><i class="fas fa-phone me-2"></i> (123) 456-7890</p>
          <p><i class="fas fa-envelope me-2"></i> info@bnhs.edu.ph</p>
        </div>
      </div>
      <hr class="mt-4">
      <div class="text-center py-3">
        <small>&copy; 2025 Balaytigue National High School. All rights reserved.</small>
      </div>
    </div>
  </footer>

  <!-- Scroll to Top Button -->
  <div class="scroll-top" id="scrollTop">
    <i class="fas fa-chevron-up"></i>
  </div>

  <script>
    // Scroll to top functionality
    const scrollTop = document.getElementById('scrollTop');
    
    window.addEventListener('scroll', () => {
      if (window.pageYOffset > 300) {
        scrollTop.classList.add('active');
      } else {
        scrollTop.classList.remove('active');
      }
    });
    
    scrollTop.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
      if (window.scrollY > 50) {
        document.querySelector('.navbar').style.padding = '10px 0';
        document.querySelector('.navbar').style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
      } else {
        document.querySelector('.navbar').style.padding = '15px 0';
        document.querySelector('.navbar').style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
      }
    });
    
    // Fade in animation on scroll
    const fadeElements = document.querySelectorAll('.fade-in');
    
    const fadeInOnScroll = function() {
      fadeElements.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;
        const elementVisible = 150;
        
        if (elementTop < window.innerHeight - elementVisible) {
          element.classList.add('visible');
        }
      });
    };
    
    window.addEventListener('scroll', fadeInOnScroll);
    // Initial check in case elements are already in view
    fadeInOnScroll();
  </script>
  
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>