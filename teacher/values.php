<?php
session_start();
require '../config.php';
date_default_timezone_set('Asia/Manila');
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

// AJAX: Load students and values
if (isset($_GET['ajax']) && $_GET['ajax'] == 'load_students') {
    $quarter = $_GET['quarter'];
    $school_year = $_GET['school_year'];
    
    // Get male students
    $male_students_sql = "SELECT s.StudentID, s.FirstName, s.LastName 
                     FROM student s 
                     JOIN section_enrollment se ON s.StudentID = se.StudentID 
                     WHERE se.SectionID = ? AND se.SchoolYear = ? AND s.sex = 'Male'
                     ORDER BY s.LastName, s.FirstName";
    $male_students_stmt = $conn->prepare($male_students_sql);
    $male_students_stmt->bind_param("is", $sectionID, $school_year);
    $male_students_stmt->execute();
    $male_students_result = $male_students_stmt->get_result();
    
    // Get female students
    $female_students_sql = "SELECT s.StudentID, s.FirstName, s.LastName 
                     FROM student s 
                     JOIN section_enrollment se ON s.StudentID = se.StudentID 
                     WHERE se.SectionID = ? AND se.SchoolYear = ? AND s.sex = 'Female'
                     ORDER BY s.LastName, s.FirstName";
    $female_students_stmt = $conn->prepare($female_students_sql);
    $female_students_stmt->bind_param("is", $sectionID, $school_year);
    $female_students_stmt->execute();
    $female_students_result = $female_students_stmt->get_result();
    
    // Get existing values
    $existing_values = [];
    if ($male_students_result->num_rows > 0 || $female_students_result->num_rows > 0) {
        $student_ids = [];
        while ($student = $male_students_result->fetch_assoc()) {
            $student_ids[] = $student['StudentID'];
        }
        mysqli_data_seek($male_students_result, 0);
        
        while ($student = $female_students_result->fetch_assoc()) {
            $student_ids[] = $student['StudentID'];
        }
        mysqli_data_seek($female_students_result, 0);
        
        if (!empty($student_ids)) {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            $values_sql = "SELECT * FROM student_values WHERE student_id IN ($placeholders) AND quarter = ? AND school_year = ?";
            $values_stmt = $conn->prepare($values_sql);
            
            // Bind parameters: student_ids + quarter + school_year
            $types = str_repeat('i', count($student_ids)) . 'is';
            $params = array_merge($student_ids, [$quarter, $school_year]);
            $values_stmt->bind_param($types, ...$params);
            $values_stmt->execute();
            $values_result = $values_stmt->get_result();
            
            while ($row = $values_result->fetch_assoc()) {
                $existing_values[$row['student_id']] = $row;
            }
        }
    }
    
    // Build response
    $response = [
        'success' => true,
        'male_students' => [],
        'female_students' => [],
        'existing_values' => $existing_values
    ];
    
    // Process male students
    while ($student = $male_students_result->fetch_assoc()) {
        $response['male_students'][] = $student;
    }
    
    // Process female students
    while ($student = $female_students_result->fetch_assoc()) {
        $response['female_students'][] = $student;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// AJAX: Save values
if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_values') {
    $quarter = $_POST['quarter'];
    $school_year = $_POST['school_year'];
    $students_data = json_decode($_POST['students'], true);
    
    $success = true;
    $errors = [];
    
    foreach ($students_data as $studentID => $values) {
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
            if (!$update_stmt->execute()) {
                $success = false;
                $errors[] = "Failed to update student ID $studentID: " . $update_stmt->error;
            }
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
            if (!$insert_stmt->execute()) {
                $success = false;
                $errors[] = "Failed to insert student ID $studentID: " . $insert_stmt->error;
            }
        }
    }
    
    // Check if this quarter is complete and move to next
    $next_quarter = null;
    if ($success) {
        // Count how many students have values for this quarter
        $count_sql = "SELECT COUNT(DISTINCT student_id) as count FROM student_values 
                     WHERE quarter = ? AND school_year = ?";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param("is", $quarter, $school_year);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        
        // Count total students in the section
        $total_sql = "SELECT COUNT(*) as total FROM section_enrollment 
                     WHERE SectionID = ? AND SchoolYear = ?";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("is", $sectionID, $school_year);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_row = $total_result->fetch_assoc();
        
        // If all students have values for this quarter, move to next quarter
        if ($count_row['count'] == $total_row['total'] && $quarter < 4) {
            $next_quarter = $quarter + 1;
        }
    }
    
    $response = [
        'success' => $success,
        'message' => $success ? 'Values saved successfully!' : 'Error saving values.',
        'errors' => $errors,
        'next_quarter' => $next_quarter
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Values Evaluation</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon">
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
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
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
        
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="school_year" class="form-label">School Year:</label>
                <select name="school_year" id="school_year" class="form-select" required>
                    <option value="">Select School Year</option>
                    <?php foreach ($school_years as $year): ?>
                        <option value="<?= $year ?>"><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="quarter" class="form-label">Quarter:</label>
                <select name="quarter" id="quarter" class="form-select" required>
                    <option value="1">Quarter 1</option>
                    <option value="2">Quarter 2</option>
                    <option value="3">Quarter 3</option>
                    <option value="4">Quarter 4</option>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="button" id="saveValues" class="btn btn-primary">Save All Values</button>
            </div>
        </div>

        <div id="studentsTables"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const schoolYearSelect = document.getElementById('school_year');
            const quarterSelect = document.getElementById('quarter');
            const saveBtn = document.getElementById('saveValues');
            const studentsTables = document.getElementById('studentsTables');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            let currentData = {
                male_students: [],
                female_students: [],
                existing_values: {}
            };
            
            // Load students when school year or quarter changes
            schoolYearSelect.addEventListener('change', loadStudents);
            quarterSelect.addEventListener('change', loadStudents);
            
            // Save button click
            saveBtn.addEventListener('click', saveValues);
            
            // Initialize with first school year if available
            if (schoolYearSelect.options.length > 1) {
                schoolYearSelect.value = schoolYearSelect.options[1].value;
                loadStudents();
            }
            
            async function loadStudents() {
                const school_year = schoolYearSelect.value;
                const quarter = quarterSelect.value;
                
                if (!school_year) {
                    studentsTables.innerHTML = '<div class="alert alert-info">Please select a school year to start evaluating.</div>';
                    return;
                }
                
                showLoading();
                
                try {
                    const response = await fetch(`?ajax=load_students&quarter=${quarter}&school_year=${encodeURIComponent(school_year)}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        currentData = data;
                        renderTables();
                    } else {
                        studentsTables.innerHTML = '<div class="alert alert-warning">No students found for the selected school year and quarter.</div>';
                    }
                } catch (error) {
                    console.error('Error loading students:', error);
                    studentsTables.innerHTML = '<div class="alert alert-danger">Error loading students. Please try again.</div>';
                } finally {
                    hideLoading();
                }
            }
            
            function renderTables() {
                let html = '';
                
                if (currentData.male_students.length === 0 && currentData.female_students.length === 0) {
                    html = '<div class="alert alert-warning">No students found for the selected school year and section.</div>';
                } else {
                    // Male Students Table
                    if (currentData.male_students.length > 0) {
                        html += `
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
                                    <tbody>`;
                        
                        currentData.male_students.forEach(student => {
                            html += renderStudentRow(student);
                        });
                        
                        html += `</tbody></table></div></div>`;
                    }
                    
                    // Female Students Table
                    if (currentData.female_students.length > 0) {
                        html += `
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
                                    <tbody>`;
                        
                        currentData.female_students.forEach(student => {
                            html += renderStudentRow(student);
                        });
                        
                        html += `</tbody></table></div></div>`;
                    }
                }
                
                studentsTables.innerHTML = html;
                
                // Attach event listeners to new selects
                document.querySelectorAll('.values-select').forEach(select => {
                    select.addEventListener('change', updateRowColor);
                });
            }
            
            function renderStudentRow(student) {
                const studentID = student.StudentID;
                const existingValues = currentData.existing_values[studentID] || {};
                const valueTypes = ['makadiyos_1', 'makadiyos_2', 'makatao_1', 'makatao_2', 'makakalikasan_1', 'makabansa_1', 'makabansa_2'];
                
                let html = `<tr>
                    <td>
                        <strong>${student.LastName}, ${student.FirstName}</strong>
                    </td>`;
                
                valueTypes.forEach(type => {
                    const value = existingValues[type] || 'AO';
                    html += `
                    <td>
                        <select name="${type}" data-student-id="${studentID}" class="form-select values-select" data-type="${type}">
                            <option value="AO" ${value === 'AO' ? 'selected' : ''}>AO</option>
                            <option value="SO" ${value === 'SO' ? 'selected' : ''}>SO</option>
                            <option value="RO" ${value === 'RO' ? 'selected' : ''}>RO</option>
                            <option value="NO" ${value === 'NO' ? 'selected' : ''}>NO</option>
                        </select>
                    </td>`;
                });
                
                html += '</tr>';
                return html;
            }
            
            async function saveValues() {
                const school_year = schoolYearSelect.value;
                const quarter = parseInt(quarterSelect.value);
                
                if (!school_year) {
                    alert('Error: Please select a school year.');
                    return;
                }
                
                // Collect all student data
                const studentsData = {};
                const selects = document.querySelectorAll('.values-select');
                
                selects.forEach(select => {
                    const studentID = select.getAttribute('data-student-id');
                    const type = select.getAttribute('data-type');
                    const value = select.value;
                    
                    if (!studentsData[studentID]) {
                        studentsData[studentID] = {};
                    }
                    
                    studentsData[studentID][type] = value;
                });
                
                if (Object.keys(studentsData).length === 0) {
                    alert('Info: No students to save.');
                    return;
                }
                
                showLoading();
                
                try {
                    const formData = new FormData();
                    formData.append('ajax', 'save_values');
                    formData.append('quarter', quarter);
                    formData.append('school_year', school_year);
                    formData.append('students', JSON.stringify(studentsData));
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Update existing values in currentData
                        Object.keys(studentsData).forEach(studentID => {
                            if (!currentData.existing_values[studentID]) {
                                currentData.existing_values[studentID] = {};
                            }
                            Object.assign(currentData.existing_values[studentID], studentsData[studentID]);
                        });
                        
                        if (result.next_quarter) {
                            // Move to next quarter automatically
                            alert('Values saved successfully! Moving to next quarter...');
                            quarterSelect.value = result.next_quarter;
                            loadStudents();
                        } else {
                            alert('Success: ' + result.message);
                        }
                    } else {
                        alert('Error: ' + result.message);
                        if (result.errors && result.errors.length > 0) {
                            console.error('Save errors:', result.errors);
                        }
                    }
                } catch (error) {
                    console.error('Error saving values:', error);
                    alert('Error: Error saving values. Please try again.');
                } finally {
                    hideLoading();
                }
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
            
            function showLoading() {
                loadingOverlay.style.display = 'flex';
            }
            
            function hideLoading() {
                loadingOverlay.style.display = 'none';
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>