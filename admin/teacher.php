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
          <th>Assigned Subjects</th>
        </tr>
      </thead>
      <tbody>
        <?php
          include '../config.php';
          $sql = "SELECT 
                    t.TeacherID,
                    t.fName, t.mName, t.lName, t.Birthdate, t.Address, t.Contact,
                    u.Email,
                    GROUP_CONCAT(s.SubjectName ORDER BY s.SubjectName) AS Subjects
                  FROM teacher t
                  JOIN user u ON t.UserID = u.UserID
                  LEFT JOIN subject s ON t.TeacherID = s.teacherID
                  GROUP BY t.TeacherID";
          $res = $conn->query($sql);
          $i = 1;
          if ($res->num_rows) {
            while ($r = $res->fetch_assoc()) {
              // Prepare display values
              $fullName   = htmlspecialchars($r['fName'].' '.$r['mName'].' '.$r['lName']);
              $email      = htmlspecialchars($r['Email']);
              $subjects   = htmlspecialchars($r['Subjects'] ?? 'N/A');
              $birthdate  = htmlspecialchars($r['Birthdate']);
              $address    = htmlspecialchars($r['Address']);
              $contact    = htmlspecialchars($r['Contact'] ?? 'N/A');
              echo "<tr class='clickable-row' 
                    data-first-name='".htmlspecialchars($r['fName'])."'
                    data-middle-name='".htmlspecialchars($r['mName'])."'
                    data-last-name='".htmlspecialchars($r['lName'])."'
                    data-email='{$email}'
                    data-subjects='{$subjects}'
                    data-birthdate='{$birthdate}'
                    data-address='{$address}'
                    data-phone-number='{$contact}'>
                      <td>{$i}</td>
                      <td>{$fullName}</td>
                      <td>{$email}</td>
                      <td>{$subjects}</td>
                    </tr>";
              $i++;
            }
          } else {
            echo "<tr><td colspan='4' class='text-center'>No teachers found</td></tr>";
          }
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
          <p><strong>Email:</strong> <span id="detailEmail"></span></p>
          <p><strong>Subjects:</strong> <span id="detailSubjects"></span></p>
          <p><strong>Birthdate:</strong> <span id="detailBirthdate"></span></p>
          <p><strong>Address:</strong> <span id="detailAddress"></span></p>
          <p><strong>Phone Number:</strong> <span id="detailPhoneNumber"></span></p>
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
      $('#teacherTable').DataTable();
      
      // Handle row click to show teacher details
      $('#teacherTable').on('click', 'tr.clickable-row', function() {
        // Populate modal with data from the row
        $('#detailFirstName').text(this.dataset.firstName);
        $('#detailMiddleName').text(this.dataset.middleName);
        $('#detailLastName').text(this.dataset.lastName);
        $('#detailEmail').text(this.dataset.email);
        $('#detailSubjects').text(this.dataset.subjects);
        $('#detailBirthdate').text(this.dataset.birthdate);
        $('#detailAddress').text(this.dataset.address);
        $('#detailPhoneNumber').text(this.dataset.phoneNumber);
        
        // Show the modal
        $('#viewModal').modal('show');
      });
    });
  </script>
</body>
</html>