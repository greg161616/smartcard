<?php
require 'config.php';

// Optional: require teacher_id to scope records
$teacherId = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : null;

// Build base query: count Present vs Absent per date & section
$sql = "
    SELECT
      a.Date,
      CONCAT(sec.GradeLevel, ' - ', sec.SectionName) AS subject,
      SUM(a.Status = 'Present' AND a.TeacherID = ?)    AS present,
      SUM(a.Status = 'Absent'  AND a.TeacherID = ?)    AS absent
    FROM attendance a
    JOIN section sec
      ON a.SectionID = sec.SectionID
    WHERE 1=1
";

// If you only want this teacher’s records, filter:
if ($teacherId !== null) {
    $sql .= " AND a.TeacherID = ? ";
    $params = [$teacherId, $teacherId, $teacherId];
    $types  = "iii";
} else {
    // No teacher filter: count all
    $sql = str_replace(
      ["AND a.TeacherID = ?", "AND a.TeacherID = ?"],
      "",
      $sql
    );
    $params = [0, 0];    // dummy for binding
    $types  = "ii";
}

$sql .= "
    GROUP BY a.Date, sec.SectionID
    ORDER BY a.Date DESC
";

$stmt = $conn->prepare($sql);

// Bind teacherId parameters if needed
if ($teacherId !== null) {
    $stmt->bind_param($types, ...$params);
} else {
    // still need to bind the two dummy ints
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$records = [];
while ($row = $res->fetch_assoc()) {
    $records[] = [
      'date'    => $row['Date'],
      'subject' => $row['subject'],
      'present' => (int)$row['present'],
      'absent'  => (int)$row['absent'],
    ];
}

header('Content-Type: application/json');
echo json_encode($records);
