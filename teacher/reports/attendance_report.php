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

$advisory_sql = "SELECT SectionID, SectionName, GradeLevel FROM section WHERE AdviserID = ? LIMIT 1";
$advisory_stmt = $conn->prepare($advisory_sql);
$advisory_stmt->bind_param("i", $teacher_id);
$advisory_stmt->execute();
$section = $advisory_stmt->get_result()->fetch_assoc();

$attendance_summary = [];
$months = ['June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March', 'April', 'May'];
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('F');

if ($section) {
    $current_year = date('Y') . '-' . (date('Y') + 1);
    $sy_sql = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
    $sy_res = $conn->query($sy_sql);
    if ($sy_res && $sy_res->num_rows > 0) {
        $current_year = $sy_res->fetch_assoc()['school_year'];
    }

    $month_num = date('n', strtotime($selected_month));
    $year_to_query = ($month_num >= 6) ? explode('-', $current_year)[0] : explode('-', $current_year)[1];

    $sql = "SELECT s.LastName, s.FirstName, 
                   COUNT(CASE WHEN a.Status = 'present' THEN 1 END) as present,
                   COUNT(CASE WHEN a.Status = 'absent' THEN 1 END) as absent,
                   COUNT(CASE WHEN a.Status = 'excused' THEN 1 END) as excused
            FROM student s
            JOIN section_enrollment se ON s.StudentID = se.StudentID
            LEFT JOIN attendance a ON s.StudentID = a.StudentID AND MONTH(a.Date) = ? AND YEAR(a.Date) = ?
            WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
            GROUP BY s.StudentID
            ORDER BY s.LastName, s.FirstName";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $month_num, $year_to_query, $section['SectionID'], $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_summary[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - <?php echo $selected_month; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .breadcrumb-container { background: white; padding: 1rem 2rem; border-bottom: 1px solid #e1e8ed; }
        .page-title { font-size: 1.5rem; font-weight: 600; color: #1a1f2e; margin-bottom: 0.25rem; }
        .pdf-container { height: calc(100vh - 220px); background: #525659; border-radius: 8px; overflow: hidden; }
        .form-select-custom { border-radius: 8px; padding: 0.5rem 1rem; border-color: #e2e8f0; }
    </style>
</head>
<body>
    <?php include '../../navs/teacherNav.php'; ?>
    <div class="breadcrumb-container">
        <h1 class="page-title">Attendance Report</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../record.php">Reports</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>

    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="d-flex" style="height: calc(100vh - 200px);">
                    <!-- Sidebar Filters -->
                    <div class="sidebar-filters bg-white border-end" style="width: 320px; padding: 2rem 1.5rem; display: flex; flex-direction: column;">
                        <h5 class="fw-bold mb-4">Report Options</h5>
                        
                        <div class="filter-group mb-4">
                            <label class="filter-label" style="font-size: 0.85rem; font-weight: 500; color: #64748b; margin-bottom: 0.5rem; display: block;">Select Month</label>
                            <select name="month" class="form-select form-select-custom" onchange="updatePdf()" style="border-radius: 8px; padding: 0.75rem; border-color: #e2e8f0;">
                                <?php foreach ($months as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="info-group mb-4">
                            <p class="small text-muted mb-1">Grade & Section</p>
                            <p class="fw-bold mb-3"><?php echo $section['GradeLevel'] ?? ''; ?> - <?php echo $section['SectionName'] ?? ''; ?></p>
                            
                            <p class="small text-muted mb-1">Class Adviser</p>
                            <p class="fw-bold"><?php echo $teacher_name; ?></p>
                        </div>

                        <div class="mt-auto d-flex flex-column gap-2">
                            <button id="download-btn" class="btn btn-primary py-2">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </button>
                            <button onclick="document.getElementById('pdf-iframe').contentWindow.print()" class="btn btn-secondary py-2">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <a href="../record.php" class="btn btn-outline-secondary py-2">Back to Reports</a>
                        </div>
                    </div>

                    <!-- PDF Viewer -->
                    <div class="pdf-viewer-container flex-grow-1 bg-secondary bg-opacity-10">
                        <iframe id="pdf-iframe" src="generate_attendance_pdf.php?month=<?php echo urlencode($selected_month); ?>" class="w-100 h-100 border-0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- Close page-content from teacherNav.php -->

    <script>
        function updatePdf() {
            const month = document.querySelector('select[name="month"]').value;
            const iframe = document.getElementById('pdf-iframe');
            iframe.src = `generate_attendance_pdf.php?month=${encodeURIComponent(month)}`;
        }

        document.getElementById('download-btn').addEventListener('click', () => {
            const month = document.querySelector('select[name="month"]').value;
            window.open(`generate_attendance_pdf.php?month=${encodeURIComponent(month)}&download=1`, '_blank');
        });
    </script>
</body>
</html>


