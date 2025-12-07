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

// Get profile picture
$profilePicturePath = '../img/default.jpg';
$picStmt = $conn->prepare("SELECT path FROM profile_picture WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
$picStmt->bind_param("i", $user_id);
$picStmt->execute();
$picResult = $picStmt->get_result();
if ($picResult->num_rows > 0) {
    $profilePicture = $picResult->fetch_assoc();
    $profilePicturePath = $profilePicture['path'];
}
$picStmt->close();

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
            $check_stmt = $conn->prepare("SELECT UserID FROM user WHERE email = ? AND UserID != ?");
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Email already exists! Please use a different email.";
            } else {
                // Update users table (email)
                $update_user_stmt = $conn->prepare("UPDATE user SET Email = ? WHERE UserID = ?");
                $update_user_stmt->bind_param("si", $email, $user_id);
                $update_user_stmt->execute();
                
                // Update admin table (full name)
                $update_admin_stmt = $conn->prepare("UPDATE admin SET FullName = ? WHERE UserID = ?");
                $update_admin_stmt->bind_param("si", $full_name, $user_id);
                $update_admin_stmt->execute();
                
                // Handle profile picture upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    $maxFileSize = 5 * 1024 * 1024; // 5MB
                    
                    $fileType = $_FILES['profile_picture']['type'];
                    $fileSize = $_FILES['profile_picture']['size'];
                    
                    if (in_array($fileType, $allowedTypes) && $fileSize <= $maxFileSize) {
                        // Create upload directory if it doesn't exist
                        $uploadDir = '../uploads/profile_pictures/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                        $fileName = 'principal_' . $user_id . '_' . time() . '.' . $fileExtension;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                            // Check if profile picture exists
                            $check_pic_stmt = $conn->prepare("SELECT profile_id FROM profile_picture WHERE user_id = ?");
                            $check_pic_stmt->bind_param("i", $user_id);
                            $check_pic_stmt->execute();
                            $pic_result = $check_pic_stmt->get_result();
                            
                            if ($pic_result->num_rows > 0) {
                                // Update existing
                                $update_pic_stmt = $conn->prepare("UPDATE profile_picture SET path = ?, uploaded_at = NOW() WHERE user_id = ?");
                                $update_pic_stmt->bind_param("si", $filePath, $user_id);
                                $update_pic_stmt->execute();
                                $update_pic_stmt->close();
                            } else {
                                // Insert new
                                $insert_pic_stmt = $conn->prepare("INSERT INTO profile_picture (user_id, email, path, uploaded_at) VALUES (?, ?, ?, NOW())");
                                $insert_pic_stmt->bind_param("iss", $user_id, $email, $filePath);
                                $insert_pic_stmt->execute();
                                $insert_pic_stmt->close();
                            }
                            $check_pic_stmt->close();
                            $profilePicturePath = $filePath;
                        }
                    }
                }
                
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
            $stmt = $conn->prepare("SELECT Password FROM user WHERE UserID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && password_verify($current_password, $user['Password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE user SET Password = ? WHERE UserID = ?");
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
    
    // Handle profile picture removal
    if (isset($_POST['remove_profile_picture'])) {
        $deleteStmt = $conn->prepare("DELETE FROM profile_picture WHERE user_id = ?");
        $deleteStmt->bind_param("i", $user_id);
        
        if ($deleteStmt->execute()) {
            // Delete the physical file
            if ($profilePicturePath && file_exists($profilePicturePath) && $profilePicturePath !== '../img/default.jpg') {
                unlink($profilePicturePath);
            }
            $profilePicturePath = '../img/default.jpg';
            $success = "Profile picture removed successfully!";
        } else {
            $error = "Error removing profile picture: " . $conn->error;
        }
        $deleteStmt->close();
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
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            margin: 0 auto 20px;
            display: block;
        }
        .profile-picture-actions {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../navs/headNav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="profile-container">
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
                    <!-- Profile Picture Section -->
                    <div class="text-center mb-4">
                        <?php if ($profilePicturePath): ?>
                            <img src="<?php echo htmlspecialchars($profilePicturePath); ?>" alt="Profile Picture" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                                <i class="bi bi-person" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="profile-picture-actions">
                            <?php if ($profilePicturePath && $profilePicturePath !== '../img/default.jpg'): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="remove_profile_picture" value="1">
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#removePictureModal">
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_picture" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif">
                                <div class="form-text">Optional: JPEG, JPG, PNG, GIF. Max 5MB</div>
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

    <!-- Remove Picture Confirmation Modal -->
    <div class="modal fade" id="removePictureModal" tabindex="-1" aria-labelledby="removePictureModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removePictureModalLabel">Remove Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to remove your profile picture?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="remove_profile_picture" value="1">
                        <button type="submit" class="btn btn-danger">Yes, Remove Picture</button>
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
                if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else if (confirmPassword) {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            if (newPassword && confirmPassword) {
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
            
            // File size validation
            const profilePictureInput = document.querySelector('input[name="profile_picture"]');
            if (profilePictureInput) {
                profilePictureInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const maxSize = 5 * 1024 * 1024; // 5MB
                        if (file.size > maxSize) {
                            alert('File size exceeds 5MB. Please choose a smaller file.');
                            this.value = '';
                        }
                    }
                });
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