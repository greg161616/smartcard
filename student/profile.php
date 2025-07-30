<?php
session_start();
include '../config.php'; // Database configuration

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Validate inputs
    if (empty($currentPassword)) {
        $error = "Current password is required";
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = "New password and confirmation are required";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } else {
        // Fetch current password hash from database
        $email = $_SESSION['email'];
        $stmt = $conn->prepare("SELECT Password FROM user WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($currentPassword, $user['Password'])) {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password in database
            $updateStmt = $conn->prepare("UPDATE user SET Password = ? WHERE Email = ?");
            $updateStmt->bind_param("ss", $hashedPassword, $email);
            
            if ($updateStmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Error updating password: " . $conn->error;
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
}

// Fetch student data
$studentData = [];
$email = $_SESSION['email'];

$userQuery = $conn->prepare("SELECT u.UserID, u.Email, s.* 
                           FROM user u 
                           JOIN student s ON u.UserID = s.userID 
                           WHERE u.Email = ?");
$userQuery->bind_param("s", $email);
$userQuery->execute();
$result = $userQuery->get_result();
$studentData = $result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Settings | Balaytigue National High School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --dark-gray: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --sidebar-width: 320px;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        
        /* Main container */
        .settings-container {
            display: flex;
            max-width: 1400px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        /* Fixed sidebar */
        .settings-sidebar {
            width: var(--sidebar-width);
            background: var(--secondary-color);
            color: white;
            padding: 25px 0;
            position: fixed;
            height: calc(100vh - 60px); 
        }
        
        .settings-sidebar .nav-link {
            padding: 12px 25px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .settings-sidebar .nav-link:hover, 
        .settings-sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--primary-color);
            color: white !important;
        }
        
        .settings-sidebar .nav-link i {
            font-size: 1.1rem;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }
        
        .settings-sidebar .nav-link p.description {
            font-size: 0.85rem;
            opacity: 0.7;
            margin: 5px 0 0 0;
            line-height: 1.4;
        }
        
        /* Content area */
        .settings-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            margin-left: var(--sidebar-width);
        }
        
        .settings-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--medium-gray);
        }
        
        .section-header {
            border-bottom: 1px solid var(--medium-gray);
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .section-header i {
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        .avatar-container {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--medium-gray);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: var(--secondary-color);
        }
        
        .password-form .form-label {
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            background: var(--danger-color);
            transition: width 0.3s;
        }
        
        .password-rules {
            font-size: 0.85rem;
            color: var(--dark-gray);
            margin-top: 10px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 8px 20px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        /* Responsive design */
        @media (max-width: 992px) {
            .settings-container {
                flex-direction: column;
            }
            
            .settings-sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }
            
            .settings-content {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-content {
                padding: 20px 15px;
            }
            
            .settings-section {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
<?php include '../navs/studentNav.php'?>
    
    <!-- Settings Container -->
    <div class="settings-container">
        <!-- Fixed Sidebar -->
        <div class="settings-sidebar">
            <h4 class="px-4 mb-4">Settings</h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="#account" data-bs-toggle="tab">
                        <i class="fas fa-user"></i> 
                        <div>
                            <div>Account</div>
                            <p class="description m-0">Manage your profile information</p>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#security" data-bs-toggle="tab">
                        <i class="fas fa-lock"></i> 
                        <div>
                            <div>Security</div>
                            <p class="description m-0">Change your password</p>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#additional" data-bs-toggle="tab">
                        <i class="fas fa-info-circle"></i> 
                        <div>
                            <div>Student Information</div>
                            <p class="description m-0">Additional records</p>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Content Area -->
        <div class="settings-content tab-content">
            <!-- Account Tab -->
            <div class="tab-pane fade show active" id="account">
                <h2 class="mb-4">Account Settings</h2>
                
                <div class="settings-section">
                    <div class="section-header">
                        <i class="fas fa-user-circle"></i>
                        <h4 class="m-0">Profile Information</h4>
                    </div>
                    
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">First Name</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['FirstName'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Middle Name</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['MiddleName'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Last Name</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['LastName'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['Email'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Birthdate</div>
                            <div class="info-value">
                                <?= $studentData['Birthdate'] ? date('F j, Y', strtotime($studentData['Birthdate'])) : 'Not specified' ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Student ID</div>
                            <div class="info-value">
                                STD-<?= htmlspecialchars($studentData['StudentID'] ?? '0000') ?>
                            </div>
                        </div>
                    </div>
                    
                  <!--  <div class="mt-4 text-end">
                        <button class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Edit Profile
                        </button>
                    </div> -->
                </div>
            </div> 
            
            <!-- Security Tab -->
            <div class="tab-pane fade" id="security">
                <h2 class="mb-4">Security Settings</h2>
                
                <div class="settings-section">
                    <div class="section-header">
                        <i class="fas fa-lock"></i>
                        <h4 class="m-0">Password</h4>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        For security, your password should be at least 8 characters and include a mix of letters, numbers, and symbols.
                    </div>
                    
                    <form method="post" class="password-form">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-4">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="newPassword" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength mt-2">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="password-rules">
                                <div><i class="fas fa-check-circle text-success me-1"></i> At least 8 characters</div>
                                <div><i class="fas fa-times-circle text-danger me-1"></i> Include a number</div>
                                <div><i class="fas fa-times-circle text-danger me-1"></i> Include a special character</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-2 small"></div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-key me-1"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Additional Info Tab -->
            <div class="tab-pane fade" id="additional">
                <h2 class="mb-4">Student Information</h2>
                
                <div class="settings-section">
                    <div class="section-header">
                        <i class="fas fa-address-card"></i>
                        <h4 class="m-0">Contact Information</h4>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['Address'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['contactNumber'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Parents' Contact</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['ParentsContact'] ?? 'Not specified') ?>
                            </div>
                        </div>
                    </div>
                    
                  <!--  <div class="mt-4 text-end">
                        <button class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Update Contact Info
                        </button>
                    </div> -->
                </div>
                
                <div class="settings-section">
                    <div class="section-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h4 class="m-0">Academic Information</h4>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">LRN</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['LRN'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Gender</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['Gender'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Grade Level</div>
                            <div class="info-value">
                                Grade <?= htmlspecialchars($studentData['GradeLevel'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Section</div>
                            <div class="info-value">
                                <?= htmlspecialchars($studentData['Section'] ?? 'Not specified') ?>
                            </div>
                        </div>
                        
                        
                        <div class="info-item">
                            <div class="info-label">Enrollment Status</div>
                            <div class="info-value">
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
       
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.btn-outline-secondary').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        });

        // Password strength meter
        const passwordInput = document.getElementById('newPassword');
        const passwordStrengthBar = document.getElementById('passwordStrengthBar');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 15;
            
            // Character variety
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[a-z]/.test(password)) strength += 15;
            if (/\d/.test(password)) strength += 15;
            if (/[^A-Za-z0-9]/.test(password)) strength += 15;
            
            // Update the bar
            passwordStrengthBar.style.width = Math.min(strength, 100) + '%';
            
            // Update color based on strength
            if (strength < 40) {
                passwordStrengthBar.style.backgroundColor = '#dc3545'; // red
            } else if (strength < 70) {
                passwordStrengthBar.style.backgroundColor = '#ffc107'; // yellow
            } else {
                passwordStrengthBar.style.backgroundColor = '#28a745'; // green
            }
        });

        // Password match validation
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordMatch = document.getElementById('passwordMatch');
        
        confirmPasswordInput.addEventListener('input', function() {
            const newPassword = passwordInput.value;
            const confirmPassword = this.value;
            
            if (newPassword === '' && confirmPassword === '') {
                passwordMatch.textContent = '';
                passwordMatch.className = 'mt-2 small';
            } else if (newPassword === confirmPassword) {
                passwordMatch.textContent = 'Passwords match!';
                passwordMatch.className = 'mt-2 small text-success';
            } else {
                passwordMatch.textContent = 'Passwords do not match!';
                passwordMatch.className = 'mt-2 small text-danger';
            }
        });

        // Tab activation based on URL hash
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash) {
                const tabTrigger = new bootstrap.Tab(document.querySelector(`a[href="${window.location.hash}"]`));
                tabTrigger.show();
            }
        });
    </script>
</body>
</html>