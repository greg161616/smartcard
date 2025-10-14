<?php
// File: view_subjects_grades.php
session_start();
require '../config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Get section ID from URL
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

// Fetch section details including adviser
$sectionQuery = $conn->prepare("
    SELECT s.SectionName, s.GradeLevel, 
           t.fName, t.lName, t.mName 
    FROM section s 
    LEFT JOIN teacher t ON s.AdviserID = t.TeacherID 
    WHERE s.SectionID = ?
");
$sectionQuery->bind_param('i', $sectionId);
$sectionQuery->execute();
$sectionResult = $sectionQuery->get_result();

if (!$sectionResult->num_rows) {
    echo "<div class='alert alert-danger'>Section not found.</div>";
    exit;
}

$section = $sectionResult->fetch_assoc();
$sectionQuery->close();

// Build adviser name
$adviserName = '';
if ($section['fName']) {
    $adviserName = $section['lName'] . ', ' . $section['fName'];
    if ($section['mName']) {
        $adviserName .= ' ' . substr($section['mName'], 0, 1) . '.';
    }
}

// Fetch all subjects taught in this section with teacher information
$subjectsQuery = "
    SELECT DISTINCT 
        sub.SubjectID,
        sub.SubjectName,
        s.TeacherID,
        sub.GradeLevel,
        COUNT(DISTINCT g.student_id) as GradedStudents,
        COUNT(DISTINCT se.StudentID) as TotalStudents
    FROM sched s
    JOIN subject sub ON s.SubjectID = sub.SubjectID
    LEFT JOIN grades g ON sub.SubjectID = g.subject AND g.uploaded = 1
    LEFT JOIN section_enrollment se ON s.SectionID = se.SectionID AND se.status = 'active'
    WHERE s.SectionID = ?
    GROUP BY sub.SubjectID, s.TeacherID
    ORDER BY sub.SubjectName
";
$stmt = $conn->prepare($subjectsQuery);
$stmt->bind_param('i', $sectionId);
$stmt->execute();
$subjectsResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Subjects for <?= htmlspecialchars($section['SectionName']) ?></title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .subject-card {
      transition: transform 0.2s;
      margin-bottom: 20px;
      border-radius: 12px;
      overflow: hidden;
    }
    .subject-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .progress {
      height: 10px;
      margin-top: 5px;
    }
    .page-header {
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 30px;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <div class="page-header">
      <div class="row align-items-center">
        <div class="col">
          <h1 class="h3 mb-0">
            <i class="fas fa-book me-2"></i>
            Subjects for Grade <?= htmlspecialchars($section['GradeLevel']) ?> - <?= htmlspecialchars($section['SectionName']) ?>
          </h1>
          <?php if ($adviserName): ?>
            <p class="mb-0">Adviser: <?= htmlspecialchars($adviserName) ?></p>
          <?php endif; ?>
        </div>
        <div class="col-auto">
          <a href="select_sec.php" class="btn btn-light">
            <i class="fas fa-arrow-left me-1"></i> Back to Sections
          </a>
        </div>
      </div>
    </div>

    <?php if ($subjectsResult->num_rows === 0): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No subjects found for this section.
      </div>
    <?php else: ?>
      <div class="row">
        <?php while ($subject = $subjectsResult->fetch_assoc()): 
          // Calculate grading progress
          $progress = 0;
          if ($subject['TotalStudents'] > 0) {
            $progress = round(($subject['GradedStudents'] / $subject['TotalStudents']) * 100);
          }
        ?>
          <div class="col-md-4 mb-4">
            <div class="card subject-card">
              <div class="card-header">
                <h5 class="card-title mb-0"><?= htmlspecialchars($subject['SubjectName']) ?></h5>
              </div>
              <div class="card-body">
                
                <div class="mb-3">
                  <div class="d-flex justify-content-between">
                  </div>
                </div>
                
                <div class="d-grid">
                  <a href="view_grades.php?subject_id=<?= $subject['SubjectID'] ?>&section_id=<?= $sectionId ?>&teacher_id=<?= $subject['TeacherID'] ?>" 
                     class="btn btn-primary">
                    <i class="fas fa-table me-1"></i> View Grade Sheet
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>