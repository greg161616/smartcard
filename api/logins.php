<?php
require 'config.php';  // brings in headers + $conn

// parse JSON payload
$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$email    = trim($input['email']    ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error'   => 'Email and password are required'
    ]);
    exit;
}

// fetch user by email, including their TeacherID
$stmt = $conn->prepare("
    SELECT 
      u.UserID,
      u.Email,
      u.Password,
      u.Role,
      t.TeacherID
    FROM `user` u
    LEFT JOIN `teacher` t
      ON t.UserID = u.UserID
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

// check password
if (!password_verify($password, $user['Password'])) {
    http_response_code(401);
    echo json_encode([
      'success' => false,
      'error'   => 'Incorrect password'
    ]);
    exit;
}

// ensure only teachers can log in
if ($user['Role'] !== 'teacher' || empty($user['TeacherID'])) {
    echo json_encode([
      'success' => false,
      'error'   => 'Access denied: only teachers allowed'
    ]);
    exit;
}

// all good — return TeacherID instead of UserID
echo json_encode([
  'success' => true,
  'user'    => [
    'id'    => (int)$user['TeacherID'],
    'email' => $user['Email'],
    'role'  => $user['Role'],
  ]
]);
