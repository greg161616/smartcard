<?php
session_start();
require_once 'config.php'; // Your database connection
require_once 'mail_config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists in user table
        $sql = "SELECT u.*, 
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
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate 6-digit OTP
            $otp = sprintf("%06d", random_int(0, 999999));
            
            // OTP expires in 15 minutes (increased from 10 for better UX)
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store OTP in database
            $insert_sql = "INSERT INTO password_reset_otp (email, otp_code, expires_at) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sss", $email, $otp, $expires_at);
            
            if ($insert_stmt->execute()) {
                // Get username based on role
                $username = '';
                switch($user['user_role']) {
                    case 'student':
                        $student_sql = "SELECT FirstName, LastName FROM student WHERE userID = ?";
                        $stmt2 = $conn->prepare($student_sql);
                        $stmt2->bind_param("i", $user['UserID']);
                        $stmt2->execute();
                        $student = $stmt2->get_result()->fetch_assoc();
                        $username = $student['FirstName'] . ' ' . $student['LastName'];
                        break;
                    case 'teacher':
                        $teacher_sql = "SELECT fName, lName FROM teacher WHERE UserID = ?";
                        $stmt2 = $conn->prepare($teacher_sql);
                        $stmt2->bind_param("i", $user['UserID']);
                        $stmt2->execute();
                        $teacher = $stmt2->get_result()->fetch_assoc();
                        $username = $teacher['fName'] . ' ' . $teacher['lName'];
                        break;
                    case 'admin':
                        $admin_sql = "SELECT FullName FROM admin WHERE UserID = ?";
                        $stmt2 = $conn->prepare($admin_sql);
                        $stmt2->bind_param("i", $user['UserID']);
                        $stmt2->execute();
                        $admin = $stmt2->get_result()->fetch_assoc();
                        $username = $admin['FullName'];
                        break;
                    default:
                        $username = 'User';
                }
                
                // Send OTP email
                $emailResult = sendOTPEmail($email, $otp, $username);
                
                if ($emailResult['success']) {
                    // Store email in session for verification
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['otp_attempts'] = 0;
                    
                    header("Location: verify_otp.php");
                    exit();
                } else {
                    $error = "Failed to send OTP email. Please try again.";
                }
            } else {
                $error = "Error generating OTP. Please try again.";
            }
        } else {
            $error = "Email not found in our system.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - School System</title>
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
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
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter your registered email">
            </div>
            
            <button type="submit" class="btn">Send Reset OTP</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>