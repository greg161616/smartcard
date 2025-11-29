<?php
// File: get_grade_details.php
session_start();
require '../config.php';

// Check if user is logged in as teacher or principal
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'principal')) {
    die("Unauthorized access.");
}

// Get parameters - using student_id instead of LRN to match the schema
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 0;
$schoolYear = isset($_GET['school_year']) ? $_GET['school_year'] : '';

// Validate parameters
if (!$studentId || !$subjectId || !$quarter || !$schoolYear) {
    die("Invalid parameters.");
}

// Get student details
$stmt = $conn->prepare("SELECT LRN, FirstName, LastName, Middlename FROM student WHERE StudentID = ?");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) {
    die("Student not found.");
}
$student = $result->fetch_assoc();
$studentName = $student['FirstName'] . ' ' . 
               ($student['Middlename'] ? $student['Middlename'] . ' ' : '') . 
               $student['LastName'];
$lrn = $student['LRN'];
$stmt->close();

// Get subject name
$stmt = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ?");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$result = $stmt->get_result();
$subjectName = $result->num_rows ? $result->fetch_assoc()['SubjectName'] : 'Unknown Subject';
$stmt->close();

// Get grade details - using the correct column names from schema
$stmt = $conn->prepare("
    SELECT * FROM grades_details 
    WHERE studentID = ? AND subjectID = ? AND quarter = ? AND school_year = ?
");
$stmt->bind_param('iiis', $studentId, $subjectId, $quarter, $schoolYear);
$stmt->execute();
$result = $stmt->get_result();

if (!$result->num_rows) {
    echo "<div class='alert alert-info'>No detailed grade information available for this quarter.</div>";
    exit;
}

$gradeDetails = $result->fetch_assoc();
$stmt->close();

// Get highest possible scores - using correct table and column names
$stmt = $conn->prepare("
    SELECT * FROM highest_possible_score 
    WHERE subjectID = ? AND quarter = ? AND school_year = ?
");
$stmt->bind_param('iis', $subjectId, $quarter, $schoolYear);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows) {
    $hps = $result->fetch_assoc();
} else {
    // If no highest scores are set, create default values
    $hps = [];
    for ($i = 1; $i <= 10; $i++) {
        $hps["ww$i"] = 0;
        $hps["pt$i"] = 0;
    }
    $hps['qa1'] = 0;
    $hps['ww_total'] = 0;
    $hps['pt_total'] = 0;
    $hps['ww_ps'] = 0;
    $hps['ww_ws'] = 0;
    $hps['pt_ps'] = 0;
    $hps['pt_ws'] = 0;
    $hps['qa_ps'] = 0;
    $hps['qa_ws'] = 0;
}
$stmt->close();

// Display grade details
echo "<div class='container-fluid'>";
echo "<h5>Quarter $quarter Grade Details</h5>";
echo "<div class='card mb-3'>";
echo "<div class='card-body'>";
echo "<p><strong>Student:</strong> $studentName<br>";
echo "<strong>LRN:</strong> $lrn<br>";
echo "<strong>Subject:</strong> $subjectName<br>";
echo "<strong>School Year:</strong> $schoolYear</p>";
echo "</div>";
echo "</div>";

// Written Works Section
echo "<div class='card mb-4'>";
echo "<div class='card-header'>";
echo "<h6 class='mb-0'><i class='fas fa-edit me-2'></i>Written Works</h6>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-sm'>";

// Header row with numbers 1-10 and Total
echo "<thead>";
echo "<tr><th></th>";
for ($i = 1; $i <= 10; $i++) {
    echo "<th class='text-center'>$i</th>";
}
echo "<th class='text-center'>Total</th><th class='text-center'>PS</th><th class='text-center'>WS</th></tr>";
echo "</thead>";

// Highest Possible Score row
echo "<tr><th>Highest Possible Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $hpsValue = isset($hps["ww$i"]) ? $hps["ww$i"] : 0;
    echo "<td class='text-center'>" . ($hpsValue > 0 ? $hpsValue : '-') . "</td>";
}
$hpsWWTotal = isset($hps['ww_total']) ? $hps['ww_total'] : 0;
$hpsWWps = isset($hps['ww_ps']) ? $hps['ww_ps'] : 0;
$hpsWWws = isset($hps['ww_ws']) ? ($hps['ww_ws'] * 100) . '%' : '0%';
echo "<td class='text-center'>" . ($hpsWWTotal > 0 ? $hpsWWTotal : '-') . "</td>";
echo "<td class='text-center'>" . ($hpsWWps > 0 ? $hpsWWps : '-') . "</td>";
echo "<td class='text-center'>$hpsWWws</td></tr>";

// Student scores row
echo "<tr><th>Student Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $score = $gradeDetails["ww$i"];
    echo "<td class='text-center'>" . ($score > 0 ? $score : '-') . "</td>";
}
echo "<td class='text-center fw-bold'>" . ($gradeDetails['ww_total'] > 0 ? $gradeDetails['ww_total'] : '-') . "</td>";
echo "<td class='text-center'>" . ($gradeDetails['ww_ps'] > 0 ? $gradeDetails['ww_ps'] : '-') . "</td>";
echo "<td class='text-center'>" . ($gradeDetails['ww_ws'] > 0 ? $gradeDetails['ww_ws'] : '-') . "</td></tr>";

echo "</table>";
echo "</div>";
echo "</div>";
echo "</div>";

// Performance Tasks Section
echo "<div class='card mb-4'>";
echo "<div class='card-header'>";
echo "<h6 class='mb-0'><i class='fas fa-tasks me-2'></i>Performance Tasks</h6>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-sm'>";

// Header row with numbers 1-10 and Total
echo "<thead>";
echo "<tr><th></th>";
for ($i = 1; $i <= 10; $i++) {
    echo "<th class='text-center'>$i</th>";
}
echo "<th class='text-center'>Total</th><th class='text-center'>PS</th><th class='text-center'>WS</th></tr>";
echo "</thead>";

// Highest Possible Score row
echo "<tr><th>Highest Possible Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $hpsValue = isset($hps["pt$i"]) ? $hps["pt$i"] : 0;
    echo "<td class='text-center'>" . ($hpsValue > 0 ? $hpsValue : '-') . "</td>";
}
$hpsPTTotal = isset($hps['pt_total']) ? $hps['pt_total'] : 0;
$hpsPTps = isset($hps['pt_ps']) ? $hps['pt_ps'] : 0;
$hpsPTws = isset($hps['pt_ws']) ? ($hps['pt_ws'] * 100) . '%' : '0%';
echo "<td class='text-center'>" . ($hpsPTTotal > 0 ? $hpsPTTotal : '-') . "</td>";
echo "<td class='text-center'>" . ($hpsPTps > 0 ? $hpsPTps : '-') . "</td>";
echo "<td class='text-center'>$hpsPTws</td></tr>";

// Student scores row
echo "<tr><th>Student Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $score = $gradeDetails["pt$i"];
    echo "<td class='text-center'>" . ($score > 0 ? $score : '-') . "</td>";
}
echo "<td class='text-center fw-bold'>" . ($gradeDetails['pt_total'] > 0 ? $gradeDetails['pt_total'] : '-') . "</td>";
echo "<td class='text-center'>" . ($gradeDetails['pt_ps'] > 0 ? $gradeDetails['pt_ps'] : '-') . "</td>";
echo "<td class='text-center'>" . ($gradeDetails['pt_ws'] > 0 ? $gradeDetails['pt_ws'] : '-') . "</td></tr>";

echo "</table>";
echo "</div>";
echo "</div>";
echo "</div>";

// Quarterly Assessment and Final Grades
$hpsQA1 = isset($hps['qa1']) ? $hps['qa1'] : 0;
$hpsQaps = isset($hps['qa_ps']) ? $hps['qa_ps'] : 0;
$hpsQAws = isset($hps['qa_ws']) ? ($hps['qa_ws'] * 100) . '%' : '0%';

$qa1 = $gradeDetails['qa1'];
$qa_ps = $gradeDetails['qa_ps'];
$qa_ws = $gradeDetails['qa_ws'];

echo "<div class='row mb-4'>";
echo "<div class='col-md-6'>";
echo "<div class='card h-100'>";
echo "<div class='card-header'>";
echo "<h6 class='mb-0'><i class='fas fa-chart-line me-2'></i>Quarterly Assessment</h6>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-sm'>";
echo "<thead><tr><th></th><th class='text-center'>QA</th><th class='text-center'>PS</th><th class='text-center'>WS</th></tr></thead>";
echo "<tr><th>Highest Possible Score</th><td class='text-center'>" . ($hpsQA1 > 0 ? $hpsQA1 : '-') . "</td><td class='text-center'>" . ($hpsQaps > 0 ? $hpsQaps : '-') . "</td><td class='text-center'>$hpsQAws</td></tr>";
echo "<tr><th>Student Score</th><td class='text-center'>" . ($qa1 > 0 ? $qa1 : '-') . "</td><td class='text-center'>" . ($qa_ps > 0 ? $qa_ps : '-') . "</td><td class='text-center'>" . ($qa_ws > 0 ? $qa_ws : '-') . "</td></tr>";
echo "</table>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div class='col-md-6'>";
echo "<div class='card h-100'>";
echo "<div class='card-header'>";
echo "<h6 class='mb-0'><i class='fas fa-calculator me-2'></i>Final Grades</h6>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-sm'>";
echo "<thead><tr><th class='text-center'>Initial Grade</th><th class='text-center'>Quarterly Grade</th></tr></thead>";
echo "<tr>";
echo "<td class='text-center fw-bold fs-5'>" . ($gradeDetails['initial_grade'] > 0 ? $gradeDetails['initial_grade'] : '-') . "</td>";
echo "<td class='text-center fw-bold fs-5'>" . ($gradeDetails['quarterly_grade'] > 0 ? $gradeDetails['quarterly_grade'] : '-') . "</td>";
echo "</tr>";
echo "</table>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "</div>"; // Close container-fluid
?>