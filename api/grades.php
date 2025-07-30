<?php
require 'config.php';  // make sure this defines $conn as your mysqli connection

header('Content-Type: application/json');

$studentId = isset($_GET['studentId']) ? intval($_GET['studentId']) : null;
if (!$studentId) {
    http_response_code(400);
    echo json_encode(['error' => 'studentId required']);
    exit;
}

$sql = "
  SELECT
    g.grade_id,
    g.q1, g.q2, g.q3, g.q4, g.final,
    s.SubjectName,
    s.GradeLevel,
    sec.SectionName
  FROM grades AS g
  INNER JOIN subject AS s
    ON g.subject = s.SubjectID
  INNER JOIN student AS st
    ON st.StudentID = g.student_id
  INNER JOIN section AS sec
    ON sec.SectionID = st.SectionID
  WHERE g.student_id = ?
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($rows);
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
}
