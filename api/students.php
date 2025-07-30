<?php
// ──────────────────────────────────────────────────────────────────────────────
//  CORS + JSON headers
// ──────────────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // CORS preflight
    http_response_code(200);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
//  Error reporting (DEV ONLY — remove or disable in production)
// ──────────────────────────────────────────────────────────────────────────────
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ──────────────────────────────────────────────────────────────────────────────
//  Bootstrap
// ──────────────────────────────────────────────────────────────────────────────
require 'config.php';

if (isset($conn) && $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// Route request
// ──────────────────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (!empty($_GET['id'])) {
            getStudent($conn, (int) $_GET['id']);
        } else {
            $search = $_GET['search'] ?? '';
            getStudents($conn, $search);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        createStudent($conn, $data);
        break;

    case 'PUT':
        parse_str(file_get_contents('php://input'), $data);
        updateStudent($conn, $data ?? []);
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $data);
        deleteStudent($conn, (int) ($data['id'] ?? 0));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        break;
}

// ──────────────────────────────────────────────────────────────────────────────
// Controller functions
// ──────────────────────────────────────────────────────────────────────────────

function getStudents($conn, string $search)
{
    if ($search !== '') {
        $like = "%{$conn->real_escape_string($search)}%";
        $sql  = "SELECT s.StudentID, s.LRN,
                        s.FirstName, s.MiddleName, s.LastName,
                        s.Gender, s.Birthdate, s.Address,
                        s.contactNumber, s.ParentsContact,
                        sec.SectionID, sec.GradeLevel, sec.SectionName
                   FROM student s
              LEFT JOIN section sec ON s.SectionID = sec.SectionID
                  WHERE CONCAT(s.FirstName,' ',s.LastName) LIKE ?
               ORDER BY s.LastName, s.FirstName";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $like);
    } else {
        $sql  = "SELECT s.StudentID, s.LRN,
                        s.FirstName, s.MiddleName, s.LastName,
                        s.Gender, s.Birthdate, s.Address,
                        s.contactNumber, s.ParentsContact,
                        sec.SectionID, sec.GradeLevel, sec.SectionName
                   FROM student s
              LEFT JOIN section sec ON s.SectionID = sec.SectionID
                ORDER BY s.LastName, s.FirstName";
        $stmt = $conn->prepare($sql);
    }

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        return;
    }

    $stmt->execute();
    $result   = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($students);
    $stmt->close();
}

function getStudent($conn, int $id)
{
    $sql  = "SELECT s.*, sec.GradeLevel, sec.SectionName
               FROM student s
          LEFT JOIN section sec ON s.SectionID = sec.SectionID
              WHERE s.StudentID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        return;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($student = $res->fetch_assoc()) {
        echo json_encode($student);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
    }
    $stmt->close();
}

function createStudent(mysqli $conn, array $data)
{
    $sql = "INSERT INTO `student`
               (`userID`, `LRN`, `FirstName`, `MiddleName`, `LastName`,
                `Gender`, `Birthdate`, `Address`, `contactNumber`,
                `ParentsContact`, `SectionID`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        return;
    }

    // Coerce and default
    $userID         = isset($data['userID'])        ? (int)$data['userID']         : 0;
    $lRN            = $data['LRN']                  ?? '';
    $firstName      = $data['FirstName']            ?? '';
    $middleName     = $data['MiddleName']           ?? '';
    $lastName       = $data['LastName']             ?? '';
    $gender         = $data['Gender']               ?? '';
    $birthdate      = $data['Birthdate']            ?? '';
    $address        = $data['Address']              ?? '';
    $contactNumber  = $data['contactNumber']        ?? '';
    $parentsContact = $data['ParentsContact']       ?? '';
    $sectionID      = isset($data['SectionID'])     ? (int)$data['SectionID']      : 0;

    // 11 params: i (userID), s×9 (all strings), i (SectionID)
    $stmt->bind_param(
        'isssssssssi',
        $userID,
        $lRN,
        $firstName,
        $middleName,
        $lastName,
        $gender,
        $birthdate,
        $address,
        $contactNumber,
        $parentsContact,
        $sectionID
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Student created',
            'id'      => $conn->insert_id
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create student: ' . $stmt->error]);
    }

    $stmt->close();
}


function updateStudent($conn, array $data)
{
    if (empty($data['StudentID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'StudentID is required']);
        return;
    }

    $sql  = "UPDATE student SET
               LRN            = ?,
               FirstName      = ?,
               MiddleName     = ?,
               LastName       = ?,
               Gender         = ?,
               Birthdate      = ?,
               Address        = ?,
               contactNumber  = ?,
               ParentsContact = ?,
               SectionID      = ?
             WHERE StudentID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        return;
    }

    $stmt->bind_param(
        'sssssssssii',
        $data['LRN'] ?? '',
        $data['FirstName'] ?? '',
        $data['MiddleName'] ?? '',
        $data['LastName'] ?? '',
        $data['Gender'] ?? '',
        $data['Birthdate'] ?? '',
        $data['Address'] ?? '',
        $data['contactNumber'] ?? '',
        $data['ParentsContact'] ?? '',
        $data['SectionID'] ?? 0,
        $data['StudentID']
    );

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Student updated']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to update student: ' . $stmt->error]);
    }
    $stmt->close();
}

function deleteStudent($conn, int $id)
{
    $stmt = $conn->prepare("DELETE FROM student WHERE StudentID = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        return;
    }

    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Student deleted']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to delete student: ' . $stmt->error]);
    }
    $stmt->close();
}

// close connection
$conn->close();
