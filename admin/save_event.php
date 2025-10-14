<?php
include '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'task';
    
    if (!empty($title) && !empty($date)) {
        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO events (title, event_date, description, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $date, $description, $category);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving event: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Title and date are required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>