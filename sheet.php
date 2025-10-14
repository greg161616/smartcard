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

// Get section info
$stmt = $conn->prepare("
    SELECT sec.GradeLevel, sec.SectionName 
    FROM section sec 
    WHERE sec.AdviserID = ?
");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$sectionResult = $stmt->get_result();
$section = $sectionResult->fetch_assoc();
$stmt->close();

// Get teacher name
$stmt = $conn->prepare("SELECT fName, lName FROM teacher WHERE TeacherID = ?");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$teacherResult = $stmt->get_result();
$teacher = $teacherResult->fetch_assoc();
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
        /* Your existing CSS styles remain mostly the same */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .school-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .quarter-selector {
            background-color: #f1f1f1;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quarter-selector label {
            font-weight: bold;
            margin: 0;
        }
        
        .quarter-selector select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 16px;
            min-width: 150px;
        }
        
        .tab-content {
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: center;
        }
        
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f1f1f1;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .subject-info {
            background-color: #e8f4fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .info-item {
            margin: 5px 0;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 15px;
            gap: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .color-box {
            width: 15px;
            height: 15px;
            margin-right: 5px;
        }
        
        .written-works {
            background-color: #e1f5fe;
        }
        
        .performance-tasks {
            background-color: #e8f5e9;
        }
        
        .quarterly-assessment {
            background-color: #fff3e0;
        }
        
        .final-grade {
            background-color: #fce4ec;
        }
        
        input[type="number"] {
            width: 50px;
            padding: 4px;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-align: center;
        }
        
        .max-score-input {
            width: 40px;
            font-size: 10px;
            margin-top: 3px;
        }
        
        .calculate-btn {
            background-color: #2a5298;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
            transition: background-color 0.3s;
        }
        
        .calculate-btn:hover {
            background-color: #1e3c72;
        }
        
        .grade-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #2a5298;
        }
        
        .max-score-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .max-score-label {
            font-size: 10px;
            color: #666;
        }
        
        .gender-header {
            text-align: left;
            background-color: #e0e0e0;
            font-weight: bold;
            padding: 5px;
        }
        
        .summary-table {
            margin-top: 20px;
        }
        
        .summary-table th {
            background-color: #2a5298;
            color: white;
        }
        
        .save-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 1000;
            display: none;
        }
        
        .save-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .save-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .input-error {
            border: 1px solid #dc3545;
            background-color: #f8d7da;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 12px;
            display: none;
        }
        
        @media (max-width: 768px) {
            .school-info {
                flex-direction: column;
            }
            
            .subject-info {
                flex-direction: column;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .quarter-selector {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- Quarter Selector -->
        <div class="quarter-selector">
            <label for="quarterSelect">Select Quarter:</label>
            <select id="quarterSelect" onchange="changeQuarter(this.value)">
                <option value="1">Quarter 1</option>
                <option value="2">Quarter 2</option>
                <option value="3">Quarter 3</option>
                <option value="4">Quarter 4</option>
                <option value="Summary">Summary</option>
            </select>
        </div>
        
        <!-- Dynamic Content Section -->
        <div class="tab-content">
            <div id="quarterInfo" class="subject-info">
                <!-- Will be populated dynamically -->
            </div>
            
            <div id="tableContent" class="table-container">
                <!-- Will be populated dynamically -->
            </div>
            
            <div id="legendContent" class="legend">
                <!-- Will be populated dynamically -->
            </div>
        </div>
    </div>

    <!-- Save Status Indicator -->
    <div id="saveStatus" class="save-status">
        <i class="fas fa-check-circle"></i> Data saved successfully
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Student data from PHP
        const students = <?php echo json_encode($students); ?>;
        const subjectName = "<?php echo htmlspecialchars($subjectName); ?>";
        const wwPercentage = <?php echo $wwPercentage; ?>;
        const ptPercentage = <?php echo $ptPercentage; ?>;
        const qaPercentage = <?php echo $qaPercentage; ?>;
        const wwWeight = <?php echo $ww; ?>;
        const ptWeight = <?php echo $pt; ?>;
        const qaWeight = <?php echo $qa; ?>;

        // Current quarter
        let currentQuarter = 'Q1';

        // Quarter names mapping
        const quarterNames = {
            'Q1': 'First Quarter',
            'Q2': 'Second Quarter',
            'Q3': 'Third Quarter',
            'Q4': 'Fourth Quarter'
        };

        // Debounce function to limit API calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Show save status
        function showSaveStatus(type, message = '') {
            const statusEl = document.getElementById('saveStatus');
            statusEl.className = 'save-status';
            
            if (type === 'success') {
                statusEl.classList.add('save-success');
                statusEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + (message || 'Data saved successfully');
            } else {
                statusEl.classList.add('save-error');
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (message || 'Error saving data');
            }
            
            statusEl.style.display = 'block';
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 3000);
        }

        // Load existing data from database
       // Load existing data from database
function loadExistingData(quarter) {
    console.log(`Loading data for quarter: ${quarter}`);
    
    $.ajax({
        url: 'load_data.php',
        type: 'POST',
        data: { 
            quarter: quarter, 
            teacherId: <?php echo $teacherId; ?>,
            subjectId: subjectId, // Add this
            schoolYear: schoolYear // Add this
        },
        success: function(response) {
            console.log(`Data loaded for ${quarter}:`, response);
            
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (data.status === 'success') {
                    populateMaxScores(quarter, data.max_scores);
                    populateStudentScores(quarter, data.student_scores);
                    if (quarter === 'Summary' && data.summary_grades) {
                        populateSummaryGrades(data.summary_grades);
                    }
                } else {
                    console.error(`Error in response for ${quarter}:`, data.message);
                    showSaveStatus('error', `Failed to load data: ${data.message}`);
                }
            } catch (e) {
                console.error(`JSON parse error for ${quarter}:`, e);
                showSaveStatus('error', 'Failed to parse response data');
            }
        },
        error: function(xhr, status, error) {
            console.error(`AJAX error loading ${quarter}:`, error);
            showSaveStatus('error', `Network error: ${error}`);
        }
    });
}
        // Populate max scores in the header
        function populateMaxScores(quarter, maxScores) {
            if (maxScores && Object.keys(maxScores).length > 0) {
                // Populate Written Works max scores
                for (let i = 1; i <= 10; i++) {
                    const wwKey = 'ww' + i;
                    const input = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${i}"]`);
                    if (input && maxScores[wwKey] !== null && maxScores[wwKey] !== undefined) {
                        input.value = maxScores[wwKey];
                    }
                }
                
                // Populate Performance Tasks max scores
                for (let i = 1; i <= 10; i++) {
                    const ptKey = 'pt' + i;
                    const input = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${i}"]`);
                    if (input && maxScores[ptKey] !== null && maxScores[ptKey] !== undefined) {
                        input.value = maxScores[ptKey];
                    }
                }
                
                // Populate Quarterly Assessment max score
                const qaInput = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
                if (qaInput && maxScores['qa1'] !== null && maxScores['qa1'] !== undefined) {
                    qaInput.value = maxScores['qa1'];
                }
                
                // Recalculate header totals
                calculateHeaderTotals(quarter);
            }
        }

        // Populate student scores
        function populateStudentScores(quarter, studentScores) {
            if (studentScores && Object.keys(studentScores).length > 0) {
                Object.keys(studentScores).forEach(studentId => {
                    const scores = studentScores[studentId];
                    
                    // Populate Written Works scores
                    for (let i = 1; i <= 10; i++) {
                        const wwKey = 'ww' + i;
                        const input = document.querySelector(`.ww-input[data-quarter="${quarter}"][data-student="${studentId}"][data-index="${i}"]`);
                        if (input && scores[wwKey] !== null && scores[wwKey] !== undefined) {
                            input.value = scores[wwKey];
                        }
                    }
                    
                    // Populate Performance Tasks scores
                    for (let i = 1; i <= 10; i++) {
                        const ptKey = 'pt' + i;
                        const input = document.querySelector(`.pt-input[data-quarter="${quarter}"][data-student="${studentId}"][data-index="${i}"]`);
                        if (input && scores[ptKey] !== null && scores[ptKey] !== undefined) {
                            input.value = scores[ptKey];
                        }
                    }
                    
                    // Populate Quarterly Assessment score
                    const qaInput = document.querySelector(`.qa-input[data-quarter="${quarter}"][data-student="${studentId}"]`);
                    if (qaInput && scores['qa1'] !== null && scores['qa1'] !== undefined) {
                        qaInput.value = scores['qa1'];
                    }
                    
                    // Recalculate grades for this student
                    calculateStudentGrades(quarter, studentId);
                });
            }
        }

        // Populate summary grades
        function populateSummaryGrades(summaryGrades) {
            if (summaryGrades && Object.keys(summaryGrades).length > 0) {
                Object.keys(summaryGrades).forEach(studentId => {
                    const grades = summaryGrades[studentId];
                    
                    const q1GradeEl = document.querySelector(`.q1-grade[data-student="${studentId}"]`);
                    const q2GradeEl = document.querySelector(`.q2-grade[data-student="${studentId}"]`);
                    const q3GradeEl = document.querySelector(`.q3-grade[data-student="${studentId}"]`);
                    const q4GradeEl = document.querySelector(`.q4-grade[data-student="${studentId}"]`);
                    const finalGradeEl = document.querySelector(`.final-grade[data-student="${studentId}"]`);
                    const remarksEl = document.querySelector(`.remarks[data-student="${studentId}"]`);
                    
                    if (q1GradeEl && grades['Q1'] !== null) q1GradeEl.textContent = grades['Q1'];
                    if (q2GradeEl && grades['Q2'] !== null) q2GradeEl.textContent = grades['Q2'];
                    if (q3GradeEl && grades['Q3'] !== null) q3GradeEl.textContent = grades['Q3'];
                    if (q4GradeEl && grades['Q4'] !== null) q4GradeEl.textContent = grades['Q4'];
                    if (finalGradeEl && grades['Final'] !== null) finalGradeEl.textContent = grades['Final'];
                    if (remarksEl && grades['Final'] !== null) remarksEl.textContent = getRemarks(parseFloat(grades['Final']));
                });
            }
        }

        // Save max score
        function saveMaxScore(quarter, type, index, value) {
            const data = {
                action: 'save_max_score',
                quarter: quarter,
                type: type,
                index: index,
                value: value,
                teacherId: <?php echo $teacherId; ?>
            };
            saveData(data);
        }

        // Save student score
        function saveStudentScore(quarter, studentId, type, index, value) {
            const data = {
                action: 'save_student_score',
                quarter: quarter,
                studentId: studentId,
                type: type,
                index: index,
                value: value,
                teacherId: <?php echo $teacherId; ?>
            };
            saveData(data);
        }

        // Auto-save data using AJAX
        const saveData = debounce(function(data) {
            $.ajax({
                url: 'save_data.php',
                type: 'POST',
                data: data,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.status === 'success') {
                        showSaveStatus('success');
                    } else {
                        showSaveStatus('error', result.message);
                    }
                },
                error: function() {
                    showSaveStatus('error', 'Network error. Please try again.');
                }
            });
        }, 1000);

        // Validate student score doesn't exceed max score
        function validateStudentScore(input) {
            const quarter = input.getAttribute('data-quarter');
            const type = input.getAttribute('data-type');
            const index = input.getAttribute('data-index');
            const studentId = input.getAttribute('data-student');
            
            // Get max score for this activity
            let maxInput;
            if (type === 'ww') {
                maxInput = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${index}"]`);
            } else if (type === 'pt') {
                maxInput = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${index}"]`);
            } else if (type === 'qa') {
                maxInput = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
            }
            
            const maxScore = maxInput ? parseInt(maxInput.value) : 0;
            const studentScore = parseInt(input.value);
            
            // Remove any existing error
            input.classList.remove('input-error');
            
            // Check if score exceeds max
            if (maxScore > 0 && studentScore > maxScore) {
                input.classList.add('input-error');
                showSaveStatus('error', `Score cannot exceed maximum value of ${maxScore}`);
                // Reset to max value
                input.value = maxScore;
                return false;
            }
            
            return true;
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial quarter (Q1)
            changeQuarter('Q1');
        });

        // Change quarter function
        function changeQuarter(quarter) {
            currentQuarter = quarter;
            
            if (quarter === 'Summary') {
                renderSummaryTable();
            } else {
                renderQuarterTable(quarter);
            }
            
            // Load existing data for the quarter
            loadExistingData(quarter);
        }

        // Render quarter table
        function renderQuarterTable(quarter) {
            const quarterInfo = document.getElementById('quarterInfo');
            const tableContent = document.getElementById('tableContent');
            const legendContent = document.getElementById('legendContent');
            
            // Update quarter info
            quarterInfo.innerHTML = `
                <div class="info-item"><strong>Quarter:</strong> ${quarterNames[quarter]}</div>
                <div class="info-item"><strong>Subject:</strong> ${subjectName}</div>
                <div class="info-item"><strong>Grade Level:</strong> Grade 7</div>
            `;
            
            // Render table
            tableContent.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Learner's Name</th>
                            <th colspan="10">Written Works (${wwPercentage}%)</th>
                            <th>Total</th>
                            <th>PS</th>
                            <th>WS</th>
                            <th colspan="10">Performance Tasks (${ptPercentage}%)</th>
                            <th>Total</th>
                            <th>PS</th>
                            <th>WS</th>
                            <th>QA (${qaPercentage}%)</th>
                            <th>PS</th>
                            <th>WS</th>
                            <th>Initial Grade</th>
                            <th>Quarterly Grade</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th>Highest possible score</th>
                            ${Array(10).fill().map((_, i) => `
                                <th>
                                    <div class="max-score-container">
                                        <span>${i+1}</span>
                                        <input type="number" class="max-score-input ww-max" data-quarter="${quarter}" data-index="${i+1}" value="0" min="0">
                                    </div>
                                </th>
                            `).join('')}
                            <th><span class="ww-header-total" data-quarter="${quarter}">0</span></th>
                            <th><span class="ww-header-ps" data-quarter="${quarter}">100.00</span></th>
                            <th><span class="ww-header-ws" data-quarter="${quarter}">0</span>%</th>
                            ${Array(10).fill().map((_, i) => `
                                <th>
                                    <div class="max-score-container">
                                        <span>${i+1}</span>
                                        <input type="number" class="max-score-input pt-max" data-quarter="${quarter}" data-index="${i+1}" value="0" min="0">
                                    </div>
                                </th>
                            `).join('')}
                            <th><span class="pt-header-total" data-quarter="${quarter}">0</span></th>
                            <th><span class="pt-header-ps" data-quarter="${quarter}">100.00</span></th>
                            <th><span class="pt-header-ws" data-quarter="${quarter}">0</span>%</th>
                            <th>
                                <div class="max-score-container">
                                    <span>QA</span>
                                    <input type="number" class="max-score-input qa-max" data-quarter="${quarter}" value="0" min="0">
                                </div>
                            </th>
                            <th><span class="qa-header-ps" data-quarter="${quarter}">100.00</span></th>
                            <th><span class="qa-header-ws" data-quarter="${quarter}">0</span>%</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="quarterDataBody">
                        ${renderStudentRows(quarter)}
                    </tbody>
                </table>
            `;
            
            // Render legend
            legendContent.innerHTML = `
                <div class="legend-item">
                    <div class="color-box written-works"></div>
                    <span>Written Works (${wwPercentage}%)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box performance-tasks"></div>
                    <span>Performance Tasks (${ptPercentage}%)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box quarterly-assessment"></div>
                    <span>Quarterly Assessment (${qaPercentage}%)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box final-grade"></div>
                    <span>Final Grade</span>
                </div>
            `;
            
            // Add event listeners
            addEventListenersToInputs(quarter);
            calculateHeaderTotals(quarter);
        }

        // Render student rows for quarter table
        function renderStudentRows(quarter) {
            let html = '';
            
            // Add male students
            html += `<tr><td colspan="35" class="gender-header">MALE</td></tr>`;
            students.filter(s => s.gender === 'Male').forEach(student => {
                html += createStudentRow(student, quarter);
            });

            // Add female students
            html += `<tr><td colspan="35" class="gender-header">FEMALE</td></tr>`;
            students.filter(s => s.gender === 'Female').forEach(student => {
                html += createStudentRow(student, quarter);
            });
            
            return html;
        }

        // Render summary table
        function renderSummaryTable() {
            const quarterInfo = document.getElementById('quarterInfo');
            const tableContent = document.getElementById('tableContent');
            const legendContent = document.getElementById('legendContent');
            
            // Update quarter info
            quarterInfo.innerHTML = `
                <div class="info-item"><strong>Summary of Grades</strong></div>
                <div class="info-item"><strong>Subject:</strong> ${subjectName}</div>
                <div class="info-item"><strong>Grade Level:</strong> Grade 7</div>
            `;
            
            // Render summary table
            tableContent.innerHTML = `
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Learner's Name</th>
                            <th>Q1 Grade</th>
                            <th>Q2 Grade</th>
                            <th>Q3 Grade</th>
                            <th>Q4 Grade</th>
                            <th>Final Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="summaryDataBody">
                        ${renderSummaryRows()}
                    </tbody>
                </table>
            `;
            
            // Hide legend for summary
            legendContent.innerHTML = '';
        }

        // Render summary rows
        function renderSummaryRows() {
            return students.map(student => `
                <tr id="summary-student-${student.id}">
                    <td>${student.id}</td>
                    <td>${student.name}</td>
                    <td class="q1-grade" data-student="${student.id}">0</td>
                    <td class="q2-grade" data-student="${student.id}">0</td>
                    <td class="q3-grade" data-student="${student.id}">0</td>
                    <td class="q4-grade" data-student="${student.id}">0</td>
                    <td class="final-grade" data-student="${student.id}">0</td>
                    <td class="remarks" data-student="${student.id}"></td>
                </tr>
            `).join('');
        }

        // Create a row for a student in the quarter table
        function createStudentRow(student, quarter) {
            return `
                <tr id="${quarter.toLowerCase()}-student-${student.id}">
                    <td>${student.id}</td>
                    <td>${student.name}</td>
                    <!-- Written Works inputs -->
                    ${Array(10).fill().map((_, index) => `
                        <td><input type="number" min="0" class="ww-input" data-quarter="${quarter}" data-student="${student.id}" data-type="ww" data-index="${index+1}"></td>
                    `).join('')}
                    <td class="ww-total" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <td class="ww-ps" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <td class="ww-ws" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <!-- Performance Tasks inputs -->
                    ${Array(10).fill().map((_, index) => `
                        <td><input type="number" min="0" class="pt-input" data-quarter="${quarter}" data-student="${student.id}" data-type="pt" data-index="${index+1}"></td>
                    `).join('')}
                    <td class="pt-total" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <td class="pt-ps" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <td class="pt-ws" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <!-- Quarterly Assessment -->
                    <td><input type="number" min="0" class="qa-input" data-quarter="${quarter}" data-student="${student.id}" data-type="qa"></td>
                    <td class="qa-ps" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <td class="qa-ws" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <!-- Final Grades -->
                    <td class="initial-grade" data-quarter="${quarter}" data-student="${student.id}">0</td>
                    <td class="quarterly-grade" data-quarter="${quarter}" data-student="${student.id}">0</td>
                </tr>
            `;
        }

        // Add event listeners to all input fields for dynamic calculation
        function addEventListenersToInputs(quarter) {
            // Event listeners for max score inputs
            document.querySelectorAll(`.ww-max[data-quarter="${quarter}"]`).forEach(input => {
                input.addEventListener('input', function() {
                    const index = this.getAttribute('data-index');
                    const value = this.value;
                    
                    // Save the max score
                    saveMaxScore(quarter, 'ww', index, value);
                    
                    calculateHeaderTotals(quarter);
                    calculateAllStudentGrades(quarter);
                });
            });
            
            document.querySelectorAll(`.pt-max[data-quarter="${quarter}"]`).forEach(input => {
                input.addEventListener('input', function() {
                    const index = this.getAttribute('data-index');
                    const value = this.value;
                    
                    // Save the max score
                    saveMaxScore(quarter, 'pt', index, value);
                    
                    calculateHeaderTotals(quarter);
                    calculateAllStudentGrades(quarter);
                });
            });
            
            document.querySelectorAll(`.qa-max[data-quarter="${quarter}"]`).forEach(input => {
                input.addEventListener('input', function() {
                    const value = this.value;
                    
                    // Save the max score
                    saveMaxScore(quarter, 'qa', 1, value);
                    
                    calculateHeaderTotals(quarter);
                    calculateAllStudentGrades(quarter);
                });
            });
            
            // Event listeners for student score inputs
            document.querySelectorAll(`.ww-input[data-quarter="${quarter}"]`).forEach(input => {
                input.addEventListener('input', function() {
                    // Validate the score
                    if (!validateStudentScore(this)) return;
                    
                    const studentId = this.getAttribute('data-student');
                    const index = this.getAttribute('data-index');
                    const value = this.value;
                    
                    // Save the student score
                    saveStudentScore(quarter, studentId, 'ww', index, value);
                    
                    calculateStudentGrades(quarter, studentId);
                });
            });
            
            document.querySelectorAll(`.pt-input[data-quarter="${quarter}"]`).forEach(input => {
                input.addEventListener('input', function() {
                    // Validate the score
                    if (!validateStudentScore(this)) return;
                    
                    const studentId = this.getAttribute('data-student');
                    const index = this.getAttribute('data-index');
                    const value = this.value;
                    
                    // Save the student score
                    saveStudentScore(quarter, studentId, 'pt', index, value);
                    
                    calculateStudentGrades(quarter, studentId);
                });
            });
            
            document.querySelectorAll(`.qa-input[data-quarter="${quarter}"]`).forEach(input => {
                input.addEventListener('input', function() {
                    // Validate the score
                    if (!validateStudentScore(this)) return;
                    
                    const studentId = this.getAttribute('data-student');
                    const value = this.value;
                    
                    // Save the student score
                    saveStudentScore(quarter, studentId, 'qa', 1, value);
                    
                    calculateStudentGrades(quarter, studentId);
                });
            });
        }

        // The rest of your functions (calculateHeaderTotals, calculateStudentGrades, etc.) remain the same
        // [Include all the remaining functions from your original code here]
        
        // Calculate header totals, PS, and SW for a specific quarter
        function calculateHeaderTotals(quarter) {
            // Calculate Written Works header totals
            let wwTotal = 0;
            for (let i = 1; i <= 10; i++) {
                const maxInput = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${i}"]`);
                if (maxInput && maxInput.value) {
                    wwTotal += parseFloat(maxInput.value);
                }
            }

            // PS is always 100.00 for Written Works
            const wwPs = 100.00;
            
            // SW is the percentage of written works (weighted score)
            const wwWs = Math.round(wwPs * wwWeight); // Header WS as integer

            // Update header display for Written Works
            updateHeaderDisplay(quarter, 'ww', wwTotal, wwPs, wwWs);

            // Calculate Performance Tasks header totals
            let ptTotal = 0;
            for (let i = 1; i <= 10; i++) {
                const maxInput = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${i}"]`);
                if (maxInput && maxInput.value) {
                    ptTotal += parseFloat(maxInput.value);
                }
            }

            // PS is always 100.00 for Performance Tasks
            const ptPs = 100.00;
            
            // SW is the percentage of performance tasks (weighted score)
            const ptWs = Math.round(ptPs * ptWeight); // Header WS as integer

            // Update header display for Performance Tasks
            updateHeaderDisplay(quarter, 'pt', ptTotal, ptPs, ptWs);

            // Calculate Quarterly Assessment header totals
            const qaMaxInput = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
            const qaTotal = qaMaxInput && qaMaxInput.value ? parseFloat(qaMaxInput.value) : 0;

            // PS is always 100.00 for Quarterly Assessment
            const qaPs = 100.00;
            const qaWs = Math.round(qaPs * qaWeight); // Header WS as integer

            // Update header display for Quarterly Assessment
            updateHeaderDisplay(quarter, 'qa', qaTotal, qaPs, qaWs);
        }

        // Update header display with calculated values
        function updateHeaderDisplay(quarter, type, total, ps, ws) {
            if (type === 'ww') {
                // Update Written Works header values
                const wwTotalElement = document.querySelector(`.ww-header-total[data-quarter="${quarter}"]`);
                const wwPsElement = document.querySelector(`.ww-header-ps[data-quarter="${quarter}"]`);
                const wwWsElement = document.querySelector(`.ww-header-ws[data-quarter="${quarter}"]`);
                
                if (wwTotalElement) wwTotalElement.textContent = total.toFixed(0);
                if (wwPsElement) wwPsElement.textContent = ps.toFixed(2);
                // Header WS should be an integer (no decimals)
                if (wwWsElement) wwWsElement.textContent = ws;
            } else if (type === 'pt') {
                // Update Performance Tasks header values
                const ptTotalElement = document.querySelector(`.pt-header-total[data-quarter="${quarter}"]`);
                const ptPsElement = document.querySelector(`.pt-header-ps[data-quarter="${quarter}"]`);
                const ptWsElement = document.querySelector(`.pt-header-ws[data-quarter="${quarter}"]`);
                
                if (ptTotalElement) ptTotalElement.textContent = total.toFixed(0);
                if (ptPsElement) ptPsElement.textContent = ps.toFixed(2);
                // Header WS should be an integer (no decimals)
                if (ptWsElement) ptWsElement.textContent = ws;
            } else if (type === 'qa') {
                // Update Quarterly Assessment header values
                const qaPsElement = document.querySelector(`.qa-header-ps[data-quarter="${quarter}"]`);
                const qaWsElement = document.querySelector(`.qa-header-ws[data-quarter="${quarter}"]`);
                
                if (qaPsElement) qaPsElement.textContent = ps.toFixed(2);
                // Header WS should be an integer (no decimals)
                if (qaWsElement) qaWsElement.textContent = ws;
            }
        }

        // Calculate grades for all students in a quarter
        function calculateAllStudentGrades(quarter) {
            students.forEach(student => {
                calculateStudentGrades(quarter, student.id);
            });
        }

        // Calculate grades for a specific student in a quarter
        function calculateStudentGrades(quarter, studentId) {
            // Calculate Written Works
            let wwTotal = 0;
            let wwMaxTotal = 0;
            for (let i = 1; i <= 10; i++) {
                const input = document.querySelector(`.ww-input[data-quarter="${quarter}"][data-student="${studentId}"][data-index="${i}"]`);
                const maxInput = document.querySelector(`.ww-max[data-quarter="${quarter}"][data-index="${i}"]`);
                if (input && input.value) {
                    wwTotal += parseFloat(input.value);
                }
                if (maxInput && maxInput.value) {
                    wwMaxTotal += parseFloat(maxInput.value);
                }
            }

            const wwPs = wwMaxTotal > 0 ? (wwTotal / wwMaxTotal) * 100 : 0;
            
            const wwWs = wwPs * wwWeight; // Student WS as decimal

            // Update WW values in table
            document.querySelector(`.ww-total[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = Math.round(wwTotal); // Whole number
            document.querySelector(`.ww-ps[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = wwPs.toFixed(2);
            document.querySelector(`.ww-ws[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = wwWs.toFixed(2); // Student WS with decimals

            // Calculate Performance Tasks
            let ptTotal = 0;
            let ptMaxTotal = 0;
            for (let i = 1; i <= 10; i++) {
                const input = document.querySelector(`.pt-input[data-quarter="${quarter}"][data-student="${studentId}"][data-index="${i}"]`);
                const maxInput = document.querySelector(`.pt-max[data-quarter="${quarter}"][data-index="${i}"]`);
                if (input && input.value) {
                    ptTotal += parseFloat(input.value);
                }
                if (maxInput && maxInput.value) {
                    ptMaxTotal += parseFloat(maxInput.value);
                }
            }

            const ptPs = ptMaxTotal > 0 ? (ptTotal / ptMaxTotal) * 100 : 0;
            
            const ptWs = ptPs * ptWeight; // Student WS as decimal

            // Update PT values in table
            document.querySelector(`.pt-total[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = Math.round(ptTotal); // Whole number
            document.querySelector(`.pt-ps[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = ptPs.toFixed(2);
            document.querySelector(`.pt-ws[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = ptWs.toFixed(2); // Student WS with decimals

            // Calculate Quarterly Assessment
            const qaInput = document.querySelector(`.qa-input[data-quarter="${quarter}"][data-student="${studentId}"]`);
            const qaMaxInput = document.querySelector(`.qa-max[data-quarter="${quarter}"]`);
            const qaValue = qaInput && qaInput.value ? parseFloat(qaInput.value) : 0;
            const qaMax = qaMaxInput && qaMaxInput.value ? parseFloat(qaMaxInput.value) : 100;
            const qaPs = (qaValue / qaMax) * 100;
            const qaWs = qaPs * qaWeight; // Student WS as decimal

            // Update QA values in table
            document.querySelector(`.qa-ps[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = qaPs.toFixed(2);
            document.querySelector(`.qa-ws[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = qaWs.toFixed(2); // Student WS with decimals

            // Calculate Initial Grade and Quarterly Grade
            const initialGrade = wwWs + ptWs + qaWs;
            const quarterlyGrade = transmuteGrade(initialGrade);

            // Update grade values in table
            document.querySelector(`.initial-grade[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = initialGrade.toFixed(2);
            document.querySelector(`.quarterly-grade[data-quarter="${quarter}"][data-student="${studentId}"]`).textContent = quarterlyGrade;

            // Update summary table
            updateSummaryTable(studentId, quarter, quarterlyGrade);
        }

        // Update summary table with quarterly grades
        function updateSummaryTable(studentId, quarter, grade) {
            const gradeElement = document.querySelector(`.${quarter.toLowerCase()}-grade[data-student="${studentId}"]`);
            if (gradeElement) {
                gradeElement.textContent = grade;

                // Calculate final grade
                const q1Grade = parseFloat(document.querySelector(`.q1-grade[data-student="${studentId}"]`).textContent) || 0;
                const q2Grade = parseFloat(document.querySelector(`.q2-grade[data-student="${studentId}"]`).textContent) || 0;
                const q3Grade = parseFloat(document.querySelector(`.q3-grade[data-student="${studentId}"]`).textContent) || 0;
                const q4Grade = parseFloat(document.querySelector(`.q4-grade[data-student="${studentId}"]`).textContent) || 0;

                // Count quarters with grades
                const quartersWithGrades = [q1Grade, q2Grade, q3Grade, q4Grade].filter(g => g > 0).length;

                // Calculate final grade
                const finalGrade = quartersWithGrades > 0 ?
                    (q1Grade + q2Grade + q3Grade + q4Grade) / quartersWithGrades : 0;

                // Update final grade and remarks
                document.querySelector(`.final-grade[data-student="${studentId}"]`).textContent = finalGrade.toFixed(2);
                document.querySelector(`.remarks[data-student="${studentId}"]`).textContent = getRemarks(finalGrade);
            }
        }

        // Get remarks based on grade
        function getRemarks(grade) {
            if (grade >= 90) return "Outstanding";
            if (grade >= 85) return "Very Satisfactory";
            if (grade >= 80) return "Satisfactory";
            if (grade >= 75) return "Fairly Satisfactory";
            return "Did Not Meet Expectations";
        }

        // Transmute initial grade to quarterly grade using the formula: ((initial grade-60)/1.6 )+75
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