<?php
// Start session and include config at the top
session_start();
include __DIR__ . '/../config.php';

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
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Student List</h2>
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
      <table id="studentTable" class="table table-bordered table-hover align-middle">
        <thead class="table-info">
          <tr>
            <th>LRN</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Grade Level</th>
            <th>Section</th>
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
            <tr class="clickable-row" data-id="<?= $row['StudentID'] ?>">
              <td><?= htmlspecialchars($row['LRN']) ?></td>
              <td><?= htmlspecialchars($row['FullName']) ?></td>
              <td><?= htmlspecialchars($row['Email']) ?></td>
              <td><?= htmlspecialchars($row['GradeLevel']) ?></td>
              <td><?= htmlspecialchars($row['SectionName']) ?></td>
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
      $('#studentTable').DataTable();
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
  </script>
</body>
</html>