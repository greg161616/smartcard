<?php
session_start();
require_once 'config.php';

// Redirect if no email in session
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$error = '';
$message = '';

// Check if OTP has been sent (prevent direct access)
$sql = "SELECT * FROM password_reset_otp 
        WHERE email = ? AND is_used = 0 AND expires_at < NOW() 
        ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // No valid OTP found - show expired message
    $error = "Your OTP has expired. Please request a new one.";
    $otp_expired = true;
} else {
    $otp_expired = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle resend OTP request
    if (isset($_POST['resend_otp'])) {
        require_once 'mail_config.php';
        
        // Generate new OTP (whether old one expired or not)
        $new_otp = sprintf("%06d", random_int(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Insert new OTP
        $insert_sql = "INSERT INTO password_reset_otp (email, otp_code, expires_at) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $email, $new_otp, $expires_at);
        
        if ($insert_stmt->execute()) {
            // Get user info for email
            $user_sql = "SELECT u.*, 
                        CASE 
                            WHEN s.StudentID IS NOT NULL THEN 'student'
                            WHEN t.TeacherID IS NOT NULL THEN 'teacher'
                            WHEN a.AdminID IS NOT NULL THEN 'admin'
                            ELSE 'unknown'
                        END as user_role
                        FROM user u 
                        LEFT JOIN student s ON u.UserID = s.userID
                        LEFT JOIN teacher t ON u.UserID = t.UserID
                        LEFT JOIN admin a ON u.UserID = a.UserID
                        WHERE u.Email = ?";
            
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("s", $email);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $user = $user_result->fetch_assoc();
                $username = 'User';
                
                // Get username based on role
                switch($user['user_role']) {
                    case 'student':
                        $student_sql = "SELECT FirstName, LastName FROM student WHERE userID = ?";
                        $stmt2 = $conn->prepare($student_sql);
                        $stmt2->bind_param("i", $user['UserID']);
                        $stmt2->execute();
                        $student = $stmt2->get_result()->fetch_assoc();
                        if ($student) $username = $student['FirstName'] . ' ' . $student['LastName'];
                        break;
                    case 'teacher':
                        $teacher_sql = "SELECT fName, lName FROM teacher WHERE UserID = ?";
                        $stmt2 = $conn->prepare($teacher_sql);
                        $stmt2->bind_param("i", $user['UserID']);
                        $stmt2->execute();
                        $teacher = $stmt2->get_result()->fetch_assoc();
                        if ($teacher) $username = $teacher['fName'] . ' ' . $teacher['lName'];
                        break;
                    case 'admin':
                        $admin_sql = "SELECT FullName FROM admin WHERE UserID = ?";
                        $stmt2 = $conn->prepare($admin_sql);
                        $stmt2->bind_param("i", $user['UserID']);
                        $stmt2->execute();
                        $admin = $stmt2->get_result()->fetch_assoc();
                        if ($admin) $username = $admin['FullName'];
                        break;
                }
                
                $emailResult = sendOTPEmail($email, $new_otp, $username);
                if ($emailResult['success']) {
                    $message = "New OTP sent successfully!";
                    $error = ''; // Clear any previous error
                    $otp_expired = false; // Refresh the page state
                } else {
                    $error = "Failed to resend OTP. Please try again.";
                }
            } else {
                $error = "User not found. Please try again.";
            }
        } else {
            $error = "Error generating new OTP. Please try again.";
        }
    } 
    // Handle OTP verification
    else if (isset($_POST['otp'])) {
        $user_otp = trim($_POST['otp']);
        
        // Validate OTP
        $sql = "SELECT * FROM password_reset_otp 
                WHERE email = ? AND otp_code = ? AND is_used = 0 AND expires_at < NOW() 
                ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $user_otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Valid OTP, mark as used
            $update_sql = "UPDATE password_reset_otp SET is_used = 1 WHERE email = ? AND otp_code = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $email, $user_otp);
            $update_stmt->execute();
            
            // Redirect to password reset page
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            // Track failed attempts
            if (!isset($_SESSION['otp_attempts'])) {
                $_SESSION['otp_attempts'] = 1;
            } else {
                $_SESSION['otp_attempts']++;
            }
            
            // Check if too many attempts
            if ($_SESSION['otp_attempts'] >= 5) {
                // Invalidate all OTPs for this email
                $invalidate_sql = "UPDATE password_reset_otp SET expires_at = NOW() WHERE email = ?";
                $invalidate_stmt = $conn->prepare($invalidate_sql);
                $invalidate_stmt->bind_param("s", $email);
                $invalidate_stmt->execute();
                
                $error = "Too many failed attempts. Please request a new OTP.";
                unset($_SESSION['reset_email']);
            } else {
                $remaining_attempts = 5 - $_SESSION['otp_attempts'];
                $error = "Invalid OTP. You have $remaining_attempts attempt(s) remaining.";
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
    <title>Verify OTP - School System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .info-box {
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .email-display {
            font-weight: bold;
            color: #2e7d32;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .otp-input {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        .otp-input input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .otp-input input:focus {
            border-color: #667eea;
            outline: none;
        }
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .timer {
            text-align: center;
            margin: 10px 0;
            color: #666;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .actions button {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Verify OTP</h2>
        
        <div class="info-box">
            <p>OTP sent to: <span class="email-display"><?php echo htmlspecialchars($email); ?></span></p>
            <p>Check your email for the 6-digit OTP code.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (!$otp_expired): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="otp">Enter 6-digit OTP</label>
                <input type="text" 
                       id="otp" 
                       name="otp" 
                       required 
                       maxlength="6" 
                       pattern="[0-9]{6}" 
                       title="Enter 6-digit OTP"
                       placeholder="000000">
            </div>
            
            <div class="actions">
                <button type="submit" class="btn">Verify OTP</button>
            </div>
        </form>
        
        <form method="POST" action="">
            <div class="actions">
                <button type="submit" name="resend_otp" class="btn btn-secondary">Resend OTP</button>
            </div>
        </form>
        <?php else: ?>
        <form method="POST" action="">
            <div class="actions">
                <button type="submit" name="resend_otp" class="btn">Request New OTP</button>
            </div>
        </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="forgot_password.php" style="color: #667eea; text-decoration: none;">Use different email</a>
        </div>
    </div>
</body>
</html>