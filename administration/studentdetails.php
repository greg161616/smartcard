<?php
// Include database configuration
include __DIR__ . '/../config.php';

// Fetch student data based on the ID passed from AJAX
if (isset($_GET['id'])) {
    $studentId = $_GET['id'];

    // SQL query to fetch student data
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
            sec.SectionID,
            sec.GradeLevel,
            sec.SectionName
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
}
?>

<div class="container mt-4">
    <h3>Student Details</h3>
    <form>
        <!-- Basic Information -->
        <h5>Basic Information</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">LRN</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['LRN']) ?>" readonly>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['FirstName'] . ' ' . $row['MiddleName'] . ' ' . $row['LastName']) ?>" readonly>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Sex</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['Sex']) ?>" readonly>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Birthdate</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['Birthdate']) ?>" readonly>
            </div>
        </div>

        <!-- Contact Information -->
        <h5>Contact Information</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['Address']) ?>" readonly>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['ContactNumber']) ?>" readonly>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Parent's Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['ParentName']) ?>" readonly>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Parent's Contact</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['ParentsContact']) ?>" readonly>
            </div>
        </div>

        <!-- Enrollment Information -->
        <h5>Enrollment Information</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Section</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['GradeLevel'] . ' - ' . $row['SectionName']) ?>" readonly>
            </div>
        </div>

        <!-- Additional Information -->
        <h5>Additional Information</h5>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Civil Status</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['CivilStatus']) ?>" readonly>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Religion</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['Religion']) ?>" readonly>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['Email']) ?>" readonly>
            </div>
        </div>
    </form>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
