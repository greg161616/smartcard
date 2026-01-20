<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
$error = '';
$success = '';

// Get student's current information
$student = null;
$section_info = null;
$stmt = $conn->prepare('SELECT * FROM student WHERE userID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $student = $result->fetch_assoc()) {
    // Get section information
    $section_stmt = $conn->prepare('
        SELECT s.SectionName, se.SchoolYear 
        FROM section_enrollment se 
        JOIN section s ON se.SectionID = s.SectionID 
        WHERE se.StudentID = ? AND se.status = "active"
    ');
    $section_stmt->bind_param('i', $student['StudentID']);
    $section_stmt->execute();
    $section_result = $section_stmt->get_result();
    if ($section_result && $section_info = $section_result->fetch_assoc()) {
        // Section info found
    }
} else {
    // No student record found
    $error = 'Student record not found. Please contact administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $middleName = trim($_POST['middle_name']);
    $sex = $_POST['sex'];
    $birthdate = $_POST['birthdate'];
    $address = trim($_POST['address']);
    $contactNumber = trim($_POST['contact_number']);
    $parentName = trim($_POST['parent_name']);
    $parentsContact = trim($_POST['parents_contact']);
    $civilStatus = $_POST['civil_status'];
    $religion = trim($_POST['religion']);
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($birthdate) || empty($address)) {
        $error = 'First Name, Last Name, Birthdate, and Address are required fields.';
    } else {
        // Update student record
        $stmt = $conn->prepare('
            UPDATE student 
            SET FirstName = ?, LastName = ?, Middlename = ?,
                Sex = ?, Birthdate = ?, Address = ?, contactNumber = ?,
                parentname = ?, ParentsContact = ?, CivilStatus = ?, Religion = ?
            WHERE userID = ?
        ');
        $stmt->bind_param('sssssssssssi',
            $firstName, $lastName, $middleName,
            $sex, $birthdate, $address, $contactNumber,
            $parentName, $parentsContact, $civilStatus, $religion,
            $user_id
        );
        
        if ($stmt->execute()) {
            $success = 'Profile updated successfully!';
            // Refresh student data
            $stmt = $conn->prepare('SELECT * FROM student WHERE userID = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
            
            // Log the action
            require_once '../api/log_helper.php';
            log_system_action($conn, 'student_profile_updated', $user_id, [
                'email' => $email,
                'action' => 'Profile completed/updated'
            ], 'info');
            
            // Check if profile is now complete
            if (!empty($firstName) && !empty($lastName) && 
                !empty($birthdate) && !empty($address)) {
                // Redirect to student portal after 2 seconds
                header('Refresh: 2; URL=studentPort.php');
            }
        } else {
            $error = 'Error updating profile: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - Student</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('../img/bg.png') no-repeat center center fixed;
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 900px;
            margin: auto;
        }
        .profile-header {
            background: linear-gradient(135deg, #66e6eaff 0%, #07137cff 120%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .profile-body {
            padding: 30px;
        }
        .section-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(3, 11, 44, 0.25);
        }
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .form-section {
            border-left: 4px solid #667eea;
            padding-left: 15px;
            margin-bottom: 25px;
        }
        .form-section h5 {
            color: #667eea;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="profile-header">
                <h1><i class="bi bi-person-badge"></i> Complete Your Student Profile</h1>
                <p class="mb-0">Please fill in your details to continue</p>
            </div>
            
            <div class="profile-body">
                <?php if ($section_info): ?>
                    <div class="section-card">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="bi bi-journal-text"></i> Current Section</h5>
                                <h3><?php echo htmlspecialchars($section_info['SectionName']); ?></h3>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="bi bi-calendar-event"></i> School Year</h5>
                                <h3><?php echo htmlspecialchars($section_info['SchoolYear']); ?></h3>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error && strpos($error, 'not found') !== false): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <?php if (strpos($success, 'successfully') !== false): ?>
                            <br><small>Redirecting to student portal in 2 seconds...</small>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error && strpos($error, 'not found') === false): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($student): ?>
                <form method="POST" action="">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h5><i class="bi bi-person-vcard"></i> Personal Information</h5>
                                                <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="lrn" class="form-label required">LRN </label>
                                <input type="text" class="form-control" id="lrn" name="lrn" 
                                       value="<?php echo htmlspecialchars($student['LRN'] ?? ''); ?>" 
                                       pattern="\d{12}" readonly>
                                <small class="text-muted">Learner Reference Number</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label required">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($student['FirstName'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="last_name" class="form-label required">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($student['LastName'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                       value="<?php echo htmlspecialchars($student['Middlename'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="suffix" name="suffix" 
                                       value="<?php echo htmlspecialchars($student['Suffix'] ?? ''); ?>">
                                       <small class="text-muted">(Ex. Jr., II, III) </small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                                               <label for="birthdate" class="form-label required">Birthdate</label>
                                <input type="date" class="form-control" id="birthdate" name="birthdate" 
                                       value="<?php echo htmlspecialchars($student['Birthdate'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="sex" class="form-label required">Sex</label>
                                <select class="form-control" id="sex" name="sex" required>
                                    <option value="Male" <?php echo (($student['Sex'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($student['Sex'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="civil_status" class="form-label">Civil Status</label>
                                <select class="form-control" id="civil_status" name="civil_status">
                                    <option value="Single" <?php echo (($student['CivilStatus'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (($student['CivilStatus'] ?? '') == 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Separated" <?php echo (($student['CivilStatus'] ?? '') == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                    <option value="Widowed" <?php echo (($student['CivilStatus'] ?? '') == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="religion" class="form-label">Religion</label>
                                <input type="text" class="form-control" id="religion" name="religion" 
                                       value="<?php echo htmlspecialchars($student['Religion'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h5><i class="bi bi-telephone"></i> Contact Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_number" class="form-label">Student's Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                       value="<?php echo htmlspecialchars($student['contactNumber'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email (Read-only)</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
                                <small class="text-muted">This is your login email</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label required">Complete Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($student['Address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Parent/Guardian Information -->
                    <div class="form-section">
                        <h5><i class="bi bi-people"></i> Parent/Guardian Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="parent_name" class="form-label">Parent/Guardian Name</label>
                                <input type="text" class="form-control" id="parent_name" name="parent_name" 
                                       value="<?php echo htmlspecialchars($student['parentname'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="parents_contact" class="form-label">Parent's Contact Number</label>
                                <input type="text" class="form-control" id="parents_contact" name="parents_contact" 
                                       value="<?php echo htmlspecialchars($student['ParentsContact'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="studentPort.php" class="btn btn-outline-secondary me-md-2">
                            <i class="bi bi-skip-forward"></i> Skip
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Profile
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center text-muted">
                        <small>Fields marked with * are required</small>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set max date for birthdate (student should be at least 5 years old)
        const today = new Date();
        const minDate = new Date(today.getFullYear() - 25, today.getMonth(), today.getDate());
        const maxDate = new Date(today.getFullYear() - 5, today.getMonth(), today.getDate());
        document.getElementById('birthdate').min = minDate.toISOString().split('T')[0];
        document.getElementById('birthdate').max = maxDate.toISOString().split('T')[0];
        
        // Format LRN input (readonly, just display)
        const lrnInput = document.getElementById('lrn');
        if (lrnInput) {
            lrnInput.addEventListener('focus', function(e) {
                e.target.blur(); // Prevent editing
            });
        }
        
        // Format contact numbers
        function formatContactNumber(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.substring(0, 11);
                
                // Format as 09XX XXX XXXX
                if (value.length > 4) {
                    value = value.replace(/(\d{4})(\d{3})(\d{1,4})/, '$1 $2 $3');
                } else if (value.length > 0) {
                    value = value.replace(/(\d{4})/, '$1');
                }
                
                e.target.value = value;
            });
        }
        
        formatContactNumber(document.getElementById('contact_number'));
        formatContactNumber(document.getElementById('parents_contact'));
        
        // Auto-capitalize names
        const nameFields = ['first_name', 'last_name', 'middle_name', 'parent_name', 'religion'];
        nameFields.forEach(field => {
            const element = document.getElementById(field);
            if (element) {
                element.addEventListener('blur', function() {
                    this.value = this.value.split(' ').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
                    ).join(' ');
                });
            }
        });
    </script>
</body>
</html>