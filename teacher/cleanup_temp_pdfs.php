<?php
// Script to clean up old temporary PDF files in teacher/temp_pdfs
date_default_timezone_set('Asia/Manila');

$defaultMinutes = 10; // default: delete files older than 10 minutes
$minutes = $defaultMinutes;

// Determine environment (CLI vs web)
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    global $argv;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $minutes = (int)$argv[1];
    }
} else {
    // Web: require a key for security
    $providedKey = isset($_GET['key']) ? $_GET['key'] : null;
    $envKey = getenv('CLEANUP_KEY') ?: null;
    if (!$envKey) {
        // If no env key, refuse when called from web to avoid accidental access
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No CLEANUP_KEY configured; web access disabled. Use CLI or set CLEANUP_KEY.']);
        exit;
    }
    if (!$providedKey || $providedKey !== $envKey) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing key']);
        exit;
    }
    if (isset($_GET['minutes']) && is_numeric($_GET['minutes'])) {
        $minutes = (int)$_GET['minutes'];
    }
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'temp_pdfs';
if (!is_dir($dir)) {
    $out = "OK: temp_pdfs directory not found (nothing to delete).\n";
    if ($isCli) {
        echo $out;
        exit(0);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'deleted' => 0, 'note' => 'temp_pdfs directory not found']);
        exit;
    }
}

$now = time();
$threshold = $now - ($minutes * 10);

$deleted = 0;
$errors = [];

foreach (glob($dir . DIRECTORY_SEPARATOR . '*.pdf') as $file) {
    if (!is_file($file)) continue;
    $mtime = filemtime($file);
    if ($mtime === false) continue;
    if ($mtime < $threshold) {
        if (@unlink($file)) {
            $deleted++;
        } else {
            $errors[] = $file;
        }
    }
}

if ($isCli) {
    echo "Deleted: $deleted file(s)\n";
    if (!empty($errors)) {
        echo "Errors deleting: " . count($errors) . " files:\n";
        foreach ($errors as $e) echo " - $e\n";
    }
    exit(0);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'deleted' => $deleted, 'errors' => $errors]);
    exit;
}
