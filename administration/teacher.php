<?php
// teacher.php
session_start();
include __DIR__ . '/../config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

require __DIR__ . '/../api/log_helper.php';

// Add the function for deleting a teacher
if (isset($_GET['delete_teacher_id'])) {
    $teacher_id = $_GET['delete_teacher_id'];

    // Get teacher details before deletion for logging
    $getDetails = $conn->prepare("SELECT t.TeacherID, t.fName, t.mName, t.lName, u.Email, u.UserID 
                                 FROM teacher t 
                                 JOIN user u ON t.UserID = u.UserID 
                                 WHERE t.TeacherID = ?");
    $getDetails->bind_param("i", $teacher_id);
    $getDetails->execute();
    $teacherInfo = $getDetails->get_result()->fetch_assoc();
    $getDetails->close();

    if (!$teacherInfo) {
        $_SESSION['error'] = 'Teacher not found.';
        log_system_action($conn, 'delete_teacher_failed', $_SESSION['user_id'] ?? null, [
            'reason' => 'Teacher not found',
            'teacherId' => $teacher_id
        ], 'error');
        header('Location: teacher');
        exit;
    }

    // Start transaction for deleting both teacher and user
    $conn->begin_transaction();
    try {
        // Delete from teacher table
        $sql = "DELETE FROM teacher WHERE TeacherID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->close();

        // Delete from user table
        $sqlUser = "DELETE FROM user WHERE UserID = ?";
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->bind_param("i", $teacherInfo['UserID']);
        $stmtUser->execute();
        $stmtUser->close();

        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = 'Teacher deleted successfully.';
        log_system_action($conn, 'delete_teacher_success', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'email' => $teacherInfo['Email'],
            'name' => $teacherInfo['fName'] . ' ' . $teacherInfo['lName']
        ], 'info');

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        $_SESSION['error'] = 'Failed to delete teacher.';
        log_system_action($conn, 'delete_teacher_failed', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'error' => $e->getMessage(),
            'email' => $teacherInfo['Email'] ?? null
        ], 'error');
    }
    
    header('Location: teacher');
    exit;
}
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
  <?php include '../navs/headNav.php'; ?>
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
          <th>Subject & Grade Level</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $sql = "SELECT 
                    t.TeacherID,
                    t.fName, t.mName, t.lName, t.Birthdate, t.Address, t.Contact,
                    u.Email,
                    GROUP_CONCAT(DISTINCT CONCAT(s.SubjectName, ' (Grade ', s.GradeLevel, ')') ORDER BY s.GradeLevel, s.SubjectName) AS SubjectGrades,
                    GROUP_CONCAT(DISTINCT s.SubjectName ORDER BY s.SubjectName) AS Subjects,
                    GROUP_CONCAT(DISTINCT s.GradeLevel ORDER BY s.GradeLevel) AS GradeLevels
                  FROM teacher t
                  JOIN user u ON t.UserID = u.UserID
                  LEFT JOIN subject s ON t.TeacherID = s.teacherID
                  GROUP BY t.TeacherID";
          $res = $conn->query($sql);
          $i = 1;
          if ($res->num_rows) {
            while ($r = $res->fetch_assoc()) {
              $fullName   = htmlspecialchars($r['fName'].' '.$r['mName'].' '.$r['lName']);
              $email      = htmlspecialchars($r['Email']);
              $subjectGrades = htmlspecialchars($r['SubjectGrades'] ?? 'No subjects assigned');
              $subjects    = htmlspecialchars($r['Subjects'] ?? 'N/A');
              $gradeLevels = htmlspecialchars($r['GradeLevels'] ?? 'N/A');
              $birthdate  = htmlspecialchars($r['Birthdate']);
              $address    = htmlspecialchars($r['Address']);
              echo "<tr>
                      <td>{$i}</td>
                      <td>{$fullName}</td>
                      <td>{$email}</td>
                      <td>{$subjectGrades}</td>
                      <td>
                        <button
                          class='btn btn-sm btn-primary viewBtn'
                          data-bs-toggle='modal'
                          data-bs-target='#viewModal'
                          data-first-name='".htmlspecialchars($r['fName'])."'
                          data-middle-name='".htmlspecialchars($r['mName'])."'
                          data-last-name='".htmlspecialchars($r['lName'])."'
                          data-email='{$email}'
                          data-subject-grades='{$subjectGrades}'
                          data-subjects='{$subjects}'
                          data-grade-levels='{$gradeLevels}'
                          data-birthdate='{$birthdate}'
                          data-address='{$address}'
                        >
                          View
                        </button>
                        <button type='button' class='btn btn-sm btn-danger' 
                          onclick='document.getElementById(\"deleteTeacherId\").value=\"{$r['TeacherID']}\";document.getElementById(\"deleteTeacherName\").textContent=\"{$fullName}\";'
                          data-bs-toggle='modal' 
                          data-bs-target='#deleteModal'>
                          Delete
                        </button>
                      </td>
                    </tr>";
              $i++;
            }
          } else {
            echo "<tr><td colspan='5' class='text-center'>No teachers found</td></tr>";
          }
        ?>
      </tbody>
    </table>
  </div>

  <!-- Import Modal -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="../head/importTeachers.php" method="post" enctype="multipart/form-data">
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
            <small>Allowed formats: <code>CSV, XLS, XLSX</code> (columns: FirstName, MiddleName, LastName, Email)</small>
          </div>
                          <!-- Template Download Section -->
        <div class="template-download rounded p-3 mb-3 text-center">
          <h6 class="mb-2">Download Template File</h6>
          <a href="templates/teacher_records.xlsx" class="btn btn-outline-primary btn-sm" download>
            <i class="fas fa-download me-1"></i> Download Teacher Template (XLSX)
          </a>
          <div class="mt-2">
            <small class="text-muted">File includes sample data and proper column structure</small>
          </div>
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
      <form action="../administration/addTeacher.php" method="post">
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
            <small>Default password will be the teacher's last name.</small>
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
          <p><strong>Subject & Grade Level:</strong> <span id="detailSubjectGrades"></span></p>
          <p><strong>Birthdate:</strong> <span id="detailBirthdate"></span></p>
          <p><strong>Address:</strong> <span id="detailAddress"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="teacher.php" method="GET">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteModalLabel">Delete Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete teacher: <strong><span id="deleteTeacherName"></span></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
            <input type="hidden" name="delete_teacher_id" id="deleteTeacherId">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <script>
    $(document).ready(() => {
      const table = $('#teacherTable').DataTable();

      // Populate and show Details modal
      document.querySelectorAll('.viewBtn').forEach(btn => {
        btn.addEventListener('click', function() {
          $('#detailFirstName').text(this.dataset.firstName);
          $('#detailMiddleName').text(this.dataset.middleName);
          $('#detailLastName').text(this.dataset.lastName);
          $('#detailEmail').text(this.dataset.email);
          $('#detailSubjectGrades').text(this.dataset.subjectGrades);
          $('#detailBirthdate').text(this.dataset.birthdate);
          $('#detailAddress').text(this.dataset.address);
        });
      });
    });
  </script>
</body>
</html>