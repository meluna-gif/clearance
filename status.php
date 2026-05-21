<?php
$page_title = "Student Profile";
$required_role = ROLE_STUDENT;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Student.php');

$db = Database::getInstance()->getConnection();
$student = new Student($db);
$student->loadStudentData($_SESSION['user_id']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address format";
    }
    
    // Validate phone
    if (!empty($phone) && !preg_match('/^[0-9+() -]+$/', $phone)) {
        $errors[] = "Phone number can only contain numbers, spaces, and +-()";
    }
    
    // Check if password is being changed
    $password_changed = false;
    if (!empty($current_password)) {
        if (empty($new_password) || empty($confirm_password)) {
            $errors[] = "New password and confirmation are required when changing password";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New password and confirmation do not match";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        } else {
            $password_changed = true;
        }
    }
    
    if (empty($errors)) {
        // Update profile
        $sql = "UPDATE users 
                SET full_name = :full_name, email = :email 
                WHERE id = :id";
        $params = [
            ':full_name' => $full_name,
            ':email' => $email,
            ':id' => $student->getId()
        ];
        
        $result = $db->executeQuery($sql, $params);
        
        if ($result) {
            // Update phone in students table
            $sql = "UPDATE students SET phone = :phone WHERE user_id = :user_id";
            $db->executeQuery($sql, [
                ':phone' => $phone,
                ':user_id' => $student->getId()
            ]);
            
            // Update password if changed
            if ($password_changed) {
                if ($student->changePassword($current_password, $new_password)) {
                    setFlashMessage('Profile and password updated successfully!', 'success');
                    logAction($student->getId(), 'profile_update', "Updated profile and password");
                } else {
                    setFlashMessage('Profile updated but password change failed (current password incorrect)', 'warning');
                }
            } else {
                setFlashMessage('Profile updated successfully!', 'success');
                logAction($student->getId(), 'profile_update', "Updated profile information");
            }
            
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            redirect('profile.php');
        } else {
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
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
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="student_id" 
                                       value="<?php echo htmlspecialchars($student->getStudentId()); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="program" class="form-label">Program</label>
                                <input type="text" class="form-control" id="program" 
                                       value="<?php echo htmlspecialchars($student->getProgram()); ?>" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($student->getFullName()); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student->getEmail()); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($student->getPhone()); ?>">
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Change Password</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Leave password fields blank if you don't want to change your password.
                        </div>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>