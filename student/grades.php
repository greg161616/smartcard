<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student information
$student_id = $_SESSION['user_id'];
$student_query = "SELECT s.* FROM student s WHERE s.userID = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();

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
                             WHERE se.StudentID = ? AND se.status = 'active'";
$stmt = $conn->prepare($current_enrollment_query);
$stmt->bind_param('i', $student['StudentID']);
$stmt->execute();
$current_enrollment_result = $stmt->get_result();
$current_enrollment = $current_enrollment_result->fetch_assoc();

// Check if a specific enrollment is selected via POST
$selected_grade_level = $current_enrollment['GradeLevel'] ?? null;
$selected_school_year = $current_enrollment['SchoolYear'] ?? null;

if (isset($_POST['enrollment_filter'])) {
    $selected = explode('|', $_POST['enrollment_filter']);
    if (count($selected) === 2) {
        $selected_grade_level = $selected[0];
        $selected_school_year = $selected[1];
    }
}

// Get grades for the student based on selection
$grades_query = "SELECT 
                    sub.SubjectName,
                    CONCAT(t.fName, ' ', t.lName) as TeacherName,
                    g.Q1, g.Q2, g.Q3, g.Q4, g.Final
                FROM grades g
                JOIN subject sub ON g.subject = sub.SubjectID
                JOIN teacher t ON sub.teacherID = t.TeacherID
                WHERE g.student_id = ? AND sub.GradeLevel = ? AND g.school_year = ?
                ORDER BY sub.SubjectName";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param('iis', $student['StudentID'], $selected_grade_level, $selected_school_year);
$stmt->execute();
$grades_result = $stmt->get_result();
$grades = $grades_result->fetch_all(MYSQLI_ASSOC);
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
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .grade-row {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            padding: 12px 15px;
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
                        <strong>Student Name:</strong> <?php echo htmlspecialchars($student['LastName'] . ', ' . htmlspecialchars($student['FirstName']). ' '. htmlspecialchars($student['Middlename']).'.'); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <form method="post" class="filter-form">
                        <div class="mb-3">
                            <label for="enrollment_filter" class="form-label">View Grades For:</label>
                            <select class="form-select" id="enrollment_filter" name="enrollment_filter" onchange="this.form.submit()">
                                <?php foreach ($all_enrollments as $enrollment): ?>
                                    <option value="<?php echo $enrollment['GradeLevel'] . '|' . $enrollment['SchoolYear']; ?>"
                                        <?php if ($enrollment['GradeLevel'] == $selected_grade_level && $enrollment['SchoolYear'] == $selected_school_year) echo 'selected'; ?>>
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
                <div class="grade-row text-secondary">
                    <div class="grade-cell subject-name">Subject</div>
                    <div class="grade-cell teacher-name">Teacher</div>
                    <div class="grade-cell quarter-grade">Q1</div>
                    <div class="grade-cell quarter-grade">Q2</div>
                    <div class="grade-cell quarter-grade">Q3</div>
                    <div class="grade-cell quarter-grade">Q4</div>
                    <div class="grade-cell final-grade">Final</div>
                </div>
                
                <!-- Grade Rows -->
                <?php foreach ($grades as $grade): ?>
                    <div class="grade-row">
                        <div class="grade-cell subject-name"><?php echo htmlspecialchars($grade['SubjectName']); ?></div>
                        <div class="grade-cell teacher-name"><?php echo htmlspecialchars($grade['TeacherName']); ?></div>
                        <div class="grade-cell quarter-grade"><?php echo htmlspecialchars($grade['Q1'] ?? '-'); ?></div>
                        <div class="grade-cell quarter-grade"><?php echo htmlspecialchars($grade['Q2'] ?? '-'); ?></div>
                        <div class="grade-cell quarter-grade"><?php echo htmlspecialchars($grade['Q3'] ?? '-'); ?></div>
                        <div class="grade-cell quarter-grade"><?php echo htmlspecialchars($grade['Q4'] ?? '-'); ?></div>
                        <div class="grade-cell final-grade"><?php echo htmlspecialchars($grade['Final'] ?? '-'); ?></div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>