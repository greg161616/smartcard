<?php
session_start();
include '../config.php';
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit();
}

// Get available school years from existing tables
$school_years = [];
$sql = "SELECT DISTINCT school_year FROM grades 
        UNION 
        SELECT DISTINCT SchoolYear as school_year FROM section_enrollment 
        ORDER BY school_year DESC";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $school_years[] = $row['school_year'];
    }
} else {
    // If no school years in database, use current and previous year as default
    $current_year = date('Y');
    $school_years = [
        $current_year . '-' . ($current_year + 1),
        ($current_year - 1) . '-' . $current_year
    ];
}

// Get selected filters with defaults
$selected_sy = isset($_GET['school_year']) ? $_GET['school_year'] : ($school_years[0] ?? '');
$selected_grade = isset($_GET['grade_level']) ? $_GET['grade_level'] : 'all';
$selected_section = isset($_GET['section_filter']) && $_GET['section_filter'] != 'all' ? $_GET['section_filter'] : '';
$selected_quarter = isset($_GET['quarter_filter']) && $_GET['quarter_filter'] != 'all' ? $_GET['quarter_filter'] : '';

// Fetch statistics data with school year filtering
$students_count = 0;
$teachers_count = 0;
$sections_count = 0;
$male_students = 0;
$female_students = 0;

// Get total students for selected school year
$sql = "SELECT COUNT(DISTINCT se.StudentID) as count 
        FROM section_enrollment se 
        WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "' 
        AND se.status = 'active'";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $students_count = $row['count'];
}

// Get male students for selected school year
$sql = "SELECT COUNT(DISTINCT se.StudentID) as count 
        FROM section_enrollment se 
        JOIN student st ON se.StudentID = st.StudentID 
        WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "' 
        AND se.status = 'active' 
        AND st.Sex = 'Male'";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $male_students = $row['count'];
}

// Get female students for selected school year
$sql = "SELECT COUNT(DISTINCT se.StudentID) as count 
        FROM section_enrollment se 
        JOIN student st ON se.StudentID = st.StudentID 
        WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "' 
        AND se.status = 'active' 
        AND st.Sex = 'Female'";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $female_students = $row['count'];
}

// Get total teachers (not tied to school year)
$sql = "SELECT COUNT(*) as count FROM teacher";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $teachers_count = $row['count'];
}

// Get total sections with active enrollments in selected school year
$sql = "SELECT COUNT(DISTINCT s.SectionID) as count 
        FROM section s 
        JOIN section_enrollment se ON s.SectionID = se.SectionID 
        WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "' 
        AND se.status = 'active'";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $sections_count = $row['count'];
}

// Get students per section with gender breakdown for selected school year
$students_per_section = [];
$sql = "SELECT s.SectionID, s.SectionName, s.GradeLevel, 
               COUNT(CASE WHEN st.Sex = 'Male' THEN 1 END) as male_count,
               COUNT(CASE WHEN st.Sex = 'Female' THEN 1 END) as female_count,
               COUNT(se.StudentID) as total_count
        FROM section s 
        LEFT JOIN section_enrollment se ON s.SectionID = se.SectionID 
            AND se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "' 
            AND se.status = 'active'
        LEFT JOIN student st ON se.StudentID = st.StudentID
        GROUP BY s.SectionID 
        ORDER BY s.GradeLevel, s.SectionName";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students_per_section[] = $row;
    }
}

// Get top 5 students by average grade for selected school year
$top_students = [];
$sql = "SELECT st.StudentID, st.FirstName, st.LastName, sec.GradeLevel, sec.SectionName, 
               AVG(g.Final) as avg_grade 
        FROM student st 
        JOIN grades g ON st.StudentID = g.student_id 
        JOIN section_enrollment se ON st.StudentID = se.StudentID 
        JOIN section sec ON se.SectionID = sec.SectionID 
        WHERE g.Final IS NOT NULL 
        AND g.school_year = '" . mysqli_real_escape_string($conn, $selected_sy) . "'
        AND se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "'
        GROUP BY st.StudentID 
        ORDER BY avg_grade DESC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $top_students[] = $row;
    }
}

// Get today's activities from system logs (not tied to school year)
$todays_activities = [];
$sql = "SELECT sl.action, sl.created_at, sl.details,
         COALESCE(CONCAT(t.fName, ' ', t.lName), a.FullName, CONCAT(s.FirstName, ' ', s.LastName), u.Email, sl.user_id) AS user_display
  FROM system_logs sl
  LEFT JOIN teacher t ON t.UserID = sl.user_id
  LEFT JOIN admin a ON a.UserID = sl.user_id
  LEFT JOIN student s ON s.userID = sl.user_id
  LEFT JOIN user u ON u.UserID = sl.user_id
  WHERE DATE(sl.created_at) = CURDATE()
  ORDER BY sl.created_at DESC
  LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $todays_activities[] = $row;
    }
}

// Get upcoming events (next 7 days) - not tied to school year
$upcoming_events = [];
$sql = "SELECT title, event_date, description, category 
        FROM events 
        WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY event_date ASC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $upcoming_events[] = $row;
    }
}

// Get average grade per subject per quarter with filters
$avg_grade_per_subject = [];

// Build the SQL query dynamically based on filters
$sql_parts = [];
$quarters_to_query = [];

if ($selected_quarter && $selected_quarter != 'all') {
    $quarters_to_query = [$selected_quarter];
} else {
    $quarters_to_query = [1, 2, 3, 4];
}

foreach ($quarters_to_query as $quarter) {
    $quarter_field = 'Q' . $quarter;
    
    $sql = "SELECT sub.SubjectName, $quarter AS quarter, AVG(g.$quarter_field) AS avg_grade
            FROM grades g
            JOIN subject sub ON g.subject = sub.SubjectID
            JOIN section_enrollment se ON g.student_id = se.StudentID
            JOIN section sec ON se.SectionID = sec.SectionID
            WHERE g.$quarter_field IS NOT NULL
            AND g.school_year = '" . mysqli_real_escape_string($conn, $selected_sy) . "'
            AND se.SchoolYear = '" . mysqli_real_escape_string($conn, $selected_sy) . "'";
    
    // Apply grade level filter
    if ($selected_grade != 'all') {
        $sql .= " AND sec.GradeLevel = " . intval($selected_grade);
    }
    
    // Apply section filter
    if ($selected_section) {
        $sql .= " AND sec.SectionID = '" . mysqli_real_escape_string($conn, $selected_section) . "'";
    }
    
    $sql .= " GROUP BY sub.SubjectID, sub.SubjectName";
    
    $sql_parts[] = $sql;
}

// Combine all quarter queries
if (!empty($sql_parts)) {
    $final_sql = implode(" UNION ALL ", $sql_parts) . " ORDER BY quarter, avg_grade DESC";
    
    $result = mysqli_query($conn, $final_sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $avg_grade_per_subject[] = $row;
        }
    }
}

// Get unique grade levels for filter dropdown
$grade_levels = [];
$sql = "SELECT DISTINCT GradeLevel FROM section ORDER BY GradeLevel";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $grade_levels[] = $row['GradeLevel'];
    }
}

// Get total teachers who uploaded grades per quarter and those who did not
$teachers_uploaded_per_quarter = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$teachers_not_uploaded_per_quarter = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$sql = "SELECT quarter, COUNT(DISTINCT uploadedby) as teacher_count FROM (
    SELECT 1 as quarter, uploadedby FROM grades WHERE Q1 IS NOT NULL AND school_year = '" . mysqli_real_escape_string($conn, $selected_sy) . "' AND uploadedby IS NOT NULL
    UNION ALL
    SELECT 2 as quarter, uploadedby FROM grades WHERE Q2 IS NOT NULL AND school_year = '" . mysqli_real_escape_string($conn, $selected_sy) . "' AND uploadedby IS NOT NULL
    UNION ALL
    SELECT 3 as quarter, uploadedby FROM grades WHERE Q3 IS NOT NULL AND school_year = '" . mysqli_real_escape_string($conn, $selected_sy) . "' AND uploadedby IS NOT NULL
    UNION ALL
    SELECT 4 as quarter, uploadedby FROM grades WHERE Q4 IS NOT NULL AND school_year = '" . mysqli_real_escape_string($conn, $selected_sy) . "' AND uploadedby IS NOT NULL
) t GROUP BY quarter ORDER BY quarter";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers_uploaded_per_quarter[$row['quarter']] = $row['teacher_count'];
    }
}
foreach ([1,2,3,4] as $q) {
    $teachers_not_uploaded_per_quarter[$q] = $teachers_count - $teachers_uploaded_per_quarter[$q];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BAHAHIS | Dashboard</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .students-card {
            border-left-color: #4e73df;
        }
        .teacher-card {
            border-left-color: #1cc88a;
        }
        .section-card {
            border-left-color: #36b9cc;
        }
        .activities-card {
            border-left-color: #f6c23e;
        }
        .events-card {
            border-left-color: #e74a3b;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #5a5c69;
            margin-bottom: 10px;
        }
        .gender-breakdown {
            font-size: 0.8rem;
            color: #858796;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            height: 100%;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .activities-list, .events-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .activity-item, .event-item {
            padding: 10px 0;
            border-bottom: 1px solid #e3e6f0;
        }
        .activity-item:last-child, .event-item:last-child {
            border-bottom: none;
        }
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 15px;
        }
        .chart-wrapper {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .badge-quarter {
            font-size: 0.7em;
        }
        .filter-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .school-year-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
        }
        .grade-filter-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<?php include '../navs/adminNav.php'; ?>
 
<div class="container-fluid dashboard-container mt-4">
    <!-- Welcome Header with School Year Badge -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow position-relative">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="card-title mb-1">
                                <i class="bi bi-speedometer2 me-2"></i>Welcome to BAHAHIS Dashboard
                            </h4>
                            <p class="card-text mb-0">Overview of school statistics and performance metrics</p>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check me-1"></i>School Year: <?php echo $selected_sy; ?>
                            <span class="badge bg-light text-secondary fs-6"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Filters - School Year Only -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="filter-form">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="school_year" class="form-label fw-bold">School Year</label>
                        <select class="form-select" id="school_year" name="school_year" onchange="this.form.submit()">
                            <?php foreach ($school_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $selected_sy == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="d-grid">
                            <a href="?school_year=<?php echo $selected_sy; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card students-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-number"><?php echo $students_count; ?></div>
                        <div class="gender-breakdown">
                            <span class="badge bg-primary me-1"><i class="bi bi-gender-male"></i> <?php echo $male_students; ?></span>
                            <span class="badge bg-danger"><i class="bi bi-gender-female"></i> <?php echo $female_students; ?></span>
                        </div>
                        <small class="text-muted">SY: <?php echo $selected_sy; ?></small>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card teacher-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Teachers</div>
                        <div class="stat-number"><?php echo $teachers_count; ?></div>
                        <div class="gender-breakdown">Active faculty members</div>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-person-badge-fill text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card section-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Active Sections</div>
                        <div class="stat-number"><?php echo $sections_count; ?></div>
                        <div class="gender-breakdown">SY: <?php echo $selected_sy; ?></div>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-layers-fill text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card activities-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label">Total Logs Today</div>
                        <div class="stat-number"><?php echo count($todays_activities); ?></div>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="bi bi-activity text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Events Row -->
    <div class="row mb-4">
        <!-- Students per Class Chart -->
        <div class="col-xl-8 col-lg-8 mb-4">
            <div class="chart-container h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="chart-title">
                        <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Students Distribution by Class
                        <small class="text-muted ms-2">(SY: <?php echo $selected_sy; ?>)</small>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="studentsPerClassChart"></canvas>
                </div>
            </div>
        </div>
        <!-- Upcoming Events -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="stat-card events-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="stat-label">Upcoming Events</div>
                    <span class="badge bg-danger">Next 7 days</span>
                </div>
                <div class="events-list">
                    <?php if (!empty($upcoming_events)): ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="event-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo $event['title']; ?></div>
                                        <small class="text-muted"><?php echo $event['category']; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold"><?php echo date('M j', strtotime($event['event_date'])); ?></div>
                                        <small class="text-muted"><?php echo date('D', strtotime($event['event_date'])); ?></small>
                                    </div>
                                </div>
                                <?php if (!empty($event['description'])): ?>
                                    <small class="text-muted d-block mt-1"><?php echo substr($event['description'], 0, 50); ?>...</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted text-center py-4">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                            No upcoming events
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-sm btn-outline-danger">View All Events</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Teachers Upload Pie Charts Row -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="chart-container h-100">
                <div class="chart-title mb-3">
                    <i class="bi bi-pie-chart-fill me-2 text-success"></i>Teachers Upload Status Per Quarter
                    <small class="text-muted ms-2">(SY: <?php echo $selected_sy; ?>)</small>
                </div>
                <div class="row">
                    <?php foreach ([1,2,3,4] as $q): ?>
                    <div class="col-md-3 mb-3">
                        <div class="chart-wrapper">
                            <canvas id="teachersUploadPieQ<?php echo $q; ?>"></canvas>
                        </div>
                        <div class="text-center">
                            <span class="badge bg-success">Uploaded: <?php echo $teachers_uploaded_per_quarter[$q]; ?></span>
                            <span class="badge bg-secondary">Not Uploaded: <?php echo $teachers_not_uploaded_per_quarter[$q]; ?></span>
                            <div class="fw-bold">Quarter <?php echo $q; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Average Grade and Top Students Row -->
    <div class="row mb-4">
        <!-- Average Grade per Subject per Quarter -->
        <div class="col-xl-12 col-lg-7 mb-4">
            <div class="chart-container h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="chart-title">
                        <i class="bi bi-graph-up me-2 text-success"></i>Average Grade per Subject
                        <small class="text-muted ms-2">(SY: <?php echo $selected_sy; ?>)</small>
                    </div>
                </div>

                <!-- Filter Form for Average Grade Section -->
                <div class="grade-filter-form mb-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="school_year" value="<?php echo $selected_sy; ?>">
                        
                        <div class="col-md-4">
                            <label for="grade_level" class="form-label fw-bold">Grade Level</label>
                            <select class="form-select" id="grade_level" name="grade_level">
                                <option value="all" <?php echo $selected_grade == 'all' ? 'selected' : ''; ?>>All Grades</option>
                                <?php foreach ($grade_levels as $level): ?>
                                    <option value="<?php echo $level; ?>" <?php echo $selected_grade == $level ? 'selected' : ''; ?>>
                                        Grade <?php echo $level; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="section_filter" class="form-label fw-bold">Section</label>
                            <select class="form-select" id="section_filter" name="section_filter">
                                <option value="all" <?php echo ($_GET['section_filter'] ?? 'all') == 'all' ? 'selected' : ''; ?>>All Sections</option>
                                <?php foreach ($students_per_section as $section): ?>
                                    <?php if ($selected_grade == 'all' || $section['GradeLevel'] == $selected_grade): ?>
                                        <option value="<?php echo $section['SectionID']; ?>" 
                                            <?php echo ($_GET['section_filter'] ?? '') == $section['SectionID'] ? 'selected' : ''; ?>>
                                            <?php echo $section['SectionName']; ?> (G<?php echo $section['GradeLevel']; ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="quarter_filter" class="form-label fw-bold">Quarter</label>
                            <select class="form-select" id="quarter_filter" name="quarter_filter">
                                <option value="all" <?php echo ($_GET['quarter_filter'] ?? 'all') == 'all' ? 'selected' : ''; ?>>All Quarters</option>
                                <option value="1" <?php echo ($_GET['quarter_filter'] ?? '') == '1' ? 'selected' : ''; ?>>Quarter 1</option>
                                <option value="2" <?php echo ($_GET['quarter_filter'] ?? '') == '2' ? 'selected' : ''; ?>>Quarter 2</option>
                                <option value="3" <?php echo ($_GET['quarter_filter'] ?? '') == '3' ? 'selected' : ''; ?>>Quarter 3</option>
                                <option value="4" <?php echo ($_GET['quarter_filter'] ?? '') == '4' ? 'selected' : ''; ?>>Quarter 4</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Apply Filters
                            </button>
                        </div>
                        <div class="col-md-6">
                            <a href="?school_year=<?php echo $selected_sy; ?>" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise"></i> Reset Filters
                            </a>
                        </div>
                    </form>
                </div>

                <?php if (!empty($avg_grade_per_subject)): ?>
                    <div class="chart-wrapper">
                        <canvas id="avgGradePerSubjectChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>
                        No grade data available for selected filters
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Dynamic section filtering based on grade level
    document.addEventListener('DOMContentLoaded', function() {
        const gradeLevelSelect = document.getElementById('grade_level');
        const sectionSelect = document.getElementById('section_filter');
        
        if (gradeLevelSelect && sectionSelect) {
            gradeLevelSelect.addEventListener('change', function() {
                const selectedGrade = this.value;
                const currentSection = sectionSelect.value;
                
                // Reset section options
                sectionSelect.innerHTML = '<option value="all">All Sections</option>';
                
                // Get all available sections from PHP data
                const allSections = <?php echo json_encode($students_per_section); ?>;
                
                // Filter sections by selected grade level
                const filteredSections = selectedGrade === 'all' 
                    ? allSections 
                    : allSections.filter(section => section.GradeLevel == selectedGrade);
                
                // Populate section dropdown
                filteredSections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.SectionID;
                    option.textContent = `${section.SectionName} (G${section.GradeLevel})`;
                    option.selected = (currentSection == section.SectionID);
                    sectionSelect.appendChild(option);
                });
            });
            
            // Trigger change on page load to set initial state
            gradeLevelSelect.dispatchEvent(new Event('change'));
        }
    });

    // Teachers Upload Pie Charts
    <?php foreach ([1,2,3,4] as $q): ?>
    new Chart(document.getElementById('teachersUploadPieQ<?php echo $q; ?>').getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['Uploaded', 'Not Uploaded'],
            datasets: [{
                data: [<?php echo $teachers_uploaded_per_quarter[$q]; ?>, <?php echo $teachers_not_uploaded_per_quarter[$q]; ?>],
                backgroundColor: [
                    'rgba(28, 200, 138, 0.8)',
                    'rgba(231, 74, 59, 0.8)'
                ],
                borderColor: [
                    'rgba(28, 200, 138, 1)',
                    'rgba(231, 74, 59, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed}`;
                        }
                    }
                }
            }
        }
    });
    <?php endforeach; ?>

    // Students Per Class Chart (Stacked Bar Chart)
    const studentsPerClassCtx = document.getElementById('studentsPerClassChart').getContext('2d');
    const sectionLabels = <?php echo json_encode(array_map(function($item) { 
        return $item['SectionName'] . ' (G' . $item['GradeLevel'] . ')'; 
    }, $students_per_section)); ?>;
    
    const maleData = <?php echo json_encode(array_column($students_per_section, 'male_count')); ?>;
    const femaleData = <?php echo json_encode(array_column($students_per_section, 'female_count')); ?>;
    
    new Chart(studentsPerClassCtx, {
        type: 'bar',
        data: {
            labels: sectionLabels,
            datasets: [
                {
                    label: 'Male Students',
                    data: maleData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Female Students',
                    data: femaleData,
                    backgroundColor: 'rgba(255, 182, 193, 0.8)',
                    borderColor: 'rgba(255, 182, 193, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 5
                    },
                    grid: {
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Number of Students'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        afterBody: function(context) {
                            const datasetIndex = context[0].datasetIndex;
                            const dataIndex = context[0].dataIndex;
                            const maleCount = maleData[dataIndex];
                            const femaleCount = femaleData[dataIndex];
                            const total = maleCount + femaleCount;
                            return `Total: ${total} students`;
                        }
                    }
                }
            }
        }
    });
    
    <?php if (!empty($avg_grade_per_subject)): ?>
    // Average Grade Per Subject Chart (Grouped by Quarter)
    const avgGradePerSubjectCtx = document.getElementById('avgGradePerSubjectChart').getContext('2d');
    
    // Group data by quarter
    const quarters = [...new Set(<?php echo json_encode(array_column($avg_grade_per_subject, 'quarter')); ?>)].sort();
    const subjects = [...new Set(<?php echo json_encode(array_column($avg_grade_per_subject, 'SubjectName')); ?>)].sort();
    
    // Create dataset for each quarter
    const quarterDatasets = quarters.map(quarter => {
        const quarterData = subjects.map(subject => {
            const record = <?php echo json_encode($avg_grade_per_subject); ?>.find(
                item => item.quarter == quarter && item.SubjectName === subject
            );
            return record ? parseFloat(record.avg_grade).toFixed(2) : null;
        });
        
        const colors = [
            'rgba(28, 200, 138, 0.8)',
            'rgba(54, 185, 204, 0.8)',
            'rgba(246, 194, 62, 0.8)',
            'rgba(231, 74, 59, 0.8)'
        ];
        
        return {
            label: `Quarter ${quarter}`,
            data: quarterData,
            backgroundColor: colors[quarter - 1] || 'rgba(126, 87, 194, 0.8)',
            borderColor: colors[quarter - 1]?.replace('0.8', '1') || 'rgba(126, 87, 194, 1)',
            borderWidth: 1
        };
    });
    
    new Chart(avgGradePerSubjectCtx, {
        type: 'bar',
        data: {
            labels: subjects,
            datasets: quarterDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Average Grade'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: quarters.length > 1,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y}`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
</script>
</body>
</html>