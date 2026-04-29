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
$side        = $_GET['side'] ?? 'both';

if (!$school_year) {
    die("Missing school year");
}
if (!$student_id && $batch !== 'all') {
    die("Missing student id");
}

$student_ids_to_process = [];
if ($batch === 'all') {
    $user_id = $_SESSION['user_id'];
    $teacher_stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
    $teacher_stmt->bind_param("i", $user_id);
    $teacher_stmt->execute();
    $teacher_res = $teacher_stmt->get_result()->fetch_assoc();
    $teacher_id = $teacher_res['TeacherID'] ?? 0;

    $advisory_stmt = $conn->prepare("SELECT SectionID FROM section WHERE AdviserID = ?");
    $advisory_stmt->bind_param("i", $teacher_id);
    $advisory_stmt->execute();
    $advisory = $advisory_stmt->get_result()->fetch_assoc();
    $advisory_section_id = $advisory['SectionID'] ?? 0;

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
} else {
    $student_ids_to_process[] = $student_id;
}

if (empty($student_ids_to_process)) {
    die("No students found.");
}

// ─── Helper ────────────────────────────────────────────────────────────────
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  PDF SETUP
// ═══════════════════════════════════════════════════════════════════════════════
class ReportCardPDF extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

$pdf = new ReportCardPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->SetFont('times', '', 8);

// FIX: Capture the default paddings so we can safely restore them without leaking state between students
$default_paddings = $pdf->getCellPaddings();

foreach ($student_ids_to_process as $current_student_id) {
// ─── Student Info ───────────────────────────────────────────────────────────
$stu_stmt = $conn->prepare("SELECT * FROM student WHERE StudentID = ?");
$stu_stmt->bind_param("i", $current_student_id);
$stu_stmt->execute();
$student = $stu_stmt->get_result()->fetch_assoc();
if (!$student) die("Student not found");

$age = '';
if (!empty($student['Birthdate'])) {
    $age = date_diff(date_create($student['Birthdate']), date_create('today'))->y;
}
$sex = ucfirst($student['Sex'] ?? '');
$full_name = strtoupper($student['LastName'] . ', ' . $student['FirstName']);

// ─── Section / Adviser ─────────────────────────────────────────────────────
$sec_stmt = $conn->prepare(
    "SELECT s.* FROM section s
     JOIN section_enrollment se ON s.SectionID = se.SectionID
     WHERE se.StudentID = ? AND se.SchoolYear = ? AND se.status = 'active'
     LIMIT 1"
);
$sec_stmt->bind_param("is", $current_student_id, $school_year);
$sec_stmt->execute();
$section = $sec_stmt->get_result()->fetch_assoc();

$teacher_name = "Class Adviser";
if ($section && $section['AdviserID']) {
    $t_stmt = $conn->prepare("SELECT fName, lName, mName FROM teacher WHERE TeacherID = ?");
    $t_stmt->bind_param("i", $section['AdviserID']);
    $t_stmt->execute();
    $t = $t_stmt->get_result()->fetch_assoc();
    if ($t) {
        $teacher_name = trim(
            $t['fName'] . ' ' .
            ($t['mName'] ? $t['mName'] . ' ' : '') .
            $t['lName']
        );
    }
}

// ─── Principal / School Head ────────────────────────────────────────────────
$head_res    = $conn->query("SELECT FullName FROM admin WHERE Position = 'Principal' AND status = 'active' LIMIT 1");
$head_teacher = ($head_res && $head_res->num_rows > 0)
    ? $head_res->fetch_assoc()['FullName']
    : 'School Head';

// ─── School Details ─────────────────────────────────────────────────────────
$sd_res = $conn->query("SELECT * FROM school_details ORDER BY id DESC LIMIT 1");
$sd = ($sd_res && $sd_res->num_rows > 0) ? $sd_res->fetch_assoc() : [];
$sd_school_name    = strtoupper($sd['school_name']    ?? 'School Name');
$sd_school_address = $sd['school_address'] ?? 'School Address';
$sd_sub_office     = ($sd['sub_office']    ?? 'Sub-Office') . ' Sub-Office';
$sd_division       = 'DIVISION OF ' . strtoupper($sd['division'] ?? 'Division');
$sd_region         = strtoupper($sd['region']         ?? 'Region');

// ─── Grades ─────────────────────────────────────────────────────────────────
$grades = [];
$g_stmt = $conn->prepare(
    "SELECT g.*, s.SubjectName FROM grades g
     JOIN subject s ON g.subject = s.SubjectID
     WHERE g.student_id = ? AND g.school_year = ?"
);
$g_stmt->bind_param("is", $current_student_id, $school_year);
$g_stmt->execute();
$g_res = $g_stmt->get_result();
while ($row = $g_res->fetch_assoc()) {
    $grades[$row['SubjectName']] = $row;
}

// ─── Attendance ──────────────────────────────────────────────────────────────
$year = $next_year = '';
if (preg_match('/^(\d{4})-(\d{4})$/', $school_year, $m)) {
    $year      = $m[1];
    $next_year = $m[2];
}

$att_data = [];
$sid      = $section['SectionID'] ?? 0;
$att_stmt = $conn->prepare(
    "SELECT MONTH(Date) as mo,
            COUNT(*) as total,
            SUM(CASE WHEN Status='present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN Status='absent'  THEN 1 ELSE 0 END) as absent
     FROM attendance
     WHERE StudentID = ? AND SectionID = ?
       AND ((YEAR(Date) = ? AND MONTH(Date) >= 6)
         OR (YEAR(Date) = ? AND MONTH(Date) <= 5))
     GROUP BY MONTH(Date), YEAR(Date)
     ORDER BY YEAR(Date), MONTH(Date)"
);
$att_stmt->bind_param("iiii", $current_student_id, $sid, $year, $next_year);
$att_stmt->execute();
$att_res = $att_stmt->get_result();
while ($row = $att_res->fetch_assoc()) {
    $att_data[(int)$row['mo']] = $row;
}

$month_order = [6, 7, 8, 9, 10, 11, 12, 1, 2, 3, 4, 5];
$month_names = [
    6 => 'Jun', 7 => 'Jul', 8 => 'Aug',  9 => 'Sept',
    10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    1 => 'Jan',  2 => 'Feb',  3 => 'Mar',  4 => 'Apr', 5 => 'May'
];

// ─── Observed Values ─────────────────────────────────────────────────────────
$observed_values = [];
if (tableExists($conn, 'student_values')) {
    $v_stmt = $conn->prepare("SELECT * FROM student_values WHERE student_id = ? AND school_year = ?");
    $v_stmt->bind_param("is", $current_student_id, $school_year);
    $v_stmt->execute();
    $v_res = $v_stmt->get_result();
    while ($r = $v_res->fetch_assoc()) {
        $observed_values[(int)$r['quarter']] = $r;
    }
}

// ─── Grade helper ─────────────────────────────────────────────────────────────
if (!function_exists('finalGrade')) {
    function finalGrade(array $g): string {
        $q = array_filter([$g['Q1'] ?? '', $g['Q2'] ?? '', $g['Q3'] ?? '', $g['Q4'] ?? '']);
        if (count($q) === 4) return (string) round(array_sum($q) / 4);
        return $g['Final'] ?? '';
    }
}
if (!function_exists('remarks')) {
    function remarks(string $fin): string {
        if ($fin === '') return '';
        return ((int)$fin >= 75) ? 'Passed' : 'Failed';
    }
}

if ($side === 'both' || $side === 'front') {

// ═══════════════════════════════════════════════════════════════════════════════
//  PAGE 1  (Front):  Attendance (left)  |  Progress Report Card (right)
// ═══════════════════════════════════════════════════════════════════════════════
$pdf->AddPage();

$pageW   = $pdf->getPageWidth();
$margin  = 8;
$gap     = 5;
$colW    = ($pageW - $margin * 2 - $gap) / 2;

$leftX   = $margin;
$rightX  = $margin + $colW + $gap;
$topY    = $margin;

// ── LEFT COLUMN: REPORT ON ATTENDANCE ─────────────────────────────────────────
$pdf->SetXY($leftX, $topY);
$pdf->SetFont('times', 'B', 10);
$pdf->Cell($colW, 6, 'REPORT ON ATTENDANCE', 0, 1, 'C');
$pdf->SetFont('times', '', 8);

$attLabelW = 30;
$attMonthW = round(($colW - $attLabelW) / 13, 2);
$attTotalW = round($colW - $attLabelW - ($attMonthW * 12), 2);
$attRowH   = 10; 

// Header row
$pdf->SetX($leftX);
$pdf->SetFont('times', 'B', 7);
$pdf->Cell($attLabelW, $attRowH, '', 1, 0, 'C');
foreach ($month_names as $m) {
    $pdf->Cell($attMonthW, $attRowH, $m, 1, 0, 'C');
}
$pdf->Cell($attTotalW, $attRowH, 'Total', 1, 1, 'C');
$pdf->SetFont('times', '', 8);

// Data rows
$rows = [
    ['No. of school days',  'total'],
    ['No. of days present', 'present'],
    ['No. of days absent',  'absent'],
    ['No. of days tardy',   null],
];
$totals = ['total' => 0, 'present' => 0, 'absent' => 0];

foreach ($rows as [$label, $key]) {
    $pdf->SetX($leftX);
    $pdf->SetFont('times', '', 8);
    $pdf->Cell($attLabelW, $attRowH, $label, 1, 0, 'L');
    $rowTotal = 0;
    foreach ($month_order as $mo) {
        $val = '';
        if ($key && isset($att_data[$mo][$key])) {
            $val = (int)$att_data[$mo][$key] ?: '';
        }
        $pdf->Cell($attMonthW, $attRowH, $val, 1, 0, 'C');
        if ($key && $val !== '') {
            $rowTotal += (int)$val;
            $totals[$key] += (int)$val;
        }
    }
    $pdf->Cell($attTotalW, $attRowH, $key ? ($rowTotal ?: '') : '', 1, 1, 'C');
}

// ── Parent/Guardian Signature section ──────────────────────────────────────────
$pdf->Ln(8);
$pdf->SetX($leftX);
$pdf->SetFont('times', 'B', 10);
$pdf->Cell($colW, 5, "Parent/Guardian's Signature", 0, 1, 'C');
$pdf->SetFont('times', '', 10);
$pdf->Ln(6);

$quarters  = ['First Quarter', 'Second Quarter', 'Third Quarter', 'Fourth Quarter'];
$sigPadL   = 6;    // Left indent
$sigLabelW = 32;   // Fixed width for quarter label
$sigLineW  = 80;   // Longer line to match image
$line1_H   = 5;    // Height of cell for first line (aligns with text base)
$line2_H   = 8;    // Height of cell for second line (creates gap)
$groupGap  = 6;    // Gap between quarters

foreach ($quarters as $q) {
    // First line (with label)
    $pdf->SetX($leftX + $sigPadL);
    $pdf->Cell($sigLabelW, $line1_H, $q, 0, 0, 'L');
    $pdf->Cell($sigLineW,  $line1_H, '', 'B', 1, 'L');

    // Second line
    $pdf->SetX($leftX + $sigPadL + $sigLabelW);
    $pdf->Cell($sigLineW, $line2_H, '', 'B', 1, 'L');

    // Gap between groups
    $pdf->Ln($groupGap);
}


// ── RIGHT COLUMN: SCHOOL FORM 9 / PROGRESS REPORT CARD ───────────────────────
$startRightY = $topY;

$pdf->SetXY($rightX, $startRightY);
$pdf->SetFont('times', 'BI', 9);
$pdf->Cell($colW, 5, 'School Form 9', 0, 1, 'L');

$logoY = $pdf->GetY();
$pdf->SetXY($rightX, $logoY);

$logoPath = '../../img/deped_logo.png';
if (file_exists($logoPath)) {
    $logoW  = 18;
    $logoX  = $rightX + ($colW / 2) - ($logoW / 2);
    $logoY0 = $pdf->GetY();
    list($imgPxW, $imgPxH) = getimagesize($logoPath);
    $logoH = ($imgPxH / $imgPxW) * $logoW;
    $pdf->Image($logoPath, $logoX, $logoY0, $logoW, $logoH, 'PNG');
    $pdf->SetY($logoY0 + $logoH + 1);
} else {
    $pdf->Ln(3);
}

$oldEnglishFont = 'times'; 
$oldEnglishStyle = 'I';
$ttfCandidates = [
    '../../fonts/OLDENGL.TTF', 
    '/usr/share/fonts/truetype/OLDENGL.TTF',
    'C:/Windows/Fonts/OLDENGL.TTF',
];
foreach ($ttfCandidates as $ttf) {
    if (file_exists($ttf)) {
        $registered = TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 96);
        if ($registered) { $oldEnglishFont = $registered; $oldEnglishStyle = ''; }
        break;
    }
}

$pdf->SetX($rightX);
$pdf->SetFont($oldEnglishFont, $oldEnglishStyle, 10);
$pdf->Cell($colW, 4, 'Republic of the Philippines', 0, 1, 'C');

$pdf->SetX($rightX);
$pdf->SetFont($oldEnglishFont, $oldEnglishStyle, 13);
$pdf->Cell($colW, 6, 'Department of Education', 0, 1, 'C');
$pdf->SetFont('times', '', 8);

$pdf->SetX($rightX);
$pdf->SetFont('times', '', 7);
$pdf->Cell($colW, 3, $sd_region, 0, 1, 'C');

$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 7);
$pdf->Cell($colW, 3, $sd_division, 0, 1, 'C');

$pdf->SetX($rightX);
$pdf->SetFont('times', '', 7);
$pdf->Cell($colW, 3, $sd_sub_office, 0, 1, 'C');

$pdf->SetX($rightX);
$pdf->SetFont('times', '', 8);
$pdf->Cell($colW, 3, $sd_school_name, 0, 1, 'C');

$pdf->SetX($rightX);
$pdf->SetFont('times', '', 7);
$pdf->Cell($colW, 3, $sd_school_address, 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 12);
$pdf->Cell($colW, 6, 'PROGRESS REPORT CARD', 0, 1, 'C');

$pdf->SetX($rightX);
$pdf->SetFont('times', '', 9);
$pdf->Cell($colW, 4, 'School Year ' . $school_year, 0, 1, 'C');

$pdf->Ln(3);

$pdf->SetFont('times', '', 9);
$fldLabelW = 14;
$fldValW   = $colW - $fldLabelW;

// Name
$pdf->SetX($rightX);
$pdf->Cell($fldLabelW, 5, 'Name:', 0, 0, 'L');
$pdf->SetFont('times', 'B', 9);
$pdf->Cell($fldValW, 5, $full_name, 'B', 1, 'L');
$pdf->SetFont('times', '', 9);

// LRN / Sex
$halfW    = ($colW - $fldLabelW) / 2;
$lblSmall = 8;
$pdf->SetX($rightX);
$pdf->Cell($fldLabelW, 5, 'LRN:', 0, 0, 'L');
$pdf->Cell($halfW, 5, $student['LRN'] ?? '', 'B', 0, 'L');
$pdf->Cell($lblSmall, 5, 'Sex:', 0, 0, 'L');
$pdf->Cell($halfW - $lblSmall, 5, $sex, 'B', 1, 'L');

// Age / Section
$pdf->SetX($rightX);
$pdf->Cell($fldLabelW, 5, 'Age:', 0, 0, 'L');
$pdf->Cell($halfW, 5, $age, 'B', 0, 'L');
$pdf->Cell($lblSmall + 2, 5, 'Section:', 0, 0, 'L');
$pdf->Cell($halfW - $lblSmall - 2, 5, $section['SectionName'] ?? '', 'B', 1, 'L');

// Grade
$pdf->SetX($rightX);
$pdf->Cell($fldLabelW, 5, 'Grade:', 0, 0, 'L');
$pdf->Cell($colW - $fldLabelW, 5, $section['GradeLevel'] ?? '', 'B', 1, 'L');

$pdf->Ln(3);

$pdf->SetX($rightX);
$pdf->SetFont('times', '', 9);
$pdf->Cell($colW, 4, 'Dear Parent:', 0, 1, 'L');
$pdf->SetFont('times', 'I', 8);
$pdf->SetX($rightX);
$dearText1 = "      This report card shows the ability and progress your child has made in the different learning areas as well as his/her progress in core values.";
$pdf->MultiCell($colW, 4, $dearText1, 0, 'J');
$pdf->SetX($rightX);
$pdf->MultiCell($colW, 4, "      The school welcomes you should you desire to know more about your child's progress.", 0, 'J');

$pdf->Ln(4);

$sigColW = $colW / 2;

$pdf->SetX($rightX + $sigColW);
$pdf->SetFont('times', 'B', 9);
$pdf->Cell($sigColW, 5, strtoupper($teacher_name), 'B', 1, 'C');
$pdf->SetX($rightX + $sigColW);
$pdf->SetFont('times', 'I', 8);
$pdf->Cell($sigColW, 4, 'Teacher', 0, 1, 'C');

$pdf->Ln(2);

$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 9);
$pdf->Cell($sigColW, 5, strtoupper($head_teacher), 'B', 1, 'C');
$pdf->SetX($rightX);
$pdf->SetFont('times', 'I', 8);
$pdf->Cell($sigColW, 4, 'School Head', 0, 1, 'C');

$pdf->Ln(3);

$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 9);
$pdf->Cell($colW, 5, 'CERTIFICATE OF TRANSFER', 'T', 1, 'C');

$pdf->SetFont('times', '', 8);
$ctL1 = 28; $ctV1 = 28; $ctL2 = 16; $ctV2 = $colW - $ctL1 - $ctV1 - $ctL2;
$pdf->SetX($rightX);
$pdf->Cell($ctL1, 5, 'Admitted to Grade:', 0, 0, 'L');
$pdf->Cell($ctV1, 5, '', 'B', 0);
$pdf->Cell($ctL2, 5, 'Section:', 0, 0, 'L');
$pdf->Cell($ctV2, 5, '', 'B', 1);

$pdf->SetX($rightX);
$pdf->Cell(44, 5, 'Eligibility for Admission to Grade:', 0, 0, 'L');
$pdf->Cell($colW - 44, 5, '', 'B', 1);

$pdf->Ln(2);
$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 8);
$pdf->Cell($colW, 4, 'Approved:', 0, 1, 'L');

$pdf->SetFont('times', 'I', 8);
$appW = ($colW - 10) / 2;
$pdf->SetX($rightX);
$pdf->Cell($appW, 5, '', 'B', 0, 'C');
$pdf->Cell(10, 5, '', 0, 0);
$pdf->Cell($appW, 5, '', 'B', 1, 'C');
$pdf->SetX($rightX);
$pdf->Cell($appW, 4, 'Teacher', 0, 0, 'C');
$pdf->Cell(10, 4, '', 0, 0);
$pdf->Cell($appW, 4, 'School Head', 0, 1, 'C');

$pdf->Ln(3);
$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 9);
$pdf->Cell($colW, 4, 'Cancellation of Eligibility to Transfer', 0, 1, 'C');

$pdf->SetFont('times', '', 8);
$pdf->SetX($rightX);
$pdf->Cell(18, 5, 'Admitted in:', 0, 0, 'L');
$pdf->Cell($colW - 18, 5, '', 'B', 1);

$pdf->SetX($rightX);
$pdf->Cell(10, 5, 'Date:', 0, 0, 'L');
$pdf->Cell(40, 5, '', 'B', 1);

$pdf->Ln(4);
$pdf->SetX($rightX + $appW + 10);
$pdf->Cell($appW, 5, '', 'B', 1, 'C');
$pdf->SetX($rightX + $appW + 10);
$pdf->SetFont('times', 'I', 8);
$pdf->Cell($appW, 4, 'Principal', 0, 1, 'C');

} // End Front Page

if ($side === 'both' || $side === 'back') {

// ═══════════════════════════════════════════════════════════════════════════════
//  PAGE 2  (Back):  Grades (left)  |  Observed Values (right)
// ═══════════════════════════════════════════════════════════════════════════════
$pdf->AddPage();

$rowH    = 9;   
$valRowH = 8;   

$pageW     = $pdf->getPageWidth();
$margin    = 8; $gap = 5;
$colW      = ($pageW - $margin * 2 - $gap) / 2;
$leftColW  = $colW;   
$rightColW = $colW;
$leftX     = $margin;
$rightX    = $margin + $colW + $gap;
$topY      = $margin;

// ───────────────────────────────────────────────────────────────────────────────
//  LEFT COLUMN: LEARNING PROGRESS AND ACHIEVEMENT
// ───────────────────────────────────────────────────────────────────────────────
$pdf->SetXY($leftX, $topY);
$pdf->SetFont('times', 'BI', 11);
$pdf->Cell($leftColW, 5, 'GRADE 7 - 10', 0, 1, 'L');
$pdf->Ln(1);
$pdf->SetX($leftX);
$pdf->SetFont('times', 'B', 11);
$pdf->Cell($leftColW, 5, 'REPORT ON LEARNING PROGRESS AND ACHIEVEMENT', 0, 1, 'L');
$pdf->Ln(2);

$subjectW = 58;
$qW       = 10;      
$finW     = 20;      
$remW     = $leftColW - $subjectW - (4 * $qW) - $finW;  

// ── 2-row merged table header ─────────────────────────────────────────────────
$hH  = $rowH * 2;      
$hY  = $pdf->GetY();
$hX  = $leftX;

$pdf->SetFont('times', 'B', 11);

$pdf->MultiCell($subjectW, $hH, "Learning Areas",    1, 'C', false, 0, $hX, $hY);
$pdf->MultiCell($qW * 4,   $rowH, 'Quarter',         1, 'C', false, 0, $hX + $subjectW, $hY);
$pdf->MultiCell($finW,     $hH, "Final\nGrade",      1, 'C', false, 0, $hX + $subjectW + $qW * 4, $hY);
$pdf->MultiCell($remW,     $hH, 'Remarks',           1, 'C', false, 0, $hX + $subjectW + $qW * 4 + $finW, $hY);

$qNumY = $hY + $rowH;
foreach ([1, 2, 3, 4] as $i => $q) {
    $pdf->MultiCell($qW, $rowH, $q, 1, 'C', false, 0, $hX + $subjectW + $i * $qW, $qNumY);
}
$pdf->SetY($hY + $hH);

// ── Main subject rows (dynamic height) ────────────────────────────────────────
$main_subjects = [
    'Filipino',
    'English',
    'Mathematics',
    'Science',
    'Araling Panlipunan (AP)',
    'Values Education (VE)',
    'Technology and Livelihood Education (TLE)',
];

$avg_sum = 0;
$avg_cnt = 0;
$lineH = 5.5;   

foreach ($main_subjects as $sub) {
    $g   = $grades[$sub] ?? [];
    $fin = finalGrade($g);
    if ($fin !== '') { $avg_sum += (int)$fin; $avg_cnt++; }

    $maxLines = 1;
    $maxLines = max($maxLines, $pdf->getNumLines($sub, $subjectW, false, true, '', 1));
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $maxLines = max($maxLines, $pdf->getNumLines($g[$q] ?? '', $qW, false, true, '', 1));
    }
    $maxLines = max($maxLines, $pdf->getNumLines($fin, $finW, false, true, '', 1));
    $maxLines = max($maxLines, $pdf->getNumLines(remarks($fin), $remW, false, true, '', 1));
    $dynRowH = $maxLines * $lineH;

    $rY = $pdf->GetY();
    $pdf->SetFont('times', '', 11);
    $pdf->MultiCell($subjectW, $dynRowH, $sub,          1, 'L', false, 0, $hX,                                    $rY);
    foreach (['Q1','Q2','Q3','Q4'] as $qi => $q) {
        $pdf->MultiCell($qW, $dynRowH, $g[$q] ?? '',    1, 'C', false, 0, $hX + $subjectW + $qi * $qW,          $rY);
    }
    $pdf->SetFont('times', 'B', 11);
    $pdf->MultiCell($finW, $dynRowH, $fin,               1, 'C', false, 0, $hX + $subjectW + $qW * 4,             $rY);
    $pdf->SetFont('times', '', 11);
    $pdf->MultiCell($remW, $dynRowH, remarks($fin),      1, 'C', false, 0, $hX + $subjectW + $qW * 4 + $finW,   $rY);
    $pdf->SetY($rY + $dynRowH);
}

// ── MAPEH main row (dynamic height) ───────────────────────────────────────────
$mapeh_comps = ['Music', 'Arts', 'Physical Education', 'Health'];
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
$m_fin = ($mq1 && $mq2 && $mq3 && $mq4)
    ? (string) round(($mq['Q1'] + $mq['Q2'] + $mq['Q3'] + $mq['Q4']) / array_sum($mc))
    : '';
if ($m_fin !== '') { $avg_sum += (int)$m_fin; $avg_cnt++; }

$maxLines = 1;
$maxLines = max($maxLines, $pdf->getNumLines('MAPEH', $subjectW, false, true, '', 1));
foreach ([$mq1, $mq2, $mq3, $mq4] as $v) {
    $maxLines = max($maxLines, $pdf->getNumLines($v, $qW, false, true, '', 1));
}
$maxLines = max($maxLines, $pdf->getNumLines($m_fin, $finW, false, true, '', 1));
$maxLines = max($maxLines, $pdf->getNumLines(remarks($m_fin), $remW, false, true, '', 1));
$dynRowH_mapeh = $maxLines * $lineH;

$rY = $pdf->GetY();
$pdf->SetFont('times', '', 11);
$pdf->MultiCell($subjectW, $dynRowH_mapeh, 'MAPEH',       1, 'L', false, 0, $hX,                               $rY);
foreach ([$mq1, $mq2, $mq3, $mq4] as $i => $v) {
    $pdf->MultiCell($qW, $dynRowH_mapeh, $v,              1, 'C', false, 0, $hX + $subjectW + $i * $qW,       $rY);
}
$pdf->SetFont('times', 'B', 11);
$pdf->MultiCell($finW, $dynRowH_mapeh, $m_fin,            1, 'C', false, 0, $hX + $subjectW + $qW * 4,        $rY);
$pdf->SetFont('times', '', 11);
$pdf->MultiCell($remW, $dynRowH_mapeh, remarks($m_fin),   1, 'C', false, 0, $hX + $subjectW + $qW * 4 + $finW, $rY);
$pdf->SetY($rY + $dynRowH_mapeh);

if (!function_exists('mapeh_sub_avg')) {
    function mapeh_sub_avg(array $grades, array $comps, string $q): string {
        $sum = 0; $cnt = 0;
        foreach ($comps as $c) {
            if (!empty($grades[$c][$q])) { $sum += $grades[$c][$q]; $cnt++; }
        }
        return $cnt > 0 ? (string) round($sum / $cnt) : '';
    }
}

// ── MAPEH sub-rows (Music & Arts / PE & Health) with dynamic heights ──────────
foreach ([
    ['  • Music and Arts',  ['Music', 'Arts']],
    ['  • PE and Health',   ['Physical Education', 'Health']],
] as [$label, $comps]) {
    $vals = [];
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $vals[$q] = mapeh_sub_avg($grades, $comps, $q);
    }
    $sub_fin = ($vals['Q1'] && $vals['Q2'] && $vals['Q3'] && $vals['Q4'])
        ? (string) round((array_sum(array_map('intval', $vals))) / 4)
        : '';

    $maxLines = 1;
    $maxLines = max($maxLines, $pdf->getNumLines($label, $subjectW, false, true, '', 1));
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $maxLines = max($maxLines, $pdf->getNumLines($vals[$q], $qW, false, true, '', 1));
    }
    $maxLines = max($maxLines, $pdf->getNumLines($sub_fin, $finW, false, true, '', 1));
    $dynRowH_sub = $maxLines * $lineH;

    $rY = $pdf->GetY();
    $pdf->SetFont('times', '', 11);
    $pdf->MultiCell($subjectW, $dynRowH_sub, $label,      1, 'L', false, 0, $hX,                               $rY);
    foreach (['Q1','Q2','Q3','Q4'] as $qi => $q) {
        $pdf->MultiCell($qW, $dynRowH_sub, $vals[$q],     1, 'C', false, 0, $hX + $subjectW + $qi * $qW,      $rY);
    }
    $pdf->SetFont('times', 'B', 11);
    $pdf->MultiCell($finW, $dynRowH_sub, $sub_fin,        1, 'C', false, 0, $hX + $subjectW + $qW * 4,        $rY);
    $pdf->SetFont('times', '', 11);
    $pdf->MultiCell($remW, $dynRowH_sub, '',              1, 'C', false, 0, $hX + $subjectW + $qW * 4 + $finW, $rY);
    $pdf->SetY($rY + $dynRowH_sub);
}

// ── General Average row ────────────────────────────────────────────────────────
$gen_avg = $avg_cnt > 0 ? (string) round($avg_sum / $avg_cnt) : '';
$rY = $pdf->GetY();
$pdf->SetFont('times', 'B', 11);
$pdf->MultiCell($subjectW + $qW * 4, $rowH, 'General Average',  1, 'R', false, 0, $hX,                               $rY);
$pdf->MultiCell($finW,                $rowH, $gen_avg,          1, 'C', false, 0, $hX + $subjectW + $qW * 4,      $rY);
$pdf->MultiCell($remW,                $rowH, remarks($gen_avg), 1, 'C', false, 0, $hX + $subjectW + $qW*4 + $finW, $rY);
$pdf->SetY($rY + $rowH);

// ── Grading Scale legend ───────────────────────────────────────────────────────
$pdf->Ln(5);
$pdf->SetX($leftX);
$pdf->SetFont('times', 'BI', 11);

$descW   = $leftColW * 0.42;
$scaleW  = $leftColW * 0.28;
$remarkW = $leftColW - $descW - $scaleW;

$pdf->Cell($descW,   4, 'Descriptors',   0, 0, 'L');
$pdf->Cell($scaleW,  4, 'Grading Scale', 0, 0, 'L');
$pdf->Cell($remarkW, 4, 'Remarks',       0, 1, 'L');

$pdf->SetFont('times', 'I', 11);
$grading = [
    ['Outstanding',               '90-100',   'Passed'],
    ['Very Satisfactory',         '85-89',    'Passed'],
    ['Satisfactory',              '80-84',    'Passed'],
    ['Fairly Satisfactory',       '75-79',    'Passed'],
    ['Did Not Meet Expectations', 'Below 75', 'Failed'],
];
foreach ($grading as [$d, $s, $r]) {
    $pdf->SetX($leftX);
    $pdf->Cell($descW,   4, $d, 0, 0, 'L');
    $pdf->Cell($scaleW,  4, $s, 0, 0, 'L');
    $pdf->Cell($remarkW, 4, $r, 0, 1, 'L');
}

// ───────────────────────────────────────────────────────────────────────────────
//  RIGHT COLUMN: REPORT ON LEARNER'S OBSERVED VALUES
// ───────────────────────────────────────────────────────────────────────────────
$pdf->SetXY($rightX, $topY);

$pdf->SetFont('times', 'B', 11);
$pdf->Cell($rightColW, 5, "REPORT ON LEARNER'S OBSERVED VALUES", 0, 1, 'C');
$pdf->Ln(2);

$coreW   = 28;
$behavW  = 60;
$qTotalW = $rightColW - $coreW - $behavW;  
$qSmW    = 12.5;
$q4AdjW  = 12.5;

$vhY  = $pdf->GetY();
$vhSR = $rowH;        
$vhH  = $rowH * 2;    
$vhX  = $rightX;

$pdf->SetFont('times', 'B', 11);  

$lineH_vals = (11 / 72 * 25.4) * $pdf->getCellHeightRatio();
$vPadTall  = ($vhH  - $lineH_vals) / 2;
$vPadShort = ($vhSR - $lineH_vals) / 2;
$hPad = 1;

$pdf->setCellPaddings($hPad, $vPadTall, $hPad, 0);
$pdf->MultiCell($coreW,  $vhH, 'Core Values',         1, 'C', false, 0, $vhX,          $vhY);

$pdf->setCellPaddings($hPad, $vPadTall, $hPad, 0);
$pdf->MultiCell($behavW, $vhH, 'Behavior Statements', 1, 'C', false, 0, $vhX + $coreW, $vhY);

$pdf->setCellPaddings($hPad, $vPadShort, $hPad, 0);
$pdf->MultiCell($qTotalW, $vhSR, 'Quarter', 1, 'C', false, 0, $vhX + $coreW + $behavW, $vhY);

$qNumY = $vhY + $vhSR;
$qX    = $vhX + $coreW + $behavW;
foreach ([1, 2, 3, 4] as $qi) {
    $w = ($qi < 4) ? $qSmW : $q4AdjW;
    $pdf->setCellPaddings($hPad, $vPadShort, $hPad, 0);
    $pdf->MultiCell($w, $vhSR, $qi, 1, 'C', false, 0, $qX, $qNumY);
    $qX += $w;
}

// FIX: Safely reset to standard TCPDF paddings using the previously saved defaults
$pdf->setCellPaddings($default_paddings['L'], $default_paddings['T'], $default_paddings['R'], $default_paddings['B']);
$pdf->SetY($vhY + $vhH);

$value_groups = [
    [
        'label'     => '1. Maka-Diyos',
        'behaviors' => [
            ["Expresses one's spiritual beliefs while respecting the spiritual beliefs of others.", 'makadiyos_1'],
            ['Shows adherence to ethical principles by upholding truth.',                           'makadiyos_2'],
        ],
    ],
    [
        'label'     => '2. Makatao',
        'behaviors' => [
            ['Is sensitive to individual, social and cultural differences.',    'makatao_1'],
            ['Demonstrates contributions toward solidarity.',                   'makatao_2'],
        ],
    ],
    [
        'label'     => '3. Maka-kalikasan',
        'behaviors' => [
            ['Cares for the environment and utilizes resources wisely, judiciously, and economically.', 'makakalikasan_1'],
        ],
    ],
    [
        'label'     => '4. Makabansa',
        'behaviors' => [
            ['Demonstrates pride in being a Filipino; exercises rights and responsibilities of a Filipino citizen.',    'makabansa_1'],
            ['Demonstrates appropriate behavior in carrying out activities in the school, community and country.',      'makabansa_2'],
        ],
    ],
];

$pdf->SetFont('times', '', 11);

foreach ($value_groups as $group) {
    $rowHeights = [];
    foreach ($group['behaviors'] as [$behavior, $field]) {
        $nbLines      = $pdf->getNumLines($behavior, $behavW, false, true, '', 1);
        $rowHeights[] = max($valRowH, $nbLines * 5.5);
    }
    $groupH  = array_sum($rowHeights);
    $groupY  = $pdf->GetY();

    $labelText = $group['label'];
    $labelLines = $pdf->getNumLines($labelText, $coreW, false, true, '', 1);
    $labelTextH = $labelLines * 5.5;
    $vPadCore = ($groupH - $labelTextH) / 2;
    if ($vPadCore < 0) $vPadCore = 0;

    $pdf->setCellPaddings($hPad, $vPadCore, $hPad, 0);
    $pdf->MultiCell($coreW, $groupH, $labelText, 1, 'C', false, 0, $rightX, $groupY);
    
    // FIX: Safely reset to standard TCPDF paddings
    $pdf->setCellPaddings($default_paddings['L'], $default_paddings['T'], $default_paddings['R'], $default_paddings['B']);

    $bY = $groupY;
    foreach ($group['behaviors'] as $i => [$behavior, $field]) {
        $rH = $rowHeights[$i];

        $bhLines = $pdf->getNumLines($behavior, $behavW, false, true, '', 1);
        $textH = $bhLines * 5.5;
        $vPadBehav = ($rH - $textH) / 2;
        if ($vPadBehav < 0) $vPadBehav = 0;
        $pdf->setCellPaddings($hPad, $vPadBehav, $hPad, 0);
        $pdf->MultiCell($behavW, $rH, $behavior, 1, 'C', false, 0, $rightX + $coreW, $bY);
        
        // FIX: Safely reset to standard TCPDF paddings
        $pdf->setCellPaddings($default_paddings['L'], $default_paddings['T'], $default_paddings['R'], $default_paddings['B']);

        $qX2 = $rightX + $coreW + $behavW;
        foreach ([1, 2, 3, 4] as $qi) {
            $val = $observed_values[$qi][$field] ?? '';
            $w   = ($qi < 4) ? $qSmW : $q4AdjW;

            $vPadQ = ($rH - 5.5) / 2;   
            if ($vPadQ < 0) $vPadQ = 0;
            $pdf->setCellPaddings($hPad, $vPadQ, $hPad, 0);
            $pdf->MultiCell($w, $rH, $val, 1, 'C', false, 0, $qX2, $bY);
            
            // FIX: Safely reset to standard TCPDF paddings
            $pdf->setCellPaddings($default_paddings['L'], $default_paddings['T'], $default_paddings['R'], $default_paddings['B']);

            $qX2 += $w;
        }
        $bY += $rH;
    }

    $pdf->SetY($groupY + $groupH);
}

$pdf->Ln(5);
$pdf->SetX($rightX);
$pdf->SetFont('times', 'B', 11);
$markLabelW = $rightColW * 0.30;
$markDescW  = $rightColW - $markLabelW;
$pdf->Cell($markLabelW, 5, 'Marking',              0, 0, 'C');
$pdf->Cell($markDescW,  5, 'Non-numerical Rating', 0, 1, 'C');

$pdf->SetFont('times', '', 11);
foreach ([
    ['AO', 'Always Observed'],
    ['SO', 'Sometimes Observed'],
    ['RO', 'Rarely Observed'],
    ['NO', 'Not Observed'],
] as [$mark, $desc]) {
    $pdf->SetX($rightX);
    $pdf->Cell($markLabelW, 4, $mark, 0, 0, 'C');
    $pdf->Cell($markDescW,  4, $desc, 0, 1, 'C');
}
} // End Back Page
} // End student loop

// ─── Output ──────────────────────────────────────────────────────────────────
$filename = 'ReportCard' . ($batch === 'all' ? 's_Batch' : '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $student['LastName'])) . '.pdf';
$pdf->Output($filename, 'I');