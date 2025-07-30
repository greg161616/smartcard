<?php
// api/attendance.php
require 'config.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Handle different types of GET requests
    if (isset($_GET['section_id'])) {
        // Get attendance for specific section (existing functionality)
        $sec = (int) $_GET['section_id'];
        $dt = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $teacherFilter = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : null;

        if ($teacherFilter !== null) {
            $stmt = $conn->prepare("
                SELECT 
                    s.StudentID,
                    CONCAT_WS(' ', CONCAT(s.LastName, ',', s.FirstName), s.MiddleName) AS name,
                    COALESCE(a.Status, 'Absent') AS Status,
                    a.Date
                FROM section_enrollment e
                JOIN student s ON e.StudentID = s.StudentID
                LEFT JOIN attendance a 
                    ON a.StudentID = s.StudentID
                    AND a.SectionID = ?
                    AND a.Date = ?
                    AND a.TeacherID = ?
                WHERE e.SectionID = ?
                ORDER BY s.LastName, s.FirstName;
            ");
            $stmt->bind_param("isis", $sec, $dt, $teacherFilter, $sec);
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    s.StudentID,
                    CONCAT_WS(' ', CONCAT(s.LastName, ',', s.FirstName), s.MiddleName) AS name,
                    COALESCE(a.Status, 'Absent') AS Status,
                    a.Date,
                    sec.SectionName
                FROM section_enrollment e
                JOIN student s ON e.StudentID = s.StudentID
                JOIN section sec ON e.SectionID = sec.SectionID
                LEFT JOIN attendance a 
                    ON a.StudentID = s.StudentID
                    AND a.SectionID = ?
                    AND a.Date = ?
                WHERE e.SectionID = ?
                ORDER BY s.LastName, s.FirstName;
            ");
            $stmt->bind_param("isi", $sec, $dt, $sec);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $out = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($out);
        exit;

    } elseif (isset($_GET['teacher_id'])) {
        // New: Get all attendance records for a teacher
        $teacherId = (int) $_GET['teacher_id'];
        $dateFilter = isset($_GET['date']) ? $_GET['date'] : null;
        $sectionFilter = isset($_GET['section_id']) ? (int) $_GET['section_id'] : null;

        $query = "
            SELECT 
                a.Date,
                a.SectionID,
                sec.SectionName,
                s.StudentID,
                CONCAT_WS(' ', CONCAT(s.LastName, ',', s.FirstName), s.MiddleName) AS name,
                a.Status
            FROM attendance a
            JOIN student s ON a.StudentID = s.StudentID
            JOIN section sec ON a.SectionID = sec.SectionID
            WHERE a.TeacherID = ?
        ";

        $params = [$teacherId];
        $types = "i";

        if ($dateFilter) {
            $query .= " AND a.Date = ?";
            $params[] = $dateFilter;
            $types .= "s";
        }

        if ($sectionFilter) {
            $query .= " AND a.SectionID = ?";
            $params[] = $sectionFilter;
            $types .= "i";
        }

        $query .= " ORDER BY a.Date DESC, sec.SectionName, s.LastName, s.FirstName";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        
        // Group results by date and section
        $grouped = [];
        while ($row = $res->fetch_assoc()) {
            $date = $row['Date'];
            $sectionId = $row['SectionID'];
            
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            
            if (!isset($grouped[$date][$sectionId])) {
                $grouped[$date][$sectionId] = [
                    'section_name' => $row['SectionName'],
                    'students' => []
                ];
            }
            
            $grouped[$date][$sectionId]['students'][] = [
                'StudentID' => $row['StudentID'],
                'name' => $row['name'],
                'Status' => $row['Status']
            ];
        }

        // Convert to array format expected by frontend
        $output = [];
        foreach ($grouped as $date => $sections) {
            foreach ($sections as $sectionId => $sectionData) {
                $output[] = [
                    'date' => $date,
                    'section_id' => $sectionId,
                    'section_name' => $sectionData['section_name'],
                    'students' => $sectionData['students']
                ];
            }
        }

        echo json_encode($output);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Either section_id or teacher_id must be provided']);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (
        !isset(
            $data['section_id'],
            $data['attendance'],
            $data['teacher_id']
        )
    ) {
        http_response_code(400);
        echo json_encode([
            'error' => 'section_id, attendance array and teacher_id are required',
        ]);
        exit;
    }

    $sec = (int) $data['section_id'];
    $dt = isset($data['date']) ? $data['date'] : date('Y-m-d');
    $teacher = (int) $data['teacher_id'];
    $att = $data['attendance'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // delete this teacher's records for the date + section
        $del = $conn->prepare("
            DELETE FROM attendance
            WHERE SectionID = ?
            AND Date = ?
            AND TeacherID = ?
        ");
        $del->bind_param("isi", $sec, $dt, $teacher);
        $del->execute();

        // insert new records
        $ins = $conn->prepare("
            INSERT INTO attendance
                (StudentID, SectionID, Date, Status, TeacherID)
            VALUES (?,?,?,?,?)
        ");
        
        foreach ($att as $row) {
            $ins->bind_param(
                'iissi',
                $row['StudentID'],
                $sec,
                $dt,
                $row['Status'],
                $teacher
            );
            $ins->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);