<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BNHS Grading System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f9fa;
    }
    .hero {
      background-color: #0d6efd;
      color: white;
      padding: 60px 20px;
      text-align: center;
    }
    .features .card {
      height: 100%;
    }
  </style>
</head>
<body>

  <header class="hero">
    <h1>Web-Based Grading System</h1>
    <p class="lead">Efficient Academic Record Management at Balaytigue National High School</p>
    <a href="login.php" class="btn btn-light mt-3">Login</a>
  </header>

  <main class="container my-5">
    <section class="mb-5 text-center">
      <h2>Welcome to the BNHS Grading Portal</h2>
      <p>Empowering educators, students, and administrators through streamlined digital recordkeeping and grading automation.</p>
    </section>

    <section class="features row text-center">
      <div class="col-md-4 mb-4">
        <div class="card shadow">
          <div class="card-body">
            <h5 class="card-title">For Teachers</h5>
            <p class="card-text">Automated grade computation, attendance tracking, report card generation, and real-time academic records.</p>
          </div>
        </div>
      </div>

      <div class="col-md-4 mb-4">
        <div class="card shadow">
          <div class="card-body">
            <h5 class="card-title">For Students</h5>
            <p class="card-text">Access your grades, monitor progress, and track quarterly performance with full transparency and security.</p>
          </div>
        </div>
      </div>

      <div class="col-md-4 mb-4">
        <div class="card shadow">
          <div class="card-body">
            <h5 class="card-title">For Admin</h5>
            <p class="card-text">Centralized academic data, user management, report generation, system oversight, and secure recordkeeping.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="text-center mt-5">
      <h3>Why Digital Grading?</h3>
      <p>Manual grading delays progress. Our system automates the process—boosting efficiency, accuracy, and academic transparency.</p>
    </section>
  </main>

  <footer class="text-center py-4 bg-light">
    <small>&copy; 2025 Balaytigue National High School. All rights reserved.</small>
  </footer>

</body>
</html>
