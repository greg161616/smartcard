<?php
require_once '../../vendor/autoload.php';
include '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    die("Unauthorized");
}

$student_id  = $_GET['student_id']  ?? '';
$school_year = $_GET['school_year'] ?? '';
$batch       = $_GET['batch'] ?? '';
$download    = $_GET['download'] ?? '';

if (!$school_year) {
    die("Missing school year");
}
$render_blank = false;
if (!$student_id && $batch !== 'all') {
    $render_blank = true;
}

$student_ids_to_process = [];
$user_id = $_SESSION['user_id'];

// Get teacher advisory class
$teacher_stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
$teacher_stmt->bind_param("i", $user_id);
$teacher_stmt->execute();
$teacher_res = $teacher_stmt->get_result()->fetch_assoc();
$teacher_id = $teacher_res['TeacherID'] ?? 0;
$teacher_stmt->close();

if ($render_blank) {
    $student_ids_to_process = [0];
} elseif ($batch === 'all') {
    $advisory_stmt = $conn->prepare("SELECT SectionID FROM section WHERE AdviserID = ?");
    $advisory_stmt->bind_param("i", $teacher_id);
    $advisory_stmt->execute();
    $advisory = $advisory_stmt->get_result()->fetch_assoc();
    $advisory_section_id = $advisory['SectionID'] ?? 0;
    $advisory_stmt->close();

    $students_sql = "
        SELECT DISTINCT s.StudentID
        FROM student s
        JOIN section_enrollment se ON s.StudentID = se.StudentID
        WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
        ORDER BY s.LastName, s.FirstName
    ";
    $stu_list_stmt = $conn->prepare($students_sql);
    $stu_list_stmt->bind_param("is", $advisory_section_id, $school_year);
    $stu_list_stmt->execute();
    $res = $stu_list_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $student_ids_to_process[] = $row['StudentID'];
    }
    $stu_list_stmt->close();
} else {
    $student_ids_to_process[] = $student_id;
}

if (empty($student_ids_to_process)) {
    die("No students found.");
}

// Helper logic for calculating average
function finalGrade(array $g): string {
    $q = array_filter([$g['Q1'] ?? '', $g['Q2'] ?? '', $g['Q3'] ?? '', $g['Q4'] ?? '']);
    if (count($q) === 4) return (string) round(array_sum($q) / 4);
    return $g['Final'] ?? '';
}

class CertificatePDF extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

$pdf = new CertificatePDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

// School Details
$sd_res = $conn->query("SELECT * FROM school_details ORDER BY id DESC LIMIT 1");
$sd = ($sd_res && $sd_res->num_rows > 0) ? $sd_res->fetch_assoc() : [];
$sd_school_name    = strtoupper($sd['school_name']    ?? 'School Name');
$sd_school_address = $sd['school_address'] ?? 'School Address';
$sd_division       = 'DIVISION OF ' . strtoupper($sd['division'] ?? 'Division');
$sd_region         = strtoupper($sd['region']         ?? 'Region');

// Head Teacher / Principal
$head_res = $conn->query("SELECT FullName FROM admin WHERE Position = 'Principal' AND status = 'active' LIMIT 1");
$head_teacher = ($head_res && $head_res->num_rows > 0) ? $head_res->fetch_assoc()['FullName'] : 'School Head';

// Adviser Info
$teacher_name = "Class Adviser";
if ($teacher_id) {
    $t_stmt = $conn->prepare("SELECT fName, lName, mName FROM teacher WHERE TeacherID = ?");
    $t_stmt->bind_param("i", $teacher_id);
    $t_stmt->execute();
    $t = $t_stmt->get_result()->fetch_assoc();
    if ($t) {
        $teacher_name = trim($t['fName'] . ' ' . ($t['mName'] ? $t['mName'] . ' ' : '') . $t['lName']);
    }
    $t_stmt->close();
}

$processed_count = 0;

foreach ($student_ids_to_process as $current_student_id) {
    if ($render_blank) {
        $full_name = "JUAN DELA CRUZ";
        $rank = "WITH HIGHEST HONORS";
        $gen_avg = "98";
        $processed_count++;
    } else {
        // Calculate average
        $main_subjects = ['Filipino', 'English', 'Mathematics', 'Science', 'Araling Panlipunan (AP)', 'Values Education (VE)', 'Technology and Livelihood Education (TLE)'];
        $mapeh_comps = ['Music', 'Arts', 'Physical Education', 'Health'];

        $grades = [];
        $g_stmt = $conn->prepare("SELECT g.*, s.SubjectName FROM grades g JOIN subject s ON g.subject = s.SubjectID WHERE g.student_id = ? AND g.school_year = ?");
        $g_stmt->bind_param("is", $current_student_id, $school_year);
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

        if ($gen_avg < 90) {
            continue; // Only 90 and above
        }

        $rank = '';
        if ($gen_avg >= 98 && $gen_avg <= 100) $rank = 'WITH HIGHEST HONORS';
        elseif ($gen_avg >= 95 && $gen_avg <= 97) $rank = 'WITH HIGH HONORS';
        elseif ($gen_avg >= 90 && $gen_avg <= 94) $rank = 'WITH HONORS';

        // Student Info
        $stu_stmt = $conn->prepare("SELECT * FROM student WHERE StudentID = ?");
        $stu_stmt->bind_param("i", $current_student_id);
        $stu_stmt->execute();
        $student = $stu_stmt->get_result()->fetch_assoc();
        $stu_stmt->close();

        if (!$student) continue;

        $full_name = strtoupper(trim(($student['FirstName'] ?? '') . ' ' . ($student['Middlename'] ? substr($student['Middlename'], 0, 1) . '. ' : '') . ($student['LastName'] ?? '')));

        $processed_count++;
    }
    $pdf->AddPage();

    // Background Borders
    // Outer Border
    $pdf->SetLineWidth(1.5);
    $pdf->SetDrawColor(45, 106, 79); // Primary Dark Green (#2d6a4f)
    $pdf->Rect(10, 10, 277, 190);

    // Inner Border
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(184, 134, 11); // Gold (#b8860b)
    $pdf->Rect(13, 13, 271, 184);

    // Corner flourishes (simple decorative rectangles)
    $pdf->SetFillColor(45, 106, 79);
    $pdf->Rect(10, 10, 15, 15, 'DF');
    $pdf->Rect(272, 10, 15, 15, 'DF');
    $pdf->Rect(10, 185, 15, 15, 'DF');
    $pdf->Rect(272, 185, 15, 15, 'DF');

    $pdf->SetFillColor(184, 134, 11);
    $pdf->Rect(13, 13, 9, 9, 'DF');
    $pdf->Rect(275, 13, 9, 9, 'DF');
    $pdf->Rect(13, 188, 9, 9, 'DF');
    $pdf->Rect(275, 188, 9, 9, 'DF');

    // DepEd Logo & School Logo
    $deped_logo = '../../img/deped_logo.png';
    $school_logo = '../../img/logo.png';
    
    if (file_exists($deped_logo)) {
        $pdf->Image($deped_logo, 30, 25, 22, 0, 'PNG');
    }
    if (file_exists($school_logo)) {
        $pdf->Image($school_logo, 245, 25, 22, 0, 'PNG');
    }

    // Header
    $pdf->SetY(25);
    $pdf->SetFont('times', '', 10);
    $pdf->Cell(0, 5, 'Republic of the Philippines', 0, 1, 'C');
    $pdf->SetFont('times', 'B', 12);
    $pdf->Cell(0, 6, 'DEPARTMENT OF EDUCATION', 0, 1, 'C');
    
    $pdf->SetFont('times', '', 10);
    $pdf->Cell(0, 5, $sd_region, 0, 1, 'C');
    $pdf->Cell(0, 5, $sd_division, 0, 1, 'C');
    
    $pdf->SetFont('times', 'B', 13);
    $pdf->Cell(0, 7, $sd_school_name, 0, 1, 'C');
    
    $pdf->SetFont('times', 'I', 9);
    $pdf->Cell(0, 5, $sd_school_address, 0, 1, 'C');

    $pdf->Ln(10);

    // Title
    $pdf->SetFont('times', 'B', 26);
    $pdf->SetTextColor(45, 106, 79);
    $pdf->Cell(0, 12, 'CERTIFICATE OF RECOGNITION', 0, 1, 'C');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'I', 14);
    $pdf->Cell(0, 8, 'is proudly presented to', 0, 1, 'C');

    $pdf->Ln(5);

    // Student Name
    $pdf->SetFont('times', 'B', 28);
    $pdf->SetTextColor(184, 134, 11);
    $pdf->Cell(0, 14, $full_name, 0, 1, 'C');

    // Underline for name
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(184, 134, 11);
    $pdf->Line(50, $pdf->GetY(), 247, $pdf->GetY());

    $pdf->Ln(6);

    // Achievement details
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', '', 13);
    
    // We can safely output UTF-8 characters as long as font supports it, or stick to standard text
    $text = "for achieving a general average of " . $gen_avg . "% for the School Year " . $school_year;
    $pdf->Cell(0, 8, $text, 0, 1, 'C');

    $pdf->Ln(4);

    // Honors Rank
    $pdf->SetFont('times', 'B', 20);
    $pdf->SetTextColor(45, 106, 79);
    $pdf->Cell(0, 10, $rank, 0, 1, 'C');
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'I', 11);
    $day = date('jS');
    $month = date('F');
    $year = date('Y');
    $pdf->Cell(0, 8, "Given this " . $day . " day of " . $month . ", " . $year . ".", 0, 1, 'C');

    // Signatures
    $pdf->SetY(160);
    $pdf->SetFont('times', 'B', 11);
    
    // Adviser
    $pdf->SetX(40);
    $pdf->Cell(80, 5, strtoupper($teacher_name), 'T', 0, 'C');
    
    // Principal
    $pdf->SetX(177);
    $pdf->Cell(80, 5, strtoupper($head_teacher), 'T', 1, 'C');

    $pdf->SetFont('times', 'I', 10);
    $pdf->SetX(40);
    $pdf->Cell(80, 5, 'Class Adviser', 0, 0, 'C');
    
    $pdf->SetX(177);
    $pdf->Cell(80, 5, 'School Head / Principal', 0, 1, 'C');
}

if ($processed_count === 0) {
    $pdf->AddPage();
    $pdf->SetFont('times', 'B', 16);
    $pdf->Cell(0, 10, 'No students qualified for academic honors.', 0, 1, 'C');
}

// Output
if ($download == '1') {
    $pdf->Output('Honor_Certificates_' . $school_year . '.pdf', 'D');
} else {
    $pdf->Output('Honor_Certificates_' . $school_year . '.pdf', 'I');
}
