<?php
// teacher.php
session_start();
include __DIR__ . '/../config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

require __DIR__ . '/../api/log_helper.php';

// Handle status updates
if (isset($_GET['deactivate_teacher_id'])) {
    $teacher_id = $_GET['deactivate_teacher_id'];

    // Get teacher details before deactivation for logging
    $getDetails = $conn->prepare("SELECT t.TeacherID, t.fName, t.mName, t.lName, u.Email 
                                 FROM teacher t 
                                 JOIN user u ON t.UserID = u.UserID 
                                 WHERE t.TeacherID = ?");
    $getDetails->bind_param("i", $teacher_id);
    $getDetails->execute();
    $teacherInfo = $getDetails->get_result()->fetch_assoc();
    $getDetails->close();

    if (!$teacherInfo) {
        $_SESSION['error'] = 'Teacher not found.';
        header('Location: teacher.php');
        exit;
    }

    // Update teacher status to inactive
    $sql = "UPDATE teacher SET status = 'inactive' WHERE TeacherID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Teacher deactivated successfully.';
        log_system_action($conn, 'deactivate_teacher', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'email' => $teacherInfo['Email'],
            'name' => $teacherInfo['fName'] . ' ' . $teacherInfo['lName']
        ], 'info');
    } else {
        $_SESSION['error'] = 'Failed to deactivate teacher.';
        log_system_action($conn, 'deactivate_teacher_failed', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'email' => $teacherInfo['Email'] ?? null
        ], 'error');
    }
    $stmt->close();
    
    header('Location: teacher.php');
    exit;
}

// Handle reactivation
if (isset($_GET['activate_teacher_id'])) {
    $teacher_id = $_GET['activate_teacher_id'];

    // Get teacher details before activation for logging
    $getDetails = $conn->prepare("SELECT t.TeacherID, t.fName, t.mName, t.lName, u.Email 
                                 FROM teacher t 
                                 JOIN user u ON t.UserID = u.UserID 
                                 WHERE t.TeacherID = ?");
    $getDetails->bind_param("i", $teacher_id);
    $getDetails->execute();
    $teacherInfo = $getDetails->get_result()->fetch_assoc();
    $getDetails->close();

    if (!$teacherInfo) {
        $_SESSION['error'] = 'Teacher not found.';
        header('Location: teacher.php?view=inactive');
        exit;
    }

    // Update teacher status to active
    $sql = "UPDATE teacher SET status = 'active' WHERE TeacherID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Teacher activated successfully.';
        log_system_action($conn, 'activate_teacher', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'email' => $teacherInfo['Email'],
            'name' => $teacherInfo['fName'] . ' ' . $teacherInfo['lName']
        ], 'info');
    } else {
        $_SESSION['error'] = 'Failed to activate teacher.';
        log_system_action($conn, 'activate_teacher_failed', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'email' => $teacherInfo['Email'] ?? null
        ], 'error');
    }
    $stmt->close();
    
    header('Location: teacher.php?view=inactive');
    exit;
}

// Handle permanent deletion
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
        header('Location: teacher.php?view=inactive');
        exit;
    }

    // Start transaction for deleting both teacher and user
    $conn->begin_transaction();
    try {
        // Delete from assigned_subject table first (foreign key constraint)
        $sqlDeleteAssignments = "DELETE FROM assigned_subject WHERE teacher_id = ?";
        $stmtAssignments = $conn->prepare($sqlDeleteAssignments);
        $stmtAssignments->bind_param("i", $teacher_id);
        $stmtAssignments->execute();
        $stmtAssignments->close();

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
        
        $_SESSION['message'] = 'Teacher permanently deleted successfully.';
        log_system_action($conn, 'delete_teacher_permanent', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'email' => $teacherInfo['Email'],
            'name' => $teacherInfo['fName'] . ' ' . $teacherInfo['lName']
        ], 'info');

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        $_SESSION['error'] = 'Failed to delete teacher permanently.';
        log_system_action($conn, 'delete_teacher_failed', $_SESSION['user_id'] ?? null, [
            'teacherId' => $teacher_id,
            'error' => $e->getMessage(),
            'email' => $teacherInfo['Email'] ?? null
        ], 'error');
    }
    
    header('Location: teacher.php?view=inactive');
    exit;
}

// Determine current view
$current_view = isset($_GET['view']) && $_GET['view'] === 'inactive' ? 'inactive' : 'active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher List</title>
  <link rel="icon" href="../img/logo.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .required-field::after {
        content: " *";
        color: #dc3545;
    }
    .form-control.is-invalid {
        border-color: #dc3545;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6.4.4.4-.4'/%3e%3cpath d='M6 7v1'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    .form-control.is-valid {
        border-color: #198754;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    .invalid-feedback {
        display: none;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
    .is-invalid ~ .invalid-feedback {
        display: block;
    }
    .form-text {
        font-size: 0.875em;
        color: #6c757d;
    }
  </style>
</head>
<body>
  <?php include '../navs/headNav.php'; ?>
  <div class="container-fluid mt-5">
    <div class="card p-4 mb-3">
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="mb-0">
          <?php echo $current_view === 'active' ? 'Active Teachers' : 'Inactive Teachers'; ?>
        </h2>
        <div>
          <?php if ($current_view === 'active'): ?>
            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
              Add Teacher
            </button>
            <button class="btn btn-light border-dark btn-sm me-2" data-bs-toggle="modal" data-bs-target="#importModal">
              Import
            </button>
            <a href="teacher.php?view=inactive" class="btn btn-warning btn-sm">
              <i class="fas fa-archive me-1"></i> Archives
            </a>
          <?php else: ?>
            <a href="teacher.php" class="btn btn-secondary btn-sm me-2">
              <i class="fas fa-users me-1"></i> back
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card p-4">
      <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
      <?php endif; ?>

      <table id="teacherTable" class="table table-responsive table-bordered table-striped">
        <thead class="table-info">
          <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Contact</th>
            <th>Assigned Subjects</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Get active school year
          $active_school_year = '';
          $yearResult = mysqli_query($conn, "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
          if ($yearResult && mysqli_num_rows($yearResult) > 0) {
              $yearRow = mysqli_fetch_assoc($yearResult);
              $active_school_year = $yearRow['school_year'];
          }
          mysqli_free_result($yearResult);

          $status_condition = $current_view === 'active' ? "t.status = 'active'" : "t.status = 'inactive'";
          
          $sql = "SELECT 
                    t.TeacherID,
                    t.fName, t.mName, t.lName, t.surfix, t.gender, t.birthdate, t.address, t.contact, t.status,
                    u.Email,
                    GROUP_CONCAT(DISTINCT CONCAT(s.SubjectName, ' - Grade ', sec.GradeLevel, ' (', sec.SectionName, ')') ORDER BY sec.GradeLevel, s.SubjectName SEPARATOR '<br>') AS AssignedSubjects
                  FROM teacher t
                  JOIN user u ON t.UserID = u.UserID
                  LEFT JOIN assigned_subject a ON t.TeacherID = a.teacher_id AND a.school_year = ?
                  LEFT JOIN subject s ON a.subject_id = s.SubjectID
                  LEFT JOIN section sec ON a.section_id = sec.SectionID
                  WHERE $status_condition
                  GROUP BY t.TeacherID
                  ORDER BY t.lName, t.fName";

          $stmt = $conn->prepare($sql);
          $stmt->bind_param("s", $active_school_year);
          $stmt->execute();
          $res = $stmt->get_result();
          $i = 1;
          
          if ($res->num_rows) {
            while ($r = $res->fetch_assoc()) {
              $fullName = htmlspecialchars($r['fName'] . ' ' . $r['mName'] . ' ' . $r['lName']);
              if (!empty($r['surfix'])) {
                $fullName .= ' ' . htmlspecialchars($r['surfix']);
              }
              $email = htmlspecialchars($r['Email']);
              $contact = htmlspecialchars($r['contact'] ?? 'N/A');
              $assignedSubjects = $r['AssignedSubjects'] ?: 'No subjects assigned';
              $status = htmlspecialchars($r['status']);
              $statusBadge = $status === 'active' ? 'badge bg-success' : 'badge bg-secondary';
              
              echo "<tr>
                      <td>{$i}</td>
                      <td>{$fullName}</td>
                      <td>{$email}</td>
                      <td>{$contact}</td>
                      <td>{$assignedSubjects}</td>
                      <td><span class='{$statusBadge}'>{$status}</span></td>
                      <td class='d-flex gap-1'>";
              
              // View button for both active and inactive
              echo "<button
                      class='btn btn-sm btn-primary viewBtn'
                      data-bs-toggle='modal'
                      data-bs-target='#viewModal'
                      data-first-name='".htmlspecialchars($r['fName'])."'
                      data-middle-name='".htmlspecialchars($r['mName'])."'
                      data-last-name='".htmlspecialchars($r['lName'])."'
                      data-surfix='".htmlspecialchars($r['surfix'] ?? '')."'
                      data-gender='".htmlspecialchars($r['gender'] ?? '')."'
                      data-email='{$email}'
                      data-contact='".htmlspecialchars($r['contact'] ?? '')."'
                      data-birthdate='".htmlspecialchars($r['birthdate'] ?? '')."'
                      data-address='".htmlspecialchars($r['address'] ?? '')."'
                      data-status='{$status}'
                      data-assigned-subjects='".htmlspecialchars($r['AssignedSubjects'] ?? '')."'
                    >
                      View
                    </button>";
              
              if ($current_view === 'active') {
                // Deactivate button for active teachers
                echo "<button type='button' class='btn btn-sm btn-outline-secondary' 
                        onclick='document.getElementById(\"deactivateTeacherId\").value=\"{$r['TeacherID']}\";document.getElementById(\"deactivateTeacherName\").textContent=\"{$fullName}\";'
                        data-bs-toggle='modal' 
                        data-bs-target='#deactivateModal'>
                        Deactivate
                      </button>";
              } else {
                // Reactivate and Delete buttons for inactive teachers
                echo "<a href='teacher.php?activate_teacher_id={$r['TeacherID']}' 
                      class='btn btn-sm btn-success'
                      onclick='return confirm(\"Are you sure you want to activate this teacher?\")'>
                      Activate
                    </a>";
                echo "<button type='button' class='btn btn-sm btn-danger' 
                        onclick='document.getElementById(\"deleteTeacherId\").value=\"{$r['TeacherID']}\";document.getElementById(\"deleteTeacherName\").textContent=\"{$fullName}\";'
                        data-bs-toggle='modal' 
                        data-bs-target='#deleteModal'>
                        Delete
                      </button>";
              }
              
              echo "</td>
                    </tr>";
              $i++;
            }
          } else {
            $colspan = $current_view === 'active' ? 'No active teachers found' : 'No inactive teachers found';
            echo "<tr><td colspan='7' class='text-center'>{$colspan}</td></tr>";
          }
          $stmt->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Import Modal -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="../administration/importTeachers.php" method="post" enctype="multipart/form-data">
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
            
            <!-- Template Download Section -->
            <div class="template-download rounded p-3 mb-3 text-center mt-3">
              <h6 class="mb-2">Download Template File</h6>
              <a href="templates/teacher_records.xlsx" class="btn btn-outline-primary btn-sm" download>
                <i class="fas fa-download me-1"></i> Download Teacher Template (XLSX)
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
        </div>
      </form>
    </div>
  </div>

  <!-- Add Teacher Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="addTeacherForm" action="../administration/addTeacher.php" method="post" novalidate>
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="firstName" class="form-label required-field">First Name</label>
                <input type="text" name="first_name" id="firstName" class="form-control" required
                       pattern="[A-Za-z\s]{2,}" maxlength="50">
                <div class="invalid-feedback">
                  Please provide a valid first name (letters and spaces only, minimum 2 characters).
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="middleName" class="form-label">Middle Name</label>
                <input type="text" name="middle_name" id="middleName" class="form-control"
                       pattern="[A-Za-z\s]*" maxlength="50">
                <div class="invalid-feedback">
                  Middle name can only contain letters and spaces.
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="lastName" class="form-label required-field">Last Name</label>
                <input type="text" name="last_name" id="lastName" class="form-control" required
                       pattern="[A-Za-z\s]{2,}" maxlength="50">
                <div class="invalid-feedback">
                  Please provide a valid last name (letters and spaces only, minimum 2 characters).
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="surfix" class="form-label">Suffix</label>
                <input type="text" name="surfix" id="surfix" class="form-control" 
                       placeholder="Jr., Sr., III, etc." maxlength="10">
                <div class="invalid-feedback">
                  Suffix can only contain letters, numbers, and periods (max 10 characters).
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="gender" class="form-label required-field">Gender</label>
                <select name="gender" id="gender" class="form-select" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
                <div class="invalid-feedback">
                  Please select a gender.
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="birthdate" class="form-label">Birthdate</label>
                <input type="date" name="birthdate" id="birthdate" class="form-control" 
                       max="<?php echo date('Y-m-d', strtotime('-23 years')); ?>">
                <div class="invalid-feedback">
                  Birthdate cannot be in the future and teacher must be at least 23 years old.
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label for="emailAddress" class="form-label required-field">Email Address</label>
              <input type="email" name="email_address" id="emailAddress" class="form-control" required
                     pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" maxlength="100">
              <div class="invalid-feedback">
                Please provide a valid email address.
              </div>
            </div>
            <div class="mb-3">
              <label for="contact" class="form-label">Contact Number</label>
              <input type="text" name="contact" id="contact" class="form-control" 
                     pattern="[\+]?[0-9\s\-\(\)]{7,}" maxlength="20"
                     placeholder="e.g., 09123456789 or +639123456789">
              <div class="invalid-feedback">
                Please provide a valid contact number (minimum 7 digits, can include +, spaces, hyphens, parentheses).
              </div>
            </div>
            <div class="mb-3">
              <label for="address" class="form-label">Address</label>
              <textarea name="address" id="address" class="form-control" rows="3" maxlength="255"></textarea>
              <div class="form-text">Maximum 255 characters.</div>
              <div class="invalid-feedback">
                Address cannot exceed 255 characters.
              </div>
            </div>
            <div class="alert alert-info">
              <small>
                <i class="fas fa-info-circle me-1"></i>
                Default password will be the teacher's last name in lowercase. The teacher will be prompted to change it on first login.
              </small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add_teacher" class="btn btn-success">Save Teacher</button>
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
          <div class="row">
            <div class="col-md-6">
              <p><strong>First Name:</strong> <span id="detailFirstName"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Middle Name:</strong> <span id="detailMiddleName"></span></p>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Last Name:</strong> <span id="detailLastName"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Suffix:</strong> <span id="detailSurfix"></span></p>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Gender:</strong> <span id="detailGender"></span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Birthdate:</strong> <span id="detailBirthdate"></span></p>
            </div>
          </div>
          <p><strong>Email:</strong> <span id="detailEmail"></span></p>
          <p><strong>Contact:</strong> <span id="detailContact"></span></p>
          <p><strong>Address:</strong> <span id="detailAddress"></span></p>
          <p><strong>Status:</strong> <span id="detailStatus"></span></p>
          <p><strong>Assigned Subjects:</strong> <span id="detailAssignedSubjects"></span></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Deactivate Confirmation Modal -->
  <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="teacher.php" method="GET">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deactivateModalLabel">Deactivate Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to deactivate teacher: <strong><span id="deactivateTeacherName"></span></strong>?</p>
            <p class="text-warning">The teacher will be moved to inactive status and can be reactivated later.</p>
            <input type="hidden" name="deactivate_teacher_id" id="deactivateTeacherId">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Deactivate</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form action="teacher.php" method="GET">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteModalLabel">Permanently Delete Teacher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to permanently delete teacher: <strong><span id="deleteTeacherName"></span></strong>?</p>
            <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All teacher data including user account will be permanently deleted.</p>
            <input type="hidden" name="delete_teacher_id" id="deleteTeacherId">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete Permanently</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="loadingModalLabel">Importing Teachers</h5>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">Please wait while we import the teacher data...</p>
                <p class="text-muted small mt-2">This may take a few moments depending on the file size.</p>
            </div>
            <div class="modal-footer border-0">
                <!-- No buttons - modal can't be closed manually -->
            </div>
        </div>
    </div>
</div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(() => {
        const table = $('#teacherTable').DataTable({
            "pageLength": 25,
            "responsive": true
        });

        // Populate and show Details modal
        document.querySelectorAll('.viewBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                $('#detailFirstName').text(this.dataset.firstName || 'N/A');
                $('#detailMiddleName').text(this.dataset.middleName || 'N/A');
                $('#detailLastName').text(this.dataset.lastName || 'N/A');
                $('#detailSurfix').text(this.dataset.surfix || 'N/A');
                $('#detailGender').text(this.dataset.gender || 'N/A');
                $('#detailEmail').text(this.dataset.email || 'N/A');
                $('#detailContact').text(this.dataset.contact || 'N/A');
                $('#detailBirthdate').text(this.dataset.birthdate || 'N/A');
                $('#detailAddress').text(this.dataset.address || 'N/A');
                $('#detailStatus').text(this.dataset.status || 'N/A');
                
                const assignedSubjects = this.dataset.assignedSubjects || 'No subjects assigned';
                $('#detailAssignedSubjects').html(assignedSubjects.replace(/<br>/g, ', '));
            });
        });

        // Handle import form submission
        $('#importModal form').on('submit', function(e) {
            // Show loading modal
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            loadingModal.show();
            
            // Hide the import modal
            const importModal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
            importModal.hide();
            
            // The form will continue submitting in the background
        });

        // Hide loading modal when page reloads (if it's still showing)
        <?php if (isset($_SESSION['message']) || isset($_SESSION['error'])): ?>
            $(document).ready(function() {
                const loadingModal = document.getElementById('loadingModal');
                const modalInstance = bootstrap.Modal.getInstance(loadingModal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        <?php endif; ?>

        // Form validation for add teacher
        const addTeacherForm = document.getElementById('addTeacherForm');
        
        // Real-time validation
        const fields = addTeacherForm.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.addEventListener('input', function() {
                validateField(this);
            });
            
            field.addEventListener('blur', function() {
                validateField(this);
            });
        });

        // Form submission validation
        addTeacherForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            let isValid = true;
            fields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });

            if (isValid) {
                // Check if email already exists
                checkEmailExists().then(emailExists => {
                    if (emailExists) {
                        showEmailError();
                        return;
                    }
                    
                    // If all validations pass, submit the form
                    addTeacherForm.submit();
                }).catch(error => {
                    console.error('Error checking email:', error);
                    // If email check fails, still submit the form (server will handle duplicate check)
                    addTeacherForm.submit();
                });
            }
        });

        function validateField(field) {
            const value = field.value.trim();
            const isRequired = field.hasAttribute('required');
            const pattern = field.getAttribute('pattern');
            const maxLength = field.getAttribute('maxlength');
            
            // Clear previous validation states
            field.classList.remove('is-valid', 'is-invalid');
            
            // Check required fields
            if (isRequired && !value) {
                field.classList.add('is-invalid');
                return false;
            }
            
            // Skip validation for empty non-required fields
            if (!isRequired && !value) {
                return true;
            }
            
            // Check pattern if exists
            if (pattern && value) {
                const regex = new RegExp(pattern);
                if (!regex.test(value)) {
                    field.classList.add('is-invalid');
                    return false;
                }
            }
            
            // Check max length
            if (maxLength && value.length > parseInt(maxLength)) {
                field.classList.add('is-invalid');
                return false;
            }
            
            // Special validations for specific fields
            if (field.id === 'birthdate' && value) {
                const birthDate = new Date(value);
                const today = new Date();
                const minAgeDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
                
                if (birthDate > minAgeDate) {
                    field.classList.add('is-invalid');
                    return false;
                }
            }
            
            if (field.id === 'emailAddress' && value) {
                const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailRegex.test(value)) {
                    field.classList.add('is-invalid');
                    return false;
                }
            }
            
            if (field.id === 'contact' && value) {
                const contactRegex = /^[\+]?[0-9\s\-\(\)]{7,}$/;
                if (!contactRegex.test(value.replace(/\s/g, ''))) {
                    field.classList.add('is-invalid');
                    return false;
                }
            }
            
            // If all validations pass
            field.classList.add('is-valid');
            return true;
        }

        function checkEmailExists() {
            const email = document.getElementById('emailAddress').value.trim();
            
            return new Promise((resolve, reject) => {
                if (!email) {
                    resolve(false);
                    return;
                }
                
                $.ajax({
                    url: '../administration/check_email.php',
                    type: 'POST',
                    data: {
                        email: email,
                        check_type: 'teacher'
                    },
                    success: function(response) {
                        resolve(response.exists === true);
                    },
                    error: function(xhr, status, error) {
                        reject(error);
                    }
                });
            });
        }

        function showEmailError() {
            const emailField = document.getElementById('emailAddress');
            emailField.classList.remove('is-valid');
            emailField.classList.add('is-invalid');
            
            // Create or update custom error message
            let customError = emailField.parentNode.querySelector('.email-exists-error');
            if (!customError) {
                customError = document.createElement('div');
                customError.className = 'invalid-feedback email-exists-error';
                emailField.parentNode.appendChild(customError);
            }
            customError.textContent = 'This email address is already registered. Please use a different email.';
            
            // Focus on the email field
            emailField.focus();
        }

        // Reset form when modal is closed
        $('#addModal').on('hidden.bs.modal', function() {
            addTeacherForm.reset();
            // Remove validation classes
            fields.forEach(field => {
                field.classList.remove('is-valid', 'is-invalid');
            });
        });
    });
</script>
</body>
</html>