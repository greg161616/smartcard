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
    body { background: #F5F0E8; } /* Matching cream background */
    
    .page-title {
        color: #1a1f2e;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 0;
    }
    
    .filter-widget {
        background: #ffffff;
        border-radius: 16px;
        padding: 16px 28px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.04);
        display: inline-flex;
        align-items: center;
        margin-bottom: 32px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 8px 12px;
        border-radius: 12px;
        transition: background 0.2s;
    }
    
    .filter-group:hover {
        background: #f8f9fa;
    }

    .filter-icon-wrapper {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .bg-icon-date { background: #e3f2fd; color: #0d6efd; }
    .bg-icon-year { background: #fff3cd; color: #ffc107; }

    .filter-input-wrapper {
        display: flex;
        flex-direction: column;
    }

    .filter-label-floating {
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #adb5bd;
        margin-bottom: 2px;
        letter-spacing: 0.5px;
    }

    .custom-filter-input {
        border: none;
        background: transparent;
        font-weight: 700;
        font-size: 1.1rem;
        color: #1a1f2e;
        padding: 0;
        cursor: pointer;
        outline: none;
        max-width: 150px;
    }

    .filter-divider {
        width: 1px;
        height: 40px;
        background: #e9ecef;
        margin: 0 16px;
    }

    .class-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.04);
        background: #ffffff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    
    .class-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.08);
    }

    .class-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
        background: #3a7bd5; /* Accent color */
    }

    .class-card .card-body {
        padding: 24px;
    }

    .class-card .card-title {
        color: #1a1f2e;
        font-weight: 700;
        font-size: 1.25rem;
        margin-bottom: 12px;
    }

    .class-card .card-text {
        color: #495057;
        margin-bottom: 8px;
    }

    .btn-take-attendance {
        background: #1a1f2e;
        color: #ffffff;
        border-radius: 8px;
        font-weight: 600;
        padding: 8px 16px;
        transition: background 0.2s;
        border: none;
    }
    
    .btn-take-attendance:hover {
        background: #2a3142;
        color: #ffffff;
    }
    
    .alert-info-custom {
        background: #e3f2fd;
        border-color: #b6effb;
        color: #055160;
        border-radius: 12px;
        font-weight: 500;
    }
  </style>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>

  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Take Attendance</h2>
    </div>

    <!-- Modern Filter Widget -->
    <div class="filter-widget">
        <div class="filter-group">
            <div class="filter-icon-wrapper bg-icon-date">
                <i class="bi bi-calendar-event-fill"></i>
            </div>
            <div class="filter-input-wrapper">
                <span class="filter-label-floating">Attendance Date</span>
                <form method="get" id="dateForm" class="m-0">
                    <input 
                        type="date" 
                        name="date" 
                        value="<?= htmlspecialchars($date) ?>" 
                        class="custom-filter-input" 
                        onchange="document.getElementById('dateForm').submit();" 
                        required>
                    <input type="hidden" name="school_year" value="<?= htmlspecialchars($selectedSchoolYear) ?>">
                </form>
            </div>
        </div>
        
        <div class="filter-divider d-none d-md-block"></div>

        <div class="filter-group">
            <div class="filter-icon-wrapper bg-icon-year">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="filter-input-wrapper">
                <span class="filter-label-floating">School Year</span>
                <form method="get" id="yearForm" class="m-0">
                    <select name="school_year" class="custom-filter-input" onchange="document.getElementById('yearForm').submit();">
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

      <div class="card-body">
        <?php if (empty($sections)): ?>
          <div class="alert alert-warning mb-0" style="border-radius: 12px;">
            You don't have any scheduled classes for the selected school year (<?= htmlspecialchars($selectedSchoolYear) ?>).
          </div>
        <?php else: ?>
          <div class="row g-4 mb-5 mt-2">
            <?php foreach ($sections as $sec): ?>
              <div class="col-sm-6 col-lg-4">
                <div class="class-card">
                  <div class="card-body d-flex flex-column h-100">
                    <h5 class="card-title">
                      Grade <?= htmlspecialchars($sec['GradeLevel']) ?> - <?= htmlspecialchars($sec['SectionName']) ?>
                    </h5>
                    <div class="mb-4 mt-2">
                        <span class="badge bg-light text-dark border px-2 py-1 me-2">
                          <i class="bi bi-mortarboard text-muted"></i> <?= htmlspecialchars($selectedSchoolYear) ?>
                        </span>
                    </div>
                    <div class="mt-auto pt-3 border-top text-end">
                      <a 
                        href="attendance.php?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>&school_year=<?= urlencode($selectedSchoolYear) ?>" 
                        class="btn btn-take-attendance">
                        Take Attendance <i class="bi bi-arrow-right ms-1"></i>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</div><!-- /.page-content -->
</body>
</html>