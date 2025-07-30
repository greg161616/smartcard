<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require 'config.php'; // Ensure $conn (MySQLi) is defined

$sql = "SELECT id, title, date, content FROM announcement ORDER BY date DESC";
$result = $conn->query($sql);

$announcements = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

echo json_encode($announcements);
?>
