<?php
// function.php
// Requires PHPMailer: run `composer require phpmailer/phpmailer`

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Send teacher credentials by email.
 *
 * @param string $email
 * @param string $defaultPassword
 * @return bool
 */
function sendTeacherCredentials(string $email, string $defaultPassword): bool {
    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration — customize to your host
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'banahis2008@gmail.com';
        $mail->Password   = 'ehjc vdej avxu kryb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('banahis2008@gmail.com', 'School Admin');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Teacher Account Credentials';
        $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #378becff 0%, #4b98a2ff 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
                    .credentials-box { background: white; border: 2px solid #000000ff; border-radius: 8px; padding: 20px; margin: 20px 0; }
                    .credential-item { margin: 15px 0; }
                    .credential-label { font-weight: bold; color: #000000ff; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
                    .credential-value { background: #f0f0f0; padding: 12px 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 16px; margin-top: 5px; word-break: break-all; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; color: #856404; }
                    .warning strong { color: #856404; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
                    .button { display: inline-block; background: #66dbeaff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🎓 Welcome to Your Teacher Account</h1>
                    </div>
                    <div class='content'>
                        <p>Hello,</p>
                        <p>Your teacher account has been successfully created! Below are your login credentials:</p>
                        
                        <div class='credentials-box'>
                            <div class='credential-item'>
                                <div class='credential-label'>📧 Email Address</div>
                                <div class='credential-value'>{$email}</div>
                            </div>
                            <div class='credential-item'>
                                <div class='credential-label'>🔐 Password</div>
                                <div class='credential-value'>{$defaultPassword}</div>
                            </div>
                        </div>

                        <div class='warning'>
                            <strong>⚠️ Important:</strong> Please change your password immediately after your first login. Use a strong password combining uppercase, lowercase, numbers, and special characters.
                        </div>

                        <p>To access your account, visit the school portal and log in with the credentials above.</p>
                        
                        <center>
                            <a href='https://thesmartcard.xyz/login' class='button'>Login to School Portal</a>
                        </center>

                        <p style='margin-top: 30px; color: #666; font-size: 14px;'>If you have any questions or need assistance, please contact the school administration.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2025 School Administration System. All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error to {$email}: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send student credentials by email.
 *
 * @param string $email
 * @param string $defaultPassword
 * @return bool
 */
function sendStudentCredentials(string $email, string $defaultPassword): bool {
    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration — customize if needed
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'banahis2008@gmail.com';
        $mail->Password   = 'ehjc vdej avxu kryb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('banahis2008@gmail.com', 'School Admin');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Student Portal Credentials';
        $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #00b4db 0%, #0083b0 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd; border-top: none; }
                    .credentials-box { background: white; border: 2px solid #00b4db; border-radius: 8px; padding: 20px; margin: 20px 0; }
                    .credential-item { margin: 15px 0; }
                    .credential-label { font-weight: bold; color: #00b4db; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
                    .credential-value { background: #f0f0f0; padding: 12px 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 16px; margin-top: 5px; word-break: break-all; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; color: #856404; }
                    .warning strong { color: #856404; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
                    .button { display: inline-block; background: #00b4db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>📚 Welcome to Your Student Portal</h1>
                    </div>
                    <div class='content'>
                        <p>Hello Student,</p>
                        <p>Your student account has been successfully created! Below are your login credentials:</p>
                        
                        <div class='credentials-box'>
                            <div class='credential-item'>
                                <div class='credential-label'>📧 Email Address</div>
                                <div class='credential-value'>{$email}</div>
                            </div>
                            <div class='credential-item'>
                                <div class='credential-label'>🔐 Password</div>
                                <div class='credential-value'>{$defaultPassword}</div>
                            </div>
                        </div>

                        <div class='warning'>
                            <strong>⚠️ Important:</strong> Please change your password immediately after your first login. Use a strong password combining uppercase, lowercase, numbers, and special characters.
                        </div>

                        <p>To access your student portal, visit <a href='https://thesmartcard.xyz/login' style='color: #00b4db; text-decoration: none;'><strong>Portal Login</strong></a> and log in with the credentials above.</p>
                        
                        <center>
                            <a href='https://thesmartcard.xyz/login' class='button'>Login to Student Portal</a>
                        </center>

                        <p style='margin-top: 30px; color: #666; font-size: 14px;'>If you have any questions or need assistance, please contact the school administration.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2025 School Administration System. All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error to {$email}: {$mail->ErrorInfo}");
        return false;
    }
}
function getCurrentSchoolYear() {
    $currentYear = date('Y');
    $currentMonth = date('n');
    
    if ($currentMonth >= 6) { // June-December
        return $currentYear . '-' . ($currentYear + 1);
    } else { // January-May
        return ($currentYear - 1) . '-' . $currentYear;
    }
}
