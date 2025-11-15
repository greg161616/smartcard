<?php
session_start();
require_once '../config.php';
// Logging helper
require_once __DIR__ . '/../api/log_helper.php';


function getTeacherDisplayName($conn, $teacher_id) {
    if (empty($teacher_id)) return 'Unknown Teacher';
    try {
        // Try UserID in teacher table first
        $q = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE UserID = ? LIMIT 1");
        if ($q) {
            $q->bind_param('i', $teacher_id);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                $q->close();
                $name = trim($row['fname'] . ' ' . $row['lname']);
                if ($name !== '') return $name . " (ID: $teacher_id)";
            }
            $q->close();
        }

        // Try teacherID column
        $q2 = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE teacherID = ? LIMIT 1");
        if ($q2) {
            $q2->bind_param('i', $teacher_id);
            $q2->execute();
            $r2 = $q2->get_result();
            if ($r2 && $r2->num_rows > 0) {
                $row2 = $r2->fetch_assoc();
                $q2->close();
                $name2 = trim($row2['fname'] . ' ' . $row2['lname']);
                if ($name2 !== '') return $name2 . " (ID: $teacher_id)";
            }
            $q2->close();
        }

        // Try user table as last resort
        $q3 = $conn->prepare("SELECT COALESCE(FirstName, '') AS fname, COALESCE(LastName, '') AS lname, COALESCE(Email, '') AS email FROM user WHERE UserID = ? LIMIT 1");
        if ($q3) {
            $q3->bind_param('i', $teacher_id);
            $q3->execute();
            $r3 = $q3->get_result();
            if ($r3 && $r3->num_rows > 0) {
                $row3 = $r3->fetch_assoc();
                $q3->close();
                $name3 = trim($row3['fname'] . ' ' . $row3['lname']);
                if ($name3 !== '') return $name3 . " (ID: $teacher_id)";
                if (!empty($row3['email'])) return $row3['email'] . " (ID: $teacher_id)";
            }
            $q3->close();
        }
    } catch (Exception $ex) {
        // ignore and fall back
    }
    return "Teacher #$teacher_id";
}

/**
 * Return teacher's name only (no ID) for use in user-facing messages.
 */
function getTeacherName($conn, $teacher_id) {
    if (empty($teacher_id)) return 'Unknown Teacher';
    try {
        $q = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE UserID = ? LIMIT 1");
        if ($q) {
            $q->bind_param('i', $teacher_id);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                $q->close();
                $name = trim($row['fname'] . ' ' . $row['lname']);
                if ($name !== '') return $name;
            }
            $q->close();
        }

        $q2 = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE teacherID = ? LIMIT 1");
        if ($q2) {
            $q2->bind_param('i', $teacher_id);
            $q2->execute();
            $r2 = $q2->get_result();
            if ($r2 && $r2->num_rows > 0) {
                $row2 = $r2->fetch_assoc();
                $q2->close();
                $name2 = trim($row2['fname'] . ' ' . $row2['lname']);
                if ($name2 !== '') return $name2;
            }
            $q2->close();
        }

        $q3 = $conn->prepare("SELECT COALESCE(FirstName, '') AS fname, COALESCE(LastName, '') AS lname, COALESCE(Email, '') AS email FROM user WHERE UserID = ? LIMIT 1");
        if ($q3) {
            $q3->bind_param('i', $teacher_id);
            $q3->execute();
            $r3 = $q3->get_result();
            if ($r3 && $r3->num_rows > 0) {
                $row3 = $r3->fetch_assoc();
                $q3->close();
                $name3 = trim($row3['fname'] . ' ' . $row3['lname']);
                if ($name3 !== '') return $name3;
                if (!empty($row3['email'])) return $row3['email'];
            }
            $q3->close();
        }
    } catch (Exception $ex) {
        // ignore and fall back
    }
    return 'Teacher';
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON response header
header('Content-Type: application/json');

// Include PhpSpreadsheet at the top level
include_once '../vendor/autoload.php';
    
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    echo json_encode([
        'message' => "Teacher not logged in. Please log in again.",
        'message_type' => 'danger',
        'students_not_found' => [],
        'students_not_enrolled' => [],
        'students_processed' => 0
    ]);
    exit;
}

class SummaryReadFilter implements IReadFilter {
    private int $start;
    private int $end;

    public function __construct(int $s, int $e) {
        $this->start = $s;
        $this->end   = $e;
    }

    public function readCell($col, $row, $wsName = ''): bool {
        // Only read from the summary sheet
        if ($wsName !== 'SUMMARY OF QUARTERLY GRADES') {
            return false;
        }
        // Keep header rows for metadata
        if ($row <= 11) {
            $keep = ['A','B','F','J','N','R','V','W'];
            // AG row 7 contains the subject name and E row 8 contains school year
            if ($row === 7)  $keep[] = 'AG';
            if ($row === 8)  $keep[] = 'E';
            return in_array($col, $keep, true);
        }
        // Only keep rows within the student section and a few specific columns
        return $row >= $this->start && $row <= $this->end
            && in_array($col, ['A','B','F','J','N','R','V','W'], true);
    }
}

function unwrapCellValue($cell) {
    $v = $cell->getValue();
    return (is_string($v) && str_starts_with($v, '='))
        ? $cell->getOldCalculatedValue()
        : $v;
}

function getSummaryName($cell): string {
    return trim((string)unwrapCellValue($cell));
}

function getSummaryGrade($cell) {
    $v = unwrapCellValue($cell);
    if (is_numeric($v) && $v >= 0 && $v <= 100) {
        return $v;
    }
    if (is_string($v) && str_contains($v, '%')) {
        $n = str_replace('%', '', $v);
        return is_numeric($n) ? (float)$n : null;
    }
    return null;
}

$teacher_id = $_SESSION['teacher_id'];

// Check if the request method is POST and file is uploaded
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['grade_file'])) {
    echo json_encode([
        'message' => "Invalid request method or no file uploaded.",
        'message_type' => 'danger',
        'students_not_found' => [],
        'students_not_enrolled' => [],
        'students_processed' => 0
    ]);
    exit;
}

// Validate quarter
$quarter = isset($_POST['quarter']) ? (int)$_POST['quarter'] : 0;
if ($quarter < 1 || $quarter > 4) {
    echo json_encode([
        'message' => "Invalid quarter selected.",
        'message_type' => 'danger',
        'students_not_found' => [],
        'students_not_enrolled' => [],
        'students_processed' => 0
    ]);
    exit;
}

$file = $_FILES['grade_file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
        UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
        UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
        UPLOAD_ERR_NO_FILE => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
    ];

    $errorMessage = isset($errorMessages[$file['error']])
        ? $errorMessages[$file['error']]
        : "Unknown upload error (Code: {$file['error']})";
        
    echo json_encode([
        'message' => "File upload failed: $errorMessage",
        'message_type' => 'danger',
        'students_not_found' => [],
        'students_not_enrolled' => [],
        'students_processed' => 0
    ]);
    exit;
}

// Validate file extension
$allowed_extensions = ['xlsx', 'xls'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode([
        'message' => "Invalid file format. Please upload an Excel file (.xlsx or .xls).",
        'message_type' => 'danger',
        'students_not_found' => [],
        'students_not_enrolled' => [],
        'students_processed' => 0
    ]);
    exit;
}

// Check file size (limit to 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode([
        'message' => "File size exceeds the maximum limit of 10MB.",
        'message_type' => 'danger',
        'students_not_found' => [],
        'students_not_enrolled' => [],
        'students_processed' => 0
    ]);
    exit;
}

// Initialize response
$response = [
    'message' => '',
    'message_type' => 'info',
    'students_not_found' => [],
    'students_not_enrolled' => [],
    'students_processed' => 0
];

try {
    // Load the spreadsheet
    $reader = IOFactory::createReaderForFile($file['tmp_name']);
    $reader->setReadDataOnly(true);
    
    // Try to load the spreadsheet with error handling
    try {
        $spreadsheet = $reader->load($file['tmp_name']);
    } catch (Exception $e) {
        throw new Exception("Failed to load the Excel file: " . $e->getMessage());
    }

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
    if (!$quarter_sheet) {
        throw new Exception("Could not access the quarter sheet.");
    }

    // FIX: Use getCalculatedValue() instead of getValue() for formula cells
    // Extract subject and school year from the quarter sheet
    $subject_name = trim($quarter_sheet->getCell('AG7')->getCalculatedValue());
    if (empty($subject_name) || strtoupper($subject_name) === '#REF!') {
        throw new Exception('Could not read subject name or subject not assigned from cell');
    }

    $school_year = trim($quarter_sheet->getCell('AG5')->getCalculatedValue());
    if (empty($school_year) || strtoupper($school_year) === '#REF!') {
        throw new Exception('Could not read school year or school year not assigned from cell');
    }
    
    // Get subject ID
    $subject_stmt = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ? AND teacherID = ?");
    $subject_stmt->bind_param("si", $subject_name, $teacher_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    $subject = $subject_result->fetch_assoc();
    
    if (!$subject) {
        throw new Exception("Subject '$subject_name' not found for the logged-in teacher.");
    }
    
    $subject_id = $subject['SubjectID'];
    // --- Validate Excel metadata against database (grade level / section / teacher) ---
    // We'll attempt to find Grade Level, Section and Teacher name in the top header rows (1-11).
    // Assumption: the workbook includes human-readable metadata in header rows (e.g. "Grade 7 - Section A", "Teacher: Juan D. Reyes").
    $headerText = '';
    $headerCols = array_merge(range('A','Z'), ['AA','AB','AC','AD','AE','AF','AG']);
    for ($r = 1; $r <= 11; $r++) {
        foreach ($headerCols as $c) {
            try {
                $v = trim((string)$quarter_sheet->getCell($c . $r)->getCalculatedValue());
            } catch (Throwable $t) {
                $v = '';
            }
            if ($v !== '') $headerText .= ' ' . $v;
        }
    }

    $headerLower = strtolower($headerText);

    // Try to extract grade level (numeric) and section (text)
    $excelGrade = null;
    $excelSection = null;
    $excelTeacherName = null;

    if (preg_match('/grade\s*[:\-]?\s*(\d{1,2})/i', $headerText, $m)) {
        $excelGrade = (int)$m[1];
    } elseif (preg_match('/grade\s*(\d{1,2})/i', $headerText, $m2)) {
        $excelGrade = (int)$m2[1];
    }

    if (preg_match('/section\s*[:\-]?\s*([A-Za-z0-9\-\s]+)/i', $headerText, $m)) {
        $excelSection = trim($m[1]);
    }

    if (preg_match('/teacher\s*[:\-]?\s*([A-Za-z\.\'\"\-\s]+)/i', $headerText, $m)) {
        $excelTeacherName = trim($m[1]);
    }

    // Get subject's assigned section and grade level from DB
    $subInfoStmt = $conn->prepare("SELECT sub.SubjectID, sub.secID, COALESCE(sub.GradeLevel, '') AS GradeLevel, COALESCE(sec.SectionName, '') AS SectionName FROM subject sub LEFT JOIN section sec ON sub.secID = sec.SectionID WHERE sub.SubjectID = ? LIMIT 1");
    $subInfoStmt->bind_param('i', $subject_id);
    $subInfoStmt->execute();
    $subInfoRes = $subInfoStmt->get_result();
    $subInfo = $subInfoRes->fetch_assoc();
    $subInfoStmt->close();

    // Compare teacher (must match logged-in teacher) - already validated by query, but also check excel teacher name if present
    if ($excelTeacherName) {
        $dbTeacherName = getTeacherName($conn, $teacher_id);
        // Normalize names: lowercase, remove dots
        $a = strtolower(str_replace('.', '', $excelTeacherName));
        $b = strtolower(str_replace('.', '', $dbTeacherName));
        if (!str_contains($b, trim($a)) && !str_contains($a, trim($b))) {
            throw new Exception("Teacher credentials ('{$excelTeacherName}') does not match logged-in teacher ({$dbTeacherName}).");
        }
    }

    // Compare grade level if available in Excel
    if ($excelGrade !== null && $subInfo && $subInfo['GradeLevel'] !== '') {
        $dbGrade = (int)$subInfo['GradeLevel'];
        if ($dbGrade !== $excelGrade) {
            throw new Exception("Grade level on your spreadsheet Grade {$excelGrade} does not match the subject's assigned grade level (Grade {$dbGrade}).");
        }
    }

    // Compare section if available in Excel
    if ($excelSection !== null && $subInfo && $subInfo['SectionName'] !== '') {
        // normalize whitespace and case
            // Normalize excel section: remove leading grade numbers (e.g. "7-" or "Grade 7"), remove words like 'teacher'/'adviser',
            // strip punctuation, and collapse whitespace. Example: "7-Amihan TEACHER" -> "amihan"
            $excelSecClean = preg_replace('/grade\s*\d+/i', '', $excelSection); // remove 'Grade 7'
            $excelSecClean = preg_replace('/^\s*\d+[-\s]*/', '', $excelSecClean); // remove leading digits like '7-' or '7 '
            $excelSecClean = preg_replace('/\b(section|teacher|adviser|teacher:|adviser:)\b/i', '', $excelSecClean);
            $excelSecClean = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $excelSecClean);
            $excelSecClean = strtolower(trim(preg_replace('/\s+/', ' ', $excelSecClean)));

            // Normalize DB section name similarly for robust comparison
            $dbSectionRaw = $subInfo['SectionName'];
            $dbSecClean = preg_replace('/grade\s*\d+/i', '', $dbSectionRaw);
            $dbSecClean = preg_replace('/^\s*\d+[-\s]*/', '', $dbSecClean);
            $dbSecClean = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $dbSecClean);
            $dbSecClean = strtolower(trim(preg_replace('/\s+/', ' ', $dbSecClean)));

            if ($excelSecClean !== '' && $dbSecClean !== '' && $excelSecClean !== $dbSecClean) {
                // Use cleaned names in message to avoid exposing raw IDs
                throw new Exception("Section in Excel ('{$excelSection}') does not match the subject's assigned section ('{$subInfo['SectionName']}').");
        }
    }
    // Log start of grade upload (teacher-friendly)
    try {
        $teacher_display = getTeacherDisplayName($conn, $teacher_id);
        $startMessage = "Started uploading grades for {$subject_name} — Q{$quarter} ({$school_year}).";
        log_system_action($conn, 'Grade Upload Started', $teacher_id, [
            'uploaded_by' => $teacher_display,
            'message' => $startMessage,
            'file_name' => $file['name']
        ], 'info');
    } catch (Exception $logEx) {
        // Non-fatal: continue even if logging fails
        error_log('Logging failed (start): ' . $logEx->getMessage());
    }
    
    // Extract highest possible scores (row 10)
    // FIX: Use getCalculatedValue() for formula-based cells
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
        'ww_total' => $quarter_sheet->getCell('P10')->getCalculatedValue(), // Formula cell
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
        'pt_total' => $quarter_sheet->getCell('AC10')->getCalculatedValue(), // Formula cell
        'pt_ps' => $quarter_sheet->getCell('AD10')->getValue(),
        'pt_ws' => $quarter_sheet->getCell('AE10')->getValue(),
        'qa1' => $quarter_sheet->getCell('AF10')->getValue(),
        'qa_ps' => $quarter_sheet->getCell('AG10')->getValue(),
        'qa_ws' => $quarter_sheet->getCell('AH10')->getValue()
    ];
    
    // Prepare the student queries
    $student_stmt = $conn->prepare("SELECT StudentID FROM student 
        WHERE LOWER(TRIM(LastName)) = LOWER(?) AND LOWER(TRIM(FirstName)) = LOWER(?)");
    
    // NEW: Check if student is enrolled in this subject
    $enrollment_stmt = $conn->prepare("SELECT StudentID FROM section_enrollment 
        WHERE StudentID = ? AND SchoolYear = ?");
    
    // FIRST PASS: Validate all students before inserting any data
    $students_to_process = [];
    $students_not_found = [];
    $students_not_enrolled = [];
    
    // Process student data (male students rows 12-61, female students rows 63-112)
    for ($row = 12; $row <= 112; $row++) {
        if ($row == 62) continue; // Skip the gap between male and female

        $student_name = trim($quarter_sheet->getCell('B' . $row)->getCalculatedValue());
        if (empty($student_name)) continue;
        
        // Parse student name - improved logic
        $last_name = '';
        $first_name = '';
        
        // Handle different name formats
        if (str_contains($student_name, ',')) {
            // Format: "Lastname, Firstname"
            $name_parts = array_map('trim', explode(',', $student_name, 2));
            $last_name = $name_parts[0];
            $first_name = $name_parts[1];
            
            // Extract just the first name if there are multiple names after the comma
            if (str_contains($first_name, ' ')) {
                $first_name_parts = explode(' ', $first_name);
                $first_name = $first_name_parts[0];
            }
        } else {
            // Format: "Firstname Lastname" or "Firstname Middlename Lastname"
            $name_parts = explode(' ', $student_name);
            $first_name = array_shift($name_parts);
            $last_name = implode(' ', $name_parts);
        }
        
        // Clean up names
        $last_name = preg_replace('/\s+/', ' ', trim($last_name));
        $first_name = preg_replace('/\s+/', ' ', trim($first_name));
        
        // Skip if names are empty after cleaning
        if (empty($last_name) || empty($first_name)) {
            $students_not_found[] = $student_name . " (Invalid name format)";
            continue;
        }
        
        // Find student in database
        $student_id = null;
        $student_stmt->bind_param("ss", $last_name, $first_name);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows > 0) {
            $student = $student_result->fetch_assoc();
            $student_id = $student['StudentID'];
        } else {
            $students_not_found[] = "$student_name (Parsed as: $first_name $last_name)";
            continue;
        }
        
        // Check if student is enrolled in this subject for the current school year
        $enrollment_stmt->bind_param("is", $student_id, $school_year);
        $enrollment_stmt->execute();
        $enrollment_result = $enrollment_stmt->get_result();
        
        if ($enrollment_result->num_rows === 0) {
            $students_not_enrolled[] = "$student_name (Not enrolled in $subject_name for $school_year)";
            continue;
        }
        
        // Extract student scores with validation
        $student_scores = [];
        $score_cells = [
            'ww1' => 'F', 'ww2' => 'G', 'ww3' => 'H', 'ww4' => 'I', 'ww5' => 'J',
            'ww6' => 'K', 'ww7' => 'L', 'ww8' => 'M', 'ww9' => 'N', 'ww10' => 'O',
            'ww_total' => 'P', 'ww_ps' => 'Q', 'ww_ws' => 'R',
            'pt1' => 'S', 'pt2' => 'T', 'pt3' => 'U', 'pt4' => 'V', 'pt5' => 'W',
            'pt6' => 'X', 'pt7' => 'Y', 'pt8' => 'Z', 'pt9' => 'AA', 'pt10' => 'AB',
            'pt_total' => 'AC', 'pt_ps' => 'AD', 'pt_ws' => 'AE',
            'qa1' => 'AF', 'qa_ps' => 'AG', 'qa_ws' => 'AH',
            'initial_grade' => 'AI', 'quarterly_grade' => 'AJ'
        ];
        
        // NEW: Validate that no required scores are null
        foreach ($score_cells as $key => $cell) {
            $value = $quarter_sheet->getCell($cell . $row)->getCalculatedValue();
            
            // Check for null/empty values in required fields
            if (($value === null || $value === '') && !in_array($key, ['ww_total', 'pt_total'])) {
                throw new Exception("Please try again. A column in your Excel sheet cannot be null or have no score. Please make sure to fill all the student scores before uploading.");
            }
            
            // Validate numeric values
            if (in_array($key, ['ww_total', 'ww_ps', 'ww_ws', 'pt_total', 'pt_ps', 'pt_ws', 'qa_ps', 'qa_ws', 'initial_grade', 'quarterly_grade'])) {
                $student_scores[$key] = is_numeric($value) ? $value : 0;
            } else {
                $student_scores[$key] = $value;
            }
        }
        
        // Store student data for processing
        $students_to_process[] = [
            'student_id' => $student_id,
            'scores' => $student_scores
        ];
    }
    
    // If there are any missing students or students not enrolled, don't insert any data
    if (!empty($students_not_found) || !empty($students_not_enrolled)) {
        $response['students_not_found'] = $students_not_found;
        $response['students_not_enrolled'] = $students_not_enrolled;
        
        $message = "Upload failed due to the following issues: ";
        if (!empty($students_not_found)) {
            $message .= count($students_not_found) . " students not found. ";
        }
        if (!empty($students_not_enrolled)) {
            $message .= count($students_not_enrolled) . " students not enrolled in this subject.";
        }
        
        echo json_encode([
            'message' => $message,
            'message_type' => 'danger',
            'students_not_found' => $students_not_found,
            'students_not_enrolled' => $students_not_enrolled,
            'students_processed' => 0
        ]);
        exit;
    }
    
    // SECOND PASS: Only insert data if all students are valid
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

    // Insert student grades
    $insert_grades_details = $conn->prepare(
        "INSERT INTO grades_details 
            (studentID, subjectID, teacherID, quarter, school_year,
             ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10,
             ww_total, ww_ps, ww_ws,
             pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10,
             pt_total, pt_ps, pt_ws,
             qa1, qa_ps, qa_ws,
             initial_grade, quarterly_grade, uploaded)
         VALUES (?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             ww1=VALUES(ww1), ww2=VALUES(ww2), ww3=VALUES(ww3), ww4=VALUES(ww4), ww5=VALUES(ww5),
             ww6=VALUES(ww6), ww7=VALUES(ww7), ww8=VALUES(ww8), ww9=VALUES(ww9), ww10=VALUES(ww10),
             ww_total=VALUES(ww_total), ww_ps=VALUES(ww_ps), ww_ws=VALUES(ww_ws),
             pt1=VALUES(pt1), pt2=VALUES(pt2), pt3=VALUES(pt3), pt4=VALUES(pt4), pt5=VALUES(pt5),
             pt6=VALUES(pt6), pt7=VALUES(pt7), pt8=VALUES(pt8), pt9=VALUES(pt9), pt10=VALUES(pt10),
             pt_total=VALUES(pt_total), pt_ps=VALUES(pt_ps), pt_ws=VALUES(pt_ws),
             qa1=VALUES(qa1), qa_ps=VALUES(qa_ps), qa_ws=VALUES(qa_ws),
             initial_grade=VALUES(initial_grade), quarterly_grade=VALUES(quarterly_grade),
             uploaded=NOW()"
    );

    // Process all validated students
    foreach ($students_to_process as $student_data) {
        $student_id = $student_data['student_id'];
        $student_scores = $student_data['scores'];
        
        $types = 'iiiis';
        $types .= str_repeat('d', 13); // ww scores
        $types .= str_repeat('d', 13); // pt scores
        $types .= str_repeat('d', 3);  // qa scores
        $types .= 'dd';                // initial and quarterly grades

        $params = [ $student_id, $subject_id, $teacher_id, $quarter, $school_year,
            $student_scores['ww1'], $student_scores['ww2'], $student_scores['ww3'], $student_scores['ww4'], $student_scores['ww5'],
            $student_scores['ww6'], $student_scores['ww7'], $student_scores['ww8'], $student_scores['ww9'], $student_scores['ww10'],
            $student_scores['ww_total'], $student_scores['ww_ps'], $student_scores['ww_ws'],
            $student_scores['pt1'], $student_scores['pt2'], $student_scores['pt3'], $student_scores['pt4'], $student_scores['pt5'],
            $student_scores['pt6'], $student_scores['pt7'], $student_scores['pt8'], $student_scores['pt9'], $student_scores['pt10'],
            $student_scores['pt_total'], $student_scores['pt_ps'], $student_scores['pt_ws'],
            $student_scores['qa1'], $student_scores['qa_ps'], $student_scores['qa_ws'],
            $student_scores['initial_grade'], $student_scores['quarterly_grade']
        ];

        if (!$insert_grades_details->bind_param($types, ...$params) || !$insert_grades_details->execute()) {
            // Check if it's a null constraint error and provide friendly message
            if (strpos($insert_grades_details->error, 'cannot be null') !== false) {
                throw new Exception("Please try again. A column in your Excel sheet cannot be null or have no score. Please make sure to fill all the student scores before uploading.");
            }
            error_log("Error inserting grades for student $student_id: " . $insert_grades_details->error);
        } else {
            $response['students_processed']++;
        }
    }
    
    $summary_errors = [];
    try {
        $readerSummary = IOFactory::createReaderForFile($file['tmp_name']);
        $readerSummary->setReadDataOnly(true);

        // Determine if the workbook contains the summary sheet
        $summaryInfo = null;
        foreach ($readerSummary->listWorksheetInfo($file['tmp_name']) as $wsInfo) {
            if ($wsInfo['worksheetName'] === 'SUMMARY OF QUARTERLY GRADES') {
                $summaryInfo = $wsInfo;
                break;
            }
        }

        if ($summaryInfo) {
            // Limit reading to only the summary sheet and relevant cells
            $readerSummary->setLoadSheetsOnly(['SUMMARY OF QUARTERLY GRADES'])
                          ->setReadFilter(new SummaryReadFilter(12, $summaryInfo['totalRows']));
            $wbSummary = $readerSummary->load($file['tmp_name']);
            $shSummary = $wbSummary->getActiveSheet();
            $lastRowSum = $shSummary->getHighestDataRow();
            $currentSection = '';

            for ($sr = 12; $sr <= $lastRowSum; $sr++) {
                $sec = strtoupper(trim((string) unwrapCellValue($shSummary->getCell('A' . $sr))));
                if (in_array($sec, ['MALE', 'FEMALE'], true)) {
                    $currentSection = $sec;
                    continue;
                }

                $fullName = getSummaryName($shSummary->getCell('B' . $sr));
                // Skip blank rows and headers
                if (
                    $fullName === '' ||
                    strcasecmp($fullName, 'MALE') === 0 ||
                    strcasecmp($fullName, 'FEMALE') === 0 ||
                    is_numeric($fullName)
                ) {
                    continue;
                }

                // Parse the name into last and first components
                if (str_contains($fullName, ',')) {
                    [$lastNamePart, $firstMiddle] = array_map('trim', explode(',', $fullName, 2));
                    $firstNamePart = explode(' ', $firstMiddle)[0];
                } else {
                    $partsName = explode(' ', $fullName);
                    $firstNamePart = array_shift($partsName);
                    $lastNamePart = array_pop($partsName) ?: '';
                }

                // Attempt to find the student in the database
                $studentStmt = $conn->prepare(
                    "SELECT StudentID FROM student WHERE LOWER(TRIM(LastName)) = LOWER(TRIM(?)) AND LOWER(TRIM(FirstName)) = LOWER(TRIM(?))"
                );
                $studentStmt->bind_param('ss', $lastNamePart, $firstNamePart);
                $studentStmt->execute();
                $studentRes = $studentStmt->get_result();
                if ($studentRes->num_rows > 0) {
                    $sidRow = $studentRes->fetch_assoc()['StudentID'];
                } else {
                    // Collect missing students separately; don't treat as fatal
                    $summary_errors[] = "Student not found in summary: $fullName (Section $currentSection)";
                    $studentStmt->close();
                    continue;
                }
                $studentStmt->close();

                // Check if student is enrolled in this subject for summary grades too
                $enrollment_stmt_summary = $conn->prepare("SELECT StudentID FROM section_enrollment 
                    WHERE StudentID = ?  AND SchoolYear = ?");
                $enrollment_stmt_summary->bind_param("is", $sidRow, $school_year);
                $enrollment_stmt_summary->execute();
                $enrollment_result_summary = $enrollment_stmt_summary->get_result();
                
                if ($enrollment_result_summary->num_rows === 0) {
                    $summary_errors[] = "Student not enrolled in subject: $fullName (Section $currentSection)";
                    $enrollment_stmt_summary->close();
                    continue;
                }
                $enrollment_stmt_summary->close();

                // Quarter column mapping
                $quarterColumnMap = [
                    1 => 'F', // Q1 column
                    2 => 'J', // Q2 column
                    3 => 'N', // Q3 column
                    4 => 'R'  // Q4 column
                ];
                
                $quarterColumn = $quarterColumnMap[$quarter];
                $quarterGrade = getSummaryGrade($shSummary->getCell($quarterColumn . $sr));
                
                // NEW: Only get final grade if we're processing 4th quarter
                $finalGrade = null;
                if ($quarter == 4) {
                    $finalGrade = getSummaryGrade($shSummary->getCell('V' . $sr));
                }

                // Prepare insert/update for the grades table
                if ($quarter == 4) {
                    // For 4th quarter, update both Q4 and Final grade
                    $gradeStmt = $conn->prepare(
                        "INSERT INTO grades 
                            (student_id, subject, school_year, Q1, Q2, Q3, Q4, Final, uploadedby, uploaded)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE
                            Q4 = VALUES(Q4),
                            Final = VALUES(Final),
                            uploadedby = VALUES(uploadedby),
                            uploaded = NOW()"
                    );
                    
                    // Get existing grades for other quarters to preserve them
                    $existingGrades = $conn->prepare("SELECT Q1, Q2, Q3 FROM grades WHERE student_id = ? AND subject = ? AND school_year = ?");
                    $existingGrades->bind_param("iis", $sidRow, $subject_id, $school_year);
                    $existingGrades->execute();
                    $existingResult = $existingGrades->get_result();
                    
                    if ($existingResult->num_rows > 0) {
                        $existing = $existingResult->fetch_assoc();
                        $q1 = $existing['Q1'];
                        $q2 = $existing['Q2'];
                        $q3 = $existing['Q3'];
                    } else {
                        $q1 = null;
                        $q2 = null;
                        $q3 = null;
                    }
                    $existingGrades->close();
                    
                    $gradeStmt->bind_param(
                        'iisdddddi',
                        $sidRow,
                        $subject_id,
                        $school_year,
                        $q1,
                        $q2,
                        $q3,
                        $quarterGrade,
                        $finalGrade,
                        $teacher_id
                    );
                } else {
                    // For quarters 1-3, only update the specific quarter
                    $gradeStmt = $conn->prepare(
                        "INSERT INTO grades 
                            (student_id, subject, school_year, Q1, Q2, Q3, Q4, Final, uploadedby, uploaded)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE
                            Q$quarter = VALUES(Q$quarter),
                            uploadedby = VALUES(uploadedby),
                            uploaded = NOW()"
                    );
                    
                    // Set parameters - only update the specific quarter, keep others as they are
                    $q1 = ($quarter == 1) ? $quarterGrade : null;
                    $q2 = ($quarter == 2) ? $quarterGrade : null;
                    $q3 = ($quarter == 3) ? $quarterGrade : null;
                    $q4 = null;
                    $final = null;
                    
                    $gradeStmt->bind_param(
                        'iisdddddi',
                        $sidRow,
                        $subject_id,
                        $school_year,
                        $q1,
                        $q2,
                        $q3,
                        $q4,
                        $final,
                        $teacher_id
                    );
                }
                
                if (!$gradeStmt->execute()) {
                    // Check for null constraint errors and provide friendly message
                    if (strpos($gradeStmt->error, 'cannot be null') !== false) {
                        throw new Exception("Please try again. A column in your Excel sheet cannot be null or have no score. Please make sure to fill all the student scores before uploading.");
                    }
                    $summary_errors[] = "Database error updating grades for $fullName: " . $gradeStmt->error;
                }
                $gradeStmt->close();
            }
            $wbSummary->disconnectWorksheets();
            unset($wbSummary);
        }
    } catch (Exception $summaryException) {
        // Check if it's a null constraint error and provide friendly message
        if (strpos($summaryException->getMessage(), 'cannot be null') !== false) {
            throw new Exception("Please try again. A column in your Excel sheet cannot be null or have no score. Please make sure to fill all the student scores before uploading.");
        }
        $summary_errors[] = $summaryException->getMessage();
    }   

    $student_stmt->close();
    $enrollment_stmt->close();
    $insert_grades_details->close();

    // Build response message
    $response['message'] = "Grades successfully uploaded! Processed {$response['students_processed']} students for {$subject_name} - Q{$quarter} ({$school_year}).";
    $response['message_type'] = 'success';
    // Log successful upload
    try {
        $teacher_display = getTeacherDisplayName($conn, $teacher_id);
        $successMessage = "Successfully uploaded {$response['students_processed']} grades for {$subject_name} — Q{$quarter} ({$school_year}).";
        log_system_action($conn, 'Grade Upload Completed', $teacher_id, [
            'uploaded_by' => $teacher_display,
            'message' => $successMessage,
            'file_name' => $file['name'],
            'students_processed' => $response['students_processed']
        ], 'success');
    } catch (Exception $logEx) {
        error_log('Logging failed (success): ' . $logEx->getMessage());
    }

} catch (Exception $e) {
    // Check if it's a null constraint error and provide friendly message
    if (strpos($e->getMessage(), 'cannot be null') !== false) {
        $response['message'] = "Please try again. A column in your Excel sheet cannot be null or have no score. Please make sure to fill all the student scores before uploading.";
    } else {
        $response['message'] = "Error processing file: " . $e->getMessage();
    }
    $response['message_type'] = 'danger';
    // Log failure
    try {
        $tid = $teacher_id ?? null;
        $teacher_display = getTeacherDisplayName($conn, $tid);
        $subj = isset($subject_name) ? $subject_name : 'Unknown subject';
        $q = isset($quarter) ? $quarter : 'N/A';
        $sy = isset($school_year) ? $school_year : '';
        $friendly = "Failed to upload grades for {$subj} — Q{$q} ({$sy}).";
        log_system_action($conn, 'Grade Upload Failed', $tid, [
            'uploaded_by' => $teacher_display,
            'message' => $friendly,
            'file_name' => isset($file['name']) ? $file['name'] : null,
            'technical' => $e->getMessage()
        ], 'error');
    } catch (Exception $logEx) {
        error_log('Logging failed (error): ' . $logEx->getMessage());
    }
    error_log("Grade upload error: " . $e->getMessage());
}

echo json_encode($response);