<?php
// File: get_grade_details.php
session_start();
require '../config.php';

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    die("<div class='alert alert-danger'>Unauthorized access.</div>");
}

// Get parameters - using student_id instead of LRN for better reliability
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 0;
$schoolYear = isset($_GET['school_year']) ? trim($_GET['school_year']) : '';
$teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// Validate parameters
if (!$studentId || !$subjectId || !$quarter || !$schoolYear || !$teacherId) {
    die("<div class='alert alert-danger'>Invalid parameters provided.</div>");
}

// Verify that the logged-in teacher matches the requested teacher_id
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ? AND TeacherID = ?");
$stmt->bind_param('ii', $userId, $teacherId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) {
    die("<div class='alert alert-danger'>Teacher authorization failed.</div>");
}
$stmt->close();

// Verify teacher is assigned to this subject and has access to this student's grades
$accessStmt = $conn->prepare("
    SELECT COUNT(*) as access_count 
    FROM assigned_subject asg
    JOIN section_enrollment se ON asg.section_id = se.SectionID
    WHERE asg.teacher_id = ? 
    AND asg.subject_id = ? 
    AND asg.school_year = ?
    AND se.StudentID = ?
    AND se.SchoolYear = ?
    AND se.status = 'active'
");
$accessStmt->bind_param('iisis', $teacherId, $subjectId, $schoolYear, $studentId, $schoolYear);
$accessStmt->execute();
$accessResult = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();

if ($accessResult['access_count'] == 0) {
    die("<div class='alert alert-danger'>You don't have access to view this student's grades.</div>");
}

// Get student information
$stmt = $conn->prepare("SELECT LRN, FirstName, MiddleName, LastName FROM student WHERE StudentID = ?");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) {
    die("<div class='alert alert-danger'>Student not found.</div>");
}
$student = $result->fetch_assoc();
$lrn = $student['LRN'];
$studentName = htmlspecialchars($student['FirstName'] . ' ' . 
    ($student['MiddleName'] ? $student['MiddleName'] . ' ' : '') . 
    $student['LastName']);
$stmt->close();

// Get subject name and grade components
$stmt = $conn->prepare("SELECT SubjectName, written_work_percentage, performance_task_percentage, quarterly_assessment_percentage FROM subject WHERE SubjectID = ?");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) {
    die("<div class='alert alert-danger'>Subject not found.</div>");
}
$subjectData = $result->fetch_assoc();
$subjectName = htmlspecialchars($subjectData['SubjectName']);
$writtenWorkPercent = $subjectData['written_work_percentage'];
$performanceTaskPercent = $subjectData['performance_task_percentage'];
$quarterlyAssessmentPercent = $subjectData['quarterly_assessment_percentage'];
$stmt->close();

// Get grade details
$stmt = $conn->prepare("
    SELECT * FROM grades_details 
    WHERE studentID = ? AND subjectID = ? AND teacherID = ? AND quarter = ? AND school_year = ?
");
$stmt->bind_param('iiiss', $studentId, $subjectId, $teacherId, $quarter, $schoolYear);
$stmt->execute();
$result = $stmt->get_result();

if (!$result->num_rows) {
    echo "<div class='alert alert-info'>No detailed grade information available for Quarter $quarter.</div>";
    
    // Show subject component percentages
    echo "<div class='mt-3'>";
    echo "<h6>Subject Grading Components:</h6>";
    echo "<ul class='list-unstyled'>";
    echo "<li>Written Works: " . number_format($writtenWorkPercent * 100, 1) . "%</li>";
    echo "<li>Performance Tasks: " . number_format($performanceTaskPercent * 100, 1) . "%</li>";
    echo "<li>Quarterly Assessment: " . number_format($quarterlyAssessmentPercent * 100, 1) . "%</li>";
    echo "</ul>";
    echo "</div>";
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
    $hps['ww_ps'] = 0;
    $hps['ww_ws'] = $writtenWorkPercent;
    $hps['pt_total'] = 0;
    $hps['pt_ps'] = 0;
    $hps['pt_ws'] = $performanceTaskPercent;
    $hps['qa_ps'] = 0;
    $hps['qa_ws'] = $quarterlyAssessmentPercent;
    $hps['initial_grade'] = 0;
    $hps['quarterly_grade'] = 0;
}
$stmt->close();

// Display grade details with simple design
echo "<div class='grade-details-container'>";
echo "<h5 class='mb-3'>Quarter $quarter Grade Details</h5>";
echo "<div class='mb-3'>";
echo "<p><strong>LRN:</strong> $lrn</p>";
echo "<p><strong>Student:</strong> $studentName</p>";
echo "<p><strong>Subject:</strong> $subjectName</p>";
echo "<p><strong>School Year:</strong> $schoolYear</p>";
echo "</div>";

// Subject Components Summary
echo "<div class='mb-4'>";
echo "<h6>Grading Components</h6>";
echo "<div class='row'>";
echo "<div class='col-md-4 mb-2'>";
echo "<div class='border p-2'>";
echo "<p class='mb-1'><strong>Written Works</strong></p>";
echo "<p class='mb-0'>" . number_format($writtenWorkPercent * 100, 1) . "%</p>";
echo "</div>";
echo "</div>";
echo "<div class='col-md-4 mb-2'>";
echo "<div class='border p-2'>";
echo "<p class='mb-1'><strong>Performance Tasks</strong></p>";
echo "<p class='mb-0'>" . number_format($performanceTaskPercent * 100, 1) . "%</p>";
echo "</div>";
echo "</div>";
echo "<div class='col-md-4 mb-2'>";
echo "<div class='border p-2'>";
echo "<p class='mb-1'><strong>Quarterly Assessment</strong></p>";
echo "<p class='mb-0'>" . number_format($quarterlyAssessmentPercent * 100, 1) . "%</p>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

// Written Works Section
echo "<div class='mb-4'>";
echo "<h6>Written Works</h6>";
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-sm'>";

// Header row with numbers 1-10 and Total
echo "<tr><th style='width: 200px;'></th>";
for ($i = 1; $i <= 10; $i++) {
    echo "<th class='text-center'>$i</th>";
}
echo "<th class='text-center'>Total</th><th class='text-center'>PS</th><th class='text-center'>WS</th></tr>";

// Highest Possible Score row
echo "<tr><th>Highest Possible Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $hpsValue = isset($hps["ww$i"]) && $hps["ww$i"] > 0 ? $hps["ww$i"] : '-';
    echo "<td class='text-center'>$hpsValue</td>";
}
$hpsWWTotal = isset($hps['ww_total']) && $hps['ww_total'] > 0 ? $hps['ww_total'] : '-';
$hpsWWps = isset($hps['ww_ps']) && $hps['ww_ps'] > 0 ? $hps['ww_ps'] : '-';
$hpsWWws = isset($hps['ww_ws']) ? number_format($hps['ww_ws'] * 100, 2) : '0.00';
echo "<td class='text-center'>$hpsWWTotal</td>";
echo "<td class='text-center'>$hpsWWps</td>";
echo "<td class='text-center'>" . $hpsWWws . "%</td></tr>";

// Student scores row
echo "<tr><th>Student Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $score = isset($gradeDetails["ww$i"]) && $gradeDetails["ww$i"] > 0 ? $gradeDetails["ww$i"] : '-';
    echo "<td class='text-center'>$score</td>";
}
$wwTotal = isset($gradeDetails['ww_total']) && $gradeDetails['ww_total'] > 0 ? $gradeDetails['ww_total'] : '-';
$wwPs = isset($gradeDetails['ww_ps']) && $gradeDetails['ww_ps'] > 0 ? $gradeDetails['ww_ps'] : '-';
$wwWs = isset($gradeDetails['ww_ws']) ? number_format($gradeDetails['ww_ws'], 2) : '00.00';
echo "<td class='text-center'><strong>$wwTotal</strong></td>";
echo "<td class='text-center'>$wwPs</td>";
echo "<td class='text-center'><strong>" . $wwWs . "%</strong></td></tr>";

echo "</table>";
echo "</div>";
echo "</div>";

// Performance Tasks Section
echo "<div class='mb-4'>";
echo "<h6>Performance Tasks</h6>";
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered table-sm'>";

// Header row with numbers 1-10 and Total
echo "<tr><th style='width: 200px;'></th>";
for ($i = 1; $i <= 10; $i++) {
    echo "<th class='text-center'>$i</th>";
}
echo "<th class='text-center'>Total</th><th class='text-center'>PS</th><th class='text-center'>WS</th></tr>";

// Highest Possible Score row
echo "<tr><th>Highest Possible Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $hpsValue = isset($hps["pt$i"]) && $hps["pt$i"] > 0 ? $hps["pt$i"] : '-';
    echo "<td class='text-center'>$hpsValue</td>";
}
$hpsPTTotal = isset($hps['pt_total']) && $hps['pt_total'] > 0 ? $hps['pt_total'] : '-';
$hpsPTps = isset($hps['pt_ps']) && $hps['pt_ps'] > 0 ? $hps['pt_ps'] : '-';
$hpsPTws = isset($hps['pt_ws']) ? number_format($hps['pt_ws'] * 100, 2) : '0.00';
echo "<td class='text-center'>$hpsPTTotal</td>";
echo "<td class='text-center'>$hpsPTps</td>";
echo "<td class='text-center'>" . $hpsPTws . "%</td></tr>";

// Student scores row
echo "<tr><th>Student Score</th>";
for ($i = 1; $i <= 10; $i++) {
    $score = isset($gradeDetails["pt$i"]) && $gradeDetails["pt$i"] > 0 ? $gradeDetails["pt$i"] : '-';
    echo "<td class='text-center'>$score</td>";
}
$ptTotal = isset($gradeDetails['pt_total']) && $gradeDetails['pt_total'] > 0 ? $gradeDetails['pt_total'] : '-';
$ptPs = isset($gradeDetails['pt_ps']) && $gradeDetails['pt_ps'] > 0 ? $gradeDetails['pt_ps'] : '-';
$ptWs = isset($gradeDetails['pt_ws']) ? number_format($gradeDetails['pt_ws'], 2) : '0.00';
echo "<td class='text-center'><strong>$ptTotal</strong></td>";
echo "<td class='text-center'>$ptPs</td>";
echo "<td class='text-center'><strong>" . $ptWs . "%</strong></td></tr>";

echo "</table>";
echo "</div>";
echo "</div>";

// Quarterly Assessment and Final Grades
$hpsQA1 = isset($hps['qa1']) && $hps['qa1'] > 0 ? $hps['qa1'] : '-';
$hpsQaps = isset($hps['qa_ps']) && $hps['qa_ps'] > 0 ? $hps['qa_ps'] : '-';
$hpsQAws = isset($hps['qa_ws']) ? number_format($hps['qa_ws'] * 100, 2) : '0.00';

$qa1 = isset($gradeDetails['qa1']) && $gradeDetails['qa1'] > 0 ? $gradeDetails['qa1'] : '-';
$qa_ps = isset($gradeDetails['qa_ps']) && $gradeDetails['qa_ps'] > 0 ? $gradeDetails['qa_ps'] : '-';
$qa_ws = isset($gradeDetails['qa_ws']) ? number_format($gradeDetails['qa_ws'], 2) : '0.00';
$initialGrade = isset($gradeDetails['initial_grade']) && $gradeDetails['initial_grade'] > 0 ? $gradeDetails['initial_grade'] : '-';
$quarterlyGrade = isset($gradeDetails['quarterly_grade']) && $gradeDetails['quarterly_grade'] > 0 ? $gradeDetails['quarterly_grade'] : '-';

echo "<div class='row mb-4'>";
echo "<div class='col-md-6'>";
echo "<h6>Quarterly Assessment</h6>";
echo "<table class='table table-bordered table-sm'>";
echo "<tr><th style='width: 50%;'></th><th class='text-center'>Score</th><th class='text-center'></th><th class='text-center'></th></tr>";
echo "<tr><th>Highest Possible Score</th><td class='text-center'>$hpsQA1</td><td class='text-center'>$hpsQaps</td><td class='text-center'>" . $hpsQAws . "%</td></tr>";
echo "<tr><th>Student Score</th><td class='text-center'><strong>$qa1</strong></td><td class='text-center'>$qa_ps</td><td class='text-center'><strong>" . $qa_ws . "%</strong></td></tr>";
echo "</table>";
echo "</div>";

echo "<div class='col-md-6'>";
echo "<h6>Final Grades</h6>";
echo "<table class='table table-bordered table-sm'>";
echo "<tr><th class='text-center'>Initial Grade</th><th class='text-center'>Quarterly Grade</th></tr>";
echo "<tr>";
echo "<td class='text-center'><strong>$initialGrade</strong></td>";
echo "<td class='text-center'><strong>$quarterlyGrade</strong></td>";
echo "</tr>";
echo "</table>";
echo "</div>";
echo "</div>";

echo "</div>"; // Close grade-details-container
?>