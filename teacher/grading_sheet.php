<?php
// File: select_grades.php
session_start();
require '../config.php';

// 1) Ensure teacher is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 2) Lookup TeacherID from UserID
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not registered as a teacher.";
    exit;
}
$teacherId = $res->fetch_assoc()['TeacherID'];
$stmt->close();

// 3) Fetch all subject-section assignments for this teacher
$sql = "
  SELECT 
    s.SubjectID,
    s.SubjectName,
    sec.SectionID,
    sec.GradeLevel,
    sec.SectionName
  FROM sched sc
  JOIN subject s ON sc.SubjectID = s.SubjectID
  JOIN section sec ON sc.SectionID = sec.SectionID
  WHERE sc.TeacherID = ?
  GROUP BY s.SubjectID, sec.SectionID
  ORDER BY s.SubjectName, sec.GradeLevel, sec.SectionName
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Grade Upload &amp; View</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>

  <div class="container mt-5">
    <h2 class="mb-4">Your Subjects &amp; Sections</h2>

    <?php if (empty($assignments)): ?>
      <div class="alert alert-info">
        You have no classes scheduled.
      </div>
    <?php else: ?>
      <table class="table table-hover align-middle table-bordered">
        <thead class="table-info">
          <tr>
            <th>Subject</th>
            <th>Section</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assignments as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['SubjectName']) ?></td>
              <td>Grade <?= $a['GradeLevel'] ?>-<?= htmlspecialchars($a['SectionName']) ?></td>
              <td>
                <a
                  href="grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>"
                  class="btn btn-sm btn-primary"
                >
                  Upload Grades
                </a>
                <a
                  href="view_grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>"
                  class="btn btn-sm btn-info"
                >
                  View Grades
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>