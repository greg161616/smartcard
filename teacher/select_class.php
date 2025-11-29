<?php
session_start();
require_once '../config.php';
date_default_timezone_set('Asia/Manila');
// 1) Ensure teacher is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// 2) Lookup TeacherID and Teacher Name from session email
$teacherEmail = $_SESSION['email'];
$stmt = $conn->prepare("
    SELECT t.TeacherID, t.fName,t.mName , t.lName
    FROM teacher t
    JOIN user u ON t.UserID = u.UserID
    WHERE u.Email = ?
");
$stmt->bind_param('s', $teacherEmail);
$stmt->execute();
$stmt->bind_result($teacherID, $firstName,$middleName, $lastName);
$stmt->fetch();
$stmt->close();

if (!$teacherID) {
    die("Teacher record not found.");
}

// Set teacher name variable
$teacher_name = $firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName;

// 3) Get current school year
// Fetch all school years from school_year table for dropdown
$schoolYearQuery = $conn->query("SELECT school_year FROM school_year ORDER BY school_year DESC");
$schoolYears = [];
if ($schoolYearQuery && $schoolYearQuery->num_rows > 0) {
  while ($row = $schoolYearQuery->fetch_assoc()) {
    $schoolYears[] = $row['school_year'];
  }
}
// Get active school year for default selection
$activeYearQuery = $conn->query("SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
$selectedSchoolYear = '';
if ($activeYearQuery && $activeYearQuery->num_rows > 0) {
  $selectedSchoolYear = $activeYearQuery->fetch_assoc()['school_year'];
}
if (empty($selectedSchoolYear)) {
  $selectedSchoolYear = !empty($schoolYears) ? $schoolYears[0] : (date('Y') . '-' . (date('Y') + 1));
}

// 4) Get date from GET (defaults to today)
// Get selected school year from GET or use default
$selectedSchoolYear = isset($_GET['school_year']) ? $_GET['school_year'] : $selectedSchoolYear;

$date = $_GET['date'] ?? date('Y-m-d');

// 5) Fetch distinct sections this teacher teaches using assigned_subject table
$sql = "
  SELECT DISTINCT
    sec.SectionID,
    sec.GradeLevel,
    sec.SectionName
  FROM assigned_subject a
  JOIN section sec ON a.section_id = sec.SectionID
  JOIN section_enrollment se ON se.SectionID = sec.SectionID
  WHERE a.teacher_id = ? AND a.school_year = ? AND se.SchoolYear = ?
  ORDER BY sec.GradeLevel, sec.SectionName
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $teacherID, $selectedSchoolYear, $selectedSchoolYear);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Select Class for Attendance</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; }
    .card-container {
      max-width: 900px;
      margin: 60px auto;
    }
    .dashboard-header {
        background: #2c3e50;
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>

  <div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                <p class="lead mb-0">Attendance - <?php echo date('F j, Y'); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <label for="school_year" class="fw-bold text-white mb-0">School Year:</label>
                <div class="d-inline-block mt-2">
                    <form method="get" class="d-flex align-items-center gap-2">
                        <select name="school_year" id="school_year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $year == $selectedSchoolYear ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>
  </div>

  <div class="container-fluid">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Take Attendance</h5>
        <!-- Date selector form -->
        <form method="get" class="d-flex">
          <input 
            type="date" 
            name="date" 
            value="<?= htmlspecialchars($date) ?>" 
            class="form-control form-control-sm me-2" 
            required>
          <input type="hidden" name="school_year" value="<?= htmlspecialchars($selectedSchoolYear) ?>">
          <button type="submit" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </form>
      </div>

      <div class="card-body">
        <?php if (empty($sections)): ?>
          <div class="alert alert-warning mb-0">
            You don't have any scheduled classes for the selected school year (<?= htmlspecialchars($selectedSchoolYear) ?>).
          </div>
        <?php else: ?>
          <div class="row g-4">
            <?php foreach ($sections as $sec): ?>
              <div class="col-sm-6 col-lg-4">
                <div class="card h-100">
                  <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                      Grade <?= htmlspecialchars($sec['GradeLevel']) ?> - 
                      <?= htmlspecialchars($sec['SectionName']) ?>
                    </h5>
                    <p class="card-text mt-auto">
                      Date: <?= htmlspecialchars($date) ?>
                    </p>
                    <small class="text-muted">
                      School Year: <?= htmlspecialchars($selectedSchoolYear) ?>
                    </small>
                  </div>
                  <div class="card-footer bg-transparent text-end">
                    <a 
                      href="attendance.php?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>&school_year=<?= urlencode($selectedSchoolYear) ?>" 
                      class="btn btn-success btn-sm">
                      <i class="bi bi-check-circle"></i>
                      Take Attendance
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>