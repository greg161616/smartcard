<?php
// profile.php
require 'config.php';  // must define $conn as your mysqli connection

header('Content-Type: application/json');

// read raw input for JSON body
$input = json_decode(file_get_contents('php://input'), true);
$studentId = isset($input['studentId'])
    ? intval($input['studentId'])
    : (isset($_GET['studentId']) ? intval($_GET['studentId']) : null);

if (!$studentId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'studentId is required'
    ]);
    exit;
}

// route by HTTP method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PUT') {
    // --- UPDATE profile ---
    // expected fields: email, address, phone, parentsContact
    $email          = isset($input['email'])          ? trim($input['email'])          : null;
    $address        = isset($input['address'])        ? trim($input['address'])        : null;
    $phone          = isset($input['phone'])          ? trim($input['phone'])          : null;
    $parentsContact = isset($input['parentsContact']) ? trim($input['parentsContact']) : null;

    // basic validation
    if (!$email || !$address || !$phone || !$parentsContact) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'email, address, phone and parentsContact are all required for update'
        ]);
        exit;
    }

    // update both user and student tables in one statement
    $sql = "
      UPDATE user AS u
      INNER JOIN student AS s ON u.UserID = s.userID
      SET
        u.Email            = ?,
        s.Address          = ?,
        s.contactNumber    = ?,
        s.ParentsContact   = ?
      WHERE s.StudentID = ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param(
            'ssssi',
            $email,
            $address,
            $phone,
            $parentsContact,
            $studentId
        );
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Update failed: ' . $stmt->error
            ]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => $conn->error
        ]);
    }
    exit;
}

// --- otherwise: FETCH profile ---
$sql = "
  SELECT
    u.UserID AS id,
    CONCAT(s.FirstName, ' ',
           IF(NULLIF(s.MiddleName, ''), CONCAT(s.MiddleName, ' '), ''),
           s.LastName) AS fullName,
    s.LRN AS lrn,
    sec.GradeLevel AS gradeLevel,
    sec.SectionName AS sectionName,
    DATE_FORMAT(s.Birthdate, '%Y-%m-%d') AS birthdate,
    s.Address AS address,
    u.Email AS email,
    s.contactNumber AS phone,
    s.ParentsContact AS parentsContact
  FROM student AS s
  INNER JOIN user AS u
    ON s.userID = u.UserID
  INNER JOIN section AS sec
    ON s.SectionID = sec.SectionID
  WHERE s.StudentID = ?
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'data'    => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error'   => 'No profile found for that studentId'
        ]);
    }

    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $conn->error
    ]);
}
