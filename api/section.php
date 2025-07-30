<?php
require 'config.php';

// 1) grab the user_id from the query string
$userId = isset($_GET['user_id']) 
    ? intval($_GET['user_id']) 
    : null;

// 2) find the matching TeacherID
$teacherId = $userId;


// 3) if we have a teacherId, pull only their sections **and** subjects
if ($teacherId) {
    $stmt = $conn->prepare("
        SELECT DISTINCT 
               sec.SectionID,
               sec.GradeLevel,
               sec.SectionName,
               subj.SubjectName
          FROM section sec
    INNER JOIN sched s    ON s.SectionID      = sec.SectionID
    INNER JOIN subject subj ON subj.SubjectID   = s.subjectID
         WHERE s.teacherID     = ?
      ORDER BY sec.GradeLevel, sec.SectionName, subj.SubjectName
    ");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();

} else {
    // fallback: all sections, without subjects
    $result = $conn->query("
        SELECT 
          SectionID,
          GradeLevel,
          SectionName,
          '' AS SubjectName
        FROM section
        ORDER BY GradeLevel, SectionName
    ");
}

// 4) build JSON response
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

echo json_encode($sections);
