<?php
session_start();
include '../config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit();
}

// Fetch statistics data
$students_count = 0;
$teachers_count = 0;
$sections_count = 0;
$male_students = 0;
$female_students = 0;
$active_sections = 0;

// Get total students
$sql = "SELECT COUNT(*) as count FROM student";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $students_count = $row['count'];
}

// Get male students
$sql = "SELECT COUNT(*) as count FROM student WHERE Sex = 'Male'";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $male_students = $row['count'];
}

// Get female students
$sql = "SELECT COUNT(*) as count FROM student WHERE Sex = 'Female'";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $female_students = $row['count'];
}

// Get total teachers
$sql = "SELECT COUNT(*) as count FROM teacher";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $teachers_count = $row['count'];
}

// Get total sections
$sql = "SELECT COUNT(*) as count FROM section";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $sections_count = $row['count'];
}

// Get active sections (sections with enrolled students)
$sql = "SELECT COUNT(*) AS failing_students
FROM grades
WHERE Final < 74;
";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $active_sections = $row['failing_students'];
}

// Get teachers by subject
$teachers_by_subject = [];
$sql = "SELECT s.SubjectName, COUNT(DISTINCT t.TeacherID) as count 
        FROM subject s 
        LEFT JOIN teacher t ON s.teacherID = t.TeacherID 
        GROUP BY s.SubjectID";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers_by_subject[] = $row;
    }
}

// Get upcoming events (next 7 days)
$upcoming_events = [];
$sql = "SELECT title, event_date, description, category 
        FROM events 
        WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
        ORDER BY event_date ASC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $upcoming_events[] = $row;
    }
}

// Get recent announcements
$recent_announcements = [];
$sql = "SELECT title, date, content 
        FROM announcement 
        ORDER BY date DESC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_announcements[] = $row;
    }
}

// Get students per section
$students_per_section = [];
$sql = "SELECT sec.GradeLevel, sec.SectionName, COUNT(se.StudentID) as student_count 
        FROM section sec 
        LEFT JOIN section_enrollment se ON sec.SectionID = se.SectionID AND se.status = 'enrolled' 
        GROUP BY sec.SectionID 
        ORDER BY sec.GradeLevel, sec.SectionName";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students_per_section[] = $row;
    }
}

// Get top 5 students by average grade
$top_students = [];
$sql = "SELECT s.FirstName, s.LastName, sec.SectionName, AVG(g.Final) as avg_grade 
        FROM student s 
        JOIN grades g ON s.StudentID = g.student_id 
        JOIN section_enrollment se ON s.StudentID = se.StudentID 
        JOIN section sec ON se.SectionID = sec.SectionID 
        WHERE g.Final IS NOT NULL 
        GROUP BY s.StudentID 
        ORDER BY avg_grade DESC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $top_students[] = $row;
    }
}

// Get students with average grade below 75
$low_performing_students = [];
$sql = "SELECT s.FirstName, s.LastName, sec.SectionName, AVG(g.Final) as avg_grade 
        FROM student s 
        JOIN grades g ON s.StudentID = g.student_id 
        JOIN section_enrollment se ON s.StudentID = se.StudentID 
        JOIN section sec ON se.SectionID = sec.SectionID 
        WHERE g.Final IS NOT NULL 
        GROUP BY s.StudentID 
        HAVING avg_grade < 75
        ORDER BY avg_grade ASC 
        LIMIT 10";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $low_performing_students[] = $row;
    }
}

// Get attendance statistics
$attendance_stats = [];
$sql = "SELECT 
        COUNT(CASE WHEN Status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN Status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN Status = 'late' THEN 1 END) as late,
        COUNT(*) as total
        FROM attendance 
        WHERE Date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$result = mysqli_query($conn, $sql);
if ($result) {
    $attendance_stats = mysqli_fetch_assoc($result);
}

// Get subjects taught by teachers
$subjects_by_teacher = [];
$sql = "SELECT t.fName, t.lName, GROUP_CONCAT(s.SubjectName SEPARATOR ', ') as subjects 
        FROM teacher t 
        LEFT JOIN subject s ON t.TeacherID = s.teacherID 
        GROUP BY t.TeacherID 
        ORDER BY t.lName, t.fName";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $subjects_by_teacher[] = $row;
    }
}

// Get performance by quarter
$quarter_performance = [];
$sql = "SELECT 
        quarter,
        AVG(quarterly_grade) as avg_grade,
        MAX(quarterly_grade) as max_grade,
        MIN(quarterly_grade) as min_grade
        FROM grades_details 
        WHERE quarterly_grade IS NOT NULL 
        GROUP BY quarter 
        ORDER BY quarter";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $quarter_performance[] = $row;
    }
}

// Get grade distribution
$grade_distribution = [];
$sql = "SELECT 
        CASE 
            WHEN Final >= 90 THEN 'A (90-100)'
            WHEN Final >= 80 THEN 'B (80-89)'
            WHEN Final >= 75 THEN 'C (75-79)'
            ELSE 'F (Below 75)'
        END as grade_range,
        COUNT(*) as count
        FROM grades 
        WHERE Final IS NOT NULL 
        GROUP BY grade_range 
        ORDER BY grade_range";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $grade_distribution[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BAHAHIS | Dashboard</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .stat-card {
      transition: transform 0.3s;
      border-left: 4px solid #0d6efd;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .dashboard-section {
      margin-bottom: 2rem;
      padding: 1.5rem;
      background-color: #f8f9fa;
      border-radius: 0.5rem;
    }
    .gender-card-male {
      border-left: 4px solid #0d6efd !important;
    }
    .gender-card-female {
      border-left: 4px solid #ff69b4 !important;
    }
    .total-card {
      border-left: 4px solid #28a745 !important;
    }
    .total-teacher{
      border-left: 4px solid #28c8ceff !important;
    }
    .section{
      border-left: 4px solid #ffc107 !important;
    }
    .fail{
      border-left: 4px solid #dc3545 !important;
    }
  </style>
</head>
<body>
 <?php include '../navs/adminNav.php'; ?>
<div class="container mt-4">
  <h1 class="mb-4">Principal Dashboard</h1>
  
  <!-- Overall School Statistics -->
  <div class="dashboard-section">
    <h2 class="mb-4">Overall School Statistics</h2>
    <div class="row">
      <div class="col-md-4 mb-4">
        <div class="card stat-card total-card">
          <div class="card-body">
            <h5 class="card-title">Total Students</h5>
            <h2 class="card-text"><?php echo $students_count; ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-4">
        <div class="card stat-card gender-card-male">
          <div class="card-body">
            <h5 class="card-title">Male Students</h5>
            <h2 class="card-text"><?php echo $male_students; ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-4">
        <div class="card stat-card gender-card-female">
          <div class="card-body">
            <h5 class="card-title">Female Students</h5>
            <h2 class="card-text"><?php echo $female_students; ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-4 mb-4">
        <div class="card stat-card total-teacher">
          <div class="card-body">
            <h5 class="card-title">Total Teachers</h5>
            <h2 class="card-text"><?php echo $teachers_count; ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-4">
        <div class="card stat-card section">
          <div class="card-body">
            <h5 class="card-title">Total Sections</h5>
            <h2 class="card-text"><?php echo $sections_count; ?></h2>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-4">
        <div class="card stat-card fail">
          <div class="card-body">
            <h5 class="card-title">Failing Student</h5>
            <h2 class="card-text"><?php echo $active_sections; ?></h2>
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <h5>Upcoming Events</h5>
          </div>
          <div class="card-body">
            <?php if (!empty($upcoming_events)): ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($upcoming_events as $event): ?>
                  <li class="list-group-item">
                    <strong><?php echo $event['title']; ?></strong> (<?php echo $event['category']; ?>)<br>
                    <small class="text-muted"><?php echo date('M j, Y', strtotime($event['event_date'])); ?></small>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="text-muted">No upcoming events</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <h5>Recent Announcements</h5>
          </div>
          <div class="card-body">
            <?php if (!empty($recent_announcements)): ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($recent_announcements as $announcement): ?>
                  <li class="list-group-item">
                    <strong><?php echo $announcement['title']; ?></strong><br>
                    <small class="text-muted"><?php echo date('M j, Y', strtotime($announcement['date'])); ?></small>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="text-muted">No recent announcements</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Student Statistics -->
  <div class="dashboard-section">
    <h2 class="mb-4">Student Statistics</h2>
    <div class="row">
      <div class="col-md-6">
        <div class="card mb-4">
          <div class="card-header">
            <h5>Students per Section</h5>
          </div>
          <div class="card-body">
            <canvas id="sectionChart" height="250"></canvas>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card mb-4">
          <div class="card-header">
            <h5>Grade Distribution</h5>
          </div>
          <div class="card-body">
            <canvas id="gradeChart" height="250"></canvas>
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <h5>Top 5 Students</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Section</th>
                    <th>Average Grade</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($top_students as $student): ?>
                    <tr>
                      <td><?php echo $student['FirstName'] . ' ' . $student['LastName']; ?></td>
                      <td><?php echo $student['SectionName']; ?></td>
                      <td><?php echo number_format($student['avg_grade'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <h5>Students Needing Attention (Below 75%)</h5>
          </div>
          <div class="card-body">
            <?php if (!empty($low_performing_students)): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Section</th>
                      <th>Average Grade</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($low_performing_students as $student): ?>
                      <tr>
                        <td><?php echo $student['FirstName'] . ' ' . $student['LastName']; ?></td>
                        <td><?php echo $student['SectionName']; ?></td>
                        <td class="text-danger"><?php echo number_format($student['avg_grade'], 2); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">No students with grades below 75%</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-4">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h5>Attendance Overview (Last 30 Days)</h5>
          </div>
          <div class="card-body">
            <canvas id="attendanceChart" height="100"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Teacher Statistics -->
  <div class="dashboard-section">
    <h2 class="mb-4">Teacher Statistics</h2>
    <div class="card mb-4">
      <div class="card-header">
        <h5>Subjects Taught by Each Teacher</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Teacher Name</th>
                <th>Subjects</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subjects_by_teacher as $teacher): ?>
                <tr>
                  <td><?php echo $teacher['fName'] . ' ' . $teacher['lName']; ?></td>
                  <td><?php echo $teacher['subjects'] ? $teacher['subjects'] : 'No subjects assigned'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Quarter Performance -->
  <div class="dashboard-section">
    <h2 class="mb-4">Quarterly Performance</h2>
    <div class="card">
      <div class="card-header">
        <h5>Performance by Quarter</h5>
      </div>
      <div class="card-body">
        <canvas id="quarterChart" height="150"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Section Chart
  const sectionCtx = document.getElementById('sectionChart').getContext('2d');
  const sectionLabels = <?php echo json_encode(array_map(function($item) { return $item['GradeLevel'] . ' - ' . $item['SectionName']; }, $students_per_section)); ?>;
  const sectionData = <?php echo json_encode(array_column($students_per_section, 'student_count')); ?>;
  
  new Chart(sectionCtx, {
    type: 'bar',
    data: {
      labels: sectionLabels,
      datasets: [{
        label: 'Number of Students',
        data: sectionData,
        backgroundColor: 'rgba(54, 162, 235, 0.5)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
  
  // Grade Distribution Chart
  const gradeCtx = document.getElementById('gradeChart').getContext('2d');
  const gradeLabels = <?php echo json_encode(array_column($grade_distribution, 'grade_range')); ?>;
  const gradeData = <?php echo json_encode(array_column($grade_distribution, 'count')); ?>;
  
  new Chart(gradeCtx, {
    type: 'pie',
    data: {
      labels: gradeLabels,
      datasets: [{
        data: gradeData,
        backgroundColor: [
          'rgba(75, 192, 192, 0.5)',
          'rgba(54, 162, 235, 0.5)',
          'rgba(255, 206, 86, 0.5)',
          'rgba(255, 99, 132, 0.5)'
        ],
        borderColor: [
          'rgba(75, 192, 192, 1)',
          'rgba(54, 162, 235, 1)',
          'rgba(255, 206, 86, 1)',
          'rgba(255, 99, 132, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true
    }
  });
  
  // Attendance Chart
  const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
  new Chart(attendanceCtx, {
    type: 'bar',
    data: {
      labels: ['Present', 'Absent', 'Late'],
      datasets: [{
        label: 'Count',
        data: [
          <?php echo $attendance_stats['present'] ?? 0; ?>,
          <?php echo $attendance_stats['absent'] ?? 0; ?>,
          <?php echo $attendance_stats['late'] ?? 0; ?>
        ],
        backgroundColor: [
          'rgba(75, 192, 192, 0.5)',
          'rgba(255, 99, 132, 0.5)',
          'rgba(255, 206, 86, 0.5)'
        ],
        borderColor: [
          'rgba(75, 192, 192, 1)',
          'rgba(255, 99, 132, 1)',
          'rgba(255, 206, 86, 1)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
  
  // Quarter Performance Chart
  const quarterCtx = document.getElementById('quarterChart').getContext('2d');
  const quarterLabels = <?php echo json_encode(array_column($quarter_performance, 'quarter')); ?>;
  const quarterAvgData = <?php echo json_encode(array_column($quarter_performance, 'avg_grade')); ?>;
  const quarterMaxData = <?php echo json_encode(array_column($quarter_performance, 'max_grade')); ?>;
  const quarterMinData = <?php echo json_encode(array_column($quarter_performance, 'min_grade')); ?>;
  
  new Chart(quarterCtx, {
    type: 'line',
    data: {
      labels: quarterLabels,
      datasets: [
        {
          label: 'Average Grade',
          data: quarterAvgData,
          fill: false,
          borderColor: 'rgb(75, 192, 192)',
          tension: 0.1
        },
        {
          label: 'Highest Grade',
          data: quarterMaxData,
          fill: false,
          borderColor: 'rgb(54, 162, 235)',
          tension: 0.1
        },
        {
          label: 'Lowest Grade',
          data: quarterMinData,
          fill: false,
          borderColor: 'rgb(255, 99, 132)',
          tension: 0.1
        }
      ]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: false,
          suggestedMin: 70,
          suggestedMax: 100
        }
      }
    }
  });
</script>
</body>
</html>