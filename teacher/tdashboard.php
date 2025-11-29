<?php
session_start();
include __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Manila');
// Check if teacher is logged in    
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$teacher_id = null;

// Get TeacherID with error handling
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not registered as a teacher.";
    exit;
}
$teacher_id = $res->fetch_assoc()['TeacherID'];
$stmt->close();

// Get teacher's basic info
$teacher_sql = "SELECT fName, lName, mName FROM teacher WHERE TeacherID = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
if (!$teacher_stmt) {
    die("Database error: " . $conn->error);
}
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_name = trim($teacher['fName'] . ' ' . ($teacher['mName'] ? $teacher['mName'] . ' ' : '') . $teacher['lName']);
$teacher_stmt->close();

// Fetch all school years from school_year table for dropdown
$all_years_sql = "SELECT school_year FROM school_year ORDER BY school_year DESC";
$all_years_result = $conn->query($all_years_sql);

$available_school_years = [];
while ($row = $all_years_result->fetch_assoc()) {
    $available_school_years[] = $row['school_year'];
}

// Fetch active school year from school_year table
$active_school_year = '';
$active_year_sql = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$active_year_result = $conn->query($active_year_sql);
if ($active_year_result && $active_year_result->num_rows > 0) {
    $active_school_year = $active_year_result->fetch_assoc()['school_year'];
}

// Set current school year - prefer active school year, fall back to GET parameter or first available
$current_school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';
if (empty($current_school_year)) {
    if (!empty($active_school_year)) {
        $current_school_year = $active_school_year;
    } elseif (!empty($available_school_years)) {
        $current_school_year = $available_school_years[0];
    } else {
        $current_year = date('Y');
        $current_school_year = $current_year . '-' . ($current_year + 1);
    }
}

// Get teacher's assigned sections and subjects for the current school year
$assigned_sections_sql = "
    SELECT 
        sec.SectionID,
        sec.GradeLevel,
        sec.SectionName,
        sub.SubjectID,
        sub.SubjectName,
        CONCAT(sec.GradeLevel, '-', sec.SectionName) as section_full_name
    FROM assigned_subject a
    INNER JOIN section sec ON a.section_id = sec.SectionID
    INNER JOIN subject sub ON a.subject_id = sub.SubjectID
    WHERE a.teacher_id = ? AND a.school_year = ?
    ORDER BY sec.GradeLevel, sec.SectionName, sub.SubjectName
";

$assigned_sections_stmt = $conn->prepare($assigned_sections_sql);
if (!$assigned_sections_stmt) {
    die("Database error: " . $conn->error);
}
$assigned_sections_stmt->bind_param("is", $teacher_id, $current_school_year);
$assigned_sections_stmt->execute();
$assigned_sections_result = $assigned_sections_stmt->get_result();

$assigned_sections = [];
$assigned_subjects = [];
$section_ids = [];

while ($row = $assigned_sections_result->fetch_assoc()) {
    $section_id = $row['SectionID'];
    $section_full_name = $row['section_full_name'];
    
    if (!in_array($section_id, $section_ids)) {
        $section_ids[] = $section_id;
    }
    
    if (!isset($assigned_sections[$section_full_name])) {
        $assigned_sections[$section_full_name] = [
            'section_id' => $section_id,
            'grade_level' => $row['GradeLevel'],
            'section_name' => $row['SectionName'],
            'subjects' => []
        ];
    }
    
    $assigned_sections[$section_full_name]['subjects'][] = [
        'subject_id' => $row['SubjectID'],
        'subject_name' => $row['SubjectName']
    ];
    
    $assigned_subjects[] = $row['SubjectID'];
}
$assigned_sections_stmt->close();

// Get total students count by gender for assigned sections in selected school year
$students_sql = "
    SELECT 
        s.Sex,
        COUNT(DISTINCT s.StudentID) as student_count,
        sec.SectionID,
        CONCAT(sec.GradeLevel, '-', sec.SectionName) as section_name
    FROM student s
    INNER JOIN section_enrollment se ON s.StudentID = se.StudentID AND se.status = 'active'
    INNER JOIN section sec ON se.SectionID = sec.SectionID
    WHERE sec.SectionID IN (" . (count($section_ids) > 0 ? implode(',', array_fill(0, count($section_ids), '?')) : '0') . ")
    AND se.SchoolYear = ?
    GROUP BY s.Sex, sec.SectionID, sec.GradeLevel, sec.SectionName
";

$students_stmt = $conn->prepare($students_sql);
if (!$students_stmt) {
    die("Database error: " . $conn->error);
}

if (count($section_ids) > 0) {
    $types = str_repeat('i', count($section_ids)) . 's';
    $params = $section_ids;
    $params[] = $current_school_year;
    $students_stmt->bind_param($types, ...$params);
} else {
    $students_stmt->bind_param("s", $current_school_year);
}

$students_stmt->execute();
$students_result = $students_stmt->get_result();

$male_count = 0;
$female_count = 0;
$total_students = 0;
$section_counts = [];

while ($row = $students_result->fetch_assoc()) {
    if ($row['Sex'] == 'Male') {
        $male_count += $row['student_count'];
    } else {
        $female_count += $row['student_count'];
    }
    $total_students += $row['student_count'];
    
    // Store section-wise counts
    $section_name = $row['section_name'];
    if (!isset($section_counts[$section_name])) {
        $section_counts[$section_name] = ['male' => 0, 'female' => 0, 'total' => 0];
    }
    if ($row['Sex'] == 'Male') {
        $section_counts[$section_name]['male'] += $row['student_count'];
    } else {
        $section_counts[$section_name]['female'] += $row['student_count'];
    }
    $section_counts[$section_name]['total'] += $row['student_count'];
}
$students_stmt->close();

// Fixed attendance query with proper table joins using assigned sections
$today = date('Y-m-d');
$attendance_sql = "
    SELECT 
        a.Status,
        COUNT(*) as count
    FROM attendance a
    WHERE a.TeacherID = ? 
    AND a.Date = ?
    AND a.SectionID IN (" . (count($section_ids) > 0 ? implode(',', array_fill(0, count($section_ids), '?')) : '0') . ")
    GROUP BY a.Status
";

$attendance_stmt = $conn->prepare($attendance_sql);
if (!$attendance_stmt) {
    die("Database error: " . $conn->error);
}

if (count($section_ids) > 0) {
    $types = 'is' . str_repeat('i', count($section_ids));
    $params = array_merge([$teacher_id, $today], $section_ids);
    $attendance_stmt->bind_param($types, ...$params);
} else {
    $attendance_stmt->bind_param("is", $teacher_id, $today);
}

$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

$present_today = 0;
$absent_today = 0;
$excused_today = 0;
$total_attendance_today = 0;
$attendance_taken = false;

if ($attendance_result->num_rows > 0) {
    $attendance_taken = true;
    while ($row = $attendance_result->fetch_assoc()) {
        switch (strtolower($row['Status'])) {
            case 'present':
                $present_today = $row['count'];
                break;
            case 'absent':
                $absent_today = $row['count'];
                break;
            case 'excused':
                $excused_today = $row['count'];
                break;
        }
    }
    $total_attendance_today = $present_today + $absent_today + $excused_today;
}
$attendance_stmt->close();

// Get grade overview for current school year using assigned subjects
$grades_sql = "
    SELECT 
        sub.SubjectName,
        gd.quarter,
        AVG(gd.quarterly_grade) as avg_grade,
        COUNT(DISTINCT gd.studentID) as student_count,
        CONCAT(sec.GradeLevel, '-', sec.SectionName) as section_name
    FROM grades_details gd
    INNER JOIN subject sub ON gd.subjectID = sub.SubjectID
    INNER JOIN section_enrollment se ON gd.studentID = se.StudentID AND se.status = 'active'
    INNER JOIN section sec ON se.SectionID = sec.SectionID
    WHERE gd.teacherID = ? AND gd.school_year = ?
    AND gd.subjectID IN (" . (count($assigned_subjects) > 0 ? implode(',', array_fill(0, count($assigned_subjects), '?')) : '0') . ")
    GROUP BY sec.SectionID, sec.GradeLevel, sec.SectionName, sub.SubjectID, sub.SubjectName, gd.quarter
    ORDER BY sec.SectionID, sub.SubjectName ASC, gd.quarter ASC
";

$grades_stmt = $conn->prepare($grades_sql);
if (!$grades_stmt) {
    die("Database error: " . $conn->error);
}

if (count($assigned_subjects) > 0) {
    $types = 'is' . str_repeat('i', count($assigned_subjects));
    $params = array_merge([$teacher_id, $current_school_year], $assigned_subjects);
    $grades_stmt->bind_param($types, ...$params);
} else {
    $grades_stmt->bind_param("is", $teacher_id, $current_school_year);
}

$grades_stmt->execute();
$grades_result = $grades_stmt->get_result();

$grade_overview = [];
$sections = [];
$quarters_data_by_section = [];

while ($row = $grades_result->fetch_assoc()) {
    $section = $row['section_name'];
    $subject = $row['SubjectName'];
    $quarter = (int)$row['quarter'];
    $avg_grade = number_format($row['avg_grade'], 1);
    
    // Store unique sections
    if (!in_array($section, $sections)) {
        $sections[] = $section;
    }
    
    // Initialize section data if not exists
    if (!isset($quarters_data_by_section[$section])) {
        $quarters_data_by_section[$section] = [
            'subjects' => [],
            'quarters' => [1 => [], 2 => [], 3 => [], 4 => []]
        ];
    }
    
    // Store unique subjects for this section
    if (!in_array($subject, $quarters_data_by_section[$section]['subjects'])) {
        $quarters_data_by_section[$section]['subjects'][] = $subject;
    }
    
    // Store grade data by section and quarter
    $quarters_data_by_section[$section]['quarters'][$quarter][$subject] = $avg_grade;
    
    $grade_overview[] = $row;
}
$grades_stmt->close();

// Get students with honors for current school year using assigned sections
$honors_sql = "
    SELECT 
        s.StudentID,
        CONCAT(s.FirstName, ' ', COALESCE(s.MiddleName, ''), ' ', s.LastName) as student_name,
        CONCAT(sec.GradeLevel, '-', sec.SectionName) as section_name,
        AVG(gd.quarterly_grade) as overall_average,
        CASE 
            WHEN AVG(gd.quarterly_grade) >= 98 THEN 'With Highest Honors'
            WHEN AVG(gd.quarterly_grade) >= 95 THEN 'With High Honors'
            WHEN AVG(gd.quarterly_grade) >= 90 THEN 'With Honors'
            ELSE 'No Honors'
        END as honor_category
    FROM student s
    INNER JOIN section_enrollment se ON s.StudentID = se.StudentID AND se.status = 'active'
    INNER JOIN section sec ON se.SectionID = sec.SectionID
    INNER JOIN grades_details gd ON s.StudentID = gd.studentID AND gd.school_year = se.SchoolYear
    WHERE se.SchoolYear = ? 
    AND sec.SectionID IN (" . (count($section_ids) > 0 ? implode(',', array_fill(0, count($section_ids), '?')) : '0') . ")
    AND gd.subjectID IN (" . (count($assigned_subjects) > 0 ? implode(',', array_fill(0, count($assigned_subjects), '?')) : '0') . ")
    GROUP BY s.StudentID, s.FirstName, s.MiddleName, s.LastName, sec.GradeLevel, sec.SectionName
    HAVING AVG(gd.quarterly_grade) >= 90
    ORDER BY 
        CASE 
            WHEN AVG(gd.quarterly_grade) >= 98 THEN 1
            WHEN AVG(gd.quarterly_grade) >= 95 THEN 2
            WHEN AVG(gd.quarterly_grade) >= 90 THEN 3
        END,
        AVG(gd.quarterly_grade) DESC,
        section_name,
        student_name
";

$honors_stmt = $conn->prepare($honors_sql);
if (!$honors_stmt) {
    die("Database error: " . $conn->error);
}

if (count($section_ids) > 0 && count($assigned_subjects) > 0) {
    $types = 's' . str_repeat('i', count($section_ids) + count($assigned_subjects));
    $params = array_merge([$current_school_year], $section_ids, $assigned_subjects);
    $honors_stmt->bind_param($types, ...$params);
} else {
    $honors_stmt->bind_param("s", $current_school_year);
}

$honors_stmt->execute();
$honors_result = $honors_stmt->get_result();

$honors_students = [
    'With Highest Honors' => [],
    'With High Honors' => [],
    'With Honors' => []
];

while ($row = $honors_result->fetch_assoc()) {
    if (isset($honors_students[$row['honor_category']])) {
        $honors_students[$row['honor_category']][] = $row;
    }
}
$honors_stmt->close();

// Get upcoming events
$events_sql = "
    SELECT title, event_date, description 
    FROM events 
    WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY event_date ASC 
    LIMIT 5
";

$events_result = $conn->query($events_sql);
if (!$events_result) {
    die("Database error: " . $conn->error);
}
$upcoming_events = [];
while ($row = $events_result->fetch_assoc()) {
    $upcoming_events[] = $row;
}

// Get recent announcements
$announcements_sql = "
    SELECT title, date, content, id
    FROM announcements 
    ORDER BY date DESC 
    LIMIT 3
";

$announcements_result = $conn->query($announcements_sql);
if (!$announcements_result) {
    die("Database error: " . $conn->error);
}
$recent_announcements = [];
while ($row = $announcements_result->fetch_assoc()) {
    $recent_announcements[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="icon" type="image/png" href="../img/logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-header {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .attendance-chart {
            max-height: 250px;
        }
        .grade-progress {
            height: 8px;
        }
        .section-stats {
            font-size: 0.9rem;
        }
        .card-header {
            font-weight: 600;
        }
        .no-record {
            color: #6c757d;
            font-style: italic;
        }
        .absent-student-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .quarter-toggle {
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .quarter-toggle.active {
            background-color: #0d6efd;
            color: white;
        }
        .honor-badge {
            font-size: 0.75rem;
        }
        .announcement-link {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        .announcement-link:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../navs/teacherNav.php'; ?>
    
    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                    <p class="lead mb-0">Teacher Dashboard - <?php echo date('F j, Y'); ?></p>
                </div>
                <div class="col-md-4 text-end">
                        <form method="GET" class="d-flex align-items-center justify-content-end gap-2">
                            <label for="school_year" class="fw-bold mb-0">School Year:</label>
                            <select name="school_year" id="school_year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <?php foreach ($available_school_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year); ?>" 
                                        <?php echo $year == $current_school_year ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (empty($available_school_years)): ?>
                                    <option value="<?php echo htmlspecialchars($current_school_year); ?>" selected>
                                        <?php echo htmlspecialchars($current_school_year); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <div class="container-fluid">
        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">TOTAL STUDENTS</h6>
                                <h3 class="fw-bold"><?php echo $total_students; ?></h3>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Male: <?php echo $male_count; ?> | Female: <?php echo $female_count; ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">TODAY'S ATTENDANCE</h6>
                                <?php if ($attendance_taken): ?>
                                    <h3 class="fw-bold"><?php echo $present_today + $excused_today; ?>/<?php echo $total_attendance_today; ?></h3>
                                <?php else: ?>
                                    <h3 class="fw-bold text-muted">No Record</h3>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-2">
                            <?php if ($attendance_taken): ?>
                                <small class="text-muted">Present: <?php echo $present_today; ?> | Absent: <?php echo $absent_today; ?> | Excused: <?php echo $excused_today; ?> </small>
                            <?php else: ?>
                                <small class="text-muted">No attendance recorded for today</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">MY CLASSES</h6>
                                <h3 class="fw-bold"><?php echo count($assigned_sections); ?></h3>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Assigned sections</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Student Distribution by Class -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark">
                        <h5 class="mb-0">Student Distribution by Class (<?php echo htmlspecialchars($current_school_year); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($section_counts)): ?>
                            <canvas id="classDistributionChart" height="250"></canvas>
                        <?php else: ?>
                            <p class="text-muted">No class data available for <?php echo htmlspecialchars($current_school_year); ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Grade Overview -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Average Grade (<?php echo htmlspecialchars($current_school_year); ?>)</h5>
                        <div class="d-flex gap-2">
                            <select id="sectionSelect" class="form-select form-select-sm" style="width: auto;">
                                <option value="all">All Classes</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary quarter-toggle active" data-quarter="all">All Quarters</button>
                                <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="1">Q1</button>
                                <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="2">Q2</button>
                                <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="3">Q3</button>
                                <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="4">Q4</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sections)): ?>
                            <canvas id="gradeOverviewChart" height="250"></canvas>
                        <?php else: ?>
                            <p class="text-muted">No grade data available for <?php echo htmlspecialchars($current_school_year); ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Students with Honors -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark">
                        <h5 class="mb-0">Students with Honors (<?php echo htmlspecialchars($current_school_year); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($honors_students['With Highest Honors']) || !empty($honors_students['With High Honors']) || !empty($honors_students['With Honors'])): ?>
                            <div class="row">
                                <!-- With Highest Honors -->
                                <div class="col-md-4">
                                    <h6 class="text-center text-danger mb-3">
                                        <i class="fas fa-crown"></i> With Highest Honors (98-100)
                                    </h6>
                                    <?php if (!empty($honors_students['With Highest Honors'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Student Name</th>
                                                        <th>Section</th>
                                                        <th>Average</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($honors_students['With Highest Honors'] as $student): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($student['section_name']); ?></td>
                                                            <td class="text-center">
                                                                <span class="badge bg-danger"><?php echo number_format($student['overall_average'], 1); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No students</p>
                                    <?php endif; ?>
                                </div>

                                <!-- With High Honors -->
                                <div class="col-md-4">
                                    <h6 class="text-center text-warning mb-3">
                                        <i class="fas fa-star"></i> With High Honors (95-97)
                                    </h6>
                                    <?php if (!empty($honors_students['With High Honors'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Student Name</th>
                                                        <th>Section</th>
                                                        <th>Average</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($honors_students['With High Honors'] as $student): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($student['section_name']); ?></td>
                                                            <td class="text-center">
                                                                <span class="badge bg-warning text-dark"><?php echo number_format($student['overall_average'], 1); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No students</p>
                                    <?php endif; ?>
                                </div>

                                <!-- With Honors -->
                                <div class="col-md-4">
                                    <h6 class="text-center text-success mb-3">
                                        <i class="fas fa-award"></i> With Honors (90-94)
                                    </h6>
                                    <?php if (!empty($honors_students['With Honors'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Student Name</th>
                                                        <th>Section</th>
                                                        <th>Average</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($honors_students['With Honors'] as $student): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($student['section_name']); ?></td>
                                                            <td class="text-center">
                                                                <span class="badge bg-success"><?php echo number_format($student['overall_average'], 1); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No students</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No students with honors for <?php echo htmlspecialchars($current_school_year); ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Upcoming Events -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark">
                        <h5 class="mb-0">Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcoming_events)): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="border-start border-3 border-secondary ps-3 mb-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <small class="text-muted d-block">
                                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php echo strlen($event['description']) > 50 ? substr($event['description'], 0, 50) . '...' : $event['description']; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No upcoming events.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Announcements -->
                <div class="card">
                    <div class="card-header bg-light text-dark">
                        <h5 class="mb-0">Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_announcements)): ?>
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <div class="mb-3 pb-2 border-bottom announcement-link" 
                                     data-bs-toggle="modal" 
                                     data-bs-target="#announcementModal"
                                     data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                     data-date="<?php echo date('M j, Y', strtotime($announcement['date'])); ?>"
                                     data-content="<?php echo htmlspecialchars($announcement['content']); ?>">
                                    <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <small class="text-muted d-block">
                                        <?php echo date('M j, Y', strtotime($announcement['date'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <?php echo strlen($announcement['content']) > 60 ? substr($announcement['content'], 0, 60) . '...' : $announcement['content']; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No recent announcements.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announcementModalLabel">Announcement Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4 id="modalAnnouncementTitle" class="mb-3"></h4>
                    <p class="text-muted mb-4" id="modalAnnouncementDate"></p>
                    <div id="modalAnnouncementContent" class="border-top pt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
        // Class Distribution Chart
        <?php if (!empty($section_counts)): ?>
        const classCtx = document.getElementById('classDistributionChart').getContext('2d');
        const classChart = new Chart(classCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_keys($section_counts)) . "'"; ?>],
                datasets: [{
                    label: 'Male',
                    data: [<?php echo implode(',', array_column($section_counts, 'male')); ?>],
                    backgroundColor: '#3498db'
                }, {
                    label: 'Female',
                    data: [<?php echo implode(',', array_column($section_counts, 'female')); ?>],
                    backgroundColor: '#e74c3c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
        <?php endif; ?>

        // Grade Overview Chart - MODIFIED to handle section and quarter filtering
        <?php if (!empty($sections)): ?>
        // Prepare data structure for JavaScript
        const gradeData = <?php echo json_encode($quarters_data_by_section); ?>;
        
        const gradeCtx = document.getElementById('gradeOverviewChart').getContext('2d');
        let gradeChart = new Chart(gradeCtx, {
            type: 'bar',
            data: getGradeChartData('all', 'all'),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Average Grade %'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Subjects'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.raw}%`;
                            }
                        }
                    }
                }
            }
        });

        // Function to get chart data based on section and quarter
        function getGradeChartData(section, quarter) {
            let subjects = [];
            let datasets = [];
            
            // Define colors for each quarter
            const quarterColors = {
                1: 'rgba(54, 162, 235, 0.7)',
                2: 'rgba(75, 192, 192, 0.7)',
                3: 'rgba(255, 159, 64, 0.7)',
                4: 'rgba(153, 102, 255, 0.7)'
            };

            const quarterNames = {
                1: 'Quarter 1',
                2: 'Quarter 2', 
                3: 'Quarter 3',
                4: 'Quarter 4'
            };

            if (section === 'all') {
                // Aggregate data from all sections
                const allSubjects = new Set();
                const quarterData = {1: {}, 2: {}, 3: {}, 4: {}};

                // Collect all subjects and aggregate grades
                Object.values(gradeData).forEach(sectionData => {
                    sectionData.subjects.forEach(subject => {
                        allSubjects.add(subject);
                        [1, 2, 3, 4].forEach(q => {
                            if (sectionData.quarters[q][subject]) {
                                if (!quarterData[q][subject]) {
                                    quarterData[q][subject] = [];
                                }
                                quarterData[q][subject].push(parseFloat(sectionData.quarters[q][subject]));
                            }
                        });
                    });
                });

                subjects = Array.from(allSubjects).sort();
                
                if (quarter === 'all') {
                    // Show all quarters
                    [1, 2, 3, 4].forEach(q => {
                        const data = subjects.map(subject => {
                            if (quarterData[q][subject]) {
                                const avg = quarterData[q][subject].reduce((a, b) => a + b, 0) / quarterData[q][subject].length;
                                return Math.round(avg * 10) / 10;
                            }
                            return null;
                        });
                        
                        datasets.push({
                            label: quarterNames[q],
                            data: data,
                            backgroundColor: quarterColors[q],
                            borderColor: quarterColors[q].replace('0.7', '1'),
                            borderWidth: 1
                        });
                    });
                } else {
                    // Show specific quarter
                    const data = subjects.map(subject => {
                        if (quarterData[quarter][subject]) {
                            const avg = quarterData[quarter][subject].reduce((a, b) => a + b, 0) / quarterData[quarter][subject].length;
                            return Math.round(avg * 10) / 10;
                        }
                        return null;
                    });
                    
                    datasets.push({
                        label: quarterNames[quarter],
                        data: data,
                        backgroundColor: quarterColors[quarter],
                        borderColor: quarterColors[quarter].replace('0.7', '1'),
                        borderWidth: 1
                    });
                }
            } else {
                // Specific section
                const sectionData = gradeData[section];
                subjects = sectionData.subjects.sort();
                
                if (quarter === 'all') {
                    // Show all quarters for specific section
                    [1, 2, 3, 4].forEach(q => {
                        const data = subjects.map(subject => sectionData.quarters[q][subject] || null);
                        datasets.push({
                            label: `${quarterNames[q]} - ${section}`,
                            data: data,
                            backgroundColor: quarterColors[q],
                            borderColor: quarterColors[q].replace('0.7', '1'),
                            borderWidth: 1
                        });
                    });
                } else {
                    // Show specific quarter for specific section
                    const data = subjects.map(subject => sectionData.quarters[quarter][subject] || null);
                    datasets.push({
                        label: `${quarterNames[quarter]} - ${section}`,
                        data: data,
                        backgroundColor: quarterColors[quarter],
                        borderColor: quarterColors[quarter].replace('0.7', '1'),
                        borderWidth: 1
                    });
                }
            }

            return {
                labels: subjects,
                datasets: datasets
            };
        }

        // Section dropdown change handler
        document.getElementById('sectionSelect').addEventListener('change', function() {
            const section = this.value;
            const quarter = document.querySelector('.quarter-toggle.active').getAttribute('data-quarter');
            updateGradeChart(section, quarter);
        });

        // Quarter toggle functionality
        document.querySelectorAll('.quarter-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const quarter = this.getAttribute('data-quarter');
                
                // Update active button
                document.querySelectorAll('.quarter-toggle').forEach(btn => {
                    btn.classList.remove('active', 'btn-primary');
                    btn.classList.add('btn-outline-primary');
                });
                this.classList.remove('btn-outline-primary');
                this.classList.add('active', 'btn-primary');
                
                const section = document.getElementById('sectionSelect').value;
                updateGradeChart(section, quarter);
            });
        });

        function updateGradeChart(section, quarter) {
            const newData = getGradeChartData(section, quarter);
            gradeChart.data.labels = newData.labels;
            gradeChart.data.datasets = newData.datasets;
            gradeChart.update();
        }
        <?php endif; ?>

        // Announcement Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const announcementModal = document.getElementById('announcementModal');
            if (announcementModal) {
                announcementModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const title = button.getAttribute('data-title');
                    const date = button.getAttribute('data-date');
                    const content = button.getAttribute('data-content');
                    
                    document.getElementById('modalAnnouncementTitle').textContent = title;
                    document.getElementById('modalAnnouncementDate').textContent = date;
                    document.getElementById('modalAnnouncementContent').textContent = content;
                });
            }
        });
    </script>
</body>
</html>