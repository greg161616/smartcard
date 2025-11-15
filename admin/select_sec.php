<?php
// File: admin_view_grades.php
session_start();
require '../config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Get available school years from section_enrollment
$school_years_query = "SELECT DISTINCT SchoolYear FROM section_enrollment ORDER BY SchoolYear DESC";
$school_years_result = $conn->query($school_years_query);
$available_school_years = [];
while ($row = $school_years_result->fetch_assoc()) {
    $available_school_years[] = $row['SchoolYear'];
}

// Set default school year (current or most recent)
$selected_school_year = isset($_GET['school_year']) ? $_GET['school_year'] : 
    (!empty($available_school_years) ? $available_school_years[0] : date('Y') . '-' . (date('Y') + 1));

// Fetch all sections with their details including adviser information for selected school year
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
    LEFT JOIN section_enrollment se ON s.SectionID = se.SectionID AND se.status = 'active' AND se.SchoolYear = ?
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

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $selected_school_year);
$stmt->execute();
$result = $stmt->get_result();
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
    .school-year-selector {
      padding: 20px;
      margin-bottom: 30px;
    }
    .school-year-badge {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container">
    <!-- School Year Selector -->
    <div class="school-year-selector">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h4 class="mb-1"><i class="fas fa-graduation-cap me-2"></i>View Grades by Section</h4>
          <p class="mb-0">Select school year to view sections and grades</p>
        </div>
        <div class="col-md-4">
          <form method="GET" action="" class="d-flex">
            <select name="school_year" class="form-select me-2" onchange="this.form.submit()">
              <?php if (empty($available_school_years)): ?>
                <option value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                  <?php echo date('Y') . '-' . (date('Y') + 1); ?>
                </option>
              <?php else: ?>
                <?php foreach ($available_school_years as $school_year): ?>
                  <option value="<?php echo htmlspecialchars($school_year); ?>" 
                    <?php echo ($school_year == $selected_school_year) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($school_year); ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </form>
        </div>
      </div>
    </div>

    <!-- Current School Year Badge -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">Showing data for school year:</h5>
      <span class="school-year-badge bg-primary">
        <i class="fas fa-calendar me-1"></i>
        <?php echo htmlspecialchars($selected_school_year); ?>
      </span>
    </div>

    <?php if ($result->num_rows === 0): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No sections found for the selected school year.
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
              <div class="card-header d-flex justify-content-between align-items-center bg-info text-white">
                <span><h4 class="mb-0">Grade</h4></span>
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
                  <a href="view_subjects_grades.php?section_id=<?= $section['SectionID'] ?>&school_year=<?= urlencode($selected_school_year) ?>" 
                     class="btn btn-success">
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