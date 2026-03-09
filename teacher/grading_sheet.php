<?php
// File: select_grades.php
session_start();
require '../config.php';
date_default_timezone_set('Asia/Manila');

// 1) Ensure teacher is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 2) Lookup TeacherID and Teacher Name from UserID
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT t.TeacherID, t.fName, t.mName, t.lName
    FROM teacher t 
    JOIN user u ON t.UserID = u.UserID 
    WHERE t.UserID = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not registered as a teacher.";
    exit;
}
$teacherData = $res->fetch_assoc();
$teacherId = $teacherData['TeacherID'];
$teacher_name = $teacherData['fName'] . ' ' . ($teacherData['mName'] ? $teacherData['mName'] . ' ' : '') . $teacherData['lName'];
$stmt->close();

// 3) Get current active school year (default)
$schoolYearQuery = $conn->query("SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
if ($schoolYearQuery && $schoolYearQuery->num_rows > 0) {
    $schoolYearData = $schoolYearQuery->fetch_assoc();
    $defaultSchoolYear = $schoolYearData['school_year'];
} else {
    $defaultSchoolYear = date('Y') . '-' . (date('Y')+1); // fallback
}

// 4) Handle filter POST
$selectedSection = isset($_POST['section_filter']) ? $_POST['section_filter'] : '';
$selectedSchoolYear = isset($_POST['school_year_filter']) ? $_POST['school_year_filter'] : $defaultSchoolYear;

// 5) Fetch all sections this teacher has ever taught (for dropdown)
$sections = [];
$sectionQuery = "
    SELECT DISTINCT sec.SectionID, 
           CONCAT('Grade ', sec.GradeLevel, ' - ', sec.SectionName) AS SectionDisplay
    FROM assigned_subject a
    JOIN section sec ON a.section_id = sec.SectionID
    WHERE a.teacher_id = ?
    ORDER BY sec.GradeLevel, sec.SectionName
";
$stmt = $conn->prepare($sectionQuery);
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$sectionsResult = $stmt->get_result();
while ($row = $sectionsResult->fetch_assoc()) {
    $sections[] = $row;
}
$stmt->close();

// 6) Fetch all school years this teacher has assignments in (for dropdown)
$schoolYears = [];
$yearQuery = "
    SELECT DISTINCT a.school_year
    FROM assigned_subject a
    WHERE a.teacher_id = ?
    ORDER BY a.school_year DESC
";
$stmt = $conn->prepare($yearQuery);
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$yearsResult = $stmt->get_result();
while ($row = $yearsResult->fetch_assoc()) {
    $schoolYears[] = $row['school_year'];
}
$stmt->close();

// 7) Build the main assignments query with optional filters
$sql = "
    SELECT 
        s.SubjectID,
        s.SubjectName,
        sec.SectionID,
        sec.GradeLevel,
        sec.SectionName,
        a.school_year AS SchoolYear,
        COUNT(se.StudentID) AS StudentCount,
        s.written_work_percentage,
        s.performance_task_percentage,
        s.quarterly_assessment_percentage
    FROM assigned_subject a
    JOIN subject s ON a.subject_id = s.SubjectID
    JOIN section sec ON a.section_id = sec.SectionID
    JOIN section_enrollment se ON sec.SectionID = se.SectionID
    WHERE a.teacher_id = ?
      AND se.SchoolYear = a.school_year
      AND se.status = 'active'
";

$params = [$teacherId];
$types = 'i';

if (!empty($selectedSection)) {
    $sql .= " AND sec.SectionID = ?";
    $params[] = $selectedSection;
    $types .= 'i';
}
if (!empty($selectedSchoolYear)) {
    $sql .= " AND a.school_year = ?";
    $params[] = $selectedSchoolYear;
    $types .= 's';
}

$sql .= " GROUP BY s.SubjectID, sec.SectionID, a.school_year
          ORDER BY s.SubjectName, sec.GradeLevel, sec.SectionName";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Grade Upload &amp; View</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .header_c {
        background: #2c3e50;
        color: white;
        padding: 1rem 0;
        margin-bottom: 1rem;
    }
    .card-action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .card-action-buttons .btn {
      flex: 1 1 auto;
      min-width: 100px;
      font-size: 0.85rem;
    }
    .mobile-card {
      border-left: 4px solid #0dcaf0;
      height: 100%;
    }
    .student-count-badge {
      font-size: 0.8rem;
      padding: 4px 8px;
    }
    /* Responsive card columns */
    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1rem;
    }
    @media (max-width: 576px) {
      .card-grid {
        grid-template-columns: 1fr;
      }
    }
    .container-fluid {
      padding-left: 0.75rem;
      padding-right: 0.75rem;
    }
    /* Remove table-specific styles since we're only using cards */
  </style>
  <script>
    function resetFilters() {
      window.location.href = window.location.pathname;
    }
  </script>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>
  
  <!-- Header Section -->
  <div class="header_c">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                <p class="mb-2">Grade Management - <?php echo date('F j, Y'); ?></p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="bg-white rounded-pill px-3 py-2 d-inline-block">
                    <small class="text-muted">School Year: <?php echo htmlspecialchars($selectedSchoolYear); ?></small>
                </div>
            </div>
        </div>
    </div>
  </div>

  <div class="container-fluid">
    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
      <div class="card-header py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-funnel me-2"></i>Filter Classes</h5>
      </div>
      <div class="card-body">
        <form method="POST" id="filterForm">
          <div class="row align-items-end">
            <div class="col-md-3">
              <label for="section_filter" class="form-label">Filter by Class:</label>
              <select class="form-select" id="section_filter" name="section_filter">
                <option value="">All My Classes</option>
                <?php foreach ($sections as $section): ?>
                  <option value="<?= $section['SectionID'] ?>" <?= ($selectedSection == $section['SectionID']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($section['SectionDisplay']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="school_year_filter" class="form-label">Filter by School Year:</label>
              <select class="form-select" id="school_year_filter" name="school_year_filter">
                <?php foreach ($schoolYears as $year): ?>
                  <option value="<?= $year ?>" <?= ($selectedSchoolYear == $year) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($year) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-auto mt-2 mt-md-0">
              <button type="submit" class="btn btn-primary">Apply Filters</button>
              <button type="button" class="btn btn-secondary ms-2" onclick="resetFilters()">Reset Filters</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Main Grades Card -->
    <div class="row">
      <div class="col-md-12">
        <div class="card shadow-sm mb-4">
          <div class="card-header  py-3">
            <h5 class="mb-0 fw-bold">
              <i class="bi bi-table me-2"></i>Grade Upload &amp; View
            </h5>
          </div>
          <div class="card-body">
            <?php if (empty($assignments)): ?>
              <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No classes match the selected filters for <?php echo htmlspecialchars($selectedSchoolYear); ?>.
              </div>
            <?php else: ?>
              <!-- Card grid for all screen sizes -->
              <div class="card-grid">
                <?php foreach ($assignments as $a): ?>
                  <div class="card mobile-card border-1 shadow-sm">
                    <div class="card-header bg-light py-3">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <h6 class="mb-1 fw-bold"><?= htmlspecialchars($a['SubjectName']) ?></h6>
                          <small class="text-muted">
                            Grade <?= $a['GradeLevel'] ?> • <?= htmlspecialchars($a['SectionName']) ?>
                          </small>
                        </div>
                        <span class="badge bg-secondary"><?= $a['StudentCount'] ?> students</span>
                      </div>
                    </div>
                    <div class="card-body py-3">
                      <div class="row align-items-center mb-2">
                        <div class="col-12">
                          <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i>School Year: <?= htmlspecialchars($a['SchoolYear']) ?>
                          </small>
                        </div>
                      </div>
                      <div class="card-action-buttons">
                        <a
                          href="spread_sheet.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($a['SchoolYear']) ?>"
                          class="btn btn-success btn-sm"
                        >
                          <i class="bi bi-table me-1"></i> Spreadsheet
                        </a>
                        <a
                          href="grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($a['SchoolYear']) ?>"
                          class="btn btn-primary btn-sm"
                        >
                          <i class="bi bi-upload me-1"></i> Upload
                        </a>
                        <a
                          href="view_grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($a['SchoolYear']) ?>"
                          class="btn btn-info btn-sm"
                        >
                          <i class="bi bi-eye me-1"></i> View
                        </a>
                        <!-- Values button removed as requested -->
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</div><!-- /.page-content -->
</body>
</html>