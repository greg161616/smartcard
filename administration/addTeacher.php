<?php
// addTeacher.php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../api/log_helper.php';
require __DIR__ . '/function.php';

if (isset($_POST['add_teacher'])) {
    // Collect and sanitize all form data
    $firstName  = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name']);
    $lastName   = trim($_POST['last_name']);
    $surfix     = trim($_POST['surfix'] ?? '');
    $gender     = trim($_POST['gender']);
    $birthdate  = trim($_POST['birthdate'] ?? '');
    $email      = trim($_POST['email_address']);
    $contact    = trim($_POST['contact'] ?? '');
    $address    = trim($_POST['address'] ?? '');

    // Validate required fields
    $errors = [];

    // Required field validation
    if (empty($firstName)) {
        $errors[] = "First name is required.";
    } elseif (!preg_match('/^[A-Za-z\s]{2,50}$/', $firstName)) {
        $errors[] = "First name must contain only letters and spaces (2-50 characters).";
    }

    if (!empty($middleName) && !preg_match('/^[A-Za-z\s]{0,50}$/', $middleName)) {
        $errors[] = "Middle name must contain only letters and spaces (max 50 characters).";
    }

    if (empty($lastName)) {
        $errors[] = "Last name is required.";
    } elseif (!preg_match('/^[A-Za-z\s]{2,50}$/', $lastName)) {
        $errors[] = "Last name must contain only letters and spaces (2-50 characters).";
    }

    if (!empty($surfix) && !preg_match('/^[A-Za-z0-9\.\s]{1,10}$/', $surfix)) {
        $errors[] = "Suffix can only contain letters, numbers, and periods (max 10 characters).";
    }

    if (empty($gender)) {
        $errors[] = "Gender is required.";
    } elseif (!in_array($gender, ['Male', 'Female'])) {
        $errors[] = "Please select a valid gender.";
    }

    // Email validation
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    } elseif (strlen($email) > 100) {
        $errors[] = "Email address cannot exceed 100 characters.";
    }

    // Contact validation
    if (!empty($contact)) {
        $cleanContact = preg_replace('/\s+/', '', $contact);
        if (!preg_match('/^[\+]?[0-9\-\(\)]{7,20}$/', $cleanContact)) {
            $errors[] = "Please provide a valid contact number (7-20 digits, can include +, -, parentheses).";
        }
    }

    // Birthdate validation
    if (!empty($birthdate)) {
        $minAgeDate = date('Y-m-d', strtotime('-18 years'));
        if ($birthdate > $minAgeDate) {
            $errors[] = "Teacher must be at least 18 years old.";
        }
    }

    // Address validation
    if (!empty($address) && strlen($address) > 255) {
        $errors[] = "Address cannot exceed 255 characters.";
    }

    // Check for validation errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Validation failed',
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'errors' => $errors
        ], 'warning');
        header("Location: ../administration/teacher.php");
        exit;
    }

    // Check for duplicate email
    $chk = $conn->prepare("SELECT UserID FROM user WHERE Email = ?");
    $chk->bind_param("s", $email);
    $chk->execute();
    $chk->store_result();
    
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

    // Insert into user table
    $defaultPass = strtolower($lastName); // Default password is last name in lowercase
    $passwordHash = password_hash($defaultPass, PASSWORD_DEFAULT);

    $insU = $conn->prepare("INSERT INTO user (Email, Password, Role) VALUES (?, ?, 'teacher')");
    $insU->bind_param("ss", $email, $passwordHash);
    
    if (!$insU->execute()) {
        $_SESSION['error'] = "Failed to create user account: " . $conn->error;
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'User insert failed',
            'email' => $email,
            'error' => $conn->error
        ], 'error');
        header("Location: ../administration/teacher.php");
        exit;
    }
    
    $userId = $insU->insert_id;
    $insU->close();

    // Insert into teacher table with all fields
    $insT = $conn->prepare("INSERT INTO teacher (fName, mName, lName, surfix, gender, birthdate, address, contact, UserID, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Handle empty values for optional fields
    $middleName = empty($middleName) ? null : $middleName;
    $surfix = empty($surfix) ? null : $surfix;
    $birthdate = empty($birthdate) ? null : $birthdate;
    $contact = empty($contact) ? null : $contact;
    $address = empty($address) ? null : $address;
    $status = 'Active';
    
    $insT->bind_param("sssssssssi", 
        $firstName, 
        $middleName, 
        $lastName, 
        $surfix, 
        $gender, 
        $birthdate, 
        $address, 
        $contact, 
        $userId,
        $status
    );
    
    if (!$insT->execute()) {
        $_SESSION['error'] = "Failed to create teacher profile: " . $conn->error;
        log_system_action($conn, 'add_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Teacher insert failed',
            'userId' => $userId,
            'email' => $email,
            'error' => $conn->error
        ], 'error');
        
        // Rollback user insertion if teacher insertion fails
        $delUser = $conn->prepare("DELETE FROM user WHERE UserID = ?");
        $delUser->bind_param("i", $userId);
        $delUser->execute();
        $delUser->close();
        
        header("Location: ../administration/teacher.php");
        exit;
    }
    
    $teacherId = $insT->insert_id;
    $insT->close();

    // Send email credentials
    $emailSent = false;
    try {
        $emailSent = sendTeacherCredentials($email, $defaultPass);
    } catch (Exception $e) {
        // Log email failure but don't stop the process
        log_system_action($conn, 'add_teacher_email_failed', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacherId,
            'email' => $email,
            'error' => $e->getMessage()
        ], 'warning');
    }

    // Set success message
    if ($emailSent) {
        $_SESSION['message'] = "Teacher added successfully and credentials have been emailed.";
        log_system_action($conn, 'add_teacher_success', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacherId,
            'userId' => $userId,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName
        ], 'info');
    } else {
        $_SESSION['message'] = "Teacher added successfully, but failed to send email credentials. Default password: " . htmlspecialchars($defaultPass);
        log_system_action($conn, 'add_teacher_partial', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacherId,
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
?>