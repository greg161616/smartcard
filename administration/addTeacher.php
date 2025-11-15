<?php
// addTeacher.php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../api/log_helper.php';
require __DIR__ . '/function.php';

if (isset($_POST['add_teacher'])) {
    $firstName  = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name']);
    $lastName   = trim($_POST['last_name']);
    $email      = trim($_POST['email_address']);

    if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please fill all required fields correctly.";
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Missing or invalid required fields',
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName
        ], 'warning');
        header("Location: ../administration/teacher.php"); 
        exit;
    }

    // Duplicate?
    $chk = $conn->prepare("SELECT UserID FROM user WHERE Email=?");
    $chk->bind_param("s", $email);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) {
        $_SESSION['error'] = "Email already registered.";
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Duplicate email',
            'email' => $email
        ], 'warning');
        $chk->close();
        header("Location: ../administration/teacher.php"); 
        exit;
    }
    $chk->close();

    // Insert user & teacher
    $defaultPass  = $lastName;
    $passwordHash = password_hash($defaultPass, PASSWORD_DEFAULT);

    $insU = $conn->prepare("INSERT INTO user (Email, Password,Role) VALUES (?,?,'teacher')");
    $insU->bind_param("ss", $email, $passwordHash);
    if (!$insU->execute()) {
        $_SESSION['error'] = "Failed to create user.";
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'User insert failed',
            'email' => $email,
            'error' => $conn->error
        ], 'error');
        header("Location: ../administration/teacher.php"); 
        exit;
    }
    $userId = $insU->insert_id; $insU->close();

    $insT = $conn->prepare("INSERT INTO teacher (fName,mName,lName,UserID) VALUES (?,?,?,?)");
    $insT->bind_param("sssi", $firstName, $middleName, $lastName, $userId);
    if (!$insT->execute()) {
        $_SESSION['error'] = "Failed to create teacher.";
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Teacher insert failed',
            'userId' => $userId,
            'error' => $conn->error
        ], 'error');
        header("Location: ../administration/teacher.php"); 
        exit;
    }
    $insT->close();

    // Email
    if (sendTeacherCredentials($email, $defaultPass)) {
        $_SESSION['message'] = "Teacher added and credentials emailed.";
        log_system_action($conn, 'add_teacher_success', $_SESSION['user_id'] ?? null, [
            'userId' => $userId,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName
        ], 'info');
    } else {
        $_SESSION['error'] = "Teacher added but email failed.";
        log_system_action($conn, 'add_teacher_partial', $_SESSION['user_id'] ?? null, [
            'userId' => $userId,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'reason' => 'Email send failed'
        ], 'warning');
    }
}
header("Location: ../administration/teacher.php");
exit;
