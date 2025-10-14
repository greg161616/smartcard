<?php
// admin/importTeachers.php
session_start();

// 1) Load Composer’s autoloader so PhpSpreadsheet is available
require __DIR__ . '/../vendor/autoload.php';

// 2) Your database config (must define $conn = new mysqli(...))
require __DIR__ . '/../config.php';

// 3) Your helper functions (e.g. sendTeacherCredentials)
require __DIR__ . '/function.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

if (isset($_POST['import_file'])) {
    if (!empty($_FILES['teachers_file']['tmp_name'])) {
        $filePath     = $_FILES['teachers_file']['tmp_name'];
        $originalName = $_FILES['teachers_file']['name'];
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        try {
            // ── select the correct reader ────────────────────────────
            if (in_array($ext, ['xls','xlsx'])) {
                $reader = IOFactory::createReaderForFile($filePath);
            } elseif ($ext === 'csv') {
                $reader = new CsvReader();
                // default delimiter is comma; adjust if needed:
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
            } else {
                throw new Exception("Unsupported file format: {$ext}");
            }

            // ── load into array ─────────────────────────────────────
            $spreadsheet = $reader->load($filePath);
            $rows        = $spreadsheet->getActiveSheet()->toArray();

            $rowNum  = 0;
            $success = 0;
            $errors  = [];

            foreach ($rows as $row) {
                $rowNum++;
                // skip header
                if ($rowNum === 1) {
                    continue;
                }

                // expect [FirstName, MiddleName, LastName, Email]
                [$firstName, $middleName, $lastName, $email] = array_pad($row, 4, null);

                // simple validation
                if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$rowNum}: invalid or missing fields.";
                    continue;
                }

                // default password = last name
                $passwordHash = password_hash($lastName, PASSWORD_BCRYPT);

                // ── transaction ────────────────────────────────────────
                $conn->begin_transaction();
                try {
                    // 1) insert into user
                    $stmt1 = $conn->prepare("
                        INSERT INTO user (Email, Password, Role, CreatedAt)
                        VALUES (?, ?, 'teacher', NOW())
                    ");
                    $stmt1->bind_param("ss", $email, $passwordHash);
                    $stmt1->execute();
                    $newUserId = $conn->insert_id;
                    $stmt1->close();

                    // 2) insert into teacher
                    $stmt2 = $conn->prepare("
                        INSERT INTO teacher (UserID, fName, mName, lName)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt2->bind_param("isss", $newUserId, $firstName, $middleName, $lastName);
                    $stmt2->execute();
                    $stmt2->close();

                    $conn->commit();
                    $success++;

                    // 3) optionally email credentials
                    sendTeacherCredentials($email, $lastName);
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "Row {$rowNum} DB error: " . $e->getMessage();
                }
            }

            // set flash messages
            $_SESSION['message'] = "{$success} teacher(s) imported successfully.";
            if (!empty($errors)) {
                $_SESSION['error']   = implode('<br>', $errors);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Import failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "No file uploaded or upload error.";
    }
}

// go back to the list
header("Location: ../head/teacher.php");
exit;
