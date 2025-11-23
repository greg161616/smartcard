<?php
// Add at the very beginning of importStudents.php
set_time_limit(600); // Increase timeout to 10 minutes
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M'); // Increase memory limit if needed

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

                // Updated column mapping based on your file structure:
                // [LRN, FirstName, MiddleName, LastName, Sex, Email, SectionName, GradeLevel]
                [
                    $lRn,
                    $firstName,
                    $middleName,
                    $lastName,
                    $sex,
                    $email,
                    $sectionName,
                    $gradeLevel
                ] = array_pad($row, 8, null);

                // Basic validation (middle name, grade level and section are optional)
                if (empty($lRn) || empty($firstName) || empty($lastName) || 
                    empty($sex) || !in_array(strtolower($sex), ['male', 'female', 'm', 'f']) ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$rowNum}: Missing or invalid required fields (LRN: {$lRn}, Name: {$firstName} {$lastName}, Sex: {$sex}, Email: {$email})";
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

                    // 2) Insert into student table (with optional MiddleName)
                    $stmt2 = $conn->prepare("
                        INSERT INTO `student` (UserID, LRN, FirstName, MiddleName, LastName, Sex)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    // Handle empty middle name (set to NULL if empty)
                    $middleName = empty($middleName) ? '' : $middleName;
                    $stmt2->bind_param("isssss", $newUserId, $lRn, $firstName, $middleName, $lastName, $sex);
                    $stmt2->execute();
                    $newStudentId = $conn->insert_id;
                    $stmt2->close();

                    // 3) Only create section enrollment if both grade level and section are provided AND section exists
                    $sectionId = '';
                    if (!empty($gradeLevel) && !empty($sectionName)) {
                        // Check if section exists
                        $stmtCheckSection = $conn->prepare("
                            SELECT SectionID FROM section 
                            WHERE SectionName = ? AND GradeLevel = ?
                        ");
                        $stmtCheckSection->bind_param("ss", $sectionName, $gradeLevel);
                        $stmtCheckSection->execute();
                        $result = $stmtCheckSection->get_result();
                        
                        if ($result->num_rows > 0) {
                            $section = $result->fetch_assoc();
                            $sectionId = $section['SectionID'];
                            
                            // Insert into section_enrollment
                            $schoolyear_query = "SELECT * FROM school_year WHERE status = 'active' LIMIT 1";
                            $schoolyear_result = mysqli_query($conn, $schoolyear_query);
                            $schoolyear = mysqli_fetch_assoc($schoolyear_result);
                            $schoolYear = $schoolyear['school_year'];
                            
                            $stmt3 = $conn->prepare("
                                INSERT INTO `section_enrollment` (SectionID, StudentID, SchoolYear, status)
                                VALUES (?, ?, ?, 'active')
                            ");
                            $stmt3->bind_param("iis", $sectionId, $newStudentId, $schoolYear);
                            $stmt3->execute();
                            $stmt3->close();
                        } else {
                            // Section doesn't exist - add warning but continue with import
                            $errors[] = "Row {$rowNum}: Section '{$sectionName}' for grade level '{$gradeLevel}' does not exist. Student was imported without section assignment.";
                        }
                        $stmtCheckSection->close();
                    }

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

            // Return JSON response instead of redirecting
            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
            } else {
                echo json_encode(['success' => true, 'message' => "{$success} student(s) imported successfully."]);
            }
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Import failed: " . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => "No file uploaded or upload error."]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => "Invalid request."]);
    exit;
}