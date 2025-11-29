<?php
session_start();
require_once 'config.php'; 

$error = '';

require_once 'api/log_helper.php';

// Consistent session variable names
if (isset($_SESSION['email']) || isset($_SESSION['user_id'])) {
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $query = "SELECT * FROM user WHERE Email = ? OR UserID = ?";
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "ss", $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if ($row['Role'] === 'teacher') {
                header("Location: teacher/tdashboard.php");
                exit();
            } elseif ($row['Role'] === 'principal' || $row['Role'] === 'staff') {
                header("Location: admin/principalDash.php");
                exit();
            } elseif ($row['Role'] === 'student') {
                header("Location: student/studentPort.php");
                exit();
            } elseif ($row['Role'] === 'head') {
                header("Location: administration/studentlist.php");
                exit();
            } else {
                $error = 'Unknown role for this account.';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Prepare and execute query
    $stmt = $conn->prepare('SELECT UserID, Email, Password, Role FROM user WHERE Email = ? LIMIT 1');
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        // Use password_verify if passwords are hashed
        if (password_verify($password, $row['Password'])) {
            $_SESSION['user_id'] = $row['UserID'];
            $_SESSION['email'] = $row['Email'];
            $_SESSION['role'] = $row['Role'];
      // Log successful login
      log_system_action($conn, 'login_success', $row['UserID'], [
        'email' => $row['Email'],
        'role' => $row['Role'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
      ], 'info');
            // Redirect based on role
            if ($row['Role'] === 'teacher') {
                header('Location: teacher/tdashboard.php');
                exit();
            } elseif ($row['Role'] === 'principal') {
                header('Location: admin/principalDash.php');
                exit();
            } elseif ($row['Role'] === 'student') {
                header('Location: student/studentPort.php');
                exit();
            } elseif ($row['Role'] === 'head') {
                header('Location: administration/dashboard.php');
                exit();
            } else {
                $error = 'Unknown role for this account.';
            }
        } else {
      // Log failed login - wrong password
      log_system_action($conn, 'login_failed_password', $row['UserID'], [
        'email' => $email,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
      ], 'warning');
            $error = 'Incorrect password. Please try again.';
        }
    } else {
    // Log failed login - no account found
    log_system_action($conn, 'login_failed_no_account', null, [
      'email' => $email,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
      'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ], 'warning');
        $error = 'No account found with that email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Balaytigue National High School Login</title>
  <link rel="icon" type="image/png" href="img/logo.png">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet" />

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f0f0f0;
      overflow-x: hidden;
    }

    .left-panel {
      background: url('img/bg.png') center center/cover no-repeat;
      color: white;
      position: relative;
    }
    
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      animation: fadeInUp 0.8s ease forwards;
    }
    
    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes float {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-10px);
      }
    }
    
    @keyframes slideInFromLeft {
      from {
        opacity: 0;
        transform: translateX(-50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes slideInFromRight {
      from {
        opacity: 0;
        transform: translateX(50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(62, 163, 170, 0.4);
      }
      70% {
        box-shadow: 0 0 0 10px rgba(25, 135, 84, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(25, 135, 84, 0);
      }
    }
    
    @keyframes shake {
      0%, 100% {
        transform: translateX(0);
      }
      10%, 30%, 50%, 70%, 90% {
        transform: translateX(-5px);
      }
      20%, 40%, 60%, 80% {
        transform: translateX(5px);
      }
    }

    .left-panel::before {
      content: "";
      position: absolute;
      inset: 0;
      background-color: rgba(0,0,0,0.45);
    }

    .left-panel-content {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 3rem;
      animation: fadeInUp 1s 0.2s both;
    }

    .left-panel-content h1 {
      font-family: 'Merriweather', serif;
      font-size: 3rem;
      letter-spacing: 0.15em;
      animation: float 6s ease-in-out infinite;
    }

    .left-panel-content p {
      font-family: 'Merriweather', serif;
      font-size: 1.5rem;
    }
    
    .login-form-container {
      animation: slideInFromRight 0.8s ease-out 0.3s both;
    }
    
    .logo-animation {
      animation: float 4s ease-in-out infinite 1s;
    }
    
    .btn-login {
      transition: all 0.3s ease;
      animation: pulse 2s infinite;
    }
    
    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .form-control {
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      transform: scale(1.02);
      box-shadow: 0 0 0 0.25rem rgba(5, 102, 119, 0.25);
    }
    
    .nav-link {
      position: relative;
      transition: color 0.3s ease;
    }
    
    .nav-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 50%;
      background-color: #31b3b8ff;
      transition: all 0.3s ease;
    }
    
    .nav-link:hover::after {
      width: 100%;
      left: 0;
    }
    
    .shake {
      animation: shake 0.5s;
    }
    
    /* Floating elements in the background */
    .floating-elements {
      position: absolute;
      width: 100%;
      height: 100%;
      overflow: hidden;
      z-index: 0;
    }
    
    .floating-element {
      position: absolute;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 15s infinite linear;
    }
    
    .floating-element:nth-child(1) {
      width: 80px;
      height: 80px;
      top: 10%;
      left: 10%;
      animation-delay: 0s;
      animation-duration: 20s;
    }
    
    .floating-element:nth-child(2) {
      width: 60px;
      height: 60px;
      top: 70%;
      left: 80%;
      animation-delay: 2s;
      animation-duration: 25s;
    }
    
    .floating-element:nth-child(3) {
      width: 40px;
      height: 40px;
      top: 40%;
      left: 85%;
      animation-delay: 4s;
      animation-duration: 15s;
    }
    .btn-custom{
      background-color: #0ba5aaff;
      color: white;
      border: none;
      transition: background-color 0.3s ease;
    }
    .btn-custom:hover {
      background-color: #1eb4b4ff;
      color: white;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <nav class="navbar navbar-light bg-white shadow-sm fixed-top w-100">
    <div class="container-fluid px-2 px-md-4 justify-content-center">
      <ul class="nav">
        <li class="nav-item">
          <a class="nav-link text-dark" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="about.php">About</a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="container-fluid p-0">
    <div class="floating-elements">
      <div class="floating-element"></div>
      <div class="floating-element"></div>
      <div class="floating-element"></div>
    </div>
    
    <div class="row vh-100 g-0">
      <!-- Left Side -->
      <div class="col-lg-7 d-none d-lg-flex align-items-center left-panel">
        <div class="left-panel-content w-100 text-start">
          <p class="fade-in">Welcome!</p>
          <h2 class="fade-in display-1">Smart Card</h2>
          <h4 class="fade-in">Student Academic Performance Management System with Report Card Generation</h4>
        </div>
      </div>
      
      <!-- Right Side -->
      <div class="col-lg-5 col-12 d-flex align-items-center justify-content-center bg-white">
        <div class="w-100 p-3 p-md-4 login-form-container" style="max-width: 400px;">
          <div class="text-center mb-4">
            <img src="img/logo.png" alt="Logo" class="logo-animation" style="width:100px;">
            <h3 class="text-muted mt-2"><strong>Sign in</strong></h3>
          </div>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger shake" role="alert">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>
          
          <form id="login-form" method="POST" autocomplete="off">
            <div class="mb-3">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
              </div>
            </div>
            <div class="mb-3">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                <span class="input-group-text bg-white border-start-0">
                  <i class="bi bi-eye-slash" id="togglePassword" role="button" tabindex="0" aria-label="Toggle password visibility"></i>
                </span>
              </div>
            </div>
            <button type="submit" class="btn btn-custom w-100 py-2 fw-bold btn-login">Login</button>
            <div class="mt-3 text-center">
              <a href="#" class="text-decoration-none text-primary">Forgot password?</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Password toggle
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    toggle.addEventListener('click', () => {
      const type = password.type === 'password' ? 'text' : 'password';
      password.type = type;
      toggle.classList.toggle('bi-eye');
      toggle.classList.toggle('bi-eye-slash');
    });

    toggle.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle.click();
      }
    });

    // Add animation to form inputs on focus
    document.querySelectorAll('.form-control').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
      });
    });

    // Add subtle animation to login button on hover
    const loginBtn = document.querySelector('.btn-login');
    loginBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-3px)';
    });
    
    loginBtn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });

    // Add typing effect to the welcome message
    document.addEventListener('DOMContentLoaded', function() {
      const welcomeText = "Welcome!";
      const welcomeElement = document.querySelector('.left-panel-content p');
      
      if (welcomeElement) {
        welcomeElement.textContent = '';
        let i = 0;
        const typeWriter = () => {
          if (i < welcomeText.length) {
            welcomeElement.textContent += welcomeText.charAt(i);
            i++;
            setTimeout(typeWriter, 100);
          }
        };
        setTimeout(typeWriter, 500);
      }
    });
  </script>
</body>
</html>
