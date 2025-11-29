<?php 
session_start();
include '../config.php'; 
date_default_timezone_set('Asia/Manila');
// 1) Ensure teacher is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// 2) Lookup TeacherID
$teacherEmail = $_SESSION['email'];
$stmt = $conn->prepare("
    SELECT t.TeacherID, t.fName, t.lName, t.mName, t.surfix, t.gender, t.contact, t.address, t.status, u.Email, u.UserID
    FROM teacher t
    JOIN user u ON t.UserID = u.UserID
    WHERE u.Email = ?
");
$stmt->bind_param("s", $teacherEmail);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher not found.");
}

$message = '';
$message_type = '';

// Handle form submission for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $fName = trim($_POST['fName']);
        $lName = trim($_POST['lName']);
        $mName = trim($_POST['mName']);
        $surfix = trim($_POST['surfix']);
        $gender = $_POST['gender'];
        $contact = trim($_POST['contact']);
        $address = trim($_POST['address']);
        $email = trim($_POST['email']);
        
        // Update teacher information
        $updateStmt = $conn->prepare("
            UPDATE teacher 
            SET fName = ?, lName = ?, mName = ?, surfix = ?, gender = ?, contact = ?, address = ?
            WHERE TeacherID = ?
        ");
        $updateStmt->bind_param("sssssssi", $fName, $lName, $mName, $surfix, $gender, $contact, $address, $teacher['TeacherID']);
        
        // Update user email if changed
        if ($email !== $teacher['Email']) {
            $emailStmt = $conn->prepare("UPDATE user SET Email = ? WHERE UserID = ?");
            $emailStmt->bind_param("si", $email, $teacher['UserID']);
        }
        
        if ($updateStmt->execute()) {
            if (isset($emailStmt)) {
                $emailStmt->execute();
                $emailStmt->close();
            }
            
            $message = "Profile updated successfully!";
            $message_type = "success";
            
            // Refresh teacher data
            $stmt = $conn->prepare("
                SELECT t.TeacherID, t.fName, t.lName, t.mName, t.surfix, t.gender, t.contact, t.address, t.status, u.Email, u.UserID
                FROM teacher t
                JOIN user u ON t.UserID = u.UserID
                WHERE t.TeacherID = ?
            ");
            $stmt->bind_param("i", $teacher['TeacherID']);
            $stmt->execute();
            $result = $stmt->get_result();
            $teacher = $result->fetch_assoc();
            $stmt->close();
        } else {
            $message = "Error updating profile: " . $conn->error;
            $message_type = "error";
        }
        
        $updateStmt->close();
    }
    
    if (isset($_POST['change_password'])) {
        // Change password
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT Password FROM user WHERE UserID = ?");
        $stmt->bind_param("i", $teacher['UserID']);
        $stmt->execute();
        $stmt->bind_result($hashedPassword);
        $stmt->fetch();
        $stmt->close();
        
        if (password_verify($currentPassword, $hashedPassword)) {
            if ($newPassword === $confirmPassword) {
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $passwordStmt = $conn->prepare("UPDATE user SET Password = ? WHERE UserID = ?");
                $passwordStmt->bind_param("si", $newHashedPassword, $teacher['UserID']);
                
                if ($passwordStmt->execute()) {
                    $message = "Password changed successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error changing password: " . $conn->error;
                    $message_type = "error";
                }
                $passwordStmt->close();
            } else {
                $message = "New passwords do not match!";
                $message_type = "error";
            }
        } else {
            $message = "Current password is incorrect!";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .form-control:focus {
            border-color: #6c757d;
            box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
        }
        .btn-primary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-primary:hover {
            background-color: #495057;
            border-color: #495057;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background-color: #6c757d;
            color: white;
            font-size: 24px;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>
    
    <div class="profile-header py-5 mb-4">
        <div class="container-fluid">
            <h2 class="mb-1">Teacher Profile</h2>
            <p class="mb-0">Manage your personal information and credentials</p>
        </div>
    </div>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Profile Information Card -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="fName" name="fName" 
                                           value="<?php echo htmlspecialchars($teacher['fName'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="lName" name="lName" 
                                           value="<?php echo htmlspecialchars($teacher['lName'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="mName" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="mName" name="mName" 
                                           value="<?php echo htmlspecialchars($teacher['mName'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="surfix" class="form-label">Suffix</label>
                                    <input type="text" class="form-control" id="surfix" name="surfix" 
                                           value="<?php echo htmlspecialchars($teacher['surfix'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-control" id="gender" name="gender">
                                        <option value="Male" <?php echo ($teacher['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($teacher['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contact" class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="contact" name="contact" 
                                           value="<?php echo htmlspecialchars($teacher['contact'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($teacher['Email'] ?? ''); ?>" required>
                            </div>
                            
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                                Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Profile Summary Card -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">Profile Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="profile-avatar">
                            <?php 
                            $firstName = $teacher['fName'] ?? '';
                            $lastName = $teacher['lName'] ?? '';
                            echo strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)); 
                            ?>
                        </div>
                        <h5 class="text-center"><?php echo htmlspecialchars($teacher['fName'] . ' ' . $teacher['lName']); ?></h5>
                        <div class="small mt-3">
                            <p><strong>Teacher ID:</strong> <?php echo htmlspecialchars($teacher['TeacherID']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo ($teacher['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($teacher['status'] ?? 'active'); ?>
                                </span>
                            </p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['Email']); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($teacher['contact'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                </div>

                                <!-- Change Password Card -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Current Password *</label>
                                <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                           
                            
                                    <label for="newPassword" class="form-label">New Password *</label>
                                    <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                               
                                    <label for="confirmPassword" class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                </div>
                                                            
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                Change Password
                            </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Profile Confirmation Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateProfileModalLabel">Confirm Profile Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to update your profile information?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('profileForm').submit();">Yes, Update Profile</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Confirmation Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Confirm Password Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to change your password?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="validatePasswordForm()">Yes, Change Password</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validatePasswordForm() {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                alert('Please fill in all password fields!');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                document.getElementById('confirmPassword').focus();
                return;
            }
            
            if (newPassword.length < 6) {
                alert('New password must be at least 6 characters long!');
                document.getElementById('newPassword').focus();
                return;
            }
            
            document.getElementById('passwordForm').submit();
        }
    </script>
</body>
</html>