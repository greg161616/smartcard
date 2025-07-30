<?php
// admin/studentlist.php
session_start();
include __DIR__ . '/../config.php';
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
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navs/adminNav.php'; ?>

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

    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-primary">
          <tr>
            <th>Student ID</th>
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
              CONCAT_WS(' ', s.FirstName, s.MiddleName, s.LastName) AS FullName,
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
              <td><?= htmlspecialchars($row['StudentID']) ?></td>
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
      <form class="modal-content" action="importStudents.php" method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Import Students</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="students_file" class="form-label">Choose CSV / XLS / XLSX file</label>
            <input class="form-control" type="file" id="students_file" name="students_file" accept=".csv, .xls, .xlsx" required>
          </div>
          <div class="alert alert-info">
            <small>File should contain these columns: LRN, FirstName, MiddleName, LastName, Sex, Birthdate, Address, ContactNumber, ParentName, ParentsContact, CivilStatus, Religion, Barangay, Email, SectionID</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="import_file" class="btn btn-primary">Import</button>
        </div>
      </form>
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
              <div class="mb-3">
                <label class="form-label">Barangay</label>
                <input type="text" name="Barangay" class="form-control">
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

  <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function() {
      // View Student Details
      $('.view-btn').click(function() {
        var studentId = $(this).data('id');
        $.ajax({
          url: 'getStudentDetails.php',
          type: 'GET',
          data: { id: studentId },
          success: function(response) {
            $('#studentDetails').html(response);
            $('#viewModal').modal('show');
          }
        });
      });

      // Edit Student (you'll need to implement this)
      $('.edit-btn').click(function() {
        var studentId = $(this).data('id');
        // Implement edit functionality
        alert('Edit functionality for student ID: ' + studentId);
      });
    });
  </script>
</body>
</html>