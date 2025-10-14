<?php
// File: get_grade_details.php
session_start();
require '../config.php';

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

// Get parameters
$lrn = isset($_GET['lrn']) ? (int)$_GET['lrn'] : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 0;
$schoolYear = isset($_GET['school_year']) ? $_GET['school_year'] : '';

// Validate parameters
if (!$lrn || !$subjectId || !$quarter || !$schoolYear) {
    die("Invalid parameters.");
}

// Get teacher ID
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) {
    die("Teacher not found.");
}
$teacherId = $result->fetch_assoc()['TeacherID'];
$stmt->close();

// Get student ID from LRN
$stmt = $conn->prepare("SELECT StudentID, FirstName, LastName FROM student WHERE LRN = ?");
$stmt->bind_param('i', $lrn);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) {
    die("Student not found.");
}
$student = $result->fetch_assoc();
$studentId = $student['StudentID'];
$studentName = $student['FirstName'] . ' ' . $student['LastName'];
$stmt->close();

// Get subject name
$stmt = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ?");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$result = $stmt->get_result();
$subjectName = $result->num_rows ? $result->fetch_assoc()['SubjectName'] : 'Unknown Subject';
$stmt->close();

// Get grade details
$stmt = $conn->prepare("
    SELECT * FROM grades_details 
    WHERE studentID = ? AND subjectID = ? AND quarter = ? AND school_year = ?
");
$stmt->bind_param('iiis', $studentId, $subjectId, $quarter, $schoolYear);
$stmt->execute();
$result = $stmt->get_result();

if (!$result->num_rows) {
    echo "<p>No detailed grade information available for this quarter.</p>";
    exit;
}

$gradeDetails = $result->fetch_assoc();
$stmt->close();

// Get highest possible scores
$stmt = $conn->prepare("
    SELECT * FROM highest_possible_score 
    WHERE teacherID = ? AND subjectID = ? AND quarter = ? AND school_year = ?
");
$stmt->bind_param('iiis', $teacherId, $subjectId, $quarter, $schoolYear);
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
}
$stmt->close();

// Display grade details
echo "<h5>Quarter $quarter Grade Details</h5>";
echo "<p><strong>Student:</strong> $studentName<br>";
echo "<strong>Subject:</strong> $subjectName<br>";
echo "<strong>School Year:</strong> $schoolYear</p>";

// Written Works Section
echo "<div class='mb-4'>";
echo "<h6>Written Works</h6>";
echo "<table class='table table-bordered table-sm'>";

// Header row with numbers 1-10 and Total
echo "<tr><th></th>";
for ($i = 1; $i <= 10; $i++) {
    echo "<th>$i</th>";
}
echo "<th>Total</th><th>PS</th><th>WS</th></tr>";

// Highest Possible Score row
echo "<tr><th>Highest Possible Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $hpsValue = isset($hps["ww$i"]) ? $hps["ww$i"] : 0;
    echo "<td>$hpsValue</td>";
}
$hpsWWTotal = isset($hps['ww_total']) ? $hps['ww_total'] : 0;
$hpsWWps = isset($hps['ww_ps']) ? $hps['ww_ps'] : 0;
$hpsws = isset($hps['ww_ws']) ? $hps['ww_ws'] : 0;
$hpsWWws = $hpsws * 100;
echo "<td>$hpsWWTotal</td>";
echo "<td>$hpsWWps</td>";
echo "<td>$hpsWWws%</td></tr>";
// Student scores row
echo "<tr><th>Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $score = $gradeDetails["ww$i"];
    echo "<td>$score</td>";
}
echo "<td>{$gradeDetails['ww_total']}</td><td>{$gradeDetails['ww_ps']}</td><td>{$gradeDetails['ww_ws']}</td></tr>";

echo "</table>";
echo "</div>";

// Performance Tasks Section
echo "<div class='mb-4'>";
echo "<h6>Performance Tasks</h6>";
echo "<table class='table table-bordered table-sm'>";

// Header row with numbers 1-10 and Total
echo "<tr><th></th>";
for ($i = 1; $i <= 10; $i++) {
    echo "<th>$i</th>";
}
echo "<th>Total</th><th>PS</th><th>WS</th></tr>";

// Highest Possible Score row
echo "<tr><th>Highest Possible Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $hpsValue = isset($hps["pt$i"]) ? $hps["pt$i"] : 0;
    echo "<td>$hpsValue</td>";
}
$hpsPTTotal = isset($hps['pt_total']) ? $hps['pt_total'] : 0;
$hpsPTps = isset($hps['pt_ps']) ? $hps['pt_ps'] : 0;
$hpsPTws = isset($hps['pt_ws']) ? $hps['pt_ws'] : 0;
$hpsPTws = $hpsPTws * 100;
echo "<td>$hpsPTTotal</td><td>$hpsPTps</td><td>$hpsPTws%</td></tr>";

// Student scores row
echo "<tr><th>Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $score = $gradeDetails["pt$i"];
    echo "<td>$score</td>";
}
echo "<td>{$gradeDetails['pt_total']}</td><td>{$gradeDetails['pt_ps']}</td><td>{$gradeDetails['pt_ws']}</td></tr>";

echo "</table>";
echo "</div>";
$hpsQA1 = isset($hps['qa1']) ? $hps['qa1'] : 0;
$hpsQaps = isset($hps['qa_ps']) ? $hps['qa_ps'] : 0;
$hpsQAws = isset($hps['qa_ws']) ? $hps['qa_ws'] : 0;
$hpsQAws = $hpsQAws * 100;

$qa1 = $gradeDetails['qa1'];
$qa_ps = $gradeDetails['qa_ps'];
$qa_ws = $gradeDetails['qa_ws'];

// Quarterly Assessment and Final Grades in the same row
echo "<div class='row mb-4'>";
echo "<div class='col-md-6'>";
echo "<h6>Quarterly Assessment</h6>";
echo "<table class='table table-bordered table-sm'>";
echo "<tr><th></th><th>QA</th><th>PS</th><th>WS</th></tr>";
echo "<tr><th>Highest Possible Score</th><td>$hpsQA1</td><td>$hpsQaps</td><td>$hpsQAws%</td></tr>";
echo "<tr><th>Score</th><td>$qa1</td><td>$qa_ps</td><td>$qa_ws</td></tr>";
echo "</table>";
echo "</div>";

echo "<div class='col-md-6'>";
echo "<h6>Final Grades</h6>";
echo "<table class='table table-bordered table-sm'>";
echo "<tr><th>Initial Grade</th><th>Quarterly Grade</th></tr>";
echo "<tr><td><strong>{$gradeDetails['initial_grade']}</strong></td>";
echo "<td><strong>{$gradeDetails['quarterly_grade']}</strong></td></tr>";
echo "</table>";
echo "</div>";
echo "</div>";
?>