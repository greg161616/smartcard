<?php
// teacherList.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher List</title>
  <link rel="icon" href="../img/logo.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>
  <div class="container mt-5">

    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Teacher List</h2>
      <div>
        <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
          Add Teacher
        </button>
        <button class="btn btn-light border-dark btn-sm me-2" data-bs-toggle="modal" data-bs-target="#importModal">
          Import
        </button>
      </div>
    </div>

    <table id="teacherTable" class="table table-bordered table-striped">
      <thead class="table-info">
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Subject</th>
          <th>Grade Level</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
          include '../config.php';
          $sql = "SELECT 
                    t.TeacherID,
                    t.fName, t.mName, t.lName, t.Birthdate, t.Address,
                    u.Email,
                    s.SubjectName, s.GradeLevel
                  FROM teacher t
                  JOIN user u ON t.UserID = u.UserID
                  LEFT JOIN subject s ON t.TeacherID = s.teacherID";
          $res = $conn->query($sql);
          $i = 1;
          if ($res->num_rows) {
            while ($r = $res->fetch_assoc()) {
              // Prepare display values
              $fullName   = htmlspecialchars($r['fName'].' '.$r['mName'].' '.$r['lName']);
              $email      = htmlspecialchars($r['Email']);
              $subject    = htmlspecialchars($r['SubjectName'] ?? 'N/A');
              $gradeLevel = htmlspecialchars($r['GradeLevel'] ?? 'N/A');
              $birthdate  = htmlspecialchars($r['Birthdate']);
              $address    = htmlspecialchars($r['Address']);
              echo "<tr>
                      <td>{$i}</td>
                      <td>{$fullName}</td>
                      <td>{$email}</td>
                      <td>{$subject}</td>
                      <td>{$gradeLevel}</td>
                      <td>
                        <button
                          class='btn btn-sm btn-primary viewBtn'
                          data-bs-toggle='modal'
                          data-bs-target='#viewModal'
                          data-first-name='".htmlspecialchars($r['fName'])."'
                          data-middle-name='".htmlspecialchars($r['mName'])."'
                          data-last-name='".htmlspecialchars($r['lName'])."'
                          data-email='{$email}'
                          data-subject='{$subject}'
                          data-grade-level='{$gradeLevel}'
                          data-birthdate='{$birthdate}'
                          data-address='{$address}'
                        >
                          View
                        </button>
                        <a href='deleteTeacher.php?id={$r['TeacherID']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>
                          Delete
                        </a>
                      </td>
                    </tr>";
              $i++;
            }
          } else {
            echo "<tr><td colspan='6' class='text-center'>No teachers found</td></tr>";
          }
        ?>
      </tbody>
    </table>
  </div>

  <!-- Import Modal -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="../admin/importTeachers.php" method="post" enctype="multipart/form-data">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="importModalLabel">Import Teachers</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="fileInput" class="form-label">Select File</label>
              <input
                type="file"
                name="teachers_file"
                id="fileInput"
                accept=".csv, .xls, .xlsx"
                class="form-control"
                required
              >
            </div>
            <small>Allowed formats: <code>CSV, XLS, XLSX</code> (columns: FirstName,MiddleName,LastName,Email)</small>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="import_file" class="btn btn-primary">Import</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Teacher Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="../admin/addTeacher.php" method="post">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="firstName" class="form-label">First Name</label>
              <input type="text" name="first_name" id="firstName" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="middleName" class="form-label">Middle Name</label>
              <input type="text" name="middle_name" id="middleName" class="form-control">
            </div>
            <div class="mb-3">
              <label for="lastName" class="form-label">Last Name</label>
              <input type="text" name="last_name" id="lastName" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="emailAddress" class="form-label">Email Address</label>
              <input type="email" name="email_address" id="emailAddress" class="form-control" required>
            </div>
            <small>Default password will be the teacher’s last name.</small>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add_teacher" class="btn btn-success">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- View Details Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewModalLabel">Teacher Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p><strong>First Name:</strong> <span id="detailFirstName"></span></p>
          <p><strong>Middle Name:</strong> <span id="detailMiddleName"></span></p>
          <p><strong>Last Name:</strong> <span id="detailLastName"></span></p>
          <p><strong>Email:</strong> <span id="detailEmail"></span></p>
          <p><strong>Subject:</strong> <span id="detailSubject"></span></p>
          <p><strong>Grade Level:</strong> <span id="detailGradeLevel"></span></p>
          <p><strong>Birthdate:</strong> <span id="detailBirthdate"></span></p>
          <p><strong>Address:</strong> <span id="detailAddress"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    $(document).ready(() => {
      $('#teacherTable').DataTable();

      // Populate and show Details modal
      document.querySelectorAll('.viewBtn').forEach(btn => {
        btn.addEventListener('click', function() {
          $('#detailFirstName').text(this.dataset.firstName);
          $('#detailMiddleName').text(this.dataset.middleName);
          $('#detailLastName').text(this.dataset.lastName);
          $('#detailEmail').text(this.dataset.email);
          $('#detailSubject').text(this.dataset.subject);
          $('#detailGradeLevel').text(this.dataset.gradeLevel);
          $('#detailBirthdate').text(this.dataset.birthdate);
          $('#detailAddress').text(this.dataset.address);
        });
      });
    });
  </script>
</body>
</html>
