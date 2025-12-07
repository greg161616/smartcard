<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Or adjust path to PHPMailer files

function sendOTPEmail($toEmail, $otp, $username = 'User') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'banahis2008@gmail.com'; // Your Gmail address
        $mail->Password   = 'ehjc vdej avxu kryb'; // Remove the extra quotes! Use App Password for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0; // Set to 2 for debugging if needed
        
        // Recipients
        $mail->setFrom('banahis2008@gmail.com', 'Balaytigue National High School');
        $mail->addAddress($toEmail, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - School System';
        $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Password Reset Request</h2>
                <p>Hello $username,</p>
                <p>You have requested to reset your password. Use the following OTP to proceed:</p>
                
                <div style='background-color: #f4f4f4; padding: 15px; margin: 20px 0; text-align: center;'>
                    <h1 style='color: #333; letter-spacing: 5px;'>$otp</h1>
                </div>
                
                <p>This OTP is valid for <strong>15 minutes</strong>.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <br>
                <p>Best regards,<br>School Administration</p>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Password Reset OTP: $otp\nThis OTP is valid for 15 minutes.";
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'OTP sent successfully'];
        } else {
            // Log error for debugging
            error_log("Email send failed for $toEmail: " . $mail->ErrorInfo);
            return ['success' => false, 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo];
        }
        
    } catch (Exception $e) {
        // Log exception for debugging
        error_log("Mailer Exception: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"];
    }
}
?>