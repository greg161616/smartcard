<?php
// admin/studentlist.php
session_start();
include __DIR__ . '/../config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Get filtered students based on criteria
    if ($action == 'getFilteredStudents') {
        $schoolYear = isset($_GET['schoolYear']) ? $_GET['schoolYear'] : '';
        $gradeLevel = isset($_GET['gradeLevel']) ? $_GET['gradeLevel'] : '';
        $sectionId = isset($_GET['sectionId']) ? $_GET['sectionId'] : '';
        
        $sql = "
            SELECT
              s.StudentID,
              s.LRN,
              CONCAT_WS(' ',s.LastName,',', s.FirstName, s.MiddleName ) AS FullName,
              u.Email,
              sec.GradeLevel,
              sec.SectionName,
              se.SchoolYear
            FROM student AS s
            JOIN `user` AS u ON u.UserID = s.userID
            JOIN section_enrollment AS se ON se.StudentID = s.StudentID AND se.status = 'active'
            JOIN section AS sec ON sec.SectionID = se.SectionID
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($schoolYear)) {
            $sql .= " AND se.SchoolYear = ?";
            $params[] = $schoolYear;
            $types .= 's';
        }
        
        if (!empty($gradeLevel)) {
            $sql .= " AND sec.GradeLevel = ?";
            $params[] = $gradeLevel;
            $types .= 's';
        }
        
        if (!empty($sectionId)) {
            $sql .= " AND sec.SectionID = ?";
            $params[] = $sectionId;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY s.LastName, s.FirstName";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($students);
        exit;
    }
    
    // Get sections by grade level
    if ($action == 'getSectionsByGrade') {
        $gradeLevel = isset($_GET['gradeLevel']) ? $_GET['gradeLevel'] : '';
        
        $sql = "SELECT SectionID, SectionName FROM section WHERE GradeLevel = ? ORDER BY SectionName";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $gradeLevel);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($sections);
        exit;
    }
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
  <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">
  <style>
    .required:after {
      content: " *";
      color: red;
    }
    .dataTables_wrapper .dataTables_length, 
    .dataTables_wrapper .dataTables_filter, 
    .dataTables_wrapper .dataTables_info, 
    .dataTables_wrapper .dataTables_paginate {
      margin: 10px 0;
    }
    #studentTable tbody tr {
      cursor: pointer;
    }
    #studentTable tbody tr:hover {
      background-color: #f5f5f5;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navs/headNav.php'; ?>

  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Student List</h2>
      <div>
        <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
          Add Students
        </button>
        <button class="btn btn-light border-dark btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#importModal">
          Import
        </button>
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

    <!-- Filter Card -->
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Filter Students</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-2">
            <label for="schoolYearFilter" class="form-label">School Year</label>
            <select class="form-select" id="schoolYearFilter">
              <option value="">All</option>
              <?php
              $years_query = $conn->query("SELECT DISTINCT school_year FROM school_year ORDER BY school_year DESC");
              while ($year = $years_query->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($year['school_year']) ?>">
                  <?= htmlspecialchars($year['school_year']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label for="gradeLevelFilter" class="form-label">Grade Level</label>
            <select class="form-select" id="gradeLevelFilter">
              <option value="">All</option>
              <?php
              $grade_query = $conn->query("SELECT DISTINCT GradeLevel FROM section ORDER BY CAST(GradeLevel AS UNSIGNED), GradeLevel");
              while ($grade = $grade_query->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($grade['GradeLevel']) ?>">
                  <?= htmlspecialchars($grade['GradeLevel']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label for="sectionFilter" class="form-label">Section</label>
            <select class="form-select" id="sectionFilter">
              <option value="">All</option>
              <?php
              $section_query = $conn->query("SELECT SectionID, SectionName FROM section ORDER BY SectionName");
              while ($section = $section_query->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($section['SectionID']) ?>">
                  <?= htmlspecialchars($section['SectionName']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <button type="button" id="resetFilter" class="btn btn-sm btn-outline-secondary ">Reset Filters</button>
          </div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table id="studentTable" class="table table-bordered table-hover align-middle">
        <thead class="table-primary">
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
          $sql = "
            SELECT
              s.StudentID,
              s.LRN,
              CONCAT_WS(' ',s.LastName,',', s.FirstName, s.MiddleName ) AS FullName,
              u.Email,
              sec.GradeLevel,
              sec.SectionName
            FROM student AS s
            JOIN `user` AS u ON u.UserID = s.userID
            JOIN section_enrollment AS se ON se.StudentID = s.StudentID AND se.status = 'active'
            JOIN section AS sec ON sec.SectionID = se.SectionID
            ORDER BY s.LastName, s.FirstName
          ";
          $res = $conn->query($sql);
          if ($res && $res->num_rows):
            while ($row = $res->fetch_assoc()):
          ?>
            <tr>
              <td><?= htmlspecialchars($row['LRN']) ?></td>
              <td><?= htmlspecialchars($row['FullName']) ?></td>
              <td><?= htmlspecialchars($row['Email']) ?></td>
              <td><?= htmlspecialchars($row['GradeLevel']) ?></td>
              <td><?= htmlspecialchars($row['SectionName']) ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary view-btn" data-id="<?= $row['StudentID'] ?>">View</button>
                <button class="btn btn-sm btn-outline-warning edit-btn" data-id="<?= $row['StudentID'] ?>">Edit</button>
              </td>
            </tr>
          <?php
            endwhile;
          else:
          ?>
            <tr>
              <td colspan="7" class="text-center">No students found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" action="importStudents.php" method="post" enctype="multipart/form-data" id="importForm">
      <div class="modal-header">
        <h5 class="modal-title" id="importModalLabel">Import Students</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="students_file" class="form-label">Choose CSV / XLS / XLSX file</label>
          <input class="form-control" type="file" id="students_file" name="students_file" accept=".csv, .xls, .xlsx" required>
        </div>
        <div class="border p-3 mb-3 rounded">
          <small>File should contain these columns: <strong>LRN, FirstName, MiddleName, LastName, Sex, Email, SectionName, GradeLevel</strong></small>
          <br>
          <small class="text-muted">Note: All columns are required except MiddleName</small>
        </div>
                <!-- Template Download Section -->
        <div class="template-download rounded p-3 mb-3 text-center">
          <h6 class="mb-2">Download Template File</h6>
          <a href="templates/student_records.xlsx" class="btn btn-outline-primary btn-sm" download>
            <i class="fas fa-download me-1"></i> Download Student Template (XLSX)
          </a>
          <div class="mt-2">
            <small class="text-muted">File includes sample data and proper column structure</small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="import_file" class="btn btn-primary">Import</button>
      </div>
    </form>
  </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">  
        <h5 class="modal-title" id="loadingModalLabel">Importing Students</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3" id="loadingMessage">Processing your file. This may take several minutes...</p>
        <div id="timeoutWarning" class="alert alert-warning mt-3 d-none">
          <strong>Note:</strong> This is taking longer than expected. Please do not close the browser.
        </div>
      </div>
    </div>
  </div>
</div>

  <!-- Add Student Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form class="modal-content" action="addStudent.php" method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="addModalLabel">Add Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h5>Basic Information</h5>
              <div class="mb-3">
                <label class="form-label required">LRN</label>
                <input type="text" name="LRN" class="form-control" required>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label required">First Name</label>
                  <input type="text" name="FirstName" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Middle Name</label>
                  <input type="text" name="MiddleName" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label required">Last Name</label>
                  <input type="text" name="LastName" class="form-control" required>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Sex</label>
                  <select name="Sex" class="form-select">
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Birthdate</label>
                  <input type="date" name="Birthdate" class="form-control">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label required">Email</label>
                <input type="email" name="Email" class="form-control" required>
              </div>
            </div>

            <div class="col-md-6">
              <h5>Contact Information</h5>
              <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="Address" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" name="ContactNumber" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Parent's Name</label>
                <input type="text" name="ParentName" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Parent's Contact</label>
                <input type="text" name="ParentsContact" class="form-control">
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Civil Status</label>
                  <select name="CivilStatus" class="form-select">
                    <option value="">Select</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Separated">Separated</option>
                    <option value="Widowed">Widowed</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Religion</label>
                  <input type="text" name="Religion" class="form-control">
                </div>
              </div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-12">
              <h5>Enrollment Information</h5>
              <div class="mb-3">
                <label class="form-label required">Section</label>
                <select name="SectionID" class="form-select" required>
                  <option value="">— Select Section —</option>
                  <?php
                  $secRes = $conn->query("
                    SELECT SectionID, CONCAT(GradeLevel,' - ',SectionName) AS secname
                    FROM section
                    ORDER BY GradeLevel, SectionName
                  ");
                  while ($sec = $secRes->fetch_assoc()):
                  ?>
                    <option value="<?= $sec['SectionID'] ?>">
                      <?= htmlspecialchars($sec['secname']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_student" class="btn btn-success">Add Student</button>
        </div>
      </form>
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

  <!-- Edit Student Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="editStudentForm">
          <!-- Edit form will be loaded via AJAX -->
        </div>
      </div>
    </div>
  </div>

  <!-- Load jQuery first, then DataTables, then Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
<script>
$(document).ready(function() {
  // Initialize DataTable with export options
  var table = $('#studentTable').DataTable();
  
  // View Student Details
  $('#studentTable').on('click', '.view-btn', function(e) {
    e.stopPropagation();
    var studentId = $(this).data('id');
    $.ajax({
      url: 'studentdetails.php',
      type: 'GET',
      data: { id: studentId },
      success: function(response) {
        $('#studentDetails').html(response);
        // Use Bootstrap 5 native API to show modal
        var viewEl = document.getElementById('viewModal');
        if (viewEl) {
          var viewModal = new bootstrap.Modal(viewEl);
          viewModal.show();
        }
      },
      error: function() {
        $('#studentDetails').html('<div class="alert alert-danger">Error loading student details.</div>');
        var viewEl = document.getElementById('viewModal');
        if (viewEl) {
          var viewModal = new bootstrap.Modal(viewEl);
          viewModal.show();
        }
      }
    });
  });

  // Edit Student
  $('#studentTable').on('click', '.edit-btn', function(e) {
    e.stopPropagation();
    var studentId = $(this).data('id');
    $.ajax({
      url: 'editform.php',
      type: 'GET',
      data: { id: studentId },
      success: function(response) {
        $('#editStudentForm').html(response);
        // Use Bootstrap 5 native API to show modal
        var editEl = document.getElementById('editModal');
        if (editEl) {
          var editModal = new bootstrap.Modal(editEl);
          editModal.show();
        }
      },
      error: function() {
        $('#editStudentForm').html('<div class="alert alert-danger">Error loading edit form.</div>');
        var editEl = document.getElementById('editModal');
        if (editEl) {
          var editModal = new bootstrap.Modal(editEl);
          editModal.show();
        }
      }
    });
  });

  // Import form handling with loading modal
  let importTimeout;
  let progressInterval;
  let timeElapsed = 0;
  
  // Function to show loading modal
  function showLoadingModal() {
    $('#loadingModal').modal('show');
    timeElapsed = 0;
    
    // Show timeout warning after 5 minutes
    importTimeout = setTimeout(function() {
      $('#timeoutWarning').removeClass('d-none');
    }, 300000);
  }
  
  // Function to hide loading modal
  function hideLoadingModal() {
    clearTimeout(importTimeout);
    clearInterval(progressInterval);
    $('#loadingModal').modal('hide');
    $('#timeoutWarning').addClass('d-none');
  }
  
  // Handle import form submission
  $('#importForm').on('submit', function(e) {
    e.preventDefault();
    
    // Validate file first
    const fileInput = $('#students_file')[0];
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
      $('#importModal .modal-body').prepend(
        '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
        'Please select a file to import.' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        '</div>'
      );
      return false;
    }
    
    // Show loading modal
    showLoadingModal();
    
    // Submit form via AJAX to handle progress
    const formData = new FormData(this);
    formData.append('import_file', 'true'); // Add the import_file parameter
    
    $.ajax({
      url: $(this).attr('action'),
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      xhr: function() {
        const xhr = new window.XMLHttpRequest();
        // Upload progress
        xhr.upload.addEventListener('progress', function(evt) {
          if (evt.lengthComputable) {
            const percentComplete = evt.loaded / evt.total * 100;
            $('#importProgressBar').css('width', percentComplete + '%')
                                  .attr('aria-valuenow', percentComplete);
            $('#progressText').text(Math.round(percentComplete) + '% complete');
          }
        }, false);
        return xhr;
      },
      success: function(response) {
        hideLoadingModal();
        try {
          const result = JSON.parse(response);
          if (result.success) {
            // Show success message and reload
            $('#importModal').modal('hide');
            $('#importForm')[0].reset();
            
            // Create a temporary success message
            $('body').append(
              '<div class="alert alert-success alert-dismissible position-fixed top-0 end-0 m-3" style="z-index: 9999;">' +
              '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
              result.message +
              '</div>'
            );
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
              $('.alert-success').alert('close');
            }, 5000);
            
            // Reload the page after a short delay
            setTimeout(function() {
              window.location.reload();
            }, 1000);
          } else {
            // Show error in the import modal
            $('#importModal .modal-body').prepend(
              '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
              '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
              result.message +
              '</div>'
            );
            $('#importModal').modal('show');
          }
        } catch (e) {
          // Show parsing error
          $('#importModal .modal-body').prepend(
            '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            'Unexpected response from server. Please try again.' +
            '</div>'
          );
          $('#importModal').modal('show');
        }
      },
      error: function(xhr, status, error) {
        hideLoadingModal();
        
        // Show error in the import modal
        $('#importModal .modal-body').prepend(
          '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
          '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
          'Import failed: ' + (error || 'Unknown error') +
          '</div>'
        );
        $('#importModal').modal('show');
        
        // Log the error for debugging
        console.error('AJAX Error:', status, error);
        console.error('Response:', xhr.responseText);
      }
    });
    
    return false;
  });
  
  // Clear any existing alerts when the import modal is shown
  $('#importModal').on('show.bs.modal', function() {
    $(this).find('.alert').remove();
  });
  
  // Also handle the case where user might close the modal manually
  $('#loadingModal').on('hidden.bs.modal', function() {
    clearTimeout(importTimeout);
    clearInterval(progressInterval);
  });

  // Filter functionality
  var table = $('#studentTable').DataTable();
  
  // Handle grade level change to populate sections
  $('#gradeLevelFilter').on('change', function() {
    var gradeLevel = $(this).val();
    var sectionSelect = $('#sectionFilter');
    
    if (gradeLevel) {
      $.ajax({
        url: '<?php echo $_SERVER['PHP_SELF']; ?>',
        type: 'GET',
        data: {
          action: 'getSectionsByGrade',
          gradeLevel: gradeLevel
        },
        dataType: 'json',
        success: function(sections) {
          sectionSelect.html('<option value="">All Sections</option>');
          sections.forEach(function(section) {
            sectionSelect.append(
              $('<option></option>')
                .val(section.SectionID)
                .text(section.SectionName)
            );
          });
        }
      });
    } else {
      // Reset sections dropdown
      sectionSelect.html('<option value="">All Sections</option>');
      $.ajax({
        url: '<?php echo $_SERVER['PHP_SELF']; ?>',
        type: 'GET',
        data: { action: 'getSectionsByGrade', gradeLevel: '' },
        dataType: 'json',
        success: function(sections) {
          sectionSelect.html('<option value="">All Sections</option>');
          sections.forEach(function(section) {
            sectionSelect.append(
              $('<option></option>')
                .val(section.SectionID)
                .text(section.SectionName)
            );
          });
        }
      });
    }
  });
  
  // Apply filters when any filter changes
  function applyFilters() {
    var schoolYear = $('#schoolYearFilter').val();
    var gradeLevel = $('#gradeLevelFilter').val();
    var sectionId = $('#sectionFilter').val();
    
    $.ajax({
      url: '<?php echo $_SERVER['PHP_SELF']; ?>',
      type: 'GET',
      data: {
        action: 'getFilteredStudents',
        schoolYear: schoolYear,
        gradeLevel: gradeLevel,
        sectionId: sectionId
      },
      dataType: 'json',
      success: function(students) {
        // Clear the table
        table.clear().draw();
        
        // Add filtered data
        if (students.length > 0) {
          students.forEach(function(student) {
            table.row.add([
              htmlEscape(student.LRN),
              htmlEscape(student.FullName),
              htmlEscape(student.Email),
              htmlEscape(student.GradeLevel),
              htmlEscape(student.SectionName),
              '<button class="btn btn-sm btn-outline-primary view-btn" data-id="' + student.StudentID + '">View</button> ' +
              '<button class="btn btn-sm btn-outline-warning edit-btn" data-id="' + student.StudentID + '">Edit</button>'
            ]);
          });
        } else {
          table.row.add([
            'No students found.',
            '',
            '',
            '',
            '',
            ''
          ]);
        }
        
        table.draw();
      },
      error: function() {
        console.error('Error loading filtered students');
      }
    });
  }
  
  // Helper function to escape HTML
  function htmlEscape(text) {
    return $('<div>').text(text).html();
  }
  
  // Listen to filter changes
  $('#schoolYearFilter, #gradeLevelFilter, #sectionFilter').on('change', function() {
    applyFilters();
  });
  
  // Reset filters
  $('#resetFilter').on('click', function() {
    $('#schoolYearFilter').val('');
    $('#gradeLevelFilter').val('');
    $('#sectionFilter').html('<option value="">All Sections</option>');
    applyFilters();
  });

});
</script>
</body>
</html>