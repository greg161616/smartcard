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

// Get all active school years
$school_years = [];
$sy_sql = "SELECT school_year, status FROM school_year ORDER BY school_year DESC";
$sy_res = $conn->query($sy_sql);
while ($row = $sy_res->fetch_assoc()) {
    $school_years[] = $row;
}

// Get sections handled by teacher
$sections = [];
$sec_sql = "SELECT DISTINCT s.SectionID, s.SectionName, s.GradeLevel 
            FROM section s
            WHERE s.AdviserID = ?
            UNION
            SELECT DISTINCT s.SectionID, s.SectionName, s.GradeLevel 
            FROM section s
            JOIN assigned_subject asub ON s.SectionID = asub.section_id
            WHERE asub.teacher_id = ?
            ORDER BY GradeLevel, SectionName";
$sec_stmt = $conn->prepare($sec_sql);
$sec_stmt->bind_param("ii", $teacher_id, $teacher_id);
$sec_stmt->execute();
$sec_res = $sec_stmt->get_result();
while ($row = $sec_res->fetch_assoc()) {
    $sections[] = $row;
}

$selected_sy = $_GET['school_year'] ?? ($school_years[0]['school_year'] ?? '');
$selected_section = $_GET['section_id'] ?? ($sections[0]['SectionID'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary of Final Grades - SmartCard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #0d6efd;
            --sidebar-bg: #ffffff;
            --main-bg: #f4f7f9;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--main-bg);
            margin: 0;
            overflow-x: hidden;
        }

        .breadcrumb-container {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e1e8ed;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1f2e;
            margin-bottom: 0.25rem;
        }

        .breadcrumb {
            font-size: 0.85rem;
            margin-bottom: 0;
        }

        .main-content {
            padding: 1.5rem;
        }

        .sidebar-filters {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .filter-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-select-custom {
            border-radius: 8px;
            padding: 0.75rem;
            border-color: #e2e8f0;
            font-size: 0.95rem;
        }

        .form-select-custom:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }
    </style>
</head>
<body>
    <?php include '../../navs/teacherNav.php'; ?>

    <div class="breadcrumb-container">
        <h1 class="page-title">Summary of Final Grades</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../record.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="../record.php" class="text-decoration-none">Reports</a></li>
                <li class="breadcrumb-item active">Summary of Final Grades</li>
            </ol>
        </nav>
    </div>

    <div class="main-content">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="card-title mb-0 fw-bold text-dark">Summary of Final Grades</h5>
            </div>
            <div class="card-body p-0">
                <div class="d-flex" style="height: calc(100vh - 220px);">
                    <!-- Sidebar Filters -->
                    <div class="sidebar-filters bg-white border-end" style="width: 320px; padding: 2rem 1.5rem;">
                        <div class="filter-group mb-4">
                            <label class="filter-label">Academic Year</label>
                            <select class="form-select form-select-custom" id="sy-select">
                                <option value="" disabled <?php echo empty($selected_sy) ? 'selected' : ''; ?>>Select Academic Year</option>
                                <?php foreach ($school_years as $sy): ?>
                                    <option value="<?php echo $sy['school_year']; ?>" <?php echo $selected_sy == $sy['school_year'] ? 'selected' : ''; ?>>
                                        <?php echo $sy['school_year']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group mb-4">
                            <label class="filter-label">Class</label>
                            <select class="form-select form-select-custom" id="section-select">
                                <option value="" disabled <?php echo empty($selected_section) ? 'selected' : ''; ?>>Select Class</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['SectionID']; ?>" <?php echo $selected_section == $sec['SectionID'] ? 'selected' : ''; ?>>
                                        Grade <?php echo $sec['GradeLevel']; ?> - <?php echo $sec['SectionName']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mt-auto d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1 py-2" id="download-btn">
                                <i class="fas fa-download me-2"></i> Download
                            </button>
                            <button class="btn btn-secondary py-2" onclick="printPdf()">
                                <i class="fas fa-print me-2"></i> Print
                            </button>
                        </div>
                    </div>

                    <!-- PDF Viewer -->
                    <div class="pdf-viewer-container flex-grow-1 bg-secondary bg-opacity-10">
                        <iframe id="pdf-iframe" src="" class="w-100 h-100 border-0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div> <!-- Close page-content from teacherNav.php -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sySelect = document.getElementById('sy-select');
        const sectionSelect = document.getElementById('section-select');
        const pdfIframe = document.getElementById('pdf-iframe');
        const downloadBtn = document.getElementById('download-btn');

        function updatePdf() {
            const sy = sySelect.value;
            const section = sectionSelect.value;
            if (sy && section) {
                const url = `generate_grades_pdf.php?school_year=${encodeURIComponent(sy)}&section_id=${encodeURIComponent(section)}`;
                pdfIframe.src = url;
            }
        }

        sySelect.addEventListener('change', updatePdf);
        sectionSelect.addEventListener('change', updatePdf);

        downloadBtn.addEventListener('click', () => {
            const sy = sySelect.value;
            const section = sectionSelect.value;
            if (sy && section) {
                window.open(`generate_grades_pdf.php?school_year=${encodeURIComponent(sy)}&section_id=${encodeURIComponent(section)}&download=1`, '_blank');
            }
        });

        function printPdf() {
            const iframe = document.getElementById('pdf-iframe');
            if (iframe.src) {
                iframe.contentWindow.print();
            }
        }

        // Initial load
        window.addEventListener('DOMContentLoaded', updatePdf);
    </script>
</body>
</html>
