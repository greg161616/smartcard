<?php
session_start();
include '../config.php'; 

// Handle class record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_class_record') {
    header('Content-Type: application/json');
    $tid  = (int)$_POST['teacher_id'];
    $sid  = (int)$_POST['subject_id'];
    $secid = (int)$_POST['section_id'];
    $qtr  = (int)$_POST['quarter'];
    $sy   = $_POST['school_year'];
    $how  = $_POST['submit_using'];

    // Check not already submitted
    $chk = $conn->prepare("SELECT id FROM grade_submissions WHERE teacher_id=? AND subject_id=? AND section_id=? AND quarter=? AND school_year=? LIMIT 1");
    $chk->bind_param('iiiis', $tid, $sid, $secid, $qtr, $sy);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        echo json_encode(['success'=>false,'message'=>'This quarter is already submitted.']);
        $chk->close();
        exit;
    }
    $chk->close();

    $ins = $conn->prepare("INSERT INTO grade_submissions (teacher_id,subject_id,section_id,quarter,school_year,submit_using) VALUES (?,?,?,?,?,?)");
    $ins->bind_param('iiiiss', $tid, $sid, $secid, $qtr, $sy, $how);
    if ($ins->execute()) {
        echo json_encode(['success'=>true,'message'=>"Quarter $qtr class record submitted successfully."]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Database error: '.$conn->error]);
    }
    $ins->close();
    exit;
}

// Include logging helper
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

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT TeacherID, fName, lName FROM teacher WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not registered as a teacher.";
    exit;
}
$teacherData = $res->fetch_assoc();
$teacherId = $teacherData['TeacherID'];
$stmt->close();

// Log page access
try {
    $teacher_display = getTeacherDisplayName($conn, $teacherId);
    $subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
    $sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
    
    $accessMessage = "Accessed class record page";
    if ($subjectId) {
        // Get subject name for logging
        $subjectStmt = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ?");
        $subjectStmt->bind_param('i', $subjectId);
        $subjectStmt->execute();
        $subjectStmt->bind_result($subjectName);
        $subjectStmt->fetch();
        $subjectStmt->close();
        $accessMessage .= " for {$subjectName}";
    }
    
    log_system_action($conn, 'Class Record Access', $teacherId, [
        'teacher' => $teacher_display,
        'message' => $accessMessage,
        'subject_id' => $subjectId,
        'section_id' => $sectionId,
        'page' => 'class_record.php'
    ], 'info');
} catch (Exception $logEx) {
    error_log('Logging failed (page access): ' . $logEx->getMessage());
}

$schoolyear_query = "SELECT * FROM school_year WHERE status = 'active' LIMIT 1";
$schoolyear_result = mysqli_query($conn, $schoolyear_query);
$schoolyear = mysqli_fetch_assoc($schoolyear_result);
$current_year = $schoolyear['school_year'];
$school_year = $current_year;

// Fetch students from database - separate male and female
$maleStudents = [];
$femaleStudents = [];
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

// Fixed query using correct table relationships
$selectSql = "
SELECT DISTINCT s.StudentID, s.FirstName, s.Middlename, s.LastName, s.Sex
FROM student s
JOIN section_enrollment se ON s.StudentID = se.StudentID
JOIN assigned_subject ass ON se.SectionID = ass.section_id
WHERE se.SchoolYear = ? 
  AND se.status = 'active' 
  AND ass.teacher_id = ?
  AND ass.section_id = ? 
  AND ass.school_year = ?
ORDER BY s.Sex, s.LastName, s.FirstName
";
$stmt = $conn->prepare($selectSql);
$stmt->bind_param('siis', $school_year, $teacherId, $sectionId, $school_year);
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

// Get subject details
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
    WHERE teacherID = ? AND subjectID = ? AND school_year = ?
");
$stmt->bind_param('iis', $teacherId, $subjectId, $school_year);
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
           pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, qa1, 
           ww_total, ww_ps, ww_ws, pt_total, pt_ps, pt_ws, qa_ps, qa_ws,
           initial_grade, quarterly_grade
    FROM grades_details 
    WHERE teacherID = ? AND subjectID = ? AND school_year = ?
");
$stmt->bind_param('iis', $teacherId, $subjectId, $school_year);
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
    WHERE subject = ? AND school_year = ?
");
$stmt->bind_param('is', $subjectId, $school_year);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $summaryGrades[$row['student_id']] = $row;
}
$stmt->close();

// Fetch submitted quarters
$submittedQuarters = [];
$stmtSub = $conn->prepare("SELECT quarter FROM grade_submissions WHERE teacher_id=? AND subject_id=? AND section_id=? AND school_year=?");
$stmtSub->bind_param('iiis', $teacherId, $subjectId, $sectionId, $school_year);
$stmtSub->execute();
$resSub = $stmtSub->get_result();
while ($rowSub = $resSub->fetch_assoc()) {
    $submittedQuarters[] = (int)$rowSub['quarter'];
}
$stmtSub->close();

function getRemarks($grade) {
    if ($grade >= 75) return "PASSED";
    return "FAILED";
}

// Function to generate quarter table HTML
function generateQuarterTable($quarter, $maleStudents, $femaleStudents, $wwPercentage, $ptPercentage, $qaPercentage, $highestScores, $gradesDetails) {
    global $teacherId, $subjectId, $submittedQuarters;
    $isLocked = in_array($quarter, $submittedQuarters);
    ?>
    <div id="Q<?= $quarter ?>" class="quarter-content <?= $quarter != 1 ? 'd-none' : '' ?>">
        <div class="card-body">
            <div class="table-container">
                <table class="table table-bordered table-sm mb-0 sticky-table">
                    <thead>
                        <tr class="sticky-header-row-1">
                            <th class="sticky-col-1 text-center">No.</th>
                            <th class="sticky-col-2 learner-name-col">Learner's Name</th>
                            <th colspan="10" class="text-center col-ww">Written Works (<?= htmlspecialchars($wwPercentage) ?>%)</th>
                            <th class="text-center col-ww">Total</th>
                            <th class="text-center col-ww">PS</th>
                            <th class="text-center col-ww">WS</th>
                            <th colspan="10" class="text-center col-pt">Performance Tasks (<?= htmlspecialchars($ptPercentage) ?>%)</th>
                            <th class="text-center col-pt">Total</th>
                            <th class="text-center col-pt">PS</th>
                            <th class="text-center col-pt">WS</th>
                            <th class="text-center col-qa">QA (<?= htmlspecialchars($qaPercentage) ?>%)</th>
                            <th class="text-center col-qa">PS</th>
                            <th class="text-center col-qa">WS</th>
                            <th class="text-center col-qa" rowspan="2" style="font-size: smaller; vertical-align: middle;">Initial Grade</th>
                            <th class="text-center col-qa" rowspan="2" style="font-size: x-small; vertical-align: middle;">Quarterly Grade</th>
                        </tr>
                        <tr class="sticky-header-row-2 ">
                            <th class="sticky-col-1"></th>
                            <th class="sticky-col-2">Highest possible score</th>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $wwValue = isset($highestScores[$quarter]["ww$i"]) ? $highestScores[$quarter]["ww$i"] : 0;
                            ?>
                            <th class="text-center fs-6 col-ww">
                                <div class="d-flex flex-column align-items-center">
                                    <span><?= $i ?></span>
                                    <input type="number" class="form-control form-control-sm max-score-input ww-max" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" value="<?= $wwValue ?>" min="0" <?= $isLocked?'disabled':'' ?>>
                                </div>
                            </th>
                            <?php endfor; ?>
                            <th class="text-center col-ww" style="font-size: smaller;"><span class="ww-header-total" data-quarter="<?= $quarter ?>">0</span></th>
                            <th class="text-center col-ww" style="font-size: smaller;"><span class="ww-header-ps" data-quarter="<?= $quarter ?>">100.00</span></th>
                            <th class="text-center col-ww" style="font-size: smaller;"><span class="ww-header-ws" data-quarter="<?= $quarter ?>">0</span>%</th>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $ptValue = isset($highestScores[$quarter]["pt$i"]) ? $highestScores[$quarter]["pt$i"] : 0;
                            ?>
                            <th class="text-center col-pt">
                                <div class="d-flex flex-column align-items-center">
                                    <span><?= $i ?></span>
                                    <input type="number" class="form-control form-control-sm max-score-input pt-max" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" value="<?= $ptValue ?>" min="0" <?= $isLocked?'disabled':'' ?>>
                                </div>
                            </th>
                            <?php endfor; ?>
                            <th class="text-center col-pt" style="font-size: smaller;"><span class="pt-header-total" data-quarter="<?= $quarter ?>">0</span></th>
                            <th class="text-center col-pt" style="font-size: smaller;"><span class="pt-header-ps" data-quarter="<?= $quarter ?>">100.00</span></th>
                            <th class="text-center col-pt" style="font-size: smaller;"><span class="pt-header-ws" data-quarter="<?= $quarter ?>">0</span>%</th>
                            <th class="text-center col-qa">
                                <div class="d-flex flex-column align-items-center">
                                    <span>QA</span>
                                    <?php $qaValue = isset($highestScores[$quarter]["qa1"]) ? $highestScores[$quarter]["qa1"] : 0; ?>
                                    <input type="number" class="form-control form-control-sm max-score-input qa-max" data-quarter="<?= $quarter ?>" value="<?= $qaValue ?>" min="0" <?= $isLocked?'disabled':'' ?>>
                                </div>
                            </th>
                            <th class="text-center col-qa" style="font-size: smaller;"><span class="qa-header-ps" data-quarter="<?= $quarter ?>">100.00</span></th>
                            <th class="text-center col-qa" style="font-size: smaller;"><span class="qa-header-ws" data-quarter="<?= $quarter ?>">0</span>%</th>
                            <!-- Initial/Quarterly grade headers span both rows -->
                        </tr>
                    </thead>
                    <tbody class="quarter-data-body" data-quarter="<?= $quarter ?>">
                        <!-- Male Students Header Row -->
                        <?php if (!empty($maleStudents)): ?>
                        <tr class="gender-divider bg-light">
                            <td colspan="2" class="fw-bold py-2 sticky-col-1">
                            Male Students (<?= count($maleStudents) ?>)
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
                            <td class="text-center col-ww">
                                <input type="number" class="form-control form-control-sm ww-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $wwScore ?>" max="<?= $maxWw ?>" <?= $isLocked?'disabled':'' ?>>
                            </td>
                            <?php endfor; ?>
                            <td class="text-center ww-total col-ww"><?= $studentData ? array_sum(array_slice($studentData, 2, 10)) : 0 ?></td>
                            <td class="text-center ww-ps col-ww"><?= $studentData ? number_format((float)($studentData['ww_ps'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center ww-ws col-ww"><?= $studentData ? number_format((float)($studentData['ww_ws'] ?? 0), 2) : '0.00' ?></td>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $ptScore = $studentData ? $studentData["pt$i"] : 0;
                                $maxPt = isset($highestScores[$quarter]["pt$i"]) ? $highestScores[$quarter]["pt$i"] : 0;
                            ?>
                            <td class="text-center col-pt">
                                <input type="number" class="form-control form-control-sm pt-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $ptScore ?>" max="<?= $maxPt ?>" <?= $isLocked?'disabled':'' ?>>
                            </td>
                            <?php endfor; ?>
                            <td class="text-center pt-total col-pt"><?= $studentData ? array_sum(array_slice($studentData, 12, 10)) : 0 ?></td>
                            <td class="text-center pt-ps col-pt"><?= $studentData ? number_format((float)($studentData['pt_ps'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center pt-ws col-pt"><?= $studentData ? number_format((float)($studentData['pt_ws'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center col-qa">
                                <?php $qaScore = $studentData ? $studentData["qa1"] : 0; ?>
                                <input type="number" class="form-control form-control-sm qa-input" data-quarter="<?= $quarter ?>" min="0" value="<?= $qaScore ?>" max="<?= $qaValue ?>" <?= $isLocked?'disabled':'' ?>>
                            </td>
                            <td class="text-center qa-ps col-qa"><?= $studentData ? number_format((float)($studentData['qa_ps'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center qa-ws col-qa"><?= $studentData ? number_format((float)($studentData['qa_ws'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center initial-grade col-qa"><?= $studentData ? number_format((float)($studentData['initial_grade'] ?? 0), 2) : '0.00' ?></td>
                            <?php $qgVal = $studentData ? (int)($studentData['quarterly_grade'] ?? 0) : 0; ?>
                            <td class="text-center quarterly-grade col-qa <?= ($qgVal <= 74) ? ' grade-low' : '' ?>"><?= $qgVal ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Female Students Header Row -->
                        <?php if (!empty($femaleStudents)): ?>
                        <tr class="gender-divider bg-light">
                            <td colspan="2" class="fw-bold py-2 sticky-col-1">
                                Female Students (<?= count($femaleStudents) ?>)
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
                            <td class="text-center col-ww">
                                <input type="number" class="form-control form-control-sm ww-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $wwScore ?>" max="<?= $maxWw ?>" <?= $isLocked?'disabled':'' ?>>
                            </td>
                            <?php endfor; ?>
                            <td class="text-center ww-total col-ww"><?= $studentData ? array_sum(array_slice($studentData, 2, 10)) : 0 ?></td>
                            <td class="text-center ww-ps col-ww"><?= $studentData ? number_format((float)($studentData['ww_ps'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center ww-ws col-ww"><?= $studentData ? number_format((float)($studentData['ww_ws'] ?? 0), 2) : '0.00' ?></td>
                            <?php for ($i = 1; $i <= 10; $i++): 
                                $ptScore = $studentData ? $studentData["pt$i"] : 0;
                                $maxPt = isset($highestScores[$quarter]["pt$i"]) ? $highestScores[$quarter]["pt$i"] : 0;
                            ?>
                            <td class="text-center col-pt">
                                <input type="number" class="form-control form-control-sm pt-input" data-quarter="<?= $quarter ?>" data-index="<?= $i ?>" min="0" value="<?= $ptScore ?>" max="<?= $maxPt ?>" <?= $isLocked?'disabled':'' ?>>
                            </td>
                            <?php endfor; ?>
                            <td class="text-center pt-total col-pt"><?= $studentData ? array_sum(array_slice($studentData, 12, 10)) : 0 ?></td>
                            <td class="text-center pt-ps col-pt"><?= $studentData ? number_format((float)($studentData['pt_ps'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center pt-ws col-pt"><?= $studentData ? number_format((float)($studentData['pt_ws'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center col-qa">
                                <?php $qaScore = $studentData ? $studentData["qa1"] : 0; ?>
                                <input type="number" class="form-control form-control-sm qa-input" data-quarter="<?= $quarter ?>" min="0" value="<?= $qaScore ?>" max="<?= $qaValue ?>" <?= $isLocked?'disabled':'' ?>>
                            </td>
                            <td class="text-center qa-ps col-qa"><?= $studentData ? number_format((float)($studentData['qa_ps'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center qa-ws col-qa"><?= $studentData ? number_format((float)($studentData['qa_ws'] ?? 0), 2) : '0.00' ?></td>
                            <td class="text-center initial-grade col-qa"><?= $studentData ? number_format((float)($studentData['initial_grade'] ?? 0), 2) : '0.00' ?></td>
                            <?php $qgVal = $studentData ? (int)($studentData['quarterly_grade'] ?? 0) : 0; ?>
                            <td class="text-center quarterly-grade col-qa <?= ($qgVal <= 74) ? ' grade-low' : '' ?>"><?= $qgVal ?></td>
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
    body { background: #F5F0E8; }

    .header-title-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-top: 24px;
    }
    
    .subject-title {
        color: #1a1f2e;
        font-weight: 800;
        font-size: 2.2rem;
        letter-spacing: -0.5px;
        text-transform: uppercase;
        margin: 0;
    }

    .quarter-pills {
        display: flex;
        gap: 8px;
        background: transparent;
        margin-bottom: 32px;
    }

    .quarter-pill {
        border: 1px solid #adb5bd;
        background: transparent;
        color: #495057;
        font-weight: 600;
        border-radius: 50px;
        padding: 6px 24px;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
    }

    .quarter-pill:hover {
        background: #e9ecef;
    }

    .quarter-pill.active {
        background: #1a1f2e;
        color: #ffffff;
        border-color: #1a1f2e;
    }
    
    .view-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: -1px;
        position: relative;
        z-index: 2;
    }

    .view-tab {
        background: #e9ecef;
        color: #6c757d;
        border: 1px solid #dee2e6;
        border-bottom: none;
        padding: 12px 28px;
        border-radius: 12px 12px 0 0;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
    }

    .view-tab:hover {
        background: #dee2e6;
        color: #495057;
    }

    .view-tab.active {
        background: #ffffff;
        color: #1a1f2e;
        border-color: transparent;
    }
    
    .main-card {
        background: #ffffff;
        border-radius: 0 16px 16px 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.06);
        padding: 24px;
        position: relative;
        z-index: 1;
        margin-bottom: 40px;
    }

    /* Premium Button Styles */
    .btn-upload-premium {
        background: linear-gradient(135deg, #1D6F42 0%, #217346 100%);
        color: white !important;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(33, 115, 70, 0.15);
        font-size: 0.9rem;
    }

    .btn-upload-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(33, 115, 70, 0.25);
        filter: brightness(1.1);
    }

    .btn-submit-premium {
        background: #1a1f2e;
        color: white !important;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(26, 31, 46, 0.15);
        font-size: 0.9rem;
    }

    .btn-submit-premium:hover {
        background: #2a3142;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(26, 31, 46, 0.25);
    }

    .btn-close-premium {
        background: #ffffff;
        color: #1a1f2e !important;
        border: 1px solid #dee2e6;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 700;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        font-size: 0.9rem;
    }

    .btn-close-premium:hover {
        background: #f8f9fa;
        color: #dc3545 !important;
        border-color: #dc3545;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.1);
    }
        .table-container {
            max-height: 100vh;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            position: relative;
            cursor: grab; /* 👈 grab cursor for drag scrolling */
        }
        
        .table-container.grabbing {
            cursor: grabbing; /* 👈 grabbing when dragging */
            user-select: none;
        }
        
        .sticky-table {
            width: 100%;
            table-layout: auto;
            margin-bottom: 0;
        }
        
        /* Sticky header rows */
        .sticky-header-row-1 {
            position: sticky;
            top: 0;
            z-index: 70;
            background-color: #f8f9fa;
        }
        
        .sticky-header-row-2 {
            position: sticky;
            top: 0px; /* Height of first header row */
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
            min-width: 60px;
            max-width: 60px;
            box-sizing: border-box;
        }
        
        .sticky-col-2 {
            position: sticky;
            left: 60px; /* Width of first column */
            z-index: 89;
            background-color: white;
            border-right: 2px solid #f8f9fa;
            min-width: 200px;
            width: auto;
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
        }
        
        .max-score-input {
            width: 45px;
            font-size: 0.75rem;
            border: 1px solid #ced4da; /* keep border for max inputs */
            border-radius: 0.2rem;
            text-align: center;
            padding: 2px;
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
        
        /* Input styling - borderless with bottom border on focus */
        .ww-input, .pt-input, .qa-input {
            border: none !important;
            background-color: transparent;
            text-align: center;
            padding: 0.25rem;
            width: 100%;
            box-shadow: none !important;
            outline: none;
            border-bottom: 2px solid transparent;
            transition: border-bottom-color 0.2s, background-color 0.2s;
        }
        .ww-input:focus, .pt-input:focus, .qa-input:focus {
            border-bottom-color: #007bff;
            background-color: #f0f7ff;
        }
        
        .error-highlight {
            background-color: #ffe6e6 !important;
            border-bottom-color: #dc3545 !important;
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

        /* Low grade highlight (red) */
        .grade-low {
            color: #dc3545 !important;
            font-weight: 600;
        }

        /* Hide number input spinners (up/down arrows) */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        
        /* Summary table styles */
        #Summary .table-container {
            max-height: 400px;
            overflow: auto;
        }
        
        #Summary .sticky-table {
            width: 100%;
            table-layout: auto;
        }
        
        #Summary .sticky-col-1 {
            width: 60px;
        }
        
        #Summary .sticky-col-2 {
            min-width: 200px;
            width: auto;
        }

        /* Ensure proper cell padding */
        .table-sm th,
        .table-sm td {
            padding: 0.25rem 0.2rem;
            font-size: 0.85rem;
        }
        
        /* Prevent grade columns from stretching (ignore spanning headers) */
        th.col-ww:not([colspan]), td.col-ww,
        th.col-pt:not([colspan]), td.col-pt {
            width: 48px;
            min-width: 48px;
            white-space: nowrap;
        }
        th.col-qa:not([colspan]), td.col-qa {
            width: 70px;
            min-width: 70px;
            white-space: nowrap;
        }
        
        /* Specific widths for computed columns */
        th.ww-total, th.ww-ps, th.ww-ws, td.ww-total, td.ww-ps, td.ww-ws,
        th.pt-total, th.pt-ps, th.pt-ws, td.pt-total, td.pt-ps, td.pt-ws,
        th.qa-ps, th.qa-ws, td.qa-ps, td.qa-ws,
        th.initial-grade, td.initial-grade, th.quarterly-grade, td.quarterly-grade {
            width: 60px;
            min-width: 60px;
        }

        /* Fix for table header alignment */
        thead th {
            vertical-align: middle;
        }

        /* Upload Modal Styles */
        .upload-modal-header {
            background-color: #1a1f2e;
            color: white;
            border-bottom: none;
            padding: 1.5rem 2rem;
        }
        .upload-modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .upload-area {
            border: 2px dashed #ced4da;
            border-radius: 12px;
            padding: 2.5rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #1a1f2e;
            background-color: #f1f3f5;
        }
        .upload-area i {
            color: #6c757d;
            transition: all 0.3s ease;
        }
        .upload-area:hover i {
            color: #1a1f2e;
            transform: scale(1.1);
        }
        .modal-success .modal-header { background-color: #198754; color: white; border-bottom: none; }
        .modal-error .modal-header { background-color: #dc3545; color: white; border-bottom: none; }
        .result-icon { font-size: 5rem; margin-bottom: 1rem; }
        .student-list { max-height: 200px; overflow-y: auto; border-radius: 8px; }
    </style>
</head>
<body style="background: #F5F0E8;">
    <?php include '../navs/teacherNav.php'; ?>
    <div class="container-fluid px-2">
        
        <div class="header-title-container">
            <div>
                <h1 class="subject-title"><?= htmlspecialchars($subjectName) ?></h1>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <!-- Auto-save status indicator -->
                <div id="autoSaveStatus" class="me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; cursor: help;" title="All changes saved">
                    <span class="fa-stack" style="font-size: 0.85em;">
                        <i class="fas fa-cloud fa-stack-2x text-success"></i>
                        <i class="fas fa-check fa-stack-1x fa-inverse" style="margin-top: 2px;"></i>
                    </span>
                </div>

                <button id="btnSubmitClassRecord" class="btn btn-submit-premium d-none">
                    <i class="fas fa-paper-plane"></i> Submit Class Record
                </button>

                <button type="button" class="btn btn-upload-premium" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-file-excel"></i> Import Excel
                </button>

                <a href="grading_sheet.php" class="btn btn-close-premium">
                    <i class="fas fa-times me-1"></i> Close
                </a>
            </div>
        </div>

        <div class="quarter-pills d-flex align-items-center justify-content-between" id="quarterPillsContainer">
            <div class="d-flex gap-2">
                <?php for ($qp = 1; $qp <= 4; $qp++): ?>
                <button class="quarter-pill <?= $qp===1?'active':'' ?>" data-quarter="<?= $qp ?>">
                    Q<?= $qp ?>
                    <?php if (in_array($qp, $submittedQuarters)): ?>
                    <span class="badge bg-success ms-1" style="font-size:0.62rem;"><i class="fas fa-check"></i> Submitted</span>
                    <?php endif; ?>
                </button>
                <?php endfor; ?>
            </div>
        </div>

        <div class="view-tabs" id="viewTabsContainer">
            <button class="view-tab active" data-view="ww">Written Work</button>
            <button class="view-tab" data-view="pt">Performance Task</button>
            <button class="view-tab" data-view="qa">Quarterly Assessment</button>
            <button class="view-tab" data-view="classrecord">Class Record</button>
            <button class="view-tab" data-view="summary">Summary</button>
        </div>

        <div class="main-card">
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
                    // Function to get final grade and remarks from database
                    function getSummaryGrades($studentId, $gradesDetails, $summaryGrades) {
                        $q1 = isset($gradesDetails[$studentId][1]['quarterly_grade']) ? 
                              $gradesDetails[$studentId][1]['quarterly_grade'] : null;
                        $q2 = isset($gradesDetails[$studentId][2]['quarterly_grade']) ? 
                              $gradesDetails[$studentId][2]['quarterly_grade'] : null;
                        $q3 = isset($gradesDetails[$studentId][3]['quarterly_grade']) ? 
                              $gradesDetails[$studentId][3]['quarterly_grade'] : null;
                        $q4 = isset($gradesDetails[$studentId][4]['quarterly_grade']) ? 
                              $gradesDetails[$studentId][4]['quarterly_grade'] : null;
                        
                        // Fetch final grade from database
                        $final = null;
                        if (isset($summaryGrades[$studentId]['Final'])) {
                            $final = $summaryGrades[$studentId]['Final'];
                        }
                        
                        return [
                            'q1' => $q1,
                            'q2' => $q2,
                            'q3' => $q3,
                            'q4' => $q4,
                            'final' => $final
                        ];
                    }

                    // Male students
                    if (!empty($maleStudents)): ?>
                        <tr class="gender-divider bg-light">
                            <td colspan="8" class="fw-bold bg-secondary text-white">Male Students (<?= count($maleStudents) ?>)</td>
                        </tr>
                        <?php foreach ($maleStudents as $index => $student): 
                            $summary = getSummaryGrades($student['id'], $gradesDetails, $summaryGrades);
                        ?>
                            <tr>
                                <td class="sticky-col-1 text-center"><?= $index + 1 ?></td>
                                <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                                <td class="text-center<?= (is_numeric($summary['q1']) && $summary['q1'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q1'] !== null ? $summary['q1'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['q2']) && $summary['q2'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q2'] !== null ? $summary['q2'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['q3']) && $summary['q3'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q3'] !== null ? $summary['q3'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['q4']) && $summary['q4'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q4'] !== null ? $summary['q4'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['final']) && $summary['final'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['final'] !== null ? $summary['final'] : '-' ?>
                                </td>
                                <td class="text-center remarks-cell">
                                    <?= $summary['final'] !== null ? getRemarks($summary['final']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; 
                    endif;

                    // Female students
                    if (!empty($femaleStudents)): ?>
                        <tr class="gender-divider bg-light">
                            <td colspan="8" class="fw-bold bg-secondary text-white">Female Students (<?= count($femaleStudents) ?>)</td>
                        </tr>
                        <?php foreach ($femaleStudents as $index => $student): 
                            $summary = getSummaryGrades($student['id'], $gradesDetails, $summaryGrades);
                        ?>
                            <tr>
                                <td class="sticky-col-1 text-center"><?= $index + 1 ?></td>
                                <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                                <td class="text-center<?= (is_numeric($summary['q1']) && $summary['q1'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q1'] !== null ? $summary['q1'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['q2']) && $summary['q2'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q2'] !== null ? $summary['q2'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['q3']) && $summary['q3'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q3'] !== null ? $summary['q3'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['q4']) && $summary['q4'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['q4'] !== null ? $summary['q4'] : '-' ?>
                                </td>
                                <td class="text-center<?= (is_numeric($summary['final']) && $summary['final'] <= 74) ? ' grade-low' : '' ?>">
                                    <?= $summary['final'] !== null ? $summary['final'] : '-' ?>
                                </td>
                                <td class="text-center remarks-cell">
                                    <?= $summary['final'] !== null ? getRemarks($summary['final']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; 
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== CLASS RECORD PANEL ===== -->
<div id="ClassRecord" class="quarter-content d-none">
    <div class="card-body">

        <!-- Class Record Tables per quarter (one shown at a time via outer pills) -->
        <?php for ($q = 1; $q <= 4; $q++):
            $isSubmitted = in_array($q, $submittedQuarters);
            // Compute WW and PT totals for header safely
            $wwHeaderTotal = 0;
            $ptHeaderTotal = 0;
            if (isset($highestScores[$q])) {
                for ($ci = 1; $ci <= 10; $ci++) {
                    $wwHeaderTotal += isset($highestScores[$q]["ww$ci"]) ? (int)$highestScores[$q]["ww$ci"] : 0;
                    $ptHeaderTotal += isset($highestScores[$q]["pt$ci"]) ? (int)$highestScores[$q]["pt$ci"] : 0;
                }
            }
        ?>
        <div id="CR_Q<?= $q ?>" class="cr-quarter-panel <?= $q!==1?'d-none':'' ?>">

            <?php if ($isSubmitted): ?>
            <div class="alert d-flex align-items-center gap-2 mb-3" style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;color:#2e7d32;">
                <i class="fas fa-lock fs-5"></i>
                <div><strong>Q<?= $q ?> has been submitted.</strong></div>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <table class="table table-bordered table-sm mb-0 sticky-table">
                    <thead>
                        <tr class="sticky-header-row-1">
                            <th class="sticky-col-1 text-center">No.</th>
                            <th class="sticky-col-2 learner-name-col">Learner's Name</th>
                            <th colspan="10" class="text-center col-ww">Written Works (<?= htmlspecialchars($wwPercentage) ?>%)</th>
                            <th class="text-center col-ww">Total</th>
                            <th class="text-center col-ww">PS</th>
                            <th class="text-center col-ww">WS</th>
                            <th colspan="10" class="text-center col-pt">Performance Tasks (<?= htmlspecialchars($ptPercentage) ?>%)</th>
                            <th class="text-center col-pt">Total</th>
                            <th class="text-center col-pt">PS</th>
                            <th class="text-center col-pt">WS</th>
                            <th class="text-center col-qa">QA (<?= htmlspecialchars($qaPercentage) ?>%)</th>
                            <th class="text-center col-qa">PS</th>
                            <th class="text-center col-qa">WS</th>
                            <th class="text-center col-qa" rowspan="2" style="font-size:smaller;vertical-align:middle;">Initial Grade</th>
                            <th class="text-center col-qa" rowspan="2" style="font-size:x-small;vertical-align:middle;">Quarterly Grade</th>
                        </tr>
                        <tr class="sticky-header-row-2">
                            <th class="sticky-col-1"></th>
                            <th class="sticky-col-2">Highest Possible Score</th>
                            <?php for ($i = 1; $i <= 10; $i++): $hv = isset($highestScores[$q]["ww$i"]) ? (int)$highestScores[$q]["ww$i"] : 0; ?>
                            <th class="text-center col-ww"><?= $i ?><br><small><?= $hv ?></small></th>
                            <?php endfor; ?>
                            <th class="text-center col-ww" style="font-size:smaller;"><?= $wwHeaderTotal ?></th>
                            <th class="text-center col-ww" style="font-size:smaller;">100.00</th>
                            <th class="text-center col-ww" style="font-size:smaller;"><?= $wwPercentage ?>%</th>
                            <?php for ($i = 1; $i <= 10; $i++): $hv = isset($highestScores[$q]["pt$i"]) ? (int)$highestScores[$q]["pt$i"] : 0; ?>
                            <th class="text-center col-pt"><?= $i ?><br><small><?= $hv ?></small></th>
                            <?php endfor; ?>
                            <th class="text-center col-pt" style="font-size:smaller;"><?= $ptHeaderTotal ?></th>
                            <th class="text-center col-pt" style="font-size:smaller;">100.00</th>
                            <th class="text-center col-pt" style="font-size:smaller;"><?= $ptPercentage ?>%</th>
                            <th class="text-center col-qa"><?= isset($highestScores[$q]['qa1']) ? (int)$highestScores[$q]['qa1'] : 0 ?></th>
                            <th class="text-center col-qa" style="font-size:smaller;">100.00</th>
                            <th class="text-center col-qa" style="font-size:smaller;"><?= $qaPercentage ?>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $crMale   = $maleStudents;
                        $crFemale = $femaleStudents;
                        $crRowNum = 0;
                        if (!empty($crMale)): ?>
                        <tr class="gender-divider">
                            <td colspan="28" class="fw-bold py-2 bg-secondary text-white">Male Students (<?= count($crMale) ?>)</td>
                        </tr>
                        <?php foreach ($crMale as $student):
                            $sd = isset($gradesDetails[$student['id']][$q]) ? $gradesDetails[$student['id']][$q] : null;
                            $crRowNum++;
                            $wwSt = 0; $ptSt = 0;
                            if ($sd) { for ($ci=1;$ci<=10;$ci++) { $wwSt += (int)($sd["ww$ci"]??0); $ptSt += (int)($sd["pt$ci"]??0); } }
                            $qgv = $sd ? (int)$sd['quarterly_grade'] : 0;
                        ?>
                        <tr data-student-id="<?= $student['id'] ?>">
                            <td class="sticky-col-1 text-center"><?= $crRowNum ?></td>
                            <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                            <?php for ($i=1;$i<=10;$i++): ?>
                            <td class="text-center col-ww cr-ww-<?= $i ?>"><?= $sd ? (int)($sd["ww$i"]??0) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="text-center col-ww cr-ww-total"><?= $sd ? $wwSt : '-' ?></td>
                            <td class="text-center col-ww cr-ww-ps"><?= $sd ? number_format((float)($sd['ww_ps']??0),2) : '-' ?></td>
                            <td class="text-center col-ww cr-ww-ws"><?= $sd ? number_format((float)($sd['ww_ws']??0),2) : '-' ?></td>
                            <?php for ($i=1;$i<=10;$i++): ?>
                            <td class="text-center col-pt cr-pt-<?= $i ?>"><?= $sd ? (int)($sd["pt$i"]??0) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="text-center col-pt cr-pt-total"><?= $sd ? $ptSt : '-' ?></td>
                            <td class="text-center col-pt cr-pt-ps"><?= $sd ? number_format((float)($sd['pt_ps']??0),2) : '-' ?></td>
                            <td class="text-center col-pt cr-pt-ws"><?= $sd ? number_format((float)($sd['pt_ws']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-qa-1"><?= $sd ? (int)($sd['qa1']??0) : '-' ?></td>
                            <td class="text-center col-qa cr-qa-ps"><?= $sd ? number_format((float)($sd['qa_ps']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-qa-ws"><?= $sd ? number_format((float)($sd['qa_ws']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-initial-grade"><?= $sd ? number_format((float)($sd['initial_grade']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-quarterly-grade <?= ($qgv>0&&$qgv<=74)?'grade-low':'' ?>"><?= $sd ? $qgv : '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        <?php $crRowNum = 0; if (!empty($crFemale)): ?>
                        <tr class="gender-divider">
                            <td colspan="28" class="fw-bold py-2 bg-secondary text-white">Female Students (<?= count($crFemale) ?>)</td>
                        </tr>
                        <?php foreach ($crFemale as $student):
                            $sd = isset($gradesDetails[$student['id']][$q]) ? $gradesDetails[$student['id']][$q] : null;
                            $crRowNum++;
                            $wwSt = 0; $ptSt = 0;
                            if ($sd) { for ($ci=1;$ci<=10;$ci++) { $wwSt += (int)($sd["ww$ci"]??0); $ptSt += (int)($sd["pt$ci"]??0); } }
                            $qgv = $sd ? (int)$sd['quarterly_grade'] : 0;
                        ?>
                        <tr data-student-id="<?= $student['id'] ?>">
                            <td class="sticky-col-1 text-center"><?= $crRowNum ?></td>
                            <td class="sticky-col-2 learner-name-cell"><?= htmlspecialchars($student['name']) ?></td>
                            <?php for ($i=1;$i<=10;$i++): ?>
                            <td class="text-center col-ww cr-ww-<?= $i ?>"><?= $sd ? (int)($sd["ww$i"]??0) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="text-center col-ww cr-ww-total"><?= $sd ? $wwSt : '-' ?></td>
                            <td class="text-center col-ww cr-ww-ps"><?= $sd ? number_format((float)($sd['ww_ps']??0),2) : '-' ?></td>
                            <td class="text-center col-ww cr-ww-ws"><?= $sd ? number_format((float)($sd['ww_ws']??0),2) : '-' ?></td>
                            <?php for ($i=1;$i<=10;$i++): ?>
                            <td class="text-center col-pt cr-pt-<?= $i ?>"><?= $sd ? (int)($sd["pt$i"]??0) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="text-center col-pt cr-pt-total"><?= $sd ? $ptSt : '-' ?></td>
                            <td class="text-center col-pt cr-pt-ps"><?= $sd ? number_format((float)($sd['pt_ps']??0),2) : '-' ?></td>
                            <td class="text-center col-pt cr-pt-ws"><?= $sd ? number_format((float)($sd['pt_ws']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-qa-1"><?= $sd ? (int)($sd['qa1']??0) : '-' ?></td>
                            <td class="text-center col-qa cr-qa-ps"><?= $sd ? number_format((float)($sd['qa_ps']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-qa-ws"><?= $sd ? number_format((float)($sd['qa_ws']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-initial-grade"><?= $sd ? number_format((float)($sd['initial_grade']??0),2) : '-' ?></td>
                            <td class="text-center col-qa cr-quarterly-grade <?= ($qgv>0&&$qgv<=74)?'grade-low':'' ?>"><?= $sd ? $qgv : '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>
<!-- ===== END CLASS RECORD PANEL ===== -->

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
const schoolYear = "<?= $school_year ?>";
const sectionId = <?= $sectionId ?>;
const submittedQuarters = <?= json_encode($submittedQuarters) ?>;

let currentQuarter = 1;
let hasUnsavedChanges = false;

// Helper functions
function getRemarks(grade) {
    if (grade >= 75) return "PASSED";
    return "Did Not Meet Expectations";
}

function transmuteGrade(initialGrade) {
    // First handle the exact 100 case
    if (initialGrade === 100) return 100;
    
    // Handle each range as specified in the table
    if (initialGrade >= 98.40 && initialGrade <= 99.99) return 99;
    if (initialGrade >= 96.80 && initialGrade <= 98.39) return 98;
    if (initialGrade >= 95.20 && initialGrade <= 96.79) return 97;
    if (initialGrade >= 93.60 && initialGrade <= 95.19) return 96;
    if (initialGrade >= 92.00 && initialGrade <= 93.59) return 95;
    if (initialGrade >= 90.40 && initialGrade <= 91.99) return 94;
    if (initialGrade >= 88.80 && initialGrade <= 90.39) return 93;
    if (initialGrade >= 87.20 && initialGrade <= 88.79) return 92;
    if (initialGrade >= 85.60 && initialGrade <= 87.19) return 91;
    if (initialGrade >= 84.00 && initialGrade <= 85.59) return 90;
    if (initialGrade >= 82.40 && initialGrade <= 83.99) return 89;
    if (initialGrade >= 80.80 && initialGrade <= 82.39) return 88;
    if (initialGrade >= 79.20 && initialGrade <= 80.79) return 87;
    if (initialGrade >= 77.60 && initialGrade <= 79.19) return 86;
    if (initialGrade >= 76.00 && initialGrade <= 77.59) return 85;
    if (initialGrade >= 74.40 && initialGrade <= 75.99) return 84;
    if (initialGrade >= 72.80 && initialGrade <= 74.39) return 83;
    if (initialGrade >= 71.20 && initialGrade <= 72.79) return 82;
    if (initialGrade >= 69.60 && initialGrade <= 71.19) return 81;
    if (initialGrade >= 68.00 && initialGrade <= 69.59) return 80;
    if (initialGrade >= 66.40 && initialGrade <= 67.99) return 79;
    if (initialGrade >= 64.80 && initialGrade <= 66.39) return 78;
    if (initialGrade >= 63.20 && initialGrade <= 64.79) return 77;
    if (initialGrade >= 61.60 && initialGrade <= 63.19) return 76;
    if (initialGrade >= 60.00 && initialGrade <= 61.59) return 75;
    if (initialGrade >= 56.00 && initialGrade <= 59.99) return 74;
    if (initialGrade >= 52.00 && initialGrade <= 55.99) return 73;
    if (initialGrade >= 48.00 && initialGrade <= 51.99) return 72;
    if (initialGrade >= 44.00 && initialGrade <= 47.99) return 71;
    if (initialGrade >= 40.00 && initialGrade <= 43.99) return 70;
    if (initialGrade >= 36.00 && initialGrade <= 39.99) return 69;
    if (initialGrade >= 32.00 && initialGrade <= 35.99) return 68;
    if (initialGrade >= 28.00 && initialGrade <= 31.99) return 67;
    if (initialGrade >= 24.00 && initialGrade <= 27.99) return 66;
    if (initialGrade >= 20.00 && initialGrade <= 23.99) return 65;
    if (initialGrade >= 16.00 && initialGrade <= 19.99) return 64;
    if (initialGrade >= 12.00 && initialGrade <= 15.99) return 63;
    if (initialGrade >= 8.00 && initialGrade <= 11.99) return 62;
    if (initialGrade >= 4.00 && initialGrade <= 7.99) return 61;
    if (initialGrade >= 0 && initialGrade <= 3.99) return 60;
    
    // Fallback for values outside the table (though the table covers 0-100)
    return Math.floor(Math.max(0, Math.min(100, initialGrade)));
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
    const qCell = row.querySelector('.quarterly-grade');
    if (qCell) {
        qCell.textContent = quarterlyGrade;
        if (parseFloat(quarterlyGrade) <= 74) {
            qCell.classList.add('grade-low');
        } else {
            qCell.classList.remove('grade-low');
        }
    }

    // ── Update Class Record tab in real-time ──
    const crRow = document.querySelector(`#CR_Q${quarterToUse} tr[data-student-id="${row.getAttribute('data-student-id')}"]`);
    if (crRow) {
        // Individual Scores
        for (let i = 1; i <= 10; i++) {
            const wwInput = row.querySelector(`.ww-input[data-index="${i}"]`);
            const ptInput = row.querySelector(`.pt-input[data-index="${i}"]`);
            const crWW = crRow.querySelector(`.cr-ww-${i}`);
            const crPT = crRow.querySelector(`.cr-pt-${i}`);
            if (crWW && wwInput) crWW.textContent = wwInput.value !== '' ? wwInput.value : '0';
            if (crPT && ptInput) crPT.textContent = ptInput.value !== '' ? ptInput.value : '0';
        }
        const qaInputRow = row.querySelector('.qa-input');
        const crQA = crRow.querySelector('.cr-qa-1');
        if (crQA && qaInputRow) crQA.textContent = qaInputRow.value !== '' ? qaInputRow.value : '0';

        // Totals & Calculated Columns
        const crWwTotal = crRow.querySelector('.cr-ww-total');
        if (crWwTotal) crWwTotal.textContent = wwTotal;
        const crWwPs = crRow.querySelector('.cr-ww-ps');
        if (crWwPs) crWwPs.textContent = wwPS;
        const crWwWs = crRow.querySelector('.cr-ww-ws');
        if (crWwWs) crWwWs.textContent = wwWS;

        const crPtTotal = crRow.querySelector('.cr-pt-total');
        if (crPtTotal) crPtTotal.textContent = ptTotal;
        const crPtPs = crRow.querySelector('.cr-pt-ps');
        if (crPtPs) crPtPs.textContent = ptPS;
        const crPtWs = crRow.querySelector('.cr-pt-ws');
        if (crPtWs) crPtWs.textContent = ptWS;

        const crQaPs = crRow.querySelector('.cr-qa-ps');
        if (crQaPs) crQaPs.textContent = qaPS;
        const crQaWs = crRow.querySelector('.cr-qa-ws');
        if (crQaWs) crQaWs.textContent = qaWS;

        const crInit = crRow.querySelector('.cr-initial-grade');
        if (crInit) crInit.textContent = initialGrade;
        const crQG = crRow.querySelector('.cr-quarterly-grade');
        if (crQG) {
            crQG.textContent = quarterlyGrade;
            crQG.classList.toggle('grade-low', parseFloat(quarterlyGrade) <= 74);
        }
    }
    
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

function saveGrades(isAutoSave = false) {
    const data = {
        teacherID: teacherId,
        subjectID: subjectId,
        school_year: schoolYear,
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
    if (errorInputs.length > 0) return;

    setAutoSaveStatus('saving');

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
                hasUnsavedChanges = false;
                setAutoSaveStatus('saved');
            } else {
                setAutoSaveStatus('idle');
                console.error('Save error:', result.message);
            }
        } catch (e) {
            console.error('JSON parse error:', e, 'Response text:', text);
            setAutoSaveStatus('idle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const isOffline = !navigator.onLine;
        setAutoSaveStatus(isOffline ? 'offline' : 'idle');
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
    if (type === 'success') {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            confirmButtonText: 'OK'
        });
    }
}

// ==================== KEYBOARD NAVIGATION ====================
function navigateToInput(currentInput, direction) {
    const currentRow = currentInput.closest('tr');
    const tbody = currentRow.parentNode;
    // Get all student rows (excluding gender dividers)
    const rows = Array.from(tbody.children).filter(tr => !tr.classList.contains('gender-divider'));
    const rowIndex = rows.indexOf(currentRow);
    if (rowIndex === -1) return;

    // Get all inputs in current row
    const inputsInRow = Array.from(currentRow.querySelectorAll('input.ww-input, input.pt-input, input.qa-input'));
    const colIndex = inputsInRow.indexOf(currentInput);
    if (colIndex === -1) return;

    let targetRow, targetInput;

    switch (direction) {
        case 'left':
            if (colIndex > 0) {
                targetInput = inputsInRow[colIndex - 1];
            } else {
                // Move to previous row's last input
                if (rowIndex > 0) {
                    const prevRow = rows[rowIndex - 1];
                    const prevInputs = Array.from(prevRow.querySelectorAll('input.ww-input, input.pt-input, input.qa-input'));
                    targetInput = prevInputs[prevInputs.length - 1];
                }
            }
            break;
        case 'right':
            if (colIndex < inputsInRow.length - 1) {
                targetInput = inputsInRow[colIndex + 1];
            } else {
                // Move to next row's first input
                if (rowIndex < rows.length - 1) {
                    const nextRow = rows[rowIndex + 1];
                    const nextInputs = Array.from(nextRow.querySelectorAll('input.ww-input, input.pt-input, input.qa-input'));
                    targetInput = nextInputs[0];
                }
            }
            break;
        case 'up':
            if (rowIndex > 0) {
                const prevRow = rows[rowIndex - 1];
                const prevInputs = Array.from(prevRow.querySelectorAll('input.ww-input, input.pt-input, input.qa-input'));
                if (colIndex < prevInputs.length) {
                    targetInput = prevInputs[colIndex];
                }
            }
            break;
        case 'down':
        case 'enter':
            if (rowIndex < rows.length - 1) {
                const nextRow = rows[rowIndex + 1];
                const nextInputs = Array.from(nextRow.querySelectorAll('input.ww-input, input.pt-input, input.qa-input'));
                if (colIndex < nextInputs.length) {
                    targetInput = nextInputs[colIndex];
                }
            }
            break;
    }

    if (targetInput) {
        targetInput.focus();
        targetInput.select(); // optional: select the text for quick overwrite
    }
}

// ==================== DRAG TO SCROLL ====================
let isDragging = false;
let startX;
let scrollLeftStart;

function initDragScroll() {
    const containers = document.querySelectorAll('.table-container');
    
    containers.forEach(container => {
        // Mouse down: start drag if not on an input
        container.addEventListener('mousedown', (e) => {
            // Don't start drag if clicking on an input or select
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'BUTTON') {
                return;
            }
            
            e.preventDefault(); // prevent text selection
            isDragging = true;
            container.classList.add('grabbing');
            startX = e.pageX - container.offsetLeft;
            scrollLeftStart = container.scrollLeft;
        });
        
        // Mouse move: update scroll if dragging
        container.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const walk = (x - startX) * 2; // scroll speed multiplier
            container.scrollLeft = scrollLeftStart - walk;
        });
        
        // Mouse up / leave: stop dragging
        container.addEventListener('mouseup', () => {
            isDragging = false;
            container.classList.remove('grabbing');
        });
        
        container.addEventListener('mouseleave', () => {
            isDragging = false;
            container.classList.remove('grabbing');
        });
    });
}

// ==================== EVENT LISTENERS ====================
document.addEventListener('DOMContentLoaded', function() {
    // ── Restore saved tab + quarter from localStorage ──
    const savedTab     = localStorage.getItem('ss_activeTab')     || 'ww';
    const savedQuarter = parseInt(localStorage.getItem('ss_activeQuarter') || '1');

    // Set active quarter pill
    document.querySelectorAll('.quarter-pill').forEach(p => {
        p.classList.toggle('active', parseInt(p.getAttribute('data-quarter')) === savedQuarter);
    });

    // Activate the saved tab and render its view
    document.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
    const savedTabBtn = document.querySelector(`.view-tab[data-view="${savedTab}"]`);
    if (savedTabBtn) savedTabBtn.classList.add('active');

    // Hide all contents first
    document.querySelectorAll('.quarter-content').forEach(c => c.classList.add('d-none'));

    if (savedTab === 'summary') {
        document.getElementById('Summary').classList.remove('d-none');
    } else if (savedTab === 'classrecord') {
        document.getElementById('ClassRecord').classList.remove('d-none');
        document.getElementById('btnSubmitClassRecord').classList.remove('d-none');
        document.querySelectorAll('#ClassRecord .col-ww, #ClassRecord .col-pt, #ClassRecord .col-qa').forEach(c => c.style.display = '');
        currentCRQuarter = savedQuarter;
        loadCRQuarter(savedQuarter);
    } else {
        loadQuarterData(savedQuarter);
        // Show correct column set
        document.querySelectorAll('.col-ww, .col-pt, .col-qa').forEach(c => c.style.display = 'none');
        document.querySelectorAll(`.col-${savedTab === 'ww' ? 'ww' : savedTab === 'pt' ? 'pt' : 'qa'}`).forEach(c => c.style.display = '');
        const qEl = document.getElementById(`Q${savedQuarter}`);
        if (qEl) qEl.classList.remove('d-none');
    }

    initDragScroll();

    // Initialize columns for Written Works view (only if no saved tab or saved tab is ww)
    function initView() {
        if (savedTab === 'ww' || !['ww','pt','qa','classrecord','summary'].includes(savedTab)) {
            document.querySelectorAll('.col-pt, .col-qa').forEach(col => col.style.display = 'none');
        }
    }
    initView();

    document.querySelectorAll('.quarter-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            const selectedQuarter = parseInt(this.getAttribute('data-quarter'));

            document.querySelectorAll('.quarter-pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            localStorage.setItem('ss_activeQuarter', selectedQuarter);

            const currentTab = document.querySelector('.view-tab.active').getAttribute('data-view');
            if (currentTab !== 'summary' && currentTab !== 'classrecord') {
                loadQuarterData(selectedQuarter);
            } else if (currentTab === 'classrecord') {
                loadCRQuarter(selectedQuarter);
            }
        });
    });

    // View Tab Listener
    document.querySelectorAll('.view-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const view = this.getAttribute('data-view');

            document.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            localStorage.setItem('ss_activeTab', view);
            
            if (view === 'summary') {
                document.querySelectorAll('.quarter-content').forEach(c => c.classList.add('d-none'));
                document.getElementById('Summary').classList.remove('d-none');
                document.getElementById('ClassRecord').classList.add('d-none');
                document.getElementById('btnSubmitClassRecord').classList.add('d-none');
            } else if (view === 'classrecord') {
                document.querySelectorAll('.quarter-content').forEach(c => c.classList.add('d-none'));
                document.getElementById('Summary').classList.add('d-none');
                document.getElementById('ClassRecord').classList.remove('d-none');
                document.getElementById('btnSubmitClassRecord').classList.remove('d-none');
                // Show all columns in class record
                document.querySelectorAll('#ClassRecord .col-ww, #ClassRecord .col-pt, #ClassRecord .col-qa').forEach(col => col.style.display = '');
                // Load CR panel matching the currently active outer quarter pill
                const aq = document.querySelector('#quarterPillsContainer .quarter-pill.active')?.getAttribute('data-quarter') || '1';
                currentCRQuarter = parseInt(aq);
                loadCRQuarter(currentCRQuarter);
            } else {
                document.getElementById('Summary').classList.add('d-none');
                document.getElementById('ClassRecord').classList.add('d-none');
                document.getElementById('btnSubmitClassRecord').classList.add('d-none');
                const activeQuarter = document.querySelector('.quarter-pill.active').getAttribute('data-quarter');
                document.getElementById(`Q${activeQuarter}`).classList.remove('d-none');
                
                document.querySelectorAll('.col-ww, .col-pt, .col-qa').forEach(col => {
                    col.style.display = 'none';
                });
                
                if (view === 'ww') {
                    document.querySelectorAll('.col-ww').forEach(col => col.style.display = '');
                } else if (view === 'pt') {
                    document.querySelectorAll('.col-pt').forEach(col => col.style.display = '');
                } else if (view === 'qa') {
                    document.querySelectorAll('.col-qa').forEach(col => col.style.display = '');
                }
                
                document.querySelectorAll('.table-container').forEach(c => c.scrollLeft = 0);
            }
        });
    });
    

    
    let autoSaveTimer = null;
    function triggerAutoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            saveGrades(true);
        }, 1500);
    }
    
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('ww-input') || 
            e.target.classList.contains('pt-input') || 
            e.target.classList.contains('qa-input')) {
            
            triggerAutoSave();
            
            // Limit input to two digits
            if (e.target.value.length > 2) {
                e.target.value = e.target.value.slice(0, 2);
            }
            
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
            
            triggerAutoSave();
            
            // Limit input to two digits
            if (e.target.value.length > 2) {
                e.target.value = e.target.value.slice(0, 2);
            }
            
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
    
    // Keyboard navigation for score inputs
    document.addEventListener('keydown', function(e) {
        const target = e.target;
        if (target.tagName === 'INPUT' && (target.classList.contains('ww-input') || target.classList.contains('pt-input') || target.classList.contains('qa-input'))) {
            // Arrow keys
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigateToInput(target, 'left');
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                navigateToInput(target, 'right');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateToInput(target, 'up');
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateToInput(target, 'down');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                navigateToInput(target, 'enter');
            }
        }

        // Ctrl+S — trigger immediate auto-save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveGrades(true);
        }
    });
    
    // Remove beforeunload warning — saving is fully automatic
});

// ==================== AUTO-SAVE STATUS ====================
let saveStatusTimer = null;
function setAutoSaveStatus(state) {
    const container = document.getElementById('autoSaveStatus');
    if (!container) return;
    clearTimeout(saveStatusTimer);
    
    // Ensure styles for custom animation exist
    if (!document.getElementById('saveAnimStyle')) {
        const style = document.createElement('style');
        style.id = 'saveAnimStyle';
        style.textContent = `
            @keyframes pulseCloud {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.6; }
                100% { transform: scale(1); opacity: 1; }
            }
            .cloud-saving-anim { animation: pulseCloud 1s infinite ease-in-out; }
        `;
        document.head.appendChild(style);
    }

    if (state === 'saving') {
        container.innerHTML = '<i class="fas fa-cloud-upload-alt text-primary cloud-saving-anim" style="font-size:1.4rem;"></i>';
        container.setAttribute('title', 'Saving changes...');
    } else if (state === 'saved') {
        container.innerHTML = `
            <span class="fa-stack" style="font-size: 0.85em;">
                <i class="fas fa-cloud fa-stack-2x text-success"></i>
                <i class="fas fa-check fa-stack-1x fa-inverse" style="margin-top: 2px;"></i>
            </span>`;
        container.setAttribute('title', 'All changes saved');
    } else if (state === 'offline') {
        container.innerHTML = '<i class="fas fa-unlink text-danger" style="font-size:1.4rem;"></i>';
        container.setAttribute('title', "You're offline");
    } else {
        container.innerHTML = `
            <span class="fa-stack" style="font-size: 0.85em;">
                <i class="fas fa-cloud fa-stack-2x text-success"></i>
                <i class="fas fa-check fa-stack-1x fa-inverse" style="margin-top: 2px;"></i>
            </span>`;
        container.setAttribute('title', 'All changes saved');
    }
}

// Listen for online/offline events
window.addEventListener('offline', () => setAutoSaveStatus('offline'));
window.addEventListener('online',  () => setAutoSaveStatus('saved'));

// ==================== CLASS RECORD FUNCTIONS ====================
let currentCRQuarter = 1;

function loadCRQuarter(quarter) {
    currentCRQuarter = quarter;
    document.querySelectorAll('.cr-quarter-panel').forEach(p => p.classList.add('d-none'));
    const panel = document.getElementById(`CR_Q${quarter}`);
    if (panel) panel.classList.remove('d-none');
    updateSubmitButtonState();
}

function updateSubmitButtonState() {
    const btn = document.getElementById('btnSubmitClassRecord');
    if (!btn) return;
    const isSubmitted = submittedQuarters.includes(currentCRQuarter);
    if (isSubmitted) {
        btn.disabled = true;
        btn.style.background = '#6c757d';
        btn.innerHTML = '<i class="fas fa-lock me-2"></i>Already Submitted';
    } else {
        btn.disabled = false;
        btn.style.background = '#1a1f2e';
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Class Record';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Submit Class Record button
    document.getElementById('btnSubmitClassRecord')?.addEventListener('click', function() {
        if (submittedQuarters.includes(currentCRQuarter)) return;
        Swal.fire({
            title: `Submit Q${currentCRQuarter} Class Record?`,
            html: `<p class="mb-2">You are about to submit the <strong>Quarter ${currentCRQuarter}</strong> class record.</p>
                   <div class="alert alert-warning py-2 px-3 text-start" style="font-size:0.9rem;border-radius:8px;">
                       <i class="fas fa-exclamation-triangle me-2"></i>
                       <strong>This action cannot be undone.</strong> Once submitted, data input for Q${currentCRQuarter} will be <u>permanently locked</u>.
                   </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Yes, Submit',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#1a1f2e',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
        }).then(result => {
            if (!result.isConfirmed) return;
            const btn = document.getElementById('btnSubmitClassRecord');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            const formData = new FormData();
            formData.append('action', 'submit_class_record');
            formData.append('teacher_id', teacherId);
            formData.append('subject_id', subjectId);
            formData.append('section_id', sectionId);
            formData.append('quarter', currentCRQuarter);
            formData.append('school_year', schoolYear);
            formData.append('submit_using', 'system input');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    submittedQuarters.push(currentCRQuarter);
                    // Add badge to outer quarter pill
                    const pill = document.querySelector(`#quarterPillsContainer .quarter-pill[data-quarter="${currentCRQuarter}"]`);
                    if (pill && !pill.querySelector('.badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-success ms-1';
                        badge.style.fontSize = '0.62rem';
                        badge.innerHTML = '<i class="fas fa-check"></i> Submitted';
                        pill.appendChild(badge);
                    }
                    // Show lock alert in CR panel
                    const panel = document.getElementById(`CR_Q${currentCRQuarter}`);
                    if (panel && !panel.querySelector('.lock-alert')) {
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert d-flex align-items-center gap-2 mb-3 lock-alert';
                        alertDiv.style.cssText = 'background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;color:#2e7d32;';
                        alertDiv.innerHTML = `<i class="fas fa-lock fs-5"></i><div><strong>Q${currentCRQuarter} has been submitted.</strong></div>`;
                        panel.insertBefore(alertDiv, panel.querySelector('.table-container'));
                    }
                    // Lock inputs on grade entry table (including highest possible scores)
                    const qPanel = document.getElementById(`Q${currentCRQuarter}`);
                    if (qPanel) qPanel.querySelectorAll('input').forEach(inp => inp.disabled = true);
                    updateSubmitButtonState();
                    Swal.fire({
                        icon: 'success', title: 'Submitted!', text: res.message,
                        timer: 3000, showConfirmButton: false, toast: true, position: 'top-end'
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                    updateSubmitButtonState();
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Network error. Please try again.', 'error');
                updateSubmitButtonState();
            });
        });
    });

    // Lock inputs for already-submitted quarters on page load
    submittedQuarters.forEach(q => {
        const qPanel = document.getElementById(`Q${q}`);
        if (qPanel) qPanel.querySelectorAll('input').forEach(inp => inp.disabled = true);
    });
});

function updateSubmitButtonState() {
    const btn = document.getElementById('btnSubmitClassRecord');
    if (!btn) return;
    const isSubmitted = submittedQuarters.includes(currentCRQuarter);
    if (isSubmitted) {
        btn.disabled = true;
        btn.style.background = '#6c757d';
        btn.innerHTML = '<i class="fas fa-lock me-2"></i>Already Submitted';
    } else {
        btn.disabled = false;
        btn.style.background = '#1a1f2e';
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Class Record';
    }
}


    </script>
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header upload-modal-header">
                <h5 class="modal-title fw-bold" style="letter-spacing: -0.5px;"><i class="fas fa-file-upload me-2"></i>Upload Grade File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                    <input type="hidden" name="school_year" value="<?= $current_year ?>">
                    
                    <div class="row g-4">
                        <div class="col-md-5 border-end pe-md-4">
                            <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.85rem; letter-spacing: 1px;">Step 1: Configuration</h6>
                            <label class="form-label fw-bold text-dark">Select Quarter</label>
                            <select name="quarter" class="form-select mb-4 shadow-sm" style="border-radius: 8px; padding: 10px 15px;" required>
                                <option value="1">Quarter 1</option>
                                <option value="2">Quarter 2</option>
                                <option value="3">Quarter 3</option>
                                <option value="4">Quarter 4</option>
                            </select>
                            
                            <label class="form-label fw-bold text-dark mt-2">Need a Template?</label>
                            <?php
                            $templateFilename = in_array($subjectName, ["Music", "Arts", "PE", "Health", "MAPEH"]) ? "MAPEH.zip" : str_replace(' ', '_', $subjectName) . '.xlsx';
                            $templatePath = 'ClassRecord/' . $templateFilename;
                            $downloadLink = file_exists($templatePath) ? $templatePath : 'ClassRecord/Default_Template.xlsx';
                            ?>
                            <a href="<?= $downloadLink ?>" download class="btn btn-light border w-100 fw-bold shadow-sm" style="border-radius: 8px; padding: 10px;">
                                <i class="fas fa-download me-2 text-primary"></i> <?= htmlspecialchars($subjectName) ?> Template
                            </a>
                        </div>
                        
                        <div class="col-md-7 ps-md-4">
                            <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.85rem; letter-spacing: 1px;">Step 2: Upload File</h6>
                            <div class="upload-area mb-4" onclick="document.getElementById('grade_file').click()">
                                <i class="fas fa-file-excel fs-1 mb-3"></i>
                                <h6 class="fw-bold text-dark mb-1">Click to browse Excel files</h6>
                                <p class="text-muted small mb-0">Supported formats: .xlsx, .xls</p>
                                <input type="file" class="d-none" id="grade_file" name="grade_file" accept=".xlsx,.xls" required onchange="document.getElementById('file-name').innerHTML = '<i class=\'fas fa-check-circle text-success me-1\'></i> ' + this.files[0].name; document.getElementById('file-name').classList.add('text-success'); document.getElementById('file-name').classList.remove('text-muted');">
                                <div class="mt-3 text-muted fw-bold small" id="file-name">No file chosen</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm" style="border-radius: 8px; padding: 12px; background-color: #1a1f2e; border-color: #1a1f2e;">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Upload and Process
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-primary mb-4" style="width: 3rem; height: 3rem; color: #1a1f2e !important;"></div>
                <h5 class="fw-bold text-dark m-0">Processing Upload</h5>
                <p class="text-muted small mt-2 mb-0">Please do not close this window...</p>
            </div>
        </div>
    </div>
</div>

<!-- Result Modals (Combined) -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header" id="resultHeader" style="border-bottom: none; padding: 1.5rem 2rem;">
                <h5 class="modal-title fw-bold" id="resultTitle" style="letter-spacing: -0.5px;">Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
            </div>
            <div class="modal-body text-center p-5">
                <i id="resultIcon" class="result-icon"></i>
                <h4 id="resultMessage" class="mt-2 fw-bold text-dark"></h4>
                <div id="missingStudentsContainer" class="mt-4 text-start d-none">
                    <h6 class="text-danger fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">Missing Students:</h6>
                    <div class="card card-body student-list bg-light border-0 shadow-sm">
                        <ul class="mb-0 text-muted" id="missingStudentsList" style="font-size: 0.9rem;"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: none; padding: 1.5rem 2rem;">
                <button type="button" class="btn btn-secondary w-100 fw-bold" style="border-radius: 8px; padding: 12px;" data-bs-dismiss="modal" onclick="if(document.getElementById('resultIcon').classList.contains('text-success')) location.reload();">Close and Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const uploadModal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
    uploadModal.hide();
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    fetch('upload_process.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(response => {
        loadingModal.hide();
        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        
        const isSuccess = response.message_type === 'success';
        document.getElementById('resultHeader').className = `modal-header text-white ${isSuccess ? 'bg-success' : 'bg-danger'}`;
        document.getElementById('resultTitle').innerHTML = `<i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle'} me-2"></i>${isSuccess ? 'Success' : 'Error'}`;
        document.getElementById('resultIcon').className = `result-icon fas ${isSuccess ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'}`;
        document.getElementById('resultMessage').textContent = isSuccess ? 'Grades successfully uploaded!' : response.message;
        
        const missingContainer = document.getElementById('missingStudentsContainer');
        const missingList = document.getElementById('missingStudentsList');
        if (response.students_not_found && response.students_not_found.length > 0) {
            missingContainer.classList.remove('d-none');
            missingList.innerHTML = response.students_not_found.map(s => `<li>${s}</li>`).join('');
        } else {
            missingContainer.classList.add('d-none');
        }
        
        resultModal.show();
    })
    .catch(e => {
        loadingModal.hide();
        alert('Upload failed due to a network error.');
    });
});
</script>
</div><!-- /.page-content -->
</body>
</html>