<?php
session_start();
require_once 'config.php'; 

$error = '';

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
                header("Location: teacher/list.php");
                exit();
            } elseif ($row['Role'] === 'principal' || $row['Role'] === 'staff') {
                header("Location: admin/principalDash.php");
                exit();
            } elseif ($row['Role'] === 'student') {
                header("Location: student/studentPort.php");
                exit();
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
            // Redirect based on role
            if ($row['Role'] === 'teacher') {
                header('Location: teacher/list.php');
                exit();
            } elseif ($row['Role'] === 'principal') {
                header('Location: admin/principalDash.php');
                exit();
            } elseif ($row['Role'] === 'student') {
                header('Location: student/studentPort.php');
                exit();
            } else {
                $error = 'Unknown role for this account.';
            }
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    } else {
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
    }

    .left-panel {
      background: url('img/bg.png') center center/cover no-repeat;
      color: white;
      position: relative;
      text-shadow: 0 2px 8px rgba(0,0,0,0.8);
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
    }

    .left-panel-content h1 {
      font-family: 'Merriweather', serif;
      font-size: 3rem;
      letter-spacing: 0.15em;
    }

    .left-panel-content p {
      font-family: 'Merriweather', serif;
      font-size: 1.5rem;
    }



  </style>
</head>
<body>

  <!-- Header -->
  <nav class="navbar navbar-light bg-white shadow-sm fixed-top ">
    <div class="container-fluid px-4 justify-content-center">

      <ul class="nav ">
        <li class="nav-item">
          <a class="nav-link text-dark" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="about.php">About</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="contact.php">Contact</a>
        </li>
      </ul>

    </div>
  </nav>

  <!-- Main Content -->
  <main class="container-fluid" >
    <div class="row vh-100">
      
      <!-- Left Side -->
      <div class="col-lg-7 d-none d-lg-flex align-items-center left-panel">
        <div class="left-panel-content w-100">
          <img src="img/logo.png" alt="School Logo" class="logo">
          <h1>BANAHIS</h1>
          <p>Balaytigue National High School</p>
        </div>
      </div>

      <!-- Right Side -->
      <div class="col-lg-5 d-flex align-items-center justify-content-center bg-white">
        <div class="w-100 p-4" style="max-width: 400px;">
          <h2 class="mb-4 fw-semibold">Login</h2>
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>
          <form id="login-form" method="POST" autocomplete="off">
            <div class="mb-3 input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
            </div>
            <div class="mb-3 input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
              <span class="input-group-text bg-white border-start-0">
                <i class="bi bi-eye-slash" id="togglePassword" role="button" tabindex="0" aria-label="Toggle password visibility"></i>
              </span>
            </div>
            <button type="submit" class="btn btn-success w-100">Login</button>
          </form>
        </div>
      </div>
    </div>
  </main>


  <!-- Bootstrap JS (optional but useful for interactivity) -->
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

 
  </script>
</body>
</html>
