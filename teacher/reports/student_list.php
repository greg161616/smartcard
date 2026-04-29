<?php
include '../../config.php';
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$teacher_sql = "SELECT TeacherID, fName, lName FROM teacher WHERE UserID = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->get_result()->fetch_assoc();
$teacher_id = $teacher['TeacherID'];
$teacher_name = trim($teacher['fName'] . ' ' . $teacher['lName']);

// Get Advisory Section
$advisory_sql = "SELECT SectionID, SectionName, GradeLevel FROM section WHERE AdviserID = ? LIMIT 1";
$advisory_stmt = $conn->prepare($advisory_sql);
$advisory_stmt->bind_param("i", $teacher_id);
$advisory_stmt->execute();
$section = $advisory_stmt->get_result()->fetch_assoc();

$students = [];
if ($section) {
    $current_year = date('Y') . '-' . (date('Y') + 1); // Simplified for now
    $sy_sql = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
    $sy_res = $conn->query($sy_sql);
    if ($sy_res && $sy_res->num_rows > 0) {
        $current_year = $sy_res->fetch_assoc()['school_year'];
    }

    $sql = "SELECT s.StudentID, s.FirstName, s.LastName, s.Middlename, s.LRN, s.Sex 
            FROM student s
            JOIN section_enrollment se ON s.StudentID = se.StudentID
            WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
            ORDER BY s.LastName, s.FirstName";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $section['SectionID'], $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List - <?php echo $section['SectionName'] ?? 'No Section'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .breadcrumb-container { background: white; padding: 1rem 2rem; border-bottom: 1px solid #e1e8ed; }
        .page-title { font-size: 1.5rem; font-weight: 600; color: #1a1f2e; margin-bottom: 0.25rem; }
        .pdf-container { height: calc(100vh - 200px); background: #525659; border-radius: 8px; overflow: hidden; }
    </style>
</head>
<body>
    <?php include '../../navs/teacherNav.php'; ?>
    <div class="breadcrumb-container">
        <h1 class="page-title">Student List</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../record.php">Reports</a></li>
                <li class="breadcrumb-item active">Student List</li>
            </ol>
        </nav>
    </div>

    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="d-flex" style="height: calc(100vh - 200px);">
                    <!-- Sidebar Filters -->
                    <div class="sidebar-filters bg-white border-end" style="width: 320px; padding: 2rem 1.5rem; display: flex; flex-direction: column;">
                        <h5 class="fw-bold mb-4">Report Details</h5>
                        
                        <div class="info-group mb-4">
                            <p class="small text-muted mb-1">Grade & Section</p>
                            <p class="fw-bold mb-3"><?php echo $section['GradeLevel'] ?? ''; ?> - <?php echo $section['SectionName'] ?? ''; ?></p>
                            
                            <p class="small text-muted mb-1">Class Adviser</p>
                            <p class="fw-bold mb-3"><?php echo $teacher_name; ?></p>

                            <p class="small text-muted mb-1">School Year</p>
                            <p class="fw-bold mb-3"><?php echo $current_year; ?></p>

                            <p class="small text-muted mb-1">Total Students</p>
                            <p class="fw-bold"><?php echo count($students); ?></p>
                        </div>

                        <div class="mt-auto d-flex flex-column gap-2">
                            <a href="generate_student_list_pdf.php?download=1" class="btn btn-primary py-2">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </a>
                            <button onclick="document.getElementById('pdf-iframe').contentWindow.print()" class="btn btn-secondary py-2">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <a href="../record.php" class="btn btn-outline-secondary py-2">Back to Reports</a>
                        </div>
                    </div>

                    <!-- PDF Viewer -->
                    <div class="pdf-viewer-container flex-grow-1 bg-secondary bg-opacity-10">
                        <iframe id="pdf-iframe" src="generate_student_list_pdf.php" class="w-100 h-100 border-0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- Close page-content from teacherNav.php -->
</body>
</html>


