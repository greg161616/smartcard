<?php
session_start();
include '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Handle Add Principal Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_principal'])) {
    $full_name = trim($_POST['full_name']);
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_password = $_POST['admin_password'];
    
    // Validate inputs
    if (empty($full_name) || empty($position) || empty($email) || empty($password) || empty($confirm_password) || empty($admin_password)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Verify admin password
        $admin_id = $_SESSION['user_id'];
        $check_admin_stmt = $conn->prepare("SELECT Password FROM user WHERE UserID = ?");
        $check_admin_stmt->bind_param("i", $admin_id);
        $check_admin_stmt->execute();
        $admin_result = $check_admin_stmt->get_result();
        $admin_data = $admin_result->fetch_assoc();
        
        if (!$admin_data || !password_verify($admin_password, $admin_data['Password'])) {
            $error = "Admin password is incorrect!";
        } else {
            // Check if email already exists
            $check_email_stmt = $conn->prepare("SELECT UserID FROM user WHERE Email = ?");
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $email_result = $check_email_stmt->get_result();
            
            if ($email_result->num_rows > 0) {
                $error = "Email already exists! Please use a different email.";
            } else {
                $conn->begin_transaction();
                
                try {
                    // Set current principal to inactive
                    $deactivate_stmt = $conn->prepare("UPDATE admin SET status = 'inactive' WHERE Position IN ('Head teacher', 'Principal') AND status = 'active'");
                    $deactivate_stmt->execute();
                    
                    // Create user account
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'principal';
                    
                    $user_stmt = $conn->prepare("INSERT INTO user (Email, Password, Role, CreatedAt) VALUES (?, ?, ?, NOW())");
                    $user_stmt->bind_param("sss", $email, $hashed_password, $role);
                    $user_stmt->execute();
                    
                    $new_user_id = $conn->insert_id;
                    
                    // Create admin record
                    $admin_stmt = $conn->prepare("INSERT INTO admin (UserID, FullName, Position, status) VALUES (?, ?, ?, 'active')");
                    $admin_stmt->bind_param("iss", $new_user_id, $full_name, $position);
                    $admin_stmt->execute();
                    
                    // Log the action
                    $log_action = "PRINCIPAL_ADDED";
                    $log_details = "New principal added: " . $full_name;
                    $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
                    $log_stmt->bind_param("sis", $log_action, $admin_id, $log_details);
                    $log_stmt->execute();
                    
                    $conn->commit();
                    $success = "New principal added successfully! Previous principal has been set to inactive.";
                    
                    // Clear form
                    $_POST = array();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to add principal: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Activate Principal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_principal'])) {
    $admin_id = intval($_POST['admin_id']);
    $admin_user_id = $_SESSION['user_id'];
    
    $conn->begin_transaction();
    
    try {
        // Set all principals to inactive
        $deactivate_stmt = $conn->prepare("UPDATE admin SET status = 'inactive' WHERE Position IN ('Head teacher', 'Principal')");
        $deactivate_stmt->execute();
        
        // Set selected principal to active
        $activate_stmt = $conn->prepare("UPDATE admin SET status = 'active' WHERE AdminID = ?");
        $activate_stmt->bind_param("i", $admin_id);
        $activate_stmt->execute();
        
        // Log the action
        $log_action = "PRINCIPAL_ACTIVATED";
        $log_details = "Principal activated with ID: " . $admin_id;
        $log_stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, 'INFO', NOW())");
        $log_stmt->bind_param("sis", $log_action, $admin_user_id, $log_details);
        $log_stmt->execute();
        
        $conn->commit();
        $success = "Principal activated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to activate principal: " . $e->getMessage();
    }
}

// Get current active principal
$current_principal_stmt = $conn->prepare("SELECT a.FullName, a.Position, u.Email FROM admin a JOIN user u ON a.UserID = u.UserID WHERE a.status = 'active' AND a.Position IN ('Head teacher', 'Principal')");
$current_principal_stmt->execute();
$current_principal_result = $current_principal_stmt->get_result();
$current_principal = $current_principal_result->fetch_assoc();

// Get all principals
$principals_stmt = $conn->prepare("
    SELECT a.AdminID, a.FullName, a.Position, a.status, u.Email, u.CreatedAt 
    FROM admin a 
    JOIN user u ON a.UserID = u.UserID 
    WHERE a.Position IN ('Head teacher', 'Principal')
    ORDER BY a.status DESC, a.AdminID DESC
");
$principals_stmt->execute();
$principals_result = $principals_stmt->get_result();
$principals = $principals_result->fetch_all(MYSQLI_ASSOC);

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'manage';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Management</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .current-principal-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .required::after {
            content: " *";
            color: red;
        }
        .nav-pills .nav-link.active {
            background-color: #667eea;
        }
        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <?php include '../navs/adminNav.php'; ?>
    
    <div class="container mt-4">
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'manage' ? 'active' : ''; ?>" 
                        id="pills-manage-tab" data-bs-toggle="pill" data-bs-target="#pills-manage" type="button" role="tab">
                    <i class="bi bi-list"></i> Manage Principals
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'add' ? 'active' : ''; ?>" 
                        id="pills-add-tab" data-bs-toggle="pill" data-bs-target="#pills-add" type="button" role="tab">
                    <i class="bi bi-person-plus"></i> Add New Principal
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="pills-tabContent">
            <!-- Manage Principals Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'manage' ? 'show active' : ''; ?>" 
                 id="pills-manage" role="tabpanel">
                
                <?php if (empty($principals)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person-x display-1 text-muted"></i>
                        <h5 class="mt-3">No principals found</h5>
                        <p class="text-muted">Get started by adding your first principal.</p>
                        <button type="button" class="btn btn-primary" onclick="switchToAddTab()">
                            Add Principal
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Current Principal Info -->
                    <?php if ($current_principal): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-person-check"></i> Current Active Principal</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($current_principal['FullName']); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Position:</strong> <?php echo htmlspecialchars($current_principal['Position']); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($current_principal['Email']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Principals List -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-list"></i> Principals List</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($principals as $principal): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($principal['FullName']); ?></td>
                                                <td><?php echo htmlspecialchars($principal['Position']); ?></td>
                                                <td><?php echo htmlspecialchars($principal['Email']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $principal['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo ucfirst($principal['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($principal['CreatedAt'])); ?></td>
                                                <td>
                                                    <?php if ($principal['status'] === 'inactive'): ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="admin_id" value="<?php echo $principal['AdminID']; ?>">
                                                            <button type="submit" name="activate_principal" class="btn btn-sm btn-success" 
                                                                    onclick="return confirm('Activate this principal? This will deactivate the current active principal.')">
                                                                <i class="bi bi-check-circle"></i> Activate
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle"></i> Active
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Principal Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'add' ? 'show active' : ''; ?>" 
                 id="pills-add" role="tabpanel">
                
                <!-- Current Principal Warning -->
                <?php if ($current_principal): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-person-check"></i> Current Active Principal</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Name:</strong> <?php echo htmlspecialchars($current_principal['FullName']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Position:</strong> <?php echo htmlspecialchars($current_principal['Position']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Email:</strong> <?php echo htmlspecialchars($current_principal['Email']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Add New Principal Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> New Principal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="addPrincipalForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                           required placeholder="Enter full name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Position</label>
                                    <select class="form-select" name="position" required>
                                        <option value="">Select Position</option>
                                        <option value="Head teacher" <?php echo (($_POST['position'] ?? '') === 'Head teacher') ? 'selected' : ''; ?>>Head Teacher</option>
                                        <option value="Principal" <?php echo (($_POST['position'] ?? '') === 'Principal') ? 'selected' : ''; ?>>Principal</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required placeholder="Enter email address">
                                    <div class="form-text">This will be used for login</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Password</label>
                                    <input type="password" class="form-control" name="password" 
                                           minlength="6" required placeholder="Enter password (min. 6 characters)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           minlength="6" required placeholder="Confirm password">
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required fw-bold">Admin Password</label>
                                    <input type="password" class="form-control" name="admin_password" 
                                           required placeholder="Enter your admin password">
                                    <div class="form-text">For security verification</div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle"></i> Important Notice</h6>
                                <ul class="mb-0">
                                    <li>The current principal will be set to <strong>inactive</strong></li>
                                    <li>This action requires admin password verification</li>
                                    <li>The new principal will have full administrative access</li>
                                    <li>This action will be logged for security purposes</li>
                                </ul>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="submit" name="add_principal" class="btn btn-primary" 
                                        onclick="return confirmAddPrincipal()">
                                    <i class="bi bi-person-plus"></i> Add New Principal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchToAddTab() {
            const addTab = new bootstrap.Tab(document.getElementById('pills-add-tab'));
            addTab.show();
        }
        
        function switchToManageTab() {
            const manageTab = new bootstrap.Tab(document.getElementById('pills-manage-tab'));
            manageTab.show();
        }
        
        function confirmAddPrincipal() {
            const currentPrincipal = "<?php echo $current_principal ? htmlspecialchars($current_principal['FullName']) : 'None'; ?>";
            const newPrincipal = document.querySelector('input[name="full_name"]').value;
            
            return confirm(`Are you sure you want to add a new principal?\n\nCurrent Principal: ${currentPrincipal}\nNew Principal: ${newPrincipal}\n\nThis will set the current principal to inactive.`);
        }
        
        // Real-time password validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            if (password && confirmPassword) {
                password.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
            
            // Set active tab based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.getElementById(`pills-${tab}-tab`);
                if (tabElement) {
                    const bsTab = new bootstrap.Tab(tabElement);
                    bsTab.show();
                }
            }
        });
    </script>
</body>
</html>