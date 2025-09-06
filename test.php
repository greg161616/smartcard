<?php
session_start();
require_once 'config.php';

include 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$teacher_id = $_SESSION['teacher_id'] ?? 11;
$message = '';
$message_type = 'info';
$students_not_found = [];
$students_processed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['grade_file'])) {
    $quarter = $_POST['quarter'];
    $file = $_FILES['grade_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "File upload failed with error code: " . $file['error'];
        $message_type = 'danger';
    } else {
        $allowed_extensions = ['xlsx', 'xls'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $message = "Invalid file format. Please upload an Excel file (.xlsx or .xls).";
            $message_type = 'danger';
        } else {
            try {
                // Load the spreadsheet
                $spreadsheet = IOFactory::load($file['tmp_name']);
                
                // Determine which sheet to use based on quarter selection
                $quarter_sheet_name = null;
                $sheet_names = $spreadsheet->getSheetNames();
                
                foreach ($sheet_names as $sheet_name) {
                    if (strpos(strtoupper($sheet_name), '_Q' . $quarter) !== false) {
                        $quarter_sheet_name = $sheet_name;
                        break;
                    }
                }
                
                if (!$quarter_sheet_name) {
                    throw new Exception("No sheet found for Quarter $quarter. Sheet names should contain '_Q$quarter'.");
                }
                
                $quarter_sheet = $spreadsheet->getSheetByName($quarter_sheet_name);
                
                // Extract subject and school year from the quarter sheet
                $subject_name = trim($quarter_sheet->getCell('AG7')->getValue());

                $syRaw = $quarter_sheet->getCell('AG5')->getValue();
                if (is_string($syRaw) && str_starts_with($syRaw, '=')) {
                    $syRaw = $quarter_sheet->getCell('AG5')->getOldCalculatedValue();
                }
                $school_year = trim((string)$syRaw);
                if ($school_year === '' || strtoupper($school_year) === '#REF!') {
                    throw new Exception('Could not read school year');
                }

                if (empty($subject_name) || empty($school_year)) {
                    throw new Exception("Could not extract subject or school year from the selected quarter sheet.");
                }
                
                // Get subject ID (create if doesn't exist)
                $subject_stmt = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ? AND teacherID = ?");
                $subject_stmt->bind_param("si", $subject_name, $teacher_id);
                $subject_stmt->execute();
                $subject_result = $subject_stmt->get_result();
                $subject = $subject_result->fetch_assoc();
                
                if (!$subject) {
                    $message = "Subject '$subject_name' not found for the logged-in teacher."; 
                    $message_type = 'danger';
                    throw new Exception($message);
                } else {
                    $subject_id = $subject['SubjectID'];
                }
                
                // Extract highest possible scores (row 10)
                $highest_scores = [
                    'ww1' => $quarter_sheet->getCell('F10')->getValue(),
                    'ww2' => $quarter_sheet->getCell('G10')->getValue(),
                    'ww3' => $quarter_sheet->getCell('H10')->getValue(),
                    'ww4' => $quarter_sheet->getCell('I10')->getValue(),
                    'ww5' => $quarter_sheet->getCell('J10')->getValue(),
                    'ww6' => $quarter_sheet->getCell('K10')->getValue(),
                    'ww7' => $quarter_sheet->getCell('L10')->getValue(),
                    'ww8' => $quarter_sheet->getCell('M10')->getValue(),
                    'ww9' => $quarter_sheet->getCell('N10')->getValue(),
                    'ww10' => $quarter_sheet->getCell('O10')->getValue(),
                    'ww_total' => $quarter_sheet->getCell('P10')->getValue(),
                    'ww_ps' => $quarter_sheet->getCell('Q10')->getValue(),
                    'ww_ws' => $quarter_sheet->getCell('R10')->getValue(),
                    'pt1' => $quarter_sheet->getCell('S10')->getValue(),
                    'pt2' => $quarter_sheet->getCell('T10')->getValue(),
                    'pt3' => $quarter_sheet->getCell('U10')->getValue(),
                    'pt4' => $quarter_sheet->getCell('V10')->getValue(),
                    'pt5' => $quarter_sheet->getCell('W10')->getValue(),
                    'pt6' => $quarter_sheet->getCell('X10')->getValue(),
                    'pt7' => $quarter_sheet->getCell('Y10')->getValue(),
                    'pt8' => $quarter_sheet->getCell('Z10')->getValue(),
                    'pt9' => $quarter_sheet->getCell('AA10')->getValue(),
                    'pt10' => $quarter_sheet->getCell('AB10')->getValue(),
                    'pt_total' => $quarter_sheet->getCell('AC10')->getValue(),
                    'pt_ps' => $quarter_sheet->getCell('AD10')->getValue(),
                    'pt_ws' => $quarter_sheet->getCell('AE10')->getValue(),
                    'qa1' => $quarter_sheet->getCell('AF10')->getValue(),
                    'qa_ps' => $quarter_sheet->getCell('AG10')->getValue(),
                    'qa_ws' => $quarter_sheet->getCell('AH10')->getValue()
                ];
                
                // Save highest possible scores
                $insert_hps = $conn->prepare("INSERT INTO highest_possible_score 
                    (teacherID, subjectID, quarter, school_year, ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10, ww_total, ww_ps, ww_ws, 
                    pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, pt_total, pt_ps, pt_ws, 
                    qa1, qa_ps, qa_ws, uploaded) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    ww1=VALUES(ww1), ww2=VALUES(ww2), ww3=VALUES(ww3), ww4=VALUES(ww4), ww5=VALUES(ww5),
                    ww6=VALUES(ww6), ww7=VALUES(ww7), ww8=VALUES(ww8), ww9=VALUES(ww9), ww10=VALUES(ww10),
                    ww_total=VALUES(ww_total), ww_ps=VALUES(ww_ps), ww_ws=VALUES(ww_ws),
                    pt1=VALUES(pt1), pt2=VALUES(pt2), pt3=VALUES(pt3), pt4=VALUES(pt4), pt5=VALUES(pt5),
                    pt6=VALUES(pt6), pt7=VALUES(pt7), pt8=VALUES(pt8), pt9=VALUES(pt9), pt10=VALUES(pt10),
                    pt_total=VALUES(pt_total), pt_ps=VALUES(pt_ps), pt_ws=VALUES(pt_ws),
                    qa1=VALUES(qa1), qa_ps=VALUES(qa_ps), qa_ws=VALUES(qa_ws),
                    uploaded=NOW()");
                
                $insert_hps->bind_param(
                    "iiisiiiiiiiiiiiddiiiiiiiiiiiddidd", 
                    $teacher_id, $subject_id, $quarter, $school_year,
                    $highest_scores['ww1'], $highest_scores['ww2'], $highest_scores['ww3'], $highest_scores['ww4'], $highest_scores['ww5'],
                    $highest_scores['ww6'], $highest_scores['ww7'], $highest_scores['ww8'], $highest_scores['ww9'], $highest_scores['ww10'],
                    $highest_scores['ww_total'], $highest_scores['ww_ps'], $highest_scores['ww_ws'],
                    $highest_scores['pt1'], $highest_scores['pt2'], $highest_scores['pt3'], $highest_scores['pt4'], $highest_scores['pt5'],
                    $highest_scores['pt6'], $highest_scores['pt7'], $highest_scores['pt8'], $highest_scores['pt9'], $highest_scores['pt10'],
                    $highest_scores['pt_total'], $highest_scores['pt_ps'], $highest_scores['pt_ws'],
                    $highest_scores['qa1'], $highest_scores['qa_ps'], $highest_scores['qa_ws']
                );
                
                if (!$insert_hps->execute()) {
                    throw new Exception("Error inserting highest possible scores: " . $insert_hps->error);
                }
                
                // Process student data (male students rows 12-61, female students rows 63-112)
                for ($row = 12; $row <= 112; $row++) {
                    if ($row == 62) continue; // Skip the gap between male and female
                    
                    $student_name = trim($quarter_sheet->getCell('B' . $row)->getValue());
                    if (empty($student_name)) continue;
                    
                    // Parse student name

                    if (str_contains($student_name, ',')) {
                        [$last_name, $first_name] = array_map('trim', explode(',', $student_name, 2));
                        $first_name = explode(' ', $first_name)[0];
                    } else {
                        $parts = explode(' ', $student_name);
                        $first_name = array_shift($parts);
                        $last_name  = array_pop($parts) ?: '';
                    }
                    
                    // Find student in database
                    $student_id = null;
                    $student_stmt = $conn->prepare("SELECT StudentID FROM student 
                    WHERE LOWER(TRIM(LastName)) = LOWER(TRIM(?))
                    AND LOWER(TRIM(FirstName)) = LOWER(TRIM(?))
           ");
                    $student_stmt->bind_param("ss", $last_name, $first_name);
                    $student_stmt->execute();
                    $student_result = $student_stmt->get_result();
                    
                    if ($student_result->num_rows > 0) {
                        $student = $student_result->fetch_assoc();
                        $student_id = $student['StudentID'];
                    } else {
                        $students_not_found[] = $student_name;
                        continue;
                    }
                    
                    // Extract student scores
                    $student_scores = [
                        'ww1' => $quarter_sheet->getCell('F' . $row)->getValue(),
                        'ww2' => $quarter_sheet->getCell('G' . $row)->getValue(),
                        'ww3' => $quarter_sheet->getCell('H' . $row)->getValue(),
                        'ww4' => $quarter_sheet->getCell('I' . $row)->getValue(),
                        'ww5' => $quarter_sheet->getCell('J' . $row)->getValue(),
                        'ww6' => $quarter_sheet->getCell('K' . $row)->getValue(),
                        'ww7' => $quarter_sheet->getCell('L' . $row)->getValue(),
                        'ww8' => $quarter_sheet->getCell('M' . $row)->getValue(),
                        'ww9' => $quarter_sheet->getCell('N' . $row)->getValue(),
                        'ww10' => $quarter_sheet->getCell('O' . $row)->getValue(),
                        'ww_total' => $quarter_sheet->getCell('P' . $row)->getValue(),
                        'ww_ps' => $quarter_sheet->getCell('Q' . $row)->getValue(),
                        'ww_ws' => $quarter_sheet->getCell('R' . $row)->getValue(),
                        'pt1' => $quarter_sheet->getCell('S' . $row)->getValue(),
                        'pt2' => $quarter_sheet->getCell('T' . $row)->getValue(),
                        'pt3' => $quarter_sheet->getCell('U' . $row)->getValue(),
                        'pt4' => $quarter_sheet->getCell('V' . $row)->getValue(),
                        'pt5' => $quarter_sheet->getCell('W' . $row)->getValue(),
                        'pt6' => $quarter_sheet->getCell('X' . $row)->getValue(),
                        'pt7' => $quarter_sheet->getCell('Y' . $row)->getValue(),
                        'pt8' => $quarter_sheet->getCell('Z' . $row)->getValue(),
                        'pt9' => $quarter_sheet->getCell('AA' . $row)->getValue(),
                        'pt10' => $quarter_sheet->getCell('AB' . $row)->getValue(),
                        'pt_total' => $quarter_sheet->getCell('AC' . $row)->getValue(),
                        'pt_ps' => $quarter_sheet->getCell('AD' . $row)->getValue(),
                        'pt_ws' => $quarter_sheet->getCell('AE' . $row)->getValue(),
                        'qa1' => $quarter_sheet->getCell('AF' . $row)->getValue(),
                        'qa_ps' => $quarter_sheet->getCell('AG' . $row)->getValue(),
                        'qa_ws' => $quarter_sheet->getCell('AH' . $row)->getValue(),
                        'initial_grade' => $quarter_sheet->getCell('AI' . $row)->getValue(),
                        'quarterly_grade' => $quarter_sheet->getCell('AJ' . $row)->getValue()
                    ];
                    
                    // Save student grades
                    $insert_grades = $conn->prepare("INSERT INTO grades_details 
                        (studentID, subjectID, teacherID, quarter, school_year, 
                        ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10, ww_total, ww_ps, ww_ws,
                        pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, pt_total, pt_ps, pt_ws,
                        qa1, qa_ps, qa_ws, initial_grade, quarterly_grade, uploaded)
                        VALUES (?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                        ww1=VALUES(ww1), ww2=VALUES(ww2), ww3=VALUES(ww3), ww4=VALUES(ww4), ww5=VALUES(ww5),
                        ww6=VALUES(ww6), ww7=VALUES(ww7), ww8=VALUES(ww8), ww9=VALUES(ww9), ww10=VALUES(ww10),
                        ww_total=VALUES(ww_total), ww_ps=VALUES(ww_ps), ww_ws=VALUES(ww_ws),
                        pt1=VALUES(pt1), pt2=VALUES(pt2), pt3=VALUES(pt3), pt4=VALUES(pt4), pt5=VALUES(pt5),
                        pt6=VALUES(pt6), pt7=VALUES(pt7), pt8=VALUES(pt8), pt9=VALUES(pt9), pt10=VALUES(pt10),
                        pt_total=VALUES(pt_total), pt_ps=VALUES(pt_ps), pt_ws=VALUES(pt_ws),
                        qa1=VALUES(qa1), qa_ps=VALUES(qa_ps), qa_ws=VALUES(qa_ws),
                        initial_grade=VALUES(initial_grade), quarterly_grade=VALUES(quarterly_grade),
                        uploaded=NOW()");
                    
                    $insert_grades->bind_param(
                        "iiiisiiiiiiiiiiiddiiiiiiiiiiiddidd", 
                        $student_id, $subject_id, $teacher_id, $quarter, $school_year,
                        $student_scores['ww1'], $student_scores['ww2'], $student_scores['ww3'], $student_scores['ww4'], $student_scores['ww5'],
                        $student_scores['ww6'], $student_scores['ww7'], $student_scores['ww8'], $student_scores['ww9'], $student_scores['ww10'],
                        $student_scores['ww_total'], $student_scores['ww_ps'], $student_scores['ww_ws'],
                        $student_scores['pt1'], $student_scores['pt2'], $student_scores['pt3'], $student_scores['pt4'], $student_scores['pt5'],
                        $student_scores['pt6'], $student_scores['pt7'], $student_scores['pt8'], $student_scores['pt9'], $student_scores['pt10'],
                        $student_scores['pt_total'], $student_scores['pt_ps'], $student_scores['pt_ws'],
                        $student_scores['qa1'], $student_scores['qa_ps'], $student_scores['qa_ws'],
                        $student_scores['initial_grade'], $student_scores['quarterly_grade']
                    );
                    
                    if (!$insert_grades->execute()) {
                        throw new Exception("Error inserting student grades: " . $insert_grades->error);
                    }
                    
                    $students_processed++;
                }
                
                $message = "Grades successfully uploaded! Processed {$students_processed} students for {$subject_name} - Q{$quarter} ({$school_year}).";
                
                if (!empty($students_not_found)) {
                    $message .= " " . count($students_not_found) . " students not found in database.";
                    $message_type = 'warning';
                } else {
                    $message_type = 'success';
                }
                
            } catch (Exception $e) {
                $message = "Error processing file: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Grades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #6f42c1;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
        }
        
        .upload-area {
            border: 2px dashed #d1d3e2;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            background-color: #f8f9fc;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
            background-color: #eaecf4;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .instructions {
            background-color: #f8f9fc;
            border-left: 4px solid var(--info);
            padding: 1rem;
            border-radius: 4px;
        }
        
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .student-list {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card">
                    <div class="card-header py-3">
                        <h3 class="text-center mb-0"><i class="fas fa-file-upload me-2"></i>Upload Grade File</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <strong><?php echo ucfirst($message_type); ?>!</strong> <?php echo $message; ?>
                                <?php if (!empty($students_not_found)): ?>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-<?php echo $message_type; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#missingStudents">
                                            Show missing students
                                        </button>
                                        <div class="collapse mt-2" id="missingStudents">
                                            <div class="card card-body student-list">
                                                <ul class="mb-0">
                                                    <?php foreach ($students_not_found as $student): ?>
                                                        <li><?php echo htmlspecialchars($student); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="instructions mb-4">
                            <h5><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                            <p class="mb-0">Please ensure your Excel file follows the required format with quarter sheets (MATH_Q1, MATH_Q2, etc.). The system will extract student grades and save them to the database.</p>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="quarter" class="form-label">Select Quarter:</label>
                                    <select class="form-select" id="quarter" name="quarter" required>
                                        <option value="1">Quarter 1</option>
                                        <option value="2">Quarter 2</option>
                                        <option value="3">Quarter 3</option>
                                        <option value="4">Quarter 4</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="teacher_id" class="form-label">Teacher ID:</label>
                                    <input type="text" class="form-control" id="teacher_id" name="teacher_id" value="<?php echo $teacher_id; ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="grade_file" class="form-label">Upload Grade File:</label>
                                <div class="upload-area">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <h5>Drag & Drop your file here</h5>
                                    <p class="text-muted">OR</p>
                                    <input type="file" class="form-control d-none" id="grade_file" name="grade_file" accept=".xlsx,.xls" required>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('grade_file').click()">
                                        <i class="fas fa-file-excel me-1"></i> Browse Excel Files
                                    </button>
                                    <div class="mt-2" id="file-name">No file chosen</div>
                                </div>
                                <div class="form-text">Supported formats: .xlsx, .xls</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload me-2"></i> Upload and Process
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('grade_file').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').textContent = fileName;
        });
        
        // Set current quarter based on month
        const currentMonth = new Date().getMonth() + 1;
        let currentQuarter = 1;
        if (currentMonth >= 4 && currentMonth <= 6) currentQuarter = 2;
        else if (currentMonth >= 7 && currentMonth <= 9) currentQuarter = 3;
        else if (currentMonth >= 10) currentQuarter = 4;
        
        document.getElementById('quarter').value = currentQuarter;
    </script>
</body>
</html>