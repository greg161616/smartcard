<?php
// addTeacher.php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/function.php';

if (isset($_POST['add_teacher'])) {
    $firstName  = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name']);
    $lastName   = trim($_POST['last_name']);
    $email      = trim($_POST['email_address']);

    if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please fill all required fields correctly.";
        header("Location: ../head/teacher.php"); exit;
    }

    // Duplicate?
    $chk = $conn->prepare("SELECT UserID FROM user WHERE Email=?");
    $chk->bind_param("s", $email);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) {
        $_SESSION['error'] = "Email already registered.";
        $chk->close();
        header("Location: ../head/teacher.php"); exit;
    }
    $chk->close();

    // Insert user & teacher
    $defaultPass  = $lastName;
    $passwordHash = password_hash($defaultPass, PASSWORD_DEFAULT);

    $insU = $conn->prepare("INSERT INTO user (Email, Password,Role) VALUES (?,?,'teacher')");
    $insU->bind_param("ss", $email, $passwordHash);
    if (!$insU->execute()) {
        $_SESSION['error'] = "Failed to create user.";
        header("Location: ../head/teacher.php"); exit;
    }
    $userId = $insU->insert_id; $insU->close();

    $insT = $conn->prepare("INSERT INTO teacher (fName,mName,lName,UserID) VALUES (?,?,?,?)");
    $insT->bind_param("sssi", $firstName, $middleName, $lastName, $userId);
    if (!$insT->execute()) {
        $_SESSION['error'] = "Failed to create teacher.";
        header("Location: ../head/teacher.php"); exit;
    }
    $insT->close();

    // Email
    if (sendTeacherCredentials($email, $defaultPass)) {
        $_SESSION['message'] = "Teacher added and credentials emailed.";
    } else {
        $_SESSION['error']   = "Teacher added but email failed.";
    }
}
header("Location: ../head/teacher.php");
exit;
