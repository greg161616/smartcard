<?php
// File: upload.php

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

include '../config.php'; // your mysqli $conn
session_start();

/**
 * Send a JSON response and exit.
 */
function jsonResponse(array $data, int $status = 200): void {
    header('Content-Type: application/json', true, $status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detect AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $successCnt = 0;
    $errors     = [];
    $subject    = '';
    $uploaderId = $_SESSION['user_id'] ?? 1;

    // 1) Validate upload
    if (empty($_FILES['grades_file']['tmp_name'])) {
        $errors[] = 'No file uploaded. Please choose an Excel file first.';
    } else {
        $file = $_FILES['grades_file']['tmp_name'];
    }

    // 2) Read subject from INPUT DATA!AG7
    if (empty($errors)) {
        try {
            $readerSub = IOFactory::createReaderForFile($file);
            $readerSub->setReadDataOnly(true)
                      ->setLoadSheetsOnly(['INPUT DATA']);
            $wbSub = $readerSub->load($file);
            $inSheet = $wbSub->getSheetByName('INPUT DATA');
            if (!$inSheet) {
                throw new Exception('Sheet "INPUT DATA" not found.');
            }
            $raw = $inSheet->getCell('AG7')->getValue();
            if (is_string($raw) && str_starts_with($raw, '=')) {
                $raw = $inSheet->getCell('AG7')->getOldCalculatedValue();
            }
            $subject = trim((string)$raw);
            if ($subject === '' || strtoupper($subject) === '#REF!') {
                throw new Exception('Could not read subject from INPUT DATA!AG7');
            }
            $wbSub->disconnectWorksheets();
            unset($wbSub);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    // 3) Lookup SubjectID
    if (empty($errors)) {
        $stmtS = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ?");
        $stmtS->bind_param('s', $subject);
        $stmtS->execute();
        $rs = $stmtS->get_result();
        if (!$rs->num_rows) {
            $errors[] = "Subject '$subject' not found in database.";
        } else {
            $subjectId = $rs->fetch_assoc()['SubjectID'];
        }
        $stmtS->close();
    }

    // 4) Process SUMMARY OF QUARTERLY GRADES
    if (empty($errors)) {
        class SummaryReadFilter implements IReadFilter {
            private int $start, $end;
            public function __construct(int $s, int $e) {
                $this->start = $s;
                $this->end   = $e;
            }
            public function readCell($col, $row, $wsName=''): bool {
                if ($wsName !== 'SUMMARY OF QUARTERLY GRADES') return false;
                if ($row <= 11) {
                    $keep = ['A','B','F','J','N','R','V','W'];
                    if ($row === 7)  $keep[] = 'AG';
                    if ($row === 8)  $keep[] = 'E';
                    return in_array($col, $keep, true);
                }
                return $row >= $this->start && $row <= $this->end
                    && in_array($col, ['A','B','F','J','N','R','V','W'], true);
            }
        }

        function unwrap($cell) {
            $v = $cell->getValue();
            return (is_string($v) && str_starts_with($v, '=')) 
                ? $cell->getOldCalculatedValue() 
                : $v;
        }
        function getName($cell): string {
            return trim((string)unwrap($cell));
        }
        function getGrade($cell) {
            $v = unwrap($cell);
            if (is_numeric($v) && $v >= 0 && $v <= 100) return $v;
            if (is_string($v) && str_contains($v, '%')) {
                $n = str_replace('%','',$v);
                return is_numeric($n) ? $n : null;
            }
            return null;
        }

        try {
            $reader = IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);

            // find SUMMARY sheet info
            $info = null;
            foreach ($reader->listWorksheetInfo($file) as $ws) {
                if ($ws['worksheetName'] === 'SUMMARY OF QUARTERLY GRADES') {
                    $info = $ws;
                    break;
                }
            }
            if (!$info) {
                throw new Exception('Sheet "SUMMARY OF QUARTERLY GRADES" missing.');
            }

            $reader->setLoadSheetsOnly(['SUMMARY OF QUARTERLY GRADES'])
                   ->setReadFilter(new SummaryReadFilter(12, $info['totalRows']));
            $wb = $reader->load($file);
            $sh = $wb->getActiveSheet();
            $lastRow = $sh->getHighestDataRow();

            $currentSection = '';
            for ($r = 12; $r <= $lastRow; $r++) {
                $sec = strtoupper(trim((string)unwrap($sh->getCell("A$r"))));
                if (in_array($sec, ['MALE','FEMALE'], true)) {
                    $currentSection = $sec;
                    continue;
                }

                $name = getName($sh->getCell("B$r"));
                if (
                    $name === '' ||
                    strcasecmp($name, 'MALE') === 0 ||
                    strcasecmp($name, 'FEMALE') === 0 ||
                    is_numeric($name)
                ) {
                    continue;
                }

                // split name
                if (str_contains($name, ',')) {
                    [$last, $fm] = array_map('trim', explode(',', $name, 2));
                    $first       = explode(' ', $fm)[0];
                } else {
                    $parts = explode(' ', $name);
                    $first = array_shift($parts);
                    $last  = array_pop($parts) ?: '';
                }

                // lookup student
                $st = $conn->prepare("
                    SELECT StudentID FROM student
                    WHERE LOWER(TRIM(LastName)) = LOWER(TRIM(?))
                      AND LOWER(TRIM(FirstName)) = LOWER(TRIM(?))
                ");
                $st->bind_param('ss', $last, $first);
                $st->execute();
                $res = $st->get_result();
                if (!$res->num_rows) {
                    $errors[] = "Student not found: $name (Section $currentSection)";
                    $st->close();
                    continue;
                }
                $sid = $res->fetch_assoc()['StudentID'];
                $st->close();

                // get grades
                $q1    = getGrade($sh->getCell("F$r"));
                $q2    = getGrade($sh->getCell("J$r"));
                $q3    = getGrade($sh->getCell("N$r"));
                $q4    = getGrade($sh->getCell("R$r"));
                $final = getGrade($sh->getCell("V$r"));
                if (!array_filter([$q1, $q2, $q3, $q4, $final])) {
                    $errors[] = "No valid grades for $name";
                    continue;
                }

                // insert or update
                $ins = $conn->prepare("
                    INSERT INTO grades
                      (student_id, subject, Q1, Q2, Q3, Q4, Final, uploadedby, uploaded)
                    VALUES (?,?,?,?,?,?,?,?,NOW())
                    ON DUPLICATE KEY UPDATE
                      Q1=VALUES(Q1), Q2=VALUES(Q2),
                      Q3=VALUES(Q3), Q4=VALUES(Q4),
                      Final=VALUES(Final),
                      uploadedby=VALUES(uploadedby),
                      uploaded=NOW()
                ");
                $ins->bind_param(
                    'iidddddi',
                    $sid,
                    $subjectId,
                    $q1,
                    $q2,
                    $q3,
                    $q4,
                    $final,
                    $uploaderId
                );
                if ($ins->execute()) {
                    $successCnt++;
                } else {
                    $errors[] = "DB error for $name: " . $ins->error;
                }
                $ins->close();
            }

            $wb->disconnectWorksheets();
            unset($wb);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Return JSON for AJAX or redirect back for normal POST
    if ($isAjax) {
        jsonResponse([
            'success'   => empty($errors),
            'processed' => $successCnt,
            'subject'   => $subject,
            'errors'    => $errors
        ]);
    } else {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Ajax + Progress Bar Uploader</title>
  <style>
    body { font-family: Arial; padding: 20px; }
    .note { background:#fff3cd; padding:10px; border-radius:5px; }
    #progressBar { width:100%; height:20px; display:none; margin-top:10px; }
    #result { margin-top:15px; }
    .error-list { background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; }
  </style>
</head>
<body>
  <h1>Upload Quarterly Grades</h1>
  <div class="note">
    <ul>
      <li>Sheets “INPUT DATA” and “SUMMARY OF QUARTERLY GRADES” required</li>
      <li>Subject read from INPUT DATA!AG7 → matched in <code>subject</code> table</li>
      <li>Existing grades updated via <code>ON DUPLICATE KEY</code></li>
    </ul>
  </div>

  <form id="uploadForm" enctype="multipart/form-data">
    <input type="file" name="grades_file" accept=".xls,.xlsx" required>
    <button type="submit" style="margin-top:10px;">Upload &amp; Process</button>
  </form>

  <progress id="progressBar" value="0" max="100"></progress>
  <div id="result"></div>

  <script>
    const form      = document.getElementById('uploadForm');
    const bar       = document.getElementById('progressBar');
    const resultDiv = document.getElementById('result');

    form.addEventListener('submit', e => {
      e.preventDefault();
      if (!confirm('This will overwrite any existing grades. Continue?')) return;

      const data = new FormData(form);
      resultDiv.innerHTML = '';
      bar.style.display = 'block';
      bar.value = 0;

      const xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.upload.onprogress = evt => {
        if (evt.lengthComputable) {
          bar.value = (evt.loaded / evt.total) * 100;
        }
      };

      xhr.onload = () => {
        bar.style.display = 'none';
        let json;
        try {
          json = JSON.parse(xhr.responseText);
        } catch {
          resultDiv.textContent = '❌ Unexpected server response';
          return;
        }
        if (json.success) {
          alert(`✅ Processed ${json.processed} grades for ${json.subject}`);
          resultDiv.innerHTML = `<div>✅ Processed <strong>${json.processed}</strong> grades for <em>${json.subject}</em>.</div>`;
        } else {
          resultDiv.innerHTML = `
            <div class="error-list">
              <strong>${json.errors.length} errors:</strong>
              <ul>${json.errors.map(err => `<li>${err}</li>`).join('')}
              </ul>
            </div>
          `;
        }
      };

      xhr.onerror = () => {
        bar.style.display = 'none';
        resultDiv.textContent = '❌ Network error';
      };

      xhr.send(data);
    });
  </script>
</body>
</html>
