<?php
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

// Get active school year from database
$active_school_year = '';
$result = mysqli_query($conn, "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $active_school_year = $row['school_year'];
}
mysqli_free_result($result);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Initialize totals
$total_students = 0;
$total_teachers = 0;
$total_sections = 0;
$total_subjects = 0;
$today_logs = 0;

// Total Students (enrolled in active school year)
$sql_students = "SELECT COUNT(DISTINCT se.StudentID) as total 
                 FROM section_enrollment se 
                 WHERE se.SchoolYear = ? AND se.status = 'Active'";
$stmt = mysqli_prepare($conn, $sql_students);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $active_school_year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_students = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

// Total Teachers (active teachers)
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM teacher ");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_teachers = $row['total'];
    mysqli_free_result($result);
}

// Total Sections (sections with enrollments in active school year)
$sql_sections = "SELECT COUNT(DISTINCT s.SectionID) as total 
                 FROM section s 
                 INNER JOIN section_enrollment se ON s.SectionID = se.SectionID 
                 WHERE se.SchoolYear = ?";
$stmt = mysqli_prepare($conn, $sql_sections);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $active_school_year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_sections = $row['total'];
    }
    mysqli_stmt_close($stmt);
}
// Today's Logs (attendance for today in active school year)
$today = date('Y-m-d');
$sql_attendance = "SELECT COUNT(*) as total 
                   FROM attendance a 
                   INNER JOIN section_enrollment se ON a.StudentID = se.StudentID 
                   WHERE a.Date = ? AND se.SchoolYear = ?";
$stmt = mysqli_prepare($conn, $sql_attendance);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $today, $active_school_year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $today_logs = $row['total'];
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Head Teacher Dashboard</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --info: #17a2b8;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), #34495e);
            color: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.students { border-left-color: var(--secondary); }
        .stat-card.teachers { border-left-color: var(--success); }
        .stat-card.sections { border-left-color: var(--info); }
        .stat-card.subjects { border-left-color: var(--warning); }
        .stat-card.attendance { border-left-color: var(--danger); }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            font-weight: 600;
        }
        
        .welcome-text {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .date-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .card-body {
            padding: 2rem 1.5rem;
        }
        
        .school-year-badge {
            background: var(--success);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .school-year-indicator {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 4px solid var(--info);
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../navs/headNav.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="dashboard-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h1 class="h2 mb-2"><i class="fas fa-tachometer-alt me-2"></i>Head Teacher Dashboard</h1>
                            <p class="welcome-text mb-0">Welcome back! Here's your overview for the current school year.</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="date-badge">
                                <i class="fas fa-calendar me-1"></i><?php echo date('F j, Y'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- School Year Indicator -->
                <?php if (!empty($active_school_year)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="school-year-indicator p-3 rounded d-flex align-items-center justify-content-between">
                            <div>
                                <i class="fas fa-calendar-alt me-2 text-info"></i>
                                <strong>Active School Year:</strong> 
                                <span class="ms-2 fw-bold"><?php echo htmlspecialchars($active_school_year); ?></span>
                            </div>
                            <span class="school-year-badge">
                                <i class="fas fa-check-circle me-1"></i>Currently Active
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row g-4">
                    <!-- Students Card -->
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card stat-card students">
                            <div class="card-body text-center">
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h2 class="stat-number text-primary"><?php echo $total_students; ?></h2>
                                <p class="stat-title">Total Students</p>
                                <small class="text-muted">
                                    <?php echo !empty($active_school_year) ? 'Enrolled in ' . htmlspecialchars($active_school_year) : 'Currently enrolled'; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Teachers Card -->
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card stat-card teachers">
                            <div class="card-body text-center">
                                <div class="stat-icon text-success">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <h2 class="stat-number text-success"><?php echo $total_teachers; ?></h2>
                                <p class="stat-title">Total Teachers</p>
                                <small class="text-muted">Active teaching staff</small>
                            </div>
                        </div>
                    </div>

                    <!-- Sections Card -->
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card stat-card sections">
                            <div class="card-body text-center">
                                <div class="stat-icon text-info">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <h2 class="stat-number text-info"><?php echo $total_sections; ?></h2>
                                <p class="stat-title">Total Sections</p>
                                <small class="text-muted">
                                    <?php echo !empty($active_school_year) ? 'Active in ' . htmlspecialchars($active_school_year) : 'Active classes'; ?>
                                </small>
                            </div>
                        </div>
                    </div>


                    <!-- Today's Attendance Card -->
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card stat-card attendance">
                            <div class="card-body text-center">
                                <div class="stat-icon text-danger">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h2 class="stat-number text-danger"><?php echo $today_logs; ?></h2>
                                <p class="stat-title">Today's Attendance</p>
                                <small class="text-muted">For <?php echo date('M j, Y'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & Popper JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>