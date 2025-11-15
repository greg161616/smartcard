<?php
// File: view_grades.php
session_start();
require '../config.php';

// 1) Ensure teacher is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}


$teacherId = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : 0;

// 3) Get subject and section IDs from URL parameters
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;


// 5) Fetch section info
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

// 6) Fetch subject name
$sb = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ?");
$sb->bind_param('i', $subjectId);
$sb->execute();
$sbr = $sb->get_result();
$subjectName = $sbr->num_rows ? $sbr->fetch_assoc()['SubjectName'] : '';
$sb->close();

// 7) Query enrolled students + their grades, separated by gender and ordered by name
$q = "
  SELECT 
    st.LRN,
    st.FirstName,
    st.MiddleName,
    st.LastName,
    st.Sex,
    se.SchoolYear,
    g.Q1, g.Q2, g.Q3, g.Q4, g.Final
  FROM (
    SELECT DISTINCT se.StudentID, se.SchoolYear
    FROM section_enrollment se
    JOIN subject sc ON sc.secID = se.SectionID
    WHERE se.SectionID = ?
    AND sc.SubjectID = ?
  ) AS se
  JOIN student st ON se.StudentID = st.StudentID
  LEFT JOIN grades g 
    ON g.student_id = st.StudentID 
   AND g.subject = ?
  ORDER BY st.Sex, st.LastName, st.FirstName
";
$stmt = $conn->prepare($q);
$stmt->bind_param('iii', $sectionId, $subjectId, $subjectId);
$stmt->execute();
$res2 = $stmt->get_result();

// 8) Pull the School Year from the first row if available
$schoolYear = '';
if ($res2->num_rows > 0) {
    $firstRow = $res2->fetch_assoc();
    $schoolYear = htmlspecialchars($firstRow['SchoolYear']);
    mysqli_data_seek($res2, 0);
}

// 9) Separate students by gender
$maleStudents = [];
$femaleStudents = [];

while ($row = $res2->fetch_assoc()) {
    if ($row['Sex'] === 'Male') {
        $maleStudents[] = $row;
    } else {
        $femaleStudents[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>View Grades</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .grade-link {
      cursor: pointer;
      color: #232425ff;
      position: relative;
    }
    .grade-link:hover {
      color: #0056b3;
    }
    .grade-link:hover::after {
      content: "View Grade Details";
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background-color: #333;
      color: #fff;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      white-space: nowrap;
      z-index: 1000;
    }
    .gender-section {
      margin-bottom: 30px;
    }
    .gender-header {
      background-color: #f8f9fa;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Student Grades</h2>
<div>
<a href="view_subjects_grades.php?section_id=<?= $sectionId ?>&school_year=<?= $schoolYear ?>" class="btn btn-secondary">
<i class="fas fa-book-open me-1"></i> View Subjects & Grades
</a>
</div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Class Information</h5>
        <p class="card-text">
          <strong>Subject:</strong> <?= htmlspecialchars($subjectName) ?><br>
          <strong>Section:</strong> Grade <?= htmlspecialchars($section['GradeLevel']) ?>-<?= htmlspecialchars($section['SectionName']) ?><br>
          <?php if ($schoolYear): ?>
            <strong>School Year:</strong> <?= $schoolYear ?>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <?php if (count($maleStudents) === 0 && count($femaleStudents) === 0): ?>
      <div class="alert alert-info">No students or grades found for this class.</div>
    <?php else: ?>
      <!-- Male Students Section -->
      <?php if (count($maleStudents) > 0): ?>
      <div class="gender-section">
        <div class="gender-header">
          <h4>Male</h4>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-info">
              <tr>
                <th>LRN</th>
                <th>Name</th>
                <th>Q1</th>
                <th>Q2</th>
                <th>Q3</th>
                <th>Q4</th>
                <th>Final</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($maleStudents as $row): 
                $fullName = htmlspecialchars(
                  $row['FirstName'] . ' '
                  . ($row['MiddleName'] ? $row['MiddleName'] . ' ' : '')
                  . $row['LastName']
                );
              ?>
                <tr>
                  <td><?= htmlspecialchars($row['LRN']) ?></td>
                  <td><?= $fullName ?></td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 1)">
                      <?= htmlspecialchars($row['Q1']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 2)">
                      <?= htmlspecialchars($row['Q2']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 3)">
                      <?= htmlspecialchars($row['Q3']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 4)">
                      <?= htmlspecialchars($row['Q4']) ?>
                    </span>
                  </td>
                  <td><strong><?= htmlspecialchars($row['Final']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Female Students Section -->
      <?php if (count($femaleStudents) > 0): ?>
      <div class="gender-section">
        <div class="gender-header">
          <h4>Female</h4>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-info">
              <tr>
                <th>LRN</th>
                <th>Name</th>
                <th>Q1</th>
                <th>Q2</th>
                <th>Q3</th>
                <th>Q4</th>
                <th>Final</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($femaleStudents as $row): 
                $fullName = htmlspecialchars(
                  $row['FirstName'] . ' '
                  . ($row['MiddleName'] ? $row['MiddleName'] . ' ' : '')
                  . $row['LastName']
                );
              ?>
                <tr>
                  <td><?= htmlspecialchars($row['LRN']) ?></td>
                  <td><?= $fullName ?></td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 1)">
                      <?= htmlspecialchars($row['Q1']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 2)">
                      <?= htmlspecialchars($row['Q2']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 3)">
                      <?= htmlspecialchars($row['Q3']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-link" onclick="showGradeDetails(<?= $row['LRN'] ?>, 4)">
                      <?= htmlspecialchars($row['Q4']) ?>
                    </span>
                  </td>
                  <td><strong><?= htmlspecialchars($row['Final']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Modal for Grade Details -->
  <div class="modal fade" id="gradeDetailsModal" tabindex="-1" aria-labelledby="gradeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="gradeDetailsModalLabel">Grade Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="gradeDetailsContent">
          <!-- Grade details will be loaded here via AJAX -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function showGradeDetails(lrn, quarter) {
      // Show loading message
      document.getElementById('gradeDetailsContent').innerHTML = '<p>Loading grade details...</p>';
      
      // Create and show modal
      const modal = new bootstrap.Modal(document.getElementById('gradeDetailsModal'));
      modal.show();
      
      // Fetch grade details via AJAX
      fetch(`grades_details.php?lrn=${lrn}&subject_id=<?= $subjectId ?>&quarter=${quarter}&school_year=<?= urlencode($schoolYear) ?>`)
        .then(response => response.text())
        .then(data => {
          document.getElementById('gradeDetailsContent').innerHTML = data;
        })
        .catch(error => {
          document.getElementById('gradeDetailsContent').innerHTML = '<p>Error loading grade details.</p>';
          console.error('Error:', error);
        });
    }
  </script>
</body>
</html>