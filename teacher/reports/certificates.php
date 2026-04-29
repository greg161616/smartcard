<?php
include '../../config.php';
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../../login.php");
    exit();
}

$current_year = '2025-2026';
$user_id = $_SESSION['user_id'];

// Fetch active school year
$schoolyear_query = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$schoolyear_result = $conn->query($schoolyear_query);
$schoolyear = $schoolyear_result ? $schoolyear_result->fetch_assoc() : null;
$current_year = $schoolyear ? $schoolyear['school_year'] : '2025-2026';

// Get teacher info
$teacher_stmt = $conn->prepare("SELECT TeacherID, fName, lName, mName FROM teacher WHERE UserID = ?");
$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->get_result()->fetch_assoc();
$teacher_id = $teacher['TeacherID'] ?? null;
$teacher_stmt->close();

// Helper functions for calculation
function finalGrade(array $g): string {
    $q = array_filter([$g['Q1'] ?? '', $g['Q2'] ?? '', $g['Q3'] ?? '', $g['Q4'] ?? '']);
    if (count($q) === 4) return (string) round(array_sum($q) / 4);
    return $g['Final'] ?? '';
}

function calculateStudentAverage($student_id, $school_year, $conn) {
    $main_subjects = ['Filipino', 'English', 'Mathematics', 'Science', 'Araling Panlipunan (AP)', 'Values Education (VE)', 'Technology and Livelihood Education (TLE)'];
    $mapeh_comps = ['Music', 'Arts', 'Physical Education', 'Health'];

    $grades = [];
    $g_stmt = $conn->prepare("SELECT g.*, s.SubjectName FROM grades g JOIN subject s ON g.subject = s.SubjectID WHERE g.student_id = ? AND g.school_year = ?");
    $g_stmt->bind_param("is", $student_id, $school_year);
    $g_stmt->execute();
    $g_res = $g_stmt->get_result();
    while ($row = $g_res->fetch_assoc()) {
        $grades[$row['SubjectName']] = $row;
    }
    $g_stmt->close();

    $avg_sum = 0;
    $avg_cnt = 0;

    foreach ($main_subjects as $sub) {
        $g = $grades[$sub] ?? [];
        $fin = finalGrade($g);
        if ($fin !== '') {
            $avg_sum += (int)$fin;
            $avg_cnt++;
        }
    }

    // MAPEH Calculation
    $mq = array_fill_keys(['Q1','Q2','Q3','Q4'], 0);
    $mc = array_fill_keys(['Q1','Q2','Q3','Q4'], 0);

    foreach ($mapeh_comps as $comp) {
        if (!isset($grades[$comp])) continue;
        foreach (['Q1','Q2','Q3','Q4'] as $q) {
            if (!empty($grades[$comp][$q])) {
                $mq[$q] += $grades[$comp][$q];
                $mc[$q]++;
            }
        }
    }

    $mq1 = $mc['Q1'] > 0 ? (string) round($mq['Q1'] / $mc['Q1']) : '';
    $mq2 = $mc['Q2'] > 0 ? (string) round($mq['Q2'] / $mc['Q2']) : '';
    $mq3 = $mc['Q3'] > 0 ? (string) round($mq['Q3'] / $mc['Q3']) : '';
    $mq4 = $mc['Q4'] > 0 ? (string) round($mq['Q4'] / $mc['Q4']) : '';

    $m_fin = '';
    if ($mq1 && $mq2 && $mq3 && $mq4) {
        $total_mapeh_sum = $mq['Q1'] + $mq['Q2'] + $mq['Q3'] + $mq['Q4'];
        $total_mapeh_cnt = array_sum($mc);
        if ($total_mapeh_cnt > 0) {
            $m_fin = (string) round($total_mapeh_sum / $total_mapeh_cnt);
        }
    }

    if ($m_fin !== '') {
        $avg_sum += (int)$m_fin;
        $avg_cnt++;
    }

    $gen_avg = $avg_cnt > 0 ? (int) round($avg_sum / $avg_cnt) : 0;
    return $gen_avg;
}

// Get qualifying students
$qualifying_students = [];
if ($teacher_id) {
    $advisory_stmt = $conn->prepare("SELECT SectionID FROM section WHERE AdviserID = ?");
    $advisory_stmt->bind_param("i", $teacher_id);
    $advisory_stmt->execute();
    $advisory = $advisory_stmt->get_result()->fetch_assoc();
    $advisory_stmt->close();

    if ($advisory) {
        $advisory_section_id = $advisory['SectionID'];
        $students_sql = "
            SELECT DISTINCT s.StudentID, s.FirstName, s.LastName, s.Middlename, s.LRN
            FROM student s
            JOIN section_enrollment se ON s.StudentID = se.StudentID
            WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
            ORDER BY s.LastName, s.FirstName
        ";
        $students_stmt = $conn->prepare($students_sql);
        $students_stmt->bind_param("is", $advisory_section_id, $current_year);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
        $students_stmt->close();

        while ($row = $students_result->fetch_assoc()) {
            $avg = calculateStudentAverage($row['StudentID'], $current_year, $conn);
            if ($avg >= 90) {
                $rank = '';
                if ($avg >= 98 && $avg <= 100) $rank = 'With Highest Honors';
                elseif ($avg >= 95 && $avg <= 97) $rank = 'With High Honors';
                elseif ($avg >= 90 && $avg <= 94) $rank = 'With Honors';

                $row['average'] = $avg;
                $row['rank'] = $rank;
                $qualifying_students[] = $row;
            }
        }
    }
}

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Honor Certificates - SmartCard</title>
    <link rel="icon" type="image/png" href="../../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .breadcrumb-container { background: white; padding: 1rem 2rem; border-bottom: 1px solid #e1e8ed; }
        .page-title { font-size: 1.5rem; font-weight: 600; color: #1a1f2e; margin-bottom: 0.25rem; }
        .sidebar-filters { width: 350px; background: white; border-right: 1px solid #e1e8ed; height: calc(100vh - 145px); display: flex; flex-direction: column; }
        .student-list { overflow-y: auto; flex-grow: 1; }
        .student-item { padding: 0.75rem 1.5rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit; display: block; }
        .student-item:hover { background-color: #f8fafc; }
        .student-item.active { background-color: #eff6ff; border-left: 4px solid #2d6a4f; }
        .student-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.1rem; }
        .student-rank { font-size: 0.8rem; font-weight: 600; }
        .rank-highest { color: #b8860b; }
        .rank-high { color: #8a9a5b; }
        .rank-honors { color: #40916c; }
        .student-lrn { font-size: 0.75rem; color: #64748b; }
        .pdf-viewer-container { flex-grow: 1; background: #525659; }
    </style>
</head>
<body>
    <?php include '../../navs/teacherNav.php'; ?>
    
    <div class="breadcrumb-container">
        <h1 class="page-title">Academic Honor Certificates</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../record.php">Reports</a></li>
                <li class="breadcrumb-item active">Certificates</li>
            </ol>
        </nav>
    </div>

    <div class="container-fluid p-0">
        <div class="d-flex">
            <!-- Sidebar: Student List -->
            <div class="sidebar-filters">
                <div class="p-3 border-bottom">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="studentSearch" placeholder="Search student...">
                    </div>
                </div>
                <div class="student-list" id="studentList">
                    <?php 
                    if (!empty($qualifying_students)):
                        foreach ($qualifying_students as $row):
                            $isActive = ($student_id == $row['StudentID']) ? 'active' : '';
                            $rankClass = '';
                            if ($row['rank'] === 'With Highest Honors') $rankClass = 'rank-highest';
                            elseif ($row['rank'] === 'With High Honors') $rankClass = 'rank-high';
                            else $rankClass = 'rank-honors';
                    ?>
                        <a href="?student_id=<?php echo $row['StudentID']; ?>" class="student-item <?php echo $isActive; ?>">
                            <div class="student-name"><?php echo strtoupper($row['LastName'] . ', ' . $row['FirstName']); ?></div>
                            <div class="student-rank <?php echo $rankClass; ?>"><?php echo $row['rank']; ?> (<?php echo $row['average']; ?>%)</div>
                            <div class="student-lrn">LRN: <?php echo $row['LRN']; ?></div>
                        </a>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <div class="p-4 text-center text-muted small">No students qualified for Academic Honors yet.</div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($qualifying_students)): ?>
                <div class="p-3 border-top bg-light mt-auto">
                    <button id="btnBatchPrint" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-print me-2"></i>Generate All Certificates
                    </button>
                </div>
                <?php endif; ?>
                <div class="p-3 border-top">
                    <a href="../record.php" class="btn btn-outline-secondary w-100 btn-sm">Back to Reports</a>
                </div>
            </div>

            <!-- Main Content: PDF Viewer -->
            <div class="pdf-viewer-container d-flex flex-column">
                <?php if ($student_id): ?>
                    <?php 
                        $selected_student = null;
                        foreach ($qualifying_students as $qs) {
                            if ($qs['StudentID'] == $student_id) {
                                $selected_student = $qs;
                                break;
                            }
                        }
                    ?>
                    <?php if ($selected_student): ?>
                        <div class="bg-white p-2 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold ms-2"><?php echo strtoupper($selected_student['LastName'] . ', ' . $selected_student['FirstName']); ?> - <?php echo $selected_student['rank']; ?></span>
                            <div class="d-flex gap-2">
                                <a href="generate_certificate_pdf.php?student_id=<?php echo $student_id; ?>&school_year=<?php echo urlencode($current_year); ?>&download=1" class="btn btn-primary btn-sm">
                                    <i class="fas fa-download me-2"></i>Download
                                </a>
                                <button onclick="document.getElementById('pdf-iframe').contentWindow.print()" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-print me-2"></i>Print
                                </button>
                            </div>
                        </div>
                        <iframe id="pdf-iframe" src="generate_certificate_pdf.php?student_id=<?php echo $student_id; ?>&school_year=<?php echo urlencode($current_year); ?>" class="w-100 h-100 border-0"></iframe>
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white opacity-50">
                            <i class="fas fa-exclamation-triangle fa-4x mb-3"></i>
                            <h5>Selected student does not qualify for Academic Honors</h5>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-white p-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold ms-2 text-muted"><i class="fas fa-eye me-2"></i>PREVIEW TEMPLATE</span>
                    </div>
                    <iframe id="pdf-iframe" src="generate_certificate_pdf.php?school_year=<?php echo urlencode($current_year); ?>" class="w-100 h-100 border-0"></iframe>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('studentSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.student-item');
            items.forEach(item => {
                const name = item.querySelector('.student-name').textContent.toLowerCase();
                if (name.includes(term)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        if (document.getElementById('btnBatchPrint')) {
            document.getElementById('btnBatchPrint').addEventListener('click', function() {
                const url = 'generate_certificate_pdf.php?batch=all&school_year=<?php echo urlencode($current_year); ?>';
                document.getElementById('pdf-iframe').src = url;
                document.querySelectorAll('.student-item').forEach(item => item.classList.remove('active'));
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
