<?php
include '../config.php';
session_start();

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher_query = "SELECT * FROM teacher WHERE userID = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

// Get the current year and the next year
$year = (string)date("Y");
$next_year = (string)($year + 1);
$current_year = $year . '-' . $next_year;

// Get student ID from query parameter
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

if (!$student_id) {
    header("Location: record.php");
    exit();
}

// Initialize student data
$student = null;
$grades = [];
$attendance = [];
$observed_values = [];
$section = [];

// Get student information
$student_query = "SELECT * FROM student WHERE StudentID = '$student_id'";
$student_result = mysqli_query($conn, $student_query);
$student = mysqli_fetch_assoc($student_result);

if (!$student) {
    die("Student not found");
}

// Get student's current section
$section_query = "
    SELECT s.*, se.SchoolYear 
    FROM section_enrollment se 
    JOIN section s ON se.SectionID = s.SectionID 
    WHERE se.StudentID = '$student_id' 
    AND se.SchoolYear = '$current_year'
    AND se.status = 'active'
    LIMIT 1
";
$section_result = mysqli_query($conn, $section_query);
if (mysqli_num_rows($section_result) > 0) {
    $section = mysqli_fetch_assoc($section_result);
}

// CORRECTED: Get grades from grades table (not grades_details)
$grades_query = "
    SELECT g.*, s.SubjectName 
    FROM grades g 
    JOIN subject s ON g.subject = s.SubjectID 
    WHERE g.student_id = '$student_id'
    AND g.school_year = '$current_year'
";
$grades_result = mysqli_query($conn, $grades_query);

// Debug: Check if query works
if (!$grades_result) {
    die("Grades query failed: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($grades_result)) {
    $grades[] = $row;
}

// CORRECTED: Get attendance data
$attendance_query = "
    SELECT MONTH(Date) as month_num, 
           COUNT(*) as total_days,
           SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_days,
           SUM(CASE WHEN Status = 'excused' THEN 1 ELSE 0 END) as excused_days
    FROM attendance 
    WHERE StudentID = '$student_id'
    AND (
        (YEAR(Date) = '$year' AND MONTH(Date) >= 6) OR 
        (YEAR(Date) = '$next_year' AND MONTH(Date) <= 5)
    )
    GROUP BY MONTH(Date), YEAR(Date)
    ORDER BY YEAR(Date), MONTH(Date)
";
$attendance_result = mysqli_query($conn, $attendance_query);

// Debug: Check if attendance query works
if (!$attendance_result) {
    die("Attendance query failed: " . mysqli_error($conn));
}

$attendance_data = [];
while ($row = mysqli_fetch_assoc($attendance_result)) {
    $attendance_data[$row['month_num']] = [
        'present' => $row['present_days'],
        'absent' => $row['absent_days'],
        'excused' => $row['excused_days'],
        'total' => $row['total_days']
    ];
}

// Map month numbers to names as in the report card (School Year: June to May)
$month_names = [
    6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sept', 10 => 'Oct', 
    11 => 'Nov', 12 => 'Dec', 1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 
    4 => 'Apr', 5 => 'May'
];

foreach ($month_names as $num => $name) {
    $attendance[$name] = isset($attendance_data[$num]) ? $attendance_data[$num] : ['present' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0];
}

// Function to calculate age from birthdate
function calculateAge($birthdate) {
    if (empty($birthdate) || $birthdate == '0000-00-00') return '';
    
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

// Get teacher's name for signature
$teacher_name_query = "SELECT fName, lName FROM teacher WHERE userID = '$teacher_id'";
$teacher_name_result = mysqli_query($conn, $teacher_name_query);
$teacher_name = mysqli_fetch_assoc($teacher_name_result);
$teacher_full_name = $teacher_name ? $teacher_name['fName'] . ' ' . $teacher_name['lName'] : 'Teacher';

// Load observed/student values (per quarter) if table exists
$observed_values = [];
$vals_sql = "SELECT * FROM student_values WHERE student_id = '$student_id' AND school_year = '$current_year'";
$vals_res = mysqli_query($conn, $vals_sql);
if ($vals_res) {
    while ($r = mysqli_fetch_assoc($vals_res)) {
        $observed_values[intval($r['quarter'])] = $r;
    }
}
/*
// AJAX endpoint: check completeness of grades before allowing PDF save
if (isset($_GET['action']) && $_GET['action'] === 'check_complete') {
    header('Content-Type: application/json');

    $incomplete_quarters = [];

    // Determine subjects for the student's grade level
    $grade_level = !empty($section) ? $section['GradeLevel'] : '';
    $subjects_query_ajax = "SELECT SubjectID FROM subject WHERE GradeLevel = '$grade_level'";
    $subjects_result_ajax = mysqli_query($conn, $subjects_query_ajax);
    $subjects_ajax = [];
    while ($sr = mysqli_fetch_assoc($subjects_result_ajax)) {
        $subjects_ajax[] = $sr['SubjectID'];
    }

    // For each subject check the grades table for Q1..Q4 presence
    foreach ($subjects_ajax as $subid) {
        $gq = "SELECT Q1, Q2, Q3, Q4 FROM grades WHERE student_id = '$student_id' AND subject = '$subid' AND school_year = '$current_year' LIMIT 1";
        $gres = mysqli_query($conn, $gq);
        if (!$gres || mysqli_num_rows($gres) === 0) {
            // No grades row means quarters missing
            $incomplete_quarters = array_unique(array_merge($incomplete_quarters, [1,2,3,4]));
            continue;
        }

        $grow = mysqli_fetch_assoc($gres);
        for ($q = 1; $q <= 4; $q++) {
            $col = 'Q' . $q;
            if (!isset($grow[$col]) || $grow[$col] === '' || $grow[$col] === null) {
                $incomplete_quarters[] = $q;
            }
        }
    }

    $incomplete_quarters = array_values(array_unique($incomplete_quarters));
    if (!empty($incomplete_quarters)) {
        $names = array_map(function($q){ return 'Quarter '.$q; }, $incomplete_quarters);
        $msg = 'Cannot save PDF. Grades are incomplete for: ' . implode(', ', $names) . '. Please complete grades before saving the report card.';
        echo json_encode(['ok' => false, 'message' => $msg]);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'All quarters complete.']);
    exit;
}*/

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?php echo $student['FirstName'] . ' ' . $student['LastName']; ?></title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
    /* Reset and Base Styles */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Times New Roman', serif;
        background-color: #f5f5f5;
        margin: 0;
        padding: 0;
        line-height: 1.4;
    }
    
    /* Typography */
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
    
    /* Layout */
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
        max-width: 11in; /* Landscape width */
        position: relative;
        page-break-inside: avoid; /* Prevent page breaks inside report cards */
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
    
    .table-bordered th, .table-bordered td {
        border: 1px solid #000;
        padding: 2px;
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
        font-size: 11pt;
        width: 100%;
    }
    
    .subject-table th, .subject-table td {
        padding: 3px;
    }
    
    .values-table {
        font-size: 11pt;
        width: 100%;
    }
    
    .values-table th, .values-table td {
        padding: 1px;
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
    
    .compact-table {
        margin-bottom: 5px;
    }
    
    /* Print Styles - FIXED */
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
        }

        .page-break {
            page-break-before: always;
        }

        /* Ensure tables don't break across pages */
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
        
        tfoot {
            display: table-footer-group;
        }

        /* Adjust font sizes for print */
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

        /* Ensure proper spacing */
        .mt-1, .mt-2, .mt-3, .mt-4, .mt-5,
        .mb-1, .mb-2, .mb-3, .mb-4, .mb-5 {
            margin-top: 0.05in !important;
            margin-bottom: 0.05in !important;
        }

        /* Fix table cell padding for print */
        .table-bordered th, 
        .table-bordered td {
            padding: 2px !important;
            font-size: 7pt !important;
        }

        /* Ensure images print correctly */
        img {
            max-width: 100%;
            height: auto;
        }

        /* Student info styles for print */
        .student-info-row {
            margin-bottom: 1px !important;
        }
        
        .student-info-value {
            min-height: 14px !important;
            font-size: 9pt !important;
        }
    }

    /* Additional Styles for Print Page Formatting */
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

    /* Responsive adjustments */
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

    /* PDF Loading Overlay */
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

    /* Fixed action buttons shown in the top-right corner (no-print) */
    .fixed-action-buttons {
        position: fixed;
        top: 12px;
        right: 12px;
        z-index: 12000;
        display: flex;
        gap: 8px;
        align-items: center;
    }
    </style>
</head>
<body>
    <!-- Fixed action buttons at top-right -->
    <div class="fixed-action-buttons no-print">
        <button type="button" class="text-secondary btn" id="pdfButton" title="Save as PDF" style="font-size: 50px;"><i class="fas fa-file-pdf"></i></button>
        <a href="record.php" class="text-secondary " title="Back" style="font-size: 30px;"><i class="fas fa-times"></i></a>
    </div>

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
                
                    <table class="table table-bordered attendance-table compact-table" style="height: 250px;">
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

                    <div class="signature-area " style="margin-top: 30px;">
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
                            <p class="mb-3  ms-1 col-md-4 text-end"></p>
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
                        <p class="mb-0 fw-bold old-english-font" style="font-size: 9pt;">Republic of the Philippines</p>
                        <p class="mb-0 fw-bold old-english-font" style="font-size: 11pt;">Department of Education</p>
                        <p class="mb-0" style="font-size: 7pt;">REGION IV-A CALABARZON</p>
                        <p class="mb-0" style="font-size: 8pt;"><strong>DIVISION OF BATANGAS</strong></p>
                        <p class="mb-0" style="font-size: 9pt;">Nasugbu East Sub-Office</p>
                        <p class="mb-0" style="font-size: 7pt;"><strong>BALAYTIGUE NATIONAL HIGH SCHOOL</strong></p>
                        <p class="mb-0" style="font-size: 7pt;">Balaytigue Nasugbu, Batangas</p>
                    </div>

                    <!-- Progress Report Card Title -->
                    <h4 class="text-center mt-2 mb-0" style="font-size: 12pt;"><strong>PROGRESS REPORT CARD</strong></h4>
                    <h4 class="text-center mb-3" style="font-size: 11pt;">School Year <?php echo $current_year; ?></h4>

                    <!-- Student Information - Converted to DIV layout -->
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
                    <h5 class="mb-2" style="font-size: 11pt;">GRADE 7 - 10</h5>
                    <h6 class="mt-3 mb-1" style="font-size: 11pt;"><strong>REPORT ON LEARNING PROGRESS AND ACHIEVEMENT</strong></h6>
                    
                    <table class="table table-bordered subject-table compact-table" style="height: 360px; font-size: 11pt;">
                        <thead>
                            <tr>
                                <th style="width:30%;" rowspan="2">Learning Areas</th>
                                <th colspan="4" style="text-align:center;">Quarter</th>
                                <th style="width:10%;">Final Grade</th>
                                <th style="width:12%;">Remarks</th>
                            </tr>
                            <tr>
                                <th style="width:8%; text-align:center;"><strong>1</strong></th>
                                <th style="width:8%; text-align:center;"><strong>2</strong></th>
                                <th style="width:8%; text-align:center;"><strong>3</strong></th>
                                <th style="width:8%; text-align:center;"><strong>4</strong></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get all subjects for the student's grade level
                            $grade_level = !empty($section) ? $section['GradeLevel'] : '' ;
                            $subjects_query = "
                                SELECT s.SubjectID, s.SubjectName 
                                FROM subject s 
                                WHERE s.GradeLevel = '$grade_level'
                                ORDER BY s.SubjectID
                            ";
                            $subjects_result = mysqli_query($conn, $subjects_query);
                            $all_subjects = [];
                            while ($subject_row = mysqli_fetch_assoc($subjects_result)) {
                                $all_subjects[] = $subject_row;
                            }

                            $general_average = 0;
                            $subject_count = 0;
                            
                            foreach ($all_subjects as $subject) {
                                $grade_found = null;
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
                                    if ($final >= 90) {
                                        $remarks = 'Passed';
                                    } elseif ($final >= 85) {
                                        $remarks = 'Passed';
                                    } elseif ($final >= 80) {
                                        $remarks = 'Passed';
                                    } elseif ($final >= 75) {
                                        $remarks = 'Passed';
                                    } else {
                                        $remarks = 'Failed';
                                    }
                                    
                                    $general_average += $final;
                                    $subject_count++;
                                }
                                
                                echo "<tr>
                                    <td style=\"text-align:left;padding-left:10px;\">{$subject['SubjectName']}</td>
                                    <td>$q1</td>
                                    <td>$q2</td>
                                    <td>$q3</td>
                                    <td>$q4</td>
                                    <td>$final</td>
                                    <td>$remarks</td>
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
                                <td colspan="5" class="text-end"><strong>General Average</strong></td>
                                <td><strong><?php echo $gen_avg; ?></strong></td>
                                <td><strong><?php echo $gen_remarks; ?></strong></td>
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
                    <h6 class="text-center mb-1" style="font-size: 11pt;"><strong>REPORT ON LEARNER'S OBSERVED VALUES</strong></h6>
                    
                    <table class="table table-bordered values-table" style="height: 450px; font-size: 10pt;">
                        <thead>
                            <tr>
                                <th style="width:20%; height: 30px;">Core Values</th>
                                <th style="width:50%;">Behavior Statements</th>
                                <th style="width:7%; text-align:center;">Q1</th>
                                <th style="width:7%; text-align:center;">Q2</th>
                                <th style="width:7%; text-align:center;">Q3</th>
                                <th style="width:7%; text-align:center;">Q4</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 10pt;">
                            <tr>
                                <td rowspan="2">1. Maka-Diyos</td>
                                <td>Expresses one's spiritual beliefs while respecting the spiritual beliefs of others.</td>
                                <td><?php echo isset($observed_values[1]['makadiyos_1']) ? $observed_values[1]['makadiyos_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makadiyos_1']) ? $observed_values[2]['makadiyos_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makadiyos_1']) ? $observed_values[3]['makadiyos_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makadiyos_1']) ? $observed_values[4]['makadiyos_1'] : ''; ?></td>
                            </tr>
                            <tr>
                                <td>Shows adherence to ethical principles by upholding truth.</td>
                                <td><?php echo isset($observed_values[1]['makadiyos_2']) ? $observed_values[1]['makadiyos_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makadiyos_2']) ? $observed_values[2]['makadiyos_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makadiyos_2']) ? $observed_values[3]['makadiyos_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makadiyos_2']) ? $observed_values[4]['makadiyos_2'] : ''; ?></td>
                            </tr>
                            <tr>
                                <td rowspan="2">2. Makatao</td>
                                <td>Is sensitive to individual, social and cultural differences.</td>
                                <td><?php echo isset($observed_values[1]['makatao_1']) ? $observed_values[1]['makatao_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makatao_1']) ? $observed_values[2]['makatao_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makatao_1']) ? $observed_values[3]['makatao_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makatao_1']) ? $observed_values[4]['makatao_1'] : ''; ?></td>
                            </tr>
                            <tr>
                                <td>Demonstrates contributions toward solidarity.</td>
                                <td><?php echo isset($observed_values[1]['makatao_2']) ? $observed_values[1]['makatao_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makatao_2']) ? $observed_values[2]['makatao_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makatao_2']) ? $observed_values[3]['makatao_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makatao_2']) ? $observed_values[4]['makatao_2'] : ''; ?></td>
                            </tr>
                            <tr>
                                <td>3. Maka-kalikasan</td>
                                <td>Cares for the environment and utilizes resources wisely, judiciously, and economically.</td>
                                <td><?php echo isset($observed_values[1]['makakalikasan_1']) ? $observed_values[1]['makakalikasan_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makakalikasan_1']) ? $observed_values[2]['makakalikasan_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makakalikasan_1']) ? $observed_values[3]['makakalikasan_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makakalikasan_1']) ? $observed_values[4]['makakalikasan_1'] : ''; ?></td>
                            </tr>
                            <tr>
                                <td rowspan="2">4. Makabansa</td>
                                <td>Demonstrates pride in being a Filipino; exercises rights and responsibilities of a Filipino citizen.</td>
                                <td><?php echo isset($observed_values[1]['makabansa_1']) ? $observed_values[1]['makabansa_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makabansa_1']) ? $observed_values[2]['makabansa_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makabansa_1']) ? $observed_values[3]['makabansa_1'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makabansa_1']) ? $observed_values[4]['makabansa_1'] : ''; ?></td>
                            </tr>
                            <tr>
                                <td>Demonstrates appropriate behavior in carrying out activities in the school, community and country.</td>
                                <td><?php echo isset($observed_values[1]['makabansa_2']) ? $observed_values[1]['makabansa_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[2]['makabansa_2']) ? $observed_values[2]['makabansa_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[3]['makabansa_2']) ? $observed_values[3]['makabansa_2'] : ''; ?></td>
                                <td><?php echo isset($observed_values[4]['makabansa_2']) ? $observed_values[4]['makabansa_2'] : ''; ?></td>
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
document.getElementById('pdfButton').addEventListener('click', function() {
    generatePDF();
});

function generatePDF() {
    // Show loading
    const loadingOverlay = document.getElementById('pdfLoading');
    loadingOverlay.style.display = 'flex';

    // Student info for filename
    const studentName = "<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $student['LastName'] . '_' . $student['FirstName']); ?>";
    const filename = `Report_Card_${studentName}_<?php echo $current_year; ?>.pdf`;
    
    // Get the PDF content
    const element = document.getElementById('pdf-content');
    
    // PDF options with reduced margins
    const options = { // Reduced margins: [top, right, bottom, left]
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
            // Hide loading
            loadingOverlay.style.display = 'none';
            
            // Success message
            showNotification('PDF saved successfully!', 'success');
        })
        .catch((error) => {
            // Hide loading
            loadingOverlay.style.display = 'none';
            
            // Error message
            showNotification('Error generating PDF. Please try again.', 'error');
            console.error('PDF generation error:', error);
        });
}

// Notification function
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

// Fallback if html2pdf fails to load
if (typeof html2pdf === 'undefined') {
    document.getElementById('pdfButton').style.display = 'none';
    console.warn('html2pdf library not loaded. PDF functionality disabled.');
}
</script>
</body>
</html>