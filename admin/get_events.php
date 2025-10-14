<?php
include '../config.php';

header('Content-Type: application/json');

// Check if we're getting events for a specific date or for a whole month
if (isset($_GET['date'])) {
    $date = $_GET['date'];
    $sql = "SELECT * FROM events WHERE event_date = '$date' ORDER BY created_at DESC";
} elseif (isset($_GET['month']) && isset($_GET['year'])) {
    $month = $_GET['month'];
    $year = $_GET['year'];
    $sql = "SELECT * FROM events WHERE MONTH(event_date) = '$month' AND YEAR(event_date) = '$year' ORDER BY event_date ASC";
} else {
    echo json_encode([]);
    exit;
}

$result = $conn->query($sql);

$events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

echo json_encode($events);

$conn->close();
?>