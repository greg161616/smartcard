<?php
session_start();
require_once '../config.php';

// 1) Ensure teacher is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// 2) Lookup TeacherID from session email
$teacherID = null;
$stmt = $conn->prepare("
    SELECT t.TeacherID
      FROM teacher t
      JOIN user u ON t.UserID = u.UserID
     WHERE u.Email = ?
");
$stmt->bind_param('s', $_SESSION['email']);
$stmt->execute();
$stmt->bind_result($teacherID);
$stmt->fetch();
$stmt->close();

if (!$teacherID) {
    die("Teacher record not found. Please contact your administrator.");
}

// 3) (Optional) Filter by current school year if you store that in session
//    e.g. $_SESSION['school_year'] = '2025-2026';
$filterYear = isset($_SESSION['school_year']) ? $_SESSION['school_year'] : null;

// 4) Fetch students in teacher’s sections
$sql = "
    SELECT DISTINCT 
        s.LRN,
        s.FirstName,
        s.MiddleName,
        s.LastName,
        s.Sex,
        s.ContactNumber,
        s.ParentsContact
      FROM student s
      JOIN section_enrollment se 
        ON s.StudentID = se.StudentID
      JOIN sched sc 
        ON se.SectionID = sc.SectionID
     WHERE sc.TeacherID = ?
";
if ($filterYear) {
    $sql .= " AND se.SchoolYear = ?";
}

$stmt = $conn->prepare($sql);
if ($filterYear) {
    $stmt->bind_param('is', $teacherID, $filterYear);
} else {
    $stmt->bind_param('i', $teacherID);
}
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

if ($conn->error) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .main-content { padding: 40px 20px; }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>

    <div class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">My Students</h2>
            </div>

            <div class="table-responsive">
                <table id="studentTable" class="table table-striped table-bordered">
                    <thead class="table-secondary">
                        <tr>
                            <th>LRN No.</th>
                            <th>Name</th>
                            <th>Sex</th>
                            <th>Contact No.</th>
                            <th>Parents Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): ?>
                            <tr>
                                <td><?= htmlspecialchars($stu['LRN']) ?></td>
                                <td>
                                    <?= htmlspecialchars(
                                        trim("{$stu['FirstName']} {$stu['MiddleName']} {$stu['LastName']}")
                                    ) ?>
                                </td>
                                <td><?= htmlspecialchars($stu['Sex']) ?></td>
                                <td><?= htmlspecialchars($stu['ContactNumber']) ?></td>
                                <td><?= htmlspecialchars($stu['ParentsContact']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS & DataTables -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#studentTable').DataTable({
                // you can add page length, ordering, etc. here
            });
        });
    </script>
</body>
</html>
