<?php
require_once '../../vendor/autoload.php';
include '../../config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    die("Unauthorized");
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

if (!$section) {
    die("No advisory section found.");
}

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('F');
$current_year = date('Y') . '-' . (date('Y') + 1);
$sy_sql = "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1";
$sy_res = $conn->query($sy_sql);
if ($sy_res && $sy_res->num_rows > 0) {
    $current_year = $sy_res->fetch_assoc()['school_year'];
}

$month_num = date('n', strtotime($selected_month));
$year_to_query = ($month_num >= 6) ? explode('-', $current_year)[0] : explode('-', $current_year)[1];

$attendance_summary = [];
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
            $this->Image($school_logo, 173, 10, 20, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
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
            <span style="font-weight: bold; font-size: 11pt;">MONTHLY ATTENDANCE REPORT</span><br>
            <span style="font-size: 10pt;">' . strtoupper($GLOBALS['selected_month']) . ' ' . $GLOBALS['year_to_query'] . '</span>
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

$pdf = new MYPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SmartCard System');
$pdf->SetTitle('Attendance Report');
$pdf->SetMargins(15, 60, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

$html = '
<style>
    table { border-collapse: collapse; width: 100%; }
    th { border: 1px solid black; background-color: #f2f2f2; font-weight: bold; text-align: center; font-size: 9pt; padding: 6px; }
    td { border: 1px solid black; text-align: center; font-size: 9pt; padding: 5px; }
    .text-left { text-align: left; }
</style>

<div style="margin-bottom: 10px;">
    <strong>Grade & Section:</strong> ' . htmlspecialchars($section['GradeLevel'] . ' - ' . $section['SectionName']) . '<br>
    <strong>Adviser:</strong> ' . htmlspecialchars($teacher_name) . '
</div>

<table>
    <thead>
        <tr>
            <th rowspan="2" style="width: 40%;">STUDENT NAME</th>
            <th colspan="3" style="width: 45%;">ATTENDANCE COUNT</th>
            <th rowspan="2" style="width: 15%;">REMARKS</th>
        </tr>
        <tr>
            <th>PRESENT</th>
            <th>ABSENT</th>
            <th>EXCUSED</th>
        </tr>
    </thead>
    <tbody>';

foreach ($attendance_summary as $row) {
    $name = strtoupper($row['LastName'] . ', ' . $row['FirstName']);
    $html .= '<tr>
                <td class="text-left">' . $name . '</td>
                <td>' . $row['present'] . '</td>
                <td>' . $row['absent'] . '</td>
                <td>' . $row['excused'] . '</td>
                <td></td>
              </tr>';
}

if (empty($attendance_summary)) {
    $html .= '<tr><td colspan="5">No attendance data found for this month.</td></tr>';
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->SetY($pdf->GetY() + 20);
$sig_html = '
<table style="width: 100%; border: none;">
    <tr>
        <td style="width: 60%; border: none; text-align: left; font-size: 8pt;">Generated by SmartCard System</td>
        <td style="width: 40%; border: none; text-align: center;">
            <div style="border-top: 1px solid black; padding-top: 5px;">
                <span style="font-weight: bold; font-size: 10pt;">' . strtoupper($teacher_name) . '</span><br>
                <span style="font-size: 9pt;">Class Adviser</span>
            </div>
        </td>
    </tr>
</table>';
$pdf->writeHTML($sig_html, true, false, true, false, '');

if (isset($_GET['download'])) {
    $pdf->Output('Attendance_Report_' . $selected_month . '_' . $year_to_query . '.pdf', 'D');
} else {
    $pdf->Output('Attendance_Report.pdf', 'I');
}
