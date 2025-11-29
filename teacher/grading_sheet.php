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

// 3) Get current school year
$schoolYearQuery = $conn->query("SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
if ($schoolYearQuery && $schoolYearQuery->num_rows > 0) {
    $schoolYearData = $schoolYearQuery->fetch_assoc();
    $selectedSchoolYear = $schoolYearData['school_year'];
} 

// 4) FIXED: Fetch all subject-section assignments for this teacher using assigned_subject table
$sql = "
    SELECT 
        s.SubjectID,
        s.SubjectName,
        sec.SectionID,
        sec.GradeLevel,
        sec.SectionName,
        a.school_year as SchoolYear,
        COUNT(se.StudentID) as StudentCount,
        s.written_work_percentage,
        s.performance_task_percentage,
        s.quarterly_assessment_percentage
    FROM assigned_subject a
    JOIN subject s ON a.subject_id = s.SubjectID
    JOIN section sec ON a.section_id = sec.SectionID
    JOIN section_enrollment se ON sec.SectionID = se.SectionID
    WHERE a.teacher_id = ? 
      AND a.school_year = ?
      AND se.SchoolYear = ?
      AND se.status = 'active'
    GROUP BY s.SubjectID, sec.SectionID, a.school_year
    ORDER BY s.SubjectName, sec.GradeLevel, sec.SectionName
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $teacherId, $selectedSchoolYear, $selectedSchoolYear);
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
      flex: 1;
      min-width: 120px;
      font-size: 0.85rem;
    }
    .mobile-card {
      border-left: 4px solid #0dcaf0;
    }
    
    /* Improved Table Styles */
    .table-container {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .table th {
      background-color: #d1ecf1;
      border-bottom: 2px solid #bee5eb;
      font-weight: 600;
      padding: 12px 8px;
      font-size: 0.9rem;
    }
    
    .table td {
      padding: 10px 8px;
      vertical-align: middle;
      border-color: #e9ecef;
    }
    
    .table tbody tr:hover {
      background-color: #f8f9fa;
    }
    
    .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      justify-content: center;
    }
    
    .action-buttons .btn {
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 4px;
      white-space: nowrap;
    }
    
    .student-count-badge {
      font-size: 0.8rem;
      padding: 4px 8px;
    }
    
    /* Mobile-specific improvements */
    @media (max-width: 768px) {
      .desktop-table {
        display: none;
      }
      .mobile-cards {
        display: block;
      }
      .header_c {
        padding: 0.75rem 0;
        margin-bottom: 1rem;
      }
      .header_c h1 {
        font-size: 1.25rem;
        margin-bottom: 0.25rem;
      }
      .header_c .lead {
        font-size: 0.8rem;
        margin-bottom: 0.5rem;
      }
      .school-year-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
      }
      .card-header h5 {
        font-size: 1rem;
      }
      .mobile-card .card-header h6 {
        font-size: 0.95rem;
      }
      .mobile-card .card-body {
        padding: 0.75rem;
      }
      .card-action-buttons {
        flex-direction: column;
      }
      .card-action-buttons .btn {
        width: 100%;
        margin-bottom: 0.25rem;
      }
    }
    
    @media (min-width: 769px) {
      .mobile-cards {
        display: none;
      }
      .desktop-table {
        display: block;
      }
      
      /* Improved desktop table layout */
      .table-container {
        margin: 0;
      }
      
      .table {
        margin-bottom: 0;
      }
      
      /* Fixed column widths for better layout */
      .table th:nth-child(1),
      .table td:nth-child(1) {
        width: 25%;
      }
      
      .table th:nth-child(2),
      .table td:nth-child(2) {
        width: 20%;
      }
      
      .table th:nth-child(3),
      .table td:nth-child(3) {
        width: 15%;
        text-align: center;
      }
      
      .table th:nth-child(4),
      .table td:nth-child(4) {
        width: 15%;
      }
      
      .table th:nth-child(5),
      .table td:nth-child(5) {
        width: 25%;
      }
    }
    
    /* Ensure proper spacing on all devices */
    .container-fluid {
      padding-left: 0.75rem;
      padding-right: 0.75rem;
    }
    
    @media (max-width: 576px) {
      .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
      }
    }
    
    /* Card header improvements */
    .card-header.bg-primary {
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;
    }
  </style>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>
  
  <!-- Improved Header Section -->
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
    <div class="row">
      <div class="col-md-12">
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0 fw-bold">
              <i class="bi bi-table me-2"></i>Grade Upload &amp; View
            </h5>
          </div>
          <div class="card-body p-0">
            <?php if (empty($assignments)): ?>
              <div class="alert alert-info m-3">
                <i class="bi bi-info-circle me-2"></i>
                You have no classes assigned for the <?php echo htmlspecialchars($selectedSchoolYear); ?> school year.
              </div>
            <?php else: ?>
              <!-- Desktop Table View - IMPROVED -->
              <div class="table-container desktop-table">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-info">
                      <tr>
                        <th>Subject</th>
                        <th>Section</th>
                        <th>Students</th>
                        <th>School Year</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($assignments as $a): ?>
                        <tr>
                          <td class="fw-semibold"><?= htmlspecialchars($a['SubjectName']) ?></td>
                          <td>
                            <span class="text-muted small">Grade <?= $a['GradeLevel'] ?></span><br>
                            <strong><?= htmlspecialchars($a['SectionName']) ?></strong>
                          </td>
                          <td class="text-center">
                            <span class="badge bg-primary student-count-badge">
                              <?= $a['StudentCount'] ?> student<?= $a['StudentCount'] != 1 ? 's' : '' ?>
                            </span>
                          </td>
                          <td class="text-muted small"><?= htmlspecialchars($a['SchoolYear']) ?></td>
                          <td>
                            <div class="action-buttons">
                              <a
                                href="spread_sheet.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>"
                                class="btn btn-success btn-sm"
                                title="Spreadsheet View"
                              >
                                <i class="bi bi-table"></i>
                              </a>
                              <a
                                href="grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>"
                                class="btn btn-primary btn-sm"
                                title="Upload Grades"
                              >
                                <i class="bi bi-upload"></i>
                              </a>
                              <a
                                href="view_grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>"
                                class="btn btn-info btn-sm"
                                title="View Grades"
                              >
                                <i class="bi bi-eye"></i>
                              </a>
                              <a 
                                href="values.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>" 
                                class="btn btn-secondary btn-sm"
                                title="Values"
                              >
                                <i class="bi bi-clipboard-data"></i>
                              </a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Mobile Cards View - IMPROVED -->
              <div class="mobile-cards p-3">
                <div class="row g-3">
                  <?php foreach ($assignments as $a): ?>
                    <div class="col-12">
                      <div class="card mobile-card border-0 shadow-sm">
                        <div class="card-header bg-light py-3">
                          <div class="d-flex justify-content-between align-items-start">
                            <div>
                              <h6 class="mb-1 fw-bold text-primary"><?= htmlspecialchars($a['SubjectName']) ?></h6>
                              <small class="text-muted">
                                Grade <?= $a['GradeLevel'] ?> • <?= htmlspecialchars($a['SectionName']) ?>
                              </small>
                            </div>
                            <span class="badge bg-primary"><?= $a['StudentCount'] ?> students</span>
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
                              href="spread_sheet.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>"
                              class="btn btn-success btn-sm"
                            >
                              <i class="bi bi-table me-1"></i> Spreadsheet
                            </a>
                            <a
                              href="grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>"
                              class="btn btn-primary btn-sm"
                            >
                              <i class="bi bi-upload me-1"></i> Upload
                            </a>
                            <a
                              href="view_grades.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>"
                              class="btn btn-info btn-sm"
                            >
                              <i class="bi bi-eye me-1"></i> View
                            </a>
                            <a 
                              href="values.php?subject_id=<?= $a['SubjectID'] ?>&section_id=<?= $a['SectionID'] ?>&school_year=<?= urlencode($selectedSchoolYear) ?>" 
                              class="btn btn-secondary btn-sm"
                            >
                              <i class="bi bi-clipboard-data me-1"></i> Values
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>