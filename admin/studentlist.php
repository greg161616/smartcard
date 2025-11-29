<?php
// Start session and include config at the top
session_start();
include __DIR__ . '/../config.php';

// Get filter values from GET parameters
$gradeFilter = isset($_GET['grade']) ? $_GET['grade'] : '';
$sectionFilter = isset($_GET['section']) ? $_GET['section'] : '';
$schoolYearFilter = isset($_GET['school_year']) ? $_GET['school_year'] : '';

// Handle AJAX request for student details
if (isset($_GET['action']) && $_GET['action'] == 'getStudentDetails' && isset($_GET['id'])) {
    $studentId = $_GET['id'];
    
    // Query to get student details
    $sql = "
        SELECT 
            s.*, 
            u.Email,
            sec.GradeLevel,
            sec.SectionName,
            t.fName as TeacherFirstName,
            t.lName as TeacherLastName
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
            </div>
            <div class="col-md-6">
                <h5>Contact Information</h5>
                <p><strong>Email:</strong> <?= htmlspecialchars($student['Email']) ?></p>
                <p><strong>Contact Number:</strong> <?= htmlspecialchars($student['contactNumber']) ?></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($student['Address']) ?></p>
                <p><strong>Barangay:</strong> <?= htmlspecialchars($student['Barangay']) ?></p>
                <p><strong>Parent's Name:</strong> <?= htmlspecialchars($student['parentname']) ?></p>
                <p><strong>Parent's Contact:</strong> <?= htmlspecialchars($student['ParentsContact']) ?></p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h5>Academic Information</h5>
                <p><strong>Grade Level:</strong> <?= htmlspecialchars($student['GradeLevel']) ?></p>
                <p><strong>Section:</strong> <?= htmlspecialchars($student['SectionName']) ?></p>
                <p><strong>Adviser:</strong> <?= htmlspecialchars($student['TeacherFirstName'] . ' ' . $student['TeacherLastName']) ?></p>
            </div>
        </div>
        <?php
    } else {
        echo "<p>Student not found.</p>";
    }
    exit();
}

// Handle AJAX request for sections based on grade level
if (isset($_GET['action']) && $_GET['action'] == 'getSections' && isset($_GET['grade'])) {
    $grade = $_GET['grade'];
    $sections = [];
    
    if (!empty($grade)) {
        $sql = "SELECT DISTINCT SectionID, SectionName FROM section WHERE GradeLevel = ? ORDER BY SectionName";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $grade);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($sections);
    exit();
}
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
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
    .filter-card {
      border: 1px solid #dee2e6;
      border-radius: 0.375rem;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .filter-card .card-header {
      background-color: #f8f9fa;
      border-bottom: 1px solid #dee2e6;
    }
    .filter-form .form-group {
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navs/adminNav.php'; ?>

  <div class="container-fluid mt-5">
    <div class="row">
      <div class="col-12">
        <!-- Filter Card -->
        <div class="card filter-card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Filter Students</h5>
          </div>
          <div class="card-body">
            <form id="filterForm" method="GET" class="filter-form">
              <div class="row">
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="school_year" class="form-label">School Year</label>
                    <select class="form-select" id="school_year" name="school_year">
                      <option value="">All School Years</option>
                      <option value="2023-2024" <?= $schoolYearFilter == '2023-2024' ? 'selected' : '' ?>>2023-2024</option>
                      <option value="2024-2025" <?= $schoolYearFilter == '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
                      <option value="2025-2026" <?= $schoolYearFilter == '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="grade" class="form-label">Grade Level</label>
                    <select class="form-select" id="grade" name="grade">
                      <option value="">All Grade Levels</option>
                      <option value="7" <?= $gradeFilter == '7' ? 'selected' : '' ?>>Grade 7</option>
                      <option value="8" <?= $gradeFilter == '8' ? 'selected' : '' ?>>Grade 8</option>
                      <option value="9" <?= $gradeFilter == '9' ? 'selected' : '' ?>>Grade 9</option>
                      <option value="10" <?= $gradeFilter == '10' ? 'selected' : '' ?>>Grade 10</option>
                      <option value="11" <?= $gradeFilter == '11' ? 'selected' : '' ?>>Grade 11</option>
                      <option value="12" <?= $gradeFilter == '12' ? 'selected' : '' ?>>Grade 12</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="section" class="form-label">Section</label>
                    <select class="form-select" id="section" name="section" <?= empty($gradeFilter) ? 'disabled' : '' ?>>
                      <option value="">All Sections</option>
                      <?php
                      // Populate sections based on selected grade level
                      if (!empty($gradeFilter)) {
                          $sql = "SELECT DISTINCT SectionID, SectionName FROM section WHERE GradeLevel = ? ORDER BY SectionName";
                          $stmt = $conn->prepare($sql);
                          $stmt->bind_param("s", $gradeFilter);
                          $stmt->execute();
                          $result = $stmt->get_result();
                          
                          while ($row = $result->fetch_assoc()) {
                              $selected = ($sectionFilter == $row['SectionName']) ? 'selected' : '';
                              echo "<option value=\"" . htmlspecialchars($row['SectionName']) . "\" $selected>" . htmlspecialchars($row['SectionName']) . "</option>";
                          }
                      }
                      ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <div class="form-group w-100">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Student List Card -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Student List</h5>
            <a href="#" class="btn btn-success">Add Student</a>
          </div>
          <div class="card-body">
            <?php if (!empty($_SESSION['message'])): ?>
              <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
              <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
              <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="table-responsive">
              <table id="studentTable" class="table table-bordered table-hover align-middle">
                <thead class="table-info">
                  <tr>
                    <th>LRN</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Grade Level</th>
                    <th>Section</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Build the query with filters
                  $sql = "
                    SELECT
                      s.StudentID,
                      s.LRN,
                      CONCAT_WS(' ', s.FirstName, s.MiddleName, s.LastName) AS FullName,
                      u.Email,
                      sec.GradeLevel,
                      sec.SectionName
                    FROM student AS s
                    JOIN `user` AS u ON u.UserID = s.userID
                    JOIN section_enrollment AS se ON se.StudentID = s.StudentID AND se.status = 'active'
                    JOIN section AS sec ON sec.SectionID = se.SectionID
                  ";
                  
                  $whereConditions = [];
                  $params = [];
                  $types = "";
                  
                  // Add grade level filter if selected
                  if (!empty($gradeFilter)) {
                    $whereConditions[] = "sec.GradeLevel = ?";
                    $params[] = $gradeFilter;
                    $types .= "s";
                  }
                  
                  // Add section filter if selected
                  if (!empty($sectionFilter)) {
                    $whereConditions[] = "sec.SectionName = ?";
                    $params[] = $sectionFilter;
                    $types .= "s";
                  }
                  
                  // Add WHERE clause if there are conditions
                  if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(" AND ", $whereConditions);
                  }
                  
                  $sql .= " ORDER BY s.LastName, s.FirstName";
                  
                  // Prepare and execute the query
                  $stmt = $conn->prepare($sql);
                  
                  if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                  }
                  
                  $stmt->execute();
                  $res = $stmt->get_result();
                  
                  if ($res && $res->num_rows):
                    while ($row = $res->fetch_assoc()):
                  ?>
                    <tr class="clickable-row" data-id="<?= $row['StudentID'] ?>">
                      <td><?= htmlspecialchars($row['LRN']) ?></td>
                      <td><?= htmlspecialchars($row['FullName']) ?></td>
                      <td><?= htmlspecialchars($row['Email']) ?></td>
                      <td><?= htmlspecialchars($row['GradeLevel']) ?></td>
                      <td><?= htmlspecialchars($row['SectionName']) ?></td>
                      <td class="action-cell">
                        <button class="btn btn-sm btn-outline-primary view-student" data-id="<?= $row['StudentID'] ?>">View</button>
                        <button class="btn btn-sm btn-outline-secondary">Edit</button>
                      </td>
                    </tr>
                  <?php
                    endwhile;
                  else:
                  ?>
                    <tr>
                      <td colspan="6" class="text-center">No students found.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
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
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    $(document).ready(function() {
      // Initialize DataTable
      $('#studentTable').DataTable({
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
      });
      
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
      
      // View Student Details when clicking view button
      $('.view-student').click(function(e) {
        e.stopPropagation();
        var studentId = $(this).data('id');
        viewStudent(studentId);
      });

      // Dynamic section dropdown based on grade level selection
      $('#grade').change(function() {
        var gradeLevel = $(this).val();
        var sectionDropdown = $('#section');
        
        if (gradeLevel) {
          // Enable section dropdown
          sectionDropdown.prop('disabled', false);
          
          // Clear current options
          sectionDropdown.html('<option value="">All Sections</option>');
          
          // Fetch sections for selected grade level
          $.ajax({
            url: '<?php echo $_SERVER['PHP_SELF']; ?>',
            type: 'GET',
            data: { 
              action: 'getSections',
              grade: gradeLevel
            },
            success: function(response) {
              // Add new options
              response.forEach(function(section) {
                sectionDropdown.append(
                  $('<option></option>').val(section.SectionName).text(section.SectionName)
                );
              });
            },
            error: function() {
              console.error('Error loading sections');
            }
          });
        } else {
          // Disable section dropdown if no grade level selected
          sectionDropdown.prop('disabled', true);
          sectionDropdown.html('<option value="">All Sections</option>');
        }
      });
    });
  </script>
</body>
</html>