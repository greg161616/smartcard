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
        display: flex;
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
    
    .bg-icon-class { background: #e3f2fd; color: #0d6efd; }
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
        max-width: 200px;
    }
    
    .custom-filter-input:focus {
        outline: none;
    }

    .filter-divider {
        width: 1px;
        height: 40px;
        background: #e9ecef;
        margin: 0 16px;
    }

    .btn-apply-filter {
        background: #1a1f2e;
        color: #ffffff;
        border-radius: 8px;
        font-weight: 600;
        padding: 10px 20px;
        transition: background 0.2s;
        border: none;
    }
    .btn-apply-filter:hover {
        background: #2a3142;
        color: #ffffff;
    }
    
    .btn-reset-filter {
        background: #ffffff;
        color: #6c757d;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-weight: 600;
        padding: 10px 20px;
        transition: all 0.2s;
    }
    .btn-reset-filter:hover {
        background: #f8f9fa;
        color: #495057;
        border-color: #adb5bd;
    }

    .custom-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.04);
        background: #ffffff;
        margin-bottom: 24px;
        overflow: hidden;
    }
    .custom-card-header {
        background: transparent !important;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.25rem 1.5rem;
        font-weight: 700;
        color: #1a1f2e;
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
    .student-badge {
        background: #f0f2f5;
        color: #495057;
        font-weight: 600;
        border-radius: 6px;
        padding: 6px 10px;
    }
    
    .grading-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
        background: #ffffff;
        border-left: 4px solid #1a1f2e;
    }
    .grading-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    }
    .grading-card .card-header {
        background: transparent !important;
        border-bottom: 1px solid rgba(0,0,0,0.03);
        padding: 1.25rem;
    }
    .grading-card .card-body {
        padding: 1.25rem;
    }

    .action-btn-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 16px;
    }
    .action-btn-row {
        display: flex;
        gap: 8px;
    }
    .btn-grading-action {
        flex: 1;
        border-radius: 8px;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
    }
    .btn-spreadsheet {
        background: #cee8dcff;
        color: #0f5132;
        border: 1px solid #0c4128;  
    }
    .btn-spreadsheet:hover { background: #badbcc; color: #0c4128; }
    
    .btn-upload {
        background: #fff3cd;
        color: #997404;
    }
    .btn-upload:hover { background: #ffe69c; color: #856404; }

    .btn-view {
        background: #1a1f2e;
        color: #ffffff;
    }
    .btn-view:hover { background: #2a3142; color: #ffffff; }

    /* Responsive card columns */
    .card-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.5rem;
      padding: 0.5rem;
    }
    @media (max-width: 1400px) {
        .card-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 1100px) {
        .card-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 768px) {
        .card-grid {
            grid-template-columns: 1fr;
        }
    }

    /* View Toggle Styles */
    .view-toggle {
        display: flex;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 4px;
        gap: 4px;
        border: 1px solid #e9ecef;
    }
    .btn-view-toggle {
        background: transparent;
        border: none;
        color: #6c757d;
        border-radius: 6px;
        padding: 6px 12px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-view-toggle:hover {
        color: #1a1f2e;
        background: #e9ecef;
    }
    .btn-view-toggle.active {
        background: #ffffff;
        color: #1a1f2e;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    /* Table styles for list view */
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
    .table thead th {
        background-color: #ffffff;
        border-bottom: 2px solid #f0f2f5;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        padding: 16px;
    }
    .table tbody td {
        padding: 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f2f5;
    }
  </style>
  <script>
    function resetFilters() {
      window.location.href = window.location.pathname;
    }

    document.addEventListener("DOMContentLoaded", () => {
        const gridBtn = document.getElementById("btn-grid-view");
        const listBtn = document.getElementById("btn-list-view");
        const gridView = document.getElementById("grading-grid-view");
        const listView = document.getElementById("grading-list-view");

        if(gridBtn && listBtn && gridView && listView) {
            // Load preference
            const viewPref = localStorage.getItem('gradingViewPref') || 'grid';
            if(viewPref === 'list') {
                setListView();
            }

            gridBtn.addEventListener("click", setGridView);
            listBtn.addEventListener("click", setListView);

            function setGridView() {
                gridBtn.classList.add("active");
                listBtn.classList.remove("active");
                gridView.classList.remove("d-none");
                listView.classList.add("d-none");
                localStorage.setItem('gradingViewPref', 'grid');
            }

            function setListView() {
                listBtn.classList.add("active");
                gridBtn.classList.remove("active");
                listView.classList.remove("d-none");
                gridView.classList.add("d-none");
                localStorage.setItem('gradingViewPref', 'list');
            }
        }
    });
  </script>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>
  
  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Grade Management</h2>
    </div>

    <!-- Modern Filter Widget -->
    <form method="POST" id="filterForm" class="filter-widget">
        <div class="filter-group">
            <div class="filter-icon-wrapper bg-icon-class">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="filter-input-wrapper">
                <span class="filter-label-floating">Filter by Class</span>
                <select class="custom-filter-input" id="section_filter" name="section_filter">
                    <option value="">All My Classes</option>
                    <?php foreach ($sections as $section): ?>
                      <option value="<?= $section['SectionID'] ?>" <?= ($selectedSection == $section['SectionID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($section['SectionDisplay']) ?>
                      </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="filter-divider d-none d-md-block"></div>

        <div class="filter-group">
            <div class="filter-icon-wrapper bg-icon-year">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="filter-input-wrapper">
                <span class="filter-label-floating">School Year</span>
                <select class="custom-filter-input" id="school_year_filter" name="school_year_filter">
                    <?php foreach ($schoolYears as $year): ?>
                      <option value="<?= $year ?>" <?= ($selectedSchoolYear == $year) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>
                      </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="ms-md-auto mt-3 mt-md-0 d-flex gap-2 w-100 w-md-auto justify-content-end">
            <button type="button" class="btn-reset-filter flex-grow-1 flex-md-grow-0" onclick="resetFilters()">Reset</button>
            <button type="submit" class="btn-apply-filter flex-grow-1 flex-md-grow-0">Apply</button>
        </div>
    </form>

    <!-- Main Grades Card -->
    <div class="row">
      <div class="col-md-12">
        <div class="custom-card mb-4">
          <div class="custom-card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
              <i class="bi bi-journal-bookmark-fill text-primary me-2"></i>My Grading Sheets
            </h5>
            <div class="view-toggle">
                <button type="button" class="btn-view-toggle active" id="btn-grid-view" title="Grid View"><i class="bi bi-grid-fill"></i></button>
                <button type="button" class="btn-view-toggle" id="btn-list-view" title="List View"><i class="bi bi-list-ul"></i></button>
            </div>
          </div>
          <div class="card-body">
            <?php if (empty($assignments)): ?>
              <div class="alert alert-info m-4 border-0 text-center py-4" style="background: #f8f9fa; color: #6c757d; border-radius: 12px;">
                <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                No classes match the selected filters for <?php echo htmlspecialchars($selectedSchoolYear); ?>.
              </div>
            <?php else: ?>
              <!-- Card grid for all screen sizes -->
              <div class="card-grid" id="grading-grid-view">
                <?php foreach ($assignments as $a): ?>
                  <div class="card grading-card">
                    <div class="card-header py-3">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <h6 class="mb-1 fw-bold text-dark fs-5"><?= htmlspecialchars($a['SubjectName']) ?></h6>
                          <div class="text-muted" style="font-weight: 500;">
                            Grade <?= $a['GradeLevel'] ?> • <?= htmlspecialchars($a['SectionName']) ?>
                          </div>
                        </div>
                        <span class="student-badge"><i class="bi bi-people-fill me-1"></i><?= $a['StudentCount'] ?></span>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="mb-2">
                        <small class="text-muted fw-semibold">
                          <i class="bi bi-calendar3 me-2"></i>School Year: <?= htmlspecialchars($a['SchoolYear']) ?>
                        </small>
                      </div>
                      
                      <div class="action-btn-group">
                        <a href="spread_sheet.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($a['SchoolYear']) ?>" class="btn-grading-action btn-spreadsheet w-100" style="justify-content: center;">
                          <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Open Spreadsheet
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- List view for all screen sizes -->
              <div class="table-responsive d-none" id="grading-list-view">
                <table class="table table-hover align-middle border-0 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Subject</th>
                            <th>Section</th>
                            <th>School Year</th>
                            <th>Students</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark fs-6"><?= htmlspecialchars($a['SubjectName']) ?></td>
                            <td>
                                <div class="text-muted" style="font-weight: 500;">Grade <?= $a['GradeLevel'] ?> • <?= htmlspecialchars($a['SectionName']) ?></div>
                            </td>
                            <td><small class="text-muted fw-semibold"><i class="bi bi-calendar3 me-2"></i><?= htmlspecialchars($a['SchoolYear']) ?></small></td>
                            <td><span class="student-badge"><i class="bi bi-people-fill me-1"></i><?= $a['StudentCount'] ?></span></td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="spread_sheet.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($a['SchoolYear']) ?>" class="btn-grading-action btn-spreadsheet" style="flex: 0 1 auto; padding: 6px 16px;" title="Spreadsheet">
                                      <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Spreadsheet
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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