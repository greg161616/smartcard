<?php
// Endpoint to receive uploaded PDF blob and save to teacher/temp_pdfs
header('Content-Type: application/json');

// Basic security: ensure request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$uploadDir = __DIR__ . '/temp_pdfs';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error']);
    exit;
}

// Generate a safe unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = strtolower($ext) ?: 'pdf';
$allowedExt = ['pdf'];
if (!in_array($ext, $allowedExt)) {
    // still allow but force to pdf
    $ext = 'pdf';
}

$newName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$target = $uploadDir . DIRECTORY_SEPARATOR . $newName;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    exit;
}

// Return a relative URL (relative to teacher folder)
$url = 'temp_pdfs/' . $newName;

// Ensure file has safe permissions
@chmod($target, 0644);

// Build a full URL so clients (HEAD checks) can reliably access the file.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$fullUrl = $scheme . '://' . $host . $scriptDir . '/' . $url;

echo json_encode(['success' => true, 'url' => $url, 'full_url' => $fullUrl, 'filename' => $newName]);
exit;
exit;
