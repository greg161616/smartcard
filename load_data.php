<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Get POST data
$quarter = $_POST['quarter'] ?? '';
$teacherId = $_POST['teacherId'] ?? '';
$studentId = $_POST['studentId'] ?? '';
$subjectId = $_POST['subjectId'] ?? 4; // Default to 4 (Science) if not provided
$schoolYear = $_POST['schoolYear'] ?? '2025-2026'; // Default school year

try {
    $response = [
        'status' => 'success',
        'max_scores' => [],
        'student_scores' => [],
        'summary_grades' => [],
        'subject_percentages' => []
    ];

    // Debug logging (you can remove this in production)
    error_log("Load Data Request - Quarter: $quarter, Teacher: $teacherId, Subject: $subjectId, SchoolYear: $schoolYear");

    // First, get subject percentages for the selected subject
    $subjectStmt = $conn->prepare("
        SELECT written_work_percentage, performance_task_percentage, quarterly_assessment_percentage, SubjectName 
        FROM subject 
        WHERE SubjectID = ?
    ");
    $subjectStmt->bind_param('i', $subjectId);
    $subjectStmt->execute();
    $subjectResult = $subjectStmt->get_result();
    
    if ($subjectResult->num_rows > 0) {
        $subjectData = $subjectResult->fetch_assoc();
        $response['subject_percentages'] = [
            'written_work' => $subjectData['written_work_percentage'],
            'performance_task' => $subjectData['performance_task_percentage'],
            'quarterly_assessment' => $subjectData['quarterly_assessment_percentage'],
            'subject_name' => $subjectData['SubjectName']
        ];
    } else {
        // Default percentages if subject not found
        $response['subject_percentages'] = [
            'written_work' => 0.30,
            'performance_task' => 0.50,
            'quarterly_assessment' => 0.20,
            'subject_name' => 'Science'
        ];
    }
    $subjectStmt->close();

    // Load max scores for the quarter - FIXED QUERY
    $maxStmt = $conn->prepare("
        SELECT ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10, 
               pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, 
               qa1 
        FROM highest_possible_score 
        WHERE teacherID = ? AND subjectID = ? AND quarter = ? AND school_year = ?
    ");
    
    if (!$maxStmt) {
        throw new Exception("Prepare failed for max scores: " . $conn->error);
    }
    
    $maxStmt->bind_param('iiss', $teacherId, $subjectId, $quarter, $schoolYear);
    $maxStmt->execute();
    $maxResult = $maxStmt->get_result();
    
    if ($maxResult && $maxResult->num_rows > 0) {
        $maxScores = $maxResult->fetch_assoc();
        // Convert null values to 0
        foreach ($maxScores as $key => $value) {
            if ($value === null) {
                $maxScores[$key] = 0;
            }
        }
        $response['max_scores'] = $maxScores;
    } else {
        // Initialize with zeros if no record exists
        $response['max_scores'] = array_fill_keys([
            'ww1','ww2','ww3','ww4','ww5','ww6','ww7','ww8','ww9','ww10',
            'pt1','pt2','pt3','pt4','pt5','pt6','pt7','pt8','pt9','pt10','qa1'
        ], 0);
    }
    
    if ($maxStmt) {
        $maxStmt->close();
    }

    // Load student scores for the quarter - FIXED QUERY
    $studentQuery = "
        SELECT gd.studentID, 
               gd.ww1, gd.ww2, gd.ww3, gd.ww4, gd.ww5, gd.ww6, gd.ww7, gd.ww8, gd.ww9, gd.ww10,
               gd.pt1, gd.pt2, gd.pt3, gd.pt4, gd.pt5, gd.pt6, gd.pt7, gd.pt8, gd.pt9, gd.pt10,
               gd.qa1,
               gd.ww_total, gd.ww_ps, gd.ww_ws,
               gd.pt_total, gd.pt_ps, gd.pt_ws,
               gd.qa_ps, gd.qa_ws,
               gd.initial_grade, gd.quarterly_grade,
               s.FirstName, s.LastName
        FROM grades_details gd
        INNER JOIN student s ON gd.studentID = s.StudentID
        WHERE gd.teacherID = ? AND gd.subjectID = ? AND gd.quarter = ? AND gd.school_year = ?
    ";
    
    if ($studentId) {
        $studentQuery .= " AND gd.studentID = ?";
    }
    
    $studentStmt = $conn->prepare($studentQuery);
    
    if (!$studentStmt) {
        throw new Exception("Prepare failed for student scores: " . $conn->error);
    }
    
    if ($studentId) {
        $studentStmt->bind_param('iissi', $teacherId, $subjectId, $quarter, $schoolYear, $studentId);
    } else {
        $studentStmt->bind_param('iiss', $teacherId, $subjectId, $quarter, $schoolYear);
    }
    
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    
    if ($studentResult && $studentResult->num_rows > 0) {
        while ($row = $studentResult->fetch_assoc()) {
            // Convert null values to empty strings for display
            foreach ($row as $key => $value) {
                if ($value === null) {
                    $row[$key] = '';
                }
            }
            $response['student_scores'][$row['studentID']] = $row;
        }
    } else {
        $response['student_scores'] = [];
    }
    
    if ($studentStmt) {
        $studentStmt->close();
    }

    // Load quarterly grades for summary table
    $summaryStmt = $conn->prepare("
        SELECT student_id, Q1, Q2, Q3, Q4, Final 
        FROM grades 
        WHERE uploadedby = ? AND subject = ?
    ");
    
    if ($summaryStmt) {
        $subjectName = $response['subject_percentages']['subject_name'];
        $summaryStmt->bind_param('is', $teacherId, $subjectName);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        
        while ($row = $summaryResult->fetch_assoc()) {
            $response['summary_grades'][$row['student_id']] = $row;
        }
        $summaryStmt->close();
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Database error in load_data.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage(),
        'max_scores' => [],
        'student_scores' => [],
        'summary_grades' => [],
        'subject_percentages' => []
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>