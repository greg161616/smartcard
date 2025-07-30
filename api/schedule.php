<?php
require 'config.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$student_id = intval($in['student_id'] ?? 0);

if (!$student_id) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error'   => 'student_id is required'
    ]);
    exit;
}

$sql = "
SELECT 
  subj.SubjectName   AS subject,
  s.day,
  s.time,
  CONCAT(t.fName, ' ', t.lName) AS teacher,
  CONCAT(sec.GradeLevel,' - ',sec.SectionName)    AS details,
  t.contact,
  u.Email            AS email
FROM section_enrollment e
JOIN sched     s   ON s.SectionID         = e.SectionID
JOIN subject   subj ON subj.SubjectID     = s.subjectID
JOIN teacher   t    ON t.TeacherID        = s.teacherID
JOIN `user`    u    ON u.UserID           = t.UserID
JOIN section   sec  ON sec.SectionID      = e.SectionID
WHERE e.StudentID = ?
ORDER BY 
  FIELD(s.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
  s.time;


";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $row['phone'] = '';
    $data[] = $row;
}

echo json_encode([
  'success' => true,
  'data'    => $data
]);
