<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BANAHIS | Teacher List</title>
  <link rel="icon" href="../img/logo.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    .clickable-row {
      cursor: pointer;
    }
    .clickable-row:hover {
      background-color: #454c53ff;
    }
    #viewModal .modal-body p {
      margin-bottom: 0.8rem;
      padding-bottom: 0.8rem;
      border-bottom: 1px solid #f0f0f0;
    }
    #viewModal .modal-body p:last-child {
      border-bottom: none;
    }
  </style>
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
    </div>

    <table id="teacherTable" class="table table-bordered table-hover align-middle">
      <thead class="table-info">
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Gender</th>
          <th>Status</th>
          <th>Assigned Subjects</th>
        </tr>
      </thead>
      <tbody>
        <?php
          include '../config.php';
          $sql = "SELECT 
                    t.TeacherID,
                    t.fName, t.mName, t.lName, t.surfix, t.gender, t.birthdate, 
                    t.address, t.contact, t.status,
                    u.Email,
                    GROUP_CONCAT(DISTINCT sub.SubjectName ORDER BY sub.SubjectName SEPARATOR ', ') AS Subjects,
                    GROUP_CONCAT(DISTINCT CONCAT(s.GradeLevel, ' - ', s.SectionName) ORDER BY s.GradeLevel, s.SectionName SEPARATOR '; ') AS AssignedSections
                  FROM teacher t
                  JOIN user u ON t.UserID = u.UserID
                  LEFT JOIN assigned_subject a ON t.TeacherID = a.teacher_id
                  LEFT JOIN section s ON a.section_id = s.SectionID
                  LEFT JOIN subject sub ON a.subject_id = sub.SubjectID
                  GROUP BY t.TeacherID
                  ORDER BY t.lName, t.fName";
          
          $res = $conn->query($sql);
          $i = 1;
          if ($res && $res->num_rows > 0) {
            while ($r = $res->fetch_assoc()) {
              // Prepare display values
              $fullName = htmlspecialchars($r['fName'].' '.$r['mName'].' '.$r['lName'].($r['surfix'] ? ' '.$r['surfix'] : ''));
              $email = htmlspecialchars($r['Email']);
              $gender = htmlspecialchars($r['gender'] ?? 'N/A');
              $status = htmlspecialchars($r['status'] ?? 'N/A');
              $subjects = htmlspecialchars($r['Subjects'] ?? 'No subjects assigned');
              $assignedSections = htmlspecialchars($r['AssignedSections'] ?? 'No sections assigned');
              $birthdate = htmlspecialchars($r['birthdate']);
              $address = htmlspecialchars($r['address']);
              $contact = htmlspecialchars($r['contact'] ?? 'N/A');
              
              echo "<tr class='clickable-row' 
                    data-first-name='".htmlspecialchars($r['fName'])."'
                    data-middle-name='".htmlspecialchars($r['mName'])."'
                    data-last-name='".htmlspecialchars($r['lName'])."'
                    data-surfix='".htmlspecialchars($r['surfix'] ?? '')."'
                    data-email='{$email}'
                    data-gender='{$gender}'
                    data-status='{$status}'
                    data-subjects='{$subjects}'
                    data-sections='{$assignedSections}'
                    data-birthdate='{$birthdate}'
                    data-address='{$address}'
                    data-phone-number='{$contact}'>
                      <td>{$i}</td>
                      <td>{$fullName}</td>
                      <td>{$email}</td>
                      <td>{$gender}</td>
                      <td>{$status}</td>
                      <td>{$subjects}</td>
                    </tr>";
              $i++;
            }
          } else {
            echo "<tr><td colspan='6' class='text-center'>No teachers found</td></tr>";
          }
          $conn->close();
        ?>
      </tbody>
    </table>
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
          <p><strong>Suffix:</strong> <span id="detailSurfix"></span></p>
          <p><strong>Email:</strong> <span id="detailEmail"></span></p>
          <p><strong>Gender:</strong> <span id="detailGender"></span></p>
          <p><strong>Status:</strong> <span id="detailStatus"></span></p>
          <p><strong>Subjects:</strong> <span id="detailSubjects"></span></p>
          <p><strong>Assigned Sections:</strong> <span id="detailSections"></span></p>
          <p><strong>Birthdate:</strong> <span id="detailBirthdate"></span></p>
          <p><strong>Address:</strong> <span id="detailAddress"></span></p>
          <p><strong>Contact Number:</strong> <span id="detailPhoneNumber"></span></p>
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

  <script>
    $(document).ready(() => {
      $('#teacherTable').DataTable({
        "pageLength": 25,
        "responsive": true
      });
      
      // Handle row click to show teacher details
      $('#teacherTable').on('click', 'tr.clickable-row', function() {
        // Populate modal with data from the row
        $('#detailFirstName').text(this.dataset.firstName || 'N/A');
        $('#detailMiddleName').text(this.dataset.middleName || 'N/A');
        $('#detailLastName').text(this.dataset.lastName || 'N/A');
        $('#detailSurfix').text(this.dataset.surfix || 'N/A');
        $('#detailEmail').text(this.dataset.email || 'N/A');
        $('#detailGender').text(this.dataset.gender || 'N/A');
        $('#detailStatus').text(this.dataset.status || 'N/A');
        $('#detailSubjects').text(this.dataset.subjects || 'No subjects assigned');
        $('#detailSections').text(this.dataset.sections || 'No sections assigned');
        $('#detailBirthdate').text(this.dataset.birthdate || 'N/A');
        $('#detailAddress').text(this.dataset.address || 'N/A');
        $('#detailPhoneNumber').text(this.dataset.phoneNumber || 'N/A');
        
        // Show the modal
        $('#viewModal').modal('show');
      });
    });
  </script>
</body>
</html>