<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Updated thresholds as per requirements
$threshold_honor = 90;      // With Honors: 90-94
$threshold_high = 95;       // With High Honors: 95-97  
$threshold_highest = 98;    // With Highest Honors: 98-100

// Load filter options
$school_years = [];
$sy_q = mysqli_query($conn, "SELECT DISTINCT SchoolYear FROM section_enrollment ORDER BY SchoolYear DESC");
while ($r = mysqli_fetch_assoc($sy_q)) $school_years[] = $r['SchoolYear'];

$grade_levels = [];
$gl_q = mysqli_query($conn, "SELECT DISTINCT GradeLevel FROM section ORDER BY GradeLevel");
while ($r = mysqli_fetch_assoc($gl_q)) $grade_levels[] = $r['GradeLevel'];

$subjects = [];
// Fetch unique subject names to avoid repeating identical subject names in the dropdown.
// Use MIN(SubjectID) as a representative id for each subject name.
$sub_q = mysqli_query($conn, "SELECT SubjectName, MIN(SubjectID) AS SubjectID FROM subject GROUP BY SubjectName ORDER BY SubjectName");
while ($r = mysqli_fetch_assoc($sub_q)) $subjects[$r['SubjectID']] = $r['SubjectName'];

// Read selectors
$selected_sy = $_GET['school_year'] ?? ($school_years[0] ?? '');
$selected_grade = $_GET['grade_level'] ?? ($grade_levels[0] ?? '');
$selected_subject = $_GET['subject'] ?? '';
$report_type = $_GET['report_type'] ?? 'quarter';
$selected_quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : 1;

// Detect AJAX requests for dynamic filtering
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

$all_honor_students = [];
$total_subjects = 0;

if ($selected_sy && $selected_grade) {
    // Get sections for the selected grade level
    $sections = [];
    $sec_q = $conn->prepare("SELECT SectionID, SectionName FROM section WHERE GradeLevel = ?");
    $sec_q->bind_param('s', $selected_grade);
    $sec_q->execute();
    $sec_res = $sec_q->get_result();
    while ($section = $sec_res->fetch_assoc()) {
        $sections[$section['SectionID']] = $section['SectionName'];
    }

    if ($report_type === 'quarter') {
        // For quarter grades - using grades_details table
        if ($selected_subject) {
            // Filter by specific subject
            $sql = "SELECT 
                        st.StudentID, st.LRN, st.FirstName, st.LastName, st.Middlename,
                        se.SectionID, gd.quarter, s.SubjectName, gd.quarterly_grade as grade
                    FROM student st
                    JOIN section_enrollment se ON se.StudentID = st.StudentID AND se.SchoolYear = ?
                    JOIN grades_details gd ON gd.studentID = st.StudentID
                    JOIN subject s ON s.SubjectID = gd.subjectID
                    WHERE se.SchoolYear = ? 
                    AND gd.quarter = ?
                    AND gd.school_year = ?
                    AND gd.subjectID = ?
                    AND gd.quarterly_grade >= 90
                    ORDER BY gd.quarterly_grade DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssisi', $selected_sy, $selected_sy, $selected_quarter, $selected_sy, $selected_subject);
        } else {
            // All subjects - get average per student
            $sql = "SELECT 
                        st.StudentID, st.LRN, st.FirstName, st.LastName, st.Middlename,
                        se.SectionID, gd.quarter,
                        AVG(gd.quarterly_grade) as avg_grade, 
                        MIN(gd.quarterly_grade) as min_grade,
                        COUNT(gd.quarterly_grade) as subject_count
                    FROM student st
                    JOIN section_enrollment se ON se.StudentID = st.StudentID AND se.SchoolYear = ?
                    JOIN grades_details gd ON gd.studentID = st.StudentID
                    JOIN subject s ON s.SubjectID = gd.subjectID
                    JOIN section sec ON sec.SectionID = se.SectionID
                    WHERE se.SchoolYear = ? 
                    AND sec.GradeLevel = ?
                    AND gd.quarter = ?
                    AND gd.school_year = ?
                    GROUP BY st.StudentID, gd.quarter
                    HAVING min_grade >= 90";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssis', $selected_sy, $selected_sy, $selected_grade, $selected_quarter, $selected_sy);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if ($selected_subject) {
                $grade = round(floatval($row['grade']), 2);
                $avg = $grade; // For single subject, use the actual grade
            } else {
                $grade = round(floatval($row['avg_grade']), 2);
                $avg = $grade;
            }
            
            // Determine honor category based on new ranges
            $honor_category = '';
            $honor_class = '';
            if ($avg >= $threshold_highest) {
                $honor_category = 'With Highest Honors';
                $honor_class = 'highest-honor';
            } elseif ($avg >= $threshold_high) {
                $honor_category = 'With High Honors';
                $honor_class = 'high-honor';
            } elseif ($avg >= $threshold_honor) {
                $honor_category = 'With Honors';
                $honor_class = 'honor';
            }

            if ($honor_category) {
                $all_honor_students[] = [
                    'id' => $row['StudentID'],
                    'lrn' => $row['LRN'],
                    'name' => $row['LastName'] . ', ' . $row['FirstName'] . 
                             ($row['Middlename'] ? ' ' . substr($row['Middlename'], 0, 1) . '.' : ''),
                    'avg' => $avg,
                    'section_id' => $row['SectionID'],
                    'section_name' => $sections[$row['SectionID']] ?? 'N/A',
                    'quarter' => $row['quarter'],
                    'subject_name' => $selected_subject ? $row['SubjectName'] : 'All Subjects',
                    'honor_category' => $honor_category,
                    'honor_class' => $honor_class
                ];
            }
        }
    } else {
        // Final grades - using the grades table
        if ($selected_subject) {
            // Filter by specific subject
            $sql = "SELECT 
                        st.StudentID, st.LRN, st.FirstName, st.LastName, st.Middlename,
                        se.SectionID, s.SubjectName, g.Final as grade
                    FROM student st
                    JOIN section_enrollment se ON se.StudentID = st.StudentID AND se.SchoolYear = ?
                    JOIN grades g ON g.student_id = st.StudentID
                    JOIN subject s ON s.SubjectID = g.subject
                    JOIN section sec ON sec.SectionID = se.SectionID
                    WHERE se.SchoolYear = ? 
                    AND sec.GradeLevel = ?
                    AND g.subject = ?
                    AND g.uploaded = 1
                    AND g.Final >= 90
                    ORDER BY g.Final DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $selected_sy, $selected_sy, $selected_grade, $selected_subject);
        } else {
            // All subjects - get average per student
            $sql = "SELECT 
                        st.StudentID, st.LRN, st.FirstName, st.LastName, st.Middlename,
                        se.SectionID,
                        AVG(g.Final) as avg_final, 
                        MIN(g.Final) as min_final,
                        COUNT(g.Final) as subject_count
                    FROM student st
                    JOIN section_enrollment se ON se.StudentID = st.StudentID AND se.SchoolYear = ?
                    JOIN grades g ON g.student_id = st.StudentID
                    JOIN subject s ON s.SubjectID = g.subject
                    JOIN section sec ON sec.SectionID = se.SectionID
                    WHERE se.SchoolYear = ? 
                    AND sec.GradeLevel = ?
                    AND g.uploaded = 1
                    GROUP BY st.StudentID
                    HAVING min_final >= 90";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $selected_sy, $selected_sy, $selected_grade);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if ($selected_subject) {
                $grade = round(floatval($row['grade']), 2);
                $avg = $grade; // For single subject, use the actual grade
            } else {
                $grade = round(floatval($row['avg_final']), 2);
                $avg = $grade;
            }
            
            // Determine honor category based on new ranges
            $honor_category = '';
            $honor_class = '';
            if ($avg >= $threshold_highest) {
                $honor_category = 'With Highest Honors';
                $honor_class = 'highest-honor';
            } elseif ($avg >= $threshold_high) {
                $honor_category = 'With High Honors';
                $honor_class = 'high-honor';
            } elseif ($avg >= $threshold_honor) {
                $honor_category = 'With Honors';
                $honor_class = 'honor';
            }

            if ($honor_category) {
                $all_honor_students[] = [
                    'id' => $row['StudentID'],
                    'lrn' => $row['LRN'],
                    'name' => $row['LastName'] . ', ' . $row['FirstName'] . 
                             ($row['Middlename'] ? ' ' . substr($row['Middlename'], 0, 1) . '.' : ''),
                    'avg' => $avg,
                    'section_id' => $row['SectionID'],
                    'section_name' => $sections[$row['SectionID']] ?? 'N/A',
                    'quarter' => 'Final',
                    'subject_name' => $selected_subject ? $row['SubjectName'] : 'All Subjects',
                    'honor_category' => $honor_category,
                    'honor_class' => $honor_class
                ];
            }
        }
    }

    // Sort all honor students by average grade (descending)
    usort($all_honor_students, function($a, $b) {
        return $b['avg'] <=> $a['avg'];
    });
}

// Helper to render the results fragment (summary + table)
function render_results_fragment($all_honor_students, $selected_grade, $report_type, $selected_quarter, $selected_subject, $subjects, $selected_sy) {
    ob_start();
    ?>
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= count(array_filter($all_honor_students, fn($s) => $s['honor_category'] === 'With Highest Honors')) ?></h4>
                    <p class="card-text mb-0">With Highest Honors</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= count(array_filter($all_honor_students, fn($s) => $s['honor_category'] === 'With High Honors')) ?></h4>
                    <p class="card-text mb-0">With High Honors</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= count(array_filter($all_honor_students, fn($s) => $s['honor_category'] === 'With Honors')) ?></h4>
                    <p class="card-text mb-0"> With Honors</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h4 class="card-title"><?= count($all_honor_students) ?></h4>
                    <p class="card-text">Total Honor Students</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Single Honors Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">
                Honor Students - Grade <?= htmlspecialchars($selected_grade) ?> 
                (<?= $report_type === 'quarter' ? 'Quarter ' . $selected_quarter : 'Final Grade' ?>) 
                - <?= $selected_subject ? 'Subject: ' . htmlspecialchars($subjects[$selected_subject]) : 'All Subjects' ?>
                - SY: <?= htmlspecialchars($selected_sy) ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($all_honor_students)): ?>
                <div class="text-center py-4 text-muted">
                    <h6>No honor students found for the selected criteria</h6>
                    <p class="mb-0">Students need a grade of 90% or higher <?= $selected_subject ? 'in this subject' : 'in all subjects' ?>.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 honors-table">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Student Name</th>
                                <th width="15%">LRN</th>
                                <th width="15%">Section</th>
                                <?php if ($selected_subject): ?>
                                <th width="15%" class="text-center">Subject Grade</th>
                                <?php else: ?>
                                <th width="15%" class="text-center">Average Grade</th>
                                <?php endif; ?>
                                <th width="20%" class="text-center">Honor Category</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_honor_students as $index => $student): ?>
                            <tr>
                                <td class="fw-bold"><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['lrn']) ?></td>
                                <td><?= htmlspecialchars($student['section_name']) ?></td>
                                <td class="text-center fw-bold"><?= $student['avg'] ?></td>
                                <td class="text-center">
                                    <span class="honor-badge <?= $student['honor_class'] ?>">
                                        <?= htmlspecialchars($student['honor_category']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// If this is an AJAX request, return only the fragment and exit
if ($isAjax && $selected_sy && $selected_grade) {
    echo render_results_fragment($all_honor_students, $selected_grade, $report_type, $selected_quarter, $selected_subject, $subjects, $selected_sy);
    exit;
}

// For initial page load, prepare results HTML
$results_html = '';
if ($selected_sy && $selected_grade) {
    $results_html = render_results_fragment($all_honor_students, $selected_grade, $report_type, $selected_quarter, $selected_subject, $subjects, $selected_sy);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Honors Report</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .honors-table th, .honors-table td { vertical-align: middle; }
        .honor-badge { 
            font-weight: bold; 
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .highest-honor { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .high-honor { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .honor { background-color: #d1e7dd; color: #155724; border: 1px solid #c3e6cb; }
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,.075); }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include '../navs/adminNav.php'; ?>
    <div class="container mt-4">
        <h3>Academic Honors Report</h3>

        <form method="GET" class="row g-3 mb-4 p-3 border rounded" id="filterForm">
            <div class="col-md-2">
                <label class="form-label">School Year</label>
                <select name="school_year" class="form-select filter-input" required>
                    <option value="">Select School Year</option>
                    <?php foreach ($school_years as $sy): ?>
                        <option value="<?= htmlspecialchars($sy) ?>" <?= $sy === $selected_sy ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sy) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Grade Level</label>
                <select name="grade_level" class="form-select filter-input" required>
                    <option value="">Select Grade Level</option>
                    <?php foreach ($grade_levels as $gl): ?>
                        <option value="<?= htmlspecialchars($gl) ?>" <?= $gl === $selected_grade ? 'selected' : '' ?>>
                            Grade <?= htmlspecialchars($gl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select name="subject" class="form-select filter-input">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $selected_subject == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Report Type</label>
                <select name="report_type" class="form-select filter-input" id="report_type">
                    <option value="quarter" <?= $report_type==='quarter'?'selected':'' ?>>Quarter</option>
                    <option value="final" <?= $report_type==='final'?'selected':'' ?>>Final Grade</option>
                </select>
            </div>
            <div class="col-md-2" id="quarter_select" style="display: <?= $report_type==='quarter' ? 'block' : 'none' ?>;">
                <label class="form-label">Quarter</label>
                <select name="quarter" class="form-select filter-input">
                    <?php for ($q = 1; $q <= 4; $q++): ?>
                        <option value="<?= $q ?>" <?= $selected_quarter===$q?'selected':'' ?>>Q<?= $q ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <!-- Filter button removed: filtering will run automatically on selection -->
            <noscript>
                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary">Filter (JS disabled)</button>
                </div>
            </noscript>
        </form>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading honors data...</p>
        </div>

        <!-- Results container (populated by server or via AJAX) -->
        <div id="results">
            <?php
            if ($selected_sy && $selected_grade) {
                echo $results_html;
            }
            ?>
        </div>

        <?php if (!$selected_sy || !$selected_grade): ?>
            <div class="alert alert-info">Please select a school year and grade level to view honors.</div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle quarter select based on report type
        document.getElementById('report_type').addEventListener('change', function(e){
            var v = e.target.value;
            document.getElementById('quarter_select').style.display = v === 'quarter' ? 'block' : 'none';
        });
        
        // Show loading spinner
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('results').innerHTML = '';
        }
        
        // Hide loading spinner
        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        // Gather filter parameters
        function gatherFilters() {
            const form = document.querySelector('form');
            const params = new URLSearchParams();
            const sy = form.querySelector('select[name="school_year"]').value;
            const grade = form.querySelector('select[name="grade_level"]').value;
            const subject = form.querySelector('select[name="subject"]').value;
            const report_type = form.querySelector('select[name="report_type"]').value;
            const quarterEl = form.querySelector('select[name="quarter"]');
            const quarter = quarterEl ? quarterEl.value : '';
            
            if (sy) params.append('school_year', sy);
            if (grade) params.append('grade_level', grade);
            if (subject) params.append('subject', subject);
            if (report_type) params.append('report_type', report_type);
            if (report_type === 'quarter' && quarter) params.append('quarter', quarter);
            params.append('ajax', '1');
            return params.toString();
        }

        // Send AJAX request for filtering
        function sendAjaxFilter() {
            const sy = document.querySelector('select[name="school_year"]').value;
            const grade = document.querySelector('select[name="grade_level"]').value;
            
            // Only send request if both school year and grade level are selected
            if (!sy || !grade) {
                document.getElementById('results').innerHTML = '<div class="alert alert-info">Please select a school year and grade level to view honors.</div>';
                return;
            }
            
            showLoading();
            const qs = gatherFilters();
            const url = window.location.pathname + '?' + qs;
            
            fetch(url, { 
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                } 
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('results').innerHTML = html;
                hideLoading();
            })
            .catch(err => {
                console.error('Error:', err);
                document.getElementById('results').innerHTML = '<div class="alert alert-danger p-3">An error occurred while loading results.</div>';
                hideLoading();
            });
        }

        // Attach change listeners to all filter inputs
        document.querySelectorAll('.filter-input').forEach(el => {
            el.addEventListener('change', function() {
                // If report_type toggles, show/hide quarter select immediately
                if (this.name === 'report_type') {
                    document.getElementById('quarter_select').style.display = this.value === 'quarter' ? 'block' : 'none';
                }
                
                // Small delay to ensure all values are updated
                setTimeout(sendAjaxFilter, 100);
            });
        });

        // Trigger initial load if filters are already set
        document.addEventListener('DOMContentLoaded', function() {
            const sy = document.querySelector('select[name="school_year"]').value;
            const grade = document.querySelector('select[name="grade_level"]').value;
            
            if (sy && grade) {
                // Small delay to ensure page is fully loaded
                setTimeout(sendAjaxFilter, 300);
            }
        });
    </script>
</body>
</html>