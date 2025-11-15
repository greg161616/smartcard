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
        $mail->Password   = '"ehjc vdej avxu kryb"';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('banahis2008@gmail.com', 'School Admin');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Teacher Account Credentials';
        $mail->Body    = "
            <p>Hello,</p>
            <p>Your teacher account has been created:</p>
            <ul>
              <li><strong>Email:</strong> {$email}</li>
              <li><strong>Password:</strong> {$defaultPassword}</li>
            </ul>
            <p>Please log in and change your password immediately.</p>
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
            <p>Welcome to the Student Portal!</p>
            <ul>
              <li><strong>Email:</strong> {$email}</li>
              <li><strong>Password:</strong> {$defaultPassword}</li>
            </ul>
            <p>Please log in at <a href=\"https://yourdomain.com/login\">Portal Login</a> and change your password immediately.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error to {$email}: {$mail->ErrorInfo}");
        return false;
    }
}
// Add this function to function.php
function getCurrentSchoolYear() {
    $currentYear = date('Y');
    $currentMonth = date('n');
    
    if ($currentMonth >= 6) { // June-December
        return $currentYear . '-' . ($currentYear + 1);
    } else { // January-May
        return ($currentYear - 1) . '-' . $currentYear;
    }
}
