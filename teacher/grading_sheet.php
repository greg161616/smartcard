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

// 3) AJAX handler: return grades snippet and exit
if (isset($_GET['action']) && $_GET['action'] === 'view_grades') {
    $subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
    $sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

    // Fetch section info
    $s = $conn->prepare("SELECT GradeLevel, SectionName FROM section WHERE SectionID = ?");
    $s->bind_param('i', $sectionId);
    $s->execute();
    $sr = $s->get_result();
    if (!$sr->num_rows) {
        echo "<div class='alert alert-danger'>Section not found.</div>";
        exit;
    }
    $section = $sr->fetch_assoc();
    $s->close();

    // Fetch subject name
    $sb = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ?");
    $sb->bind_param('i', $subjectId);
    $sb->execute();
    $sbr = $sb->get_result();
    $subjectName = $sbr->num_rows ? $sbr->fetch_assoc()['SubjectName'] : '';
    $sb->close();

    // Query enrolled students + their grades
 // Query enrolled students + their grades - FIXED VERSION
// Alternative query that first gets distinct students, then joins grades
$q = "
  SELECT 
    st.LRN,
    st.FirstName,
    st.MiddleName,
    st.LastName,
    se.SchoolYear,
    g.Q1, g.Q2, g.Q3, g.Q4, g.Final
  FROM (
    SELECT DISTINCT se.StudentID, se.SchoolYear
    FROM section_enrollment se
    JOIN sched sc ON sc.SectionID = se.SectionID
    WHERE se.SectionID = ?
    AND sc.SubjectID = ?
    AND sc.TeacherID = ?
  ) AS se
  JOIN student st ON se.StudentID = st.StudentID
  LEFT JOIN grades g 
    ON g.student_id = st.StudentID 
   AND g.subject = ?
  ORDER BY st.LastName, st.FirstName
";
$stmt = $conn->prepare($q);
$stmt->bind_param('iiii', $sectionId, $subjectId, $teacherId, $subjectId);
    $stmt->execute();
    $res2 = $stmt->get_result();

    if ($res2->num_rows === 0) {
        echo "<div class='alert alert-info'>No students or grades found.</div>";
        exit;
    }

    // Pull the School Year from the first row, then rewind the pointer
    $firstRow = $res2->fetch_assoc();
    $schoolYear = htmlspecialchars($firstRow['SchoolYear']);
    mysqli_data_seek($res2, 0);

    // Render header + table
    echo "<h5>Subject: " . htmlspecialchars($subjectName) . "</h5>";
    echo "<h6>Section: Grade "
       . htmlspecialchars($section['GradeLevel'])
       . "-" . htmlspecialchars($section['SectionName'])
       . "</h6>";
    echo "<h6>School Year: {$schoolYear}</h6>";

    echo '<table class="table table-bordered mt-3">';
    echo '<thead><tr>
            <th>Name</th>
            <th>LRN</th>
            <th>Q1</th>
            <th>Q2</th>
            <th>Q3</th>
            <th>Q4</th>
            <th>Final</th>
          </tr></thead><tbody>';

    while ($row = $res2->fetch_assoc()) {
        $fullName = htmlspecialchars(
          $row['FirstName'] . ' '
          . ($row['MiddleName'] ? $row['MiddleName'] . ' ' : '')
          . $row['LastName']
        );
        echo '<tr>';
        echo "<td>{$fullName}</td>";
        echo '<td>' . htmlspecialchars($row['LRN'])   . '</td>';
        echo '<td>' . htmlspecialchars($row['Q1'])    . '</td>';
        echo '<td>' . htmlspecialchars($row['Q2'])    . '</td>';
        echo '<td>' . htmlspecialchars($row['Q3'])    . '</td>';
        echo '<td>' . htmlspecialchars($row['Q4'])    . '</td>';
        echo '<td>' . htmlspecialchars($row['Final']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    $stmt->close();
    exit;
}

// 4) Fetch all subject-section assignments for this teacher
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
      <table class="table table-hover">
        <thead class="table-light">
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
                  href="upload.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>"
                  class="btn btn-sm btn-primary"
                >
                  Upload Grades
                </a>
                <button
                  type="button"
                  class="btn btn-sm btn-info view-grades-btn"
                  data-subject-id="<?= $a['SubjectID'] ?>"
                  data-section-id="<?= $a['SectionID'] ?>"
                  data-bs-toggle="modal"
                  data-bs-target="#gradesModal"
                >
                  View Grades
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Grades Modal -->
  <div class="modal fade" id="gradesModal" tabindex="-1" aria-labelledby="gradesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="gradesModalLabel">Student Grades</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="gradesContent" class="py-3 text-center">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  document.querySelectorAll('.view-grades-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const subjectId = this.dataset.subjectId;
      const sectionId = this.dataset.sectionId;
      const content = document.getElementById('gradesContent');

      // show spinner
      content.innerHTML = '<div class="text-center py-3"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

      // fetch from this same page
      const url = `${location.pathname}?action=view_grades&subject_id=${subjectId}&section_id=${sectionId}`;
      fetch(url)
        .then(res => res.text())
        .then(html => { content.innerHTML = html; })
        .catch(err => {
          console.error(err);
          content.innerHTML = '<div class="alert alert-danger">Error loading grades.</div>';
        });
    });
  });
  </script>
</body>
</html>
