<?php
include '../../config.php';
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../../login.php");
    exit();
}

// Initialize variables
$students_result = null;
$current_year = '2025-2026';

// Get teacher information
$user_id = $_SESSION['user_id'];

// Fetch active school year
$schoolyear_query = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$schoolyear_result = $conn->query($schoolyear_query);
$schoolyear = $schoolyear_result ? $schoolyear_result->fetch_assoc() : null;
$current_year = $schoolyear ? $schoolyear['school_year'] : '2025-2026';

// Get teacher's basic info
$teacher_sql = "SELECT TeacherID, fName, lName, mName FROM teacher WHERE UserID = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_id = $teacher ? $teacher['TeacherID'] : null;
$teacher_name = $teacher ? trim($teacher['fName'] . ' ' . ($teacher['mName'] ? $teacher['mName'] . ' ' : '') . $teacher['lName']) : 'Teacher';
$teacher_stmt->close();

// Session flag to prevent repeated PDF generation
$generate_pdf = isset($_GET['generate_pdf']) ? $_GET['generate_pdf'] : null;
$quarter = isset($_GET['quarter']) ? $_GET['quarter'] : null;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$save_temp = isset($_GET['save_temp']) ? $_GET['save_temp'] : null;

// Prevent looping
if ($generate_pdf && isset($_SESSION['pdf_generated']) && $_SESSION['pdf_generated'] === true) {
    unset($_SESSION['pdf_generated']);
    $redirect_url = 'report_card.php';
    if ($student_id) {
        $redirect_url .= '?student_id=' . urlencode($student_id);
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Set the flag if PDF generation is requested
if ($generate_pdf) {
    $_SESSION['pdf_generated'] = true;
}

// Always fetch students from teacher's advisory class if teacher_id is known
if ($teacher_id) {
    $advisory_sql = "SELECT SectionID, AdviserID FROM section WHERE AdviserID = ?";
    $advisory_stmt = $conn->prepare($advisory_sql);
    $advisory_stmt->bind_param("i", $teacher_id);
    $advisory_stmt->execute();
    $advisory_result = $advisory_stmt->get_result();
    $advisory = $advisory_result->fetch_assoc();
    $advisory_stmt->close();
    
    if ($advisory) {
        $advisory_section_id = $advisory['SectionID'];
        $students_sql = "
            SELECT DISTINCT s.StudentID, s.FirstName, s.LastName, s.Middlename, s.LRN,
                            sec.GradeLevel, sec.SectionName
            FROM student s
            JOIN section_enrollment se ON s.StudentID = se.StudentID
            JOIN section sec ON se.SectionID = sec.SectionID
            WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
            ORDER BY s.LastName, s.FirstName
        ";
        $students_stmt = $conn->prepare($students_sql);
        $students_stmt->bind_param("is", $advisory_section_id, $current_year);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
        // Don't close yet, we need to loop through it
    }
}

if ($student_id) {
    // REPORT CARD GENERATION CODE
    // Initialize student data
    $student = null;
    $grades = [];
    $attendance = [];
    $observed_values = [];
    $section = [];

    // Get student information
    $student_query = "SELECT * FROM student WHERE StudentID = ?";
    $student_stmt = $conn->prepare($student_query);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $student = $student_result->fetch_assoc();
    $student_stmt->close();

    if (!$student) {
        $_SESSION['error'] = "Student not found";
        header("Location: report_card.php");
        exit();
    }

    // Get student's current section
    $section_query = "
        SELECT s.*, se.SchoolYear 
        FROM section_enrollment se 
        JOIN section s ON se.SectionID = s.SectionID 
        WHERE se.StudentID = ? 
        AND se.SchoolYear = ?
        AND se.status = 'active'
        LIMIT 1
    ";
    $section_stmt = $conn->prepare($section_query);
    $section_stmt->bind_param("is", $student_id, $current_year);
    $section_stmt->execute();
    $section_result = $section_stmt->get_result();
    $section = $section_result->fetch_assoc();
    $section_stmt->close();

    // Get grades from grades table
    $grades_query = "
        SELECT g.*, s.SubjectName 
        FROM grades g 
        JOIN subject s ON g.subject = s.SubjectID 
        WHERE g.student_id = ?
        AND g.school_year = ?
    ";
    $grades_stmt = $conn->prepare($grades_query);
    $grades_stmt->bind_param("is", $student_id, $current_year);
    $grades_stmt->execute();
    $grades_result = $grades_stmt->get_result();
    $grades_stmt->close();

    $grades = [];
    while ($row = $grades_result->fetch_assoc()) {
        $grades[] = $row;
    }

    // Get attendance data
    $year = '';
    $next_year = '';
    if (preg_match('/^(\d{4})-(\d{4})$/', $current_year, $matches)) {
        $year = $matches[1];
        $next_year = $matches[2];
    }
    
    $attendance_query = "
        SELECT MONTH(Date) as month_num, 
               COUNT(*) as total_days,
               SUM(CASE WHEN Status = 'present' THEN 1 ELSE 0 END) as present_days,
               SUM(CASE WHEN Status = 'absent' THEN 1 ELSE 0 END) as absent_days,
               SUM(CASE WHEN Status = 'excused' THEN 1 ELSE 0 END) as excused_days
        FROM attendance 
        WHERE StudentID = ?
        AND SectionID = ?
        AND (
            (YEAR(Date) = ? AND MONTH(Date) >= 6) OR 
            (YEAR(Date) = ? AND MONTH(Date) <= 5)
        )
        GROUP BY MONTH(Date), YEAR(Date)
        ORDER BY YEAR(Date), MONTH(Date)
    ";
    $attendance_stmt = $conn->prepare($attendance_query);
    $section_id = $section ? $section['SectionID'] : 0;
    $attendance_stmt->bind_param("iiii", $student_id, $section_id, $year, $next_year);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    $attendance_stmt->close();

    $attendance_data = [];
    while ($row = mysqli_fetch_assoc($attendance_result)) {
        $attendance_data[$row['month_num']] = [
            'present' => $row['present_days'],
            'absent' => $row['absent_days'],
            'excused' => $row['excused_days'],
            'total' => $row['total_days']
        ];
    }

    // Map month numbers to names
    $month_names = [
        6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sept', 10 => 'Oct', 
        11 => 'Nov', 12 => 'Dec', 1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 
        4 => 'Apr', 5 => 'May'
    ];

    foreach ($month_names as $num => $name) {
        $attendance[$name] = isset($attendance_data[$num]) ? $attendance_data[$num] : ['present' => 0, 'absent' => 0, 'excused' => 0, 'total' => 0];
    }

    // Get teacher's name for signature
    $teacher_name_query = "SELECT fName, lName FROM teacher WHERE TeacherID = ?";
    $teacher_name_stmt = $conn->prepare($teacher_name_query);
    $teacher_name_stmt->bind_param("i", $teacher_id);
    $teacher_name_stmt->execute();
    $teacher_name_result = $teacher_name_stmt->get_result();
    $teacher_name_info = $teacher_name_result->fetch_assoc();
    $teacher_name_stmt->close();
    $teacher_full_name = $teacher_name_info ? $teacher_name_info['fName'] . ' ' . $teacher_name_info['lName'] : 'Teacher';

    $head_query = "SELECT FullName FROM admin WHERE Position = 'Head teacher' AND status = 'active' LIMIT 1";
    $head_query = $conn->prepare($head_query);
    $head_query->execute();
    $head_result = $head_query->get_result();
    $head = $head_result->fetch_assoc();
    $head_query->close();
    $head_teacher_name = $head ? $head['FullName'] : 'Head Teacher';

    // Get observed values if table exists
    $observed_values = [];
    if (tableExists($conn, 'student_values')) {
        $vals_sql = "SELECT * FROM student_values WHERE student_id = ? AND school_year = ?";
        $vals_stmt = $conn->prepare($vals_sql);
        $vals_stmt->bind_param("is", $student_id, $current_year);
        $vals_stmt->execute();
        $vals_res = $vals_stmt->get_result();
        $vals_stmt->close();
        while ($r = $vals_res->fetch_assoc()) {
            $observed_values[intval($r['quarter'])] = $r;
        }
    }

    // Get all subjects for the student's grade level
    $grade_level = !empty($section) ? $section['GradeLevel'] : '';
    $section_name = !empty($section) ? $section['SectionName'] : '';

    // Define the subjects in the specific order
    $ordered_subjects = [
        'Filipino',
        'English', 
        'Mathematics',
        'Science',
        'Araling Panlipunan (AP)',
        'Values Education (VE)',
        'Technology and Livelihood Education (TLE)',
        'Music',
        'Arts',
        'Physical Education',
        'Health'
    ];

    // Get all subjects
    $subjects_query = "
        SELECT SubjectID, SubjectName 
        FROM subject 
        ORDER BY SubjectID
    ";
    $subjects_stmt = $conn->prepare($subjects_query);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    $subjects_stmt->close();
    $all_subjects = [];
    while ($subject_row = $subjects_result->fetch_assoc()) {
        $all_subjects[] = $subject_row;
    }

    // Reorder subjects according to the specified order
    $ordered_subject_list = [];
    foreach ($ordered_subjects as $subject_name) {
        foreach ($all_subjects as $subject) {
            if ($subject['SubjectName'] == $subject_name) {
                $ordered_subject_list[] = $subject;
                break;
            }
        }
    }

    // Get MAPEH component IDs (Music, Arts, Physical Education, Health)
    $mapeh_component_names = ['Music', 'Arts', 'Physical Education', 'Health'];
    $mapeh_component_ids = [];
    
    foreach ($mapeh_component_names as $component_name) {
        foreach ($all_subjects as $subject) {
            if ($subject['SubjectName'] == $component_name) {
                $mapeh_component_ids[] = $subject['SubjectID'];
                break;
            }
        }
    }

    // Validate grades when generating PDF directly
    if ($generate_pdf && $quarter) {
        $missing_grades = validateQuarterGrades($grades, $quarter, $ordered_subject_list, $mapeh_component_ids);
        
        if (!empty($missing_grades)) {
            $_SESSION['error'] = "Cannot generate PDF for Quarter $quarter. The following subjects are missing grades:\n\n" . 
                                implode("\n", $missing_grades) . 
                                "\n\nPlease complete all grades before generating the report card.";
            header("Location: report_card.php");
            exit();
        }
    }
}

// Function to calculate age from birthdate
function calculateAge($birthdate) {
    if (empty($birthdate) || $birthdate == '0000-00-00') return '';
    
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

// Function to validate quarter grades
function validateQuarterGrades($grades, $quarter, $ordered_subject_list, $mapeh_components = []) {
    $missing_grades = [];
    $mapeh_component_ids = array_values($mapeh_components);
    
    foreach ($ordered_subject_list as $subject) {
        // Skip non-graded entries
        if (strpos($subject['SubjectName'], '•') === 0) {
            continue;
        }
        
        $grade_found = false;
        $has_grade = false;
        
        // Find the grade record for this subject
        foreach ($grades as $grade) {
            if ($grade['subject'] == $subject['SubjectID']) {
                $grade_found = $grade;
                break;
            }
        }
        
        // Check if the specific quarter grade exists and is not empty
        if ($grade_found) {
            switch ($quarter) {
                case 1:
                    $has_grade = !empty($grade_found['Q1']) && $grade_found['Q1'] !== '';
                    break;
                case 2:
                    $has_grade = !empty($grade_found['Q2']) && $grade_found['Q2'] !== '';
                    break;
                case 3:
                    $has_grade = !empty($grade_found['Q3']) && $grade_found['Q3'] !== '';
                    break;
                case 4:
                    $has_grade = !empty($grade_found['Q4']) && $grade_found['Q4'] !== '';
                    break;
            }
        }
        
        if (!$has_grade) {
            $missing_grades[] = $subject['SubjectName'];
        }
    }
    
    return $missing_grades;
}

// Helper function to check if table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// Function to calculate MAPEH grade
function calculateMAPEHGrade($grades, $mapeh_component_ids) {
    $mapeh_grades = [
        'q1' => null,
        'q2' => null,
        'q3' => null,
        'q4' => null,
        'final' => null
    ];
    
    // Collect grades for each MAPEH component
    $component_grades = [
        'music' => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null],
        'arts' => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null],
        'pe' => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null],
        'health' => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null]
    ];
    
    // Map component IDs to names (expecting order: Music, Arts, Physical Education, Health)
    $component_map = [];
    foreach ($mapeh_component_ids as $index => $component_id) {
        $component_name = '';
        switch ($index) {
            case 0: $component_name = 'music'; break;
            case 1: $component_name = 'arts'; break;
            case 2: $component_name = 'pe'; break;
            case 3: $component_name = 'health'; break;
        }
        if ($component_name && $component_id) {
            $component_map[$component_id] = $component_name;
        }
    }
    
    // Extract grades for each component
    foreach ($grades as $grade) {
        if (isset($component_map[$grade['subject']])) {
            $component = $component_map[$grade['subject']];
            
            if (isset($grade['Q1']) && $grade['Q1'] !== '') {
                $component_grades[$component]['q1'] = $grade['Q1'];
            }
            if (isset($grade['Q2']) && $grade['Q2'] !== '') {
                $component_grades[$component]['q2'] = $grade['Q2'];
            }
            if (isset($grade['Q3']) && $grade['Q3'] !== '') {
                $component_grades[$component]['q3'] = $grade['Q3'];
            }
            if (isset($grade['Q4']) && $grade['Q4'] !== '') {
                $component_grades[$component]['q4'] = $grade['Q4'];
            }
        }
    }
    
    // Calculate MAPEH for each quarter using the formula:
    // group1 = (music+arts)/2, group2 = (pe+health)/2, MAPEH = (group1+group2)/2
    for ($i = 1; $i <= 4; $i++) {
        $quarter_key = 'q' . $i;

        $music_grade = $component_grades['music'][$quarter_key];
        $arts_grade = $component_grades['arts'][$quarter_key];
        $pe_grade = $component_grades['pe'][$quarter_key];
        $health_grade = $component_grades['health'][$quarter_key];

        // Compute group averages with fallbacks if a single component is present
        $music_arts_avg = null;
        if ($music_grade !== null && $arts_grade !== null) {
            $music_arts_avg = ($music_grade + $arts_grade) / 2;
        } elseif ($music_grade !== null) {
            $music_arts_avg = $music_grade;
        } elseif ($arts_grade !== null) {
            $music_arts_avg = $arts_grade;
        }

        $pe_health_avg = null;
        if ($pe_grade !== null && $health_grade !== null) {
            $pe_health_avg = ($pe_grade + $health_grade) / 2;
        } elseif ($pe_grade !== null) {
            $pe_health_avg = $pe_grade;
        } elseif ($health_grade !== null) {
            $pe_health_avg = $health_grade;
        }

        // Combine group averages
        if ($music_arts_avg !== null && $pe_health_avg !== null) {
            $mapeh_grades[$quarter_key] = round(($music_arts_avg + $pe_health_avg) / 2);
        } elseif ($music_arts_avg !== null) {
            $mapeh_grades[$quarter_key] = round($music_arts_avg);
        } elseif ($pe_health_avg !== null) {
            $mapeh_grades[$quarter_key] = round($pe_health_avg);
        } else {
            $mapeh_grades[$quarter_key] = null;
        }
    }
    
    // Calculate final MAPEH grade from available quarter values (average of present quarters)
    $available = [];
    for ($i = 1; $i <= 4; $i++) {
        $q = $mapeh_grades['q' . $i];
        if ($q !== null && $q !== '') $available[] = $q;
    }
    if (!empty($available)) {
        $mapeh_grades['final'] = round(array_sum($available) / count($available));
    }
    
    return $mapeh_grades;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card (SF9) - SmartCard</title>
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
        .student-item.active { background-color: #eff6ff; border-left: 4px solid #3b82f6; }
        .student-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.1rem; }
        .student-lrn { font-size: 0.75rem; color: #64748b; }
        .pdf-viewer-container { flex-grow: 1; background: #525659; }
    </style>
</head>
<body>
    <?php include '../../navs/teacherNav.php'; ?>
    
    <div class="breadcrumb-container">
        <h1 class="page-title">Report Card (SF9)</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../record.php">Reports</a></li>
                <li class="breadcrumb-item active">Report Card</li>
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
                    if ($students_result && $students_result->num_rows > 0):
                        while ($row = $students_result->fetch_assoc()):
                            $isActive = ($student_id == $row['StudentID']) ? 'active' : '';
                    ?>
                        <a href="?student_id=<?php echo $row['StudentID']; ?>" class="student-item <?php echo $isActive; ?>">
                            <div class="student-name"><?php echo strtoupper($row['LastName'] . ', ' . $row['FirstName']); ?></div>
                            <div class="student-lrn">LRN: <?php echo $row['LRN']; ?></div>
                        </a>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="p-4 text-center text-muted small">No students found in your advisory class.</div>
                    <?php endif; ?>
                </div>
                <div class="p-3 border-top bg-light mt-auto">
                    <label class="form-label small fw-bold text-muted mb-1">Batch Print Class</label>
                    <select class="form-select form-select-sm mb-2" id="batchPrintOptions">
                        <option value="both">Front & Back Pages</option>
                        <option value="front">Front Pages Only</option>
                        <option value="back">Back Pages Only</option>
                    </select>
                    <button id="btnBatchPrint" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-print me-2"></i>Generate All
                    </button>
                </div>
                <div class="p-3 border-top">
                    <a href="../record.php" class="btn btn-outline-secondary w-100 btn-sm">Back to Reports</a>
                </div>
            </div>

            <!-- Main Content: PDF Viewer -->
            <div class="pdf-viewer-container d-flex flex-column">
                <?php if ($student_id): ?>
                    <div class="bg-white p-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold ms-2"><?php echo strtoupper($student['LastName'] . ', ' . $student['FirstName']); ?></span>
                        <div class="d-flex gap-2">
                            <a href="generate_report_card_pdf.php?student_id=<?php echo $student_id; ?>&school_year=<?php echo urlencode($current_year); ?>&download=1" class="btn btn-primary btn-sm">
                                <i class="fas fa-download me-2"></i>Download
                            </a>
                            <button onclick="document.getElementById('pdf-iframe').contentWindow.print()" class="btn btn-secondary btn-sm">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                        </div>
                    </div>
                    <iframe id="pdf-iframe" src="generate_report_card_pdf.php?student_id=<?php echo $student_id; ?>&school_year=<?php echo urlencode($current_year); ?>" class="w-100 h-100 border-0"></iframe>
                <?php else: ?>
                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white opacity-50">
                        <i class="fas fa-id-card fa-4x mb-3"></i>
                        <h5>Select a student to view report card</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Simple search functionality
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

        // Batch print functionality
        document.getElementById('btnBatchPrint').addEventListener('click', function() {
            const side = document.getElementById('batchPrintOptions').value;
            const url = 'generate_report_card_pdf.php?batch=all&school_year=<?php echo urlencode($current_year); ?>&side=' + side;
            document.getElementById('pdf-iframe').src = url;
            
            // Remove active class from individual students
            document.querySelectorAll('.student-item').forEach(item => item.classList.remove('active'));
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
>
