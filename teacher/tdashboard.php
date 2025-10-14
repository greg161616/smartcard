<?php
session_start();
include __DIR__ . '/../config.php';

// Check if teacher is logged in    
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
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
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_name = $teacher['fName'] . ' ' . $teacher['mName'] . ' ' . $teacher['lName'];

// Get available school years from section_enrollment
$school_years_sql = "
    SELECT DISTINCT SchoolYear 
    FROM section_enrollment 
    WHERE SectionID IN (
        SELECT DISTINCT sec.SectionID 
        FROM section sec 
        LEFT JOIN sched sch ON sec.SectionID = sch.SectionID 
        LEFT JOIN subject sub ON sch.SubjectID = sub.SubjectID 
        WHERE sub.TeacherID = ?
    )
    ORDER BY SchoolYear DESC
";
$school_years_stmt = $conn->prepare($school_years_sql);
$school_years_stmt->bind_param("i", $teacher_id);
$school_years_stmt->execute();
$school_years_result = $school_years_stmt->get_result();

$available_school_years = [];
while ($row = $school_years_result->fetch_assoc()) {
    $available_school_years[] = $row['SchoolYear'];
}

// Set current school year (default to current year if available, or most recent)
$current_school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';
if (empty($current_school_year) && !empty($available_school_years)) {
    $current_school_year = $available_school_years[0];
} elseif (empty($current_school_year)) {
    // Default to current academic year if no data exists
    $current_year = date('Y');
    $current_school_year = $current_year . '-' . ($current_year + 1);
}

// Get total students count by gender for selected school year
$students_sql = "
    SELECT 
        s.Sex,
        COUNT(DISTINCT s.StudentID) as student_count,
        sec.SectionID,
        CONCAT(sec.GradeLevel, '-', sec.SectionName) as section_name
    FROM student s
    JOIN section_enrollment se ON s.StudentID = se.StudentID AND se.status = 'active'
    JOIN section sec ON sec.SectionID = se.SectionID
    WHERE sec.SectionID IN (
        SELECT DISTINCT sec2.SectionID 
        FROM section sec2 
        LEFT JOIN sched sch ON sec2.SectionID = sch.SectionID 
        LEFT JOIN subject sub ON sch.SubjectID = sub.SubjectID 
        WHERE sub.TeacherID = ? 
    )
    AND se.SchoolYear = ?
    GROUP BY s.Sex, sec.SectionID
";

$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("is", $teacher_id, $current_school_year);
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
    if (!isset($section_counts[$row['section_name']])) {
        $section_counts[$row['section_name']] = ['male' => 0, 'female' => 0, 'total' => 0];
    }
    if ($row['Sex'] == 'Male') {
        $section_counts[$row['section_name']]['male'] += $row['student_count'];
    } else {
        $section_counts[$row['section_name']]['female'] += $row['student_count'];
    }
    $section_counts[$row['section_name']]['total'] += $row['student_count'];
}

// FIXED ATTENDANCE QUERY - Match database enum values (lowercase)
$today = date('Y-m-d');
$attendance_sql = "
    SELECT 
        a.Status,
        COUNT(*) as count
    FROM attendance a
    WHERE a.TeacherID = ? AND a.Date = ?
    GROUP BY a.Status
";

$attendance_stmt = $conn->prepare($attendance_sql);
$attendance_stmt->bind_param("is", $teacher_id, $today);
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
        // Match the exact enum values from your database
        if ($row['Status'] == 'present') {
            $present_today = $row['count'];
        } elseif ($row['Status'] == 'absent') {
            $absent_today = $row['count'];
        } elseif ($row['Status'] == 'excused') {
            $excused_today = $row['count'];
        }
    }
    $total_attendance_today = $present_today + $absent_today + $excused_today;
} else {
    // No attendance records for today
    $attendance_taken = false;
}

// MODIFIED: Get grade overview for ALL quarters for selected school year
$grades_sql = "
    SELECT 
        sub.SubjectName,
        gd.quarter,
        AVG(gd.quarterly_grade) as avg_grade,
        COUNT(DISTINCT gd.studentID) as student_count
    FROM grades_details gd
    JOIN subject sub ON gd.subjectID = sub.SubjectID
    JOIN section_enrollment se ON gd.studentID = se.StudentID AND se.status = 'active'
    WHERE gd.teacherID = ? AND gd.school_year = ?
    GROUP BY sub.SubjectID, sub.SubjectName, gd.quarter
    ORDER BY sub.SubjectName ASC, gd.quarter ASC
";

$grades_stmt = $conn->prepare($grades_sql);
$grades_stmt->bind_param("is", $teacher_id, $current_school_year);
$grades_stmt->execute();
$grades_result = $grades_stmt->get_result();

// MODIFIED: Restructure data to show all quarters
$grade_overview = [];
$subjects = [];
$quarters_data = [1 => [], 2 => [], 3 => [], 4 => []];

while ($row = $grades_result->fetch_assoc()) {
    $subject = $row['SubjectName'];
    $quarter = $row['quarter'];
    $avg_grade = number_format($row['avg_grade'], 1);
    
    // Store unique subjects
    if (!in_array($subject, $subjects)) {
        $subjects[] = $subject;
    }
    
    // Store grade data by quarter
    $quarters_data[$quarter][$subject] = $avg_grade;
    
    // Also store in the original format for compatibility
    $grade_overview[] = $row;
}

// MODIFIED: Prepare data for chart with all quarters
$grade_labels = $subjects;
$quarter_datasets = [];

// Define colors for each quarter
$quarter_colors = [
    1 => 'rgba(54, 162, 235, 0.7)',
    2 => 'rgba(75, 192, 192, 0.7)',
    3 => 'rgba(255, 159, 64, 0.7)',
    4 => 'rgba(153, 102, 255, 0.7)'
];

$quarter_names = [
    1 => 'Quarter 1',
    2 => 'Quarter 2', 
    3 => 'Quarter 3',
    4 => 'Quarter 4'
];

foreach ([1, 2, 3, 4] as $quarter) {
    $data = [];
    foreach ($subjects as $subject) {
        $data[] = isset($quarters_data[$quarter][$subject]) ? $quarters_data[$quarter][$subject] : null;
    }
    
    $quarter_datasets[] = [
        'label' => $quarter_names[$quarter],
        'data' => $data,
        'backgroundColor' => $quarter_colors[$quarter],
        'borderColor' => str_replace('0.7', '1', $quarter_colors[$quarter]),
        'borderWidth' => 1
    ];
}

// Get students with 10+ absences for current school year
$absent_students_sql = "
    SELECT 
        s.StudentID,
        CONCAT(s.FirstName, ' ', s.MiddleName, ' ', s.LastName) as student_name,
        CONCAT(sec.GradeLevel, '-', sec.SectionName) as section_name,
        COUNT(a.AttendanceID) as absence_count
    FROM student s
    JOIN section_enrollment se ON s.StudentID = se.StudentID AND se.status = 'active'
    JOIN section sec ON sec.SectionID = se.SectionID
    LEFT JOIN attendance a ON s.StudentID = a.StudentID AND a.Status = 'absent' 
        AND a.Date BETWEEN ? AND ?
    WHERE sec.SectionID IN (
        SELECT DISTINCT sec2.SectionID 
        FROM section sec2 
        LEFT JOIN sched sch ON sec2.SectionID = sch.SectionID 
        LEFT JOIN subject sub ON sch.SubjectID = sub.SubjectID 
        WHERE sub.TeacherID = ? 
    )
    AND se.SchoolYear = ?
    GROUP BY s.StudentID, s.FirstName, s.MiddleName, s.LastName, sec.GradeLevel, sec.SectionName
    HAVING absence_count >= 10
    ORDER BY absence_count DESC, section_name, student_name
";

// Calculate date range for current school year (assuming school year runs from June to March)
$current_year = date('Y');
$school_year_start = $current_year . '-06-01';
$school_year_end = ($current_year + 1) . '-03-31';

$absent_stmt = $conn->prepare($absent_students_sql);
$absent_stmt->bind_param("ssis", $school_year_start, $school_year_end, $teacher_id, $current_school_year);
$absent_stmt->execute();
$absent_result = $absent_stmt->get_result();

$absent_students = [];
while ($row = $absent_result->fetch_assoc()) {
    $absent_students[] = $row;
}

// Get upcoming events (next 7 days) - not filtered by school year as they're current
$events_sql = "
    SELECT title, event_date, description 
    FROM events 
    WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY event_date ASC 
    LIMIT 5
";

$events_result = $conn->query($events_sql);
$upcoming_events = [];
while ($row = $events_result->fetch_assoc()) {
    $upcoming_events[] = $row;
}

// Get recent announcements - not filtered by school year as they're current
$announcements_sql = "
    SELECT title, date, content 
    FROM announcement 
    ORDER BY date DESC 
    LIMIT 3
";

$announcements_result = $conn->query($announcements_sql);
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
        .school-year-filter {
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 0.5rem 1rem;
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
    </style>
</head>
<body>
    <?php include __DIR__ . '/../navs/teacherNav.php'; ?>
    
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                    <p class="lead mb-0">Teacher Dashboard - <?php echo date('F j, Y'); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="school-year-filter">
                        <form method="GET" class="d-flex align-items-center justify-content-end gap-2">
                            <label for="school_year" class="text-white mb-0">School Year:</label>
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
    </div>

    <div class="container">
        <!-- School Year Indicator -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info py-2">
                    <small class="fw-bold">Currently viewing data for School Year: <span class="badge bg-primary"><?php echo htmlspecialchars($current_school_year); ?></span></small>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
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
            
            <div class="col-xl-3 col-md-6 mb-4">
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
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">MY CLASSES</h6>
                                <h3 class="fw-bold"><?php echo count($section_counts); ?></h3>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Active sections</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">WITH LARGE ABSENCES</h6>
                                <h3 class="fw-bold"><?php echo count($absent_students); ?></h3>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Need attention</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Grade Overview -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Grade Overview (All Quarters - <?php echo htmlspecialchars($current_school_year); ?>)</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary quarter-toggle active" data-quarter="all">All Quarters</button>
                            <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="1">Q1</button>
                            <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="2">Q2</button>
                            <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="3">Q3</button>
                            <button type="button" class="btn btn-outline-primary quarter-toggle" data-quarter="4">Q4</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($subjects)): ?>
                            <canvas id="gradeOverviewChart" height="250"></canvas>
                        <?php else: ?>
                            <p class="text-muted">No grade data available for <?php echo htmlspecialchars($current_school_year); ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>

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

                <!-- Students with 10+ Absences -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark">
                        <h5 class="mb-0">Students with Major Absences (<?php echo htmlspecialchars($current_school_year); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($absent_students)): ?>
                            <div class="absent-student-list">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Section</th>
                                                <th class="text-center">Absences</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($absent_students as $student): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['section_name']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger"><?php echo $student['absence_count']; ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No students with 10 or more absences for <?php echo htmlspecialchars($current_school_year); ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Today's Attendance Chart -->
                <div class="card mb-4">
                    <div class="card-header bg-light text-dark">
                        <h5 class="mb-0">Today's Attendance (<?php echo date('M j, Y'); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($attendance_taken): ?>
                            <div class="attendance-chart">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Total Records: <?php echo $total_attendance_today; ?> students
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="no-record">
                                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                    <p class="mb-0">No attendance recorded for today</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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
                                <div class="mb-3 pb-2 border-bottom">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
        // Attendance Chart (only if attendance was taken)
        <?php if ($attendance_taken): ?>
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Excused', 'Absent'],
                datasets: [{
                    data: [<?php echo $present_today; ?>, <?php echo $excused_today; ?>, <?php echo $absent_today; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                    borderWidth: 1,
                    borderColor: '#dee2e6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Grade Overview Bar Chart - MODIFIED to show all quarters
        <?php if (!empty($subjects)): ?>
        const gradeCtx = document.getElementById('gradeOverviewChart').getContext('2d');
        let gradeChart = new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($grade_labels); ?>,
                datasets: <?php echo json_encode($quarter_datasets); ?>
            },
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
                
                // Filter chart data
                if (quarter === 'all') {
                    // Show all datasets
                    gradeChart.data.datasets.forEach((dataset, index) => {
                        gradeChart.setDatasetVisibility(index, true);
                    });
                } else {
                    // Show only selected quarter
                    gradeChart.data.datasets.forEach((dataset, index) => {
                        if (dataset.label === `Quarter ${quarter}`) {
                            gradeChart.setDatasetVisibility(index, true);
                        } else {
                            gradeChart.setDatasetVisibility(index, false);
                        }
                    });
                }
                
                gradeChart.update();
            });
        });
        <?php endif; ?>

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
    </script>
</body>
</html>