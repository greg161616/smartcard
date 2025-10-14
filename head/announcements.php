<?php
include '../config.php';

// CREATE
if (isset($_POST['add_announcement'])) {
    $title = $_POST['title'];
    $date = $_POST['date'];
    $content = $_POST['content'];
    $stmt = $conn->prepare("INSERT INTO announcement (title, date, content) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $date, $content);
    $stmt->execute();
    $stmt->close();
    header("Location: principalDash.php");
    exit;
}

// UPDATE
if (isset($_POST['update_announcement'])) {
    $id = $_POST['announcement_id'];
    $title = $_POST['title'];
    $date = $_POST['date'];
    $content = $_POST['content'];
    $stmt = $conn->prepare("UPDATE announcement SET title = ?, date = ?, content = ? WHERE id = ?");
    $stmt->bind_param("sssi", $title, $date, $content, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: principalDash.php");
    exit;
}

// DELETE
if (isset($_POST['delete_announcement'])) {
    $id = $_POST['announcement_id'];
    $stmt = $conn->prepare("DELETE FROM announcement WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: principalDash.php");
    exit;
}

// READ
$announcements = $conn->query("SELECT * FROM announcement ORDER BY date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Announcements</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
 <?php include '../navs/adminNav.php'; ?>
<div class="container mt-4">
   
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-light border-dark btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">Add Announcement</button>
  </div>

  <div class="row justify-content-start">
    <div class="col-md-5">
      <?php while ($row = $announcements->fetch_assoc()): ?>
        <div class="card mb-3 shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($row['date']) ?></h6>
            <p class="card-text"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
            <div class="d-flex justify-content-end gap-2">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
              <form method="post" onsubmit="return confirm('Delete this announcement?')">
                <input type="hidden" name="announcement_id" value="<?= $row['id'] ?>">
                <button type="submit" name="delete_announcement" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <form method="post" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="announcement_id" value="<?= $row['id'] ?>">
                <div class="mb-2">
                  <label class="form-label">Title</label>
                  <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($row['title']) ?>" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">Date</label>
                  <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($row['date']) ?>" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">Content</label>
                  <textarea name="content" rows="4" class="form-control" required><?= htmlspecialchars($row['content']) ?></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button name="update_announcement" class="btn btn-success">Update</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>

      <?php endwhile; ?>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Content</label>
          <textarea name="content" rows="4" class="form-control" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button name="add_announcement" class="btn btn-primary">Add</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
