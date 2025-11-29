<?php
// File: view_grades.php
session_start();
require '../config.php';

// Ensure user is logged in and is principal/headteacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Get subject and section IDs from URL parameters
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$schoolYear = isset($_GET['school_year']) ? $_GET['school_year'] : '';

if (!$subjectId || !$sectionId || !$schoolYear) {
    echo "<div class='alert alert-danger'>Missing required parameters.</div>";
    exit;
}

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

// Fetch subject name and teacher info
$sb = $conn->prepare("
    SELECT sub.SubjectName, t.fName, t.lName, t.mName, t.surfix
    FROM subject sub
    LEFT JOIN assigned_subject a ON sub.SubjectID = a.subject_id
    LEFT JOIN teacher t ON a.teacher_id = t.TeacherID
    WHERE sub.SubjectID = ? AND a.section_id = ? AND a.school_year = ?
");
$sb->bind_param('iis', $subjectId, $sectionId, $schoolYear);
$sb->execute();
$sbr = $sb->get_result();
if (!$sbr->num_rows) {
    echo "<div class='alert alert-danger'>Subject not found or not assigned to this section.</div>";
    exit;
}
$subjectData = $sbr->fetch_assoc();
$subjectName = $subjectData['SubjectName'];

// Build teacher name
$teacherName = 'Not assigned';
if ($subjectData['fName']) {
    $teacherName = $subjectData['lName'] . ', ' . $subjectData['fName'];
    if ($subjectData['mName']) {
        $teacherName .= ' ' . substr($subjectData['mName'], 0, 1) . '.';
    }
    if ($subjectData['surfix']) {
        $teacherName .= ' ' . $subjectData['surfix'];
    }
}
$sb->close();

// Query enrolled students + their grades from grades_details table, separated by gender and ordered by name
$q = "
  SELECT 
    st.LRN,
    st.StudentID,
    st.FirstName,
    st.Middlename,
    st.LastName,
    st.Sex,
    se.SchoolYear
  FROM section_enrollment se
  JOIN student st ON se.StudentID = st.StudentID
  WHERE se.SectionID = ? AND se.SchoolYear = ? AND se.status = 'active'
  ORDER BY st.Sex, st.LastName, st.FirstName
";
$stmt = $conn->prepare($q);
$stmt->bind_param('is', $sectionId, $schoolYear);
$stmt->execute();
$res2 = $stmt->get_result();

// Separate students by gender and get their grades
$maleStudents = [];
$femaleStudents = [];

while ($row = $res2->fetch_assoc()) {
    // Get grades for each student from grades_details table
    $gradesQuery = "
        SELECT quarter, quarterly_grade 
        FROM grades_details 
        WHERE studentID = ? AND subjectID = ? AND school_year = ?
        ORDER BY quarter
    ";
    $gradeStmt = $conn->prepare($gradesQuery);
    $gradeStmt->bind_param('iis', $row['StudentID'], $subjectId, $schoolYear);
    $gradeStmt->execute();
    $gradeResult = $gradeStmt->get_result();
    
    $quarterGrades = [1 => null, 2 => null, 3 => null, 4 => null];
    $hasGrades = false;
    
    while ($gradeRow = $gradeResult->fetch_assoc()) {
        $quarter = (int)$gradeRow['quarter'];
        if ($quarter >= 1 && $quarter <= 4) {
            $quarterGrades[$quarter] = $gradeRow['quarterly_grade'];
            $hasGrades = true;
        }
    }
    $gradeStmt->close();
    
    // Get final grade from grades table
    $finalGradeQuery = "
        SELECT Final 
        FROM grades 
        WHERE student_id = ? AND subject = ? AND school_year = ?
    ";
    $finalStmt = $conn->prepare($finalGradeQuery);
    $finalStmt->bind_param('iis', $row['StudentID'], $subjectId, $schoolYear);
    $finalStmt->execute();
    $finalResult = $finalStmt->get_result();
    $finalGrade = $finalResult->num_rows ? $finalResult->fetch_assoc()['Final'] : null;
    $finalStmt->close();
    
    // If no final grade in grades table but we have quarterly grades, calculate average
    if ($finalGrade === null && $hasGrades) {
        $validGrades = array_filter($quarterGrades, function($grade) {
            return $grade !== null && $grade > 0;
        });
        if (count($validGrades) > 0) {
            $finalGrade = round(array_sum($validGrades) / count($validGrades));
        }
    }
    
    $studentData = [
        'LRN' => $row['LRN'],
        'StudentID' => $row['StudentID'],
        'FirstName' => $row['FirstName'],
        'Middlename' => $row['Middlename'],
        'LastName' => $row['LastName'],
        'Sex' => $row['Sex'],
        'SchoolYear' => $row['SchoolYear'],
        'Q1' => $quarterGrades[1],
        'Q2' => $quarterGrades[2],
        'Q3' => $quarterGrades[3],
        'Q4' => $quarterGrades[4],
        'Final' => $finalGrade
    ];
    
    if ($row['Sex'] === 'Male') {
        $maleStudents[] = $studentData;
    } else {
        $femaleStudents[] = $studentData;
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .grade-link {
      cursor: pointer;
      color: #232425ff;
      position: relative;
      text-decoration: none;
      border-bottom: 1px dotted #007bff;
    }
    .grade-link:hover {
      color: #0056b3;
      text-decoration: none;
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
    .no-grade {
      color: #6c757d;
      font-style: italic;
    }
    .class-info-card {
      border-left: 4px solid #4e73df;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Student Grades - Headteacher View</h2>
      <div>
        <a href="view_subjects_grades.php?section_id=<?= $sectionId ?>&school_year=<?= urlencode($schoolYear) ?>" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-1"></i> Back to Subjects
        </a>
      </div>
    </div>

    <div class="card mb-4 class-info-card">
      <div class="card-body">
        <h5 class="card-title">Class Information</h5>
        <p class="card-text">
          <strong>Subject:</strong> <?= htmlspecialchars($subjectName) ?><br>
          <strong>Teacher:</strong> <?= htmlspecialchars($teacherName) ?><br>
          <strong>Section:</strong> Grade <?= htmlspecialchars($section['GradeLevel']) ?> - <?= htmlspecialchars($section['SectionName']) ?><br>
          <?php if ($schoolYear): ?>
            <strong>School Year:</strong> <?= htmlspecialchars($schoolYear) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <?php if (count($maleStudents) === 0 && count($femaleStudents) === 0): ?>
      <div class="alert alert-info">No students found for this class in the selected school year.</div>
    <?php else: ?>
      <!-- Male Students Section -->
      <?php if (count($maleStudents) > 0): ?>
      <div class="gender-section">
        <div class="gender-header">
          <h4><i class="fas fa-mars me-2"></i>Male Students (<?= count($maleStudents) ?>)</h4>
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
                  . ($row['Middlename'] ? $row['Middlename'] . ' ' : '')
                  . $row['LastName']
                );
              ?>
                <tr>
                  <td><?= htmlspecialchars($row['LRN']) ?></td>
                  <td><?= $fullName ?></td>
                  <td>
                    <?php if ($row['Q1'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 1)">
                        <?= htmlspecialchars($row['Q1']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['Q2'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 2)">
                        <?= htmlspecialchars($row['Q2']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['Q3'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 3)">
                        <?= htmlspecialchars($row['Q3']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['Q4'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 4)">
                        <?= htmlspecialchars($row['Q4']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td><strong><?= $row['Final'] !== null ? htmlspecialchars($row['Final']) : '<span class="no-grade">-</span>' ?></strong></td>
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
          <h4><i class="fas fa-venus me-2"></i>Female Students (<?= count($femaleStudents) ?>)</h4>
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
                  . ($row['Middlename'] ? $row['Middlename'] . ' ' : '')
                  . $row['LastName']
                );
              ?>
                <tr>
                  <td><?= htmlspecialchars($row['LRN']) ?></td>
                  <td><?= $fullName ?></td>
                  <td>
                    <?php if ($row['Q1'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 1)">
                        <?= htmlspecialchars($row['Q1']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['Q2'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 2)">
                        <?= htmlspecialchars($row['Q2']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['Q3'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 3)">
                        <?= htmlspecialchars($row['Q3']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['Q4'] !== null): ?>
                      <span class="grade-link" onclick="showGradeDetails(<?= $row['StudentID'] ?>, 4)">
                        <?= htmlspecialchars($row['Q4']) ?>
                      </span>
                    <?php else: ?>
                      <span class="no-grade">-</span>
                    <?php endif; ?>
                  </td>
                  <td><strong><?= $row['Final'] !== null ? htmlspecialchars($row['Final']) : '<span class="no-grade">-</span>' ?></strong></td>
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
    function showGradeDetails(studentId, quarter) {
      // Show loading message
      document.getElementById('gradeDetailsContent').innerHTML = `
        <div class="text-center">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Loading grade details...</p>
        </div>
      `;
      
      // Create and show modal
      const modal = new bootstrap.Modal(document.getElementById('gradeDetailsModal'));
      modal.show();
      
      // Fetch grade details via AJAX
      fetch(`grades_details.php?student_id=${studentId}&subject_id=<?= $subjectId ?>&quarter=${quarter}&school_year=<?= urlencode($schoolYear) ?>`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.text();
        })
        .then(data => {
          document.getElementById('gradeDetailsContent').innerHTML = data;
        })
        .catch(error => {
          document.getElementById('gradeDetailsContent').innerHTML = `
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle me-2"></i>
              Error loading grade details. Please try again.
            </div>
          `;
          console.error('Error:', error);
        });
    }
  </script>
</body>
</html>