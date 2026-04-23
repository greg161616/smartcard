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

        /* General styling */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 24px;
        }
        
        .card-header {
            background: transparent !important;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            color: #1a1f2e !important;
        }
        .card-header h5 {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }

        /* Quick Stat Cards */
        .stat-card {
            border-radius: 16px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 28px rgba(0,0,0,0.12);
        }
        .stat-card .card-body {
            padding: 1.5rem 1.5rem 1.2rem;
            position: relative;
            z-index: 2;
        }
        .stat-icon-bg {
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 6.5rem;
            color: rgba(255,255,255,0.15);
            z-index: 1;
            transform: rotate(-10deg);
        }
        
        .stat-title {
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 0;
        }
        .stat-subtext {
            font-size: 0.85rem;
            opacity: 0.95;
            margin-top: 12px;
            background: rgba(0,0,0,0.18);
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        /* Stat Colors */
        .stat-primary { background: linear-gradient(135deg, #3a7bd5 0%, #3a6073 100%); }
        .stat-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-warning { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); color: #fff;}

        /* Other elements */
        .quarter-toggle {
            cursor: pointer;
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .quarter-toggle.active {
            background-color: #1a1f2e;
            color: white;
            border-color: #1a1f2e;
        }
        
        .announcement-link {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            border-radius: 12px;
            padding: 12px;
            transition: background 0.2s;
            margin-bottom: 12px !important;
            border-bottom: none !important;
            background: #f8f9fa;
        }
        .announcement-link:hover {
            background: #f1f3f5;
        }
        
        .event-item {
            border-left: 4px solid #3a7bd5;
            background: #f8f9fa;
            border-radius: 0 12px 12px 0;
            padding: 12px 16px;
            margin-bottom: 12px;
        }
        
        /* Table enhancements */
        .table {
            vertical-align: middle;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../navs/teacherNav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-end mb-4">
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="school_year" class="fw-bold mb-0 text-dark">School Year:</label>
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

        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card stat-primary">
                    <i class="bi bi-people-fill stat-icon-bg"></i>
                    <div class="card-body">
                        <div class="stat-title">Total Students</div>
                        <div class="stat-value"><?php echo $total_students; ?></div>
                        <div class="stat-subtext">
                            <i class="bi bi-gender-male"></i> <?php echo $male_count; ?> &nbsp;|&nbsp; 
                            <i class="bi bi-gender-female"></i> <?php echo $female_count; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card stat-success">
                    <i class="bi bi-calendar-check-fill stat-icon-bg"></i>
                    <div class="card-body">
                        <div class="stat-title">Today's Attendance</div>
                        <?php if ($attendance_taken): ?>
                            <div class="stat-value"><?php echo $present_today + $excused_today; ?><span style="font-size: 1.5rem; opacity: 0.8;">/<?php echo $total_attendance_today; ?></span></div>
                            <div class="stat-subtext">
                                Present: <?php echo $present_today; ?> | Absent: <?php echo $absent_today; ?>
                            </div>
                        <?php else: ?>
                            <div class="stat-value" style="font-size: 2rem;">No Record</div>
                            <div class="stat-subtext">No attendance recorded today</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stat-card stat-warning">
                    <i class="bi bi-journal-bookmark-fill stat-icon-bg"></i>
                    <div class="card-body">
                        <div class="stat-title">My Classes</div>
                        <div class="stat-value"><?php echo count($assigned_sections); ?></div>
                        <div class="stat-subtext">Total assigned sections</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Student Distribution by Class -->
                <div class="card mb-4">
                    <div class="card-header">
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
            </div>
            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Upcoming Events -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcoming_events)): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="event-item">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <small class="text-primary fw-semibold d-block mb-1">
                                        <i class="bi bi-calendar-event"></i> <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
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
            </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
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
                </div>
                <div class="col-md-4">
                <!-- Recent Announcements -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_announcements)): ?>
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <div class="announcement-link d-block" 
                                     data-bs-toggle="modal" 
                                     data-bs-target="#announcementModal"
                                     data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                     data-date="<?php echo date('M j, Y', strtotime($announcement['date'])); ?>"
                                     data-content="<?php echo htmlspecialchars($announcement['content']); ?>">
                                    <h6 class="mb-1 text-dark fw-bold"><i class="bi bi-megaphone-fill text-warning me-2"></i><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <small class="text-muted d-block mb-1">
                                        <i class="bi bi-clock me-1"></i><?php echo date('M j, Y', strtotime($announcement['date'])); ?>
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

                <!-- Students with Honors -->
                <div class="card mb-4">
                    <div class="card-header">
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
// Class Distribution Chart - Stacked Bar Graph with Patterns
<?php if (!empty($section_counts)): ?>
const classCtx = document.getElementById('classDistributionChart').getContext('2d');

// Prepare labels and data arrays from PHP
const classLabels = [<?php echo "'" . implode("','", array_keys($section_counts)) . "'"; ?>];
const maleData = [<?php 
    $male_data = [];
    foreach ($section_counts as $section) {
        $male_data[] = $section['male'];
    }
    echo implode(',', $male_data); 
?>];
const femaleData = [<?php 
    $female_data = [];
    foreach ($section_counts as $section) {
        $female_data[] = $section['female'];
    }
    echo implode(',', $female_data); 
?>];

// Compute totals and y-axis max (10% padding, rounded up to nearest 10)
const totals = classLabels.map((_, i) => (parseFloat(maleData[i] || 0) + parseFloat(femaleData[i] || 0)));
const maxTotal = totals.length ? Math.max(...totals) : 0;
const yMax = maxTotal <= 0 ? 10 : Math.ceil((maxTotal * 1.1) / 10) * 10;

// Compute initial bar thickness based on container width and number of labels
function computeBarThickness() {
    const containerWidth = classCtx.canvas.parentElement.clientWidth || classCtx.canvas.width || 600;
    const labelsCount = Math.max(1, classLabels.length);
    const categoryWidth = containerWidth / labelsCount;
    // 70% of category width, capped at 50
    return Math.min(50, Math.max(8, Math.floor(categoryWidth * 0.7)));
}

let initialBarThickness = computeBarThickness();

const classChart = new Chart(classCtx, {
    type: 'bar',
    data: {
        labels: classLabels,
        datasets: [
            {
                label: 'Male',
                data: maleData,
                backgroundColor: 'rgba(52, 152, 219, 0.8)', // Semi-transparent blue
                borderColor: 'rgba(41, 128, 185, 1)',
                borderWidth: 1,
                barThickness: initialBarThickness,
                maxBarThickness: 70,
                stack: 'Stack 1'
            }, 
            {
                label: 'Female',
                data: femaleData,
                backgroundColor: 'rgba(255, 182, 193, 0.8)',
                borderColor: 'rgba(255, 182, 193, 1)',
                borderWidth: 1,
                barThickness: initialBarThickness,
                maxBarThickness: 70,
                stack: 'Stack 1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#3498db',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += context.raw + ' student';
                        if (context.raw !== 1) label += 's';
                        
                        // Calculate percentage
                        const total = classChart.data.datasets.reduce((sum, dataset) => {
                            return sum + dataset.data[context.dataIndex];
                        }, 0);
                        
                        const percentage = ((context.raw / total) * 100).toFixed(1);
                        return [`${label} (${percentage}%)`];
                    },
                    footer: function(tooltipItems) {
                        if (tooltipItems.length > 0) {
                            const sectionIndex = tooltipItems[0].dataIndex;
                            const total = classChart.data.datasets.reduce((sum, dataset) => {
                                return sum + dataset.data[sectionIndex];
                            }, 0);
                            return `Total: ${total} student${total !== 1 ? 's' : ''}`;
                        }
                        return '';
                    }
                }
            },
            legend: {
                position: 'top',
                labels: {
                    color: '#2c3e50',
                    font: {
                        size: 12,
                        weight: '500'
                    },
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 15
                }
            }
        },
            scales: {
            x: {
                stacked: true,
                grid: {
                    display: false,
                    drawBorder: false
                },
                // Make bars fuller but constrained by maxBarThickness
                categoryPercentage: 0.85,
                barPercentage: 0.9,
                ticks: {
                    color: '#2c3e50',
                    font: {
                        size: 11,
                        weight: '500'
                    },
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                max: yMax,
                ticks: {
                    color: '#7f8c8d',
                    stepSize: 20, // 20, 40, 60, 80, 100, 120
                    font: {
                        size: 11
                    },
                    callback: function(value) {
                        return value;
                    }
                },
                title: {
                    display: true,
                    text: 'Number of Students',
                    color: '#2c3e50',
                    font: {
                        size: 12,
                        weight: '600'
                    },
                    padding: {top: 10, bottom: 20}
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false,
                    tickLength: 0
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        }
    }
});
<?php endif; ?>
// Recompute bar thickness and y-max on window resize to keep chart readable
function adjustClassChart() {
    if (typeof classChart === 'undefined' || !classChart) return;
    const newBar = computeBarThickness();
    classChart.data.datasets.forEach(ds => {
        ds.barThickness = newBar;
        ds.maxBarThickness = 50;
    });
    // recompute yMax in case data changed or container size affects perception
    const totals = classChart.data.labels.map((_, i) => {
        const m = parseFloat(classChart.data.datasets[0].data[i] || 0);
        const f = parseFloat(classChart.data.datasets[1].data[i] || 0);
        return m + f;
    });
    const maxTotal = totals.length ? Math.max(...totals) : 0;
    const newYMax = maxTotal <= 0 ? 10 : Math.ceil((maxTotal * 1.1) / 10) * 10;
    if (classChart.options && classChart.options.scales && classChart.options.scales.y) {
        classChart.options.scales.y.max = newYMax;
    }
    classChart.update();
}

window.addEventListener('resize', function(){
    // debounce
    clearTimeout(window._classChartResizeTimeout);
    window._classChartResizeTimeout = setTimeout(adjustClassChart, 120);
});
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
</div><!-- /.page-content -->
</body>
</html>