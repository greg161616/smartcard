<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student information
$studentUserId = $_SESSION['user_id'];
$student_query = "SELECT s.* FROM student s WHERE s.userID = ? LIMIT 1";
$stmt = $conn->prepare($student_query);
$stmt->bind_param('i', $studentUserId);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();

// If student record not found, clear session and redirect to login
if (!$student) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Get all enrollments for the student (for dropdown)
$all_enrollments_query = "SELECT se.SectionID, se.SchoolYear, sec.GradeLevel, sec.SectionName 
                          FROM section_enrollment se
                          JOIN section sec ON se.SectionID = sec.SectionID
                          WHERE se.StudentID = ?
                          ORDER BY se.SchoolYear DESC, sec.GradeLevel DESC";
$stmt = $conn->prepare($all_enrollments_query);
$stmt->bind_param('i', $student['StudentID']);
$stmt->execute();
$all_enrollments_result = $stmt->get_result();
$all_enrollments = $all_enrollments_result->fetch_all(MYSQLI_ASSOC);

// Get current enrollment (default selection)
$current_enrollment_query = "SELECT se.SectionID, se.SchoolYear, sec.GradeLevel, sec.SectionName 
                             FROM section_enrollment se
                             JOIN section sec ON se.SectionID = sec.SectionID
                             WHERE se.StudentID = ? AND se.status = 'active' LIMIT 1";
$stmt = $conn->prepare($current_enrollment_query);
$stmt->bind_param('i', $student['StudentID']);
$stmt->execute();
$current_enrollment_result = $stmt->get_result();
$current_enrollment = $current_enrollment_result->fetch_assoc();

// Check if a specific enrollment is selected via POST
$selected_section_id = $current_enrollment['SectionID'] ?? null;
$selected_grade_level = $current_enrollment['GradeLevel'] ?? null;
$selected_school_year = $current_enrollment['SchoolYear'] ?? null;

if (isset($_POST['enrollment_filter'])) {
    $selected = explode('|', $_POST['enrollment_filter']);
    if (count($selected) === 3) {
        // sanitize values
        $selected_section_id = (int)$selected[0];
        $selected_grade_level = preg_replace('/[^0-9]/', '', $selected[1]);
        $selected_school_year = preg_replace('/[^0-9\-]/', '', $selected[2]);
    }
}

// Get grades for the student based on selection - FIXED QUERY
$grades_query = "SELECT 
                    sub.SubjectID,
                    sub.SubjectName,
                    CONCAT(t.fName, ' ', t.lName) as TeacherName,
                    g.Q1, g.Q2, g.Q3, g.Q4, g.Final
                FROM grades g
                JOIN subject sub ON g.subject = sub.SubjectID
                JOIN assigned_subject a ON a.subject_id = sub.SubjectID
                JOIN teacher t ON a.teacher_id = t.TeacherID
                WHERE g.student_id = ? 
                AND g.school_year = ?
                AND a.section_id = ?
                AND a.school_year = ?
                ORDER BY sub.SubjectName";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param('isis', $student['StudentID'], $selected_school_year, $selected_section_id, $selected_school_year);
$stmt->execute();
$grades_result = $stmt->get_result();
$grades = $grades_result->fetch_all(MYSQLI_ASSOC);

// Get detailed scores for all subjects and quarters
$detailed_scores = [];
if (count($grades) > 0) {
    foreach ($grades as $grade) {
        $subject_id = $grade['SubjectID'];
        
        // Get teacher ID for this subject and section
        $teacher_query = "SELECT teacher_id FROM assigned_subject 
                         WHERE subject_id = ? AND section_id = ? AND school_year = ? LIMIT 1";
        $teacher_stmt = $conn->prepare($teacher_query);
        $teacher_stmt->bind_param('iis', $subject_id, $selected_section_id, $selected_school_year);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        $teacher_row = $teacher_result->fetch_assoc();
        $teacher_id = $teacher_row ? $teacher_row['teacher_id'] : null;
        $teacher_stmt->close();
        
        // Get details for all quarters
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            // Get student's detailed scores
            $details_query = "SELECT * FROM grades_details 
                             WHERE studentID = ? AND subjectID = ? AND quarter = ? AND school_year = ?";
            $stmt = $conn->prepare($details_query);
            $stmt->bind_param('iiis', $student['StudentID'], $subject_id, $quarter, $selected_school_year);
            $stmt->execute();
            $details_result = $stmt->get_result();
            $details = $details_result->fetch_assoc();

            // Get highest possible scores for this subject and quarter
            $hps_query = "SELECT * FROM highest_possible_score 
                         WHERE subjectID = ? AND teacherID = ? AND school_year = ? AND quarter = ? LIMIT 1";
            $hps_stmt = $conn->prepare($hps_query);
            $hps_stmt->bind_param('iisi', $subject_id, $teacher_id, $selected_school_year, $quarter);
            $hps_stmt->execute();
            $hps_result = $hps_stmt->get_result();
            $hps = $hps_result->fetch_assoc();
            $hps_stmt->close();

            // Attach highest possible score to details
            if ($details) {
                $details['hps'] = $hps;
            }
            $detailed_scores[$subject_id][$quarter] = $details;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Grades - Balaytigue National High School</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .header-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        .grade-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .grade-row {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            padding: 12px 15px;
            align-items: center;
        }
        .grade-row:last-child {
            border-bottom: none;
        }
        .grade-header {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .grade-cell {
            flex: 1;
            padding: 0 5px;
        }
        .subject-name {
            flex: 3;
        }
        .teacher-name {
            flex: 2;
        }
        .quarter-grade {
            flex: 0 0 70px;
            text-align: center;
        }
        .final-grade {
            flex: 0 0 80px;
            text-align: center;
            font-weight: bold;
        }
        .no-grades {
            background-color: white;
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .student-info {
            margin-bottom: 15px;
        }
        .student-info strong {
            display: inline-block;
            width: 120px;
        }
        .filter-form {
            max-width: 400px;
        }
        .grade-btn {
            display: inline-block;
            width: 50px;
            height: 50px;
            line-height: 50px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            text-decoration: none;
        }
        .grade-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .grade-btn.q1 { background-color: #e3f2fd; color: #1976d2; border-color: #bbdefb; }
        .grade-btn.q2 { background-color: #e8f5e9; color: #388e3c; border-color: #c8e6c9; }
        .grade-btn.q3 { background-color: #fff3e0; color: #f57c00; border-color: #ffe0b2; }
        .grade-btn.q4 { background-color: #fce4ec; color: #c2185b; border-color: #f8bbd9; }
        .grade-btn.final { background-color: #e8eaf6; color: #303f9f; border-color: #c5cae9; font-weight: 700; }
        .grade-btn.empty { background-color: #f5f5f5; color: #9e9e9e; border-color: #e0e0e0; cursor: default; }
        .grade-btn.empty:hover { transform: none; box-shadow: none; }
        .score-table {
            width: 100%;
            font-size: 0.9rem;
        }
        .score-table th {
            background-color: #e9ecef;
            text-align: center;
            padding: 5px;
        }
        .score-table td {
            text-align: center;
            padding: 5px;
            border: 1px solid #dee2e6;
        }
        .score-category {
            font-weight: bold;
            text-align: left;
        }
        .modal-lg-custom {
            max-width: 95%;
        }
        @media (max-width: 768px) {
            .modal-lg-custom {
                max-width: 100%;
                margin: 10px;
            }
            .grade-row {
                flex-wrap: wrap;
            }
            .subject-name, .teacher-name {
                flex: 100%;
                margin-bottom: 10px;
            }
            .quarter-grade, .final-grade {
                flex: 0 0 20%;
                margin-bottom: 5px;
            }
            .grade-btn {
                width: 45px;
                height: 45px;
                line-height: 45px;
            }
        }
        .score-card {
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .score-card-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        .score-card-body {
            padding: 15px;
        }
        .scrollable-table {
            overflow-x: auto;
        }
        .grades-container {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .grade-label {
            font-size: 0.75rem;
            text-align: center;
            margin-top: 5px;
            color: #6c757d;
        }
        .grade-section {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include '../navs/studentNav.php'; ?>

    <div class="container py-5">
        <!-- Student Information Header -->
        <div class="header-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="student-info">
                        <strong>LRN:</strong> <?php echo htmlspecialchars($student['LRN']); ?>
                    </div>
                    <div class="student-info">
                        <strong>Student Name:</strong> <?php $displayName = trim((($student['LastName'] ?? '') . ', ' . ($student['FirstName'] ?? '') . ' ' . ($student['Middlename'] ?? ''))); echo htmlspecialchars($displayName); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <form method="post" class="filter-form">
                        <div class="mb-3">
                            <label for="enrollment_filter" class="form-label">View Grades For:</label>
                            <select class="form-select" id="enrollment_filter" name="enrollment_filter" onchange="this.form.submit()">
                                <?php foreach ($all_enrollments as $enrollment): ?>
                                    <option value="<?php echo $enrollment['SectionID'] . '|' . $enrollment['GradeLevel'] . '|' . $enrollment['SchoolYear']; ?>"
                                        <?php if ($enrollment['SectionID'] == $selected_section_id && $enrollment['GradeLevel'] == $selected_grade_level && $enrollment['SchoolYear'] == $selected_school_year) echo 'selected'; ?>>
                                        Grade <?php echo $enrollment['GradeLevel']; ?> - <?php echo $enrollment['SectionName']; ?> (SY: <?php echo $enrollment['SchoolYear']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (count($grades) > 0): ?>
            <div class="card">
                <!-- Header Row -->
                <div class="grade-row grade-header text-secondary">
                    <div class="grade-cell subject-name">Subject</div>
                    <div class="grade-cell teacher-name">Teacher</div>
                    <div class="grade-cell quarter-grade">
                        <div class="grade-section">
                            <div>Q1</div>
                            <div class="grade-label">Quarter 1</div>
                        </div>
                    </div>
                    <div class="grade-cell quarter-grade">
                        <div class="grade-section">
                            <div>Q2</div>
                            <div class="grade-label">Quarter 2</div>
                        </div>
                    </div>
                    <div class="grade-cell quarter-grade">
                        <div class="grade-section">
                            <div>Q3</div>
                            <div class="grade-label">Quarter 3</div>
                        </div>
                    </div>
                    <div class="grade-cell quarter-grade">
                        <div class="grade-section">
                            <div>Q4</div>
                            <div class="grade-label">Quarter 4</div>
                        </div>
                    </div>
                    <div class="grade-cell final-grade">
                        <div class="grade-section">
                            <div>Final</div>
                            <div class="grade-label">Final Grade</div>
                        </div>
                    </div>
                </div>
                
                <!-- Grade Rows -->
                <?php foreach ($grades as $grade): 
                    $subject_id = $grade['SubjectID'];
                ?>
                    <div class="grade-row">
                        <div class="grade-cell subject-name">
                            <strong><?php echo htmlspecialchars($grade['SubjectName']); ?></strong>
                        </div>
                        <div class="grade-cell teacher-name"><?php echo htmlspecialchars($grade['TeacherName']); ?></div>
                        
                        <!-- Quarter 1 Grade Button -->
                        <div class="grade-cell quarter-grade">
                            <?php if (!empty($grade['Q1'])): ?>
                                <a href="#" class="grade-btn q1" data-bs-toggle="modal" data-bs-target="#detailsModal" 
                                   onclick="showDetailedScores(<?php echo $subject_id; ?>, '<?php echo htmlspecialchars($grade['SubjectName']); ?>', 1)">
                                    <?php echo htmlspecialchars($grade['Q1']); ?>
                                </a>
                            <?php else: ?>
                                <span class="grade-btn empty">-</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quarter 2 Grade Button -->
                        <div class="grade-cell quarter-grade">
                            <?php if (!empty($grade['Q2'])): ?>
                                <a href="#" class="grade-btn q2" data-bs-toggle="modal" data-bs-target="#detailsModal" 
                                   onclick="showDetailedScores(<?php echo $subject_id; ?>, '<?php echo htmlspecialchars($grade['SubjectName']); ?>', 2)">
                                    <?php echo htmlspecialchars($grade['Q2']); ?>
                                </a>
                            <?php else: ?>
                                <span class="grade-btn empty">-</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quarter 3 Grade Button -->
                        <div class="grade-cell quarter-grade">
                            <?php if (!empty($grade['Q3'])): ?>
                                <a href="#" class="grade-btn q3" data-bs-toggle="modal" data-bs-target="#detailsModal" 
                                   onclick="showDetailedScores(<?php echo $subject_id; ?>, '<?php echo htmlspecialchars($grade['SubjectName']); ?>', 3)">
                                    <?php echo htmlspecialchars($grade['Q3']); ?>
                                </a>
                            <?php else: ?>
                                <span class="grade-btn empty">-</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quarter 4 Grade Button -->
                        <div class="grade-cell quarter-grade">
                            <?php if (!empty($grade['Q4'])): ?>
                                <a href="#" class="grade-btn q4" data-bs-toggle="modal" data-bs-target="#detailsModal" 
                                   onclick="showDetailedScores(<?php echo $subject_id; ?>, '<?php echo htmlspecialchars($grade['SubjectName']); ?>', 4)">
                                    <?php echo htmlspecialchars($grade['Q4']); ?>
                                </a>
                            <?php else: ?>
                                <span class="grade-btn empty">-</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Final Grade Button -->
                        <div class="grade-cell final-grade">
                            <?php if (!empty($grade['Final'])): ?>
                                <span class="grade-btn final"><?php echo htmlspecialchars($grade['Final']); ?></span>
                            <?php else: ?>
                                <span class="grade-btn empty">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-grades">
                <i class="bi bi-journal-text" style="font-size: 3rem; color: #6c757d;"></i>
                <h4 class="mt-3">No grades available yet</h4>
                <p class="text-muted">Your grades will appear here once they're posted by your teachers.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detailed Scores Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Detailed Scores</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store detailed scores data for JavaScript access
        const detailedScoresData = <?php echo json_encode($detailed_scores); ?>;
        
        function showDetailedScores(subjectId, subjectName, quarter) {
            const details = detailedScoresData[subjectId] ? detailedScoresData[subjectId][quarter] : null;
            if (!details) {
                document.getElementById('modalBody').innerHTML = '<p>No detailed scores available for this subject and quarter.</p>';
                return;
            }
            const hps = details.hps || {};
            // Create written works table
            let wwTable = '';
            if (details.ww_total > 0 || (hps.ww_total > 0)) {
                wwTable = `
                <div class="score-card">
                    <div class="score-card-header">Written Works (WW)</div>
                    <div class="score-card-body">
                        <div class="scrollable-table">
                            <table class="score-table">
                                <thead>
                                    <tr>
                                        ${Array.from({length: 10}, (_, i) => `<th>${i+1}</th>`).join('')}
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="background:#e9ecef; font-weight:600;">
                                        <td colspan="11" class="text-primary text-center">Highest Possible Score</td>
                                    </tr>
                                    <tr style="background:#f8f9fa;">
                                        ${Array.from({length: 10}, (_, i) => `<td class='text-muted'>${hps['ww' + (i+1)] || '-'}</td>`).join('')}
                                        <td class='text-muted'>${hps.ww_total || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="11" class="text-success text-center">Student Score</td>
                                    </tr>
                                    <tr>
                                        ${Array.from({length: 10}, (_, i) => `<td>${details['ww' + (i+1)] || '-'}</td>`).join('')}
                                        <td>${details.ww_total || '-'}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="10" class="score-category">Percentage Score:</td>
                                        <td>${details.ww_ps || '-'}%</td>
                                    </tr>
                                    <tr>
                                        <td colspan="10" class="score-category">Weighted Score:</td>
                                        <td>${details.ww_ws || '-'}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>`;
            }
            // Create performance tasks table
            let ptTable = '';
            if (details.pt_total > 0 || (hps.pt_total > 0)) {
                ptTable = `
                <div class="score-card">
                    <div class="score-card-header">Performance Tasks (PT)</div>
                    <div class="score-card-body">
                        <div class="scrollable-table">
                            <table class="score-table">
                                <thead>
                                    <tr>
                                        ${Array.from({length: 10}, (_, i) => `<th>${i+1}</th>`).join('')}
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="background:#e9ecef; font-weight:600;">
                                        <td colspan="11" class="text-primary text-center">Highest Possible Score (HPS)</td>
                                    </tr>
                                    <tr style="background:#f8f9fa;">
                                        ${Array.from({length: 10}, (_, i) => `<td class='text-muted'>${hps['pt' + (i+1)] || '-'}</td>`).join('')}
                                        <td class='text-muted'>${hps.pt_total || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="11" class="text-success text-center">Student Score</td>
                                    </tr>
                                    <tr>
                                        ${Array.from({length: 10}, (_, i) => `<td>${details['pt' + (i+1)] || '-'}</td>`).join('')}
                                        <td>${details.pt_total || '-'}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="10" class="score-category">Percentage Score:</td>
                                        <td>${details.pt_ps || '-'}%</td>
                                    </tr>
                                    <tr>
                                        <td colspan="10" class="score-category">Weighted Score:</td>
                                        <td>${details.pt_ws || '-'}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>`;
            }
            // Create quarterly assessment table
            let qaTable = '';
            if (details.qa1 > 0 || (hps.qa1 > 0)) {
                qaTable = `
                <div class="score-card">
                    <div class="score-card-header">Quarterly Assessment (QA)</div>
                    <div class="score-card-body">
                        <table class="score-table">
                            <thead>
                                <tr>
                                    <th>QA1</th>
                                    <th>Score</th>
                                    <th>HPS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>QA1</td>
                                    <td>${details.qa1 || '-'}</td>
                                    <td class='text-muted'>${hps.qa1 || '-'}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="score-category">Percentage Score:</td>
                                    <td colspan="2">${details.qa_ps || '-'}%</td>
                                </tr>
                                <tr>
                                    <td class="score-category">Weighted Score:</td>
                                    <td colspan="2">${details.qa_ws || '-'}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>`;
            }
            // Create summary table
            const summaryTable = `
            <div class="score-card">
                <div class="score-card-header">Grade Summary</div>
                <div class="score-card-body">
                    <table class="score-table">
                        <thead>
                            <tr>
                                <th colspan="2">Quarter ${quarter} Grade Calculation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="score-category">Initial Grade:</td>
                                <td>${details.initial_grade || '-'}</td>
                            </tr>
                            <tr>
                                <td class="score-category">Quarterly Grade:</td>
                                <td><strong>${details.quarterly_grade || '-'}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
            // Combine all sections
            const modalContent = `
            <h6 class="mb-4">${subjectName} - Quarter ${quarter}</h6>
            <div class="row">
                <div class="col-lg-6">
                    ${wwTable}
                </div>
                <div class="col-lg-6">
                    ${ptTable}
                </div>
                <div class="col-lg-6">
                    ${qaTable}
                </div>
                <div class="col-lg-6">
                    ${summaryTable}
                </div>
            </div>`;
            document.getElementById('modalBody').innerHTML = modalContent;
        }
    </script>
</body>
</html>