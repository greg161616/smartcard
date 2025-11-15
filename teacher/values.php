<?php
session_start();
require '../config.php';

// Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher_query = "SELECT * FROM teacher WHERE userID = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
$sectionID = 1;

// Get available school years
$school_years_query = "SELECT DISTINCT SchoolYear FROM section_enrollment ORDER BY SchoolYear DESC";
$school_years_result = mysqli_query($conn, $school_years_query);
$school_years = [];
while ($row = mysqli_fetch_assoc($school_years_result)) {
    $school_years[] = $row['SchoolYear'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_values'])) {
    $quarter = $_POST['quarter'];
    $school_year = $_POST['school_year'];
    
    foreach ($_POST['students'] as $studentID => $values) {
        // Check if record exists
        $check_sql = "SELECT valuesID FROM student_values WHERE student_id = ? AND quarter = ? AND school_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iis", $studentID, $quarter, $school_year);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $update_sql = "UPDATE student_values SET 
                          makadiyos_1 = ?, makadiyos_2 = ?, 
                          makatao_1 = ?, makatao_2 = ?, 
                          makakalikasan_1 = ?, 
                          makabansa_1 = ?, makabansa_2 = ?
                          WHERE student_id = ? AND quarter = ? AND school_year = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssssiis", 
                $values['makadiyos_1'], $values['makadiyos_2'],
                $values['makatao_1'], $values['makatao_2'],
                $values['makakalikasan_1'],
                $values['makabansa_1'], $values['makabansa_2'],
                $studentID, $quarter, $school_year
            );
            $update_stmt->execute();
        } else {
            // Insert new record
            $insert_sql = "INSERT INTO student_values 
                          (student_id, quarter, school_year, makadiyos_1, makadiyos_2, makatao_1, makatao_2, makakalikasan_1, makabansa_1, makabansa_2) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissssssss", 
                $studentID, $quarter, $school_year,
                $values['makadiyos_1'], $values['makadiyos_2'],
                $values['makatao_1'], $values['makatao_2'],
                $values['makakalikasan_1'],
                $values['makabansa_1'], $values['makabansa_2']
            );
            $insert_stmt->execute();
        }
    }
    
    echo "<div class='alert alert-success'>Values updated successfully!</div>";
}

// Get students from teacher's section (initially empty until school year is selected)
$male_students_result = null;
$female_students_result = null;
if (isset($_GET['load_quarter']) && isset($_GET['school_year'])) {
    $load_quarter = $_GET['load_quarter'];
    $load_school_year = $_GET['school_year'];
    
    // Get male students
    $male_students_sql = "SELECT s.StudentID, s.FirstName, s.LastName 
                     FROM student s 
                     JOIN section_enrollment se ON s.StudentID = se.StudentID 
                     WHERE se.SectionID = ? AND se.SchoolYear = ? AND s.sex = 'Male'
                     ORDER BY s.LastName, s.FirstName";
    $male_students_stmt = $conn->prepare($male_students_sql);
    $male_students_stmt->bind_param("is", $sectionID, $load_school_year);
    $male_students_stmt->execute();
    $male_students_result = $male_students_stmt->get_result();
    
    // Get female students
    $female_students_sql = "SELECT s.StudentID, s.FirstName, s.LastName 
                     FROM student s 
                     JOIN section_enrollment se ON s.StudentID = se.StudentID 
                     WHERE se.SectionID = ? AND se.SchoolYear = ? AND s.sex = 'Female'
                     ORDER BY s.LastName, s.FirstName";
    $female_students_stmt = $conn->prepare($female_students_sql);
    $female_students_stmt->bind_param("is", $sectionID, $load_school_year);
    $female_students_stmt->execute();
    $female_students_result = $female_students_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Values Evaluation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }
        .values-select {
            min-width: 100px;
        }
        .reference-table {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #ddd;
        }
        .gender-section {
            margin-bottom: 30px;
        }
        .gender-header {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>
    <div class="container mt-4">
        <h2>Student Values Evaluation</h2>
        
        <!-- Reference Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="reference-table">
                    <h5>REPORT ON LEARNER'S OBSERVED VALUES</h5>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Core Values</th>
                                <th>Behavior Statements</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td rowspan="2">1. Maka-Diyos</td>
                                <td>Expresses one's spiritual beliefs while respecting the spiritual beliefs of others.</td>

                            </tr>
                            <tr>
                                <td>Shows adherence to ethical principles by upholding truth.</td>

                            </tr>
                            <tr>
                                <td rowspan="2">2. Makatao</td>
                                <td>Is sensitive to individual, social and cultural differences.</td>
  
                            </tr>
                            <tr>
                                <td>Demonstrates contributions toward solidarity.</td>

                            </tr>
                            <tr>
                                <td>3. Maka-kalikasan</td>
                                <td>Cares for the environment and utilizes resources wisely, judiciously, and economically.</td>

                            </tr>
                            <tr>
                                <td rowspan="2">4. Makabansa</td>
                                <td>Demonstrates pride in being a Filipino; exercises rights and responsibilities of a Filipino citizen.</td>

                            </tr>
                            <tr>
                                <td>Demonstrates appropriate behavior in carrying out activities in the school, community and country.</td>

                            </tr>
                        </tbody>
                    </table>
                    
                    <h6>Marking</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <p><span></span> <strong>AO</strong> - Always Observed</p>
                            <p><span></span> <strong>SO</strong> - Sometimes Observed</p>
                        </div>
                        <div class="col-md-3">
                            <p><span></span> <strong>RO</strong> - Rarely Observed</p>
                            <p><span></span> <strong>NO</strong> - Not Observed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="school_year" class="form-label">School Year:</label>
                    <select name="school_year" id="school_year" class="form-select" required>
                        <option value="">Select School Year</option>
                        <?php foreach ($school_years as $year): ?>
                            <option value="<?= $year ?>" <?= isset($_GET['school_year']) && $_GET['school_year'] == $year ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="quarter" class="form-label">Quarter:</label>
                    <select name="quarter" id="quarter" class="form-select" required>
                        <option value="1" <?= isset($_GET['load_quarter']) && $_GET['load_quarter'] == 1 ? 'selected' : '' ?>>Quarter 1</option>
                        <option value="2" <?= isset($_GET['load_quarter']) && $_GET['load_quarter'] == 2 ? 'selected' : '' ?>>Quarter 2</option>
                        <option value="3" <?= isset($_GET['load_quarter']) && $_GET['load_quarter'] == 3 ? 'selected' : '' ?>>Quarter 3</option>
                        <option value="4" <?= isset($_GET['load_quarter']) && $_GET['load_quarter'] == 4 ? 'selected' : '' ?>>Quarter 4</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary me-2" onclick="loadValues()">Load Values</button>
                    <button type="submit" name="submit_values" class="btn btn-primary">Save All Values</button>
                </div>
            </div>

            <?php if (($male_students_result && $male_students_result->num_rows > 0) || ($female_students_result && $female_students_result->num_rows > 0)): ?>
            
            <!-- Male Students Table -->
            <?php if ($male_students_result && $male_students_result->num_rows > 0): ?>
            <div class="gender-section">
                <div class="gender-header">
                    <h4>Male Students</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="text-center">
                            <tr>
                                <th>Student Name</th>
                                <th>Makadiyos<br><small>Expresses spiritual beliefs</small></th>
                                <th>Makadiyos<br><small>Upholds truth</small></th>
                                <th>Makatao<br><small>Sensitive to differences</small></th>
                                <th>Makatao<br><small>Demonstrates solidarity</small></th>
                                <th>Makakalikasan<br><small>Cares for environment</small></th>
                                <th>Makabansa<br><small>Pride in being Filipino</small></th>
                                <th>Makabansa<br><small>Appropriate behavior</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $male_students_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($student['LastName']) ?>, <?= htmlspecialchars($student['FirstName']) ?></strong>
                                    <input type="hidden" name="students[<?= $student['StudentID'] ?>][student_id]" value="<?= $student['StudentID'] ?>">
                                </td>
                                
                                <?php 
                                $value_types = ['makadiyos_1', 'makadiyos_2', 'makatao_1', 'makatao_2', 'makakalikasan_1', 'makabansa_1', 'makabansa_2'];
                                foreach ($value_types as $type): 
                                ?>
                                <td>
                                    <select name="students[<?= $student['StudentID'] ?>][<?= $type ?>]" class="form-select values-select" onchange="updateRowColor(this)">
                                        <option value="AO">AO</option>
                                        <option value="SO">SO</option>
                                        <option value="RO">RO</option>
                                        <option value="NO">NO</option>
                                    </select>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Female Students Table -->
            <?php if ($female_students_result && $female_students_result->num_rows > 0): ?>
            <div class="gender-section">
                <div class="gender-header">
                    <h4>Female Students</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="text-center">
                            <tr>
                                <th>Student Name</th>
                                <th>Makadiyos<br><small>Expresses spiritual beliefs</small></th>
                                <th>Makadiyos<br><small>Upholds truth</small></th>
                                <th>Makatao<br><small>Sensitive to differences</small></th>
                                <th>Makatao<br><small>Demonstrates solidarity</small></th>
                                <th>Makakalikasan<br><small>Cares for environment</small></th>
                                <th>Makabansa<br><small>Pride in being Filipino</small></th>
                                <th>Makabansa<br><small>Appropriate behavior</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $female_students_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($student['LastName']) ?>, <?= htmlspecialchars($student['FirstName']) ?></strong>
                                    <input type="hidden" name="students[<?= $student['StudentID'] ?>][student_id]" value="<?= $student['StudentID'] ?>">
                                </td>
                                
                                <?php 
                                $value_types = ['makadiyos_1', 'makadiyos_2', 'makatao_1', 'makatao_2', 'makakalikasan_1', 'makabansa_1', 'makabansa_2'];
                                foreach ($value_types as $type): 
                                ?>
                                <td>
                                    <select name="students[<?= $student['StudentID'] ?>][<?= $type ?>]" class="form-select values-select" onchange="updateRowColor(this)">
                                        <option value="AO">AO</option>
                                        <option value="SO">SO</option>
                                        <option value="RO">RO</option>
                                        <option value="NO">NO</option>
                                    </select>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php elseif (isset($_GET['load_quarter']) && isset($_GET['school_year'])): ?>
                <div class="alert alert-warning">No students found for the selected school year and section.</div>
            <?php else: ?>
                <div class="alert alert-info">Please select a school year and quarter, then click "Load Values" to start evaluating.</div>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadValues() {
            const school_year = document.getElementById('school_year').value;
            const quarter = document.getElementById('quarter').value;
            
            if (!school_year) {
                alert('Please select a school year first.');
                return;
            }
            
            // Create a temporary form to load values via GET
            const tempForm = document.createElement('form');
            tempForm.method = 'GET';
            tempForm.action = window.location.href.split('?')[0];
            
            const schoolYearInput = document.createElement('input');
            schoolYearInput.type = 'hidden';
            schoolYearInput.name = 'school_year';
            schoolYearInput.value = school_year;
            tempForm.appendChild(schoolYearInput);
            
            const quarterInput = document.createElement('input');
            quarterInput.type = 'hidden';
            quarterInput.name = 'load_quarter';
            quarterInput.value = quarter;
            tempForm.appendChild(quarterInput);
            
            document.body.appendChild(tempForm);
            tempForm.submit();
        }

        function updateRowColor(select) {
            const row = select.closest('tr');
            const selects = row.querySelectorAll('.values-select');
            let allAO = true;
            let allNO = true;
            
            selects.forEach(select => {
                if (select.value !== 'AO') allAO = false;
                if (select.value !== 'NO') allNO = false;
            });
            
            // Remove existing color classes
            row.classList.remove('table-success', 'table-warning', 'table-danger', 'table-secondary');
            
            // Add appropriate color class
            if (allAO) {
                row.classList.add('table-success');
            } else if (allNO) {
                row.classList.add('table-secondary');
            } else {
                // Check if any RO or mostly SO
                const values = Array.from(selects).map(s => s.value);
                const roCount = values.filter(v => v === 'RO').length;
                const soCount = values.filter(v => v === 'SO').length;
                
                if (roCount > 2) {
                    row.classList.add('table-danger');
                } else if (soCount > 2) {
                    row.classList.add('table-warning');
                }
            }
        }

        // Load existing values when page loads
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            if (isset($_GET['load_quarter']) && isset($_GET['school_year']) && ($male_students_result || $female_students_result)) {
                $load_quarter = $_GET['load_quarter'];
                $load_school_year = $_GET['school_year'];
                
                // Fetch existing values for the selected quarter and school year
                $values_sql = "SELECT * FROM student_values WHERE quarter = ? AND school_year = ?";
                $values_stmt = $conn->prepare($values_sql);
                $values_stmt->bind_param("is", $load_quarter, $load_school_year);
                $values_stmt->execute();
                $values_result = $values_stmt->get_result();
                
                $existing_values = [];
                while ($row = $values_result->fetch_assoc()) {
                    $existing_values[$row['student_id']] = $row;
                }
                
                foreach ($existing_values as $student_id => $values) {
                    foreach ($value_types as $type) {
                        if (isset($values[$type])) {
                            echo "const select_{$student_id}_{$type} = document.querySelector('select[name=\"students[{$student_id}][{$type}]\"]');";
                            echo "if (select_{$student_id}_{$type}) {";
                            echo "  select_{$student_id}_{$type}.value = '{$values[$type]}';";
                            echo "  updateRowColor(select_{$student_id}_{$type});";
                            echo "}";
                        }
                    }
                }
            }
            ?>
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>