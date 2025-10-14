<?php
session_start();
include 'config.php'; 

$userId = $_SESSION['user_id'] ?? 2; // Default to 2 for testing
$stmt = $conn->prepare("SELECT TeacherID FROM teacher WHERE UserID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not registered as a teacher.";
    exit;
}
$teacherId = $res->fetch_assoc()['TeacherID'];
$stmt->close();

// Fetch students from database
$students = [];
$stmt = $conn->prepare("
    SELECT s.StudentID, s.FirstName, s.MiddleName, s.LastName, s.Sex 
    FROM student s 
    JOIN section_enrollment se ON s.StudentID = se.StudentID 
    JOIN section sec ON se.SectionID = sec.SectionID 
    WHERE sec.AdviserID = ? AND se.SchoolYear = '2025-2026' AND se.status = 'active'
    ORDER BY s.Sex, s.LastName, s.FirstName
");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'id' => $row['StudentID'],
        'name' => $row['LastName'] . ', ' . $row['FirstName'] . (($row['MiddleName'] != '') ? ' ' . substr($row['MiddleName'], 0, 1) . '.' : ''),
        'gender' => $row['Sex']
    ];
}
$stmt->close();

$subjectId = $_POST['subjectId'] ?? 4; 

$stmt = $conn->prepare("SELECT SubjectName, written_work_percentage, performance_task_percentage, quarterly_assessment_percentage FROM subject WHERE SubjectID = ?");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$stmt->bind_result($subjectName, $ww, $pt, $qa);
$stmt->fetch();
$wwPercentage = $ww*100;
$ptPercentage = $pt*100;
$qaPercentage = $qa*100;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Science Class Record</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        
        th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
        }
        
        .gender-header {
            background-color: #e9ecef !important;
        }
        
        .max-score-input {
            width: 60px;
            font-size: 0.8rem;
        }
        
        .save-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid p-4">
        <div class="card shadow-sm">
            <!-- Header -->
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="h4 mb-0">Class Record</h1>
                    </div>
                    <div class="col-auto">
                        <div class="row g-3 text-start">

                            <div class="col-12 col-md-6">
                                <small>School Year: 2025-2026</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <small>Grade & Section: 
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT sec.GradeLevel, sec.SectionName 
                                        FROM section sec 
                                        WHERE sec.AdviserID = ?
                                    ");
                                    $stmt->bind_param('i', $teacherId);
                                    $stmt->execute();
                                    $sectionResult = $stmt->get_result();
                                    $sectionResult->num_rows > 0 && $section = $sectionResult->fetch_assoc();
                                    echo $section['GradeLevel'] . ' - ' . $section['SectionName'];
                                    $stmt->close();
                                    ?>
                                </small>
                            </div>
                            <div class="col-12 col-md-6">
                                <small>Teacher: 
                                    <?php
                                    $stmt = $conn->prepare("SELECT fName, lName FROM teacher WHERE TeacherID = ?");
                                    $stmt->bind_param('i', $teacherId);
                                    $stmt->execute();
                                    $teacherResult = $stmt->get_result();
                                    if ($teacherResult->num_rows > 0) {
                                        $teacher = $teacherResult->fetch_assoc();
                                        echo $teacher['fName'] . ' ' . $teacher['lName'];
                                    } else {
                                        echo "N/A";
                                    }
                                    $stmt->close();
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quarter Selector -->
            <div class="card-body border-bottom">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <label for="quarterSelect" class="form-label mb-0">Select Quarter:</label>
                    </div>
                    <div class="col-auto">
                        <select id="quarterSelect" class="form-select">
                            <option value="1">Quarter 1</option>
                            <option value="2">Quarter 2</option>
                            <option value="3">Quarter 3</option>
                            <option value="4">Quarter 4</option>
                            <option value="Summary">Summary</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Quarter 1 Content -->
            <div id="Q1" class="tab-content">
                <div class="card-body">


                    <!-- Table Container -->
                    <div class="table-container border rounded">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">No.</th>
                                    <th>Learner's Name</th>
                                    <th colspan="10" class="text-center">Written Works (<?= htmlspecialchars($wwPercentage) ?>%)</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">PS</th>
                                    <th class="text-center">WS</th>
                                    <th colspan="10" class="text-center">Performance Tasks (<?= htmlspecialchars($ptPercentage) ?>%)</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">PS</th>
                                    <th class="text-center">WS</th>
                                    <th class="text-center">QA (<?= htmlspecialchars($qaPercentage) ?>%)</th>
                                    <th class="text-center">PS</th>
                                    <th class="text-center">WS</th>
                                    <th class="text-center">Initial Grade</th>
                                    <th class="text-center">Quarterly Grade</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th>Highest possible score</th>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <th class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span><?= $i ?></span>
                                            <input type="number" class="form-control form-control-sm max-score-input ww-max" data-quarter="Q1" data-index="<?= $i ?>" value="0" min="0">
                                        </div>
                                    </th>
                                    <?php endfor; ?>
                                    <th class="text-center"><span class="ww-header-total" data-quarter="Q1">0</span></th>
                                    <th class="text-center"><span class="ww-header-ps" data-quarter="Q1">100.00</span></th>
                                    <th class="text-center"><span class="ww-header-ws" data-quarter="Q1">0</span>%</th>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <th class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span><?= $i ?></span>
                                            <input type="number" class="form-control form-control-sm max-score-input pt-max" data-quarter="Q1" data-index="<?= $i ?>" value="0" min="0">
                                        </div>
                                    </th>
                                    <?php endfor; ?>
                                    <th class="text-center"><span class="pt-header-total" data-quarter="Q1">0</span></th>
                                    <th class="text-center"><span class="pt-header-ps" data-quarter="Q1">100.00</span></th>
                                    <th class="text-center"><span class="pt-header-ws" data-quarter="Q1">0</span>%</th>
                                    <th class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span>QA</span>
                                            <input type="number" class="form-control form-control-sm max-score-input qa-max" data-quarter="Q1" value="0" min="0">
                                        </div>
                                    </th>
                                    <th class="text-center"><span class="qa-header-ps" data-quarter="Q1">100.00</span></th>
                                    <th class="text-center"><span class="qa-header-ws" data-quarter="Q1">0</span>%</th>
                                    <th class="text-center"></th>
                                    <th class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="q1DataBody">
                            </tbody>
                        </table>
                    </div>

                    <!-- Legend -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary me-2" style="width: 15px; height: 15px;"></div>
                                    <small>Written Works (<?= htmlspecialchars($wwPercentage) ?>%)</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary me-2" style="width: 15px; height: 15px;"></div>
                                    <small>Performance Tasks (<?= htmlspecialchars($ptPercentage) ?>%)</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary me-2" style="width: 15px; height: 15px;"></div>
                                    <small>Quarterly Assessment (<?= htmlspecialchars($qaPercentage) ?>%)</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary me-2" style="width: 15px; height: 15px;"></div>
                                    <small>Final Grade</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Content -->
            <div id="Summary" class="tab-content d-none">
                <div class="card-body">
                    <!-- Summary Info -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <strong>Summary of Grades</strong>
                        </div>
                        <div class="col-md-4">
                            <strong>Subject:</strong> <?= htmlspecialchars($subjectName) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Grade Level:</strong> Grade 7
                        </div>
                    </div>

                    <!-- Summary Table -->
                    <div class="table-container border rounded">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-primary">
                                <tr>
                                    <th class="text-center">No.</th>
                                    <th>Learner's Name</th>
                                    <th class="text-center">Q1 Grade</th>
                                    <th class="text-center">Q2 Grade</th>
                                    <th class="text-center">Q3 Grade</th>
                                    <th class="text-center">Q4 Grade</th>
                                    <th class="text-center">Final Grade</th>
                                    <th class="text-center">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="summaryDataBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Status Indicator -->
    <div id="saveStatus" class="save-status alert alert-success d-none" role="alert">
        <i class="fas fa-check-circle me-2"></i>Data saved successfully
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>


        function getRemarks(grade) {
            if (grade >= 75) return "PASSED";
            return "Did Not Meet Expectations";
        }
            function transmuteGrade(initialGrade) {
            // Apply the transmutation formula
            let transmuted = ((initialGrade - 60) / 1.6) + 75;
            
            // Round down to the nearest whole number
            transmuted = Math.floor(transmuted);
            
            // Ensure the grade doesn't go below 0
            if (transmuted < 0) transmuted = 0;
            
            // Ensure the grade doesn't exceed 100
            if (transmuted > 100) transmuted = 100;
            
            return transmuted;
        }
</script>


</body>
</html>