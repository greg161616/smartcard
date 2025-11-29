<?php
// Start session and include config at the top
session_start();
include __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Manila');
// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher_id from session or database
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

// Handle AJAX request for student details
if (isset($_GET['action']) && $_GET['action'] == 'getStudentDetails' && isset($_GET['id'])) {
    $studentId = $_GET['id'];
    
    // Query to get student details based on your schema
    $sql = "
        SELECT 
            s.*, 
            u.Email,
            sec.GradeLevel,
            sec.SectionName,
            t.fName as TeacherFirstName,
            t.lName as TeacherLastName,
            t.mName as TeacherMiddleName
        FROM student s
        JOIN user u ON u.UserID = s.userID
        LEFT JOIN section_enrollment se ON se.StudentID = s.StudentID AND se.status = 'active'
        LEFT JOIN section sec ON sec.SectionID = se.SectionID
        LEFT JOIN teacher t ON t.TeacherID = sec.AdviserID
        WHERE s.StudentID = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        ?>
        <div class="row">
            <div class="col-md-6">
                <h5>Personal Information</h5>
                <p><strong>Name:</strong> <?= htmlspecialchars($student['FirstName'] . ' ' . $student['Middlename'] . ' ' . $student['LastName']) ?></p>
                <p><strong>LRN:</strong> <?= htmlspecialchars($student['LRN']) ?></p>
                <p><strong>Sex:</strong> <?= htmlspecialchars($student['Sex']) ?></p>
                <p><strong>Birthdate:</strong> <?= htmlspecialchars($student['Birthdate']) ?></p>
                <p><strong>Civil Status:</strong> <?= htmlspecialchars($student['CivilStatus']) ?></p>
                <p><strong>Religion:</strong> <?= htmlspecialchars($student['Religion']) ?></p>
                <p><strong>Barangay:</strong> <?= htmlspecialchars($student['Barangay'] ?? '') ?></p>
            </div>
            <div class="col-md-6">
                <h5>Contact Information</h5>
                <p><strong>Email:</strong> <?= htmlspecialchars($student['Email']) ?></p>
                <p><strong>Contact Number:</strong> <?= htmlspecialchars($student['contactNumber']) ?></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($student['Address']) ?></p>
                <p><strong>Parent's Name:</strong> <?= htmlspecialchars($student['parentname']) ?></p>
                <p><strong>Parent's Contact:</strong> <?= htmlspecialchars($student['ParentsContact']) ?></p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h5>Academic Information</h5>
                <p><strong>Grade Level:</strong> <?= htmlspecialchars($student['GradeLevel'] ?? '') ?></p>
                <p><strong>Section:</strong> <?= htmlspecialchars($student['SectionName'] ?? '') ?></p>
                <p><strong>Adviser:</strong> <?= htmlspecialchars(($student['TeacherFirstName'] ?? '') . ' ' . ($student['TeacherMiddleName'] ?? '') . ' ' . ($student['TeacherLastName'] ?? '')) ?></p>
            </div>
        </div>
        <?php
    } else {
        echo "<p>Student not found.</p>";
    }
    exit();
}

// Get teacher's basic info
$teacher_sql = "SELECT fName, lName, mName FROM teacher WHERE TeacherID = ?";
$teacher_stmt = $conn->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $teacherId);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_name = trim($teacher['fName'] . ' ' . ($teacher['mName'] ? $teacher['mName'] . ' ' : '') . $teacher['lName']);
$teacher_stmt->close();

// Get teacher's assigned sections for the current school year
$sectionSql = "
    SELECT DISTINCT 
        sec.SectionID, 
        CONCAT(sec.GradeLevel, ' - ', sec.SectionName) as SectionDisplay,
        sec.GradeLevel,
        sec.SectionName
    FROM assigned_subject a
    INNER JOIN section sec ON a.section_id = sec.SectionID
    WHERE a.teacher_id = ?
    ORDER BY sec.GradeLevel, sec.SectionName
";

$sectionStmt = $conn->prepare($sectionSql);
$sectionStmt->bind_param("i", $teacherId);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();

$sections = [];
if ($sectionResult && $sectionResult->num_rows > 0) {
    while ($section = $sectionResult->fetch_assoc()) {
        $sections[] = $section;
    }
}
$sectionStmt->close();

// Fetch all school years from school_year table for dropdown
$schoolYearSql = "SELECT school_year FROM school_year ORDER BY school_year DESC";
$schoolYearResult = $conn->query($schoolYearSql);

$schoolYears = [];
if ($schoolYearResult && $schoolYearResult->num_rows > 0) {
  while ($year = $schoolYearResult->fetch_assoc()) {
    $schoolYears[] = $year['school_year'];
  }
}

// If no school years found, use current academic year as default
if (empty($schoolYears)) {
  $currentYear = date('Y');
  $nextYear = $currentYear + 1;
  $defaultSchoolYear = $currentYear . '-' . $nextYear;
  $schoolYears[] = $defaultSchoolYear;
}

// Get selected filters from form submission or set defaults
$selectedSection = isset($_POST['section_filter']) ? $_POST['section_filter'] : (isset($sections[0]['SectionID']) ? $sections[0]['SectionID'] : '');
$selectedSchoolYear = isset($_POST['school_year_filter']) ? $_POST['school_year_filter'] : (isset($schoolYears[0]) ? $schoolYears[0] : '');

// Build the student query with section and school year filters using assigned_subject
$studentSql = "
    SELECT DISTINCT
        s.StudentID,
        s.LRN,
        CONCAT(s.LastName, ', ', s.FirstName, ' ', COALESCE(s.Middlename, '')) AS FullName,
        s.Sex,
        u.Email,
        sec.GradeLevel,
        sec.SectionName,
        se.SchoolYear
    FROM student AS s
    JOIN user AS u ON u.UserID = s.userID
    JOIN section_enrollment AS se ON se.StudentID = s.StudentID AND se.status = 'active'
    JOIN section AS sec ON sec.SectionID = se.SectionID
    WHERE sec.SectionID IN (
        SELECT DISTINCT a.section_id 
        FROM assigned_subject a 
        WHERE a.teacher_id = ?
    )
    AND se.SchoolYear = ?
";

// Add specific section filter if selected
if (!empty($selectedSection)) {
    $studentSql .= " AND sec.SectionID = ?";
}

$studentSql .= " ORDER BY s.Sex DESC, s.LastName ASC, s.FirstName ASC";

// Prepare and execute the student query
$stmt = $conn->prepare($studentSql);
if (!empty($selectedSection)) {
    $stmt->bind_param("isi", $teacherId, $selectedSchoolYear, $selectedSection);
} else {
    $stmt->bind_param("is", $teacherId, $selectedSchoolYear);
}
$stmt->execute();
$res = $stmt->get_result();

// Separate students by gender
$maleStudents = [];
$femaleStudents = [];

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        if ($row['Sex'] == 'Male') {
            $maleStudents[] = $row;
        } else {
            $femaleStudents[] = $row;
        }
    }
}

// Debug: Check what sections and students we're getting
error_log("Teacher ID: " . $teacherId);
error_log("Sections found: " . count($sections));
error_log("School Years found: " . count($schoolYears));
error_log("Selected School Year: " . $selectedSchoolYear);
error_log("Male students: " . count($maleStudents));
error_log("Female students: " . count($femaleStudents));

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student List</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .required:after {
      content: " *";
      color: red;
    }
    .clickable-row {
      cursor: pointer;
    }
    .clickable-row:hover {
      background-color: #f5f5f5;
    }
    .action-cell {
      cursor: default;
    }
    .modal-body .row {
      margin-bottom: 15px;
    }
    .modal-body h5 {
      border-bottom: 1px solid #dee2e6;
      padding-bottom: 8px;
      margin-bottom: 15px;
      color: #0d6efd;
    }
    .filter-container {
      background-color: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    .gender-section {
      margin-bottom: 10px;
    }
    .gender-header {
      background-color: #d9e9f5ff;
      padding: 10px;
      border-radius: 10px;
      margin: 15px;
    }
    .table th {
      white-space: nowrap;
    }
    .empty-table-message {
      padding: 20px;
      text-align: center;
      color: #6c757d;
      font-style: italic;
    }
    .dashboard-header {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    .filter-row {
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navs/teacherNav.php'; ?>

  <div class="">
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                    
                    <p class="lead mb-0">Student List - <?php echo date('F j, Y'); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white rounded-pill px-3 py-2 d-inline-block">
                        <small class="text-muted">School Year: <?php echo $selectedSchoolYear; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Class and School Year Selection Filter -->
    <div class="filter-container">
      <form method="POST" id="filterForm">
        <div class="row filter-row">
          <div class="col-md-6">
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
          <div class="col-md-6">
            <label for="school_year_filter" class="form-label">Filter by School Year:</label>
            <select class="form-select" id="school_year_filter" name="school_year_filter">
              <?php foreach ($schoolYears as $year): ?>
                <option value="<?= $year ?>" <?= ($selectedSchoolYear == $year) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($year) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <button type="button" class="btn btn-secondary" onclick="resetFilters()">Reset Filters</button>
          </div>
        </div>
      </form>
    </div>

    <?php if (empty($sections)): ?>
      <div class="alert alert-warning">
        <h4>No Classes Assigned</h4>
        <p class="mb-0">You are not currently assigned as an adviser or teacher for any classes.</p>
      </div>
    <?php else: ?>

      <!-- Display current filter info -->
      <div class="alert alert-info">
        <strong>Current Filter:</strong> 
        School Year: <span class="badge bg-primary"><?= htmlspecialchars($selectedSchoolYear) ?></span>
        <?php if (!empty($selectedSection)): ?>
          | Class: <span class="badge bg-secondary">
            <?php 
              $selectedSectionName = '';
              foreach ($sections as $section) {
                if ($section['SectionID'] == $selectedSection) {
                  $selectedSectionName = $section['SectionDisplay'];
                  break;
                }
              }
              echo htmlspecialchars($selectedSectionName);
            ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Male Students Table -->
      <div class="gender-section">
        <div class="gender-header">
          <h3 class="mb-0 ms-3">Males(<?= count($maleStudents) ?>)</h3>
        </div>
        <?php if (!empty($maleStudents)): ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
              <thead>
                <tr>
                  <th>LRN</th>
                  <th>Full Name</th>
                  <th>Sex</th>
                  <th>Email</th>
                  <th>Grade Level</th>
                  <th>Section</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($maleStudents as $row): ?>
                  <tr class="clickable-row" data-id="<?= $row['StudentID'] ?>">
                    <td><?= htmlspecialchars($row['LRN']) ?></td>
                    <td><?= htmlspecialchars($row['FullName']) ?></td>
                    <td><?= htmlspecialchars($row['Sex']) ?></td>
                    <td><?= htmlspecialchars($row['Email']) ?></td>
                    <td><?= htmlspecialchars($row['GradeLevel']) ?></td>
                    <td><?= htmlspecialchars($row['SectionName']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-table-message">
            <p>No male students found for the selected filters.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Female Students Table -->
      <div class="gender-section">
        <div class="gender-header">
          <h3 class="mb-0 ms-3">Females(<?= count($femaleStudents) ?>)</h3>
        </div>
        <?php if (!empty($femaleStudents)): ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
              <thead>
                <tr>
                  <th>LRN</th>
                  <th>Full Name</th>
                  <th>Sex</th>
                  <th>Email</th>
                  <th>Grade Level</th>
                  <th>Section</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($femaleStudents as $row): ?>
                  <tr class="clickable-row" data-id="<?= $row['StudentID'] ?>">
                    <td><?= htmlspecialchars($row['LRN']) ?></td>
                    <td><?= htmlspecialchars($row['FullName']) ?></td>
                    <td><?= htmlspecialchars($row['Sex']) ?></td>
                    <td><?= htmlspecialchars($row['Email']) ?></td>
                    <td><?= htmlspecialchars($row['GradeLevel']) ?></td>
                    <td><?= htmlspecialchars($row['SectionName']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-table-message">
            <p>No female students found for the selected filters.</p>
          </div>
        <?php endif; ?>
      </div>

    <?php endif; ?>
  </div>

  <!-- View Student Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewModalLabel">Student Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="studentDetails">
          <!-- Details will be loaded via AJAX -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function() {
      // Function to load student details
      function viewStudent(studentId) {
        // Show loading indicator
        $('#studentDetails').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        // Fetch student details
        $.ajax({
          url: '<?php echo $_SERVER['PHP_SELF']; ?>',
          type: 'GET',
          data: { 
            action: 'getStudentDetails',
            id: studentId
          },
          success: function(response) {
            $('#studentDetails').html(response);
            $('#viewModal').modal('show');
          },
          error: function() {
            $('#studentDetails').html('<div class="alert alert-danger">Error loading student details.</div>');
            $('#viewModal').modal('show');
          }
        });
      }

      // View Student Details when clicking row
      $('.clickable-row').click(function(e) {
        // Don't trigger if clicking on buttons or inside action cell
        if ($(e.target).is('button') || $(e.target).closest('td.action-cell').length) {
          return;
        }
        var studentId = $(this).data('id');
        viewStudent(studentId);
      });
    });

    function resetFilters() {
      document.getElementById('section_filter').value = '';
      document.getElementById('school_year_filter').value = '<?= $schoolYears[0] ?>';
      document.getElementById('filterForm').submit();
    }
  </script>
</body>
</html>