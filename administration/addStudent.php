<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../api/log_helper.php';
require __DIR__ . '/function.php';

if (isset($_POST['add_student'])) {
    // Collect form data
    $lrn = trim($_POST['LRN']);
    $firstName = trim($_POST['FirstName']);
    $middleName = trim($_POST['MiddleName']);
    $lastName = trim($_POST['LastName']);
    $email = trim($_POST['Email']);
    $sectionID = (int)$_POST['SectionID'];
    $sex = trim($_POST['Sex'] ?? '');
    $birthdate = trim($_POST['Birthdate'] ?? '');
    $address = trim($_POST['Address'] ?? '');
    $contactNumber = trim($_POST['ContactNumber'] ?? '');
    $parentName = trim($_POST['ParentName'] ?? '');
    $parentsContact = trim($_POST['ParentsContact'] ?? '');
    $civilStatus = trim($_POST['CivilStatus'] ?? '');
    $religion = trim($_POST['Religion'] ?? '');
    $barangay = trim($_POST['Barangay'] ?? '');

    // Validate required fields
    if (!$lrn || !$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$sectionID) {
        $_SESSION['error'] = "Please fill all required fields correctly.";
        log_system_action($conn, 'add_student_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Missing or invalid required fields',
            'lrn' => $lrn,
            'email' => $email,
            'sectionID' => $sectionID
        ], 'warning');
        header("Location: ../administration/studentlist"); 
        exit;
    }

    // Duplicate email check
    $chkEmail = $conn->prepare("SELECT UserID FROM user WHERE Email=?");
    $chkEmail->bind_param("s", $email);
    $chkEmail->execute(); 
    $chkEmail->store_result();
    if ($chkEmail->num_rows > 0) {
        $_SESSION['error'] = "Email already registered.";
        log_system_action($conn, 'add_student_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Duplicate email',
            'email' => $email
        ], 'warning');
        $chkEmail->close();
        header("Location: ../administration/studentlist"); 
        exit;
    }
    $chkEmail->close();

    // Duplicate LRN check
    $chkLrn = $conn->prepare("SELECT StudentID FROM student WHERE LRN=?");
    $chkLrn->bind_param("s", $lrn);
    $chkLrn->execute(); 
    $chkLrn->store_result();
    if ($chkLrn->num_rows > 0) {
        $_SESSION['error'] = "LRN already exists.";
        log_system_action($conn, 'add_student_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Duplicate LRN',
            'lrn' => $lrn
        ], 'warning');
        $chkLrn->close();
        header("Location: ../administration/studentlist"); 
        exit;
    }
    $chkLrn->close();

    // Insert user
    $defaultPass = $lrn;
    $passwordHash = password_hash($defaultPass, PASSWORD_DEFAULT);
    $insU = $conn->prepare("INSERT INTO user (Email, Password, Role, CreatedAt) VALUES (?, ?, 'student', NOW())");
    $insU->bind_param("ss", $email, $passwordHash);
    if (!$insU->execute()) {
        $_SESSION['error'] = "Failed to create user: " . $conn->error;
        log_system_action($conn, 'add_student_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'User insert failed',
            'email' => $email,
            'error' => $conn->error
        ], 'error');
        header("Location: ../administration/studentlist");
        exit;
    }
    $userId = $insU->insert_id; 
    $insU->close();

    // Insert student
    $insS = $conn->prepare("INSERT INTO student (
        userID, LRN, FirstName, MiddleName, LastName, Sex, Birthdate, 
        Address, contactNumber, parentname, ParentsContact, 
        CivilStatus, Religion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $insS->bind_param(
        "issssssssssss", 
        $userId, $lrn, $firstName, $middleName, $lastName, $sex, $birthdate,
        $address, $contactNumber, $parentName, $parentsContact,
        $civilStatus, $religion
    );
    
    if (!$insS->execute()) {
        $_SESSION['error'] = "Failed to create student: " . $conn->error;
        log_system_action($conn, 'add_student_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Student insert failed',
            'lrn' => $lrn,
            'error' => $conn->error
        ], 'error');
        header("Location: ../administration/studentlist"); 
        exit;
    }
    $studentId = $insS->insert_id;
    $insS->close();

    // Section enrollment
    $schoolYear = getCurrentSchoolYear(); // Implement this in function.php
    $insE = $conn->prepare("INSERT INTO section_enrollment (SectionID, StudentID, SchoolYear, status) VALUES (?, ?, ?, 'active')");
    $insE->bind_param("iis", $sectionID, $studentId, $schoolYear);
    if (!$insE->execute()) {
        $_SESSION['error'] = "Student created but enrollment failed: " . $conn->error;
        log_system_action($conn, 'add_student_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Enrollment insert failed',
            'studentId' => $studentId,
            'sectionID' => $sectionID,
            'error' => $conn->error
        ], 'error');
        $insE->close();
        header("Location: ../administration/studentlist");
        exit;
    }
    $insE->close();

    // Send credentials
    if (sendStudentCredentials($email, $defaultPass)) {
        $_SESSION['message'] = "Student added and credentials emailed.";
        log_system_action($conn, 'add_student_success', $_SESSION['user_id'] ?? null, [
            'studentId' => $studentId,
            'userId' => $userId,
            'lrn' => $lrn,
            'email' => $email,
            'sectionID' => $sectionID
        ], 'info');
    } else {
        $_SESSION['error'] = "Student added but email failed.";
        log_system_action($conn, 'add_student_partial', $_SESSION['user_id'] ?? null, [
            'studentId' => $studentId,
            'userId' => $userId,
            'lrn' => $lrn,
            'email' => $email,
            'sectionID' => $sectionID,
            'reason' => 'Email send failed'
        ], 'warning');
    }
}
header("Location: ../administration/studentlist");
exit;