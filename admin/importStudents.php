<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/function.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

if (isset($_POST['import_file'])) {
    if (!empty($_FILES['students_file']['tmp_name'])) {
        $filePath = $_FILES['students_file']['tmp_name'];
        $originalName = $_FILES['students_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $errors = [];
        $success = 0;

        try {
            // Select reader based on file extension
            if (in_array($ext, ['xls','xlsx'])) {
                $reader = IOFactory::createReaderForFile($filePath);
            } elseif ($ext === 'csv') {
                $reader = new CsvReader();
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
            } else {
                throw new Exception("Unsupported file format: {$ext}");
            }

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $rowNum = 0;
            foreach ($rows as $row) {
                $rowNum++;
                // Skip header row
                if ($rowNum === 1) {
                    continue;
                }

                // Expect columns: [LRN, FirstName, MiddleName, LastName, Sex, Email, SectionID]
                [
                    $lRn,
                    $firstName,
                    $middleName,
                    $lastName,
                    $sex,
                    $email,
                    $sectionId
                ] = array_pad($row, 7, null);

                // Basic validation
                if (empty($lRn) || empty($firstName) || empty($lastName) || 
                    empty($sex) || !in_array(strtolower($sex), ['male', 'female', 'm', 'f']) ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_numeric($sectionId)) {
                    $errors[] = "Row {$rowNum}: Missing or invalid fields (LRN: {$lRn}, Name: {$firstName} {$lastName}, Sex: {$sex}, Email: {$email}, SectionID: {$sectionId})";
                    continue;
                }

                // Normalize sex to 'Male' or 'Female'
                $sex = strtolower($sex);
                if ($sex === 'm') $sex = 'Male';
                if ($sex === 'f') $sex = 'Female';
                $sex = ucfirst(strtolower($sex));

                // Default password = LRN
                $passwordHash = password_hash($lRn, PASSWORD_BCRYPT);

                // Start transaction
                $conn->begin_transaction();
                try {
                    // 1) Insert into user table
                    $stmt1 = $conn->prepare("
                        INSERT INTO `user` (Email, Password, Role, CreatedAt)
                        VALUES (?, ?, 'student', NOW())
                    ");
                    $stmt1->bind_param("ss", $email, $passwordHash);
                    $stmt1->execute();
                    $newUserId = $conn->insert_id;
                    $stmt1->close();

                    // 2) Insert into student table (with Sex field)
                    $stmt2 = $conn->prepare("
                        INSERT INTO `student` (UserID, LRN, FirstName, MiddleName, LastName, Sex)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt2->bind_param("isssss", $newUserId, $lRn, $firstName, $middleName, $lastName, $sex);
                    $stmt2->execute();
                    $newStudentId = $conn->insert_id;
                    $stmt2->close();

                    // 3) Insert into section_enrollment
                    $currentYear = date('Y');
                    $schoolYear = $currentYear . '-' . ($currentYear + 1);
                    
                    $stmt3 = $conn->prepare("
                        INSERT INTO `section_enrollment` (SectionID, StudentID, SchoolYear, status)
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmt3->bind_param("iis", $sectionId, $newStudentId, $schoolYear);
                    $stmt3->execute();
                    $stmt3->close();

                    $conn->commit();
                    $success++;

                    // 4) Email credentials if function exists
                    if (function_exists('sendStudentCredentials')) {
                        sendStudentCredentials($email, $lRn);
                    }

                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "Row {$rowNum} DB error: " . $e->getMessage();
                    error_log("DB Error: " . $e->getMessage());
                }
            }

            $_SESSION['message'] = "{$success} student(s) imported successfully.";
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Import failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "No file uploaded or upload error.";
    }

    header("Location: studentlist.php");
    exit;
}