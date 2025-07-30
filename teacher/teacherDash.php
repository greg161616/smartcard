<?php
session_start();
// Check if user is logged in and is a teacher
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

require_once '../config.php'; // Database connection

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Balaytigue National High School</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
    <style>
        body {
            background: #f8f9fa;
        }
        .main-content {
            padding: 40px 20px;
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>

    <div class="main-content">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>!</h1>
                    <h3>This is your teacher dashboard.</h3>
                </div>
            </div>
        </div>
    </div>

 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

</script>

</body>
</html>
