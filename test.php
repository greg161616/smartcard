<?php
ini_set('memory_limit','1024M');
ini_set('max_execution_time','300');

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$students      = [];
$error         = '';
$summaryExists = false;

if (isset($_POST['submit']) && isset($_FILES['grades_file'])) {
    try {
        // ── Load spreadsheet with minimal overhead
        $filePath = $_FILES['grades_file']['tmp_name'];
      $reader   = IOFactory::createReaderForFile($filePath);
      // $reader->setReadDataOnly(true);
      // $reader->setReadEmptyCells(false);
      $spreadsheet = $reader->load($filePath);

        // ── Find the “SUMMARY” sheet
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (stripos($name, 'SUMMARY') !== false) {
                $summaryExists = true;
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }
        if (!$summaryExists) {
            throw new Exception("Couldn't find a sheet with 'SUMMARY' in its name.");
        }

        // ── Prep bounds
        $highestColumn = $sheet->getHighestDataColumn();
        $maxColIndex   = Coordinate::columnIndexFromString($highestColumn);
        $highestRow    = $sheet->getHighestDataRow();

        // ── 1) Locate the student-name header row in column B
        $studentHeaderRow = null;
        for ($r = 1; $r <= 113; $r++) {
            $val = (string)$sheet->getCell("B{$r}")->getValue();
            // match “Learner” or “Name” but skip “School Name”
            if (stripos($val, 'Learner') !== false
                || (stripos($val, 'Name') !== false && stripos($val, 'School') === false)
            ) {
                $studentHeaderRow = $r;
                break;
            }
        }
        if (!$studentHeaderRow) {
            throw new Exception("Couldn't locate the student-names header (e.g. \"Learners' Names\").");
        }

        // ── 2) Find the grade-header row immediately below it (within 10 rows)
        $regex           = '/\b(?:Q[1-4]|[1-4](?:st|nd|rd|th)\s*Quarter|Final\s*Grade|Grade)\b/i';
        $gradeHeaderRow  = null;
        for ($r = $studentHeaderRow + 1;
             $r <= min($studentHeaderRow + 10, $highestRow);
             $r++
        ) {
            for ($c = 1; $c <= $maxColIndex; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $hdr       = (string)$sheet->getCell("{$colLetter}{$r}")->getValue();
                if (preg_match($regex, $hdr)) {
                    $gradeHeaderRow = $r;
                    break 2;
                }
            }
        }
        if (!$gradeHeaderRow) {
            throw new Exception(
                "Could not find the grade-header row. " .
                "Make sure it has cells like “1st Quarter”, “2nd Quarter”, … and “Grade”."
            );
        }

        // ── 3) Build the gradeCols map
        $gradeCols = [];
        for ($c = 1; $c <= $maxColIndex; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $hdr       = trim((string)$sheet->getCell("{$colLetter}{$gradeHeaderRow}")->getValue());
            if (preg_match($regex, $hdr)) {
                // strip any parenthetical notes
                $clean = preg_replace('/\s*\([^)]*\)/', '', $hdr);
                $gradeCols[$colLetter] = trim($clean);
            }
        }
        if (empty($gradeCols)) {
            throw new Exception("No grade columns detected on row {$gradeHeaderRow}.");
        }

        // ── 4) Extract student rows
        for ($r = $gradeHeaderRow + 1; $r <= $highestRow; $r++) {
            // new: evaluates the formula and returns the actual name
          $name = trim((string)$sheet->getCell("B{$r}")->getCalculatedValue());
            
            if ($name === '') {
                continue;
            }
            $rowData = ['name' => $name, 'grades' => []];
            foreach ($gradeCols as $col => $title) {
                $rowData['grades'][$title]
                    = $sheet->getCell("{$col}{$r}")
                            ->getCalculatedValue();
            }
            $students[] = $rowData;
        }

        // cleanup
        unset($sheet, $spreadsheet, $reader);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Grades Summary</title>
    <link
      rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"
    >
    <style>
      body { font-family: Arial, sans-serif; margin: 20px; }
      .container { max-width:1200px; margin:auto; }
      .error { color:#d9534f; background:#f2dede; padding:10px; margin:10px 0; }
      .info  { color:#31708f; background:#d9edf7; padding:10px; margin:10px 0; }
      .instructions { background:#f9f9f9; border-left:4px solid #337ab7; padding:15px; }
    </style>
</head>
<body>
  <div class="container">
    <h1>Student Grades Summary</h1>

    <form method="post" enctype="multipart/form-data">
      <label>Select E-Class Record Excel File:</label>
      <input type="file" name="grades_file" accept=".xlsx,.xls" required>
      <button type="submit" name="submit">Process File</button>
    </form>

    <div class="instructions">
      <h3>File Requirements:</h3>
      <ul>
        <li>One sheet whose name includes <strong>SUMMARY</strong></li>
        <li>Column B must have a header like <em>“Learners’ Names”</em></li>
        <li>Some row below that must contain:
          <ul>
            <li>“1st Quarter”, “2nd Quarter”, “3rd Quarter”, “4th Quarter”</li>
            <li>“Grade” (or “Final Grade”)</li>
          </ul>
        </li>
      </ul>
    </div>

    <?php if ($error): ?>
      <div class="error">Error: <?= htmlspecialchars($error) ?></div>
    <?php elseif (!$summaryExists && $_SERVER['REQUEST_METHOD']==='POST'): ?>
      <div class="info">
        No SUMMARY sheet found in your upload.
      </div>
    <?php endif; ?>

    <?php if (!empty($students)): ?>
      <table id="gradesTable" class="display" style="width:100%">
        <thead>
          <tr>
            <th>Student Name</th>
            <?php foreach (current($students)['grades'] as $hdr => $_): ?>
              <th><?= htmlspecialchars($hdr) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $stu): ?>
            <tr>
              <td><?= htmlspecialchars($stu['name']) ?></td>
              <?php foreach ($stu['grades'] as $val): ?>
                <td>
                  <?= is_numeric($val)
                         ? number_format($val,2)
                         : htmlspecialchars($val) ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"
  ></script>
  <script>
    $(document).ready(function(){
      $('#gradesTable').DataTable({
        paging: true,
        pageLength: 25,
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
        order: [[0,'asc']],
        dom: '<"top"fl<"clear">>rt<"bottom"ip<"clear">>'
      });
    });
  </script>
</body>
</html>
