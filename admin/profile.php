<?php
session_start();
include '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Get admin profile picture for nav
$adminProfilePicturePath = '../img/default.jpg';
if (isset($_SESSION['user_id'])) {
    $adminID = $_SESSION['user_id'];
    $picStmt = $conn->prepare("SELECT path FROM profile_picture WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $picStmt->bind_param("i", $adminID);
    $picStmt->execute();
    $picResult = $picStmt->get_result();
    if ($picResult->num_rows > 0) {
        $profilePicture = $picResult->fetch_assoc();
        $adminProfilePicturePath = $profilePicture['path'];
    }
    $picStmt->close();
}

$error = '';
$success = '';

// Handle Add Principal Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_principal'])) {
    $full_name = trim($_POST['full_name']);
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_password = $_POST['admin_password'];
    
    // Validate inputs
    if (empty($full_name) || empty($position) || empty($email) || empty($password) || empty($confirm_password) || empty($admin_password)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Verify admin password
        $admin_id = $_SESSION['user_id'];
        $check_admin_stmt = $conn->prepare("SELECT Password FROM user WHERE UserID = ?");
        $check_admin_stmt->bind_param("i", $admin_id);
        $check_admin_stmt->execute();
        $admin_result = $check_admin_stmt->get_result();
        $admin_data = $admin_result->fetch_assoc();
        
        if (!$admin_data || !password_verify($admin_password, $admin_data['Password'])) {
            $error = "Admin password is incorrect!";
        } else {
            // Check if email already exists
            $check_email_stmt = $conn->prepare("SELECT UserID FROM user WHERE Email = ?");
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $email_result = $check_email_stmt->get_result();
            
            if ($email_result->num_rows > 0) {
                $error = "Email already exists! Please use a different email.";
            } else {
                $conn->begin_transaction();
                
                try {
                    // Set current principal to inactive
                    $deactivate_stmt = $conn->prepare("UPDATE admin SET status = 'inactive' WHERE Position IN ('Head teacher', 'Principal') AND status = 'active'");
                    $deactivate_stmt->execute();
                    
                    // Create user account
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'principal';
                    
                    $user_stmt = $conn->prepare("INSERT INTO user (Email, Password, Role, CreatedAt) VALUES (?, ?, ?, NOW())");
                    $user_stmt->bind_param("sss", $email, $hashed_password, $role);
                    $user_stmt->execute();
                    
                    $new_user_id = $conn->insert_id;
                    
                    // Create admin record
                    $admin_stmt = $conn->prepare("INSERT INTO admin (UserID, FullName, Position, status) VALUES (?, ?, ?, 'active')");
                    $admin_stmt->bind_param("iss", $new_user_id, $full_name, $position);
                    $admin_stmt->execute();
                    
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
                            $fileName = 'principal_' . $new_user_id . '_' . time() . '.' . $fileExtension;
                            $filePath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
                                // Insert profile picture record
                                $pictureStmt = $conn->prepare("INSERT INTO profile_picture (user_id, email, path, uploaded_at) VALUES (?, ?, ?, NOW())");
                                $pictureStmt->bind_param("iss", $new_user_id, $email, $filePath);
                                $pictureStmt->execute();
                                $pictureStmt->close();
                            }
                        }
                    }
                    
                    // Log the action
                    $log_action = "PRINCIPAL_ADDED";
                    $log_details = "New principal added: " . $full_name;
                    $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
                    $log_stmt->bind_param("sis", $log_action, $admin_id, $log_details);
                    $log_stmt->execute();
                    
                    $conn->commit();
                    $success = "New principal added successfully! Previous principal has been set to inactive.";
                    
                    // Clear form
                    $_POST = array();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to add principal: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Update Principal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_principal'])) {
    $admin_id = intval($_POST['admin_id']);
    $full_name = trim($_POST['full_name']);
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    $admin_password = $_POST['admin_password'];
    
    // Validate inputs
    if (empty($full_name) || empty($position) || empty($email) || empty($admin_password)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else {
        // Verify admin password
        $current_admin_id = $_SESSION['user_id'];
        $check_admin_stmt = $conn->prepare("SELECT Password FROM user WHERE UserID = ?");
        $check_admin_stmt->bind_param("i", $current_admin_id);
        $check_admin_stmt->execute();
        $admin_result = $check_admin_stmt->get_result();
        $admin_data = $admin_result->fetch_assoc();
        
        if (!$admin_data || !password_verify($admin_password, $admin_data['Password'])) {
            $error = "Admin password is incorrect!";
        } else {
            $conn->begin_transaction();
            
            try {
                // Get user ID for this admin
                $get_user_stmt = $conn->prepare("SELECT UserID FROM admin WHERE AdminID = ?");
                $get_user_stmt->bind_param("i", $admin_id);
                $get_user_stmt->execute();
                $user_result = $get_user_stmt->get_result();
                $admin_data = $user_result->fetch_assoc();
                $user_id = $admin_data['UserID'];
                
                // Update admin record
                $update_stmt = $conn->prepare("UPDATE admin SET FullName = ?, Position = ? WHERE AdminID = ?");
                $update_stmt->bind_param("ssi", $full_name, $position, $admin_id);
                $update_stmt->execute();
                
                // Update user email
                $email_stmt = $conn->prepare("UPDATE user SET Email = ? WHERE UserID = ?");
                $email_stmt->bind_param("si", $email, $user_id);
                $email_stmt->execute();
                
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
                        }
                    }
                }
                
                // Log the action
                $log_action = "PRINCIPAL_UPDATED";
                $log_details = "Principal updated: " . $full_name;
                $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
                $log_stmt->bind_param("sis", $log_action, $current_admin_id, $log_details);
                $log_stmt->execute();
                
                $conn->commit();
                $success = "Principal updated successfully!";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to update principal: " . $e->getMessage();
            }
        }
    }
}

// Handle Activate Principal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_principal'])) {
    $admin_id = intval($_POST['admin_id']);
    $admin_user_id = $_SESSION['user_id'];
    
    $conn->begin_transaction();
    
    try {
        // Set all principals to inactive
        $deactivate_stmt = $conn->prepare("UPDATE admin SET status = 'inactive' WHERE Position IN ('Head teacher', 'Principal')");
        $deactivate_stmt->execute();
        
        // Set selected principal to active
        $activate_stmt = $conn->prepare("UPDATE admin SET status = 'active' WHERE AdminID = ?");
        $activate_stmt->bind_param("i", $admin_id);
        $activate_stmt->execute();
        
        // Log the action
        $log_action = "PRINCIPAL_ACTIVATED";
        $log_details = "Principal activated with ID: " . $admin_id;
        $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
        $log_stmt->bind_param("sis", $log_action, $admin_user_id, $log_details);
        $log_stmt->execute();
        
        $conn->commit();
        $success = "Principal activated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to activate principal: " . $e->getMessage();
    }
}

// Get current active principal with profile picture
$current_principal_stmt = $conn->prepare("
    SELECT a.AdminID, a.FullName, a.Position, u.Email, u.UserID, 
           (SELECT path FROM profile_picture WHERE user_id = u.UserID ORDER BY uploaded_at DESC LIMIT 1) as profile_picture
    FROM admin a 
    JOIN user u ON a.UserID = u.UserID 
    WHERE a.status = 'active' AND a.Position IN ('Head teacher', 'Principal')
");
$current_principal_stmt->execute();
$current_principal_result = $current_principal_stmt->get_result();
$current_principal = $current_principal_result->fetch_assoc();

// Get all principals with profile pictures
$principals_stmt = $conn->prepare("
    SELECT a.AdminID, a.FullName, a.Position, a.status, u.Email, u.CreatedAt, u.UserID,
           (SELECT path FROM profile_picture WHERE user_id = u.UserID ORDER BY uploaded_at DESC LIMIT 1) as profile_picture
    FROM admin a 
    JOIN user u ON a.UserID = u.UserID 
    WHERE a.Position IN ('Head teacher', 'Principal')
    ORDER BY a.status DESC, a.AdminID DESC
");
$principals_stmt->execute();
$principals_result = $principals_stmt->get_result();
$principals = $principals_result->fetch_all(MYSQLI_ASSOC);

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'manage';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Management</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .current-principal-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .required::after {
            content: " *";
            color: red;
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
        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
        }
        .profile-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        .current-principal-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            margin-right: 20px;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .edit-btn {
            margin-left: 5px;
        }
        .modal-profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dee2e6;
            margin: 0 auto 15px;
            display: block;
        }
    </style>
</head>
<body>
    <?php 
    // Include the admin nav with profile picture
    include '../navs/adminNav.php'; 
    ?>
    
    <div class="container mt-4">
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'manage' ? 'active' : ''; ?>" 
                        id="pills-manage-tab" data-bs-toggle="pill" data-bs-target="#pills-manage" type="button" role="tab">
                    <i class="bi bi-list"></i> Manage Principals
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'add' ? 'active' : ''; ?>" 
                        id="pills-add-tab" data-bs-toggle="pill" data-bs-target="#pills-add" type="button" role="tab">
                    <i class="bi bi-person-plus"></i> Add New Principal
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="pills-tabContent">
            <!-- Manage Principals Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'manage' ? 'show active' : ''; ?>" 
                 id="pills-manage" role="tabpanel">
                
                <?php if (empty($principals)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person-x display-1 text-muted"></i>
                        <h5 class="mt-3">No principals found</h5>
                        <p class="text-muted">Get started by adding your first principal.</p>
                        <button type="button" class="btn btn-primary" onclick="switchToAddTab()">
                            Add Principal
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Current Principal Info -->
                    <?php if ($current_principal): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <?php if ($current_principal['profile_picture']): ?>
                                    <img src="<?php echo htmlspecialchars($current_principal['profile_picture']); ?>" 
                                         alt="Profile Picture" class="current-principal-avatar">
                                <?php else: ?>
                                    <div class="current-principal-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                                        <i class="bi bi-person" style="font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1"><i class="bi bi-person-check"></i> Current Active Principal</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <strong>Name:</strong> <?php echo htmlspecialchars($current_principal['FullName']); ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Position:</strong> <?php echo htmlspecialchars($current_principal['Position']); ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($current_principal['Email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPrincipalModal"
                                                onclick="loadPrincipalData(<?php echo $current_principal['AdminID']; ?>, '<?php echo htmlspecialchars($current_principal['FullName']); ?>', '<?php echo htmlspecialchars($current_principal['Position']); ?>', '<?php echo htmlspecialchars($current_principal['Email']); ?>', '<?php echo $current_principal['profile_picture'] ? htmlspecialchars($current_principal['profile_picture']) : ''; ?>')">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Principals List -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-list"></i> Principals List</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Profile</th>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($principals as $principal): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($principal['profile_picture']): ?>
                                                        <img src="<?php echo htmlspecialchars($principal['profile_picture']); ?>" 
                                                             alt="Profile Picture" class="profile-avatar-small">
                                                    <?php else: ?>
                                                        <div class="profile-avatar-small bg-secondary d-flex align-items-center justify-content-center text-white">
                                                            <i class="bi bi-person"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($principal['FullName']); ?></td>
                                                <td><?php echo htmlspecialchars($principal['Position']); ?></td>
                                                <td><?php echo htmlspecialchars($principal['Email']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $principal['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo ucfirst($principal['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($principal['CreatedAt'])); ?></td>
                                                <td>
                                                    <?php if ($principal['status'] === 'inactive'): ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="admin_id" value="<?php echo $principal['AdminID']; ?>">
                                                            <button type="submit" name="activate_principal" class="btn btn-sm btn-success" 
                                                                    onclick="return confirm('Activate this principal? This will deactivate the current active principal.')">
                                                                <i class="bi bi-check-circle"></i> Activate
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle"></i> Active
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Principal Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'add' ? 'show active' : ''; ?>" 
                 id="pills-add" role="tabpanel">
                
                <!-- Current Principal Warning -->
                <?php if ($current_principal): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <?php if ($current_principal['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($current_principal['profile_picture']); ?>" 
                                     alt="Profile Picture" class="current-principal-avatar">
                            <?php else: ?>
                                <div class="current-principal-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                                    <i class="bi bi-person" style="font-size: 2rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h5 class="card-title mb-1"><i class="bi bi-person-check"></i> Current Active Principal</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($current_principal['FullName']); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Position:</strong> <?php echo htmlspecialchars($current_principal['Position']); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($current_principal['Email']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Add New Principal Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> New Principal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="addPrincipalForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                           required placeholder="Enter full name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Position</label>
                                    <select class="form-select" name="position" required>
                                        <option value="">Select Position</option>
                                        <option value="Head teacher" <?php echo (($_POST['position'] ?? '') === 'Head teacher') ? 'selected' : ''; ?>>Head Teacher</option>
                                        <option value="Principal" <?php echo (($_POST['position'] ?? '') === 'Principal') ? 'selected' : ''; ?>>Principal</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required placeholder="Enter email address">
                                    <div class="form-text">This will be used for login</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Profile Picture</label>
                                    <input type="file" class="form-control" name="profile_picture" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    <div class="form-text">Optional: JPEG, JPG, PNG, GIF. Max 5MB</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Password</label>
                                    <input type="password" class="form-control" name="password" 
                                           minlength="6" required placeholder="Enter password (min. 6 characters)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           minlength="6" required placeholder="Confirm password">
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Admin Password</label>
                                    <input type="password" class="form-control" name="admin_password" 
                                           required placeholder="Enter your admin password">
                                    <div class="form-text">For security verification</div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle"></i> Important Notice</h6>
                                <ul class="mb-0">
                                    <li>The current principal will be set to <strong>inactive</strong></li>
                                    <li>This action requires admin password verification</li>
                                    <li>The new principal will have full administrative access</li>
                                    <li>This action will be logged for security purposes</li>
                                </ul>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="submit" name="add_principal" class="btn btn-primary" 
                                        onclick="return confirmAddPrincipal()">
                                    <i class="bi bi-person-plus"></i> Add New Principal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Principal Modal -->
    <div class="modal fade" id="editPrincipalModal" tabindex="-1" aria-labelledby="editPrincipalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editPrincipalModalLabel">
                        <i class="bi bi-pencil"></i> Edit Principal Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editPrincipalForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="admin_id" id="edit_admin_id">
                        <input type="hidden" name="update_principal" value="1">
                        
                        <!-- Current Profile Picture -->
                        <div class="text-center mb-4">
                            <img id="current_profile_picture" src="" alt="Current Profile Picture" class="modal-profile-picture">
                            <div id="no_profile_picture" class="modal-profile-picture bg-secondary d-flex align-items-center justify-content-center text-white" style="display: none;">
                                <i class="bi bi-person" style="font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted mb-0">Current Profile Picture</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required fw-bold">Full Name</label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required fw-bold">Position</label>
                                <select class="form-select" id="edit_position" name="position" required>
                                    <option value="Head teacher">Head Teacher</option>
                                    <option value="Principal">Principal</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required fw-bold">Email Address</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                                <div class="form-text">This will be used for login</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Profile Picture</label>
                                <input type="file" class="form-control" name="profile_picture" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif">
                                <div class="form-text">Optional: JPEG, JPG, PNG, GIF. Max 5MB</div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required fw-bold">Admin Password</label>
                                <input type="password" class="form-control" name="admin_password" 
                                       required placeholder="Enter your admin password">
                                <div class="form-text">For security verification</div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Update Information</h6>
                            <ul class="mb-0">
                                <li>Upload a new profile picture only if you want to change the current one</li>
                                <li>This action requires admin password verification</li>
                                <li>This action will be logged for security purposes</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-circle"></i> Update Principal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchToAddTab() {
            const addTab = new bootstrap.Tab(document.getElementById('pills-add-tab'));
            addTab.show();
        }
        
        function switchToManageTab() {
            const manageTab = new bootstrap.Tab(document.getElementById('pills-manage-tab'));
            manageTab.show();
        }
        
        function confirmAddPrincipal() {
            const currentPrincipal = "<?php echo $current_principal ? htmlspecialchars($current_principal['FullName']) : 'None'; ?>";
            const newPrincipal = document.querySelector('input[name="full_name"]').value;
            
            return confirm(`Are you sure you want to add a new principal?\n\nCurrent Principal: ${currentPrincipal}\nNew Principal: ${newPrincipal}\n\nThis will set the current principal to inactive.`);
        }
        
        // Function to load principal data into modal
        function loadPrincipalData(adminId, fullName, position, email, profilePicture) {
            document.getElementById('edit_admin_id').value = adminId;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_position').value = position;
            document.getElementById('edit_email').value = email;
            
            // Handle profile picture display
            const profileImg = document.getElementById('current_profile_picture');
            const noProfileDiv = document.getElementById('no_profile_picture');
            
            if (profilePicture) {
                profileImg.src = profilePicture;
                profileImg.style.display = 'block';
                noProfileDiv.style.display = 'none';
            } else {
                profileImg.style.display = 'none';
                noProfileDiv.style.display = 'flex';
            }
            
            // Clear the file input
            const fileInput = document.querySelector('#editPrincipalForm input[type="file"]');
            if (fileInput) {
                fileInput.value = '';
            }
            
            // Clear admin password field
            const adminPasswordInput = document.querySelector('#editPrincipalForm input[name="admin_password"]');
            if (adminPasswordInput) {
                adminPasswordInput.value = '';
            }
        }
        
        // Real-time password validation for add form
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            function validatePasswords() {
                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else if (confirmPassword) {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            if (password && confirmPassword) {
                password.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
            
            // File size validation
            const profilePictureInputs = document.querySelectorAll('input[name="profile_picture"]');
            profilePictureInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const maxSize = 5 * 1024 * 1024; // 5MB
                        if (file.size > maxSize) {
                            alert('File size exceeds 5MB. Please choose a smaller file.');
                            this.value = '';
                        }
                    }
                });
            });
            
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
            
            // Set active tab based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.getElementById(`pills-${tab}-tab`);
                if (tabElement) {
                    const bsTab = new bootstrap.Tab(tabElement);
                    bsTab.show();
                }
            }
            
            // Clear modal form when modal is hidden
            const editModal = document.getElementById('editPrincipalModal');
            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function () {
                    const form = document.getElementById('editPrincipalForm');
                    if (form) {
                        form.reset();
                    }
                });
            }
        });
    </script>
</body>
</html>