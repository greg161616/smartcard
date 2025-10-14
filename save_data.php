<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Get POST data
$action = $_POST['action'] ?? '';
$quarter = $_POST['quarter'] ?? '';
$type = $_POST['type'] ?? '';
$index = $_POST['index'] ?? '';
$value = $_POST['value'] ?? '';
$studentId = $_POST['studentId'] ?? '';
$teacherId = $_POST['teacherId'] ?? '';

// Get subject ID for Science
$subjectId = 4; // Consistent with load_data.php
$schoolYear = '2025-2026';

try {
    if ($action === 'save_max_score') {
        $column = $type . $index; // e.g., ww1, pt5, qa1
        
        // Check if record exists
        $checkStmt = $conn->prepare("SELECT id FROM highest_possible_score WHERE teacherID = ? AND subjectID = ? AND quarter = ? AND school_year = ?");
        $checkStmt->bind_param('iiss', $teacherId, $subjectId, $quarter, $schoolYear);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing record
            $updateStmt = $conn->prepare("UPDATE highest_possible_score SET $column = ?, uploaded = NOW() WHERE teacherID = ? AND subjectID = ? AND quarter = ? AND school_year = ?");
            $updateStmt->bind_param('diiss', $value, $teacherId, $subjectId, $quarter, $schoolYear);
            
            if ($updateStmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update max score: ' . $updateStmt->error]);
            }
            $updateStmt->close();
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO highest_possible_score (teacherID, subjectID, school_year, quarter, $column, uploaded) VALUES (?, ?, ?, ?, ?, NOW())");
            $insertStmt->bind_param('iissd', $teacherId, $subjectId, $schoolYear, $quarter, $value);
            
            if ($insertStmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to insert max score: ' . $insertStmt->error]);
            }
            $insertStmt->close();
        }
        $checkStmt->close();
        
    } elseif ($action === 'save_student_score') {
        $column = $type . $index; // e.g., ww1, pt5, qa1
        
        // Check if record exists in grades_details
        $checkStmt = $conn->prepare("SELECT gradeID FROM grades_details WHERE studentID = ? AND subjectID = ? AND quarter = ? AND school_year = ?");
        $checkStmt->bind_param('iiss', $studentId, $subjectId, $quarter, $schoolYear);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing record
            $updateStmt = $conn->prepare("UPDATE grades_details SET $column = ?, uploaded = NOW() WHERE studentID = ? AND subjectID = ? AND quarter = ? AND school_year = ?");
            $updateStmt->bind_param('diiss', $value, $studentId, $subjectId, $quarter, $schoolYear);
            
            if ($updateStmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update student score: ' . $updateStmt->error]);
            }
            $updateStmt->close();
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO grades_details (studentID, subjectID, teacherID, quarter, school_year, $column, uploaded) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insertStmt->bind_param('iiissd', $studentId, $subjectId, $teacherId, $quarter, $schoolYear, $value);
            
            if ($insertStmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to insert student score: ' . $insertStmt->error]);
            }
            $insertStmt->close();
        }
        $checkStmt->close();
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>