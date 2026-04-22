<?php
include '../config.php';
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get parameters
$quarter    = isset($_GET['quarter'])    ? intval($_GET['quarter'])    : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$student_id || $quarter < 1 || $quarter > 4) {
    $_SESSION['error'] = "Invalid parameters for PDF generation.";
    header("Location: record.php");
    exit();
}

// ─── HELPER FUNCTIONS ────────────────────────────────────────────────────────

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function calculateAge($birthdate) {
    if (empty($birthdate) || $birthdate == '0000-00-00') return '';
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

function calculateMAPEHGrade($grades, $mapeh_component_ids) {
    $mapeh_grades = ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null, 'final' => null];
    $component_grades = [
        'music'  => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null],
        'arts'   => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null],
        'pe'     => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null],
        'health' => ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null]
    ];
    $component_map = [];
    $keys = ['music', 'arts', 'pe', 'health'];
    foreach ($mapeh_component_ids as $idx => $cid) {
        if (isset($keys[$idx]) && $cid) $component_map[$cid] = $keys[$idx];
    }
    foreach ($grades as $g) {
        if (isset($component_map[$g['subject']])) {
            $c = $component_map[$g['subject']];
            foreach (['Q1','Q2','Q3','Q4'] as $qi) {
                if (isset($g[$qi]) && $g[$qi] !== '') $component_grades[$c][strtolower($qi)] = $g[$qi];
            }
        }
    }
    for ($i = 1; $i <= 4; $i++) {
        $k = 'q'.$i;
        $m = $component_grades['music'][$k]; $a = $component_grades['arts'][$k];
        $pe = $component_grades['pe'][$k];   $h = $component_grades['health'][$k];
        $ma = ($m!==null&&$a!==null) ? ($m+$a)/2 : ($m!==null?$m:($a!==null?$a:null));
        $ph = ($pe!==null&&$h!==null)? ($pe+$h)/2: ($pe!==null?$pe:($h!==null?$h:null));
        if ($ma!==null&&$ph!==null) $mapeh_grades[$k]=round(($ma+$ph)/2);
        elseif ($ma!==null) $mapeh_grades[$k]=round($ma);
        elseif ($ph!==null) $mapeh_grades[$k]=round($ph);
    }
    $avail = [];
    for ($i=1;$i<=4;$i++){$q=$mapeh_grades['q'.$i];if($q!==null&&$q!=='')$avail[]=$q;}
    if (!empty($avail)) $mapeh_grades['final']=round(array_sum($avail)/count($avail));
    return $mapeh_grades;
}

// ─── FETCH DATA ───────────────────────────────────────────────────────────────

$user_id = $_SESSION['user_id'];

// School year
$sy_q = $conn->query("SELECT school_year FROM school_year WHERE status='active' LIMIT 1");
$sy_row = $sy_q ? $sy_q->fetch_assoc() : null;
$current_year = $sy_row ? $sy_row['school_year'] : '2025-2026';

// Teacher info
$t_stmt = $conn->prepare("SELECT TeacherID, fName, lName FROM teacher WHERE UserID=?");
$t_stmt->bind_param("i", $user_id);
$t_stmt->execute();
$teacher = $t_stmt->get_result()->fetch_assoc();
$t_stmt->close();
$teacher_id = $teacher ? $teacher['TeacherID'] : null;
$teacher_full_name = $teacher ? trim($teacher['fName'].' '.$teacher['lName']) : 'Teacher';

// Head teacher
$head_q = $conn->prepare("SELECT FullName FROM admin WHERE Position='Head teacher' AND status='active' LIMIT 1");
$head_q->execute();
$head = $head_q->get_result()->fetch_assoc();
$head_q->close();
$head_teacher_name = $head ? $head['FullName'] : 'Head Teacher';

// Student
$s_stmt = $conn->prepare("SELECT * FROM student WHERE StudentID=?");
$s_stmt->bind_param("i", $student_id);
$s_stmt->execute();
$student = $s_stmt->get_result()->fetch_assoc();
$s_stmt->close();

if (!$student) {
    $_SESSION['error'] = "Student not found.";
    header("Location: record.php");
    exit();
}

// Section
$sec_stmt = $conn->prepare("
    SELECT s.*, se.SchoolYear
    FROM section_enrollment se
    JOIN section s ON se.SectionID=s.SectionID
    WHERE se.StudentID=? AND se.SchoolYear=? AND se.status='active'
    LIMIT 1
");
$sec_stmt->bind_param("is", $student_id, $current_year);
$sec_stmt->execute();
$section = $sec_stmt->get_result()->fetch_assoc();
$sec_stmt->close();
$section_id = $section ? $section['SectionID'] : 0;

// Grades
$g_stmt = $conn->prepare("SELECT g.*, s.SubjectName FROM grades g JOIN subject s ON g.subject=s.SubjectID WHERE g.student_id=? AND g.school_year=?");
$g_stmt->bind_param("is", $student_id, $current_year);
$g_stmt->execute();
$grades_result = $g_stmt->get_result();
$g_stmt->close();
$grades = [];
while ($row = $grades_result->fetch_assoc()) $grades[] = $row;

// Attendance
$year = $next_year = '';
if (preg_match('/^(\d{4})-(\d{4})$/', $current_year, $m)) { $year=$m[1]; $next_year=$m[2]; }
$att_stmt = $conn->prepare("
    SELECT MONTH(Date) as month_num,
           COUNT(*) as total_days,
           SUM(CASE WHEN Status='present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN Status='absent'  THEN 1 ELSE 0 END) as absent_days
    FROM attendance
    WHERE StudentID=? AND SectionID=?
      AND ((YEAR(Date)=? AND MONTH(Date)>=6) OR (YEAR(Date)=? AND MONTH(Date)<=5))
    GROUP BY MONTH(Date), YEAR(Date)
    ORDER BY YEAR(Date), MONTH(Date)
");
$att_stmt->bind_param("iiii", $student_id, $section_id, $year, $next_year);
$att_stmt->execute();
$att_res = $att_stmt->get_result();
$att_stmt->close();
$att_data = [];
while ($r = $att_res->fetch_assoc()) {
    $att_data[$r['month_num']] = ['present'=>$r['present_days'],'absent'=>$r['absent_days'],'total'=>$r['total_days']];
}
$month_map = [6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sept',10=>'Oct',11=>'Nov',12=>'Dec',1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May'];
$attendance = [];
foreach ($month_map as $num => $name) {
    $attendance[$name] = isset($att_data[$num]) ? $att_data[$num] : ['present'=>0,'absent'=>0,'total'=>0];
}

// Observed values
$observed_values = [];
if (tableExists($conn, 'student_values')) {
    $ov_stmt = $conn->prepare("SELECT * FROM student_values WHERE student_id=? AND school_year=?");
    $ov_stmt->bind_param("is", $student_id, $current_year);
    $ov_stmt->execute();
    $ov_res = $ov_stmt->get_result();
    $ov_stmt->close();
    while ($r = $ov_res->fetch_assoc()) $observed_values[intval($r['quarter'])] = $r;
}

// Subjects in order
$ordered_subject_names = ['Filipino','English','Mathematics','Science','Araling Panlipunan (AP)','Values Education (VE)','Technology and Livelihood Education (TLE)','Music','Arts','Physical Education','Health'];
$sub_stmt = $conn->prepare("SELECT SubjectID, SubjectName FROM subject ORDER BY SubjectID");
$sub_stmt->execute();
$all_subjects_res = $sub_stmt->get_result();
$sub_stmt->close();
$all_subjects = [];
while ($r = $all_subjects_res->fetch_assoc()) $all_subjects[] = $r;

$ordered_subject_list = [];
foreach ($ordered_subject_names as $sn) {
    foreach ($all_subjects as $s) {
        if ($s['SubjectName'] == $sn) { $ordered_subject_list[] = $s; break; }
    }
}

$mapeh_component_names = ['Music','Arts','Physical Education','Health'];
$mapeh_component_ids = [];
foreach ($mapeh_component_names as $cn) {
    foreach ($all_subjects as $s) {
        if ($s['SubjectName'] == $cn) { $mapeh_component_ids[] = $s['SubjectID']; break; }
    }
}

// ─── COMPUTE GRADES & AVERAGES ────────────────────────────────────────────────

$mapeh_grade = !empty($mapeh_component_ids) ? calculateMAPEHGrade($grades, $mapeh_component_ids) : [];

$find_grade = function($sid) use ($grades) {
    foreach ($grades as $g) { if ($g['subject']==$sid) return $g; }
    return null;
};

$general_average = 0;
$subject_count   = 0;
$mapeh_already_processed = false;

// Pre-compute MAPEH component pair values
$comp_ids = $mapeh_component_ids;
$music_id  = $comp_ids[0] ?? null;
$arts_id   = $comp_ids[1] ?? null;
$pe_id     = $comp_ids[2] ?? null;
$health_id = $comp_ids[3] ?? null;

$compute_pair = function($idA, $idB, $qf) use ($find_grade) {
    $a = $find_grade($idA); $b = $find_grade($idB);
    $av = ($a && isset($a[$qf]) && $a[$qf]!=='') ? $a[$qf] : null;
    $bv = ($b && isset($b[$qf]) && $b[$qf]!=='') ? $b[$qf] : null;
    if ($av!==null && $bv!==null) return round(($av+$bv)/2);
    return $av!==null ? $av : ($bv!==null ? $bv : '');
};

// Build subject rows data
$subject_rows = [];
foreach ($ordered_subject_list as $subject) {
    $is_mapeh_component = in_array($subject['SubjectID'], $mapeh_component_ids);
    if (in_array($subject['SubjectName'], ['Music','Arts','Physical Education','Health']) && !$mapeh_already_processed) {
        $mapeh_already_processed = true;
        $q1 = !empty($mapeh_grade['q1']) ? $mapeh_grade['q1'] : '';
        $q2 = !empty($mapeh_grade['q2']) ? $mapeh_grade['q2'] : '';
        $q3 = !empty($mapeh_grade['q3']) ? $mapeh_grade['q3'] : '';
        $q4 = !empty($mapeh_grade['q4']) ? $mapeh_grade['q4'] : '';
        $final = !empty($mapeh_grade['final']) ? $mapeh_grade['final'] : '';
        $remarks = !empty($final) ? ($final >= 75 ? 'Passed' : 'Failed') : '';
        if (!empty($final)) { $general_average += $final; $subject_count++; }

        $g1_q1=$compute_pair($music_id,$arts_id,'Q1'); $g1_q2=$compute_pair($music_id,$arts_id,'Q2');
        $g1_q3=$compute_pair($music_id,$arts_id,'Q3'); $g1_q4=$compute_pair($music_id,$arts_id,'Q4');
        $g1_vals=array_filter([$g1_q1,$g1_q2,$g1_q3,$g1_q4],function($v){return $v!==''&&$v!==null;});
        $g1_final=!empty($g1_vals)?round(array_sum($g1_vals)/count($g1_vals)):'';

        $g2_q1=$compute_pair($pe_id,$health_id,'Q1'); $g2_q2=$compute_pair($pe_id,$health_id,'Q2');
        $g2_q3=$compute_pair($pe_id,$health_id,'Q3'); $g2_q4=$compute_pair($pe_id,$health_id,'Q4');
        $g2_vals=array_filter([$g2_q1,$g2_q2,$g2_q3,$g2_q4],function($v){return $v!==''&&$v!==null;});
        $g2_final=!empty($g2_vals)?round(array_sum($g2_vals)/count($g2_vals)):'';

        $subject_rows[] = ['type'=>'mapeh','q1'=>$q1,'q2'=>$q2,'q3'=>$q3,'q4'=>$q4,'final'=>$final,'remarks'=>$remarks];
        $subject_rows[] = ['type'=>'mapeh_comp','name'=>'&#8226; Music and Arts','q1'=>$g1_q1,'q2'=>$g1_q2,'q3'=>$g1_q3,'q4'=>$g1_q4,'final'=>$g1_final,'remarks'=>''];
        $subject_rows[] = ['type'=>'mapeh_comp','name'=>'&#8226; PE and Health','q1'=>$g2_q1,'q2'=>$g2_q2,'q3'=>$g2_q3,'q4'=>$g2_q4,'final'=>$g2_final,'remarks'=>''];
    } elseif ($is_mapeh_component) {
        continue;
    } else {
        $gf = $find_grade($subject['SubjectID']);
        $q1 = $gf && !empty($gf['Q1']) ? $gf['Q1'] : '';
        $q2 = $gf && !empty($gf['Q2']) ? $gf['Q2'] : '';
        $q3 = $gf && !empty($gf['Q3']) ? $gf['Q3'] : '';
        $q4 = $gf && !empty($gf['Q4']) ? $gf['Q4'] : '';
        if (!empty($q1)&&!empty($q2)&&!empty($q3)&&!empty($q4)) $final=round(($q1+$q2+$q3+$q4)/4);
        else $final = $gf && !empty($gf['Final']) ? $gf['Final'] : '';
        $remarks = !empty($final) ? ($final>=75?'Passed':'Failed') : '';
        if (!empty($final)) { $general_average+=$final; $subject_count++; }
        $subject_rows[] = ['type'=>'regular','name'=>$subject['SubjectName'],'q1'=>$q1,'q2'=>$q2,'q3'=>$q3,'q4'=>$q4,'final'=>$final,'remarks'=>$remarks];
    }
}

$gen_avg = $subject_count > 0 ? round($general_average / $subject_count) : '';
$gen_remarks = !empty($gen_avg) ? ($gen_avg>=75?'Passed':'Failed') : '';

// ─── HELPER: get value for a quarter column ───────────────────────────────────

function qval($row, $col, $quarter) {
    // $col: 'q1'..'q4', 'final', 'remarks'
    // Only show the relevant data for the selected quarter
    if ($col === 'final' || $col === 'remarks') {
        return ($quarter == 4) ? $row[$col] : '';
    }
    $q_key = 'q'.$quarter;
    if ($col === $q_key) return $row[$col];
    return '';
}

function ov($observed_values, $quarter, $field) {
    return isset($observed_values[$quarter][$field]) ? htmlspecialchars($observed_values[$quarter][$field]) : '';
}

// ─── BUILD HTML FOR TCPDF ─────────────────────────────────────────────────────

// Page 1: Attendance + Left (school header / student info / signatories)
// Page 2: Grades table + Observed values

$student_name_upper = strtoupper($student['LastName'].', '.$student['FirstName'].' '.($student['Middlename']??''));

// Attendance months
$months = ['Jun','Jul','Aug','Sept','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May'];
$total_school_days = $total_present = $total_absent = 0;
$att_row_days = $att_row_present = $att_row_absent = '';
foreach ($months as $month) {
    $d = $attendance[$month]['total'];   $total_school_days += $d; $att_row_days    .= "<td>$d</td>";
    $p = $attendance[$month]['present']; $total_present     += $p; $att_row_present .= "<td>$p</td>";
    $a = $attendance[$month]['absent'];  $total_absent      += $a; $att_row_absent  .= "<td>$a</td>";
}

// Grades table HTML
$grades_html = '';
foreach ($subject_rows as $row) {
    if ($row['type'] === 'mapeh') {
        $grades_html .= '<tr>
            <td style="text-align:left;padding-left:4px;"><strong>MAPEH</strong></td>
            <td>'.qval($row,'q1',$quarter).'</td>
            <td>'.qval($row,'q2',$quarter).'</td>
            <td>'.qval($row,'q3',$quarter).'</td>
            <td>'.qval($row,'q4',$quarter).'</td>
            <td>'.qval($row,'final',$quarter).'</td>
            <td>'.qval($row,'remarks',$quarter).'</td>
        </tr>';
    } elseif ($row['type'] === 'mapeh_comp') {
        $grades_html .= '<tr>
            <td style="text-align:left;padding-left:12px;">'.$row['name'].'</td>
            <td>'.qval($row,'q1',$quarter).'</td>
            <td>'.qval($row,'q2',$quarter).'</td>
            <td>'.qval($row,'q3',$quarter).'</td>
            <td>'.qval($row,'q4',$quarter).'</td>
            <td></td><td></td>
        </tr>';
    } else {
        $grades_html .= '<tr>
            <td style="text-align:left;padding-left:4px;">'.htmlspecialchars($row['name']).'</td>
            <td>'.qval($row,'q1',$quarter).'</td>
            <td>'.qval($row,'q2',$quarter).'</td>
            <td>'.qval($row,'q3',$quarter).'</td>
            <td>'.qval($row,'q4',$quarter).'</td>
            <td>'.qval($row,'final',$quarter).'</td>
            <td>'.qval($row,'remarks',$quarter).'</td>
        </tr>';
    }
}

// General average row (only on Q4)
$gen_avg_display   = ($quarter == 4) ? $gen_avg   : '';
$gen_rmks_display  = ($quarter == 4) ? $gen_remarks : '';
$grades_html .= '<tr>
    <td></td>
    <td colspan="4" style="text-align:center;"><strong>General Average</strong></td>
    <td style="text-align:center;">'.$gen_avg_display.'</td>
    <td style="text-align:center;">'.$gen_rmks_display.'</td>
</tr>';

// Observed values - only show the selected quarter column data
$ov_cols = ['q1'=>'','q2'=>'','q3'=>'','q4'=>''];
$ov_key  = 'q'.$quarter;
// Each OV row: make all quarter cells empty except the selected one
function ov_row($ov, $q, $field) {
    $cells = '';
    for ($i=1;$i<=4;$i++) {
        $cells .= '<td style="text-align:center;">'.($i==$q ? ov($ov,$q,$field) : '').'</td>';
    }
    return $cells;
}

// ─── LOAD TCPDF ───────────────────────────────────────────────────────────────

require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

class ReportCardPDF extends TCPDF {
    public function Header() {} // no default header
    public function Footer() {} // no default footer
}

$pdf = new ReportCardPDF('L', 'pt', 'LETTER');
$pdf->SetCreator('SmartCard System');
$pdf->SetAuthor('SmartCard');
$pdf->SetTitle('Report Card - ' . $student_name_upper);
$pdf->SetMargins(20, 15, 20);
$pdf->SetAutoPageBreak(false, 0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Letter landscape: 792 x 612 pt  margins 20,15 → content: 752 x 582
$pageW = 792;
$pageH = 612;
$margin = 20;
$colW = ($pageW - 2*$margin) / 2;  // ~376 pt per column

// ─── PAGE 1: Attendance (left col) + School Header / Student Info (right col) ─

$pdf->AddPage();

// ── LEFT COLUMN: Attendance header + table + signatures ──
$leftX = $margin;
$rightX = $margin + $colW;
$topY = 15;

// "REPORT ON ATTENDANCE" title
$pdf->SetFont('times','B',11);
$pdf->SetXY($leftX, $topY);
$pdf->Cell($colW, 14, 'REPORT ON ATTENDANCE', 0, 1, 'C');

// Attendance table via writeHTMLCell
$att_html = '
<table border="1" cellpadding="2" cellspacing="0" style="font-size:7pt;width:100%;border-collapse:collapse;">
<thead>
<tr style="height:18px;">
    <th style="width:16%;text-align:center;"></th>
    <th style="text-align:center;">Jun</th><th style="text-align:center;">Jul</th>
    <th style="text-align:center;">Aug</th><th style="text-align:center;">Sept</th>
    <th style="text-align:center;">Oct</th><th style="text-align:center;">Nov</th>
    <th style="text-align:center;">Dec</th><th style="text-align:center;">Jan</th>
    <th style="text-align:center;">Feb</th><th style="text-align:center;">Mar</th>
    <th style="text-align:center;">Apr</th><th style="text-align:center;">May</th>
    <th style="text-align:center;">Total</th>
</tr>
</thead>
<tbody>
<tr><td>No. of school days</td>'.$att_row_days.'<td style="text-align:center;">'.$total_school_days.'</td></tr>
<tr><td>No. of days present</td>'.$att_row_present.'<td style="text-align:center;">'.$total_present.'</td></tr>
<tr><td>No. of days absent</td>'.$att_row_absent.'<td style="text-align:center;">'.$total_absent.'</td></tr>
</tbody>
</table>';

$pdf->SetFont('times','',7);
$pdf->SetXY($leftX, $topY + 16);
$pdf->writeHTMLCell($colW, 0, $leftX, $topY + 16, $att_html, 0, 1, false, true, 'C');

// Signatures
$sigY = $topY + 110;
$pdf->SetFont('times','B',9);
$pdf->SetXY($leftX, $sigY);
$pdf->Cell($colW, 12, "Parent/Guardian's Signature", 0, 1, 'C');

$sigY += 50;
$quarters_labels = ['First Quarter','Second Quarter','Third Quarter','Fourth Quarter'];
$pdf->SetFont('times','',9);
foreach ($quarters_labels as $ql) {
    $pdf->SetXY($leftX, $sigY);
    $pdf->Cell($colW*0.45, 12, $ql, 0, 0, 'R');
    $pdf->Cell($colW*0.5, 12, '_____________________________', 0, 0, 'L');
    $sigY += 20;
}

// ── RIGHT COLUMN: DepEd Header + student info + parent message ──
// Logo
$logoPath = realpath(__DIR__.'/../img/image.png');
if ($logoPath && file_exists($logoPath)) {
    $pdf->Image($logoPath, $rightX + $colW/2 - 15, $topY, 30, 30, '', '', '', false, 300);
}

$headerY = $topY + 32;
$pdf->SetFont('times','B',8);
$pdf->SetXY($rightX, $headerY);
$pdf->Cell($colW, 10, 'Republic of the Philippines', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 10);
$pdf->SetFont('times','B',10);
$pdf->Cell($colW, 10, 'Department of Education', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 20);
$pdf->SetFont('times','',7);
$pdf->Cell($colW, 8, 'REGION IV-A CALABARZON', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 28);
$pdf->SetFont('times','B',7);
$pdf->Cell($colW, 8, 'DIVISION OF BATANGAS', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 36);
$pdf->SetFont('times','',7);
$pdf->Cell($colW, 8, 'Nasugbu East Sub-Office', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 44);
$pdf->SetFont('times','B',7);
$pdf->Cell($colW, 8, 'BALAYTIGUE NATIONAL HIGH SCHOOL', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 52);
$pdf->SetFont('times','',7);
$pdf->Cell($colW, 8, 'Balaytigue Nasugbu, Batangas', 0, 1, 'C');

// Progress Report Card title
$pdf->SetXY($rightX, $headerY + 62);
$pdf->SetFont('times','B',12);
$pdf->Cell($colW, 14, 'PROGRESS REPORT CARD', 0, 1, 'C');
$pdf->SetXY($rightX, $headerY + 76);
$pdf->SetFont('times','',10);
$pdf->Cell($colW, 12, 'School Year '.$current_year, 0, 1, 'C');

// Student info
$infoY = $headerY + 96;
$pdf->SetFont('times','',10);
$labelW = 50;
$valueW = $colW - $labelW - 5;

// Name
$pdf->SetXY($rightX, $infoY);
$pdf->Cell($labelW, 12, 'Name:', 0, 0, 'L');
$pdf->Cell($valueW, 12, $student_name_upper, 'B', 1, 'L');

// LRN + Sex
$pdf->SetXY($rightX, $infoY + 14);
$pdf->Cell($labelW, 12, 'LRN:', 0, 0, 'L');
$halfVal = ($valueW - 40) / 2;
$pdf->Cell($halfVal, 12, $student['LRN'] ?? '', 'B', 0, 'L');
$pdf->Cell(35, 12, 'Sex:', 0, 0, 'C');
$pdf->Cell($halfVal, 12, strtoupper($student['Sex'] ?? ''), 'B', 1, 'L');

// Age + Section
$pdf->SetXY($rightX, $infoY + 28);
$pdf->Cell($labelW, 12, 'Age:', 0, 0, 'L');
$pdf->Cell($halfVal, 12, calculateAge($student['Birthdate'] ?? ''), 'B', 0, 'L');
$pdf->Cell(35, 12, 'Section:', 0, 0, 'C');
$pdf->Cell($halfVal, 12, $section ? $section['SectionName'] : '', 'B', 1, 'L');

// Grade
$pdf->SetXY($rightX, $infoY + 42);
$pdf->Cell($labelW, 12, 'Grade:', 0, 0, 'L');
$pdf->Cell($halfVal, 12, $section ? $section['GradeLevel'] : '', 'B', 1, 'L');

// Parent message
$msgY = $infoY + 62;
$pdf->SetFont('times','',9);
$pdf->SetXY($rightX, $msgY);
$pdf->MultiCell($colW, 11, "Dear Parent:\n     This report card shows the ability and progress your child has made in the different learning areas as well as his/her progress in core values.\n     The school welcomes you should you desire to know more about your child's progress.", 0, 'L');

// School Head / Teacher signature area
$sig2Y = $msgY + 55;
$pdf->SetFont('times','',9);
$pdf->SetXY($rightX, $sig2Y);
$pdf->SetFont('times','BU',9);
$pdf->Cell($colW/2, 12, '   '.strtoupper($head_teacher_name).'   ', 0, 0, 'C');
$pdf->SetFont('times','BU',9);
$pdf->Cell($colW/2, 12, '   '.strtoupper($teacher_full_name).'   ', 0, 1, 'C');
$pdf->SetFont('times','I',9);
$pdf->SetXY($rightX, $sig2Y + 12);
$pdf->Cell($colW/2, 10, 'School Head', 0, 0, 'C');
$pdf->Cell($colW/2, 10, 'Teacher', 0, 1, 'C');

$pdf->SetXY($rightX, $sig2Y + 24);
$pdf->SetFont('times','',9);
$pdf->Cell($colW, 10, '_________________________________________________________________', 0, 1, 'C');

// Certificate of Transfer
$certY = $sig2Y + 40;
$pdf->SetFont('times','B',9);
$pdf->SetXY($rightX, $certY);
$pdf->Cell($colW, 10, 'CERTIFICATE OF TRANSFER', 0, 1, 'C');
$pdf->SetFont('times','',9);
$pdf->SetXY($rightX, $certY + 10);
$pdf->Cell($colW, 10, 'Admitted to Grade: ___________________________ Section: _____________________', 0, 1, 'L');
$pdf->SetXY($rightX, $certY + 20);
$pdf->Cell($colW, 10, 'Eligibility for Admission to Grade: ____________________________', 0, 1, 'L');

$pdf->SetXY($rightX, $certY + 32);
$pdf->Cell($labelW, 10, 'Approved:', 0, 0, 'L');
$pdf->SetXY($rightX, $certY + 44);
$pdf->Cell($colW/2, 10, '_____________________', 0, 0, 'C');
$pdf->Cell($colW/2, 10, '_____________________', 0, 1, 'C');
$pdf->SetFont('times','I',9);
$pdf->SetXY($rightX, $certY + 54);
$pdf->Cell($colW/2, 10, 'Teacher', 0, 0, 'C');
$pdf->Cell($colW/2, 10, 'School Head', 0, 1, 'C');

// Cancellation of Eligibility
$canY = $certY + 68;
$pdf->SetFont('times','B',9);
$pdf->SetXY($rightX, $canY);
$pdf->Cell($colW, 10, 'Cancellation of Eligibility to Transfer', 0, 1, 'C');
$pdf->SetFont('times','',9);
$pdf->SetXY($rightX, $canY + 10);
$pdf->Cell($colW, 10, 'Admitted in: _______________________________________________', 0, 1, 'L');
$pdf->SetXY($rightX, $canY + 20);
$pdf->Cell($colW, 10, 'Date: ________________________', 0, 1, 'L');
$pdf->SetXY($rightX, $canY + 36);
$pdf->Cell($colW, 10, '_________________________', 0, 1, 'C');
$pdf->SetFont('times','I',9);
$pdf->SetXY($rightX, $canY + 46);
$pdf->Cell($colW, 10, 'Principal', 0, 1, 'C');

// ─── PAGE 2: Grades + Observed Values ────────────────────────────────────────

$pdf->AddPage();

$p2topY = 15;

// ── LEFT COLUMN: Grades ──
$pdf->SetFont('times','B',9);
$pdf->SetXY($leftX, $p2topY);
$pdf->Cell($colW, 11, 'GRADE 7 - 10', 0, 1, 'L');
$pdf->SetXY($leftX, $p2topY + 13);
$pdf->Cell($colW, 11, 'REPORT ON LEARNING PROGRESS AND ACHIEVEMENT', 0, 1, 'C');

$grades_table_html = '
<table border="1" cellpadding="2" cellspacing="0" style="font-size:8pt;width:100%;border-collapse:collapse;">
<thead>
<tr>
    <th rowspan="2" style="width:30%;text-align:center;">Learning Areas</th>
    <th colspan="4" style="text-align:center;">Quarter</th>
    <th style="width:12%;text-align:center;">Final Grade</th>
    <th style="width:14%;text-align:center;">Remarks</th>
</tr>
<tr>
    <th style="width:6%;text-align:center;">1</th>
    <th style="width:6%;text-align:center;">2</th>
    <th style="width:6%;text-align:center;">3</th>
    <th style="width:6%;text-align:center;">4</th>
    <th></th><th></th>
</tr>
</thead>
<tbody style="text-align:center;">
'.$grades_html.'
</tbody>
</table>';

$pdf->SetFont('times','',8);
$pdf->writeHTMLCell($colW, 0, $leftX, $p2topY + 26, $grades_table_html, 0, 1, false, true, 'C');

// Legend
$legendY = $p2topY + 26 + 195;
$pdf->SetFont('times','BI',9);
$pdf->SetXY($leftX, $legendY);
$pdf->Cell($colW*0.45, 10, 'Descriptors', 0, 0, 'L');
$pdf->Cell($colW*0.3, 10, 'Grading Scale', 0, 0, 'L');
$pdf->Cell($colW*0.25, 10, 'Remarks', 0, 1, 'L');
$pdf->SetFont('times','I',9);
$descriptors=[['Outstanding','90-100','Passed'],['Very Satisfactory','85-89','Passed'],['Satisfactory','80-84','Passed'],['Fairly Satisfactory','75-79','Passed'],['Did Not Meet Expectations','Below 75','Failed']];
$ly = $legendY + 10;
foreach ($descriptors as $d) {
    $pdf->SetXY($leftX, $ly);
    $pdf->Cell($colW*0.45, 10, $d[0], 0, 0, 'L');
    $pdf->Cell($colW*0.3, 10, $d[1], 0, 0, 'L');
    $pdf->Cell($colW*0.25, 10, $d[2], 0, 1, 'L');
    $ly += 10;
}

// ── RIGHT COLUMN: Observed Values ──
$pdf->SetFont('times','B',10);
$pdf->SetXY($rightX, $p2topY);
$pdf->Cell($colW, 13, "REPORT ON LEARNER'S OBSERVED VALUES", 0, 1, 'C');

// Build OV rows: only show value for selected quarter
$ov_rows_html = '';
$ov_defs = [
    ['rowspan'=>2,'core'=>'1. Maka-Diyos','behaviors'=>[
        ['field'=>'makadiyos_1','text'=>'Expresses one\'s spiritual beliefs while respecting the spiritual beliefs of others.'],
        ['field'=>'makadiyos_2','text'=>'Shows adherence to ethical principles by upholding truth.'],
    ]],
    ['rowspan'=>2,'core'=>'2. Makatao','behaviors'=>[
        ['field'=>'makatao_1','text'=>'Is sensitive to individual, social and cultural differences.'],
        ['field'=>'makatao_2','text'=>'Demonstrates contributions toward solidarity.'],
    ]],
    ['rowspan'=>1,'core'=>'3. Maka-kalikasan','behaviors'=>[
        ['field'=>'makakalikasan_1','text'=>'Cares for the environment and utilizes resources wisely, judiciously, and economically.'],
    ]],
    ['rowspan'=>2,'core'=>'4. Makabansa','behaviors'=>[
        ['field'=>'makabansa_1','text'=>'Demonstrates pride in being a Filipino; exercises rights and responsibilities of a Filipino citizen.'],
        ['field'=>'makabansa_2','text'=>'Demonstrates appropriate behavior in carrying out activities in the school, community and country.'],
    ]],
];

foreach ($ov_defs as $def) {
    $first = true;
    foreach ($def['behaviors'] as $beh) {
        $cells = '';
        for ($qi=1;$qi<=4;$qi++) {
            $val = ($qi==$quarter && isset($observed_values[$quarter][$beh['field']])) ? htmlspecialchars($observed_values[$quarter][$beh['field']]) : '';
            $cells .= "<td style=\"text-align:center;\">$val</td>";
        }
        if ($first) {
            $ov_rows_html .= '<tr><td rowspan="'.$def['rowspan'].'" style="vertical-align:middle;">'.$def['core'].'</td><td>'.htmlspecialchars($beh['text']).'</td>'.$cells.'</tr>';
        } else {
            $ov_rows_html .= '<tr><td>'.htmlspecialchars($beh['text']).'</td>'.$cells.'</tr>';
        }
        $first = false;
    }
}

$ov_table_html = '
<table border="1" cellpadding="2" cellspacing="0" style="font-size:8pt;width:100%;border-collapse:collapse;">
<thead>
<tr>
    <th style="width:22%;text-align:center;">Core Values</th>
    <th style="width:40%;text-align:center;">Behavior Statements</th>
    <th style="width:9%;text-align:center;">Q1</th>
    <th style="width:9%;text-align:center;">Q2</th>
    <th style="width:9%;text-align:center;">Q3</th>
    <th style="width:9%;text-align:center;">Q4</th>
</tr>
</thead>
<tbody>
'.$ov_rows_html.'
</tbody>
</table>';

$pdf->SetFont('times','',8);
$pdf->writeHTMLCell($colW, 0, $rightX, $p2topY + 15, $ov_table_html, 0, 1, false, true, 'C');

// Marking legend
$markY = $p2topY + 15 + 130;
$pdf->SetFont('times','B',9);
$pdf->SetXY($rightX, $markY);
$pdf->Cell($colW*0.3, 10, 'Marking', 0, 0, 'C');
$pdf->Cell($colW*0.7, 10, 'Numerical Rating', 0, 1, 'L');
$pdf->SetFont('times','',9);
$markings = [['AO','Always Observed'],['SO','Sometimes Observed'],['RO','Rarely Observed'],['NO','Not Observed']];
$my = $markY + 10;
foreach ($markings as $mk) {
    $pdf->SetXY($rightX, $my);
    $pdf->Cell($colW*0.3, 10, $mk[0], 0, 0, 'C');
    $pdf->Cell($colW*0.7, 10, $mk[1], 0, 1, 'L');
    $my += 10;
}

// ─── OUTPUT ───────────────────────────────────────────────────────────────────
$safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', $student['LastName'].'_'.$student['FirstName']);
$filename  = 'Report_Card_'.$safe_name.'_Q'.$quarter.'_'.$current_year.'.pdf';

$pdf->Output($filename, 'D'); // 'D' = force download, 'I' = inline
exit();
?>
