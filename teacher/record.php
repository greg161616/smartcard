<?php
session_start();
include '../config.php';
date_default_timezone_set('Asia/Manila');

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher information
$user_id = $_SESSION['user_id'];
$teacher_sql = "SELECT TeacherID, fName, lName, mName FROM teacher WHERE UserID = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_name = $teacher ? trim($teacher['fName'] . ' ' . $teacher['lName']) : 'Teacher';
$teacher_stmt->close();

// Fetch active school year
$schoolyear_query = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$schoolyear_result = $conn->query($schoolyear_query);
$schoolyear = $schoolyear_result ? $schoolyear_result->fetch_assoc() : null;
$current_year = $schoolyear ? $schoolyear['school_year'] : date('Y') . '-' . (date('Y') + 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Selection - SmartCard</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2d6a4f;
            --secondary-color: #1a1f2e;
            --accent-color: #40916c;
            --bg-light: #f8f9fa;
            --text-dark: #212529;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            color: var(--text-dark);
            min-height: 100vh;
        }

        .reports-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            padding: 4rem 0 6rem;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .reports-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
            opacity: 0.1;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .reports-container {
            margin-top: -4rem;
            padding-bottom: 4rem;
        }

        .report-card {
            background: white;
            border: none;
            border-radius: 20px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .report-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            transform: scaleX(0);
            transition: var(--transition);
        }

        .report-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }

        .report-card:hover::after {
            transform: scaleX(1);
        }

        .icon-box {
            width: 80px;
            height: 80px;
            background: var(--bg-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .report-card:hover .icon-box {
            background: var(--primary-color);
            color: white;
            transform: rotate(-10deg);
        }

        .report-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--secondary-color);
        }

        .report-desc {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.5;
        }

        .badge-sy {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            margin-top: 1rem;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .reports-header {
                padding: 3rem 0 5rem;
            }
            .reports-container {
                margin-top: -3rem;
            }
        }
    </style>
</head>
<body>

    <?php include '../navs/teacherNav.php'; ?>

    <div class="reports-header">
        <div class="container header-content">
            <h1 class="display-5 fw-bold mb-2">School Records & Reports</h1>
            <p class="lead opacity-75">Select the type of report you wish to generate or view</p>
            <div class="badge-sy">
                <i class="bi bi-calendar3 me-2"></i>School Year: <?php echo $current_year; ?>
            </div>
        </div>
    </div>

    <div class="container reports-container">
        <div class="row g-4 justify-content-center row-cols-1 row-cols-md-3 row-cols-lg-5">
            <!-- Attendance Report -->
            <div class="col">
                <a href="reports/attendance_report.php" class="report-card">
                    <div class="icon-box">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <h3 class="report-title">Attendance</h3>
                    <p class="report-desc">Generate monthly or quarterly attendance summaries for your advisory class.</p>
                </a>
            </div>

            <!-- Student List -->
            <div class="col">
                <a href="reports/student_list.php" class="report-card">
                    <div class="icon-box">
                        <i class="fa-solid fa-users-rectangle"></i>
                    </div>
                    <h3 class="report-title">Student List</h3>
                    <p class="report-desc">Export or print the complete list of students enrolled in your section.</p>
                </a>
            </div>

            <!-- Grades Report -->
            <div class="col">
                <a href="reports/grades_summary.php" class="report-card">
                    <div class="icon-box">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <h3 class="report-title">Grades</h3>
                    <p class="report-desc">View overall grade summaries and performance tracking for all subjects.</p>
                </a>
            </div>

            <!-- Report Card (SF9) -->
            <div class="col">
                <a href="reports/report_card.php" class="report-card">
                    <div class="icon-box">
                        <i class="fa-solid fa-address-card"></i>
                    </div>
                    <h3 class="report-title">Report Card</h3>
                    <p class="report-desc">Generate official Progress Report Cards (SF9) for individual students.</p>
                </a>
            </div>

            <!-- Certificates -->
            <div class="col">
                <a href="reports/certificates.php" class="report-card">
                    <div class="icon-box">
                        <i class="fa-solid fa-award"></i>
                    </div>
                    <h3 class="report-title">Certificates</h3>
                    <p class="report-desc">Generate academic honor certificates for qualifying students (90+ average).</p>
                </a>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-muted small">
                <i class="fa-solid fa-circle-info me-1"></i>
                All reports are generated based on the latest synchronized data from the SmartCard system.
            </p>
        </div>
    </div>

    </div> <!-- Close page-content from teacherNav.php -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
