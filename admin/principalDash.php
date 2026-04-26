<?php
session_start();
include '../config.php';
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit();
}

// ── Create school_details table if it doesn't exist ──
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `school_details` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `school_year_id` VARCHAR(50) DEFAULT NULL,
    `school_name` VARCHAR(255) NOT NULL,
    `school_address` TEXT DEFAULT NULL,
    `sub_office` VARCHAR(150) DEFAULT NULL,
    `division` VARCHAR(150) DEFAULT NULL,
    `region` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Migration: ensure new columns exist if table was created with old schema
$check_cols = mysqli_query($conn, "SHOW COLUMNS FROM `school_details` LIKE 'school_year_id'");
if (mysqli_num_rows($check_cols) == 0) {
    mysqli_query($conn, "ALTER TABLE `school_details` 
        ADD COLUMN `school_year_id` VARCHAR(50) DEFAULT NULL AFTER `id`,
        ADD COLUMN `school_address` TEXT DEFAULT NULL AFTER `school_name`,
        ADD COLUMN `sub_office` VARCHAR(150) DEFAULT NULL AFTER `school_address`
    ");
}

// ── Handle school_details save ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_school_details'])) {
    $sd_school_year_id = trim($_POST['school_year_id'] ?? '');
    $sd_school_name    = trim($_POST['school_name'] ?? '');
    $sd_school_address = trim($_POST['school_address'] ?? '');
    $sd_sub_office     = trim($_POST['sub_office'] ?? '');
    $sd_division       = trim($_POST['division'] ?? '');
    $sd_region         = trim($_POST['region'] ?? '');

    // Check if a row already exists
    $chk = mysqli_query($conn, "SELECT id FROM school_details LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $row_id = mysqli_fetch_assoc($chk)['id'];
        $upd = $conn->prepare("UPDATE school_details SET school_year_id=?, school_name=?, school_address=?, sub_office=?, division=?, region=? WHERE id=?");
        $upd->bind_param("ssssssi", $sd_school_year_id, $sd_school_name, $sd_school_address, $sd_sub_office, $sd_division, $sd_region, $row_id);
        $upd->execute();
        $upd->close();
    } else {
        $ins = $conn->prepare("INSERT INTO school_details (school_year_id, school_name, school_address, sub_office, division, region) VALUES (?,?,?,?,?,?)");
        $ins->bind_param("ssssss", $sd_school_year_id, $sd_school_name, $sd_school_address, $sd_sub_office, $sd_division, $sd_region);
        $ins->execute();
        $ins->close();
    }
    // Redirect to clear POST
    header('Location: principalDash.php?school_setup=1');
    exit();
}

// ── Check if school_details is empty ──
$school_details_empty = true;
$school_info = null;
$sd_check = mysqli_query($conn, "SELECT * FROM school_details LIMIT 1");
if ($sd_check && mysqli_num_rows($sd_check) > 0) {
    $school_details_empty = false;
    $school_info = mysqli_fetch_assoc($sd_check);
}

// AJAX Handler
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    
    // Get filters from AJAX request
    $selected_sy = isset($_GET['school_year']) ? $_GET['school_year'] : '';
    $selected_grade = isset($_GET['grade_level']) ? $_GET['grade_level'] : 'all';
    $selected_section = isset($_GET['section_filter']) ? $_GET['section_filter'] : 'all';
    $selected_quarter = isset($_GET['quarter_filter']) ? $_GET['quarter_filter'] : 'all';
    $selected_upload_quarter = isset($_GET['upload_quarter']) ? intval($_GET['upload_quarter']) : 1;
    
    // Validate quarter values
    if (!in_array($selected_upload_quarter, [1, 2, 3, 4])) {
        $selected_upload_quarter = 1;
    }
    
    // Collect all data in an array
    $response = [];
    
    // Get statistics
    $response['statistics'] = getStatistics($conn, $selected_sy);
    $response['students_per_section'] = getStudentsPerSection($conn, $selected_sy);
    $response['avg_grade_per_subject'] = getAvgGradePerSubject($conn, $selected_sy, $selected_grade, $selected_section, $selected_quarter);
    $response['teachers_upload_stats'] = getTeacherUploadStats($conn, $selected_sy, $selected_upload_quarter);
    $response['subjects_status'] = getSubjectsStatus($conn, $selected_sy);
    $response['grade_levels'] = getGradeLevels($conn);
    $response['sections'] = getSections($conn, $selected_grade);
    
    echo json_encode($response);
    exit();
}

// Function to get statistics
function getStatistics($conn, $school_year) {
    $stats = [
        'students_count' => 0,
        'teachers_count' => 0,
        'sections_count' => 0,
        'male_students' => 0,
        'female_students' => 0
    ];
    
    // Total students
    $sql = "SELECT COUNT(DISTINCT se.StudentID) as count 
            FROM section_enrollment se 
            WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $school_year) . "' 
            AND se.status = 'active'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['students_count'] = $row['count'];
    }
    
    // Male students
    $sql = "SELECT COUNT(DISTINCT se.StudentID) as count 
            FROM section_enrollment se 
            JOIN student st ON se.StudentID = st.StudentID 
            WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $school_year) . "' 
            AND se.status = 'active' 
            AND st.Sex = 'Male'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['male_students'] = $row['count'];
    }
    
    // Female students
    $sql = "SELECT COUNT(DISTINCT se.StudentID) as count 
            FROM section_enrollment se 
            JOIN student st ON se.StudentID = st.StudentID 
            WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $school_year) . "' 
            AND se.status = 'active' 
            AND st.Sex = 'Female'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['female_students'] = $row['count'];
    }
    
    // Total teachers
    $sql = "SELECT COUNT(*) as count FROM teacher WHERE status = 'Active'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['teachers_count'] = $row['count'];
    }
    
    // Total sections
    $sql = "SELECT COUNT(DISTINCT s.SectionID) as count 
            FROM section s 
            JOIN section_enrollment se ON s.SectionID = se.SectionID 
            WHERE se.SchoolYear = '" . mysqli_real_escape_string($conn, $school_year) . "' 
            AND se.status = 'active'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['sections_count'] = $row['count'];
    }
    
    return $stats;
}

// Function to get students per section
function getStudentsPerSection($conn, $school_year) {
    $sections = [];
    $sql = "SELECT s.SectionID, s.SectionName, s.GradeLevel, 
                   COUNT(CASE WHEN st.Sex = 'Male' THEN 1 END) as male_count,
                   COUNT(CASE WHEN st.Sex = 'Female' THEN 1 END) as female_count,
                   COUNT(se.StudentID) as total_count
            FROM section s 
            LEFT JOIN section_enrollment se ON s.SectionID = se.SectionID 
                AND se.SchoolYear = '" . mysqli_real_escape_string($conn, $school_year) . "' 
                AND se.status = 'active'
            LEFT JOIN student st ON se.StudentID = st.StudentID
            GROUP BY s.SectionID 
            ORDER BY s.GradeLevel, s.SectionName";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sections[] = $row;
        }
    }
    return $sections;
}

// Function to get average grade per subject
function getAvgGradePerSubject($conn, $school_year, $grade_level, $section_id, $quarter) {
    $avg_grades = [];
    $quarters_to_query = [];
    
    if ($quarter && $quarter != 'all') {
        $quarters_to_query = [$quarter];
    } else {
        $quarters_to_query = [1, 2, 3, 4];
    }
    
    $sql_parts = [];
    foreach ($quarters_to_query as $q) {
        $quarter_field = 'Q' . $q;
        
        $sql = "SELECT sub.SubjectName, $q AS quarter, AVG(g.$quarter_field) AS avg_grade
                FROM grades g
                JOIN subject sub ON g.subject = sub.SubjectID
                JOIN section_enrollment se ON g.student_id = se.StudentID
                JOIN section sec ON se.SectionID = sec.SectionID
                WHERE g.$quarter_field IS NOT NULL
                AND g.school_year = '" . mysqli_real_escape_string($conn, $school_year) . "'
                AND se.SchoolYear = '" . mysqli_real_escape_string($conn, $school_year) . "'";
        
        if ($grade_level != 'all') {
            $sql .= " AND sec.GradeLevel = " . intval($grade_level);
        }
        
        if ($section_id != 'all') {
            $sql .= " AND sec.SectionID = '" . mysqli_real_escape_string($conn, $section_id) . "'";
        }
        
        $sql .= " GROUP BY sub.SubjectID, sub.SubjectName";
        $sql_parts[] = $sql;
    }
    
    if (!empty($sql_parts)) {
        $final_sql = implode(" UNION ALL ", $sql_parts) . " ORDER BY quarter, avg_grade DESC";
        $result = mysqli_query($conn, $final_sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $avg_grades[] = $row;
            }
        }
    }
    
    return $avg_grades;
}

// Function to get teacher upload stats
function getTeacherUploadStats($conn, $school_year, $quarter) {
    $stats = [
        'uploaded' => 0,
        'not_uploaded' => 0,
        'teachers_uploaded' => [],
        'teachers_not_uploaded' => []
    ];
    
    // Get all active teachers
    $sql_teachers = "SELECT t.TeacherID, t.fName, t.lName, u.Email 
                     FROM teacher t 
                     JOIN user u ON t.UserID = u.UserID 
                     WHERE t.status = 'Active'";
    $result_teachers = mysqli_query($conn, $sql_teachers);
    $all_teachers = [];
    if ($result_teachers) {
        while ($row = mysqli_fetch_assoc($result_teachers)) {
            $all_teachers[$row['TeacherID']] = $row;
        }
    }
    
    // Check for grade uploads
    $quarter_field = 'Q' . $quarter;
    $sql = "SELECT DISTINCT uploadedby 
            FROM grades 
            WHERE $quarter_field IS NOT NULL 
            AND school_year = '" . mysqli_real_escape_string($conn, $school_year) . "'
            AND uploadedby IS NOT NULL";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $teacher_id = $row['uploadedby'];
            if (isset($all_teachers[$teacher_id])) {
                $stats['uploaded']++;
                $stats['teachers_uploaded'][$teacher_id] = $all_teachers[$teacher_id];
            }
        }
    }
    
    // Get total teachers count
    $sql = "SELECT COUNT(*) as count FROM teacher WHERE status = 'Active'";
    $result = mysqli_query($conn, $sql);
    $total_teachers = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_teachers = $row['count'];
    }
    
    $stats['not_uploaded'] = $total_teachers - $stats['uploaded'];
    
    // Populate not uploaded teachers list
    foreach ($all_teachers as $teacher_id => $teacher) {
        if (!isset($stats['teachers_uploaded'][$teacher_id])) {
            $stats['teachers_not_uploaded'][$teacher_id] = $teacher;
        }
    }
    
    return $stats;
}

// Function to get subjects status
function getSubjectsStatus($conn, $school_year) {
    $subjects_with_grades = [];
    $subjects_without_grades = [];
    
    $sql = "SELECT s.SubjectID, s.SubjectName, 
            COUNT(DISTINCT g.grade_id) as grade_count,
            COUNT(DISTINCT t.TeacherID) as assigned_teachers
            FROM subject s
            LEFT JOIN grades g ON s.SubjectID = g.subject AND g.school_year = '" . mysqli_real_escape_string($conn, $school_year) . "'
            LEFT JOIN assigned_subject a ON s.SubjectID = a.subject_id AND a.school_year = '" . mysqli_real_escape_string($conn, $school_year) . "'
            LEFT JOIN teacher t ON a.teacher_id = t.TeacherID
            GROUP BY s.SubjectID, s.SubjectName
            ORDER BY s.SubjectName";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['grade_count'] > 0) {
                $subjects_with_grades[] = $row;
            } else {
                $subjects_without_grades[] = $row;
            }
        }
    }
    
    return [
        'with_grades' => $subjects_with_grades,
        'without_grades' => $subjects_without_grades
    ];
}

// Function to get grade levels
function getGradeLevels($conn) {
    $levels = [];
    $sql = "SELECT DISTINCT GradeLevel FROM section ORDER BY GradeLevel";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $levels[] = $row['GradeLevel'];
        }
    }
    return $levels;
}

// Function to get sections based on grade level
function getSections($conn, $grade_level) {
    $sections = [];
    $sql = "SELECT SectionID, SectionName, GradeLevel 
            FROM section 
            WHERE " . ($grade_level != 'all' ? "GradeLevel = " . intval($grade_level) : "1=1") . "
            ORDER BY GradeLevel, SectionName";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sections[] = $row;
        }
    }
    return $sections;
}

// Get initial data for page load
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
    $current_year = date('Y');
    $school_years = [
        $current_year . '-' . ($current_year + 1),
        ($current_year - 1) . '-' . $current_year
    ];
}

// Get initial filters
$selected_sy = isset($_GET['school_year']) ? $_GET['school_year'] : ($school_years[0] ?? '');
$selected_grade = isset($_GET['grade_level']) ? $_GET['grade_level'] : 'all';
$selected_section = isset($_GET['section_filter']) ? $_GET['section_filter'] : 'all';
$selected_quarter = isset($_GET['quarter_filter']) ? $_GET['quarter_filter'] : 'all';
$selected_upload_quarter = isset($_GET['upload_quarter']) ? intval($_GET['upload_quarter']) : 1;

// Validate quarter values
if (!in_array($selected_upload_quarter, [1, 2, 3, 4])) {
    $selected_upload_quarter = 1;
}

// Get initial data
$statistics = getStatistics($conn, $selected_sy);
$students_per_section = getStudentsPerSection($conn, $selected_sy);
$avg_grade_per_subject = getAvgGradePerSubject($conn, $selected_sy, $selected_grade, $selected_section, $selected_quarter);
$teachers_upload_stats = getTeacherUploadStats($conn, $selected_sy, $selected_upload_quarter);
$subjects_status = getSubjectsStatus($conn, $selected_sy);
$grade_levels = getGradeLevels($conn);
$sections = getSections($conn, $selected_grade);

// Get other data (non-AJAX)
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

$recent_uploads = [];
$sql_logs = "SELECT sl.*, 
             COALESCE(CONCAT(t.fName, ' ', t.lName), a.FullName, u.Email) AS user_name
             FROM system_logs sl
             LEFT JOIN teacher t ON t.TeacherID = sl.user_id
             LEFT JOIN admin a ON a.UserID = sl.user_id
             LEFT JOIN user u ON u.UserID = sl.user_id
             WHERE (sl.action LIKE '%Grade Upload%' OR sl.action LIKE '%grade%')
             AND (sl.action LIKE '%Completed%' OR sl.action LIKE '%Success%' OR sl.action LIKE '%successful%')
             AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY sl.created_at DESC
             LIMIT 10";
$result_logs = mysqli_query($conn, $sql_logs);
if ($result_logs) {
    while ($row = mysqli_fetch_assoc($result_logs)) {
        $recent_uploads[] = $row;
    }
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
        .upload-card {
            border-left-color: #6f42c1;
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
            position: relative;
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
            width: 100%;
            min-height: 200px;
        }
        .students-chart-wrapper {
            height: 350px;
        }
        .grades-chart-wrapper {
            height: 400px;
        }
        .pie-chart-wrapper {
            height: 300px;
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
        .grade-filter-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .teacher-list {
            max-height: 150px;
            overflow-y: auto;
            font-size: 0.85rem;
        }
        .upload-status-badge {
            font-size: 0.75rem;
        }
        .progress {
            height: 8px;
        }
        .upload-quarter-selector {
            max-width: 200px;
        }
        .combined-upload-section {
            min-height: 400px;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 10px;
        }
        
        /* Chart tooltip improvements */
        .chartjs-tooltip {
            background: rgba(0, 0, 0, 0.8) !important;
            border-radius: 4px !important;
            padding: 10px !important;
            color: white !important;
            border: none !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important;
            max-width: 300px !important;
        }
        
        /* Responsive chart adjustments */
        @media (max-width: 1400px) {
            .students-chart-wrapper {
                height: 300px;
            }
            .grades-chart-wrapper {
                height: 350px;
            }
            .pie-chart-wrapper {
                height: 250px;
            }
        }
        
        @media (max-width: 1200px) {
            .students-chart-wrapper {
                height: 280px;
            }
            .grades-chart-wrapper {
                height: 320px;
            }
            .pie-chart-wrapper {
                height: 220px;
            }
        }
        
        @media (max-width: 992px) {
            .students-chart-wrapper {
                height: 250px;
            }
            .grades-chart-wrapper {
                height: 300px;
            }
            .pie-chart-wrapper {
                height: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .students-chart-wrapper {
                height: 220px;
            }
            .grades-chart-wrapper {
                height: 270px;
            }
            .pie-chart-wrapper {
                height: 180px;
            }
            .chart-title {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .students-chart-wrapper {
                height: 200px;
            }
            .grades-chart-wrapper {
                height: 250px;
            }
            .pie-chart-wrapper {
                height: 170px;
            }
            .stat-number {
                font-size: 1.5rem;
            }
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
                                <i class="bi bi-speedometer2 me-2"></i>Welcome to <?php echo htmlspecialchars($school_info['school_name'] ?? 'SmartCard'); ?> Dashboard
                            </h4>
                            <p class="card-text mb-0">Overview of school statistics and performance metrics for <?php echo htmlspecialchars($school_info['division'] ?? 'System'); ?></p>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check me-1"></i>School Year: <span id="school-year-display"><?php echo $selected_sy; ?></span>
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
                <form id="school-year-form" class="row g-3 align-items-end">
                    <div class="col-md-8 text-end"><label for="school_year" class="form-label fw-bold">School Year</label></div>
                    <div class="col-md-2">
                        <select class="form-select" id="school_year" name="school_year">
                            <?php foreach ($school_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $selected_sy == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid">
                            <button type="button" id="reset-filters" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset Filters
                            </button>
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
                        <div class="stat-number" id="students-count"><?php echo $statistics['students_count']; ?></div>
                        <div class="gender-breakdown">
                            <span class="badge bg-primary me-1"><i class="bi bi-gender-male"></i> <span id="male-students"><?php echo $statistics['male_students']; ?></span></span>
                            <span class="badge bg-danger"><i class="bi bi-gender-female"></i> <span id="female-students"><?php echo $statistics['female_students']; ?></span></span>
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
                        <div class="stat-number" id="teachers-count"><?php echo $statistics['teachers_count']; ?></div>
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
                        <div class="stat-number" id="sections-count"><?php echo $statistics['sections_count']; ?></div>
                        <div class="gender-breakdown">SY: <?php echo $selected_sy; ?></div>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-layers-fill text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card upload-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Subjects with Grades</div>
                        <div class="stat-number" id="subjects-with-grades"><?php echo count($subjects_status['with_grades']); ?>/<?php echo count($subjects_status['with_grades']) + count($subjects_status['without_grades']); ?></div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-purple" role="progressbar" 
                                 id="subjects-progress-bar"
                                 style="width: <?php echo (count($subjects_status['with_grades']) / (count($subjects_status['with_grades']) + count($subjects_status['without_grades']))) * 100; ?>%">
                            </div>
                        </div>
                        <small class="text-muted">Grade completion rate</small>
                    </div>
                    <div class="bg-purple bg-opacity-10 p-3 rounded">
                        <i class="bi bi-clipboard-data text-purple fs-4"></i>
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
                        <small class="text-muted ms-2">(SY: <span id="chart-school-year"><?php echo $selected_sy; ?></span>)</small>
                    </div>
                </div>
                <div class="chart-wrapper students-chart-wrapper">
                    <canvas id="studentsPerClassChart"></canvas>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>Hover over bars to see detailed breakdown
                    </small>
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

    <!-- Combined Upload Statistics and Recent Activities -->
    <div class="row mb-4">
        <!-- Teacher Upload Statistics -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="chart-container combined-upload-section">
                <div class="chart-title">
                    <i class="bi bi-cloud-upload-fill me-2 text-purple"></i>Teacher Grade Upload Status
                    <small class="text-muted ms-2">(SY: <?php echo $selected_sy; ?>)</small>
                </div>
                
                <!-- Quarter Selector -->
                <div class="mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label for="upload_quarter" class="form-label fw-bold">Select Quarter:</label>
                            <select class="form-select upload-quarter-selector" id="upload_quarter" name="upload_quarter">
                                <option value="1" <?php echo $selected_upload_quarter == 1 ? 'selected' : ''; ?>>Quarter 1</option>
                                <option value="2" <?php echo $selected_upload_quarter == 2 ? 'selected' : ''; ?>>Quarter 2</option>
                                <option value="3" <?php echo $selected_upload_quarter == 3 ? 'selected' : ''; ?>>Quarter 3</option>
                                <option value="4" <?php echo $selected_upload_quarter == 4 ? 'selected' : ''; ?>>Quarter 4</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-wrapper pie-chart-wrapper">
                            <canvas id="teachersUploadPie"></canvas>
                        </div>
                        <div class="text-center mt-2">
                            <span class="badge bg-success upload-status-badge" id="uploaded-count">Uploaded: <?php echo $teachers_upload_stats['uploaded']; ?></span>
                            <span class="badge bg-secondary upload-status-badge" id="not-uploaded-count">Not Uploaded: <?php echo $teachers_upload_stats['not_uploaded']; ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- Teacher Lists -->
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1 fw-bold">Teachers who uploaded (Q<?php echo $selected_upload_quarter; ?>):</small>
                            <div class="teacher-list" id="uploaded-teachers-list">
                                <?php if (!empty($teachers_upload_stats['teachers_uploaded'])): ?>
                                    <?php foreach (array_slice($teachers_upload_stats['teachers_uploaded'], 0, 5) as $teacher): ?>
                                        <div class="text-success mb-1">✓ <?php echo $teacher['fName'] . ' ' . $teacher['lName']; ?></div>
                                    <?php endforeach; ?>
                                    <?php if (count($teachers_upload_stats['teachers_uploaded']) > 5): ?>
                                        <small class="text-muted">+<?php echo count($teachers_upload_stats['teachers_uploaded']) - 5; ?> more</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-muted">No teachers uploaded for this quarter</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <small class="text-muted d-block mb-1 fw-bold">Teachers who haven't uploaded (Q<?php echo $selected_upload_quarter; ?>):</small>
                            <div class="teacher-list" id="not-uploaded-teachers-list">
                                <?php if (!empty($teachers_upload_stats['teachers_not_uploaded'])): ?>
                                    <?php foreach (array_slice($teachers_upload_stats['teachers_not_uploaded'], 0, 5) as $teacher): ?>
                                        <div class="text-danger mb-1">✗ <?php echo $teacher['fName'] . ' ' . $teacher['lName']; ?></div>
                                    <?php endforeach; ?>
                                    <?php if (count($teachers_upload_stats['teachers_not_uploaded']) > 5): ?>
                                        <small class="text-muted">+<?php echo count($teachers_upload_stats['teachers_not_uploaded']) - 5; ?> more</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-success">All teachers have uploaded for this quarter!</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Upload Activities -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="table-container combined-upload-section">
                <div class="chart-title">
                    <i class="bi bi-clock-history me-2 text-warning"></i>Recent Grade Upload Activities
                    <small class="text-muted ms-2">(Last 30 days)</small>
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Teacher</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_uploads)): ?>
                                <?php foreach ($recent_uploads as $upload): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($upload['created_at'])); ?></td>
                                        <td><?php echo $upload['user_name'] ?: 'Unknown User'; ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php echo strpos($upload['action'], 'Completed') !== false ? 'bg-success' : 
                                                       (strpos($upload['action'], 'Failed') !== false ? 'bg-danger' : 'bg-info'); ?>">
                                                <?php echo $upload['action']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                $details = json_decode($upload['details'], true);
                                                if ($details && isset($details['message'])) {
                                                    echo $details['message'];
                                                } else {
                                                    echo $upload['details'] ?: 'No details';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (strpos($upload['action'], 'Completed') !== false): ?>
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                            <?php elseif (strpos($upload['action'], 'Failed') !== false): ?>
                                                <i class="bi bi-x-circle-fill text-danger"></i>
                                            <?php else: ?>
                                                <i class="bi bi-info-circle-fill text-info"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No recent upload activities found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                    <form id="grade-filter-form" class="row g-3 align-items-end">
                        <div class="col-md-3">
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
                        
                        <div class="col-md-3">
                            <label for="section_filter" class="form-label fw-bold">Section</label>
                            <select class="form-select" id="section_filter" name="section_filter">
                                <option value="all" <?php echo $selected_section == 'all' ? 'selected' : ''; ?>>All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['SectionID']; ?>" 
                                        <?php echo $selected_section == $section['SectionID'] ? 'selected' : ''; ?>>
                                        <?php echo $section['SectionName']; ?> (G<?php echo $section['GradeLevel']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="quarter_filter" class="form-label fw-bold">Quarter</label>
                            <select class="form-select" id="quarter_filter" name="quarter_filter">
                                <option value="all" <?php echo $selected_quarter == 'all' ? 'selected' : ''; ?>>All Quarters</option>
                                <option value="1" <?php echo $selected_quarter == '1' ? 'selected' : ''; ?>>Quarter 1</option>
                                <option value="2" <?php echo $selected_quarter == '2' ? 'selected' : ''; ?>>Quarter 2</option>
                                <option value="3" <?php echo $selected_quarter == '3' ? 'selected' : ''; ?>>Quarter 3</option>
                                <option value="4" <?php echo $selected_quarter == '4' ? 'selected' : ''; ?>>Quarter 4</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <button type="button" id="reset-grade-filters" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise"></i> Reset Filters
                            </button>
                        </div>
                    </form>
                </div>

                <div class="chart-wrapper grades-chart-wrapper">
                    <canvas id="avgGradePerSubjectChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading data...</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global chart instances
let studentsPerClassChart = null;
let teachersUploadPie = null;
let avgGradePerSubjectChart = null;

// Debounce function to prevent too many API calls
let debounceTimer;
function debounce(func, delay) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(func, delay);
}

// Initialize with PHP data
document.addEventListener('DOMContentLoaded', function() {
    // Initial data from PHP
    const initialData = {
        statistics: <?php echo json_encode($statistics); ?>,
        students_per_section: <?php echo json_encode($students_per_section); ?>,
        avg_grade_per_subject: <?php echo json_encode($avg_grade_per_subject); ?>,
        teachers_upload_stats: <?php echo json_encode($teachers_upload_stats); ?>,
        subjects_status: <?php echo json_encode($subjects_status); ?>,
        grade_levels: <?php echo json_encode($grade_levels); ?>,
        sections: <?php echo json_encode($sections); ?>
    };
    
    initializeCharts(initialData);
    setupEventListeners();
});

// Setup event listeners for AJAX filtering
function setupEventListeners() {
    // School year filter - automatic update
    document.getElementById('school_year').addEventListener('change', function() {
        debounce(() => updateDashboard(), 300);
    });
    
    // Upload quarter filter - automatic update
    document.getElementById('upload_quarter').addEventListener('change', function() {
        debounce(() => updateDashboard(), 300);
    });
    
    // Grade level filter - automatic update
    document.getElementById('grade_level').addEventListener('change', function() {
        updateSectionDropdownAutomatically();
        debounce(() => updateDashboard(), 300);
    });
    
    // Section filter - automatic update
    document.getElementById('section_filter').addEventListener('change', function() {
        debounce(() => updateDashboard(), 300);
    });
    
    // Quarter filter - automatic update
    document.getElementById('quarter_filter').addEventListener('change', function() {
        debounce(() => updateDashboard(), 300);
    });
    
    // Reset filters
    document.getElementById('reset-filters').addEventListener('click', function() {
        resetFilters();
    });
    
    document.getElementById('reset-grade-filters').addEventListener('click', function() {
        resetGradeFilters();
    });
}

// Update section dropdown automatically when grade level changes
function updateSectionDropdownAutomatically() {
    const gradeLevel = document.getElementById('grade_level').value;
    const sectionSelect = document.getElementById('section_filter');
    
    // Get sections from server for the selected grade level
    fetchSectionsForGradeLevel(gradeLevel).then(sections => {
        // Save current selection
        const currentSection = sectionSelect.value;
        
        // Clear existing options
        sectionSelect.innerHTML = '<option value="all">All Sections</option>';
        
        // Add new options
        sections.forEach(section => {
            const option = document.createElement('option');
            option.value = section.SectionID;
            option.textContent = `${section.SectionName} (G${section.GradeLevel})`;
            
            // Try to keep the same section selected if it exists in the new list
            if (section.SectionID === currentSection) {
                option.selected = true;
            }
            
            sectionSelect.appendChild(option);
        });
    });
}

// Fetch sections for a specific grade level
async function fetchSectionsForGradeLevel(gradeLevel) {
    try {
        const schoolYear = document.getElementById('school_year').value;
        const response = await fetch(`?ajax=1&school_year=${encodeURIComponent(schoolYear)}&grade_level=${encodeURIComponent(gradeLevel)}`);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        return data.sections || [];
    } catch (error) {
        console.error('Error fetching sections:', error);
        return [];
    }
}

// AJAX function to update dashboard
function updateDashboard() {
    showLoading();
    
    // Collect all filter values
    const filters = {
        school_year: document.getElementById('school_year').value,
        grade_level: document.getElementById('grade_level').value,
        section_filter: document.getElementById('section_filter').value,
        quarter_filter: document.getElementById('quarter_filter').value,
        upload_quarter: document.getElementById('upload_quarter').value,
        ajax: 1
    };
    
    // Update URL without page reload
    updateURL(filters);
    
    // AJAX request
    fetch(`?${new URLSearchParams(filters).toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            updateDashboardData(data);
            hideLoading();
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            showError('Error loading data. Please try again.');
        });
}

// Update dashboard with new data
function updateDashboardData(data) {
    try {
        // Update statistics
        document.getElementById('students-count').textContent = data.statistics.students_count || 0;
        document.getElementById('male-students').textContent = data.statistics.male_students || 0;
        document.getElementById('female-students').textContent = data.statistics.female_students || 0;
        document.getElementById('teachers-count').textContent = data.statistics.teachers_count || 0;
        document.getElementById('sections-count').textContent = data.statistics.sections_count || 0;
        
        // Update subjects progress
        const withGrades = data.subjects_status?.with_grades?.length || 0;
        const withoutGrades = data.subjects_status?.without_grades?.length || 0;
        const totalSubjects = withGrades + withoutGrades;
        document.getElementById('subjects-with-grades').textContent = `${withGrades}/${totalSubjects}`;
        
        const percentage = totalSubjects > 0 ? (withGrades / totalSubjects) * 100 : 0;
        document.getElementById('subjects-progress-bar').style.width = `${percentage}%`;
        
        // Update school year displays
        const schoolYear = document.getElementById('school_year').value;
        document.getElementById('school-year-display').textContent = schoolYear;
        document.getElementById('chart-school-year').textContent = schoolYear;
        
        // Update charts
        updateCharts(data);
        
        // Update teacher upload stats
        updateTeacherUploadStats(data.teachers_upload_stats);
        
        // Update section dropdown if grade level changed
        updateSectionsDropdown(data.sections);
    } catch (error) {
        console.error('Error updating dashboard:', error);
        showError('Error updating dashboard data.');
    }
}

// Update charts with new data
function updateCharts(data) {
    // Students per class chart
    updateStudentsChart(data.students_per_section);
    
    // Average grade per subject chart
    updateAvgGradeChart(data.avg_grade_per_subject);
    
    // Teacher upload pie chart
    updateTeacherPieChart(data.teachers_upload_stats);
}

// Update students chart
function updateStudentsChart(sectionsData) {
    const ctx = document.getElementById('studentsPerClassChart').getContext('2d');
    
    // Destroy old chart if exists
    if (studentsPerClassChart) {
        studentsPerClassChart.destroy();
    }
    
    // Check if we have data
    if (!sectionsData || sectionsData.length === 0) {
        // Show message when no data
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.font = '16px Arial';
        ctx.fillStyle = '#6c757d';
        ctx.textAlign = 'center';
        ctx.fillText('No student data available for selected school year', 
                     ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }
    
    // Prepare data
    const sectionLabels = sectionsData.map(item => 
        `${item.SectionName} (G${item.GradeLevel})`
    );
    const maleData = sectionsData.map(item => item.male_count || 0);
    const femaleData = sectionsData.map(item => item.female_count || 0);
    
    // Create new chart
    studentsPerClassChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sectionLabels,
            datasets: [
                {
                    label: 'Male Students',
                    data: maleData,
                    backgroundColor: 'rgba(52, 152, 219, 0.9)',
                    borderColor: 'rgba(41, 128, 185, 1)',
                    borderWidth: 1,
                    barThickness: 16,
                    maxBarThickness: 28,
                    stack: 'Stack 0'
                },
                {
                    label: 'Female Students',
                    data: femaleData,
                    backgroundColor: 'rgba(255, 182, 193, 0.8)',
                    borderColor: 'rgba(255, 182, 193, 1)',
                    borderWidth: 1,
                    barThickness: 16,
                    maxBarThickness: 28,
                    stack: 'Stack 0'
                }
            ]
        },
        options: getStudentsChartOptions()
    });
}

// Update average grade chart
function updateAvgGradeChart(gradeData) {
    const ctx = document.getElementById('avgGradePerSubjectChart').getContext('2d');
    
    if (avgGradePerSubjectChart) {
        avgGradePerSubjectChart.destroy();
    }
    
    if (!gradeData || gradeData.length === 0) {
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.font = '16px Arial';
        ctx.fillStyle = '#6c757d';
        ctx.textAlign = 'center';
        ctx.fillText('No grade data available for selected filters', 
                     ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }
    
    // Group data by quarter
    const quarters = [...new Set(gradeData.map(item => item.quarter))].sort((a, b) => a - b);
    const subjects = [...new Set(gradeData.map(item => item.SubjectName))].sort();
    
    // Create dataset for each quarter
    const quarterDatasets = quarters.map(quarter => {
        const quarterData = subjects.map(subject => {
            const record = gradeData.find(
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
    
    avgGradePerSubjectChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: subjects,
            datasets: quarterDatasets
        },
        options: getAvgGradeChartOptions()
    });
}

// Update teacher pie chart
function updateTeacherPieChart(uploadStats) {
    const ctx = document.getElementById('teachersUploadPie').getContext('2d');
    const quarter = document.getElementById('upload_quarter').value;
    
    if (teachersUploadPie) {
        teachersUploadPie.destroy();
    }
    
    const uploadedCount = uploadStats?.uploaded || 0;
    const notUploadedCount = uploadStats?.not_uploaded || 0;
    
    // Update badges
    document.getElementById('uploaded-count').textContent = `Uploaded: ${uploadedCount}`;
    document.getElementById('not-uploaded-count').textContent = `Not Uploaded: ${notUploadedCount}`;
    
    // Update teacher lists
    updateTeacherLists(uploadStats);
    
    // Create new pie chart
    teachersUploadPie = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Uploaded', 'Not Uploaded'],
            datasets: [{
                data: [uploadedCount, notUploadedCount],
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
        options: getPieChartOptions()
    });
}

// Update teacher lists
function updateTeacherLists(uploadStats) {
    const uploadedList = document.getElementById('uploaded-teachers-list');
    const notUploadedList = document.getElementById('not-uploaded-teachers-list');
    const quarter = document.getElementById('upload_quarter').value;
    
    // Update uploaded teachers list
    uploadedList.innerHTML = '';
    if (uploadStats?.teachers_uploaded && Object.keys(uploadStats.teachers_uploaded).length > 0) {
        const teachers = Object.values(uploadStats.teachers_uploaded);
        teachers.slice(0, 5).forEach(teacher => {
            const div = document.createElement('div');
            div.className = 'text-success mb-1';
            div.innerHTML = `✓ ${teacher.fName} ${teacher.lName}`;
            uploadedList.appendChild(div);
        });
        
        if (teachers.length > 5) {
            const more = document.createElement('small');
            more.className = 'text-muted';
            more.textContent = `+${teachers.length - 5} more`;
            uploadedList.appendChild(more);
        }
    } else {
        uploadedList.innerHTML = '<small class="text-muted">No teachers uploaded for this quarter</small>';
    }
    
    // Update not uploaded teachers list
    notUploadedList.innerHTML = '';
    if (uploadStats?.teachers_not_uploaded && Object.keys(uploadStats.teachers_not_uploaded).length > 0) {
        const teachers = Object.values(uploadStats.teachers_not_uploaded);
        teachers.slice(0, 5).forEach(teacher => {
            const div = document.createElement('div');
            div.className = 'text-danger mb-1';
            div.innerHTML = `✗ ${teacher.fName} ${teacher.lName}`;
            notUploadedList.appendChild(div);
        });
        
        if (teachers.length > 5) {
            const more = document.createElement('small');
            more.className = 'text-muted';
            more.textContent = `+${teachers.length - 5} more`;
            notUploadedList.appendChild(more);
        }
    } else {
        notUploadedList.innerHTML = '<small class="text-success">All teachers have uploaded for this quarter!</small>';
    }
}

// Update section dropdown
function updateSectionsDropdown(sections) {
    const sectionSelect = document.getElementById('section_filter');
    const currentSection = sectionSelect.value;
    
    // Clear existing options except "All Sections"
    sectionSelect.innerHTML = '<option value="all">All Sections</option>';
    
    if (sections && sections.length > 0) {
        sections.forEach(section => {
            const option = document.createElement('option');
            option.value = section.SectionID;
            option.textContent = `${section.SectionName} (G${section.GradeLevel})`;
            if (section.SectionID === currentSection) {
                option.selected = true;
            }
            sectionSelect.appendChild(option);
        });
    }
}

// Chart options configuration
function getStudentsChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        size: 11
                    }
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#3498db',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 6,
                titleFont: {
                    size: 13,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 12
                },
                footerFont: {
                    size: 11
                },
callbacks: {
    label: function(context) {
        return `${context.dataset.label}: ${context.parsed.y} students`;
    },
    footer: function(tooltipItems) {
        let total = 0;
        tooltipItems.forEach(function(item) {
            total += item.parsed.y;
        });
        return `Total Students: ${total}`;
    }
}
            }
        },
        scales: {
            x: {
                stacked: true,
                grid: {
                    display: true,
                    drawBorder: true,
                },
                // Increase category and bar percentages for fuller bars (not super thin)
                categoryPercentage: 0.,
                barPercentage: 0.8,
                ticks: {
                    font: {
                        size: 10
                    },
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    font: {
                        size: 10
                    },
                    stepSize: 10
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                },
                title: {
                    display: true,
                    text: 'Number of Students',
                    font: {
                        size: 11,
                        weight: '600'
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        },
        animation: {
            duration: 500,
            easing: 'easeOutQuart'
        }
    };
}


function getAvgGradeChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: {
                        size: 11
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#1cc88a',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 6,
                titleFont: {
                    size: 13,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 12
                },
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.parsed.y}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                grid: {
                    drawBorder: false
                },
                title: {
                    display: true,
                    text: 'Average Grade',
                    font: {
                        size: 11,
                        weight: '600'
                    }
                },
                ticks: {
                    font: {
                        size: 10
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 10
                    },
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    };
}

function getPieChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                display: true, 
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: {
                        size: 11
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#6f42c1',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 6,
                titleFont: {
                    size: 13,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 12
                },
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    };
}

// Initialize all charts
function initializeCharts(data) {
    // Students per class chart
    const studentsCtx = document.getElementById('studentsPerClassChart').getContext('2d');
    
    if (data.students_per_section && data.students_per_section.length > 0) {
        const sectionLabels = data.students_per_section.map(item => 
            `${item.SectionName} (G${item.GradeLevel})`
        );
        const maleData = data.students_per_section.map(item => item.male_count || 0);
        const femaleData = data.students_per_section.map(item => item.female_count || 0);
        
        studentsPerClassChart = new Chart(studentsCtx, {
            type: 'bar',
            data: {
                labels: sectionLabels,
                datasets: [
                    {
                        label: 'Male Students',
                        data: maleData,
                        backgroundColor: 'rgba(52, 152, 219, 0.9)',
                        borderColor: 'rgba(41, 128, 185, 1)',
                        borderWidth: 1,
                        barThickness: 50,
                        maxBarThickness: 50,
                        stack: 'Stack 0'
                    },
                    {
                        label: 'Female Students',
                        data: femaleData,
                        backgroundColor: 'rgba(255, 182, 193, 0.8)',
                        borderColor: 'rgba(255, 182, 193, 1)',
                        borderWidth: 1,
                        barThickness: 50,
                        maxBarThickness: 50,
                        stack: 'Stack 0'
                    }
                ]
            },
            options: getStudentsChartOptions()
        });
    }
    
    // Teacher upload pie chart
    const pieCtx = document.getElementById('teachersUploadPie').getContext('2d');
    const uploadedCount = data.teachers_upload_stats?.uploaded || 0;
    const notUploadedCount = data.teachers_upload_stats?.not_uploaded || 0;
    
    teachersUploadPie = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['Uploaded', 'Not Uploaded'],
            datasets: [{
                data: [uploadedCount, notUploadedCount],
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
        options: getPieChartOptions()
    });
    
    // Average grade chart
    const avgCtx = document.getElementById('avgGradePerSubjectChart').getContext('2d');
    if (data.avg_grade_per_subject && data.avg_grade_per_subject.length > 0) {
        avgGradePerSubjectChart = new Chart(avgCtx, {
            type: 'bar',
            data: {
                labels: [...new Set(data.avg_grade_per_subject.map(item => item.SubjectName))].sort(),
                datasets: [{
                    label: 'Average Grade',
                    data: data.avg_grade_per_subject.map(item => parseFloat(item.avg_grade).toFixed(2)),
                    backgroundColor: 'rgba(28, 200, 138, 0.8)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 1
                }]
            },
            options: getAvgGradeChartOptions()
        });
    }
}

// Utility functions
function showLoading() {
    document.getElementById('loading-overlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loading-overlay').style.display = 'none';
}

function updateURL(filters) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (key !== 'ajax' && value && value !== 'all') {
            params.set(key, value);
        }
    }
    const newURL = `${window.location.pathname}?${params.toString()}`;
    window.history.pushState({}, '', newURL);
}

function resetFilters() {
    // Reset all filters to default
    const schoolYearSelect = document.getElementById('school_year');
    schoolYearSelect.selectedIndex = 0;
    
    document.getElementById('upload_quarter').value = 1;
    document.getElementById('grade_level').value = 'all';
    document.getElementById('section_filter').value = 'all';
    document.getElementById('quarter_filter').value = 'all';
    
    // Trigger update
    updateDashboard();
}

function resetGradeFilters() {
    document.getElementById('grade_level').value = 'all';
    document.getElementById('section_filter').value = 'all';
    document.getElementById('quarter_filter').value = 'all';
    
    // Trigger update
    updateDashboard();
}

// Make charts responsive on window resize
window.addEventListener('resize', function() {
    if (studentsPerClassChart) {
        studentsPerClassChart.resize();
    }
    if (teachersUploadPie) {
        teachersUploadPie.resize();
    }
    if (avgGradePerSubjectChart) {
        avgGradePerSubjectChart.resize();
    }
});
</script>

<!-- ══════════════════════════════════════════════════════════
     School Details Setup Modal
     Auto-shows when school_details table is empty
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="schoolSetupModal" tabindex="-1" aria-labelledby="schoolSetupModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" action="" id="schoolSetupForm" class="modal-content" style="border:none; border-radius:18px; overflow:hidden;">
      <input type="hidden" name="save_school_details" value="1">
      
      <!-- Gradient header -->
      <div class="modal-header text-white border-0"
           style="background: linear-gradient(135deg,#1a1f2e 0%,#2d6a4f 100%); padding:28px 32px 22px;">
        <div>
          <div class="d-flex align-items-center gap-3 mb-1">
            <div style="width:46px;height:46px;background:rgba(255,255,255,0.15);border-radius:12px;
                        display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-building-gear fs-4"></i>
            </div>
            <div>
              <h5 class="modal-title mb-0 fw-bold fs-5" id="schoolSetupModalLabel">
                School Information Setup
              </h5>
              <small style="opacity:.75;">Please fill in your school details to get started</small>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-body" style="padding:32px 36px; background:#f8f9fa;">

        <!-- Step indicator -->
        <div class="d-flex align-items-center gap-2 mb-4">
          <span class="badge rounded-pill px-3 py-2" style="background:#1a1f2e;font-size:.78rem;">
            <i class="bi bi-info-circle me-1"></i>This information will appear on reports and forms
          </span>
        </div>

        <div class="row g-3">
          <!-- School Name (full width) -->
          <div class="col-12">
            <label class="form-label fw-semibold text-dark" for="setup_school_name">
              <i class="bi bi-building me-1 text-primary"></i>School Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control form-control-lg" id="setup_school_name"
                   name="school_name" required
                   value="<?php echo htmlspecialchars($school_info['school_name'] ?? ''); ?>"
                   placeholder="e.g. Bagong Araw National High School"
                   style="border-radius:10px;border-color:#dee2e6;">
          </div>

          <!-- School Year ID -->
          <div class="col-md-12">
            <label class="form-label fw-semibold text-dark" for="setup_school_year_id">
              <i class="bi bi-calendar-range me-1 text-warning"></i>School Year
            </label>
            <select class="form-select" id="setup_school_year_id" name="school_year_id" style="border-radius:10px;">
              <?php foreach ($school_years as $year): ?>
                <option value="<?php echo $year; ?>" <?php echo (isset($school_info['school_year_id']) && $school_info['school_year_id'] == $year) ? 'selected' : ''; ?>>
                  <?php echo $year; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- School Address -->
          <div class="col-12">
            <label class="form-label fw-semibold text-dark" for="setup_school_address">
              <i class="bi bi-pin-map me-1 text-secondary"></i>School Address
            </label>
            <input type="text" class="form-control" id="setup_school_address"
                   name="school_address"
                   value="<?php echo htmlspecialchars($school_info['school_address'] ?? ''); ?>"
                   placeholder="Complete address of the school"
                   style="border-radius:10px;">
          </div>

          <!-- Sub-Office & Division -->
          <div class="col-md-6">
            <label class="form-label fw-semibold text-dark" for="setup_sub_office">
              <i class="bi bi-building-up me-1 text-success"></i>Sub-Office
            </label>
            <input type="text" class="form-control" id="setup_sub_office"
                   name="sub_office"
                   value="<?php echo htmlspecialchars($school_info['sub_office'] ?? ''); ?>"
                   placeholder="e.g. Sub-Office Name"
                   style="border-radius:10px;">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold text-dark" for="setup_division">
              <i class="bi bi-geo-alt me-1 text-danger"></i>Division
            </label>
            <input type="text" class="form-control" id="setup_division"
                   name="division"
                   value="<?php echo htmlspecialchars($school_info['division'] ?? ''); ?>"
                   placeholder="e.g. Division of Quezon City"
                   style="border-radius:10px;">
          </div>

          <!-- Region -->
          <div class="col-md-12">
            <label class="form-label fw-semibold text-dark" for="setup_region">
              <i class="bi bi-globe me-1 text-purple"></i>Region
            </label>
            <input type="text" class="form-control" id="setup_region"
                   name="region"
                   value="<?php echo htmlspecialchars($school_info['region'] ?? ''); ?>"
                   placeholder="e.g. Region IV-A"
                   style="border-radius:10px;">
          </div>
        </div>

        <!-- Info notice -->
        <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0"
             style="border-radius:12px;border:none;background:#e8f4f8;">
          <i class="bi bi-info-circle-fill text-info mt-1"></i>
          <div>
            <strong>Why is this required?</strong> School details are used in grade reports, Form 138,
            and official documents. You can update these anytime from the Settings page.
          </div>
        </div>
      </div>

      <div class="modal-footer border-0" style="background:#f8f9fa;padding:16px 36px 24px;">
        <button type="submit" class="btn btn-lg px-5 fw-semibold text-white"
                style="background:linear-gradient(135deg,#1a1f2e,#2d6a4f);border:none;border-radius:10px;
                       box-shadow:0 4px 15px rgba(26,31,46,.3);transition:opacity .2s;"
                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
          <i class="bi bi-check-circle me-2"></i>Save School Details
        </button>
      </div>
    </form>
  </div>
</div>
<script>
// Auto-show school setup modal if school_details is empty
(function() {
    var isEmpty = <?php echo $school_details_empty ? 'true' : 'false'; ?>;
    if (isEmpty) {
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('schoolSetupModal');
            if (el) {
                var modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: false });
                modal.show();
            }
        });
    }
})();
</script>

</body>
</html>