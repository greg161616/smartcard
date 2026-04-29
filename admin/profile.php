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

// Handle Update School Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_school_details'])) {
    $admin_password = $_POST['admin_password'];
    $school_name = trim($_POST['school_name'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $sub_office = trim($_POST['sub_office'] ?? '');
    $division = trim($_POST['division'] ?? '');
    $region = trim($_POST['region'] ?? '');
    
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
        try {
            $chk = mysqli_query($conn, "SELECT id FROM school_details LIMIT 1");
            if ($chk && mysqli_num_rows($chk) > 0) {
                $row_id = mysqli_fetch_assoc($chk)['id'];
                $upd = $conn->prepare("UPDATE school_details SET school_name=?, school_address=?, sub_office=?, division=?, region=? WHERE id=?");
                $upd->bind_param("sssssi", $school_name, $school_address, $sub_office, $division, $region, $row_id);
                $upd->execute();
                $upd->close();
            } else {
                $ins = $conn->prepare("INSERT INTO school_details (school_name, school_address, sub_office, division, region) VALUES (?,?,?,?,?)");
                $ins->bind_param("sssss", $school_name, $school_address, $sub_office, $division, $region);
                $ins->execute();
                $ins->close();
            }
            
            $log_action = "SCHOOL_DETAILS_UPDATED";
            $log_details = "School details updated: " . $school_name;
            $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
            $log_stmt->bind_param("sis", $log_action, $current_admin_id, $log_details);
            $log_stmt->execute();
            
            $success = "School details updated successfully!";
        } catch (Exception $e) {
            $error = "Failed to update school details: " . $e->getMessage();
        }
    }
}

// Fetch school details
$school_info = null;
$sd_check = mysqli_query($conn, "SELECT * FROM school_details LIMIT 1");
if ($sd_check && mysqli_num_rows($sd_check) > 0) {
    $school_info = mysqli_fetch_assoc($sd_check);
}

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'school';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal & School Profile</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            --secondary-gradient: linear-gradient(135deg, #3B82F6 0%, #2DD4BF 100%);
            --bg-color: #F3F4F6;
            --surface-color: rgba(255, 255, 255, 0.95);
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --border-radius-lg: 20px;
            --border-radius-md: 12px;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            overflow-x: hidden;
        }

        .hero-banner {
            height: 220px;
            background: var(--primary-gradient);
            border-radius: var(--border-radius-lg);
            margin-bottom: -100px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.2);
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,255,255,0.05)" d="M0 100 V 50 Q 25 25 50 50 T 100 50 V 100 z"/></svg>') center/cover;
        }

        .main-container {
            position: relative;
            z-index: 10;
        }

        .glass-card {
            background: var(--surface-color);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: var(--transition-smooth);
        }

        .glass-card:hover {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
        }

        /* Nav Pills Modernization */
        .nav-pills-custom {
            background: white;
            border-radius: 50px;
            padding: 0.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }

        .nav-pills-custom .nav-link {
            border-radius: 50px;
            padding: 0.8rem 1.8rem;
            color: var(--text-muted);
            font-weight: 500;
            transition: var(--transition-smooth);
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-pills-custom .nav-link:hover {
            color: var(--text-main);
            background: #F3F4F6;
        }

        .nav-pills-custom .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }

        /* Avatars */
        .profile-avatar-large {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: white;
            margin-top: -80px;
            margin-bottom: 1.5rem;
        }

        .profile-avatar-small {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #E5E7EB;
        }
        
        .avatar-placeholder-large {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 6px solid white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: var(--secondary-gradient);
            margin-top: -80px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        /* Form Controls */
        .form-control, .form-select {
            border-radius: var(--border-radius-md);
            border: 1px solid #E5E7EB;
            padding: 0.8rem 1.2rem;
            font-size: 0.95rem;
            transition: var(--transition-smooth);
            background: #F9FAFB;
        }

        .form-control:focus, .form-select:focus {
            background: white;
            border-color: #7C3AED;
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .required::after {
            content: " *";
            color: #EF4444;
        }

        /* Buttons */
        .btn-custom-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-custom-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
            color: white;
        }

        .btn-custom-secondary {
            background: white;
            color: var(--text-main);
            border: 1px solid #E5E7EB;
            border-radius: 50px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            transition: var(--transition-smooth);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-custom-secondary:hover {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        /* Tables */
        .table-custom {
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }
        
        .table-custom th {
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem;
        }

        .table-custom td {
            background: white;
            border: none;
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid #F3F4F6;
            border-bottom: 1px solid #F3F4F6;
        }

        .table-custom tr td:first-child { 
            border-left: 1px solid #F3F4F6;
            border-radius: var(--border-radius-md) 0 0 var(--border-radius-md); 
        }
        .table-custom tr td:last-child { 
            border-right: 1px solid #F3F4F6;
            border-radius: 0 var(--border-radius-md) var(--border-radius-md) 0; 
        }

        .table-custom tr:hover td {
            background: #F9FAFB;
        }

        /* Detail Blocks */
        .detail-block {
            padding: 1.25rem;
            background: #F9FAFB;
            border-radius: var(--border-radius-md);
            margin-bottom: 1.25rem;
            border-left: 4px solid #7C3AED;
            transition: var(--transition-smooth);
        }

        .detail-block:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transform: translateX(5px);
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 1.1rem;
            color: var(--text-main);
            font-weight: 500;
        }

        /* Modal styling */
        .modal-content {
            border-radius: var(--border-radius-lg);
            border: none;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .modal-header-custom {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1.5rem 2rem;
        }

        .modal-header-custom .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid #F3F4F6;
            padding: 1.5rem 2rem;
        }

        .alert-custom {
            border-radius: var(--border-radius-md);
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
        }
        
        .school-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            margin-bottom: 1.5rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
        }
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }
        .status-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6B7280;
        }
    </style>
</head>
<body>
    <?php 
    // Include the admin nav with profile picture
    include '../navs/adminNav.php'; 
    ?>
    
    <div class="hero-banner"></div>
    
    <div class="container main-container">
        
        <?php if ($error): ?>
            <div class="alert alert-custom alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-octagon-fill fs-5"></i> 
                <div><?php echo $error; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-custom alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-5"></i> 
                <div><?php echo $success; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <div class="text-center">
            <div class="nav-pills-custom" id="pills-tab" role="tablist">
                <button class="nav-link <?php echo $active_tab === 'school' ? 'active' : ''; ?>" 
                        id="pills-school-tab" data-bs-toggle="pill" data-bs-target="#pills-school" type="button" role="tab">
                    <i class="bi bi-building"></i> School Profile
                </button>
                <button class="nav-link <?php echo $active_tab === 'manage' ? 'active' : ''; ?>" 
                        id="pills-manage-tab" data-bs-toggle="pill" data-bs-target="#pills-manage" type="button" role="tab">
                    <i class="bi bi-people"></i> Manage Principals
                </button>
                <button class="nav-link <?php echo $active_tab === 'add' ? 'active' : ''; ?>" 
                        id="pills-add-tab" data-bs-toggle="pill" data-bs-target="#pills-add" type="button" role="tab">
                    <i class="bi bi-person-plus"></i> Add New Principal
                </button>
            </div>
        </div>
        
        <div class="tab-content" id="pills-tabContent">
            
            <!-- School Profile Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'school' ? 'show active' : ''; ?>" 
                 id="pills-school" role="tabpanel">
                
                <div class="glass-card">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-auto text-center text-md-start">
                            <div class="school-icon-wrapper mx-auto mx-md-0">
                                <i class="bi bi-bank"></i>
                            </div>
                        </div>
                        <div class="col text-center text-md-start">
                            <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($school_info['school_name'] ?? 'SmartCard System'); ?></h2>
                            <p class="text-muted mb-0"><i class="bi bi-geo-alt-fill me-1"></i><?php echo htmlspecialchars($school_info['school_address'] ?? 'Address Not Set'); ?></p>
                        </div>
                        <div class="col-md-auto mt-3 mt-md-0 text-center text-md-end">
                            <button type="button" class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#editSchoolModal">
                                <i class="bi bi-pencil-square"></i> Edit Details
                            </button>
                        </div>
                    </div>
                    
                    <div class="row g-4 mt-2">
                        <div class="col-md-4">
                            <div class="detail-block">
                                <div class="detail-label">Region</div>
                                <div class="detail-value"><?php echo htmlspecialchars($school_info['region'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-block">
                                <div class="detail-label">Division</div>
                                <div class="detail-value"><?php echo htmlspecialchars($school_info['division'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-block">
                                <div class="detail-label">Sub-Office</div>
                                <div class="detail-value"><?php echo htmlspecialchars($school_info['sub_office'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Manage Principals Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'manage' ? 'show active' : ''; ?>" 
                 id="pills-manage" role="tabpanel">
                
                <?php if (empty($principals)): ?>
                    <div class="glass-card text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle p-4 mb-4" style="width: 100px; height: 100px;">
                            <i class="bi bi-person-x display-4 text-muted"></i>
                        </div>
                        <h4 class="fw-bold mb-3">No principals found</h4>
                        <p class="text-muted mb-4">Get started by adding your first principal profile.</p>
                        <button type="button" class="btn btn-custom-primary" onclick="switchToAddTab()">
                            <i class="bi bi-plus-lg"></i> Add Principal
                        </button>
                    </div>
                <?php else: ?>
                    
                    <!-- Current Principal Info -->
                    <?php if ($current_principal): ?>
                    <div class="glass-card text-center mb-5">
                        <?php if ($current_principal['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($current_principal['profile_picture']); ?>" 
                                 alt="Profile Picture" class="profile-avatar-large mx-auto">
                        <?php else: ?>
                            <div class="avatar-placeholder-large mx-auto">
                                <i class="bi bi-person"></i>
                            </div>
                        <?php endif; ?>
                        
                        <span class="badge status-badge status-active mb-3"><i class="bi bi-check-circle-fill me-1"></i> Current Active Principal</span>
                        <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($current_principal['FullName']); ?></h3>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($current_principal['Position']); ?></p>
                        
                        <div class="d-flex justify-content-center gap-4 mb-4">
                            <div class="text-center">
                                <div class="text-muted small text-uppercase fw-semibold mb-1">Email Address</div>
                                <div class="fw-medium"><i class="bi bi-envelope-fill text-primary me-2"></i><?php echo htmlspecialchars($current_principal['Email']); ?></div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-custom-secondary" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editPrincipalModal"
                                onclick="loadPrincipalData(<?php echo $current_principal['AdminID']; ?>, '<?php echo htmlspecialchars($current_principal['FullName']); ?>', '<?php echo htmlspecialchars($current_principal['Position']); ?>', '<?php echo htmlspecialchars($current_principal['Email']); ?>', '<?php echo $current_principal['profile_picture'] ? htmlspecialchars($current_principal['profile_picture']) : ''; ?>')">
                            <i class="bi bi-pencil"></i> Edit Profile
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Principals List -->
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-4 border-bottom border-light">
                            <h5 class="fw-bold mb-0"><i class="bi bi-list-ul me-2 text-primary"></i> Directory</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Profile</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Created Date</th>
                                        <th class="pe-4 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($principals as $principal): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <?php if ($principal['profile_picture']): ?>
                                                    <img src="<?php echo htmlspecialchars($principal['profile_picture']); ?>" 
                                                         alt="Profile Picture" class="profile-avatar-small shadow-sm">
                                                <?php else: ?>
                                                    <div class="profile-avatar-small bg-light d-flex align-items-center justify-content-center text-muted border shadow-sm">
                                                        <i class="bi bi-person"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-medium"><?php echo htmlspecialchars($principal['FullName']); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars($principal['Position']); ?></td>
                                            <td><?php echo htmlspecialchars($principal['Email']); ?></td>
                                            <td>
                                                <span class="badge status-badge <?php echo $principal['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst($principal['status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-muted"><?php echo date('M j, Y', strtotime($principal['CreatedAt'])); ?></td>
                                            <td class="pe-4 text-end">
                                                <?php if ($principal['status'] === 'inactive'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="admin_id" value="<?php echo $principal['AdminID']; ?>">
                                                        <button type="submit" name="activate_principal" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-medium" 
                                                                onclick="return confirm('Activate this principal? This will deactivate the current active principal.')">
                                                            <i class="bi bi-arrow-repeat"></i> Set Active
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Principal Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'add' ? 'show active' : ''; ?>" 
                 id="pills-add" role="tabpanel">
                
                <div class="glass-card">
                    <h4 class="fw-bold mb-4"><i class="bi bi-person-plus text-primary me-2"></i> Register New Principal</h4>
                    
                    <form method="POST" action="" id="addPrincipalForm" enctype="multipart/form-data">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label required">Full Name</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                       required placeholder="Enter full name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Position</label>
                                <select class="form-select" name="position" required>
                                    <option value="">Select Position</option>
                                    <option value="Head teacher" <?php echo (($_POST['position'] ?? '') === 'Head teacher') ? 'selected' : ''; ?>>Head Teacher</option>
                                    <option value="Principal" <?php echo (($_POST['position'] ?? '') === 'Principal') ? 'selected' : ''; ?>>Principal</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Email Address</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required placeholder="Enter email address">
                                <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Used for system login</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_picture" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif">
                                <div class="form-text text-muted small">Max 5MB (JPEG, JPG, PNG, GIF)</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Password</label>
                                <input type="password" class="form-control" name="password" 
                                       minlength="6" required placeholder="Minimum 6 characters">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" name="confirm_password" 
                                       minlength="6" required placeholder="Retype password">
                            </div>
                        </div>
                        
                        <div class="p-4 bg-light rounded-4 mb-4 border border-warning border-opacity-25">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2 me-3">
                                    <i class="bi bi-shield-lock-fill fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Security Verification</h6>
                                    <p class="text-muted small mb-0">This action logs out the current principal and requires authorization.</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label required">Admin Password</label>
                                    <input type="password" class="form-control border-warning border-opacity-50" name="admin_password" 
                                           required placeholder="Enter current admin password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="add_principal" class="btn btn-custom-primary px-5" 
                                    onclick="return confirmAddPrincipal()">
                                <i class="bi bi-check-lg"></i> Register Principal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Edit Principal Modal -->
    <div class="modal fade" id="editPrincipalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i> Edit Principal Profile
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editPrincipalForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="admin_id" id="edit_admin_id">
                        <input type="hidden" name="update_principal" value="1">
                        
                        <div class="text-center mb-4">
                            <img id="current_profile_picture" src="" alt="Profile" class="profile-avatar-large d-block mx-auto mt-0 mb-2 border">
                            <div id="no_profile_picture" class="avatar-placeholder-large mx-auto mt-0 mb-2" style="display: none;">
                                <i class="bi bi-person"></i>
                            </div>
                            <p class="text-muted small text-uppercase fw-semibold">Current Avatar</p>
                        </div>
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label required">Full Name</label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Position</label>
                                <select class="form-select" id="edit_position" name="position" required>
                                    <option value="Head teacher">Head Teacher</option>
                                    <option value="Principal">Principal</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Email Address</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Profile Picture</label>
                                <input type="file" class="form-control" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif">
                            </div>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="form-label required text-danger"><i class="bi bi-shield-lock me-1"></i> Admin Password</label>
                            <input type="password" class="form-control" name="admin_password" required placeholder="Verify to update">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-custom-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit School Modal -->
    <div class="modal fade" id="editSchoolModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-building-add me-2"></i> Edit School Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editSchoolForm">
                    <div class="modal-body">
                        <input type="hidden" name="update_school_details" value="1">
                        
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <label class="form-label required">School Name</label>
                                <input type="text" class="form-control" name="school_name" value="<?php echo htmlspecialchars($school_info['school_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">School Address</label>
                                <input type="text" class="form-control" name="school_address" value="<?php echo htmlspecialchars($school_info['school_address'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Region</label>
                                <input type="text" class="form-control" name="region" value="<?php echo htmlspecialchars($school_info['region'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <input type="text" class="form-control" name="division" value="<?php echo htmlspecialchars($school_info['division'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sub-Office</label>
                                <input type="text" class="form-control" name="sub_office" value="<?php echo htmlspecialchars($school_info['sub_office'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="form-label required text-danger"><i class="bi bi-shield-lock me-1"></i> Admin Password</label>
                            <input type="password" class="form-control" name="admin_password" required placeholder="Verify to update">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-custom-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">Save Changes</button>
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
        
        function confirmAddPrincipal() {
            const currentPrincipal = "<?php echo $current_principal ? htmlspecialchars($current_principal['FullName']) : 'None'; ?>";
            const newPrincipal = document.querySelector('input[name="full_name"]').value;
            if(!newPrincipal) return true;
            return confirm(`Are you sure you want to add a new principal?\n\nCurrent Principal: ${currentPrincipal}\nNew Principal: ${newPrincipal}\n\nThis will set the current principal to inactive.`);
        }
        
        function loadPrincipalData(adminId, fullName, position, email, profilePicture) {
            document.getElementById('edit_admin_id').value = adminId;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_position').value = position;
            document.getElementById('edit_email').value = email;
            
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
            
            const fileInput = document.querySelector('#editPrincipalForm input[type="file"]');
            if (fileInput) fileInput.value = '';
            
            const adminPasswordInput = document.querySelector('#editPrincipalForm input[name="admin_password"]');
            if (adminPasswordInput) adminPasswordInput.value = '';
        }
        
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
            
            const profilePictureInputs = document.querySelectorAll('input[name="profile_picture"]');
            profilePictureInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const maxSize = 5 * 1024 * 1024;
                        if (file.size > maxSize) {
                            alert('File size exceeds 5MB. Please choose a smaller file.');
                            this.value = '';
                        }
                    }
                });
            });
            
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
            
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.getElementById(`pills-${tab}-tab`);
                if (tabElement) {
                    const bsTab = new bootstrap.Tab(tabElement);
                    bsTab.show();
                }
            }
            
            const editModal = document.getElementById('editPrincipalModal');
            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function () {
                    const form = document.getElementById('editPrincipalForm');
                    if (form) form.reset();
                });
            }
            
            const schoolModal = document.getElementById('editSchoolModal');
            if (schoolModal) {
                schoolModal.addEventListener('hidden.bs.modal', function () {
                    const form = document.getElementById('editSchoolForm');
                    if (form) form.reset();
                });
            }
        });
    </script>
</body>
</html>