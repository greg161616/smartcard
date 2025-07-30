<?php
// File: upload.php

session_start();
require '../config.php';

// 1) Ensure teacher is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 2) Grab & validate subject_id and section_id from query string
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
if ($subjectId <= 0 || $sectionId <= 0) {
    header('Location: grading_sheet.php');
    exit;
}

// 3) Fetch subject name
$stmt = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ?");
$stmt->bind_param('i', $subjectId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "Invalid subject."; exit;
}
$subjectName = $res->fetch_assoc()['SubjectName'];
$stmt->close();

// 4) Fetch section info
$stmt = $conn->prepare("SELECT GradeLevel, SectionName FROM section WHERE SectionID = ?");
$stmt->bind_param('i', $sectionId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "Invalid section."; exit;
}
$section = $res->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Upload Grades</title>
  
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>

  <div class="container mt-4">
    <h1>Upload Quarterly Grades</h1>
    <h5 class="text-secondary">
      Subject: <strong><?= htmlspecialchars($subjectName) ?></strong>
      | Section: Grade <?= $section['GradeLevel'] ?>-<?= htmlspecialchars($section['SectionName']) ?>
    </h5>

    <p class="mt-3">Upload an Excel file (.xls/.xlsx) containing the grades for this subject & section.</p>

    <form id="uploadForm" enctype="multipart/form-data">
      <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
      <input type="hidden" name="section_id" value="<?= $sectionId ?>">

      <div class="mb-3">
        <input
          type="file"
          name="grades_file"
          accept=".xls,.xlsx"
          class="form-control"
          required
        >
      </div>
      <button type="submit" class="btn btn-primary">Upload &amp; Process</button>
    </form>
  </div>

  <!-- Loading / Success Modal -->
  <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content text-center py-4">
        <!-- Spinner -->
        <div id="spinner">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <div class="mt-2">Processing grades…</div>
        </div>
        <!-- Image Checkmark (hidden until success) -->
        <div id="checkmark" class="d-none">
          <!-- Make sure you have a file at ../img/confirm-icon.png -->
          <img src="../img/confirm-icon.png" alt="Done" width="64" height="64">
          <div class="mt-2">Done!</div>
        </div>
      </div>
    </div>
  </div>

  <div id="result" class="container mt-3"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const form       = document.getElementById('uploadForm');
    const resultDiv  = document.getElementById('result');
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    const spinner    = document.getElementById('spinner');
    const checkmark  = document.getElementById('checkmark');

    form.addEventListener('submit', e => {
      e.preventDefault();
      if (!confirm('This will overwrite any existing grades. Continue?')) return;

      // Reset UI
      resultDiv.innerHTML = '';
      spinner.classList.remove('d-none');
      checkmark.classList.add('d-none');
      loadingModal.show();

      const data = new FormData(form);
      const xhr  = new XMLHttpRequest();
      xhr.open('POST', 'process_upload.php', true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.onload = () => {
        let json;
        try {
          json = JSON.parse(xhr.responseText);
        } catch {
          alert('Unexpected server response');
          loadingModal.hide();
          return;
        }

        if (json.success) {
          // Swap spinner for checkmark image
          spinner.classList.add('d-none');
          checkmark.classList.remove('d-none');

          setTimeout(() => {
            loadingModal.hide();
            resultDiv.innerHTML = `
              <div class="alert alert-success">
                Processed <strong>${json.processed}</strong> grades
                for <em>${json.subject}</em>.
              </div>`;
          }, 800);

        } else {
          loadingModal.hide();
          resultDiv.innerHTML = `
            <div class="alert alert-danger">
              <strong>${json.errors.length} errors:</strong>
              <ul>${json.errors.map(err => `<li>${err}</li>`).join('')}</ul>
            </div>`;
        }
      };

      xhr.onerror = () => {
        loadingModal.hide();
        resultDiv.textContent = 'Network error';
      };

      xhr.send(data);
    });
  </script>
</body>
</html>
