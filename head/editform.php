<?php
// Include database configuration
session_start(); // Added session start
include __DIR__ . '/../config.php';

// Check if student ID is passed in the URL
if (isset($_GET['id'])) {
    $studentId = $_GET['id'];

    // SQL query to fetch student data for editing
    $sql = "
        SELECT
            s.StudentID,
            s.LRN,
            s.FirstName,
            s.MiddleName,
            s.LastName,
            s.Sex,
            s.Birthdate,
            s.Address,
            s.ContactNumber,
            s.ParentName,
            s.ParentsContact,
            s.CivilStatus,
            s.Religion,
            u.Email,
            sec.SectionID
        FROM student AS s
        JOIN `user` AS u ON u.UserID = s.userID
        JOIN section_enrollment AS se ON se.StudentID = s.StudentID AND se.status = 'active'
        JOIN section AS sec ON sec.SectionID = se.SectionID
        WHERE s.StudentID = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    // If student exists, fetch the data
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "<p>No student found with this ID.</p>";
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['StudentID'])) {
    // Save the updated student data when the form is submitted
    $studentId = $_POST['StudentID'];
    $lrn = $_POST['LRN'];
    $firstName = $_POST['FirstName'];
    $middleName = $_POST['MiddleName'];
    $lastName = $_POST['LastName'];
    $sex = $_POST['Sex'];
    $birthdate = $_POST['Birthdate'];
    $email = $_POST['Email'];
    $address = $_POST['Address'];
    $contactNumber = $_POST['ContactNumber'];
    $parentName = $_POST['ParentName'];
    $parentsContact = $_POST['ParentsContact'];
    $civilStatus = $_POST['CivilStatus'];
    $religion = $_POST['Religion'];
    $sectionID = $_POST['SectionID'];

    // Prepare the SQL statement to update student details
    $sql = "
        UPDATE student
        SET
            LRN = ?,
            FirstName = ?,
            MiddleName = ?,
            LastName = ?,
            Sex = ?,
            Birthdate = ?,
            Address = ?,
            ContactNumber = ?,
            ParentName = ?,
            ParentsContact = ?,
            CivilStatus = ?,
            Religion = ?,
            userID = (SELECT userID FROM `user` WHERE Email = ?)
        WHERE StudentID = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssssssssssssi',
        $lrn, $firstName, $middleName, $lastName, $sex, $birthdate, $address, $contactNumber,
        $parentName, $parentsContact, $civilStatus, $religion, $email, $studentId
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Student details updated successfully!";
        // Check if request is AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'message' => 'Student details updated successfully!']);
            exit;
        } else {
            header("Location: studentlist.php?id=$studentId");
            exit;
        }
    } else {
        $_SESSION['error'] = "Failed to update student details!";
        // Check if request is AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Failed to update student details!']);
            exit;
        } else {
            header("Location: studentlist.php?id=$studentId");
            exit;
        }
    }
}

// Check if this is an AJAX request (for modal)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Student</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h2>Edit Student Details</h2>
    
    <!-- Display Success or Error Message -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['message']; ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error']; ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
<?php } // End if not AJAX ?>
    
    <form action="editform.php" method="POST" id="editForm">
        <input type="hidden" name="StudentID" value="<?= htmlspecialchars($row['StudentID']) ?>">
        
        <!-- LRN -->
        <div class="mb-3">
            <label class="form-label">LRN</label>
            <input type="text" class="form-control" name="LRN" value="<?= htmlspecialchars($row['LRN']) ?>" required>
        </div>
        
        <!-- First Name -->
        <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="FirstName" value="<?= htmlspecialchars($row['FirstName']) ?>" required>
        </div>

        <!-- Middle Name -->
        <div class="mb-3">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" name="MiddleName" value="<?= htmlspecialchars($row['MiddleName']) ?>">
        </div>

        <!-- Last Name -->
        <div class="mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" class="form-control" name="LastName" value="<?= htmlspecialchars($row['LastName']) ?>" required>
        </div>

        <!-- Sex -->
        <div class="mb-3">
            <label class="form-label">Sex</label>
            <select class="form-select" name="Sex">
                <option value="Male" <?= $row['Sex'] == 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $row['Sex'] == 'Female' ? 'selected' : '' ?>>Female</option>
            </select>
        </div>

        <!-- Birthdate -->
        <div class="mb-3">
            <label class="form-label">Birthdate</label>
            <input type="date" class="form-control" name="Birthdate" value="<?= htmlspecialchars($row['Birthdate']) ?>">
        </div>

        <!-- Email -->
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="Email" value="<?= htmlspecialchars($row['Email']) ?>" required>
        </div>

        <!-- Address -->
        <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="text" class="form-control" name="Address" value="<?= htmlspecialchars($row['Address']) ?>">
        </div>

        <!-- Contact Number -->
        <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="text" class="form-control" name="ContactNumber" value="<?= htmlspecialchars($row['ContactNumber']) ?>">
        </div>

        <!-- Parent's Name -->
        <div class="mb-3">
            <label class="form-label">Parent's Name</label>
            <input type="text" class="form-control" name="ParentName" value="<?= htmlspecialchars($row['ParentName']) ?>">
        </div>

        <!-- Parent's Contact -->
        <div class="mb-3">
            <label class="form-label">Parent's Contact</label>
            <input type="text" class="form-control" name="ParentsContact" value="<?= htmlspecialchars($row['ParentsContact']) ?>">
        </div>

        <!-- Civil Status -->
        <div class="mb-3">
            <label class="form-label">Civil Status</label>
            <select class="form-select" name="CivilStatus">
                <option value="Single" <?= $row['CivilStatus'] == 'Single' ? 'selected' : '' ?>>Single</option>
                <option value="Married" <?= $row['CivilStatus'] == 'Married' ? 'selected' : '' ?>>Married</option>
                <option value="Separated" <?= $row['CivilStatus'] == 'Separated' ? 'selected' : '' ?>>Separated</option>
                <option value="Widowed" <?= $row['CivilStatus'] == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
            </select>
        </div>

        <!-- Religion -->
        <div class="mb-3">
            <label class="form-label">Religion</label>
            <input type="text" class="form-control" name="Religion" value="<?= htmlspecialchars($row['Religion']) ?>">
        </div>

        <!-- Section -->
        <div class="mb-3">
            <label class="form-label">Section</label>
            <select name="SectionID" class="form-select" required>
                <option value="">Select Section</option>
                <?php
                // Fetch sections
                $secRes = $conn->query("SELECT SectionID, CONCAT(GradeLevel, ' - ', SectionName) AS secname FROM section ORDER BY GradeLevel, SectionName");
                while ($sec = $secRes->fetch_assoc()):
                    ?>
                    <option value="<?= $sec['SectionID'] ?>" <?= $sec['SectionID'] == $row['SectionID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sec['secname']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Save Button -->
        <div class="mb-3">
            <button type="submit" class="btn btn-success" onclick="return confirmSave()">Save Changes</button>
        </div>
    </form>

<?php if (!$isAjax) { ?>
  </div>
  
  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function confirmSave() {
        return confirm("Are you sure you want to save the changes?");
    }
  </script>
</body>
</html>
<?php } ?>