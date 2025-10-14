<?php
// File: admin_view_grades.php
session_start();
require '../config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Fetch all sections with their details including adviser information
$query = "
    SELECT 
        s.SectionID,
        s.SectionName,
        s.GradeLevel,
        s.AdviserID,
        COUNT(se.StudentID) as StudentCount,
        t.fName as TeacherFirstName,
        t.lName as TeacherLastName,
        t.mName as TeacherMiddleName
    FROM section s
    LEFT JOIN section_enrollment se ON s.SectionID = se.SectionID AND se.status = 'active'
    LEFT JOIN teacher te ON s.AdviserID = te.TeacherID
    LEFT JOIN user u ON te.UserID = u.UserID
    LEFT JOIN (
        SELECT 
            TeacherID,
            fName,
            lName,
            mName
        FROM teacher
    ) t ON s.AdviserID = t.TeacherID
    GROUP BY s.SectionID
    ORDER BY s.GradeLevel, s.SectionName
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin - View Grades by Section</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .section-card {
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 20px;
      height: 100%;
      border-radius: 12px;
      overflow: hidden;
    }
    .section-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    .card-header {
      color: white;
      font-weight: bold;
      padding: 15px 20px;
    }
    .grade-badge {
      font-size: 1.2rem;
      padding: 8px 18px;
      border-radius: 50px;
      background: rgba(255, 255, 255, 0.2);
    }
    .student-count {
      font-size: 0.9rem;
      color: #6c757d;
      display: flex;
      align-items: center;
    }
    .teacher-info {
      background-color: #f8f9fa;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 15px;
    }
    .stats-icon {
      font-size: 1.5rem;
      color: #4e73df;
      margin-right: 10px;
    }
    .card-footer {
      background-color: rgba(0,0,0,0.03);
      border-top: 1px solid rgba(0,0,0,0.05);
    }
    .page-header {
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 30px;
    }

  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <div class="page-header mb-4">
      <div class="row align-items-center">
        <div class="col">
          <h1 class="h3 mb-0"><i class="fas fa-graduation-cap me-2"></i>View Grades by Section</h1>
          <p class="mb-0">Select a section to view student grades</p>
        </div>
        <div class="col-auto">
          <a href="../admin/files.php" class="btn btn-light">
            <i class="fas fa-arrow-right me-1"></i> Go To Report Card Generation
          </a>
        </div>
      </div>
    </div>

    <?php if ($result->num_rows === 0): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No sections found in the system.
      </div>
    <?php else: ?>
      <div class="row">
        <?php while ($section = $result->fetch_assoc()): 
          // Build teacher name
          $teacherName = "Not assigned";
          if ($section['TeacherFirstName']) {
            $teacherName = $section['TeacherLastName'] . ', ' . $section['TeacherFirstName'];
            if ($section['TeacherMiddleName']) {
              $teacherName .= ' ' . substr($section['TeacherMiddleName'], 0, 1) . '.';
            }
          }
        ?>
          <div class="col-md-6 col-lg-4 mb-4">
            <div class="card section-card">
              <div class="card-header d-flex justify-content-between align-items-center bg-info">
                <span>Grade <?= htmlspecialchars($section['GradeLevel']) ?></span>
                <span class="grade-badge"><?= htmlspecialchars($section['GradeLevel']) ?></span>
              </div>
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($section['SectionName']) ?> Section</h5>
                
                <div class="teacher-info">
                  <h6 class="mb-1"><i class="fas fa-user-tie me-2"></i>Adviser</h6>
                  <p class="mb-0"><?= htmlspecialchars($teacherName) ?></p>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="student-count">
                    <i class="fas fa-users stats-icon"></i>
                    <?= htmlspecialchars($section['StudentCount']) ?> enrolled students
                  </span>
                </div>
                
                <div class="d-grid">
                  <a href="view_subjects_grades.php?section_id=<?= $section['SectionID'] ?>" class="btn btn-success">
                    <i class="fas fa-book-open me-1"></i> View Subjects & Grades
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>