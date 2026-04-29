<?php
require_once '../../vendor/autoload.php';
include '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    die("Unauthorized");
}

$school_year = $_GET['school_year'] ?? '';
$section_id = $_GET['section_id'] ?? '';

if (!$school_year || !$section_id) {
    die("Missing parameters");
}

// Fetch Section Info
$sec_sql = "SELECT SectionName, GradeLevel FROM section WHERE SectionID = ?";
$sec_stmt = $conn->prepare($sec_sql);
$sec_stmt->bind_param("i", $section_id);
$sec_stmt->execute();
$section = $sec_stmt->get_result()->fetch_assoc();

// Fetch Subjects for this grade level/section
$subjects = [];
$sub_sql = "SELECT DISTINCT s.SubjectID, s.SubjectName 
            FROM subject s
            JOIN grades g ON s.SubjectID = g.subject
            JOIN section_enrollment se ON g.student_id = se.StudentID
            WHERE se.SectionID = ? AND se.SchoolYear = ?
            ORDER BY s.SubjectID";
$sub_stmt = $conn->prepare($sub_sql);
$sub_stmt->bind_param("is", $section_id, $school_year);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();
while ($row = $sub_res->fetch_assoc()) {
    $subjects[] = $row;
}

// If no grades found, fallback to all subjects (or handle empty)
if (empty($subjects)) {
    $sub_sql = "SELECT SubjectID, SubjectName FROM subject ORDER BY SubjectID";
    $sub_res = $conn->query($sub_sql);
    while ($row = $sub_res->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Fetch Students
$students = [];
$stu_sql = "SELECT s.StudentID, s.LastName, s.FirstName, s.Middlename, s.Sex
            FROM student s
            JOIN section_enrollment se ON s.StudentID = se.StudentID
            WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
            ORDER BY s.Sex DESC, s.LastName, s.FirstName";
$stu_stmt = $conn->prepare($stu_sql);
$stu_stmt->bind_param("is", $section_id, $school_year);
$stu_stmt->execute();
$stu_res = $stu_stmt->get_result();
while ($row = $stu_res->fetch_assoc()) {
    $student_id = $row['StudentID'];
    $grades_map = [];
    
    // Get Final Grades (Average of Q1-Q4)
    $g_sql = "SELECT subject, Q1, Q2, Q3, Q4, Final FROM grades WHERE student_id = ? AND school_year = ?";
    $g_stmt = $conn->prepare($g_sql);
    $g_stmt->bind_param("is", $student_id, $school_year);
    $g_stmt->execute();
    $g_res = $g_stmt->get_result();
    while ($g_row = $g_res->fetch_assoc()) {
        $final = $g_row['Final'];
        if (!$final && ($g_row['Q1'] || $g_row['Q2'] || $g_row['Q3'] || $g_row['Q4'])) {
            $qs = array_filter([$g_row['Q1'], $g_row['Q2'], $g_row['Q3'], $g_row['Q4']]);
            $final = !empty($qs) ? round(array_sum($qs) / count($qs)) : '';
        }
        $grades_map[$g_row['subject']] = $final;
    }
    $row['grades'] = $grades_map;
    $students[] = $row;
}

// --- TCPDF Implementation ---

class MYPDF extends TCPDF {
    public function Header() {
        // Left Logo (DepEd)
        $deped_logo = '../../img/deped_logo.png';
        if (file_exists($deped_logo)) {
            $this->Image($deped_logo, 15, 10, 22, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        // Right Logo (School)
        $school_logo = '../../img/logo.png';
        if (file_exists($school_logo)) {
            $this->Image($school_logo, 260, 10, 20, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        $this->SetFont('helvetica', '', 9);
        $header_html = '
        <div style="text-align: center; line-height: 1.1;">
            <span style="font-size: 10pt;">Republic of the Philippines</span><br>
            <span style="font-weight: bold; font-size: 11pt;">Department of Education</span><br>
            <span style="font-size: 10pt;">Region IV-A CALABARZON</span><br>
            <span style="font-size: 10pt;">Division of Batangas</span><br>
            <span style="font-weight: bold; font-size: 12pt;">BALAYTIGUE NATIONAL HIGH SCHOOL</span><br>
            <span style="font-size: 9pt;">Nasugbu, Batangas</span><br><br>
            <span style="font-weight: bold; font-size: 11pt;">SUMMARY OF FINAL GRADES</span><br>
            <span style="font-size: 10pt;">A.Y. ' . htmlspecialchars($GLOBALS['school_year']) . '</span>
        </div>';
        $this->writeHTMLCell(0, 0, 10, 10, $header_html, 0, 1, 0, true, 'C', true);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Leading Innovations, Transforming Lives', 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SmartCard System');
$pdf->SetTitle('Summary of Final Grades');

// Set margins
$pdf->SetMargins(15, 60, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Build the Table
$html = '
<style>
    table { border-collapse: collapse; width: 100%; }
    th { border: 1px solid black; background-color: #f2f2f2; font-weight: bold; text-align: center; font-size: 8pt; vertical-align: middle; }
    td { border: 1px solid black; text-align: center; font-size: 8pt; padding: 4px; }
    .text-left { text-align: left; }
    .male-row { background-color: #f0f7ff; }
    .female-row { background-color: #fff0f5; }
    .group-header { background-color: #e9ecef; font-weight: bold; text-align: left; padding-left: 10px; }
</style>

<div style="margin-bottom: 10px;">
    <strong>Grade & Section:</strong> ' . htmlspecialchars($section['GradeLevel'] . ' - ' . $section['SectionName']) . '
</div>

<table>
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 25%;">NAME OF LEARNERS</th>';

// Dynamic Subject Headers
$sub_width = 70 / (count($subjects) + 1); // 70% left for subjects + average
foreach ($subjects as $sub) {
    $html .= '<th style="width: ' . $sub_width . '%;">' . htmlspecialchars($sub['SubjectName']) . '</th>';
}
$html .= '<th style="width: ' . $sub_width . '%;">AVG</th>';
$html .= '</tr>
    </thead>
    <tbody>';

$count = 1;
$current_sex = '';

foreach ($students as $stu) {
    if ($stu['Sex'] != $current_sex) {
        $current_sex = $stu['Sex'];
        $label = ($current_sex == 'Male' || $current_sex == 'M') ? 'MALE' : 'FEMALE';
        $html .= '<tr><td colspan="' . (count($subjects) + 3) . '" class="group-header">' . $label . '</td></tr>';
        $count = 1;
    }

    $row_class = ($current_sex == 'Male' || $current_sex == 'M') ? 'male-row' : 'female-row';
    $name = strtoupper($stu['LastName'] . ', ' . $stu['FirstName'] . ' ' . ($stu['Middlename'] ? substr($stu['Middlename'], 0, 1) . '.' : ''));
    
    $html .= '<tr class="' . $row_class . '">
                <td>' . $count++ . '</td>
                <td class="text-left">' . $name . '</td>';
    
    $sum = 0;
    $graded_count = 0;
    foreach ($subjects as $sub) {
        $grade = $stu['grades'][$sub['SubjectID']] ?? '';
        $html .= '<td>' . $grade . '</td>';
        if ($grade) {
            $sum += $grade;
            $graded_count++;
        }
    }
    
    $avg = ($graded_count > 0) ? round($sum / $graded_count) : '';
    $html .= '<td style="font-weight: bold;">' . $avg . '</td>
              </tr>';
}

if (empty($students)) {
    $html .= '<tr><td colspan="' . (count($subjects) + 3) . '">No students enrolled in this section for the selected school year.</td></tr>';
}

$html .= '</tbody>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
if (isset($_GET['download'])) {
    $pdf->Output('Summary_of_Final_Grades_' . str_replace(' ', '_', $section['SectionName']) . '.pdf', 'D');
} else {
    $pdf->Output('Summary_of_Final_Grades.pdf', 'I');
}
