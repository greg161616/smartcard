<?php
session_start();
include('../config.php'); // Database connection

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Adjust path as needed

// Ensure only admin is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX email check request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_email') {
    if (isset($_GET['email'])) {
        $email = $_GET['email'];
        $query = "SELECT UserID FROM user WHERE Email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            echo 'taken';
        } else {
            echo 'available';
        }
        exit;
    }
}

// Define position options
$positionOptions = [
    'Head Teacher',
    'Assistant Head Teacher',
    'LIS Coordinator',
    
];

if (isset($_POST['submit'])) {
    $username = $_POST['username'];
    $plainPassword = $_POST['password']; // Store plain password for email
    $password = password_hash($plainPassword, PASSWORD_DEFAULT); // Hash the password
    $fullName = $_POST['fullName'];
    $position = $_POST['position'];

    // Insert the new teacher into the user table
    $query = "INSERT INTO user (Email, Password, Role, CreatedAt) VALUES (?, ?, 'head', NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $username, $password);
    if ($stmt->execute()) {
        $userID = $stmt->insert_id; // Get the inserted UserID

        // Insert into the admin table (assuming a head teacher is an admin-level teacher)
        $adminQuery = "INSERT INTO admin (UserID, FullName, Position) VALUES (?, ?, ?)";
        $adminStmt = $conn->prepare($adminQuery);
        $adminStmt->bind_param("iss", $userID, $fullName, $position);
        
        if ($adminStmt->execute()) {
            // Send email with credentials
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // Set your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'banahis2008@gmail.com'; // SMTP username
                $mail->Password   = 'ehjc vdej avxu kryb'; // SMTP password (use App Password for Gmail)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('banahis2008@gmail.com', 'School Principal');
                $mail->addAddress($username, $fullName);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Account Credentials';
                $mail->Body    = "
                    <h2>Welcome to School Management System</h2>
                    <p>Hello $fullName,</p>
                    <p>Your account has been created successfully.</p>
                    <p><strong>Account Details:</strong></p>
                    <ul>
                        <li><strong>Email:</strong> $username</li>
                        <li><strong>Password:</strong> $plainPassword</li>
                        <li><strong>Position:</strong> $position</li>
                    </ul>
                    <p>Please log in and change your password after first login.</p>
                    <p>Login URL: <a href='http://yourschool.edu/login.php'>http://yourschool.edu/login.php</a></p>
                    <br>
                    <p>Best regards,<br>School Principal</p>
                ";

                $mail->AltBody = "Welcome to School Management System\n\nHello $fullName,\n\nYour teacher account has been created successfully.\n\nAccount Details:\nEmail: $username\nPassword: $plainPassword\nPosition: $position\n\nPlease log in and change your password after first login.\n\nLogin URL: http://yourschool.edu/login.php\n\nBest regards,\nSchool Principal";

                $mail->send();
                $successMessage = "<div class='alert alert-success'>Head teacher account created successfully. Credentials have been sent to $username.</div>";
            } catch (Exception $e) {
                $successMessage = "<div class='alert alert-warning'>Head teacher account created successfully, but email could not be sent. Error: {$mail->ErrorInfo}</div>";
            }
        } else {
            $errorMessage = "<div class='alert alert-danger'>Error creating teacher profile. Please try again.</div>";
        }
    } else {
        $errorMessage = "<div class='alert alert-danger'>Error creating user account. The email might already be registered.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANAHIS | Create Account</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            background-image: linear-gradient(to right, #f0f4ff, #e6f7ff);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .header-icon {
            font-size: 2.5rem;
            color: #0d6efd;
        }
        .password-toggle {
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(to right, #0d6efd, #0a58ca);
            border: none;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #0a58ca, #084298);
        }
        .email-status {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .email-available {
            color: #198754;
        }
        .email-taken {
            color: #dc3545;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../navs/adminNav.php'; ?>
    
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="text-center mb-3">
                    <i class="bi bi-person-plus-fill header-icon"></i>
                    <h2 class="mt-2">Create Teacher Admin Account</h2>
                    <p class="text-muted">Add a new teacher admin to the system</p>
                </div>
                
                <?php 
                if (isset($successMessage)) echo $successMessage;
                if (isset($errorMessage)) echo $errorMessage;
                ?>
                
                <div class="card p-4">
                    <form method="POST" action="" id="teacherForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="bi bi-envelope me-2"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="username" name="username" required 
                                placeholder="teacher@school.edu" autocomplete="off">
                            <div class="email-status" id="emailStatus"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-key me-2"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required 
                                    placeholder="Create a strong password">
                                <span class="input-group-text password-toggle" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <div class="form-text">Use at least 8 characters with a mix of letters, numbers and symbols</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fullName" class="form-label">
                                <i class="bi bi-person me-2"></i>Full Name
                            </label>
                            <input type="text" class="form-control" id="fullName" name="fullName" required 
                                placeholder="Enter full name">
                        </div>
                        
                        <div class="mb-4">
                            <label for="position" class="form-label">
                                <i class="bi bi-briefcase me-2"></i>Position
                            </label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="" selected disabled>Select a position</option>
                                <?php foreach ($positionOptions as $option): ?>
                                    <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Email availability check with AJAX
        const emailInput = document.getElementById('username');
        const emailStatus = document.getElementById('emailStatus');
        const submitBtn = document.getElementById('submitBtn');
        let emailCheckTimeout = null;
        let isEmailAvailable = false;

        emailInput.addEventListener('input', function() {
            clearTimeout(emailCheckTimeout);
            const email = this.value.trim();
            
            // Basic email validation
            if (!email || !email.includes('@')) {
                emailStatus.innerHTML = '';
                isEmailAvailable = false;
                updateSubmitButton();
                return;
            }
            
            // Show loading indicator
            emailStatus.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Checking availability...';
            emailStatus.className = 'email-status';
            
            // Set a timeout to avoid too many requests
            emailCheckTimeout = setTimeout(() => {
                checkEmailAvailability(email);
            }, 800);
        });

        function checkEmailAvailability(email) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `?ajax=check_email&email=${encodeURIComponent(email)}`, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        if (xhr.responseText === 'available') {
                            emailStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> Email is available';
                            emailStatus.className = 'email-status email-available';
                            isEmailAvailable = true;
                        } else {
                            emailStatus.innerHTML = '<i class="bi bi-x-circle-fill"></i> Email is already taken';
                            emailStatus.className = 'email-status email-taken';
                            isEmailAvailable = false;
                        }
                        updateSubmitButton();
                    } else {
                        emailStatus.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Error checking email';
                        emailStatus.className = 'email-status email-taken';
                        isEmailAvailable = false;
                        updateSubmitButton();
                    }
                }
            };
            xhr.send();
        }

        function updateSubmitButton() {
            if (isEmailAvailable) {
                submitBtn.disabled = false;
                submitBtn.title = '';
            } else {
                submitBtn.disabled = true;
                submitBtn.title = 'Please fix the email issues before submitting';
            }
        }

        // Form validation before submission
        document.getElementById('teacherForm').addEventListener('submit', function(e) {
            if (!isEmailAvailable) {
                e.preventDefault();
                alert('Please use a valid and available email address');
                emailInput.focus();
            }
        });
    </script>
</body>
</html>