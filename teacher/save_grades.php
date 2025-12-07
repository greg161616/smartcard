<?php
session_start();
include '../config.php';
require_once __DIR__ . '/../api/log_helper.php'; // Add logging helper

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get teacher display name for logging
function getTeacherDisplayName($conn, $teacher_id) {
    if (empty($teacher_id)) return 'Unknown Teacher';
    try {
        $q = $conn->prepare("SELECT COALESCE(fName, '') AS fname, COALESCE(lName, '') AS lname FROM teacher WHERE TeacherID = ? LIMIT 1");
        if ($q) {
            $q->bind_param('i', $teacher_id);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                $q->close();
                $name = trim($row['fname'] . ' ' . $row['lname']);
                if ($name !== '') return $name . " (ID: $teacher_id)";
            }
            $q->close();
        }
    } catch (Exception $ex) {
        // ignore and fall back
    }
    return "Teacher #$teacher_id";
}

// Function to get subject name for logging
function getSubjectName($conn, $subject_id) {
    if (empty($subject_id)) return 'Unknown Subject';
    try {
        $q = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ? LIMIT 1");
        if ($q) {
            $q->bind_param('i', $subject_id);
            $q->execute();
            $r = $q->get_result();
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                $q->close();
                return $row['SubjectName'];
            }
            $q->close();
        }
    } catch (Exception $ex) {
        // ignore and fall back
    }
    return "Subject #$subject_id";
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

// Extract data
$teacherID = $data['teacherID'] ?? null;
$subjectID = $data['subjectID'] ?? null;
$schoolYear = $data['school_year'] ?? null;
$quarter = $data['quarter'] ?? null;
$highestScores = $data['highest_scores'] ?? [];
$grades = $data['grades'] ?? [];

// Validate required parameters
if (!$teacherID || !$subjectID || !$schoolYear || !$quarter) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => ''];

// Get teacher and subject names for logging
$teacher_name = getTeacherDisplayName($conn, $teacherID);
$subject_name = getSubjectName($conn, $subjectID);

try {
    // Start transaction
    $conn->begin_transaction();

    // Log the start of grade saving
    log_system_action($conn, 'Grade Save Started', $teacherID, [
        'teacher' => $teacher_name,
        'subject' => $subject_name,
        'quarter' => $quarter,
        'school_year' => $schoolYear,
        'students_count' => count($grades),
        'message' => "Starting to save grades for {$subject_name} - Quarter {$quarter}"
    ], 'info');

    // Validate teacherID exists to avoid foreign key errors when inserting summary grades
    $checkTeacher = $conn->prepare("SELECT TeacherID FROM teacher WHERE TeacherID = ?");
    if (!$checkTeacher) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $checkTeacher->bind_param('i', $teacherID);
    $checkTeacher->execute();
    $teacherRes = $checkTeacher->get_result();
    if ($teacherRes->num_rows === 0) {
        throw new Exception("Invalid teacherID: no matching teacher record found (TeacherID={$teacherID}).");
    }
    $checkTeacher->close();
    
    // 1. Save or update highest possible scores (without initial_grade and quarterly_grade)
    $checkHighestStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM highest_possible_score 
        WHERE teacherID = ? AND subjectID = ? AND quarter = ? AND school_year = ?
    ");
    if (!$checkHighestStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $checkHighestStmt->bind_param('iiis', $teacherID, $subjectID, $quarter, $schoolYear);
    $checkHighestStmt->execute();
    $result = $checkHighestStmt->get_result();
    $row = $result->fetch_assoc();
    $highestExists = $row['count'] > 0;
    $checkHighestStmt->close();

    // Convert weighted scores from percentage to decimal for database storage
    $ww_ws = floatval($highestScores['ww_ws']/100);
    $pt_ws = floatval($highestScores['pt_ws']/100);
    $qa_ws = floatval($highestScores['qa_ws']/100);

    if ($highestExists) {
        // Update existing highest scores
        $updateHighestStmt = $conn->prepare("
            UPDATE highest_possible_score SET 
                ww1 = ?, ww2 = ?, ww3 = ?, ww4 = ?, ww5 = ?, 
                ww6 = ?, ww7 = ?, ww8 = ?, ww9 = ?, ww10 = ?,
                pt1 = ?, pt2 = ?, pt3 = ?, pt4 = ?, pt5 = ?, 
                pt6 = ?, pt7 = ?, pt8 = ?, pt9 = ?, pt10 = ?,
                qa1 = ?,
                ww_total = ?, ww_ps = ?, ww_ws = ?,
                pt_total = ?, pt_ps = ?, pt_ws = ?,
                qa_ps = ?, qa_ws = ?,
                uploaded = NOW()
            WHERE teacherID = ? AND subjectID = ? AND quarter = ? AND school_year = ?
        ");
        
        if (!$updateHighestStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $updateHighestStmt->bind_param(
            'iiiiiiiiiiiiiiiiiiiiiddddddddiiis',
            $highestScores['ww1'], $highestScores['ww2'], $highestScores['ww3'], 
            $highestScores['ww4'], $highestScores['ww5'], $highestScores['ww6'], 
            $highestScores['ww7'], $highestScores['ww8'], $highestScores['ww9'], 
            $highestScores['ww10'], $highestScores['pt1'], $highestScores['pt2'], 
            $highestScores['pt3'], $highestScores['pt4'], $highestScores['pt5'], 
            $highestScores['pt6'], $highestScores['pt7'], $highestScores['pt8'], 
            $highestScores['pt9'], $highestScores['pt10'], $highestScores['qa1'],
            $highestScores['ww_total'], $highestScores['ww_ps'], $ww_ws,
            $highestScores['pt_total'], $highestScores['pt_ps'], $pt_ws,
            $highestScores['qa_ps'], $qa_ws,
            $teacherID, $subjectID, $quarter, $schoolYear
        );
        
        if (!$updateHighestStmt->execute()) {
            throw new Exception("Update failed: " . $updateHighestStmt->error);
        }
        $updateHighestStmt->close();
    } else {
        // Insert new highest scores
        $insertHighestSql = "
            INSERT INTO highest_possible_score 
            (teacherID, subjectID, quarter, school_year, 
             ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10,
             pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10, qa1,
             ww_total, ww_ps, ww_ws,
             pt_total, pt_ps, pt_ws,
             qa_ps, qa_ws, uploaded)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $insertHighestStmt = $conn->prepare($insertHighestSql);
        if (!$insertHighestStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Build params array in the exact order of placeholders
        $insertHighestParams = [
            $teacherID, $subjectID, $quarter, $schoolYear,
            $highestScores['ww1'], $highestScores['ww2'], $highestScores['ww3'],
            $highestScores['ww4'], $highestScores['ww5'], $highestScores['ww6'],
            $highestScores['ww7'], $highestScores['ww8'], $highestScores['ww9'],
            $highestScores['ww10'], $highestScores['pt1'], $highestScores['pt2'],
            $highestScores['pt3'], $highestScores['pt4'], $highestScores['pt5'],
            $highestScores['pt6'], $highestScores['pt7'], $highestScores['pt8'],
            $highestScores['pt9'], $highestScores['pt10'], $highestScores['qa1'],
            $highestScores['ww_total'], $highestScores['ww_ps'], $ww_ws,
            $highestScores['pt_total'], $highestScores['pt_ps'], $pt_ws,
            $highestScores['qa_ps'], $qa_ws
        ];

        // Validate placeholder count matches params count
        $placeholders = substr_count($insertHighestSql, '?');
        if ($placeholders !== count($insertHighestParams)) {
            throw new Exception("Placeholder count ($placeholders) does not match number of parameters (" . count($insertHighestParams) . ") for highest_possible_score INSERT");
        }

        // Build types string (same as original): 4 initial types + 22 ints + 7 doubles => 33 params
        $insertHighestTypes = 'iiis' . str_repeat('i', 22) . str_repeat('d', 7);

        // Prepare arguments for bind_param (needs references)
        $bindArgs = array_merge([$insertHighestTypes], $insertHighestParams);
        $refs = [];
        foreach ($bindArgs as $k => $v) {
            $refs[$k] = &$bindArgs[$k];
        }

        call_user_func_array([$insertHighestStmt, 'bind_param'], $refs);

        if (!$insertHighestStmt->execute()) {
            throw new Exception("Insert failed: " . $insertHighestStmt->error);
        }
        $insertHighestStmt->close();
    }
    
    // 2. Save or update student grades (with initial_grade and quarterly_grade)
    $students_processed = 0;
    $students_updated = 0;
    $students_inserted = 0;
    
    foreach ($grades as $grade) {
        $studentID = $grade['studentID'];
        
        // Check if grade already exists
        $checkGradeStmt = $conn->prepare("
            SELECT COUNT(*) as count FROM grades_details 
            WHERE teacherID = ? AND subjectID = ? AND studentID = ? AND quarter = ? AND school_year = ?
        ");
        
        if (!$checkGradeStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $checkGradeStmt->bind_param('iiisi', $teacherID, $subjectID, $studentID, $quarter, $schoolYear);
        $checkGradeStmt->execute();
        $result = $checkGradeStmt->get_result();
        $row = $result->fetch_assoc();
        $gradeExists = $row['count'] > 0;
        $checkGradeStmt->close();

        // Convert weighted scores from percentage to decimal for database storage
        $student_ww_ws = floatval($grade['ww_ws']);
        $student_pt_ws = floatval($grade['pt_ws']);
        $student_qa_ws = floatval($grade['qa_ws']);

        if ($gradeExists) {
            // Update existing grade
           $updateGradeStmt = $conn->prepare("
            UPDATE grades_details SET 
                ww1 = ?, ww2 = ?, ww3 = ?, ww4 = ?, ww5 = ?, 
                ww6 = ?, ww7 = ?, ww8 = ?, ww9 = ?, ww10 = ?,
                pt1 = ?, pt2 = ?, pt3 = ?, pt4 = ?, pt5 = ?, 
                pt6 = ?, pt7 = ?, pt8 = ?, pt9 = ?, pt10 = ?,
                qa1 = ?,
                ww_total = ?, ww_ps = ?, ww_ws = ?,
                pt_total = ?, pt_ps = ?, pt_ws = ?,
                qa_ps = ?, qa_ws = ?,
                initial_grade = ?, quarterly_grade = ?,
                uploaded = NOW()
            WHERE teacherID = ? AND subjectID = ? AND studentID = ? AND quarter = ? AND school_year = ?
        ");

if (!$updateGradeStmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}

            // Build the types string dynamically to ensure correct count
            // Order: 21 integers (ww1-ww10, pt1-pt10, qa1),
            // then 9 doubles (ww_total, ww_ps, ww_ws, pt_total, pt_ps, pt_ws, qa_ps, qa_ws, initial_grade),
            // then 5 integers (quarterly_grade, teacherID, subjectID, studentID, quarter) and one string (school_year)
            $updateTypes = str_repeat('i', 21) . str_repeat('d', 9) . str_repeat('i', 5) . 's';
            $updateGradeStmt->bind_param(
                $updateTypes,
                $grade['ww1'], $grade['ww2'], $grade['ww3'], $grade['ww4'], $grade['ww5'],
                $grade['ww6'], $grade['ww7'], $grade['ww8'], $grade['ww9'], $grade['ww10'],
                $grade['pt1'], $grade['pt2'], $grade['pt3'], $grade['pt4'], $grade['pt5'],
                $grade['pt6'], $grade['pt7'], $grade['pt8'], $grade['pt9'], $grade['pt10'],
                $grade['qa1'],
                $grade['ww_total'], $grade['ww_ps'], $student_ww_ws,
                $grade['pt_total'], $grade['pt_ps'], $student_pt_ws,
                $grade['qa_ps'], $student_qa_ws,
                $grade['initial_grade'], $grade['quarterly_grade'],
                $teacherID, $subjectID, $studentID, $quarter, $schoolYear
            );
            
            if (!$updateGradeStmt->execute()) {
                throw new Exception("Update failed: " . $updateGradeStmt->error);
            }
            $updateGradeStmt->close();
            $students_updated++;
        } else {
            // Insert new grade
            $insertGradeSql = "
                INSERT INTO grades_details 
                (teacherID, subjectID, studentID, quarter, school_year, 
                 ww1, ww2, ww3, ww4, ww5, ww6, ww7, ww8, ww9, ww10,
                 pt1, pt2, pt3, pt4, pt5, pt6, pt7, pt8, pt9, pt10,
                 qa1,
                 ww_total, ww_ps, ww_ws,
                 pt_total, pt_ps, pt_ws,
                 qa_ps, qa_ws,
                 initial_grade, quarterly_grade, uploaded)
                VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    NOW()
                )
            ";

            $insertGradeStmt = $conn->prepare($insertGradeSql);
            if (!$insertGradeStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $insertGradeParams = [
                $teacherID, $subjectID, $studentID, $quarter, $schoolYear,
                $grade['ww1'], $grade['ww2'], $grade['ww3'], $grade['ww4'], $grade['ww5'],
                $grade['ww6'], $grade['ww7'], $grade['ww8'], $grade['ww9'], $grade['ww10'],
                $grade['pt1'], $grade['pt2'], $grade['pt3'], $grade['pt4'], $grade['pt5'],
                $grade['pt6'], $grade['pt7'], $grade['pt8'], $grade['pt9'], $grade['pt10'],
                $grade['qa1'],
                $grade['ww_total'], $grade['ww_ps'], $student_ww_ws,
                $grade['pt_total'], $grade['pt_ps'], $student_pt_ws,
                $grade['qa_ps'], $student_qa_ws,
                $grade['initial_grade'], $grade['quarterly_grade']
            ];

            $placeholders = substr_count($insertGradeSql, '?');
            if ($placeholders !== count($insertGradeParams)) {
                throw new Exception("Placeholder count ($placeholders) does not match number of parameters (" . count($insertGradeParams) . ") for grades_details INSERT");
            }

            $insertTypes = 'iiiis' . str_repeat('i', 21) . str_repeat('d', 9) . 'i';
            $bindArgs = array_merge([$insertTypes], $insertGradeParams);
            $refs = [];
            foreach ($bindArgs as $k => $v) {
                $refs[$k] = &$bindArgs[$k];
            }
            call_user_func_array([$insertGradeStmt, 'bind_param'], $refs);

            if (!$insertGradeStmt->execute()) {
                throw new Exception("Insert failed: " . $insertGradeStmt->error);
            }
            $insertGradeStmt->close();
            $students_inserted++;
        }
        
        $students_processed++;
        
        // 3. Update summary grades table
        $quarterColumn = 'Q' . $quarter;
        
        // Check if summary exists
        $checkSummaryStmt = $conn->prepare("
            SELECT COUNT(*) as count FROM grades 
            WHERE student_id = ? AND subject = ? AND school_year = ?
        ");
        
        if (!$checkSummaryStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $checkSummaryStmt->bind_param('iis', $studentID, $subjectID, $schoolYear);
        $checkSummaryStmt->execute();
        $result = $checkSummaryStmt->get_result();
        $row = $result->fetch_assoc();
        $summaryExists = $row['count'] > 0;
        $checkSummaryStmt->close();
        
        if ($summaryExists) {
            // Update existing summary (also record who uploaded and when)
            $updateSummaryStmt = $conn->prepare("
                UPDATE grades SET $quarterColumn = ?, uploadedby = ?, uploaded = NOW() 
                WHERE student_id = ? AND subject = ? AND school_year = ?
            ");

            if (!$updateSummaryStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind: grade (int), uploadedby (int), studentID (int), subjectID (int), school_year (string)
            $updateSummaryStmt->bind_param('iiiis', $grade['quarterly_grade'], $teacherID, $studentID, $subjectID, $schoolYear);

            if (!$updateSummaryStmt->execute()) {
                throw new Exception("Update failed: " . $updateSummaryStmt->error);
            }
            $updateSummaryStmt->close();
        } else {
            // Insert new summary with uploadedby set to current teacher
            $insertSummaryStmt = $conn->prepare("
                INSERT INTO grades (student_id, subject, school_year, $quarterColumn, uploadedby, uploaded) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            if (!$insertSummaryStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // Bind: studentID (int), subjectID (int), school_year (string), grade (int), uploadedby (int)
            $insertSummaryStmt->bind_param('iisii', $studentID, $subjectID, $schoolYear, $grade['quarterly_grade'], $teacherID);

            if (!$insertSummaryStmt->execute()) {
                throw new Exception("Insert failed: " . $insertSummaryStmt->error);
            }
            $insertSummaryStmt->close();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'Grades saved successfully';
    
    // Log successful save
    log_system_action($conn, 'Grade Save Completed', $teacherID, [
        'teacher' => $teacher_name,
        'subject' => $subject_name,
        'quarter' => $quarter,
        'school_year' => $schoolYear,
        'students_processed' => $students_processed,
        'students_updated' => $students_updated,
        'students_inserted' => $students_inserted,
        'message' => "Successfully saved grades for {$subject_name} - Quarter {$quarter} ({$students_processed} students)"
    ], 'success');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    
    // Log error
    log_system_action($conn, 'Grade Save Error', $teacherID, [
        'teacher' => $teacher_name,
        'subject' => $subject_name,
        'quarter' => $quarter,
        'school_year' => $schoolYear,
        'error_message' => $e->getMessage(),
        'message' => "Failed to save grades for {$subject_name} - Quarter {$quarter}"
    ], 'error');
    
    error_log("Save grades error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
?>