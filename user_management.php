<?php
$page_title = "Registrar Profile";
$required_role = ROLE_REGISTRAR;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Registrar.php');

$db = Database::getInstance()->getConnection();
$registrar = new Registrar($db);
$registrar->loadRegistrarData($_SESSION['user_id']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate inputs
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if password is being changed
    if (!empty($current_password) {
        if (empty($new_password) || empty($confirm_password)) {
            $errors[] = "New password and confirmation are required when changing password";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New password and confirmation do not match";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
    }
    
    if (empty($errors)) {
        $db->beginTransaction();
        
        try {
            // Update basic profile info
            $sql = "UPDATE users 
                    SET full_name = :full_name, email = :email 
                    WHERE id = :user_id";
            $params = [
                ':full_name' => $full_name,
                ':email' => $email,
                ':user_id' => $registrar->getId()
            ];
            
            // If password is being changed
            if (!empty($current_password)) {
                // Verify current password
                $sql_check = "SELECT password_hash FROM users WHERE id = :user_id";
                $user = $db->fetchSingle($sql_check, [':user_id' => $registrar->getId()]);
                
                if (!$user || !password_verify($current_password, $user['password_hash'])) {
                    $errors[] = "Current password is incorrect";
                    $db->rollBack();
                } else {
                    $sql = "UPDATE users 
                            SET full_name = :full_name, email = :email, 
                                password_hash = :password_hash 
                            WHERE id = :user_id";
                    $params[':password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            
            if (empty($errors)) {
                $db->executeQuery($sql, $params);
                $db->commit();
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                setFlashMessage("Profile updated successfully", 'success');
                logAction($registrar->getId(), 'profile_update', "Updated profile information");
                redirect('profile.php');
            }
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-circle me-2"></i>Profile Information
                    </h5>
                </div>
                <div class="card-body text-center">
                    <img src="<?php echo SITE_URL; ?>/assets/images/avatar.png" 
                         class="rounded-circle mb-3" width="150" alt="Profile Picture">
                    <h4><?php echo htmlspecialchars($registrar->getFullName()); ?></h4>
                    <p class="text-muted mb-1">
                        <i class="fas fa-id-card me-1"></i>
                        <?php echo htmlspecialchars($registrar->getRegistrarId()); ?>
                    </p>
                    <p class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        <?php echo $registrar->isSuperAdmin() ? 'Super Administrator' : 'Registrar'; ?>
                    </p>
                </div>
            </div>
            
            <!-- Account Activity -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $sql = "SELECT * FROM audit_logs 
                            WHERE user_id = :user_id 
                            ORDER BY created_at DESC 
                            LIMIT 5";
                    $activities = $db->fetchAll($sql, [':user_id' => $registrar->getId()]);
                    ?>
                    
                    <?php if (!empty($activities)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <small><?php echo ucfirst($activity['action']); ?></small>
                                        <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars(truncate($activity['description'], 50)); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="text-center mt-2">
                            <a href="../auditor/audit_logs.php?user_id=<?php echo $registrar->getId(); ?>" 
                               class="btn btn-sm btn-outline-primary">
                                View All Activity
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No recent activity found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Profile Update Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-edit me-2"></i>Update Profile
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading">Please fix the following errors:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($registrar->getFullName()); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($registrar->getEmail()); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" 
                                   value="<?php echo htmlspecialchars($registrar->getUsername()); ?>" readonly>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Change Password</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Leave password fields blank if you don't want to change your password.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            <div class="col-md-4">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            <div class="col-md-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>System Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Account Details</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong>Registered:</strong> 
                                    <?php echo date('M j, Y', strtotime($registrar->getCreatedAt())); ?>
                                </li>
                                <li class="mb-2">
                                    <strong>Last Login:</strong> 
                                    <?php echo $registrar->getLastLogin() ? date('M j, Y g:i A', strtotime($registrar->getLastLogin())) : 'Never'; ?>
                                </li>
                                <li class="mb-2">
                                    <strong>IP Address:</strong> 
                                    <?php echo $_SERVER['REMOTE_ADDR']; ?>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>System Status</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong>Database:</strong> 
                                    <span class="badge bg-success">Connected</span>
                                </li>
                                <li class="mb-2">
                                    <strong>Clearance Requests:</strong> 
                                    <?php
                                    $sql = "SELECT COUNT(*) AS count FROM clearance_requests";
                                    $result = $db->fetchSingle($sql);
                                    echo $result['count'];
                                    ?>
                                </li>
                                <li class="mb-2">
                                    <strong>System Users:</strong> 
                                    <?php
                                    $sql = "SELECT COUNT(*) AS count FROM users";
                                    $result = $db->fetchSingle($sql);
                                    echo $result['count'];
                                    ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>