<?php
include '../config.php';
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$students_result = null;
$current_year = '2025-2026';

// Get teacher information - FIXED: Get TeacherID from teacher table
$user_id = $_SESSION['user_id'];

// Fetch active school year
$schoolyear_query = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$schoolyear_result = $conn->query($schoolyear_query);
$schoolyear = $schoolyear_result ? $schoolyear_result->fetch_assoc() : null;
$current_year = $schoolyear ? $schoolyear['school_year'] : '2025-2026';

// Get teacher's basic info using prepared statement - FIXED: Get TeacherID properly
$teacher_sql = "SELECT TeacherID, fName, lName, mName FROM teacher WHERE UserID = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_id = $teacher ? $teacher['TeacherID'] : null; // This is the actual TeacherID
$teacher_name = $teacher ? trim($teacher['fName'] . ' ' . ($teacher['mName'] ? $teacher['mName'] . ' ' : '') . $teacher['lName']) : 'Teacher';
$teacher_stmt->close();

$generate_pdf = isset($_GET['generate_pdf']) ? $_GET['generate_pdf'] : null;
$quarter = isset($_GET['quarter']) ? $_GET['quarter'] : null;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

if (!$student_id) {
    // Fetch students from teacher's advisory class for the list page - FIXED: Use TeacherID
    if ($teacher_id) {
        $advisory_sql = "SELECT SectionID, AdviserID FROM section WHERE AdviserID = ?";
        $advisory_stmt = $conn->prepare($advisory_sql);
        $advisory_stmt->bind_param("i", $teacher_id);
        $advisory_stmt->execute();
        $advisory_result = $advisory_stmt->get_result();
        $advisory = $advisory_result->fetch_assoc();
        $advisory_stmt->close();
        
        if ($advisory) {
            $advisory_section_id = $advisory['SectionID'];
            $students_sql = "
                SELECT DISTINCT s.StudentID, s.FirstName, s.LastName, s.Middlename, 
                                sec.GradeLevel, sec.SectionName
                FROM student s
                JOIN section_enrollment se ON s.StudentID = se.StudentID
                JOIN section sec ON se.SectionID = sec.SectionID
                WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
                ORDER BY s.LastName, s.FirstName
            ";
            $students_stmt = $conn->prepare($students_sql);
            $students_stmt->bind_param("is", $advisory_section_id, $current_year);
            $students_stmt->execute();
            $students_result = $students_stmt->get_result();
            $students_stmt->close();
        } else {
            $students_result = null;
        }
    } else {
        $students_result = null;
    }
}

if ($student_id) {
    // REPORT CARD GENERATION CODE
    // Initialize student data
    $student = null;
    $grades = [];
    $attendance = [];
    $observed_values = [];
    $section = [];

    // Get student information using prepared statement
    $student_query = "SELECT * FROM student WHERE StudentID = ?";
    $student_stmt = $conn->prepare($student_query);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $student = $student_result->fetch_assoc();
    $student_stmt->close();

    if (!$student) {
        $_SESSION['error'] = "Student not found";
        header("Location: record.php");
        exit();
    }

    // Get student's current section using prepared statement
    $section_query = "
        SELECT s.*, se.SchoolYear 
        FROM section_enrollment se 
        JOIN section s ON se.SectionID = s.SectionID 
        WHERE se.StudentID = ? 
        AND se.SchoolYear = ?
        AND se.status = 'active'
        LIMIT 1
    ";
    $section_stmt = $conn->prepare($section_query);
    $section_stmt->bind_param("is", $student_id, $current_year);
    $section_stmt->execute();
    $section_result = $section_stmt->get_result();
    $section = $section_result->fetch_assoc();
    $section_stmt->close();

    // Get grades from grades table using prepared statement - FIXED: Use proper subject relationship
    $grades_query = "
        SELECT g.*, s.SubjectName 
        FROM grades g 
        JOIN subject s ON g.subject = s.SubjectID 
        WHERE g.student_id = ?
        AND g.school_year = ?
    ";
    $grades_stmt = $conn->prepare($grades_query);
    $grades_stmt->bind_param("is", $student_id, $current_year);
    $grades_stmt->execute();
    $grades_result = $grades_stmt->get_result();
    $grades_stmt->close();

    $grades = [];
    while ($row = $grades_result->fetch_assoc()) {
        $grades[] = $row;
    }

    // Get attendance data
    // Extract year and next_year from $current_year (format: YYYY-YYYY)
    $year = '';
    $next_year = '';
    if (preg_match('/^(\d{4})-(\d{4})$/', $current_year, $matches)) {
        $year = $matches[1];
        $next_year = $matches[2];
    }
    
    // FIXED: Attendance query - check if student is in teacher's section
    $attendance_query = "
        SELECT MONTH(Date) as month_num, 
               COUNT(*) as total_days,
               SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_days,
               SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_days,
               SUM(CASE WHEN Status = 'excused' THEN 1 ELSE 0 END) as excused_days
        FROM attendance 
        WHERE StudentID = ?
        AND SectionID = ?
        AND (
            (YEAR(Date) = ? AND MONTH(Date) >= 6) OR 
            (YEAR(Date) = ? AND MONTH(Date) <= 5)
        )
        GROUP BY MONTH(Date), YEAR(Date)
        ORDER BY YEAR(Date), MONTH(Date)
    ";
    $attendance_stmt = $conn->prepare($attendance_query);
    $section_id = $section ? $section['SectionID'] : 0;
    $attendance_stmt->bind_param("iiii", $student_id, $section_id, $year, $next_year);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    $attendance_stmt->close();

    $attendance_data = [];
    while ($row = mysqli_fetch_assoc($attendance_result)) {
        $attendance_data[$row['month_num']] = [
            'present' => $row['present_days'],
            'absent' => $row['absent_days'],
            'excused' => $row['excused_days'],
            'total' => $row['total_days']
        ];
    }

    // Map month numbers to names
    $month_names = [
        6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sept', 10 => 'Oct', 
        11 => 'Nov', 12 => 'Dec', 1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 
        4 => 'Apr', 5 => 'May'
    ];

    foreach ($month_names as $num => $name) {
        $attendance[$name] = isset($attendance_data[$num]) ? $attendance_data[$num] : ['present' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0];
    }

    // Get teacher's name for signature using prepared statement - FIXED: Use TeacherID
    $teacher_name_query = "SELECT fName, lName FROM teacher WHERE TeacherID = ?";
    $teacher_name_stmt = $conn->prepare($teacher_name_query);
    $teacher_name_stmt->bind_param("i", $teacher_id);
    $teacher_name_stmt->execute();
    $teacher_name_result = $teacher_name_stmt->get_result();
    $teacher_name_info = $teacher_name_result->fetch_assoc();
    $teacher_name_stmt->close();
    $teacher_full_name = $teacher_name_info ? $teacher_name_info['fName'] . ' ' . $teacher_name_info['lName'] : 'Teacher';

    // Load observed/student values using prepared statement
    // NOTE: student_values table is not in your schema, so this might need adjustment
    $observed_values = [];
    if (tableExists($conn, 'student_values')) {
        $vals_sql = "SELECT * FROM student_values WHERE student_id = ? AND school_year = ?";
        $vals_stmt = $conn->prepare($vals_sql);
        $vals_stmt->bind_param("is", $student_id, $current_year);
        $vals_stmt->execute();
        $vals_res = $vals_stmt->get_result();
        $vals_stmt->close();
        while ($r = $vals_res->fetch_assoc()) {
            $observed_values[intval($r['quarter'])] = $r;
        }
    }

    // Get all subjects for the student's grade level in the specific order
    $grade_level = !empty($section) ? $section['GradeLevel'] : '';
    $section_name = !empty($section) ? $section['SectionName'] : '';

    // Define the subjects in the specific order you want
    $ordered_subjects = [
        'Filipino',
        'English', 
        'Mathematics',
        'Science',
        'Araling Panlipunan (AP)',
        'Values Education (VE)',
        'Technology and Livelihood Education (TLE)',
        'MAPEH'
    ];

    // Get all subjects for this grade level and section using prepared statement
    // FIXED: Use assigned_subject table to get subjects for this section
    $subjects_query = "
        SELECT DISTINCT s.SubjectID, s.SubjectName 
        FROM subject s 
        JOIN assigned_subject a ON s.SubjectID = a.subject_id
        JOIN section sec ON a.section_id = sec.SectionID
        WHERE sec.GradeLevel = ? 
        AND sec.SectionName = ?
        AND a.school_year = ?
        ORDER BY s.SubjectID
    ";
    $subjects_stmt = $conn->prepare($subjects_query);
    $subjects_stmt->bind_param("sss", $grade_level, $section_name, $current_year);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    $subjects_stmt->close();
    $all_subjects = [];
    while ($subject_row = $subjects_result->fetch_assoc()) {
        $all_subjects[] = $subject_row;
    }

    // Reorder subjects according to the specified order
    $ordered_subject_list = [];
    foreach ($ordered_subjects as $subject_name) {
        foreach ($all_subjects as $subject) {
            if ($subject['SubjectName'] == $subject_name) {
                $ordered_subject_list[] = $subject;
                break;
            }
        }
    }

    // Add MAPEH components if MAPEH exists
    $mapeh_found = false;
    $mapeh_components = [
        'Music and Arts',
        'PE and Health'
    ];

    foreach ($ordered_subject_list as $index => $subject) {
        if ($subject['SubjectName'] == 'MAPEH') {
            $mapeh_found = true;
            // Insert MAPEH components after MAPEH
            array_splice($ordered_subject_list, $index + 1, 0, [
                ['SubjectID' => 'MAPEH_MUSICARTS', 'SubjectName' => '• Music and Arts'],
                ['SubjectID' => 'MAPEH_PEHEALTH', 'SubjectName' => '• PE and Health']
            ]);
            break;
        }
    }

    // Validate grades when generating PDF directly
    if ($generate_pdf && $quarter) {
        $missing_grades = validateQuarterGrades($grades, $quarter, $ordered_subject_list);
        
        if (!empty($missing_grades)) {
            $_SESSION['error'] = "Cannot generate PDF for Quarter $quarter. The following subjects are missing grades:\n\n" . 
                                implode("\n", $missing_grades) . 
                                "\n\nPlease complete all grades before generating the report card.";
            header("Location: record.php");
            exit();
        }
    }
}

// Function to calculate age from birthdate
function calculateAge($birthdate) {
    if (empty($birthdate) || $birthdate == '0000-00-00') return '';
    
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

// Function to validate quarter grades
function validateQuarterGrades($grades, $quarter, $ordered_subject_list) {
    $missing_grades = [];
    
    foreach ($ordered_subject_list as $subject) {
        // Skip MAPEH components as they're not graded individually
        if (strpos($subject['SubjectName'], '•') === 0) {
            continue;
        }
        
        $grade_found = false;
        $has_grade = false;
        
        // Find the grade record for this subject
        foreach ($grades as $grade) {
            if ($grade['subject'] == $subject['SubjectID']) {
                $grade_found = $grade;
                break;
            }
        }
        
        // Check if the specific quarter grade exists and is not empty
        if ($grade_found) {
            switch ($quarter) {
                case 1:
                    $has_grade = !empty($grade_found['Q1']) && $grade_found['Q1'] !== '';
                    break;
                case 2:
                    $has_grade = !empty($grade_found['Q2']) && $grade_found['Q2'] !== '';
                    break;
                case 3:
                    $has_grade = !empty($grade_found['Q3']) && $grade_found['Q3'] !== '';
                    break;
                case 4:
                    $has_grade = !empty($grade_found['Q4']) && $grade_found['Q4'] !== '';
                    break;
            }
        }
        
        if (!$has_grade) {
            $missing_grades[] = $subject['SubjectName'];
        }
    }
    
    return $missing_grades;
}

// Helper function to check if table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $student_id ? 'Report Card - ' . $student['FirstName'] . ' ' . $student['LastName'] : 'Select Student - Report Card'; ?></title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if ($student_id): ?>
    <link href="https://fonts.googleapis.com/css2?family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <?php endif; ?>
    <style>
        <?php if (!$student_id): ?>
        /* Styles for student list page */
        body {
            font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
            background-color: #ffffffff;
        }
        .container {
            max-width: 1000px;
        }
        .card {
            margin-top: 20px;
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table {
            margin: 0; padding: 0;
        }
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
        }
        <?php else: ?>
        /* Styles for report card page */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Times New Roman', serif;
            background-color: #ffffffff;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-end {
            text-align: right;
        }
        
        .fw-bold {
            font-weight: bold;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        .mb-1 {
            margin-bottom: 0.25rem;
        }
        
        .mb-2 {
            margin-bottom: 0.5rem;
        }
        
        .mb-3 {
            margin-bottom: 1rem;
        }
        
        .mb-4 {
            margin-bottom: 1.5rem;
        }
        
        .mb-5 {
            margin-bottom: 3rem;
        }
        
        .mt-1 {
            margin-top: 0.25rem;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .mt-3 {
            margin-top: 1rem;
        }
        
        .mt-4 {
            margin-top: 1.5rem;
        }
        
        .mt-5 {
            margin-top: 3rem;
        }
        
        .ms-1 {
            margin-left: 0.25rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-md-4, .col-md-5, .col-md-6, .col-md-12 {
            padding: 0 15px;
        }
        
        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        
        .col-md-5 {
            flex: 0 0 41.666667%;
            max-width: 41.666667%;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .col-md-12 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }
        
        /* Report Card Specific Styles */
        @import url('https://fonts.googleapis.com/css2?family=UnifrakturMaguntia&display=swap');
        
        .report-card {
            background-color: white;
            padding: 10px 20px;
            margin: 20px auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 11in;
            position: relative;
            page-break-inside: avoid;
        }
        
        .old-english-font {
            font-family: 'UnifrakturMaguntia', cursive, 'Times New Roman', serif;
            font-size: 16px;
            letter-spacing: 1px;
        }
        
        .school-header {
            text-align: center;
            margin-bottom: 8px;
        }
        
        .school-header h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 2px 0;
        }
        
        .school-header p {
            font-size: 11px;
            margin: 1px 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-bordered th {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
        }
        table.table-bordered td {
            border: 1px solid #000;
            text-align: center;
            
        }
        
        .legend {
            font-size: 11pt;
            margin-top: 20px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .parent-message {
            font-size: 11pt;
        }
        
        .attendance-table {
            font-size: 8pt;
            width: 100%;
        }
        
        .attendance-table th, .attendance-table td {
            padding: 2px;
            text-align: center;
            width: 40px;
        }
        
        blockquote {
            margin: 0;
            padding: 0;
            font-size: 8px;
        }
        
        .subject-table {
            width: 100%;
        }
        
        .values-table {
            margin-top: 25px;
            font-size: 11pt;
            width: 100%;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end; 
        }
        
        .student-info {
            width: 100%;
            font-size: 11pt;
        }
        
        .student-info-row {
            display: flex;
            margin-bottom: 3px;
            align-items: flex-start;
        }
        
        .student-info-label {
            flex: 0 0 15%;
            font-weight: normal;
            white-space: nowrap;
        }
        
        .student-info-value {
            flex: 1;
            border-bottom: 1px solid #000;
            padding: 0 5px;
            min-height: 18px;
        }
        
        .student-info-small {
            flex: 0 0 10%;
            font-weight: normal;
            white-space: nowrap;
        }
        
        .report-title {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
        }
        
        .school-form {
            font-size: 11px;
            text-align: center;
            margin: 4px 0;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 150px;
            margin: 0 5px;
        }
        
        .subject-table.compact-table {
            margin: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding: 0 !important;
        }
        .subject-table th{
            padding: 0px;
            margin: 0px;
        }

        /* If there's still spacing, target the container */
        .col-md-6:first-child {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        
        /* Add classes for identifying static content */
        .static-content {
            /* This class is for identification only - no default styling */
        }
        
        .data-content {
            /* This class is for identification only - no default styling */
        }

        /* Quarter-specific PDF styles */
        <?php if ($generate_pdf): ?>
        /* When generating PDF directly, apply data-only styling based on quarter */
        * {
            color: transparent !important;
            background: transparent !important;
        }

        /* Quarter 1 - Show: student info, quarter1 grades, quarter1 observed values */
        <?php if ($quarter == 1): ?>
        .student-info-value,
        .subject-table td:nth-child(2), /* Q1 grades column */
        .values-table .data-cell-1 /* Q1 observed values column */ {
            color: black !important;
            background: white !important;
        }
        <?php endif; ?>

        /* Quarter 2 - Show: quarter2 grades, quarter2 observed values */
        <?php if ($quarter == 2): ?>
        .subject-table td:nth-child(3), /* Q2 grades column */
        .values-table .data-cell-2 /* Q2 observed values column */ {
            color: black !important;
            background: white !important;
        }
        <?php endif; ?>

        /* Quarter 3 - Show: quarter3 grades, quarter3 observed values */
        <?php if ($quarter == 3): ?>
        .subject-table td:nth-child(4), /* Q3 grades column */
        .values-table .data-cell-3 /* Q3 observed values column */ {
            color: black !important;
            background: white !important;
        }
        <?php endif; ?>

        /* Quarter 4 - Show: quarter4 grades, final grade, remarks, general average, quarter4 observed values, attendance */
        <?php if ($quarter == 4): ?>
        .attendance-table td:not(:first-child),
        .subject-table td:nth-child(5), /* Q4 grades column */
        .subject-table td:nth-child(6), /* Final grade column */
        .subject-table td:nth-child(7), /* Remarks column */
        .subject-table tr:last-child td:nth-child(6), /* General average value */
        .subject-table tr:last-child td:nth-child(7), /* General average remarks */
        .values-table .data-cell-4 /* Q4 observed values column */ {
            color: black !important;
            background: white !important;
        }
        <?php endif; ?>

        /* Hide static content in observed values */
        .values-table .static-cell {
            color: transparent !important;
            background: transparent !important;
        }
        .subject-table .static-cell {
            color: transparent !important;
            background: transparent !important;
        }
        
        /* Hide all static content */
        .school-header,
        .report-title,
        .school-form,
        .parent-message,
        .legend,
        .student-info-label,
        .student-info-small,
        .table-bordered th,
        .attendance-table th,
        .attendance-table .static-cell,
        .subject-table th,
        .values-table th,
        .signature-area p,
        blockquote,
        em,
        strong:not(.student-info-value strong),
        .old-english-font {
            color: transparent !important;
            background: transparent !important;
        }

        /* Make structure barely visible */
        .table-bordered th, 
        .table-bordered td {
            border-color: #ffffffff !important;
        }

        .student-info-value {
            border-bottom-color: #ffffffff !important;
        }

        img {
            opacity: 0 !important;
        }
        <?php endif; ?>

        @media print {
            @page {
                size: landscape;
                margin-top: 0.25in;
                margin-bottom: 0.19in;
                margin-left: 0.31in;
                margin-right: 0.38in;
            }

            body {
                background: white;
                margin: 0;
                padding: 0;
                font-size: 10pt;
                line-height: 1.2;
                color: transparent !important;
            }

            .no-print {
                display: none !important;
            }

            .report-card {
                width: 100%;
                height: auto;
                margin: 0;
                box-shadow: none;
                page-break-inside: avoid;
                page-break-after: always;
                color: transparent !important;
            }

            /* Hide ALL text by default */
            * {
                color: transparent !important;
                background: transparent !important;
            }

            /* Quarter-specific print styles */
            /* Quarter 1 - Show: student info, quarter1 grades, quarter1 observed values */
            <?php if ($quarter == 1): ?>
            .student-info-value,
            .subject-table td:nth-child(2), /* Q1 grades column */
            .values-table td:nth-child(3) /* Q1 observed values column */ {
                color: black !important;
                background: white !important;
            }
            <?php endif; ?>

            /* Quarter 2 - Show: quarter2 grades, quarter2 observed values */
            <?php if ($quarter == 2): ?>
            .subject-table td:nth-child(3), /* Q2 grades column */
            .values-table td:nth-child(4) /* Q2 observed values column */ {
                color: black !important;
                background: white !important;
            }
            <?php endif; ?>

            /* Quarter 3 - Show: quarter3 grades, quarter3 observed values */
            <?php if ($quarter == 3): ?>
            .subject-table td:nth-child(4), /* Q3 grades column */
            .values-table td:nth-child(5) /* Q3 observed values column */ {
                color: black !important;
                background: white !important;
            }
            <?php endif; ?>

            /* Quarter 4 - Show: quarter4 grades, final grade, remarks, general average, quarter4 observed values, attendance */
            <?php if ($quarter == 4): ?>
            .attendance-table td:not(:first-child),
            .subject-table td:nth-child(5), /* Q4 grades column */
            .subject-table td:nth-child(6), /* Final grade column */
            .subject-table td:nth-child(7), /* Remarks column */
            .subject-table tr:last-child td:nth-child(6), /* General average value */
            .subject-table tr:last-child td:nth-child(7), /* General average remarks */
            .values-table td:nth-child(6) /* Q4 observed values column */ {
                color: black !important;
                background: white !important;
            }
            <?php endif; ?>

            /* Hide static content in observed values */
            .values-table .static-cell {
                color: transparent !important;
                background: transparent !important;
            }
            .subject-table .static-cell {
                color: transparent !important;
                background: transparent !important;
            }

            /* Make table borders very light but keep structure */
            .table-bordered,
            .table-bordered th,
            .table-bordered td {
                border-color: #ffffffff !important;
            }

            /* Specifically show the data values */
            .student-info-value {
                border-bottom-color: #ffffffff !important;
            }

            /* Hide all images */
            img {
                opacity: 0 !important;
            }

            /* Hide all static headers, labels, titles */
            .school-header *,
            .report-title,
            .school-form,
            .parent-message *,
            .legend *,
            .student-info-label,
            .student-info-small,
            .table-bordered th,
            .attendance-table th,
            .subject-table th,
            .subject-table .static-cell,
            .values-table th,
            .signature-area p,
            blockquote,
            em,
            strong:not(.student-info-value strong),
            .old-english-font,
            h1, h2, h3, h4, h5, h6,
            p:not(.student-info-value) {
                color: transparent !important;
                background: transparent !important;
            }

            /* Hide signature lines but keep structure */
            .signature-line {
                border-bottom-color: transparent !important;
            }

            /* Ensure table structure remains barely visible */
            .table-bordered {
                border: 1px solid #ffffffff !important;
            }

            .table-bordered th, 
            .table-bordered td {
                border: 1px solid #ffffffff !important;
            }

            .student-info-value {
                border-bottom: 1px solid #ffffffff !important;
            }

            /* Layout adjustments */
            .page-break {
                page-break-before: always;
            }

            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }

            .attendance-table,
            .subject-table,
            .values-table {
                font-size: 8pt !important;
            }

            .student-info,
            .parent-message,
            .legend {
                font-size: 9pt !important;
            }

            .mt-1, .mt-2, .mt-3, .mt-4, .mt-5,
            .mb-1, .mb-2, .mb-3, .mb-4, .mb-5 {
                margin-top: 0.05in !important;
                margin-bottom: 0.05in !important;
            }
            
            .subject-table.compact-table {
                margin: 0 !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                padding: 0 !important;
            }

            /* If there's still spacing, target the container */
            .col-md-6:first-child {
                padding-top: 0 !important;
                padding-bottom: 0 !important;
            }

            .table-bordered th, 
            .table-bordered td {
                padding: 2px !important;
                font-size: 7pt !important;
            }

            .student-info-row {
                margin-bottom: 1px !important;
            }
            
            .student-info-value {
                min-height: 14px !important;
                font-size: 9pt !important;
            }
        }

        .school-header, .parent-message, .legend, .student-info, .subject-table, .attendance-table {
            margin-top: 3px;
            margin-bottom: 3px;
        }

        .signature-area {
            margin-top: 15px;
            text-align: center;
        }

        .report-title {
            text-align: center;
            font-size: 12pt;
            margin-bottom: 10px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .col-md-4, .col-md-5, .col-md-6, .col-md-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .row {
                flex-direction: column;
            }
            
            .report-card {
                width: 100%;
                height: auto;
            }
        }

        .pdf-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.2rem;
            z-index: 9999;
            flex-direction: column;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .fixed-action-buttons {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 12000;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* Hide everything when generating PDF directly */
        <?php if ($generate_pdf): ?>
        body {
            background: white !important;
        }
        .fixed-action-buttons,
        .no-print {
            display: none !important;
        }
        <?php endif; ?>
        <?php endif; ?>

            .dashboard-header {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php if (!$student_id): ?>
        <!-- STUDENT LIST PAGE -->
        <?php include '../navs/teacherNav.php';?>
            <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                    
                    <p class="lead mb-0">Student List - <?php echo date('F j, Y'); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white rounded-pill px-3 py-2 d-inline-block">
                        <small class="text-muted">School Year: <?php echo $current_year; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <div class="container-fluid">
            <div class="row ">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h4 class="mb-0">Student Record Cards</h4>
                     
                        <div class="card alert alert-info mb-0">
                            
                            <strong class="ms-2"><i class="fas fa-info-circle me-2"></i>Note:</strong>
                            <p class="mb-0">- Select a student from your advisory class to view and generate their report card.</p>
                            <p class="mb-0">- In printing, make sure that the right side of the card is facing into the printer. </p>
                           </div>
                        <div class="card-body mt-3 py-4">
                            <?php
                            // Display any error messages
                            if (isset($_SESSION['error'])) {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                                echo nl2br(htmlspecialchars($_SESSION['error']));
                                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                                echo '</div>';
                                unset($_SESSION['error']);
                            }
                            
                            // Display any success messages
                            if (isset($_SESSION['success'])) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
                                echo htmlspecialchars($_SESSION['success']);
                                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                                echo '</div>';
                                unset($_SESSION['success']);
                            }

                            // FIXED: Use the proper teacher_id variable
                            $adviserTeacherID = $teacher_id;
                            
                            if (!$adviserTeacherID) {
                                echo "<div class='alert alert-warning'>You don't have any advisory class</div>";
                            } else {
                                // FIXED: Use prepared statement for security
                                $sec_sql = "SELECT SectionID, GradeLevel, SectionName FROM section WHERE AdviserID = ? ORDER BY GradeLevel, SectionName";
                                $sec_stmt = $conn->prepare($sec_sql);
                                $sec_stmt->bind_param("i", $adviserTeacherID);
                                $sec_stmt->execute();
                                $secRes = $sec_stmt->get_result();
                                
                                if ($secRes && mysqli_num_rows($secRes) > 0) {
                                    $secIds = [];
                                    while ($s = mysqli_fetch_assoc($secRes)) {
                                        $secIds[] = $s['SectionID'];
                                    }
                                    $sec_stmt->close();

                                    // Create placeholders for the IN clause
                                    $placeholders = str_repeat('?,', count($secIds) - 1) . '?';
                                    
                                    $students_query = "
                                        SELECT s.StudentID, s.FirstName, s.LastName, s.Middlename, sec.GradeLevel, sec.SectionName
                                        FROM student s
                                        JOIN section_enrollment se ON s.StudentID = se.StudentID
                                        JOIN section sec ON se.SectionID = sec.SectionID
                                        WHERE se.SectionID IN ($placeholders)
                                          AND se.SchoolYear = ?
                                          AND se.status = 'active'
                                        ORDER BY sec.GradeLevel, sec.SectionName, s.LastName, s.FirstName
                                    ";
                                    
                                    $students_stmt = $conn->prepare($students_query);
                                    
                                    // Build types and bind parameters
                                    $types = str_repeat('i', count($secIds)) . 's';
                                    $params = array_merge($secIds, [$current_year]);
                                    $students_stmt->bind_param($types, ...$params);
                                    $students_stmt->execute();
                                    $students_result = $students_stmt->get_result();
                                    
                                    if ($students_result && mysqli_num_rows($students_result) > 0) {
                                        ?>
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-primary">
            <tr>
                <th>Name</th>
                <th>Grade & Section</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = mysqli_fetch_assoc($students_result)) {
                $fullName = trim($row['LastName'] . ', ' . $row['FirstName'] . ' ' . $row['Middlename']);
                $gradeSection = "Grade {$row['GradeLevel']} {$row['SectionName']}";
                ?>
                <tr>
                    <td><?= htmlspecialchars($fullName) ?></td>
                    <td><?= htmlspecialchars($gradeSection) ?></td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="?student_id=<?= $row['StudentID'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <div class="btn-group">
                                <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?student_id=<?= $row['StudentID'] ?>&generate_pdf=1&quarter=1">Quarter 1</a></li>
                                    <li><a class="dropdown-item" href="?student_id=<?= $row['StudentID'] ?>&generate_pdf=1&quarter=2">Quarter 2</a></li>
                                    <li><a class="dropdown-item" href="?student_id=<?= $row['StudentID'] ?>&generate_pdf=1&quarter=3">Quarter 3</a></li>
                                    <li><a class="dropdown-item" href="?student_id=<?= $row['StudentID'] ?>&generate_pdf=1&quarter=4">Quarter 4</a></li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php
            }
            $students_stmt->close();
            ?>
        </tbody>
    </table>
</div>
                                        <?php
                                    } else {
                                        echo "<div class='alert alert-info'>No students found in your advisory section(s)</div>";
                                    }
                                } else {
                                    echo "<div class='alert alert-warning'>You don't have any advisory class</div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php else: ?>
        <!-- REPORT CARD PAGE -->
        <?php if (!$generate_pdf): ?>
        <!-- Only show action buttons when NOT generating PDF directly -->
        <div class="fixed-action-buttons no-print" style="display: none;">
            <div class="btn-group">
                <button type="button" class="text-secondary btn" id="pdfButton" title="Save as PDF" style="font-size: 50px;"><i class="fas fa-file-pdf"></i></button>
                <button type="button" class="text-secondary btn dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 30px;">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item pdf-quarter" href="#" data-quarter="1">Quarter 1</a></li>
                    <li><a class="dropdown-item pdf-quarter" href="#" data-quarter="2">Quarter 2</a></li>
                    <li><a class="dropdown-item pdf-quarter" href="#" data-quarter="3">Quarter 3</a></li>
                    <li><a class="dropdown-item pdf-quarter" href="#" data-quarter="4">Quarter 4</a></li>
                </ul>
            </div>
            <a href="record.php" class="text-secondary" title="Back to List" style="font-size: 30px;"><i class="fas fa-times"></i></a>
        </div>
        <?php endif; ?>

        <div id="pdfLoading" class="pdf-loading no-print" style="display: none;">
            <div class="text-center">
                <div class="spinner-border text-light mb-2" role="status"></div>
                <div>Generating PDF...</div>
                <div style="font-size: 0.8rem;">This may take a few moments</div>
            </div>
        </div>

        <!-- Main content for PDF generation -->
        <div id="pdf-content">
            <div class="report-card">
                <div class="row">
                    <div class="col-md-6">
                        <div class="report-title">REPORT ON ATTENDANCE</div>
                    
                        <table class="table table-bordered attendance-table compact-table" style="height: 250px; vertical-align: middle;">
                            <thead>
                                <tr style="height: 30px;">
                                    <th></th>
                                    <th>Jun</th>
                                    <th>Jul</th>
                                    <th>Aug</th>
                                    <th>Sept</th>
                                    <th>Oct</th>
                                    <th>Nov</th>
                                    <th>Dec</th>
                                    <th>Jan</th>
                                    <th>Feb</th>
                                    <th>Mar</th>
                                    <th>Apr</th>
                                    <th>May</th>
                                    <th style="width: 60px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>No. of school days</td>
                                    <?php
                                    $months = ['Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'];
                                    $total_school_days = 0;
                                    $total_present = 0;
                                    $total_absent = 0;
                                    
                                    foreach ($months as $month) {
                                        $total_days = isset($attendance[$month]) ? $attendance[$month]['total'] : 0;
                                        $total_school_days += $total_days;
                                        echo "<td>$total_days</td>";
                                    }
                                    ?>
                                    <td><?php echo $total_school_days; ?></td>
                                </tr>
                                <tr>
                                    <td>No. of days present</td>
                                    <?php
                                    foreach ($months as $month) {
                                        $present = isset($attendance[$month]) ? $attendance[$month]['present'] : 0;
                                        $total_present += $present;
                                        echo "<td>$present</td>";
                                    }
                                    ?>
                                    <td><?php echo $total_present; ?></td>
                                </tr>
                                <tr>
                                    <td>No. of days absent</td>
                                    <?php
                                    foreach ($months as $month) {
                                        $absent = isset($attendance[$month]) ? $attendance[$month]['absent'] : 0;
                                        $total_absent += $absent;
                                        echo "<td>$absent</td>";
                                    }
                                    ?>
                                    <td><?php echo $total_absent; ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="signature-area" style="margin-top: 30px;">
                            <p class="mb-2 text-center"><strong>Parent/Guardian's Signature</strong></p>
                            <div class="mb-1 row" style="margin-top: 60px; font-size: 10pt;">
                                <p class="mb-3 ms-1 col-md-4 text-end">First Quarter</p>
                                <p class="mb-3 col-md-4">___________________________</p>
                                <p class="mb-3 ms-1 col-md-4 text-end"></p>
                                <p class="mb-3 col-md-4">___________________________</p>
                            </div>
                            <div class="mb-1 row" style="font-size: 10pt;">
                                <p class="mb-3 ms-1 col-md-4 text-end">Second Quarter</p>
                                <p class="mb-3 col-md-4">___________________________</p>
                                <p class="mb-3 ms-1 col-md-4 text-end"></p>
                                <p class="mb-3 col-md-4">___________________________</p>
                            </div>
                            <div class="mb-1 row" style="font-size: 10pt;">
                                <p class="mb-3 ms-1 col-md-4 text-end">Third Quarter</p>
                                <p class="mb-3 col-md-4">___________________________</p>
                                <p class="mb-3 ms-1 col-md-4 text-end"></p>
                                <p class="mb-3 col-md-4">___________________________</p>
                            </div>
                            <div class="mb-1 row" style="font-size: 10pt;">
                                <p class="mb-3 ms-1 col-md-4 text-end">Fourth Quarter</p>
                                <p class="mb-3 col-md-4">___________________________</p>
                                <p class="mb-3 ms-1 col-md-4 text-end"></p>
                                <p class="mb-3 col-md-4">___________________________</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- School Header -->
                        <div class="school-header">
                            <p><img src="../img/image.png" alt="deped logo" style="width: 40px;"></p>
                            <p class="mb-0 fw-bold old-english-font" style="font-size: 7pt;">Republic of the Philippines</p>
                            <p class="mb-0 fw-bold old-english-font" style="font-size: 9pt;">Department of Education</p>
                            <p class="mb-0" style="font-size: 5pt;">REGION IV-A CALABARZON</p>
                            <p class="mb-0" style="font-size: 6pt;"><strong>DIVISION OF BATANGAS</strong></p>
                            <p class="mb-0" style="font-size: 7pt;">Nasugbu East Sub-Office</p>
                            <p class="mb-0" style="font-size: 5pt;"><strong>BALAYTIGUE NATIONAL HIGH SCHOOL</strong></p>
                            <p class="mb-0" style="font-size: 5pt;">Balaytigue Nasugbu, Batangas</p>
                        </div>

                        <!-- Progress Report Card Title -->
                        <h4 class="text-center mt-0 mb-0" style="font-size: 12pt;"><strong>PROGRESS REPORT CARD</strong></h4>
                        <h4 class="text-center mb-3" style="font-size: 11pt;">School Year <?php echo $current_year; ?></h4>

                        <!-- Student Information -->
                        <div class="student-info" style="font-size: 11pt;">
                            <div class="student-info-row">
                                <div class="student-info-label">Name:</div>
                                <div class="student-info-value" style="flex: 0 0 72%;">
                                    <?php echo strtoupper($student['LastName'] . ', ' . $student['FirstName'] . ' ' . $student['Middlename']); ?>
                                </div>
                            </div>
                            
                            <div class="student-info-row">
                                <div class="student-info-label">LRN:</div>
                                <div class="student-info-value" style="flex: 0 0 30%;">
                                    <?php echo $student['LRN']; ?>
                                </div>
                                <div class="student-info-small" style="margin-left: 10px;">Sex:</div>
                                <div class="student-info-value" style="flex: 0 0 30%;">
                                    <?php echo strtoupper($student['Sex']); ?>
                                </div>
                            </div>
                            
                            <div class="student-info-row">
                                <div class="student-info-label">Age:</div>
                                <div class="student-info-value" style="flex: 0 0 30%;">
                                    <?php echo calculateAge($student['Birthdate']); ?>
                                </div>
                                <div class="student-info-small" style="margin-left: 10px;">Section:</div>
                                <div class="student-info-value" style="flex: 0 0 30%;">
                                    <?php echo !empty($section) ? "{$section['SectionName']}" : ''; ?>
                                </div>
                            </div>
                            
                            <div class="student-info-row">
                                <div class="student-info-label">Grade:</div>
                                <div class="student-info-value" style="flex: 0 0 30%;">
                                    <?php echo !empty($section) ? "{$section['GradeLevel']}" : ''; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Parent Message -->
                        <div class="parent-message" style="font-size: 11pt;">
                            <p class="mb-0">Dear Parent:</p>
                            <p class="mb-0"><em>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;This report card shows the ability and progress your child has made in the different learning areas as well as his/her progress in core values.</em></p>
                            <p class="mb-0"><em>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;The school welcomes you should you desire to know more about your child's progress.</em></p>
                       
                            <div class="row">
                                <div class="col-md-6 mt-3">
                                    <p class="mb-0" style="text-decoration: underline;"><strong>&nbsp;&nbsp;&nbsp;JOEL D. ABREU&nbsp;&nbsp;&nbsp;</strong></p>
                                    <p class="mb-0" style="margin-left: 20px;"><em>School Head</em></p>
                                </div>
                                <div class="col-md-6 text-center">
                                    <p class="mb-0" style="text-decoration: underline;"><strong>&nbsp;&nbsp;&nbsp;<?php echo strtoupper($teacher_full_name); ?>&nbsp;&nbsp;&nbsp;</strong></p>
                                    <p class="mb-0"><em>Teacher</em></p>
                                </div>
                                <p class="ms-0" style="margin-left: 15px;">_________________________________________________________________</p>
                            </div>

                            <div style="font-size: 10pt;">
                                <p class="text-center mb-1"><strong>CERTIFICATE OF TRANSFER</strong></p>
                                <p class="mb-0">Admitted to Grade: ___________________________ Section: _____________________</p>
                                <p class="mb-0">Eligibility for Admission to Grade: ____________________________</p>
                                
                                <div class="row mt-1">
                                    <div class="col-6">
                                        <p class="mb-0 fw-bold">Approved:</p>
                                        <p class="mb-0 text-center">_____________________</p>
                                        <p class="mb-0 text-center"><em>Teacher</em></p>
                                    </div>
                                    <div class="col-6 text-center">
                                        <p class="mb-0">&nbsp;</p>
                                        <p class="mb-0">_____________________</p>
                                        <p class="mb-0"><em>School Head</em></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="font-size: 9pt;">
                            <p class="mb-1 text-center"><strong>Cancellation of Eligibility to Transfer</strong></p>
                            <p class="mb-0">Admitted in: _______________________________________________</p>
                            <p class="mb-0">Date: ________________________</p>
                            <p class="ms-2 text-center">_________________________</p>
                            <p class="ms-2 text-center"><em>Principal</em></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="report-card page-break">
                <div class="row" style="margin-top: 0.15in;margin-bottom: 0.1in;margin-left: 0.15in;margin-right: 0.15in;">
                    <div class="col-md-6">
                        <h5 style="font-size: 11pt;">GRADE 7 - 10</h5>
                        <h6 class="mt-4 mb-1" style="font-size: 11pt;"><strong>REPORT ON LEARNING PROGRESS AND ACHIEVEMENT</strong></h6>
<table class="table table-bordered subject-table compact-table" style="font-size: 9pt; vertical-align: middle;">
    <thead>
        <tr>
            <th style="width:24%;" rowspan="2" >Learning Areas</th>
            <th colspan="4" style="text-align:center;">Quarter</th>
            <th style="width:13%;">Final Grade</th>
            <th style="width:15%;">Remarks</th>
        </tr>
        <tr>
            <th style="width:6%; text-align:center;"><strong>1</strong></th>
            <th style="width:6%; text-align:center;"><strong>2</strong></th>
            <th style="width:6%; text-align:center;"><strong>3</strong></th>
            <th style="width:6%; text-align:center;"><strong>4</strong></th>
            <th></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $general_average = 0;
        $subject_count = 0;

        foreach ($ordered_subject_list as $subject) {
            $grade_found = null;
            
            // For MAPEH components, we need to handle them differently
            if ($subject['SubjectID'] == 'MAPEH_MUSICARTS' || $subject['SubjectID'] == 'MAPEH_PEHEALTH') {
                // These are sub-components, so we'll leave them blank or handle specially
                $q1 = $q2 = $q3 = $q4 = $final = '';
                $remarks = '';
            } else {
                // Regular subjects
                foreach ($grades as $grade) {
                    if ($grade['subject'] == $subject['SubjectID']) {
                        $grade_found = $grade;
                        break;
                    }
                }
                
                $q1 = $grade_found && !empty($grade_found['Q1']) ? $grade_found['Q1'] : '';
                $q2 = $grade_found && !empty($grade_found['Q2']) ? $grade_found['Q2'] : '';
                $q3 = $grade_found && !empty($grade_found['Q3']) ? $grade_found['Q3'] : '';
                $q4 = $grade_found && !empty($grade_found['Q4']) ? $grade_found['Q4'] : '';
                
                // Calculate final grade if all quarters are available
                if (!empty($q1) && !empty($q2) && !empty($q3) && !empty($q4)) {
                    $final = round(($q1 + $q2 + $q3 + $q4) / 4);
                } else {
                    $final = $grade_found && !empty($grade_found['Final']) ? $grade_found['Final'] : '';
                }
                
                $remarks = '';
                if (!empty($final)) {
                    if ($final >= 75) {
                        $remarks = 'Passed';
                    } else {
                        $remarks = 'Failed';
                    }
                    
                    // Only count regular subjects for general average (not MAPEH components)
                    if ($subject['SubjectName'] != 'MAPEH') {
                        $general_average += $final;
                        $subject_count++;
                    }
                }
            }
        
            // Apply different styling for MAPEH components
            $cell_style = '';
            if (strpos($subject['SubjectName'], '•') === 0) {
                $cell_style = 'style="padding: 7px; font-size: 11pt;"';
            } else {
                $cell_style = 'style="font-size: 9pt; margin: 0px; padding: 0px; text-align: left; padding-left: 10px; padding-right: 30px; vertical-align: middle;"';
            }
            
            echo "<tr>
                <td $cell_style>{$subject['SubjectName']}</td>
                <td style=\"text-align: center; font-size: 9pt;\">$q1</td>
                <td style=\"text-align: center; font-size: 9pt;\">$q2</td>
                <td style=\"text-align: center; font-size: 9pt;\">$q3</td>
                <td style=\"text-align: center; font-size: 9pt;\">$q4</td>
                <td style=\"text-align: center; font-size: 9pt;\">$final</td>
                <td style=\"text-align: center; font-size: 9pt;\">$remarks</td>
            </tr>";
        }

        // Calculate general average
        $gen_avg = $subject_count > 0 ? round($general_average / $subject_count, 2) : '';
        $gen_remarks = '';
        if (!empty($gen_avg)) {
            if ($gen_avg >= 75) {
                $gen_remarks = 'Passed';
            } else {
                $gen_remarks = 'Failed';
            }
        }
        ?>
        <tr>
            <td style="color: white;">0</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td colspan="4" class="static-cell"><Strong>General Average</Strong></td>
            <td><?php echo $gen_avg; ?></td>
            <td><?php echo $gen_remarks; ?></td>
        </tr>
    </tbody>
</table>        
                        <div class="legend mt-4" style="font-size: 11pt; margin-top: 50px;">
                            <div class="row">
                                <div class="col-md-5">
                                  <p class="mb-3"><strong><em>Descriptors</em></strong></p>
                                  <p class="mb-0"><em>Outstanding </em></p>
                                  <p class="mb-0"><em>Very Satisfactory </em></p>
                                  <p class="mb-0"><em>Satisfactory </em></p>
                                  <p class="mb-0"><em>Fairly Satisfactory </em></p>
                                  <p class="mb-0"><em>Did Not Meet Expectations</em></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-3 fw-bold"><em>Grading Scale</em></p>
                                    <p class="mb-0"><em>90-100</em></p>
                                    <p class="mb-0"><em>85-89</em></p>
                                    <p class="mb-0"><em>80-84</em></p>
                                    <p class="mb-0"><em>75-79</em></p>
                                    <p class="mb-0"><em>Below 75</em></p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-3 fw-bold"><em>Remarks</em></p>
                                    <p class="mb-0"><em>Passed</em></p>
                                    <p class="mb-0"><em>Passed</em></p>
                                    <p class="mb-0"><em>Passed</em></p>
                                    <p class="mb-0"><em>Passed</em></p>
                                    <p class="mb-0"><em>Failed</em></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- Report on Learner's Observed Values -->
                        <h6 class="text-center" style="font-size: 11pt;"><strong>REPORT ON LEARNER'S OBSERVED VALUES</strong></h6>
                        
                        <table class="table table-bordered values-table" style=" font-size: 10pt; vertical-align: middle;">
                            <thead>
                                <tr>
                                    <th style="width:17%; height: 30px;">Core Values</th>
                                    <th style="width:35%;">Behavior Statements</th>
                                    <th style="width:7%; text-align:center;">Q1</th>
                                    <th style="width:7%; text-align:center;">Q2</th>
                                    <th style="width:7%; text-align:center;">Q3</th>
                                    <th style="width:7%; text-align:center;">Q4</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 10pt; padding: 10px; margin: 0px;">
                                <tr>
                                    <td class="static-cell" rowspan="2">1. Maka-Diyos</td>
                                    <td class="static-cell">Expresses one's spiritual beliefs while respecting the spiritual beliefs of others.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makadiyos_1']) ? $observed_values[1]['makadiyos_1'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makadiyos_1']) ? $observed_values[2]['makadiyos_1'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makadiyos_1']) ? $observed_values[3]['makadiyos_1'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makadiyos_1']) ? $observed_values[4]['makadiyos_1'] : ''; ?></td>
                                </tr>
                                <tr>
                                    <td class="static-cell">Shows adherence to ethical principles by upholding truth.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makadiyos_2']) ? $observed_values[1]['makadiyos_2'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makadiyos_2']) ? $observed_values[2]['makadiyos_2'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makadiyos_2']) ? $observed_values[3]['makadiyos_2'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makadiyos_2']) ? $observed_values[4]['makadiyos_2'] : ''; ?></td>
                                </tr>
                                <tr>
                                    <td class="static-cell" rowspan="2">2. Makatao</td>
                                    <td class="static-cell">Is sensitive to individual, social and cultural differences.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makatao_1']) ? $observed_values[1]['makatao_1'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makatao_1']) ? $observed_values[2]['makatao_1'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makatao_1']) ? $observed_values[3]['makatao_1'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makatao_1']) ? $observed_values[4]['makatao_1'] : ''; ?></td>
                                </tr>
                                <tr>
                                    <td class="static-cell">Demonstrates contributions toward solidarity.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makatao_2']) ? $observed_values[1]['makatao_2'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makatao_2']) ? $observed_values[2]['makatao_2'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makatao_2']) ? $observed_values[3]['makatao_2'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makatao_2']) ? $observed_values[4]['makatao_2'] : ''; ?></td>
                                </tr>
                                <tr>
                                    <td class="static-cell">3. Maka-kalikasan</td>
                                    <td class="static-cell">Cares for the environment and utilizes resources wisely, judiciously, and economically.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makakalikasan_1']) ? $observed_values[1]['makakalikasan_1'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makakalikasan_1']) ? $observed_values[2]['makakalikasan_1'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makakalikasan_1']) ? $observed_values[3]['makakalikasan_1'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makakalikasan_1']) ? $observed_values[4]['makakalikasan_1'] : ''; ?></td>
                                </tr>
                                <tr>
                                    <td class="static-cell" rowspan="2">4. Makabansa</td>
                                    <td class="static-cell">Demonstrates pride in being a Filipino; exercises rights and responsibilities of a Filipino citizen.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makabansa_1']) ? $observed_values[1]['makabansa_1'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makabansa_1']) ? $observed_values[2]['makabansa_1'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makabansa_1']) ? $observed_values[3]['makabansa_1'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makabansa_1']) ? $observed_values[4]['makabansa_1'] : ''; ?></td>
                                </tr>
                                <tr>
                                    <td class="static-cell">Demonstrates appropriate behavior in carrying out activities in the school, community and country.</td>
                                    <td class="data-cell-1"><?php echo isset($observed_values[1]['makabansa_2']) ? $observed_values[1]['makabansa_2'] : ''; ?></td>
                                    <td class="data-cell-2"><?php echo isset($observed_values[2]['makabansa_2']) ? $observed_values[2]['makabansa_2'] : ''; ?></td>
                                    <td class="data-cell-3"><?php echo isset($observed_values[3]['makabansa_2']) ? $observed_values[3]['makabansa_2'] : ''; ?></td>
                                    <td class="data-cell-4"><?php echo isset($observed_values[4]['makabansa_2']) ? $observed_values[4]['makabansa_2'] : ''; ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="legend" style="font-size: 11pt; margin-top: 40px;">
                            <div class="row">
                                <div class="col-md-4">
                            <p class="mb-3 ms-1"><strong>Marking</strong></p>
                            <p class="mb-0 text-center">AO </p>
                            <p class="mb-0 text-center">SO </p>
                            <p class="mb-0 text-center">RO </p>
                            <p class="mb-0 text-center">NO </p>
                                </div>
                                <div class="col-md-4 ">
                                    <p class="mb-3 fw-bold">Numerical Rating</p>
                                    <p class="mb-0">Always Observed</p>
                                    <p class="mb-0">Sometimes Observed</p>
                                    <p class="mb-0">Rarely Observed</p>
                                    <p class="mb-0">Not Observed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
          
    <script>
    <?php if ($generate_pdf): ?>
    // Force data-only styling for direct PDF generation
    document.addEventListener('DOMContentLoaded', function() {
        // Generate PDF immediately after applying styles
        setTimeout(function() {
            generatePDF();
        }, 500);
    });
    <?php else: ?>
    // Manual PDF generation for view mode
    document.querySelectorAll('.pdf-quarter').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const quarter = this.getAttribute('data-quarter');
            
            // Validate before generating PDF
            validateAndGeneratePDF(quarter);
        });
    });

    function validateAndGeneratePDF(quarter) {
        // Show loading immediately
        const loadingOverlay = document.getElementById('pdfLoading');
        loadingOverlay.style.display = 'flex';
        
        // Simple client-side validation
        const quarterNum = parseInt(quarter);
        if (quarterNum < 1 || quarterNum > 4) {
            loadingOverlay.style.display = 'none';
            showNotification('Invalid quarter selected.', 'error');
            return;
        }
        
        // For more robust validation, you might want to make an AJAX call to check grades
        // For now, we'll proceed with generation and let the server handle validation
        generatePDF(quarter);
    }
    <?php endif; ?>

    function generatePDF(quarter = null) {
        // Use the quarter from parameter or from URL
        const selectedQuarter = quarter || <?php echo json_encode($quarter); ?> || '1';
        
        // Only show loading for manual generation
        <?php if (!$generate_pdf): ?>
        const loadingOverlay = document.getElementById('pdfLoading');
        loadingOverlay.style.display = 'flex';
        <?php endif; ?>

        // Student info for filename
        const studentName = "<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $student['LastName'] . '_' . $student['FirstName']); ?>";
        const filename = `Report_Card_${studentName}_Q${selectedQuarter}_<?php echo $current_year; ?>.pdf`;
        
        // Get the PDF content
        const element = document.getElementById('pdf-content');
        
        // Add quarter class to body for proper styling
        document.body.classList.add('quarter-' + selectedQuarter);
        
        // PDF options with reduced margins
        const options = {
            filename: filename,
            image: { 
                type: 'jpeg', 
                quality: 0.98 
            },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#FFFFFF',
                scrollX: 0,
                scrollY: 0,
                windowWidth: document.documentElement.offsetWidth,
                windowHeight: document.documentElement.offsetHeight
            },
            jsPDF: { 
                unit: 'in', 
                format: 'letter', 
                orientation: 'landscape' 
            },
            pagebreak: { 
                mode: ['avoid-all', 'css', 'legacy'],
                before: '.page-break'
            }
        };

        // Generate PDF
        html2pdf()
            .set(options)
            .from(element)
            .save()
            .then(() => {
                <?php if (!$generate_pdf): ?>
                // Hide loading for manual generation
                loadingOverlay.style.display = 'none';
                
                // Remove quarter class
                document.body.classList.remove('quarter-' + selectedQuarter);
                
                // Success message for manual generation
                showNotification('PDF saved successfully!', 'success');
                
                // Redirect back to list page after successful generation
                setTimeout(() => {
                    window.location.href = 'record.php';
                }, 2000);
                <?php else: ?>
                // For direct PDF generation, redirect back to list page after generation
                setTimeout(function() {
                    window.location.href = 'record.php';
                }, 1000);
                <?php endif; ?>
            })
            .catch((error) => {
                <?php if (!$generate_pdf): ?>
                // Hide loading for manual generation
                loadingOverlay.style.display = 'none';
                
                // Remove quarter class
                document.body.classList.remove('quarter-' + selectedQuarter);
                
                // Error message for manual generation
                showNotification('Error generating PDF. Please try again.', 'error');
                
                // Redirect back to list page after error
                setTimeout(() => {
                    window.location.href = 'record.php';
                }, 3000);
                <?php else: ?>
                // Error handling for direct PDF generation - redirect to list page
                console.error('PDF generation error:', error);
                setTimeout(function() {
                    window.location.href = 'record.php';
                }, 1000);
                <?php endif; ?>
                console.error('PDF generation error:', error);
            });
    }

    // Notification function (only for manual generation)
    <?php if (!$generate_pdf): ?>
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            border-radius: 5px;
            z-index: 10000;
            font-weight: bold;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 3000);
    }
    <?php endif; ?>

    if (typeof html2pdf === 'undefined') {
        <?php if (!$generate_pdf): ?>
        document.getElementById('pdfButton').style.display = 'none';
        showNotification('PDF library not loaded. Please refresh the page.', 'error');
        <?php endif; ?>
        console.warn('html2pdf library not loaded. PDF functionality disabled.');
    }
    </script>
    <?php endif; ?>
</body>
</html>