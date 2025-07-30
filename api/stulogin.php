<?php
require 'config.php';  

// parse JSON payload
$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error'   => 'Email and password are required'
    ]);
    exit;
}

// fetch user by email, include their StudentID
$stmt = $conn->prepare("
    SELECT 
      u.UserID,
      u.Email,
      u.Password,
      u.Role,
      s.StudentID
    FROM `user` u
    LEFT JOIN `student` s
      ON s.UserID = u.UserID
    WHERE u.Email = ?
    LIMIT 1
");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();

if (!$user) {
    http_response_code(401);
    echo json_encode([
      'success' => false,
      'error'   => 'Invalid email'
    ]);
    exit;
}

// verify password hash
if (!password_verify($password, $user['Password'])) {
    http_response_code(401);
    echo json_encode([
      'success' => false,
      'error'   => 'Incorrect password'
    ]);
    exit;
}

// ensure only **students** can log in
if ($user['Role'] !== 'student' || empty($user['StudentID'])) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'error'   => 'Access denied: only students can log in'
    ]);
    exit;
}

// successful login → return student ID as 'id'
echo json_encode([
  'success' => true,
  'user'    => [
    'id'    => (int)$user['StudentID'],
    'email' => $user['Email'],
    'role'  => $user['Role'],
  ]
]);
