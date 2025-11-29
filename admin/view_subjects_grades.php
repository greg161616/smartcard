<?php
// File: view_subjects_grades.php
session_start();
require '../config.php';

// Ensure admin/headteacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Get section ID and school year from URL
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';

if (!$sectionId || !$school_year) {
    echo "<div class='alert alert-danger'>Missing section ID or school year.</div>";
    exit;
}

// Fetch section details including adviser
$sectionQuery = $conn->prepare("
    SELECT s.SectionID, s.SectionName, s.GradeLevel, 
           t.fName, t.lName, t.mName, t.surfix, t.TeacherID
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
$adviserName = 'Not assigned';
if ($section['fName']) {
    $adviserName = $section['lName'] . ', ' . $section['fName'];
    if ($section['mName']) {
        $adviserName .= ' ' . substr($section['mName'], 0, 1) . '.';
    }
    if ($section['surfix']) {
        $adviserName .= ' ' . $section['surfix'];
    }
}

// Fetch all subjects assigned to this section for the school year with teacher information
$subjectsQuery = "
    SELECT 
        sub.SubjectID,
        sub.SubjectName,
        sub.written_work_percentage,
        sub.performance_task_percentage,
        sub.quarterly_assessment_percentage,
        a.teacher_id,
        t.fName as TeacherFirstName,
        t.lName as TeacherLastName,
        t.mName as TeacherMiddleName,
        t.surfix as TeacherSuffix
    FROM assigned_subject a
    JOIN subject sub ON a.subject_id = sub.SubjectID
    LEFT JOIN teacher t ON a.teacher_id = t.TeacherID
    WHERE a.section_id = ? AND a.school_year = ?
    ORDER BY sub.SubjectName
";

$stmt = $conn->prepare($subjectsQuery);
$stmt->bind_param('is', $sectionId, $school_year);
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
      border: 1px solid #e3e6f0;
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
      background: #f8f9fc;
      border-left: 4px solid #4e73df;
    }
    .teacher-info {
      background-color: #f8f9fa;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 10px;
      font-size: 0.9rem;
    }
    .stats-badge {
      background: #e3f2fd;
      color: #1976d2;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
    }
    .school-year-badge {
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
    }
    .quarter-indicators {
      display: flex;
      justify-content: space-between;
      margin: 15px 0;
    }
    .quarter-indicator {
      flex: 1;
      text-align: center;
      padding: 8px 5px;
      margin: 0 2px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.8rem;
      transition: all 0.3s ease;
    }
    .quarter-active {
      background-color: #28a745;
      color: white;
      box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }
    .quarter-inactive {
      background-color: #6c757d;
      color: white;
      opacity: 0.6;
    }
    .quarter-label {
      font-size: 0.7rem;
      opacity: 0.9;
    }
    .indicator-tooltip {
      position: relative;
      cursor: pointer;
    }
    .indicator-tooltip:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #333;
      color: white;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 0.7rem;
      white-space: nowrap;
      z-index: 1000;
    }
    .percentage-info {
      font-size: 0.8rem;
      color: #6c757d;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <div class="page-header">
      <div class="row align-items-center">
        <div class="col">
          <h1 class="h3 mb-2">
            <i class="fas fa-book me-2"></i>
            Subjects for Grade <?= htmlspecialchars($section['GradeLevel']) ?> - <?= htmlspecialchars($section['SectionName']) ?>
          </h1>
          <?php if ($adviserName): ?>
            <p class="mb-1 text-muted">Adviser: <?= htmlspecialchars($adviserName) ?></p>
          <?php endif; ?>
        </div>
        <div class="col-auto">
          <span class="school-year-badge border border-2">
            <i class="fas fa-calendar me-1"></i>
            School Year: <?= htmlspecialchars($school_year) ?>
          </span>
        </div>
      </div>
    </div>

    <?php if ($subjectsResult->num_rows === 0): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No subjects found for this section in the selected school year.
      </div>
    <?php else: ?>
      <div class="row">
        <?php while ($subject = $subjectsResult->fetch_assoc()): 
          // Build teacher name for subject
          $subjectTeacherName = 'Not assigned';
          if ($subject['TeacherFirstName']) {
            $subjectTeacherName = $subject['TeacherLastName'] . ', ' . $subject['TeacherFirstName'];
            if ($subject['TeacherMiddleName']) {
              $subjectTeacherName .= ' ' . substr($subject['TeacherMiddleName'], 0, 1) . '.';
            }
            if ($subject['TeacherSuffix']) {
              $subjectTeacherName .= ' ' . $subject['TeacherSuffix'];
            }
          }

          // Check for grades in each quarter for this subject and school year
          $quarters = [];
          for ($quarter = 1; $quarter <= 4; $quarter++) {
            $gradeCheckQuery = $conn->prepare("
              SELECT COUNT(*) as grade_count 
              FROM grades_details 
              WHERE subjectID = ? 
              AND school_year = ? 
              AND quarter = ?
            ");
            $gradeCheckQuery->bind_param('isi', $subject['SubjectID'], $school_year, $quarter);
            $gradeCheckQuery->execute();
            $gradeCheckResult = $gradeCheckQuery->get_result();
            $gradeData = $gradeCheckResult->fetch_assoc();
            $quarters[$quarter] = $gradeData['grade_count'] > 0;
            $gradeCheckQuery->close();
          }
        ?>
          <div class="col-md-6 col-lg-4 mb-4">
            <div class="card subject-card">
              <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><?= htmlspecialchars($subject['SubjectName']) ?></h5>
              </div>
              <div class="card-body">
                
                <div class="teacher-info">
                  <strong><i class="fas fa-chalkboard-teacher me-1"></i>Teacher:</strong><br>
                  <?= htmlspecialchars($subjectTeacherName) ?>
                </div>

                <!-- Grading Components -->
                <div class="percentage-info">
                  <small>
                    <strong>Grading Components:</strong><br>
                    WW: <?= htmlspecialchars($subject['written_work_percentage']*100) ?>% | 
                    PT: <?= htmlspecialchars($subject['performance_task_percentage']*100) ?>% | 
                    QA: <?= htmlspecialchars($subject['quarterly_assessment_percentage']*100) ?>%
                  </small>
                </div>

                <!-- Quarter Indicators -->
                <div class="quarter-indicators">
                  <?php for ($quarter = 1; $quarter <= 4; $quarter++): 
                    $hasGrades = $quarters[$quarter];
                    $tooltip = $hasGrades ? 
                      "Q$quarter: Grades uploaded" : 
                      "Q$quarter: No grades yet";
                  ?>
                    <div class="quarter-indicator indicator-tooltip <?= $hasGrades ? 'quarter-active' : 'quarter-inactive' ?>" 
                         data-tooltip="<?= $tooltip ?>">
                      <div class="quarter-label">Q<?= $quarter ?></div>
                      <i class="fas <?= $hasGrades ? 'fa-check' : 'fa-times' ?>"></i>
                    </div>
                  <?php endfor; ?>
                </div>
                
                <div class="d-grid">
                  <a href="view_grades.php?subject_id=<?= $subject['SubjectID'] ?>&section_id=<?= $sectionId ?>&school_year=<?= urlencode($school_year) ?>" 
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