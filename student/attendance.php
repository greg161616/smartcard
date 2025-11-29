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

// Get student attendance records
$studentId = $student['StudentID'];

// Get active school year
$school_year_query = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$active_school_year = ($school_year_result && $school_year_result->num_rows > 0) ? $school_year_result->fetch_assoc()['school_year'] : date('Y') . '-' . (date('Y')+1);

// Initialize filter variables
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('n');
}
if ($selected_year < 2000 || $selected_year > 2100) {
    $selected_year = date('Y');
}

// Build filter conditions for SQL
$month_condition = "";
$params = [$active_school_year, $studentId];
$param_types = "si";

if ($selected_month && $selected_year) {
    $month_condition = " AND MONTH(a.Date) = ? AND YEAR(a.Date) = ?";
    $params[] = $selected_month;
    $params[] = $selected_year;
    $param_types .= "ii";
}

// Get student attendance records with filtering
$attendance_query = "
    SELECT a.*, t.fName as TeacherFirstName, t.lName as TeacherLastName, 
           s.SectionName, sec.GradeLevel
    FROM attendance a
    LEFT JOIN teacher t ON a.TeacherID = t.TeacherID
    LEFT JOIN section s ON a.SectionID = s.SectionID
    LEFT JOIN section_enrollment se ON a.StudentID = se.StudentID AND se.SchoolYear = ?
    LEFT JOIN section sec ON se.SectionID = sec.SectionID
    WHERE a.StudentID = ?" . $month_condition . "
    ORDER BY a.Date DESC
";

$stmt = $conn->prepare($attendance_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$attendance_result = $stmt->get_result();
$attendance_records = $attendance_result->fetch_all(MYSQLI_ASSOC);

// Get attendance summary with filtering
$summary_query = "
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN a.Status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.Status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN a.Status = 'excused' THEN 1 ELSE 0 END) as excused_days
    FROM attendance a
    WHERE a.StudentID = ?" . $month_condition;

$stmt = $conn->prepare($summary_query);
if ($month_condition) {
    $stmt->bind_param("iii", $studentId, $selected_month, $selected_year);
} else {
    $stmt->bind_param("i", $studentId);
}
$stmt->execute();
$summary_result = $stmt->get_result();
$attendance_summary = $summary_result->fetch_assoc();

// Calculate attendance percentage
$attendance_percentage = $attendance_summary['total_days'] > 0 
    ? round(($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100, 2) 
    : 0;

// Get available years for filter dropdown (from student's attendance records)
$years_query = "
    SELECT DISTINCT YEAR(Date) as year 
    FROM attendance 
    WHERE StudentID = ? 
    ORDER BY year DESC
";
$stmt = $conn->prepare($years_query);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$years_result = $stmt->get_result();
$available_years = $years_result->fetch_all(MYSQLI_ASSOC);

// If no years found, use current year
if (empty($available_years)) {
    $available_years = [['year' => date('Y')]];
}

// Month names for dropdown
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Balaytigue National High School</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #2c3e50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sidebar {
            background-color: #34495e;
            min-height: calc(100vh - 56px);
            color: white;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #3498db;
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        .attendance-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .attendance-present {
            background-color: #d4edda;
            color: #155724;
        }
        .attendance-absent {
            background-color: #f8d7da;
            color: #721c24;
        }
        .attendance-excused {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .stats-card {
            text-align: center;
            padding: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .percentage-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#28a745 <?php echo $attendance_percentage * 3.6; ?>deg, #e9ecef 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            position: relative;
        }
        .percentage-circle::before {
            content: '';
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
        }
        .percentage-text {
            position: relative;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .btn-outline-light:hover {
            color: #667eea;
        }

    </style>
</head>
<body>
<?php include '../navs/studentNav.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="main-content">

                <!-- Filter Card -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-3"><i class="fas fa-filter me-2"></i>Filter Attendance Records</h5>
                                <form method="GET" class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label for="month" class="form-label">Month</label>
                                        <select class="form-select" id="month" name="month">
                                            <option value="">All Months</option>
                                            <?php foreach ($month_names as $num => $name): ?>
                                                <option value="<?php echo $num; ?>" <?php echo $selected_month == $num ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="year" class="form-label">Year</label>
                                        <select class="form-select" id="year" name="year">
                                            <option value="">All Years</option>
                                            <?php foreach ($available_years as $year_data): ?>
                                                <option value="<?php echo $year_data['year']; ?>" <?php echo $selected_year == $year_data['year'] ? 'selected' : ''; ?>>
                                                    <?php echo $year_data['year']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Filter
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($selected_month || $selected_year): ?>
                                    <div class="current-filter p-3 rounded">
                                        <h6 class="mb-2">Current Filter:</h6>
                                        <p class="mb-1">
                                            <?php 
                                            if ($selected_month && $selected_year) {
                                                echo $month_names[$selected_month] . ' ' . $selected_year;
                                            } elseif ($selected_month) {
                                                echo $month_names[$selected_month] . ' (All Years)';
                                            } elseif ($selected_year) {
                                                echo 'All Months, ' . $selected_year;
                                            } else {
                                                echo 'All Records';
                                            }
                                            ?>
                                        </p>
                                        <a href="?" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-times me-1"></i>Clear Filter
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="stats-number text-primary"><?php echo $attendance_summary['total_days']; ?></div>
                            <div class="stats-label">Total Days</div>
                            <?php if ($selected_month || $selected_year): ?>
                                <small class="text-muted">Filtered</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="stats-number text-success"><?php echo $attendance_summary['present_days']; ?></div>
                            <div class="stats-label">Present</div>
                            <?php if ($selected_month || $selected_year): ?>
                                <small class="text-muted">Filtered</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="stats-number text-danger"><?php echo $attendance_summary['absent_days']; ?></div>
                            <div class="stats-label">Absent</div>
                            <?php if ($selected_month || $selected_year): ?>
                                <small class="text-muted">Filtered</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="percentage-circle">
                                <div class="percentage-text"><?php echo $attendance_percentage; ?>%</div>
                            </div>
                            <div class="stats-label">Attendance Rate</div>
                            <?php if ($selected_month || $selected_year): ?>
                                <small class="text-muted">Filtered</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Month Navigation -->
                <div class="card mb-4">
                    <div class="card-header">
                        <span>Quick Month Navigation</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $current_year = date('Y');
                            $current_month = date('n');
                            $months_to_show = 6; // Show last 6 months including current
                            
                            for ($i = $months_to_show - 1; $i >= 0; $i--) {
                                $month_num = $current_month - $i;
                                $year = $current_year;
                                
                                if ($month_num < 1) {
                                    $month_num += 12;
                                    $year = $current_year - 1;
                                }
                                
                                $is_current = ($month_num == $selected_month && $year == $selected_year);
                                $month_name = date('M', mktime(0, 0, 0, $month_num, 1));
                                ?>
                                <div class="col-md-2 col-sm-4 col-6 mb-2">
                                    <a href="?month=<?php echo $month_num; ?>&year=<?php echo $year; ?>" 
                                       class="btn btn-sm w-100 <?php echo $is_current ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        <?php echo $month_name . ' ' . $year; ?>
                                    </a>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            Attendance History 
                            <?php if ($selected_month || $selected_year): ?>
                                <small class="text-muted">
                                    (Filtered: 
                                    <?php 
                                    if ($selected_month && $selected_year) {
                                        echo $month_names[$selected_month] . ' ' . $selected_year;
                                    } elseif ($selected_month) {
                                        echo $month_names[$selected_month] . ' (All Years)';
                                    } elseif ($selected_year) {
                                        echo 'All Months, ' . $selected_year;
                                    }
                                    ?>
                                    )
                                </small>
                            <?php endif; ?>
                        </span>
                        <span class="badge bg-primary"><?php echo count($attendance_records); ?> records</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Status</th>
                                        <th>Teacher</th>
                                        <th>Section</th>
                                        <th>Grade Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($attendance_records) > 0): ?>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($record['Date'])); ?></strong>
                                                </td>
                                                <td class="text-muted">
                                                    <?php echo date('l', strtotime($record['Date'])); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    if ($record['Status'] == 'present') {
                                                        $status_class = 'attendance-present';
                                                    } elseif ($record['Status'] == 'absent') {
                                                        $status_class = 'attendance-absent';
                                                    } elseif ($record['Status'] == 'excused') {
                                                        $status_class = 'attendance-excused';
                                                    }
                                                    ?>
                                                    <span class="badge attendance-badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($record['Status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($record['TeacherFirstName'] && $record['TeacherLastName']) {
                                                        echo htmlspecialchars($record['TeacherFirstName'] . ' ' . $record['TeacherLastName']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['SectionName'] ?? 'N/A'); ?></td>
                                                <td>Grade <?php echo htmlspecialchars($record['GradeLevel'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                                                <p class="mb-1">No attendance records found</p>
                                                <small class="text-muted">
                                                    <?php if ($selected_month || $selected_year): ?>
                                                        for the selected filter
                                                    <?php else: ?>
                                                        for your account
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="card">
                    <div class="card-header">
                        <span>Attendance Status Legend</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <span class="badge attendance-badge attendance-present me-2">Present</span>
                                <small>Student was present in class</small>
                            </div>
                            <div class="col-md-4">
                                <span class="badge attendance-badge attendance-absent me-2">Absent</span>
                                <small>Student was absent from class</small>
                            </div>
                            <div class="col-md-4">
                                <span class="badge attendance-badge attendance-excused me-2">Excused</span>
                                <small>Student had an excused absence</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when dropdowns change (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const monthSelect = document.getElementById('month');
            const yearSelect = document.getElementById('year');
            
            
            monthSelect.addEventListener('change', function() {
                this.form.submit();
            });
            
            yearSelect.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>