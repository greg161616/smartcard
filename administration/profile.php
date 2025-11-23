<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Get current principal data - FIXED: changed 'user' to 'users'
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT a.FullName, a.Position, u.Email FROM admin a JOIN user u ON a.UserID = u.UserID WHERE u.UserID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$principal_data = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        
        // Validate inputs
        if (empty($full_name) || empty($email)) {
            $error = "All fields are required!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        } else {
            // Check if email already exists for other users
            $check_stmt = $conn->prepare("SELECT UserID FROM users WHERE email = ? AND UserID != ?");
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Email already exists! Please use a different email.";
            } else {
                // Update users table (email)
                $update_user_stmt = $conn->prepare("UPDATE users SET Email = ? WHERE UserID = ?");
                $update_user_stmt->bind_param("si", $email, $user_id);
                $update_user_stmt->execute();
                
                // Update admin table (full name)
                $update_admin_stmt = $conn->prepare("UPDATE admin SET FullName = ? WHERE UserID = ?");
                $update_admin_stmt->bind_param("si", $full_name, $user_id);
                $update_admin_stmt->execute();
                
                // Log the action
                $log_action = "PROFILE_UPDATE";
                $log_details = "Principal updated profile information";
                $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
                $log_stmt->bind_param("sis", $log_action, $user_id, $log_details);
                $log_stmt->execute();
                
                $success = "Profile updated successfully!";
                $principal_data['FullName'] = $full_name;
                $principal_data['Email'] = $email;
                
                // Update session data if needed
                $_SESSION['email'] = $email;
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required!";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long!";
        } else {
            // Get current user's password from database
            $stmt = $conn->prepare("SELECT Password FROM users WHERE UserID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && password_verify($current_password, $user['Password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success = "Password changed successfully!";
                    
                    // Log the action
                    $log_action = "PASSWORD_CHANGE";
                    $log_details = "Principal changed password";
                    $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
                    $log_stmt->bind_param("sis", $log_action, $user_id, $log_details);
                    $log_stmt->execute();
                } else {
                    $error = "Failed to change password. Please try again.";
                }
            } else {
                $error = "Current password is incorrect!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Profile</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .nav-pills .nav-link.active {
            background-color: #667eea;
        }
        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 2rem;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php include '../navs/headNav.php'; ?>
    
    <div class="container mt-4">
        <div class="profile-container"></div>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <ul class="nav nav-pills mb-0" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-profile" type="button" role="tab">
                        <i class="bi bi-person"></i> Profile Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-password-tab" data-bs-toggle="pill" data-bs-target="#pills-password" type="button" role="tab">
                        <i class="bi bi-key"></i> Change Password
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="pills-tabContent">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="pills-profile" role="tabpanel">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($principal_data['FullName'] ?? ''); ?>" required>
                                <div class="form-text">Your display name throughout the system</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email Address</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($principal_data['Email'] ?? ''); ?>" required>
                                <div class="form-text">This will be used for login and notifications</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Position</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($principal_data['Position'] ?? 'Principal'); ?>" readonly>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">
                                <i class="bi bi-info-circle"></i> Last updated: <?php echo date('F j, Y'); ?>
                            </span>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Password Tab -->
                <div class="tab-pane fade" id="pills-password" role="tabpanel">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required placeholder="Enter your current password">
                            <div class="form-text">You must confirm your current password to make changes</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Password</label>
                            <input type="password" class="form-control" name="new_password" minlength="6" required placeholder="Enter new password (min. 6 characters)">
                            <div class="form-text">Password must be at least 6 characters long</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" minlength="6" required placeholder="Confirm your new password">
                            <div class="form-text">Re-enter your new password to confirm</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">
                                <i class="bi bi-shield-check"></i> Ensure your password is strong and unique
                            </span>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time password validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            function validatePasswords() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            if (newPassword && confirmPassword) {
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>