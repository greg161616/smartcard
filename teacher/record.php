<?php
include '../config.php';
session_start();

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher_query = "SELECT * FROM teacher WHERE userID = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

// Get the current year and the next year
$year = (string)date("Y");
$next_year = (string)($year + 1);
$current_year = $year . '-' . $next_year;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Student - Report Card</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
        }
        .card {
            margin-top: 20px;
        }
        .table {
            margin-bottom: 0;
        }
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php';?>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Student Record Cards</h4>
                    </div>
                    <div class="card-body mt-3 py-4">
                        <?php
                        $adviserTeacherID = isset($teacher['TeacherID']) ? $teacher['TeacherID'] : null;
                        
                        if (!$adviserTeacherID) {
                            echo "<div class='alert alert-warning'>You don't have any advisory class</div>";
                        } else {
                            $secRes = mysqli_query($conn, "SELECT SectionID, GradeLevel, SectionName FROM section WHERE AdviserID = '{$adviserTeacherID}' ORDER BY GradeLevel, SectionName");
                            if ($secRes && mysqli_num_rows($secRes) > 0) {
                                $secIds = [];
                                while ($s = mysqli_fetch_assoc($secRes)) {
                                    $secIds[] = $s['SectionID'];
                                }
                                $secList = implode(',', $secIds);

                                $students_query = "
                                    SELECT s.StudentID, s.FirstName, s.LastName, s.Middlename, sec.GradeLevel, sec.SectionName
                                    FROM student s
                                    JOIN section_enrollment se ON s.StudentID = se.StudentID
                                    JOIN section sec ON se.SectionID = sec.SectionID
                                    WHERE se.SectionID IN ({$secList})
                                      AND se.SchoolYear = '$current_year'
                                      AND se.status = 'active'
                                    ORDER BY sec.GradeLevel, sec.SectionName, s.LastName, s.FirstName
                                ";
                                $students_result = mysqli_query($conn, $students_query);
                                
                                if ($students_result && mysqli_num_rows($students_result) > 0) {
                                    ?>
                                    <table class="table table-striped table-hover">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>Name</th>
                                                <th>Grade & Section</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            while ($row = mysqli_fetch_assoc($students_result)) {
                                                $fullName = trim($row['LastName'] . ', ' . $row['FirstName'] . ' ' . $row['Middlename']);
                                                $gradeSection = "Grade {$row['GradeLevel']} {$row['SectionName']}";
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fullName) ?></td>
                                                    <td><?= htmlspecialchars($gradeSection) ?></td>
                                                    <td>
                                                        <a href="generate_report_card.php?student_id=<?= $row['StudentID'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-file-text"></i> View Card
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                    <?php
                                } else {
                                    echo "<div class='alert alert-info'>No students found in your advisory section(s)</div>";
                                }
                            } else {
                                echo "<div class='alert alert-warning'>You don't have any advisory class</div>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>