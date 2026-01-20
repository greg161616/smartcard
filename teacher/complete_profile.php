<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
$error = '';
$success = '';

// Get teacher's current information
$teacher = null;
$stmt = $conn->prepare('SELECT * FROM teacher WHERE userID = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $teacher = $result->fetch_assoc()) {
    // Teacher exists
} else {
    // No teacher record found - create empty array
    $teacher = [
        'fName' => '',
        'lName' => '',
        'mName' => '',
        'surfix' => '',
        'gender' => '',
        'birthdate' => '',
        'address' => '',
        'contact' => ''
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $middleName = trim($_POST['middle_name']);
    $surfix = trim($_POST['surfix']);
    $gender = trim($_POST['gender']);
    $contactNumber = trim($_POST['contact_number']);
    $birthdate = $_POST['birthdate'];
    $address = trim($_POST['address']);
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($gender)) {
        $error = 'First Name, Last Name, and Gender are required fields.';
    } else {
        // Check if teacher record already exists
        $stmt = $conn->prepare('SELECT TeacherID FROM teacher WHERE userID = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare('
                UPDATE teacher 
                SET fName = ?, lName = ?, mName = ?, surfix = ?, 
                    gender = ?, contact = ?, birthdate = ?, address = ?
                WHERE userID = ?
            ');
            $stmt->bind_param('ssssssssi', 
                $firstName, $lastName, $middleName, $surfix,
                $gender, $contactNumber, $birthdate, $address,
                $user_id
            );
        } else {
            // Insert new record
            $stmt = $conn->prepare('
                INSERT INTO teacher (userID, fName, lName, mName, surfix, 
                                   gender, contact, birthdate, address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $status = 'active';
            $stmt->bind_param('isssssssss',
                $user_id, $firstName, $lastName, $middleName, $surfix,
                $gender, $contactNumber, $birthdate, $address, $status
            );
        }
        
        if ($stmt->execute()) {
            $success = 'Profile updated successfully!';
            // Refresh teacher data
            $stmt = $conn->prepare('SELECT * FROM teacher WHERE userID = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $teacher = $result->fetch_assoc();
            
            // Log the action
            require_once '../api/log_helper.php';
            log_system_action($conn, 'teacher_profile_updated', $user_id, [
                'email' => $email,
                'action' => 'Profile completed/updated'
            ], 'info');
            
            // Check if profile is now complete
            if (!empty($firstName) && !empty($lastName) && !empty($gender)) {
                // Redirect to dashboard after 2 seconds
                header('Refresh: 2; URL=tdashboard.php');
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
    <title>Complete Your Profile - Teacher</title>
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
            max-width: 800px;
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
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="profile-header">
                <h1><i class="bi bi-person-circle"></i> Complete Your Teacher Profile</h1>
                <p class="mb-0">Please fill in your details to continue</p>
            </div>
            
            <div class="profile-body">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <?php if (strpos($success, 'successfully') !== false): ?>
                            <br><small>Redirecting to dashboard in 2 seconds...</small>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label required">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($teacher['fName'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label required">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($teacher['lName'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                   value="<?php echo htmlspecialchars($teacher['mName'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="surfix" class="form-label">Suffix (Jr., Sr., III, etc.)</label>
                            <input type="text" class="form-control" id="surfix" name="surfix" 
                                   value="<?php echo htmlspecialchars($teacher['surfix'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label required">Gender</label>
                            <select class="form-control" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($teacher['gender']) && $teacher['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($teacher['gender']) && $teacher['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($teacher['gender']) && $teacher['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_number" class="form-label required">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                   value="<?php echo htmlspecialchars($teacher['contact'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="birthdate" class="form-label required">Birthdate</label>
                            <input type="date" class="form-control" id="birthdate" name="birthdate" 
                                   value="<?php echo htmlspecialchars($teacher['birthdate'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email (Read-only)</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
                        <small class="text-muted">This is your login email. It cannot be changed here.</small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="tdashboard.php" class="btn btn-outline-secondary me-md-2">
                            <i class="bi bi-skip-forward"></i> Skip
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Continue
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center text-muted">
                        <small>Fields marked with * are required</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set max date for birthdate (18 years ago)
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
        document.getElementById('birthdate').max = maxDate.toISOString().split('T')[0];
        
        // Auto-format contact number
        document.getElementById('contact_number').addEventListener('input', function(e) {
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
    </script>
</body>
</html>