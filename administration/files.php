<?php
include '../config.php';
session_start();

// Check if user is logged in as principal
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'head') {
    header("Location: ../login.php");
    exit();
}

// Get student ID from query parameter
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

// Get the current year and the next year
$year = (string)date("Y");  // Get the current year
$next_year = (string)($year + 1);  // Get the next year and cast it to a string
$current_year = $year . '-' . $next_year;  // Combine them as a string for the school year

// Initialize variables
$teacher = null;
$teacher_id = null;
$student = null;
$grades = [];
$attendance = [];
$observed_values = [];
$section = [];

if ($student_id) {
    // Get student information
    $student_query = "SELECT * FROM student WHERE StudentID = '$student_id'";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);
    
    if ($student) {
        // Get student's current section and teacher
        $section_query = "
            SELECT s.*, se.SchoolYear, s.AdviserID
            FROM section_enrollment se 
            JOIN section s ON se.SectionID = s.SectionID 
            WHERE se.StudentID = '$student_id' 
            AND se.SchoolYear = '$current_year'
            AND se.status = 'active'
            LIMIT 1
        ";
        $section_result = mysqli_query($conn, $section_query);
        if (mysqli_num_rows($section_result) > 0) {
            $section = mysqli_fetch_assoc($section_result);
            $teacher_id = $section['AdviserID'];
            
            // Get teacher information
            if ($teacher_id) {
                $teacher_query = "SELECT * FROM teacher WHERE userID = '$teacher_id'";
                $teacher_result = mysqli_query($conn, $teacher_query);
                if ($teacher_result && mysqli_num_rows($teacher_result) > 0) {
                    $teacher = mysqli_fetch_assoc($teacher_result);
                }
            }
        }
        
        // Get grades
        $grades_query = "
            SELECT g.*, s.SubjectName 
            FROM grades g 
            JOIN subject s ON g.subject = s.SubjectID 
            WHERE g.student_id = '$student_id'
        ";
        $grades_result = mysqli_query($conn, $grades_query);
        while ($row = mysqli_fetch_assoc($grades_result)) {
            $grades[] = $row;
        }
        
        // Get attendance data for the current school year
        $attendance_query = "
            SELECT MONTH(Date) as month_num, 
                   COUNT(*) as total_days,
                   SUM(CASE WHEN Status = 'Present' THEN 1 ELSE 0 END) as present_days
            FROM attendance 
            WHERE StudentID = '$student_id'
            AND YEAR(Date) = '$year'
            GROUP BY MONTH(Date)
        ";
        $attendance_result = mysqli_query($conn, $attendance_query);
        $attendance_data = [];
        while ($row = mysqli_fetch_assoc($attendance_result)) {
            $attendance_data[$row['month_num']] = [
                'present' => $row['present_days'],
                'absent' => $row['total_days'] - $row['present_days']
            ];
        }
        
        // Map month numbers to names as in the report card
        $month_names = [
            9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 
            5 => 'May', 6 => 'Jun', 7 => 'Jul'
        ];
        
        foreach ($month_names as $num => $name) {
            $attendance[$name] = isset($attendance_data[$num]) ? $attendance_data[$num] : ['present' => 0, 'absent' => 0];
        }
        
        // Note: Observed values would require an additional table in your schema
        // For now, we'll create placeholder data
        $observed_values = [
            ['value' => 'Maka-Diyos', 'statement' => 'Expresses one\'s spiritual beliefs while respecting the spiritual beliefs of others'],
            ['value' => 'Maka-tao', 'statement' => 'Shows adherence to ethical principles by upholding truth and respecting individuals, social, and cultural differences'],
            ['value' => 'Makakalikasan', 'statement' => 'Demonstrates contributions towards solidarity'],
            ['value' => 'Makabansa', 'statement' => 'Cares for the environment and utilizes resources responsibly, effectively, and economically'],
            ['value' => 'Makabansa', 'statement' => 'Demonstrates pride in being a Filipino; exercises the rights and responsibilities of a Filipino citizen'],
            ['value' => 'Makabansa', 'statement' => 'Demonstrates appropriate behavior in carrying out activities in the school, community, and country']
        ];
    }
}

// Function to calculate age from birthdate
function calculateAge($birthdate) {
    if (empty($birthdate)) return '';
    
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANAHIS | Student Report Card</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
            background-color: #f5f5f5;
        }
        .report-card {
            background-color: white;
            padding: 15px;
            margin: 16px auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 250mm; /* A4 width */
            min-height: 197mm; /* A4 height */
        }
        .school-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .school-header h1 {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .student-info {
            margin-bottom: 20px;
            margin-top: 50px;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #000;
            padding: 4px;
            font-size: 12px;
        }
        .table-bordered th {
            background-color: #f0f0f0;
            text-align: center;
        }
        .signature-area {
            margin-top: 30px;
        }
        .legend {
            font-size: 11px;
        }
        .page-break {
            page-break-before: always;
        }
        .parent-message {
            font-size: 13px;
            margin-bottom: 20px;
        }
        @media print {
            body {
                background-color: white;
            }
            .report-card {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../navs/headNav.php';?>
    
    <div class="container mt-4 no-print">
        <div class="row">
            <div class="col-md-6">
                <h2>Generate Report Card</h2>
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Select Student</label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php
                            // Get all students for this teacher's sections
                            $students_query = "
                                SELECT s.StudentID, s.FirstName, s.LastName, s.Middlename, sec.GradeLevel, sec.SectionName
                                FROM student s
                                JOIN section_enrollment se ON s.StudentID = se.StudentID
                                JOIN section sec ON se.SectionID = sec.SectionID
                                AND se.SchoolYear = '$current_year'
                                AND se.status = 'active'
                                ORDER BY s.LastName, s.FirstName
                            ";
                            $students_result = mysqli_query($conn, $students_query);
                            while ($row = mysqli_fetch_assoc($students_result)) {
                                $selected = ($student_id == $row['StudentID']) ? 'selected' : '';
                                echo "<option value='{$row['StudentID']}' $selected>
                                    {$row['LastName']}, {$row['FirstName']} {$row['Middlename']} - Grade {$row['GradeLevel']} {$row['SectionName']}
                                </option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <?php if ($student_id): ?>
                        <button type="button" class="btn btn-success" onclick="printReportCard()">Print Report</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <?php if ($student_id && $student): ?>
    <!-- First Page -->
    <div class="report-card">
        <!-- School Header -->

        <div class="row">
            <div class="col-md-6">
 <h5 class="text-center mb-4 mt-5">REPORT ON ATTENDANCE</h5>
        <table class="table table-bordered table-sm">
            <thead>
                <tr><th colspan="2"></th>
                    <th>Sept</th>
                    <th>Oct</th>
                    <th>Nov</th>
                    <th>Dec</th>
                    <th>Jan</th>
                    <th>Feb</th>
                    <th>Mar</th>
                    <th>Apr</th>
                    <th>May</th>
                    <th>Jun</th>
                    <th>Jul</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                    $months = ['Sept', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
                    $total_present = 0;
                    $total_absent = 0;
                    
                    foreach ($months as $month) {
                        $present = isset($attendance[$month]) ? $attendance[$month]['present'] : 0;
                        $absent = isset($attendance[$month]) ? $attendance[$month]['absent'] : 0;
                        
                        
                        $total_present += $present;
                        $total_absent += $absent;
                    }
                    $total_days = $total_present + $total_absent;
                    ?>
                </tr>
                <tr>
                    <td colspan="2">No. of Days</td>
                    <?php
                    foreach ($months as $month) {
                        $present = isset($attendance[$month]) ? $attendance[$month]['present'] : 0;
                        $absent = isset($attendance[$month]) ? $attendance[$month]['absent'] : 0;
                        $total = $present + $absent;
                        echo "<td>$total</td>";
                    }
                    ?>
                    <td><?php echo $total_days; ?></td>
                </tr>
                <tr>
                    <td colspan="2">No. of Present</td>
                    <?php
                    foreach ($months as $month) {
                        $present = isset($attendance[$month]) ? $attendance[$month]['present'] : 0;
                        echo "<td>$present</td>";
                    }
                    ?>
                    <td><?php echo $total_present; ?></td>
                </tr>
                <tr>
                    <td colspan="2">No. of Absent</td>
                    <?php
                    foreach ($months as $month) {
                        $absent = isset($attendance[$month]) ? $attendance[$month]['absent'] : 0;
                        echo "<td>$absent</td>";
                    }
                    ?>
                    <td><?php echo $total_absent; ?></td>
                </tr>
            </tbody>
        </table>

                <div class="signature-area">
            <p class="text-center"><strong>PARENT'S / GUARDIAN'S SIGNATURE</strong></p>
            <div class="d-flex flex-column">
                <p><strong>1st Quarter:</strong> ___________________________</p>
                <p></p>
                <p><strong>2nd Quarter:</strong> ___________________________</p>
                <p></p>
                <p><strong>3rd Quarter:</strong> ___________________________</p>
                <p></p>
                <p><strong>4th Quarter:</strong> ___________________________</p>

            </div>
        </div>

                <!-- Certificate of Transfer -->
        <div class="signature-area mt-4">
            <div class="row">
                <div class="col-6">
                    <div class="text-center">
                        <p>_________________________</p>
                        <p><strong><?php echo strtoupper($teacher['fName']) . ' ' . strtoupper($teacher['mName']) . '. ' . strtoupper($teacher['lName']); ?></strong></p>
                        <p>Class Adviser</p>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-center">
                        <p>_________________________</p>
                        <p><strong>JOEL B. ABREU</strong></p>
                        <p>Head Teacher</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancellation of Eligibility to Transfer -->
        <div class="mt-4">
            <p><strong>Cancellation of Eligibility to Transfer</strong></p>
            <p>Admitted in: __________________ Date: _________________________</p>
        </div>
            </div>
            <div class="col-md-6">
                 <div class="school-header">
            <p>Republic of the Philippines</p>
            <p>Region IV-A CALABARZON</p>
            <p>Division of Batangas</p>

        </div>

                    <h5 class="text-center">LEARNER'S PROGRESS REPORT CARD</h5>
        <!-- Student Information -->
        <div class="student-info">
            <div><strong>LRN:</strong> <?php echo $student['LRN']; ?></div>
            <div><strong>Name:</strong> <?php echo strtoupper($student['LastName']); ?>,
            <?php echo strtoupper($student['FirstName']); ?>
            <?php echo strtoupper($student['Middlename']); ?></div>
            <div><strong>Age:</strong>&nbsp;&nbsp; <?php echo calculateAge($student['Birthdate']); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>Sex:</strong>&nbsp;&nbsp; <?php echo strtoupper($student['Sex']); ?></div>
            <div><strong>Grade:&nbsp;&nbsp; </strong> <?php echo !empty($section) ? "{$section['GradeLevel']}": ''; ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>Section: </strong><?php echo !empty($section) ? "{$section['SectionName']}": ''; ?></div>
            <div><strong>School Year:&nbsp;&nbsp; </strong> <?php echo $current_year; ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </div>

        <!-- Parent Message -->
        <div class="parent-message">
            <p>Dear Parent:</p>
            <p>This report card shows the ability and progress your child has made in the different learning areas as well as his/her core values.</p>
            <p>The school welcomes you should you desire to know more about your child's progress.</p>
                </div>


        <!-- Signatures -->
        <div class="signature-area mt-4">
            <div class="row">
                <div class="col-6">
                    <div class="text-center">
                        <p>_________________________</p>
                        <p><strong><?php echo strtoupper($teacher['fName']) . ' ' . strtoupper($teacher['mName']) . '. ' . strtoupper($teacher['lName']); ?></strong></p>
                        <p>Class Adviser</p>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-center">
                        <p>_________________________</p>
                        <p><strong>JOEL B. ABREU</strong></p>
                        <p>Head Teacher</p>
                    </div>
                </div>
            </div>
        </div>

                <div class="mt-3 text-center">
            <h5>Certificate of Transfer</h5>
            <p>Admitted to Grade: ___________ Section: ___________</p>
            <p>Eligibility for Admission to Grade: ___________</p>
        </div>
    </div>
                </div>
        </div>
       

    <!-- Second Page -->
    <div class="report-card page-break">
        <div class="row">
            <div class="col-md-6">
                    <!-- Report on Learning Progress and Achievement -->
        <h6 class="text-center">REPORT ON LEARNING PROGRESS AND ACHIEVEMENT</h6>
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Learning Areas</th>
                    <th>Quarter 1</th>
                    <th>Quarter 2</th>
                    <th>Quarter 3</th>
                    <th>Quarter 4</th>
                    <th>Final Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $general_average = 0;
                $subject_count = 0;
                
                // Define learning areas based on your screenshot
                $learning_areas = [
                    'Filipino',
                    'English',
                    'Mathematics',
                    'Science',
                    'Araling Panlipunan (AP)',
                    'Edukasyon sa Pagpapakatao (EsP)',
                    'Technology and Livelihood Education (TLE)',
                    'MAPEH',
                    'Music',
                    'Arts',
                    'Physical Education',
                    'Health',
                ];
                
                foreach ($learning_areas as $area) {
                    // Try to find a matching grade
                    $grade_found = null;
                    foreach ($grades as $grade) {
                        if (stripos($grade['SubjectName'], $area) !== false) {
                            $grade_found = $grade;
                            break;
                        }
                    }
                    
                    $q1 = $grade_found && !empty($grade_found['Q1']) ? $grade_found['Q1'] : '';
                    $q2 = $grade_found && !empty($grade_found['Q2']) ? $grade_found['Q2'] : '';
                    $q3 = $grade_found && !empty($grade_found['Q3']) ? $grade_found['Q3'] : '';
                    $q4 = $grade_found && !empty($grade_found['Q4']) ? $grade_found['Q4'] : '';
                    $final = $grade_found && !empty($grade_found['Final']) ? $grade_found['Final'] : '';
                    
                    // Calculate remarks based on final grade
                    $remarks = '';
                    if (!empty($final)) {
                        if ($final >= 90) {
                            $remarks = 'Outstanding';
                        } elseif ($final >= 85) {
                            $remarks = 'Very Satisfactory';
                        } elseif ($final >= 80) {
                            $remarks = 'Satisfactory';
                        } elseif ($final >= 75) {
                            $remarks = 'Fairly Satisfactory';
                        } else {
                            $remarks = 'Did Not Meet Expectations';
                        }
                        
                        $general_average += $final;
                        $subject_count++;
                    }
                    
                    echo "<tr>
                        <td>$area</td>
                        <td>$q1</td>
                        <td>$q2</td>
                        <td>$q3</td>
                        <td>$q4</td>
                        <td>$final</td>
                        <td></td>
                    </tr>";
                }
                
                // Calculate general average
                $gen_avg = $subject_count > 0 ? round($general_average / $subject_count, 2) : '';
                $gen_remarks = '';
                if (!empty($gen_avg)) {
                    if ($gen_avg >= 90) {
                        $gen_remarks = 'Outstanding';
                    } elseif ($gen_avg >= 85) {
                        $gen_remarks = 'Very Satisfactory';
                    } elseif ($gen_avg >= 80) {
                        $gen_remarks = 'Satisfactory';
                    } elseif ($gen_avg >= 75) {
                        $gen_remarks = 'Fairly Satisfactory';
                    } else {
                        $gen_remarks = 'Did Not Meet Expectations';
                    }
                }
                ?>
                <tr>
                    <td colspan="5" class="text-end"><strong>General Average</strong></td>
                    <td><strong><?php echo $gen_avg; ?></strong></td>
                    <td><strong><?php echo $gen_remarks; ?></strong></td>
                </tr>
            </tbody>
        </table>
                        <!-- Legend -->
        <div class="legend">
            <p><strong>Legend:</strong></p>
            <p>90-100 - Outstanding (O)</p>
            <p>85-89 - Very Satisfactory (VS)</p>
            <p>80-84 - Satisfactory (S)</p>
            <p>75-79 - Fairly Satisfactory (FS)</p>
            <p>Below 75 - Did Not Meet Expectations (DNM)</p>
        </div>
            </div>
            <div class="col-md-6">
                 <!-- Report on Learner's Observed Values -->
        <h6 class="mt-4 text-center">REPORT ON LEARNER'S OBSERVED VALUES</h6>
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Core Values</th>
                    <th>Behavior Statements</th>
                    <th>Quarter 1</th>
                    <th>Quarter 2</th>
                    <th>Quarter 3</th>
                    <th>Quarter 4</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($observed_values as $value): ?>
                <tr>
                    <td><?php echo $value['value']; ?></td>
                    <td><?php echo $value['statement']; ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Observed Values Legend -->
        <div class="legend">
            <p><strong>Observed Values Legend:</strong></p>
            <p>AO - Always Observed</p>
            <p>SO - Sometimes Observed</p>
            <p>RO - Rarely Observed</p>
            <p>NO - Not Observed</p>
        </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function printReportCard() {
            // Store the original contents of the body
            const originalContents = document.body.innerHTML;
            
            // Get the report card content
            const reportCardContent = document.getElementById('report-card-content');
            const nextPage = reportCardContent.nextElementSibling;
            
            // Create a print window content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Student Report Card</title>
                    <style>
                        body {
                            font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
                            margin: 0;
                            padding: 0;
                        }
                        .report-card {
                            background-color: white;
                            padding: 15px;
                            margin: 0;
                            width: 100%;
                            box-sizing: border-box;
                        }
                        .school-header {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .school-header h1 {
                            font-size: 18px;
                            font-weight: bold;
                            text-transform: uppercase;
                        }
                        .student-info {
                            margin-bottom: 20px;
                        }
                        .table-bordered th, .table-bordered td {
                            border: 1px solid #000;
                            padding: 4px;
                            font-size: 12px;
                        }
                        .table-bordered th {
                            background-color: #f0f0f0;
                            text-align: center;
                        }
                        .signature-area {
                            margin-top: 30px;
                        }
                        .legend {
                            font-size: 11px;
                            margin-top: 10px;
                        }
                        .page-break {
                            page-break-before: always;
                        }
                        @media print {
                            body {
                                margin: 0;
                                padding: 0;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${reportCardContent.outerHTML}
                    ${nextPage ? nextPage.outerHTML : ''}
                </body>
                </html>
            `;
            
            // Open a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for the content to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }
    </script>
</body>
</html>
