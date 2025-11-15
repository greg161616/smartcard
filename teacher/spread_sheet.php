<?php
session_start();
include '../config.php'; 

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not registered as a teacher.";
    exit;
}
$teacherId = $res->fetch_assoc()['TeacherID'];
$stmt->close();

// Fetch students from database - separate male and female
$maleStudents = [];
$femaleStudents = [];
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

$selectSql = "
SELECT DISTINCT s.StudentID, s.FirstName, s.Middlename, s.LastName, s.Sex
FROM student s
JOIN section_enrollment se ON s.StudentID = se.StudentID
JOIN section sec ON se.SectionID = sec.SectionID
JOIN subject sub ON sec.SectionID = sub.secID
WHERE se.SchoolYear = '2025-2026' 
  AND se.status = 'active' 
  AND sub.TeacherID = ?
  AND sec.SectionID = ? 
  AND sub.SubjectID = ? 
ORDER BY s.Sex, s.LastName, s.FirstName
";
$stmt = $conn->prepare($selectSql);
$stmt->bind_param('iii', $teacherId, $sectionId, $subjectId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $student = [
        'id' => $row['StudentID'],
        'name' => $row['LastName'] . ', ' . $row['FirstName'] . (($row['Middlename'] != '') ? ' ' . substr($row['Middlename'], 0, 1) . '.' : ''),
        'gender' => $row['Sex']
    ];
    
    if ($row['Sex'] == 'Male') {
        $maleStudents[] = $student;
    } else {
        $femaleStudents[] = $student;
    }
}
$stmt->close();

$stmt = $conn->prepare("SELECT SubjectName, written_work_percentage, performance_task_percentage, quarterly_assessment_percentage FROM subject WHERE SubjectID = ?");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$stmt->bind_result($subjectName, $ww, $pt, $qa);
$stmt->fetch();
$wwPercentage = $ww*100;
$ptPercentage = $pt*100;
$qaPercentage = $qa*100;
$stmt->close();

// Fetch highest possible scores for all quarters
$highestScores = [];
$stmt = $conn->prepare("
    SELECT quarter, ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10, 
           pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, qa1
    FROM highest_possible_score 
    WHERE teacherID = ? AND subjectID = ? AND school_year = '2025-2026'
");
$stmt->bind_param('ii', $teacherId, $subjectId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $highestScores[$row['quarter']] = $row;
}
$stmt->close();

// Fetch grades for all students
$gradesDetails = [];
$stmt = $conn->prepare("
    SELECT studentID, quarter, ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10,
           pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, qa1, quarterly_grade
    FROM grades_details 
    WHERE teacherID = ? AND subjectID = ? AND school_year = '2025-2026'
");
$stmt->bind_param('ii', $teacherId, $subjectId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $gradesDetails[$row['studentID']][$row['quarter']] = $row;
}
$stmt->close();

// Fetch summary grades
$summaryGrades = [];
$stmt = $conn->prepare("
    SELECT student_id, Q1, Q2, Q3, Q4, Final
    FROM grades 
    WHERE subject = ? AND school_year = '2025-2026'
");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $summaryGrades[$row['student_id']] = $row;
}
$stmt->close();

function getRemarks($grade) {
    if ($grade >= 75) return "PASSED";
    return "Did Not Meet Expectations";
}

// Function to generate quarter table HTML
function generateQuarterTable($quarter, $maleStudents, $femaleStudents, $wwPercentage, $ptPercentage, $qaPercentage, $highestScores, $gradesDetails) {
    global $teacherId, $subjectId;
    ?>
    <div id="Q<?= $quarter ?>" class="quarter-content <?= $quarter != 1 ? 'd-none' : '' ?>">
        <div class="card-body">
            <div class="table-container">
                <table class="table table-bordered table-sm mb-0 sticky-table">
                    <thead>
                        <tr class="sticky-header-row-1">
                            <th class="sticky-col-1 text-center">No.</th>
                            <th class="sticky-col-2 learner-name-col">Learner's Name</th>
                            <th colspan="10" class="text-center">Written Works (<?= htmlspecialchars($wwPercentage) ?>%)</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">PS</th>
                            <th class="text-center">WS</th>
                            <th colspan="10" class="text-center">Performance Tasks (<?= htmlspecialchars($ptPercentage) ?>%)</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">PS</th>
                            <th class="text-center">WS</th>
                            <th class="text-center">QA (<?= htmlspecialchars($qaPercentage) ?>%)</th>
                            <th class="text-center">PS</th>
                            <th class="text-center">WS</th>
                            <th class="text-center">Initial Grade</th>
                            <th class="text-center">Quarterly Grade</th>
                        </tr>
                        <tr class="sticky-header-row-2">
                            <th class="sticky-col-1"></th>
                            <th class="sticky-col-2">Highest possible score</th>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $wwValue = isset($highestScores[$quarter]["ww$i"]) ? $highestScores[$quarter]["ww$i"] : 0;
                            ?>
                            <th class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <span><?= $i ?></span>
                                    <input type="number" class="form-control form-control-sm max-score-input ww-max" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" value="<?= $wwValue ?>" min="0">
                                </div>
                            </th>
                            <?php endfor; ?>
                            <th class="text-center"><span class="ww-header-total" data-quarter="<?= $quarter ?>">0</span></th>
                            <th class="text-center"><span class="ww-header-ps" data-quarter="<?= $quarter ?>">100.00</span></th>
                            <th class="text-center"><span class="ww-header-ws" data-quarter="<?= $quarter ?>">0</span>%</th>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $ptValue = isset($highestScores[$quarter]["pt$i"]) ? $highestScores[$quarter]["pt$i"] : 0;
                            ?>
                            <th class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <span><?= $i ?></span>
                                    <input type="number" class="form-control form-control-sm max-score-input pt-max" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" value="<?= $ptValue ?>" min="0">
                                </div>
                            </th>
                            <?php endfor; ?>
                            <th class="text-center"><span class="pt-header-total" data-quarter="<?= $quarter ?>">0</span></th>
                            <th class="text-center"><span class="pt-header-ps" data-quarter="<?= $quarter ?>">100.00</span></th>
                            <th class="text-center"><span class="pt-header-ws" data-quarter="<?= $quarter ?>">0</span>%</th>
                            <th class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <span>QA</span>
                                    <?php $qaValue = isset($highestScores[$quarter]["qa1"]) ? $highestScores[$quarter]["qa1"] : 0; ?>
                                    <input type="number" class="form-control form-control-sm max-score-input qa-max" data-quarter="<?= $quarter ?>" value="<?= $qaValue ?>" min="0">
                                </div>
                            </th>
                            <th class="text-center"><span class="qa-header-ps" data-quarter="<?= $quarter ?>">100.00</span></th>
                            <th class="text-center"><span class="qa-header-ws" data-quarter="<?= $quarter ?>">0</span>%</th>
                            <th class="text-center"></th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="quarter-data-body" data-quarter="<?= $quarter ?>">
                        <!-- Male Students Header Row -->
                        <?php if (!empty($maleStudents)): ?>
                        <tr class="gender-divider bg-light">
                            <td colspan="2" class="fw-bold py-2 sticky-col-2">
                                <i class="fas fa-square me-2"></i>Male Students (<?= count($maleStudents) ?>)
                            </td>
                        </tr>
                        <?php foreach ($maleStudents as $index => $student): 
                            $studentData = isset($gradesDetails[$student['id']][$quarter]) ? $gradesDetails[$student['id']][$quarter] : null;
                        ?>
                        <tr data-student-id="<?= $student['id'] ?>" data-gender="male">
                            <td class="sticky-col-1 text-center"><?= $index + 1 ?></td>
                            <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $wwScore = $studentData ? $studentData["ww$i"] : 0;
                                $maxWw = isset($highestScores[$quarter]["ww$i"]) ? $highestScores[$quarter]["ww$i"] : 0;
                            ?>
                            <td class="text-center">
                                <input type="number" class="form-control form-control-sm ww-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $wwScore ?>" max="<?= $maxWw ?>">
                            </td>
                            <?php endfor; ?>
                            <td class="text-center ww-total"><?= $studentData ? array_sum(array_slice($studentData, 2, 10)) : 0 ?></td>
                            <td class="text-center ww-ps"><?= $studentData ? number_format($studentData['ww_ps'], 2) : '0.00' ?></td>
                            <td class="text-center ww-ws"><?= $studentData ? number_format($studentData['ww_ws'], 2) : '0.00' ?></td>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $ptScore = $studentData ? $studentData["pt$i"] : 0;
                                $maxPt = isset($highestScores[$quarter]["pt$i"]) ? $highestScores[$quarter]["pt$i"] : 0;
                            ?>
                            <td class="text-center">
                                <input type="number" class="form-control form-control-sm pt-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $ptScore ?>" max="<?= $maxPt ?>">
                            </td>
                            <?php endfor; ?>
                            <td class="text-center pt-total"><?= $studentData ? array_sum(array_slice($studentData, 12, 10)) : 0 ?></td>
                            <td class="text-center pt-ps"><?= $studentData ? number_format($studentData['pt_ps'], 2) : '0.00' ?></td>
                            <td class="text-center pt-ws"><?= $studentData ? number_format($studentData['pt_ws'], 2) : '0.00' ?></td>
                            <td class="text-center">
                                <?php $qaScore = $studentData ? $studentData["qa1"] : 0; ?>
                                <input type="number" class="form-control form-control-sm qa-input" data-quarter="<?= $quarter ?>" min="0" value="<?= $qaScore ?>" max="<?= $qaValue ?>">
                            </td>
                            <td class="text-center qa-ps"><?= $studentData ? number_format($studentData['qa_ps'], 2) : '0.00' ?></td>
                            <td class="text-center qa-ws"><?= $studentData ? number_format($studentData['qa_ws'], 2) : '0.00' ?></td>
                            <td class="text-center initial-grade"><?= $studentData ? number_format($studentData['initial_grade'], 2) : '0.00' ?></td>
                            <td class="text-center quarterly-grade"><?= $studentData ? $studentData['quarterly_grade'] : '0' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Female Students Header Row -->
                        <?php if (!empty($femaleStudents)): ?>
                        <tr class="gender-divider bg-light">
                            <td colspan="2" class="fw-bold py-2 sticky-col-2">
                                <i class="fas fa-square me-2"></i>Female Students (<?= count($femaleStudents) ?>)
                            </td>
                        </tr>
                        <?php foreach ($femaleStudents as $index => $student): 
                            $studentData = isset($gradesDetails[$student['id']][$quarter]) ? $gradesDetails[$student['id']][$quarter] : null;
                        ?>
                        <tr data-student-id="<?= $student['id'] ?>" data-gender="female">
                            <td class="sticky-col-1 text-center"><?= $index + 1 ?></td>
                            <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $wwScore = $studentData ? $studentData["ww$i"] : 0;
                                $maxWw = isset($highestScores[$quarter]["ww$i"]) ? $highestScores[$quarter]["ww$i"] : 0;
                            ?>
                            <td class="text-center">
                                <input type="number" class="form-control form-control-sm ww-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $wwScore ?>" max="<?= $maxWw ?>">
                            </td>
                            <?php endfor; ?>
                            <td class="text-center ww-total"><?= $studentData ? array_sum(array_slice($studentData, 2, 10)) : 0 ?></td>
                            <td class="text-center ww-ps"><?= $studentData ? number_format($studentData['ww_ps'], 2) : '0.00' ?></td>
                            <td class="text-center ww-ws"><?= $studentData ? number_format($studentData['ww_ws'], 2) : '0.00' ?></td>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $ptScore = $studentData ? $studentData["pt$i"] : 0;
                                $maxPt = isset($highestScores[$quarter]["pt$i"]) ? $highestScores[$quarter]["pt$i"] : 0;
                            ?>
                            <td class="text-center">
                                <input type="number" class="form-control form-control-sm pt-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $ptScore ?>" max="<?= $maxPt ?>">
                            </td>
                            <?php endfor; ?>
                            <td class="text-center pt-total"><?= $studentData ? array_sum(array_slice($studentData, 12, 10)) : 0 ?></td>
                            <td class="text-center pt-ps"><?= $studentData ? number_format($studentData['pt_ps'], 2) : '0.00' ?></td>
                            <td class="text-center pt-ws"><?= $studentData ? number_format($studentData['pt_ws'], 2) : '0.00' ?></td>
                            <td class="text-center">
                                <?php $qaScore = $studentData ? $studentData["qa1"] : 0; ?>
                                <input type="number" class="form-control form-control-sm qa-input" data-quarter="<?= $quarter ?>" min="0" value="<?= $qaScore ?>" max="<?= $qaValue ?>">
                            </td>
                            <td class="text-center qa-ps"><?= $studentData ? number_format($studentData['qa_ps'], 2) : '0.00' ?></td>
                            <td class="text-center qa-ws"><?= $studentData ? number_format($studentData['qa_ws'], 2) : '0.00' ?></td>
                            <td class="text-center initial-grade"><?= $studentData ? number_format($studentData['initial_grade'], 2) : '0.00' ?></td>
                            <td class="text-center quarterly-grade"><?= $studentData ? $studentData['quarterly_grade'] : '0' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Record</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-container {
            max-height: 400px;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            position: relative;
        }
        
        .sticky-table {
            min-width: 2200px;
            table-layout: fixed;
            margin-bottom: 0;
        }
        
        /* Sticky header rows */
        .sticky-header-row-1 {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #f8f9fa;
        }
        
        .sticky-header-row-2 {
            position: sticky;
            top: 80px; /* Height of first header row */
            z-index: 99;
            background-color: #f8f9fa;
        }
        
        /* Sticky columns */
        .sticky-col-1 {
            position: sticky;
            left: 0;
            z-index: 90;
            background-color: white;
            border-right: 2px solid #f8f9fa;
            width: 60px;
        }
        
        .sticky-col-2 {
            position: sticky;
            left: 60px; /* Width of first column */
            z-index: 89;
            background-color: white;
            border-right: 2px solid #f8f9fa;
            width: 320px;
        }
        
        /* Ensure header sticky columns have proper background */
        .sticky-header-row-1 .sticky-col-1,
        .sticky-header-row-1 .sticky-col-2,
        .sticky-header-row-2 .sticky-col-1,
        .sticky-header-row-2 .sticky-col-2 {
            background-color: #f8f9fa !important;
        }
        
        /* Gender divider */
        .gender-divider {
            background-color: #e9ecef !important;
            position: sticky;
            top: 65px; /* Below both header rows */
            z-index: 95; /* above sticky columns but below headers */
        }
        
        .max-score-input {
            width: 60px;
            font-size: 0.8rem;
        }

        .learner-name-col,
        .learner-name-cell {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .remarks-col,
        .remarks-cell {
            width: 200px;
            max-width: 200px;
            white-space: normal;
            overflow-wrap: anywhere;
            text-align: center;
        }
        
        
        .error-highlight {
            border: 2px solid #dc3545 !important;
            background-color: #f8d7da;
        }
        
        .error-tooltip {
            position: absolute;
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            z-index: 100;
            white-space: nowrap;
        }

        /* Summary table styles */
        #Summary .table-container {
            max-height: 400px;
            overflow: auto;
        }
        
        #Summary .sticky-table {
            min-width: 1000px;
        }
        
        #Summary .sticky-col-1 {
            width: 60px;
        }
        
        #Summary .sticky-col-2 {
            width: 320px;
        }

        /* Ensure proper cell padding */
        .table-sm th,
        .table-sm td {
            padding: 0.3rem;
        }

        /* Fix for table header alignment */
        thead th {
            vertical-align: middle;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../navs/teacherNav.php'; ?>
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white justify-content-between d-flex align-items-center">
                <h4 class="mb-0"> Class Record - <?= htmlspecialchars($subjectName) ?> </h4>
                                        <a href="grading_sheet.php?subject_id=<?= $subjectId ?>&section_id=<?= $sectionId ?>" class="text-white">
                            <i class="fas fa-times me-1" style="font-size: 28px;"></i> 
                 </a>
            </div>
            <!-- Quarter Selector -->
            <div class="card-body border-bottom">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <label for="quarterSelect" class="form-label mb-0">Select Quarter:</label>
                    </div>
                    <div class="col-auto">
                        <select id="quarterSelect" class="form-select">
                            <option value="1">Quarter 1</option>
                            <option value="2">Quarter 2</option>
                            <option value="3">Quarter 3</option>
                            <option value="4">Quarter 4</option>
                            <option value="Summary">Summary</option>
                        </select>
                    </div>
                    <div id="saveStatus" class="col-auto" role="alert">
 </div>
                    <div class="col-auto ms-auto me-3">
                        <a
                          href="grades.php?subject_id=<?= $subjectId ?>&section_id=<?= $sectionId ?>"
                          class="btn btn-primary me-2"
                        >
                          <i class="fas fa-upload me-1"></i> Upload
                        </a>
                        <button id="saveButton" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save Grades
                        </button>
                        
                    </div>
                </div>
            </div>

            <!-- Quarter Content -->
            <div id="quarterContent">
                <?php
                for ($quarter = 1; $quarter <= 4; $quarter++) {
                    generateQuarterTable($quarter, $maleStudents, $femaleStudents, $wwPercentage, $ptPercentage, $qaPercentage, $highestScores, $gradesDetails);
                }
                ?>
            </div>

            <!-- Summary Content -->
            <div id="Summary" class="quarter-content d-none">
                <div class="card-body">
                    <!-- Summary Info -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <strong>Summary of Grades</strong>
                        </div>
                        <div class="col-md-4">
                            <strong>Subject:</strong> <?= htmlspecialchars($subjectName) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Grade Level:</strong> Grade 7
                        </div>
                    </div>

                    <!-- Summary Table -->
                    <div class="table-container">
                        <table class="table table-bordered table-hover mb-0 sticky-table">
                            <thead>
                                <tr class="sticky-header-row-1">
                                    <th class="sticky-col-1 text-center">No.</th>
                                    <th class="sticky-col-2 learner-name-col">Learner's Name</th>
                                    <th class="text-center">Q1 Grade</th>
                                    <th class="text-center">Q2 Grade</th>
                                    <th class="text-center">Q3 Grade</th>
                                    <th class="text-center">Q4 Grade</th>
                                    <th class="text-center">Final Grade</th>
                                    <th class="text-center remarks-col">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="summaryDataBody">
                                <?php 
                                // Male students
                                if (!empty($maleStudents)): ?>
                                    <tr class="gender-divider bg-light">
                                        <td colspan="8" class="fw-bold bg-secondary text-white">Male Students (<?= count($maleStudents) ?>)</td>
                                    </tr>
                                    <?php foreach ($maleStudents as $index => $student): ?>
                                        <tr>
                                            <td class="sticky-col-1 text-center"><?= $index + 1 ?></td>
                                            <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q1']) ? $summaryGrades[$student['id']]['Q1'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q2']) ? $summaryGrades[$student['id']]['Q2'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q3']) ? $summaryGrades[$student['id']]['Q3'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q4']) ? $summaryGrades[$student['id']]['Q4'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Final']) ? $summaryGrades[$student['id']]['Final'] : '-' ?></td>
                                            <td class="text-center remarks-cell"><?= isset($summaryGrades[$student['id']]['Final']) ? getRemarks($summaryGrades[$student['id']]['Final']) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; 
                                endif;

                                // Female students
                                if (!empty($femaleStudents)): ?>
                                    <tr class="gender-divider bg-light">
                                        <td colspan="8" class="fw-bold bg-secondary text-white">Female Students (<?= count($femaleStudents) ?>)</td>
                                    </tr>
                                    <?php foreach ($femaleStudents as $index => $student): ?>
                                        <tr>
                                            <td class="sticky-col-1 text-center"><?= $index + 1 ?></td>
                                            <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q1']) ? $summaryGrades[$student['id']]['Q1'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q2']) ? $summaryGrades[$student['id']]['Q2'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q3']) ? $summaryGrades[$student['id']]['Q3'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Q4']) ? $summaryGrades[$student['id']]['Q4'] : '-' ?></td>
                                            <td class="text-center"><?= isset($summaryGrades[$student['id']]['Final']) ? $summaryGrades[$student['id']]['Final'] : '-' ?></td>
                                            <td class="text-center remarks-cell"><?= isset($summaryGrades[$student['id']]['Final']) ? getRemarks($summaryGrades[$student['id']]['Final']) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; 
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Status Indicator -->


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
// PHP data passed to JavaScript
const highestScores = <?= json_encode($highestScores) ?>;
const gradesDetails = <?= json_encode($gradesDetails) ?>;
const wwPercentage = <?= $ww ?>;
const ptPercentage = <?= $pt ?>;
const qaPercentage = <?= $qa ?>;
const maleStudents = <?= json_encode($maleStudents) ?>;
const femaleStudents = <?= json_encode($femaleStudents) ?>;
const subjectId = <?= $subjectId ?>;
const teacherId = <?= $teacherId ?>;

let currentQuarter = 1;
let hasUnsavedChanges = false;

// Helper functions
function getRemarks(grade) {
    if (grade >= 75) return "PASSED";
    return "Did Not Meet Expectations";
}

function transmuteGrade(initialGrade) {
    let transmuted = ((initialGrade - 60) / 1.6) + 75;
    transmuted = Math.floor(transmuted);
    if (transmuted < 0) transmuted = 0;
    if (transmuted > 100) transmuted = 100;
    return transmuted;
}

function calculateTotals(row, quarter) {
    const quarterToUse = quarter || currentQuarter;
    
    let wwTotal = 0;
    for (let i = 1; i <= 10; i++) {
        const input = row.querySelector(`.ww-input[data-index="${i}"]`);
        if (input && input.value) {
            wwTotal += parseInt(input.value) || 0;
        }
    }
    
    let ptTotal = 0;
    for (let i = 1; i <= 10; i++) {
        const input = row.querySelector(`.pt-input[data-index="${i}"]`);
        if (input && input.value) {
            ptTotal += parseInt(input.value) || 0;
        }
    }
    
    const qaInput = row.querySelector('.qa-input');
    const qaValue = qaInput ? parseInt(qaInput.value) || 0 : 0;
    
    const wwMaxScores = [];
    const ptMaxScores = [];
    let qaMaxScore = 0;
    
    for (let i = 1; i <= 10; i++) {
        const wwMax = document.querySelector(`.ww-max[data-quarter="${quarterToUse}"][data-index="${i}"]`);
        const ptMax = document.querySelector(`.pt-max[data-quarter="${quarterToUse}"][data-index="${i}"]`);
        wwMaxScores.push(parseInt(wwMax?.value) || 0);
        ptMaxScores.push(parseInt(ptMax?.value) || 0);
    }
    const qaMax = document.querySelector(`.qa-max[data-quarter="${quarterToUse}"]`);
    qaMaxScore = parseInt(qaMax?.value) || 0;
    
    const wwTotalMax = wwMaxScores.reduce((a, b) => a + b, 0);
    const ptTotalMax = ptMaxScores.reduce((a, b) => a + b, 0);
    
    const wwPS = wwTotalMax > 0 ? (wwTotal / wwTotalMax * 100).toFixed(2) : 0;
    const ptPS = ptTotalMax > 0 ? (ptTotal / ptTotalMax * 100).toFixed(2) : 0;
    const qaPS = qaMaxScore > 0 ? (qaValue / qaMaxScore * 100).toFixed(2) : 0;
    
    const wwWS = (wwPS * wwPercentage).toFixed(2);
    const ptWS = (ptPS * ptPercentage).toFixed(2);
    const qaWS = (qaPS * qaPercentage).toFixed(2);
    
    const initialGrade = (parseFloat(wwWS) + parseFloat(ptWS) + parseFloat(qaWS)).toFixed(2);
    const quarterlyGrade = transmuteGrade(parseFloat(initialGrade));
    
    row.querySelector('.ww-total').textContent = wwTotal;
    row.querySelector('.ww-ps').textContent = wwPS;
    row.querySelector('.ww-ws').textContent = wwWS;
    
    row.querySelector('.pt-total').textContent = ptTotal;
    row.querySelector('.pt-ps').textContent = ptPS;
    row.querySelector('.pt-ws').textContent = ptWS;
    
    row.querySelector('.qa-ps').textContent = qaPS;
    row.querySelector('.qa-ws').textContent = qaWS;
    
    row.querySelector('.initial-grade').textContent = initialGrade;
    row.querySelector('.quarterly-grade').textContent = quarterlyGrade;
    
    return {
        wwTotal,
        ptTotal,
        qaValue,
        wwPS,
        ptPS,
        qaPS,
        wwWS,
        ptWS,
        qaWS,
        initialGrade,
        quarterlyGrade
    };
}

function updateHeaderTotals(quarter) {
    const quarterToUse = quarter || currentQuarter;
    
    let wwTotalMax = 0;
    for (let i = 1; i <= 10; i++) {
        const maxInput = document.querySelector(`.ww-max[data-quarter="${quarterToUse}"][data-index="${i}"]`);
        wwTotalMax += parseInt(maxInput?.value) || 0;
    }
    
    let ptTotalMax = 0;
    for (let i = 1; i <= 10; i++) {
        const maxInput = document.querySelector(`.pt-max[data-quarter="${quarterToUse}"][data-index="${i}"]`);
        ptTotalMax += parseInt(maxInput?.value) || 0;
    }
    
    const wwHeaderTotal = document.querySelector(`.ww-header-total[data-quarter="${quarterToUse}"]`);
    const ptHeaderTotal = document.querySelector(`.pt-header-total[data-quarter="${quarterToUse}"]`);
    
    if (wwHeaderTotal) wwHeaderTotal.textContent = wwTotalMax;
    if (ptHeaderTotal) ptHeaderTotal.textContent = ptTotalMax;
    
    const wwHeaderWS = document.querySelector(`.ww-header-ws[data-quarter="${quarterToUse}"]`);
    const ptHeaderWS = document.querySelector(`.pt-header-ws[data-quarter="${quarterToUse}"]`);
    const qaHeaderWS = document.querySelector(`.qa-header-ws[data-quarter="${quarterToUse}"]`);
    
    if (wwHeaderWS) wwHeaderWS.textContent = (wwPercentage * 100).toFixed(0);
    if (ptHeaderWS) ptHeaderWS.textContent = (ptPercentage * 100).toFixed(0);
    if (qaHeaderWS) qaHeaderWS.textContent = (qaPercentage * 100).toFixed(0);
}

function validateScore(input, maxScore) {
    const value = parseInt(input.value) || 0;
    
    if (value > maxScore) {
        input.classList.add('error-highlight');
        
        let tooltip = input.parentNode.querySelector('.error-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'error-tooltip';
            input.parentNode.style.position = 'relative';
            input.parentNode.appendChild(tooltip);
        }
        tooltip.textContent = `Score exceeds maximum (${maxScore})`;
        tooltip.style.top = `${input.offsetHeight + 5}px`;
        tooltip.style.left = '0';
        
        return false;
    } else {
        input.classList.remove('error-highlight');
        
        const tooltip = input.parentNode.querySelector('.error-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
        
        return true;
    }
}

function updateInputMaxValues(quarter) {
    const tableBodies = document.querySelectorAll(`.quarter-data-body[data-quarter="${quarter}"]`);
    
    tableBodies.forEach(tableBody => {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
            for (let i = 1; i <= 10; i++) {
                const wwInput = row.querySelector(`.ww-input[data-index="${i}"]`);
                const ptInput = row.querySelector(`.pt-input[data-index="${i}"]`);
                
                const wwMax = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${i}"]`);
                const ptMax = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${i}"]`);
                
                if (wwInput && wwMax) {
                    wwInput.max = parseInt(wwMax.value) || 0;
                }
                if (ptInput && ptMax) {
                    ptInput.max = parseInt(ptMax.value) || 0;
                }
            }
            
            const qaInput = row.querySelector('.qa-input');
            const qaMax = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
            if (qaInput && qaMax) {
                qaInput.max = parseInt(qaMax.value) || 0;
            }
        });
    });
}

function loadQuarterData(quarter) {
    currentQuarter = quarter;
    
    document.querySelectorAll('.quarter-content').forEach(content => {
        content.classList.add('d-none');
    });
    document.getElementById(`Q${quarter}`).classList.remove('d-none');
    
    if (highestScores[quarter]) {
        const scores = highestScores[quarter];
        
        for (let i = 1; i <= 10; i++) {
            const wwMaxInput = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${i}"]`);
            const ptMaxInput = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${i}"]`);
            
            if (wwMaxInput) wwMaxInput.value = scores[`ww${i}`] || 0;
            if (ptMaxInput) ptMaxInput.value = scores[`pt${i}`] || 0;
        }
        
        const qaMaxInput = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
        if (qaMaxInput) qaMaxInput.value = scores['qa1'] || 0;
    }
    
    updateHeaderTotals(quarter);
    updateInputMaxValues(quarter);
    
    const tableBodies = document.querySelectorAll(`.quarter-data-body[data-quarter="${quarter}"]`);
    
    tableBodies.forEach(tableBody => {
        loadStudentDataForTable(tableBody, quarter);
    });
    
    hasUnsavedChanges = false;
}

function loadStudentDataForTable(tableBody, quarter) {
    const rows = tableBody.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (row.classList.contains('gender-divider')) return;
        
        const studentId = row.getAttribute('data-student-id');
        
        if (gradesDetails[studentId] && gradesDetails[studentId][quarter]) {
            const studentData = gradesDetails[studentId][quarter];
            
            for (let i = 1; i <= 10; i++) {
                const wwInput = row.querySelector(`.ww-input[data-index="${i}"]`);
                const ptInput = row.querySelector(`.pt-input[data-index="${i}"]`);
                
                if (wwInput) wwInput.value = studentData[`ww${i}`] || '';
                if (ptInput) ptInput.value = studentData[`pt${i}`] || '';
            }
            
            const qaInput = row.querySelector('.qa-input');
            if (qaInput) qaInput.value = studentData['qa1'] || '';
        }
        
        for (let i = 1; i <= 10; i++) {
            const wwInput = row.querySelector(`.ww-input[data-index="${i}"]`);
            const ptInput = row.querySelector(`.pt-input[data-index="${i}"]`);
            
            const wwMax = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${i}"]`);
            const ptMax = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${i}"]`);
            
            if (wwInput && wwMax) {
                wwInput.max = parseInt(wwMax.value) || 0;
                validateScore(wwInput, parseInt(wwMax.value) || 0);
            }
            if (ptInput && ptMax) {
                ptInput.max = parseInt(ptMax.value) || 0;
                validateScore(ptInput, parseInt(ptMax.value) || 0);
            }
        }
        
        const qaInputRow = row.querySelector('.qa-input');
        const qaMax = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
        if (qaInputRow && qaMax) {
            qaInputRow.max = parseInt(qaMax.value) || 0;
            validateScore(qaInputRow, parseInt(qaMax.value) || 0);
        }
        
        calculateTotals(row, quarter);
    });
}

function saveGrades() {
    const data = {
        teacherID: teacherId,
        subjectID: subjectId,
        school_year: '2025-2026',
        quarter: currentQuarter,
        highest_scores: {},
        grades: []
    };
    
    const highestScoresData = {};
    let wwTotalMax = 0;
    let ptTotalMax = 0;
    
    for (let i = 1; i <= 10; i++) {
        const wwMax = parseInt(document.querySelector(`.ww-max[data-quarter="${currentQuarter}"][data-index="${i}"]`)?.value) || 0;
        const ptMax = parseInt(document.querySelector(`.pt-max[data-quarter="${currentQuarter}"][data-index="${i}"]`)?.value) || 0;
        
        highestScoresData[`ww${i}`] = wwMax;
        highestScoresData[`pt${i}`] = ptMax;
        
        wwTotalMax += wwMax;
        ptTotalMax += ptMax;
    }
    
    const qaMax = parseInt(document.querySelector(`.qa-max[data-quarter="${currentQuarter}"]`)?.value) || 0;
    highestScoresData['qa1'] = qaMax;
    
    highestScoresData['ww_total'] = wwTotalMax;
    highestScoresData['ww_ps'] = 100.00;
    highestScoresData['ww_ws'] = (wwPercentage * 100).toFixed(2);
    
    highestScoresData['pt_total'] = ptTotalMax;
    highestScoresData['pt_ps'] = 100.00;
    highestScoresData['pt_ws'] = (ptPercentage * 100).toFixed(2);
    
    highestScoresData['qa_ps'] = 100.00;
    highestScoresData['qa_ws'] = (qaPercentage * 100).toFixed(2);
    
    data.highest_scores = highestScoresData;
    
    let hasErrors = false;
    
    const tableBodies = document.querySelectorAll(`.quarter-data-body[data-quarter="${currentQuarter}"]`);
    
    tableBodies.forEach(tableBody => {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
            if (row.classList.contains('gender-divider')) return;
            
            const studentData = collectStudentData(row);
            if (studentData) {
                data.grades.push(studentData);
            }
        });
    });
    
    const errorInputs = document.querySelectorAll('.error-highlight');
    if (errorInputs.length > 0) {
        alert('Please fix all validation errors before saving.');
        return;
    }
    
    const saveButton = document.getElementById('saveButton');
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
    saveButton.disabled = true;
    
    console.log('Sending data:', data);
    
    fetch('save_grades.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const result = JSON.parse(text);
            if (result.success) {
                showSaveStatus('success', result.message);
                hasUnsavedChanges = false;
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showSaveStatus('error', result.message);
                console.error('Save error:', result.message);
            }
        } catch (e) {
            console.error('JSON parse error:', e, 'Response text:', text);
            showSaveStatus('error', 'Invalid response from server');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showSaveStatus('error', 'Error saving data: ' + error.message);
    })
    .finally(() => {
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
    });
}

function collectStudentData(row) {
    const studentId = row.getAttribute('data-student-id');
    
    const totals = calculateTotals(row);
    
    const gradeData = {
        studentID: parseInt(studentId),
        ww1: 0, ww2: 0, ww3: 0, ww4: 0, ww5: 0, ww6: 0, ww7: 0, ww8: 0, ww9: 0, ww10: 0,
        pt1: 0, pt2: 0, pt3: 0, pt4: 0, pt5: 0, pt6: 0, pt7: 0, pt8: 0, pt9: 0, pt10: 0,
        qa1: 0,
        ww_total: 0,
        ww_ps: 0,
        ww_ws: 0,
        pt_total: 0,
        pt_ps: 0,
        pt_ws: 0,
        qa_ps: 0,
        qa_ws: 0,
        initial_grade: 0,
        quarterly_grade: 0
    };
    
    for (let i = 1; i <= 10; i++) {
        const input = row.querySelector(`.ww-input[data-index="${i}"]`);
        const maxInput = document.querySelector(`.ww-max[data-quarter="${currentQuarter}"][data-index="${i}"]`);
        const maxScore = parseInt(maxInput?.value) || 0;
        
        if (input && input.value !== '') {
            const value = parseInt(input.value) || 0;
            if (!validateScore(input, maxScore)) {
                return null;
            }
            gradeData[`ww${i}`] = value;
        }
    }
    
    for (let i = 1; i <= 10; i++) {
        const input = row.querySelector(`.pt-input[data-index="${i}"]`);
        const maxInput = document.querySelector(`.pt-max[data-quarter="${currentQuarter}"][data-index="${i}"]`);
        const maxScore = parseInt(maxInput?.value) || 0;
        
        if (input && input.value !== '') {
            const value = parseInt(input.value) || 0;
            if (!validateScore(input, maxScore)) {
                return null;
            }
            gradeData[`pt${i}`] = value;
        }
    }
    
    const qaInput = row.querySelector('.qa-input');
    const qaMaxInput = document.querySelector(`.qa-max[data-quarter="${currentQuarter}"]`);
    const qaMaxScore = parseInt(qaMaxInput?.value) || 0;
    
    if (qaInput && qaInput.value !== '') {
        const value = parseInt(qaInput.value) || 0;
        if (!validateScore(qaInput, qaMaxScore)) {
            return null;
        }
        gradeData.qa1 = value;
    }
    
    gradeData.ww_total = parseFloat(totals.wwTotal) || 0;
    gradeData.ww_ps = parseFloat(totals.wwPS) || 0;
    gradeData.ww_ws = parseFloat(totals.wwWS) || 0;
    
    gradeData.pt_total = parseFloat(totals.ptTotal) || 0;
    gradeData.pt_ps = parseFloat(totals.ptPS) || 0;
    gradeData.pt_ws = parseFloat(totals.ptWS) || 0;
    
    gradeData.qa_ps = parseFloat(totals.qaPS) || 0;
    gradeData.qa_ws = parseFloat(totals.qaWS) || 0;
    
    gradeData.initial_grade = parseFloat(totals.initialGrade) || 0;
    gradeData.quarterly_grade = parseInt(totals.quarterlyGrade) || 0;
    
    return gradeData;
}

function showSaveStatus(type, message) {
    const saveStatus = document.getElementById('saveStatus');
    
    if (type === 'success') {
        saveStatus.className = 'col-auto justify-content-end alert alert-success';
        saveStatus.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + message;
    } else {
        saveStatus.className = 'col-auto justify-content-end alert alert-danger';
        saveStatus.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + message;
    }
    
    saveStatus.classList.remove('d-none');
    
    setTimeout(() => {
        saveStatus.classList.add('d-none');
    }, 5000);
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    loadQuarterData(1);
    
    document.getElementById('quarterSelect').addEventListener('change', function() {
        const selectedQuarter = this.value;
        
        if (selectedQuarter === 'Summary') {
            document.getElementById('quarterContent').classList.add('d-none');
            document.getElementById('Summary').classList.remove('d-none');
        } else {
            document.getElementById('quarterContent').classList.remove('d-none');
            document.getElementById('Summary').classList.add('d-none');
            loadQuarterData(parseInt(selectedQuarter));
        }
    });
    
    document.getElementById('saveButton').addEventListener('click', saveGrades);
    
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('ww-input') || 
            e.target.classList.contains('pt-input') || 
            e.target.classList.contains('qa-input')) {
            
            const row = e.target.closest('tr');
            calculateTotals(row);
            hasUnsavedChanges = true;
            
            if (e.target.classList.contains('ww-input')) {
                const index = e.target.getAttribute('data-index');
                const maxInput = document.querySelector(`.ww-max[data-quarter="${currentQuarter}"][data-index="${index}"]`);
                validateScore(e.target, parseInt(maxInput?.value) || 0);
            } else if (e.target.classList.contains('pt-input')) {
                const index = e.target.getAttribute('data-index');
                const maxInput = document.querySelector(`.pt-max[data-quarter="${currentQuarter}"][data-index="${index}"]`);
                validateScore(e.target, parseInt(maxInput?.value) || 0);
            } else if (e.target.classList.contains('qa-input')) {
                const maxInput = document.querySelector(`.qa-max[data-quarter="${currentQuarter}"]`);
                validateScore(e.target, parseInt(maxInput?.value) || 0);
            }
        }
        
        if (e.target.classList.contains('ww-max') || 
            e.target.classList.contains('pt-max') || 
            e.target.classList.contains('qa-max')) {
            
            const quarter = e.target.getAttribute('data-quarter');
            updateHeaderTotals(quarter);
            updateInputMaxValues(quarter);
            
            const tableBodies = document.querySelectorAll(`.quarter-data-body[data-quarter="${quarter}"]`);
            
            tableBodies.forEach(tableBody => {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    if (!row.classList.contains('gender-divider')) {
                        calculateTotals(row, quarter);
                    }
                });
            });
            
            hasUnsavedChanges = true;
        }
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});
    </script>
</body>
</html>