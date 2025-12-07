<?php
session_start();
require_once '../config.php';
// Logging helper
require_once __DIR__ . '/../api/log_helper.php';

function getTeacherDisplayName($conn, $teacher_id) {
    if (empty($teacher_id)) return 'Unknown Teacher';
    try {
        // Query teacher table using TeacherID
        $q = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE TeacherID = ? LIMIT 1");
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

        // If not found by TeacherID, try user table via UserID
        $q2 = $conn->prepare("SELECT t.TeacherID, COALESCE(t.fName, '') AS fname, COALESCE(t.lName, '') AS lname 
                             FROM teacher t 
                             WHERE t.UserID = ? LIMIT 1");
        if ($q2) {
            $q2->bind_param('i', $teacher_id);
            $q2->execute();
            $r2 = $q2->get_result();
            if ($r2 && $r2->num_rows > 0) {
                $row2 = $r2->fetch_assoc();
                $q2->close();
                $name2 = trim($row2['fname'] . ' ' . $row2['lname']);
                if ($name2 !== '') return $name2 . " (ID: {$row2['TeacherID']})";
            }
            $q2->close();
        }

        // Try user table as last resort
        $q3 = $conn->prepare("SELECT COALESCE(Email, '') AS email FROM user WHERE UserID = ? LIMIT 1");
        if ($q3) {
            $q3->bind_param('i', $teacher_id);
            $q3->execute();
            $r3 = $q3->get_result();
            if ($r3 && $r3->num_rows > 0) {
                $row3 = $r3->fetch_assoc();
                $q3->close();
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
        $q = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE TeacherID = ? LIMIT 1");
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

        $q2 = $conn->prepare("SELECT COALESCE(t.fName, '') AS fname, COALESCE(t.lName, '') AS lname 
                             FROM teacher t 
                             WHERE t.UserID = ? LIMIT 1");
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

        $q3 = $conn->prepare("SELECT COALESCE(Email, '') AS email FROM user WHERE UserID = ? LIMIT 1");
        if ($q3) {
            $q3->bind_param('i', $teacher_id);
            $q3->execute();
            $r3 = $q3->get_result();
            if ($r3 && $r3->num_rows > 0) {
                $row3 = $r3->fetch_assoc();
                $q3->close();
                if (!empty($row3['email'])) return $row3['email'];
            }
            $q3->close();
        }
    } catch (Exception $ex) {
        // ignore and fall back
    }
    return 'Teacher';
}

// Improved name parsing function
function parseStudentName($full_name) {
    $full_name = trim(preg_replace('/\s+/', ' ', $full_name));
    
    // Default result
    $result = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'parsed_format' => $full_name
    ];
    
    // Remove any suffixes (Jr., Sr., II, III, etc.)
    $suffixes = [' JR', ' SR', ' II', ' III', ' IV', ' V', ' JR.', ' SR.', ' I', ' II.', ' III.', ' IV.'];
    $full_name = str_ireplace($suffixes, '', $full_name);
    
    // Format 1: "Lastname, Firstname Middlename"
    if (str_contains($full_name, ',')) {
        $parts = explode(',', $full_name, 2);
        $result['last_name'] = trim($parts[0]);
        $first_middle = trim($parts[1] ?? '');
        
        // Split first and middle names
        $first_middle_parts = explode(' ', $first_middle);
        $result['first_name'] = trim($first_middle_parts[0] ?? '');
        
        // Handle middle name/initial
        if (count($first_middle_parts) > 1) {
            $middle = trim(implode(' ', array_slice($first_middle_parts, 1)));
            // If middle is just an initial, keep it as is
            if (strlen($middle) === 1 || (strlen($middle) === 2 && substr($middle, -1) === '.')) {
                $result['middle_name'] = rtrim($middle, '.');
            } else {
                $result['middle_name'] = $middle;
            }
        }
    } 
    // Format 2: "Firstname Middlename Lastname"
    else {
        $parts = explode(' ', $full_name);
        
        // At least 2 parts needed
        if (count($parts) >= 2) {
            $result['first_name'] = trim(array_shift($parts));
            $result['last_name'] = trim(array_pop($parts));
            
            // Anything left is middle name
            if (!empty($parts)) {
                $result['middle_name'] = trim(implode(' ', $parts));
            }
        } 
        // Fallback: just first and last name (2 parts)
        elseif (count($parts) === 2) {
            $result['first_name'] = trim($parts[0]);
            $result['last_name'] = trim($parts[1]);
        }
    }
    
    return $result;
}

// Helper function to match student with database using first and last name only
function findStudentInDatabase($conn, $first_name, $last_name, $middle_name = '') {
    // Strategy 1: Exact match with first and last name (case-insensitive)
    $stmt = $conn->prepare("SELECT StudentID, FirstName, LastName, Middlename 
                           FROM student 
                           WHERE LOWER(TRIM(LastName)) = LOWER(TRIM(?)) 
                           AND LOWER(TRIM(FirstName)) = LOWER(TRIM(?))");
    $stmt->bind_param("ss", $last_name, $first_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $stmt->close();
        return $student;
    }
    $stmt->close();
    
    // Strategy 2: Try without any middle name parts (if first name has space)
    $first_name_parts = explode(' ', $first_name);
    if (count($first_name_parts) > 1) {
        // First part is first name, rest might be middle
        $base_first_name = trim($first_name_parts[0]);
        
        $stmt = $conn->prepare("SELECT StudentID, FirstName, LastName, Middlename 
                               FROM student 
                               WHERE LOWER(TRIM(LastName)) = LOWER(TRIM(?)) 
                               AND LOWER(TRIM(FirstName)) = LOWER(TRIM(?))");
        $stmt->bind_param("ss", $last_name, $base_first_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $stmt->close();
            return $student;
        }
        $stmt->close();
    }
    
    // Strategy 3: Fuzzy match - check if names contain each other
    $stmt = $conn->prepare("SELECT StudentID, FirstName, LastName, Middlename 
                           FROM student 
                           WHERE LOWER(TRIM(LastName)) LIKE LOWER(CONCAT('%', TRIM(?), '%'))
                           AND LOWER(TRIM(FirstName)) LIKE LOWER(CONCAT('%', TRIM(?), '%'))");
    $stmt->bind_param("ss", $last_name, $first_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $stmt->close();
        return $student;
    }
    $stmt->close();
    
    return null;
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
    'students_processed' => 0,
    'incomplete_grades' => []  // New field to track students with incomplete grades
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

    // Extract subject and school year from the quarter sheet
    $subject_name = trim($quarter_sheet->getCell('AG7')->getCalculatedValue());
    if (empty($subject_name) || strtoupper($subject_name) === '#REF!') {
        throw new Exception('Could not read subject name or subject not assigned from cell');
    }

    $school_year = trim($quarter_sheet->getCell('AG5')->getCalculatedValue());
    if (empty($school_year) || strtoupper($school_year) === '#REF!') {
        throw new Exception('Could not read school year or school year not assigned from cell');
    }
    
    // Get subject ID - FIXED: Check assigned_subject table instead of teacherID in subject table
$subject_stmt = $conn->prepare("SELECT s.SubjectID 
                               FROM subject s 
                               INNER JOIN assigned_subject a ON s.SubjectID = a.subject_id 
                               WHERE (s.SubjectName = ? OR s.SubjectName LIKE ?) 
                               AND a.teacher_id = ? 
                               AND a.school_year = ?");
$subject_name_clean = trim($subject_name);
$subject_name_like = "%" . $subject_name_clean . "%";
$subject_stmt->bind_param("ssis", $subject_name_clean, $subject_name_like, $teacher_id, $school_year);
$subject_stmt->execute();
$subject_result = $subject_stmt->get_result();
$subject = $subject_result->fetch_assoc();

    
    if (!$subject) {
        throw new Exception("Subject '$subject_name' not found or not assigned to the logged-in teacher.");
    }
    
    $subject_id = $subject['SubjectID'];
    
    // Get section information for validation
    $section_info_stmt = $conn->prepare("SELECT s.SectionID, s.GradeLevel, s.SectionName 
                                        FROM assigned_subject a 
                                        INNER JOIN section s ON a.section_id = s.SectionID 
                                        WHERE a.subject_id = ? AND a.teacher_id = ? AND a.school_year = ?");
    $section_info_stmt->bind_param("iis", $subject_id, $teacher_id, $school_year);
    $section_info_stmt->execute();
    $section_info_result = $section_info_stmt->get_result();
    $section_info = $section_info_result->fetch_assoc();
    $section_info_stmt->close();

    // --- Validate Excel metadata against database (grade level / section / teacher) ---
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

    // Compare teacher (must match logged-in teacher)
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
    if ($excelGrade !== null && $section_info && $section_info['GradeLevel'] !== '') {
        $dbGrade = (int)$section_info['GradeLevel'];
        if ($dbGrade !== $excelGrade) {
            throw new Exception("Grade level on your spreadsheet Grade {$excelGrade} does not match the subject's assigned grade level (Grade {$dbGrade}).");
        }
    }

// Compare section if available in Excel
if ($excelSection !== null && $section_info && $section_info['SectionName'] !== '') {
    // More flexible normalization for Excel section
    $excelSecClean = preg_replace('/grade\s*\d+/i', '', $excelSection); // remove 'Grade 7'
    $excelSecClean = preg_replace('/^\s*\d+[-\s]*/', '', $excelSecClean); // remove leading digits like '7-' or '7 '
    $excelSecClean = preg_replace('/\b(section|teacher|adviser|teacher:|adviser:)\b/i', '', $excelSecClean);
    $excelSecClean = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $excelSecClean);
    $excelSecClean = strtolower(trim(preg_replace('/\s+/', ' ', $excelSecClean)));
    
    // Remove any remaining leading/trailing hyphens and spaces
    $excelSecClean = trim($excelSecClean, " -");

    // Normalize DB section name
    $dbSectionRaw = $section_info['SectionName'];
    $dbSecClean = preg_replace('/grade\s*\d+/i', '', $dbSectionRaw);
    $dbSecClean = preg_replace('/^\s*\d+[-\s]*/', '', $dbSecClean);
    $dbSecClean = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $dbSectionRaw);
    $dbSecClean = strtolower(trim(preg_replace('/\s+/', ' ', $dbSecClean)));
    $dbSecClean = trim($dbSecClean, " -");

    // Debug logging (you can remove this after testing)
    error_log("Excel Section: '$excelSection' -> '$excelSecClean'");
    error_log("DB Section: '$dbSectionRaw' -> '$dbSecClean'");

    if ($excelSecClean !== '' && $dbSecClean !== '' && $excelSecClean !== $dbSecClean) {
        throw new Exception("Section in Excel ('{$excelSection}') does not match the subject's assigned section ('{$section_info['SectionName']}').");
    }
}

    // Log start of grade upload
    try {
        $teacher_display = getTeacherDisplayName($conn, $teacher_id);
        $startMessage = "Started uploading grades for {$subject_name} — Q{$quarter} ({$school_year}).";
        log_system_action($conn, 'Grade Upload Started', $teacher_id, [
            'uploaded_by' => $teacher_display,
            'message' => $startMessage,
            'file_name' => $file['name'],
            'subject_id' => $subject_id,
            'teacher_id' => $teacher_id
        ], 'info');
    } catch (Exception $logEx) {
        error_log('Logging failed (start): ' . $logEx->getMessage());
    }
    
    // Extract highest possible scores (row 10) with improved error handling
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
        'ww_total' => $quarter_sheet->getCell('P10')->getCalculatedValue(),
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
        'pt_total' => $quarter_sheet->getCell('AC10')->getCalculatedValue(),
        'pt_ps' => $quarter_sheet->getCell('AD10')->getValue(),
        'pt_ws' => $quarter_sheet->getCell('AE10')->getValue(),
        'qa1' => $quarter_sheet->getCell('AF10')->getValue(),
        'qa_ps' => $quarter_sheet->getCell('AG10')->getValue(),
        'qa_ws' => $quarter_sheet->getCell('AH10')->getValue()
    ];
    
    // Normalize HPS values: handle errors, empty values, and convert to appropriate types
    foreach ($highest_scores as $key => &$hpsValue) {
        if (in_array($key, [
            'ww1', 'ww2', 'ww3', 'ww4', 'ww5', 'ww6', 'ww7', 'ww8', 'ww9', 'ww10',
            'pt1', 'pt2', 'pt3', 'pt4', 'pt5', 'pt6', 'pt7', 'pt8', 'pt9', 'pt10',
            'qa1', 'ww_total', 'pt_total'
        ])) {
            // Check for Excel errors or empty values
            if ($hpsValue === null || $hpsValue === '' || 
                (is_string($hpsValue) && (
                    str_starts_with($hpsValue, '#') || 
                    str_starts_with($hpsValue, '=') ||
                    strtoupper($hpsValue) === 'N/A' ||
                    strtoupper($hpsValue) === '#N/A' ||
                    strtoupper($hpsValue) === '#DIV/0!' ||
                    strtoupper($hpsValue) === '#VALUE!' ||
                    strtoupper($hpsValue) === '#REF!'
                ))) {
                $hpsValue = 0;  // Empty HPS is allowed - means the teacher didn't use this assessment item
            } elseif (is_string($hpsValue) && is_numeric(trim($hpsValue))) {
                $hpsValue = (float) trim($hpsValue);
            } elseif (is_numeric($hpsValue)) {
                $hpsValue = (float) $hpsValue;
            }
        }
    }
    
    // Prepare the student queries
    $student_stmt = $conn->prepare("SELECT StudentID FROM student 
        WHERE LOWER(TRIM(LastName)) = LOWER(?) AND LOWER(TRIM(FirstName)) = LOWER(?)");
    
    // Check if student is enrolled in this subject's section
    $enrollment_stmt = $conn->prepare("SELECT se.StudentID 
                                      FROM section_enrollment se
                                      INNER JOIN assigned_subject a ON se.SectionID = a.section_id
                                      WHERE se.StudentID = ? AND se.SchoolYear = ? AND a.subject_id = ?");
    
    // FIRST PASS: Validate all students before inserting any data
    $students_to_process = [];
    $students_not_found = [];
    $students_not_enrolled = [];
    $students_incomplete_grades = []; // Track students with incomplete grades
    
    // Define score fields and calculated fields
    $score_fields = [
        'ww1', 'ww2', 'ww3', 'ww4', 'ww5', 'ww6', 'ww7', 'ww8', 'ww9', 'ww10',
        'pt1', 'pt2', 'pt3', 'pt4', 'pt5', 'pt6', 'pt7', 'pt8', 'pt9', 'pt10',
        'qa1'
    ];
    
    $calculated_fields = [
        'ww_total', 'ww_ps', 'ww_ws', 'pt_total', 'pt_ps', 'pt_ws', 
        'qa_ps', 'qa_ws', 'initial_grade', 'quarterly_grade'
    ];
    
    // Helper function to check if a value is an Excel error
    function isExcelError($value) {
        if (!is_string($value)) return false;
        $upper = strtoupper($value);
        return str_starts_with($upper, '#') || 
               $upper === 'N/A' || 
               $upper === '#N/A' || 
               $upper === '#DIV/0!' || 
               $upper === '#VALUE!' || 
               $upper === '#REF!';
    }
    
    // Helper function to safely get cell value
    function getCellValueSafely($cell) {
        try {
            $value = $cell->getCalculatedValue();
            // Handle Excel errors
            if (is_string($value) && isExcelError($value)) {
                return null;
            }
            return $value;
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Process student data (male students rows 12-61, female students rows 63-112)
    for ($row = 12; $row <= 112; $row++) {
        if ($row == 62) continue; // Skip the gap between male and female

        $student_name = trim($quarter_sheet->getCell('B' . $row)->getCalculatedValue());
        if (empty($student_name)) continue;
        
        // Parse the name using the new function
        $parsed_name = parseStudentName($student_name);

        if (empty($parsed_name['first_name']) || empty($parsed_name['last_name'])) {
            $students_not_found[] = $student_name . " (Invalid name format)";
            continue;
        }

        // Try to find the student in database
        $student = findStudentInDatabase($conn, 
            $parsed_name['first_name'], 
            $parsed_name['last_name'], 
            $parsed_name['middle_name'] ?? ''
        );

        if (!$student) {
            $students_not_found[] = "$student_name (Parsed as: " . 
                                    trim($parsed_name['first_name'] . ' ' . 
                                         ($parsed_name['middle_name'] ? $parsed_name['middle_name'] . ' ' : '') . 
                                         $parsed_name['last_name']) . ")";
            continue;
        }

        $student_id = $student['StudentID'];
        
        // Check if student is enrolled in this subject's section for the current school year
        $enrollment_stmt->bind_param("isi", $student_id, $school_year, $subject_id);
        $enrollment_stmt->execute();
        $enrollment_result = $enrollment_stmt->get_result();
        
        if ($enrollment_result->num_rows === 0) {
            $students_not_enrolled[] = "$student_name (Not enrolled in $subject_name for $school_year)";
            continue;
        }
        
        // Extract student scores with improved validation
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
        
        $student_errors = []; // Track validation errors for this student
        
        foreach ($score_cells as $key => $cell) {
            $value = getCellValueSafely($quarter_sheet->getCell($cell . $row));
            
            // Check if it's a score field (ww1-ww10, pt1-pt10, qa1)
            if (in_array($key, $score_fields)) {
                $hps_for_key = $highest_scores[$key] ?? 0;
                
                // If HPS is set (non-zero), student must have a valid score
                if ($hps_for_key > 0) {
                    if ($value === null || $value === '' || isExcelError($value)) {
                        $student_errors[] = "Missing score for $key (HPS: $hps_for_key)";
                        $student_scores[$key] = 0; // Set to 0 for now, will be rejected later
                    } elseif (!is_numeric($value)) {
                        $student_errors[] = "Non-numeric score for $key: $value";
                        $student_scores[$key] = 0;
                    } else {
                        $student_scores[$key] = (float) $value;
                    }
                } else {
                    // No HPS or HPS is 0, student can have empty score
                    if ($value === null || $value === '' || isExcelError($value) || !is_numeric($value)) {
                        $student_scores[$key] = 0;
                    } else {
                        $student_scores[$key] = (float) $value;
                    }
                }
            } 
            // Check if it's a calculated field
            elseif (in_array($key, $calculated_fields)) {
                // For calculated fields (totals, percentages, weights), allow empty/errors
                if ($value === null || $value === '' || isExcelError($value) || !is_numeric($value)) {
                    $student_scores[$key] = 0;
                } else {
                    $student_scores[$key] = (float) $value;
                }
            } else {
                // For other fields, set to 0
                $student_scores[$key] = 0;
            }
        }
        
        // Check if student has any validation errors
        if (!empty($student_errors)) {
            $error_message = "$student_name - " . implode(", ", $student_errors);
            $students_incomplete_grades[] = $error_message;
            continue; // Skip this student
        }
        
        // Additional validation: Check if quarterly_grade is valid (if HPS for any component exists)
        $hasAnyHPS = false;
        foreach ($score_fields as $field) {
            if (($highest_scores[$field] ?? 0) > 0) {
                $hasAnyHPS = true;
                break;
            }
        }
        
        // If there's any HPS, quarterly_grade should be valid
        if ($hasAnyHPS) {
            $quarterly_grade = $student_scores['quarterly_grade'] ?? 0;
            if ($quarterly_grade === 0 || !is_numeric($quarterly_grade)) {
                $students_incomplete_grades[] = "$student_name - Missing or invalid quarterly grade";
                continue;
            }
        }
        
        $students_to_process[] = [
            'student_id' => $student_id,
            'scores' => $student_scores,
            'name' => $student_name
        ];
    }
    
    // Cross-verify Excel students vs DB enrolled students
    $excel_ids = array_map(function($s){ return $s['student_id']; }, $students_to_process);
    $db_enrolled_ids = [];
    
    if ($section_info && $section_info['SectionID']) {
        $secIdForQuery = $section_info['SectionID'];
        $enrolled_q = $conn->prepare("SELECT StudentID FROM section_enrollment WHERE SectionID = ? AND SchoolYear = ? AND status = 'active'");
        $enrolled_q->bind_param('is', $secIdForQuery, $school_year);
        $enrolled_q->execute();
        $enrolled_res = $enrolled_q->get_result();
        while ($er = $enrolled_res->fetch_assoc()) {
            $db_enrolled_ids[] = (int)$er['StudentID'];
        }
        $enrolled_q->close();
    }

    // Compute differences
    $missing_in_excel = [];
    $missing_in_db = [];
    if (!empty($db_enrolled_ids)) {
        $missing_in_excel = array_values(array_diff($db_enrolled_ids, $excel_ids));
        $missing_in_db = array_values(array_diff($excel_ids, $db_enrolled_ids));
    }

    // Map IDs to human-readable names for reporting
    $missing_in_excel_names = [];
    if (!empty($missing_in_excel)) {
        $placeholders = implode(',', array_fill(0, count($missing_in_excel), '?'));
        $types = str_repeat('i', count($missing_in_excel));
        $stmt = $conn->prepare("SELECT StudentID, FirstName, Middlename, LastName FROM student WHERE StudentID IN ($placeholders)");
        $refs = [];
        $refs[] = &$types;
        for ($i = 0; $i < count($missing_in_excel); $i++) {
            $refs[] = &$missing_in_excel[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $missing_in_excel_names[] = trim($r['LastName'] . ', ' . $r['FirstName'] . ' ' . $r['Middlename']);
        }
        $stmt->close();
    }

    $missing_in_db_names = [];
    if (!empty($missing_in_db)) {
        foreach ($students_to_process as $s) {
            if (in_array($s['student_id'], $missing_in_db, true)) {
                $sid = $s['student_id'];
                $qnm = $conn->prepare('SELECT FirstName, Middlename, LastName FROM student WHERE StudentID = ? LIMIT 1');
                $qnm->bind_param('i', $sid);
                $qnm->execute();
                $rnm = $qnm->get_result();
                if ($rnm && $rnm->num_rows > 0) {
                    $row = $rnm->fetch_assoc();
                    $missing_in_db_names[] = trim($row['LastName'] . ', ' . $row['FirstName'] . ' ' . $row['Middlename']);
                } else {
                    $missing_in_db_names[] = "StudentID $sid";
                }
                $qnm->close();
            }
        }
    }

    // If there are validation issues, don't insert any data
    $hasValidationIssues = !empty($students_not_found) || 
                          !empty($students_not_enrolled) || 
                          !empty($missing_in_excel) || 
                          !empty($missing_in_db) ||
                          !empty($students_incomplete_grades);
    
    if ($hasValidationIssues) {
        $response['students_not_found'] = $students_not_found;
        $response['students_not_enrolled'] = $students_not_enrolled;
        $response['incomplete_grades'] = $students_incomplete_grades;
        
        $messageParts = [];
        if (!empty($students_not_found)) {
            $messageParts[] = count($students_not_found) . " students not found in database";
        }
        if (!empty($students_not_enrolled)) {
            $messageParts[] = count($students_not_enrolled) . " students not enrolled in this subject";
        }
        if (!empty($students_incomplete_grades)) {
            $messageParts[] = count($students_incomplete_grades) . " students have incomplete grades";
        }
        if (!empty($missing_in_excel)) {
            $messageParts[] = count($missing_in_excel) . " students missing from Excel (present in class list)";
        }
        if (!empty($missing_in_db)) {
            $messageParts[] = count($missing_in_db) . " students in Excel not enrolled in class";
        }

        $detailed = [];
        if (!empty($missing_in_excel_names)) $detailed['missing_in_excel'] = $missing_in_excel_names;
        if (!empty($missing_in_db_names)) $detailed['missing_in_db'] = $missing_in_db_names;
        if (!empty($students_incomplete_grades)) $detailed['incomplete_grades_details'] = array_slice($students_incomplete_grades, 0, 5);

        $message = "Upload failed. Please fix the following issues: " . implode('; ', $messageParts) . '.';
        
        if (!empty($students_incomplete_grades)) {
            $message .= " Students with incomplete grades need scores for all assessment items that have Highest Possible Scores (HPS).";
        }

        // Log the failed upload with student IDs and subject ID
        try {
            $teacher_display = getTeacherDisplayName($conn, $teacher_id);
            $failedMessage = "Grade upload failed for {$subject_name} — Q{$quarter} ({$school_year}).";
            log_system_action($conn, 'Grade Upload Failed', $teacher_id, [
                'uploaded_by' => $teacher_display,
                'message' => $failedMessage,
                'file_name' => $file['name'],
                'subject_id' => $subject_id,
                'teacher_id' => $teacher_id,
                'students_not_found' => $students_not_found,
                'students_not_enrolled' => $students_not_enrolled,
                'students_incomplete_grades' => count($students_incomplete_grades),
                'missing_in_excel' => $missing_in_excel_names,
                'missing_in_db' => $missing_in_db_names
            ], 'error');
        } catch (Exception $logEx) {
            error_log('Logging failed (validation): ' . $logEx->getMessage());
        }

        echo json_encode(array_merge([
            'message' => $message,
            'message_type' => 'danger',
            'students_not_found' => $students_not_found,
            'students_not_enrolled' => $students_not_enrolled,
            'students_processed' => 0
        ], $detailed));
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
        $student_name = $student_data['name'];
        
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
            if (strpos($insert_grades_details->error, 'cannot be null') !== false) {
                throw new Exception("Database error: Cannot insert null values. Please make sure all required student scores are filled in the Excel sheet.");
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
                if (
                    $fullName === '' ||
                    strcasecmp($fullName, 'MALE') === 0 ||
                    strcasecmp($fullName, 'FEMALE') === 0 ||
                    is_numeric($fullName)
                ) {
                    continue;
                }

                // Parse the name
                $parsed_name = parseStudentName($fullName);
                $firstNamePart = $parsed_name['first_name'];
                $lastNamePart = $parsed_name['last_name'];

                // Find student in database using the same function
                $student = findStudentInDatabase($conn, $firstNamePart, $lastNamePart, $parsed_name['middle_name'] ?? '');
                if (!$student) {
                    $summary_errors[] = "Student not found in summary: $fullName (Section $currentSection)";
                    continue;
                }
                
                $sidRow = $student['StudentID'];

                // Check enrollment for summary grades
                $enrollment_stmt_summary = $conn->prepare("SELECT se.StudentID 
                                                          FROM section_enrollment se
                                                          INNER JOIN assigned_subject a ON se.SectionID = a.section_id
                                                          WHERE se.StudentID = ? AND se.SchoolYear = ? AND a.subject_id = ?");
                $enrollment_stmt_summary->bind_param("isi", $sidRow, $school_year, $subject_id);
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
                
                // Get final grade if we're processing 4th quarter
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
                    if (strpos($gradeStmt->error, 'cannot be null') !== false) {
                        throw new Exception("Database error: Cannot insert null values from summary sheet. Please complete all grades in the Excel file.");
                    }
                    $summary_errors[] = "Database error updating grades for $fullName: " . $gradeStmt->error;
                }
                $gradeStmt->close();
            }
            $wbSummary->disconnectWorksheets();
            unset($wbSummary);
        }
    } catch (Exception $summaryException) {
        if (strpos($summaryException->getMessage(), 'cannot be null') !== false) {
            throw new Exception("Database error: Cannot insert null values from summary sheet. Please complete all grades in the Excel file.");
        }
        $summary_errors[] = $summaryException->getMessage();
    }   

    $student_stmt->close();
    $enrollment_stmt->close();
    $insert_grades_details->close();

    // Build response message
    $response['message'] = "Grades successfully uploaded! Processed {$response['students_processed']} students for {$subject_name} - Q{$quarter} ({$school_year}).";
    $response['message_type'] = 'success';
    
    // Log successful upload with student IDs and subject ID
    try {
        $teacher_display = getTeacherDisplayName($conn, $teacher_id);
        $successMessage = "Successfully uploaded {$response['students_processed']} grades for {$subject_name} — Q{$quarter} ({$school_year}).";
        $student_ids = array_column($students_to_process, 'student_id');
        log_system_action($conn, 'Grade Upload Completed', $teacher_id, [
            'uploaded_by' => $teacher_display,
            'message' => $successMessage,
            'file_name' => $file['name'],
            'subject_id' => $subject_id,
            'teacher_id' => $teacher_id,
            'students_processed' => $response['students_processed'],
            'student_ids' => $student_ids
        ], 'success');
    } catch (Exception $logEx) {
        error_log('Logging failed (success): ' . $logEx->getMessage());
    }

} catch (Exception $e) {
    if (strpos($e->getMessage(), 'cannot be null') !== false) {
        $response['message'] = "Database error: Cannot insert null values. Please complete all student scores in the Excel file before uploading.";
    } else {
        $response['message'] = "Error processing file: " . $e->getMessage();
    }
    $response['message_type'] = 'danger';
    
    // Log failure with available IDs
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
            'subject_id' => isset($subject_id) ? $subject_id : null,
            'teacher_id' => $tid,
            'technical' => $e->getMessage()
        ], 'error');
    } catch (Exception $logEx) {
        error_log('Logging failed (error): ' . $logEx->getMessage());
    }
    error_log("Grade upload error: " . $e->getMessage());
}

echo json_encode($response);
?>