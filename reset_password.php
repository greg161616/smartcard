<?php
session_start();
require_once 'config.php';

// Check if OTP is verified
if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified'] || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password in user table
        $sql = "UPDATE user SET Password = ? WHERE Email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            // Clear OTP records for this email
            $clear_sql = "UPDATE password_reset_otp SET is_used = 1 WHERE email = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            $clear_stmt->bind_param("s", $email);
            $clear_stmt->execute();
            
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);
            unset($_SESSION['otp_attempts']);
            
            $message = "Password reset successfully! Redirecting to login...";
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - School System</title>
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
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .email-display {
            font-weight: bold;
            color: #856404;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }
        .btn {
            background: #28a745;
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
            background: #218838;
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
        .password-requirements {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        
        <div class="info-box">
            <p>Resetting password for: <span class="email-display"><?php echo htmlspecialchars($email); ?></span></p>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="password-requirements">
            <strong>Password Requirements:</strong>
            <ul>
                <li>At least 8 characters long</li>
                <li>Contains at least one uppercase letter</li>
                <li>Contains at least one lowercase letter</li>
                <li>Contains at least one number</li>
            </ul>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       minlength="8"
                       placeholder="Enter new password">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       required 
                       minlength="8"
                       placeholder="Confirm new password">
                <div id="password-match" class="password-strength"></div>
            </div>
            
            <button type="submit" class="btn">Reset Password</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" style="color: #667eea; text-decoration: none;">Back to Login</a>
        </div>
    </div>
    
    <script>
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const matchMessage = document.getElementById('password-match');
        
        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                matchMessage.textContent = '';
                matchMessage.style.color = '';
            } else if (password.value === confirmPassword.value) {
                matchMessage.textContent = '✓ Passwords match';
                matchMessage.style.color = 'green';
            } else {
                matchMessage.textContent = '✗ Passwords do not match';
                matchMessage.style.color = 'red';
            }
        }
        
        confirmPassword.addEventListener('input', checkPasswordMatch);
        password.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>