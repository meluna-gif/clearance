<?php
$page_title = "Edit User";
$required_role = ROLE_REGISTRAR;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Registrar.php');

$db = Database::getInstance()->getConnection();
$registrar = new Registrar($db);
$registrar->loadRegistrarData($_SESSION['user_id']);

// Check if registrar has super admin privileges
if (!$registrar->isSuperAdmin()) {
    setFlashMessage('You do not have permission to access this page', 'danger');
    redirect('dashboard.php');
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get user details
$sql = "SELECT u.*, 
               s.student_id, s.program, s.year_of_study, s.phone,
               o.employee_id, o.department_id, o.position,
               r.registrar_id, r.is_super_admin,
               a.auditor_id, a.access_level
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN officers o ON u.id = o.user_id
        LEFT JOIN registrars r ON u.id = r.user_id
        LEFT JOIN auditors a ON u.id = a.user_id
        WHERE u.id = :user_id";
$user = $db->fetchSingle($sql, [':user_id' => $user_id]);

if (!$user) {
    setFlashMessage('User not found', 'danger');
    redirect('user_management.php');
}

// Get all departments for officer selection
$departments = $db->fetchAll("SELECT * FROM departments ORDER BY name");

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $full_name = sanitizeInput($_POST['full_name']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'];
    
    // Role-specific fields
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    $program = sanitizeInput($_POST['program'] ?? '');
    $year_of_study = sanitizeInput($_POST['year_of_study'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    $employee_id = sanitizeInput($_POST['employee_id'] ?? '');
    $department_id = sanitizeInput($_POST['department_id'] ?? '');
    $position = sanitizeInput($_POST['position'] ?? '');
    
    $registrar_id = sanitizeInput($_POST['registrar_id'] ?? '');
    $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
    
    $auditor_id = sanitizeInput($_POST['auditor_id'] ?? '');
    $access_level = sanitizeInput($_POST['access_level'] ?? 'basic');
    
    $errors = [];
    
    // Validate common fields
    if (empty($username) || empty($email) || empty($full_name)) {
        $errors[] = "Username, email, and full name are required";
    }
    
    // Validate role-specific fields
    if ($user['role'] === ROLE_STUDENT && (empty($student_id) || empty($program))) {
        $errors[] = "Student ID and program are required for students";
    }
    
    if ($user['role'] === ROLE_OFFICER && (empty($employee_id) || empty($department_id) || empty($position))) {
        $errors[] = "Employee ID, department, and position are required for officers";
    }
    
    if ($user['role'] === ROLE_REGISTRAR && empty($registrar_id)) {
        $errors[] = "Registrar ID is required for registrars";
    }
    
    if ($user['role'] === ROLE_AUDITOR && (empty($auditor_id) || empty($access_level))) {
        $errors[] = "Auditor ID and access level are required for auditors";
    }
    
    // Check if username or email already exists for another user
    $sql = "SELECT COUNT(*) AS count FROM users 
            WHERE (username = :username OR email = :email) AND id != :user_id";
    $result = $db->fetchSingle($sql, [
        ':username' => $username,
        ':email' => $email,
        ':user_id' => $user_id
    ]);
    
    if ($result['count'] > 0) {
        $errors[] = "Username or email already exists for another user";
    }
    
    // Check role-specific ID uniqueness
    if ($user['role'] === ROLE_STUDENT && $student_id !== $user['student_id']) {
        $sql = "SELECT COUNT(*) AS count FROM students WHERE student_id = :student_id";
        $result = $db->fetchSingle($sql, [':student_id' => $student_id]);
        if ($result['count'] > 0) $errors[] = "Student ID already exists";
    }
    
    if ($user['role'] === ROLE_OFFICER && $employee_id !== $user['employee_id']) {
        $sql = "SELECT COUNT(*) AS count FROM officers WHERE employee_id = :employee_id";
        $result = $db->fetchSingle($sql, [':employee_id' => $employee_id]);
        if ($result['count'] > 0) $errors[] = "Employee ID already exists";
    }
    
    if ($user['role'] === ROLE_REGISTRAR && $registrar_id !== $user['registrar_id']) {
        $sql = "SELECT COUNT(*) AS count FROM registrars WHERE registrar_id = :registrar_id";
        $result = $db->fetchSingle($sql, [':registrar_id' => $registrar_id]);
        if ($result['count'] > 0) $errors[] = "Registrar ID already exists";
    }
    
    if ($user['role'] === ROLE_AUDITOR && $auditor_id !== $user['auditor_id']) {
        $sql = "SELECT COUNT(*) AS count FROM auditors WHERE auditor_id = :auditor_id";
        $result = $db->fetchSingle($sql, [':auditor_id' => $auditor_id]);
        if ($result['count'] > 0) $errors[] = "Auditor ID already exists";
    }
    
    if (empty($errors)) {
        $db->beginTransaction();
        
        try {
            // Update user record
            $sql = "UPDATE users 
                    SET username = :username, email = :email, 
                        full_name = :full_name, is_active = :is_active
                    WHERE id = :user_id";
            
            $params = [
                ':username' => $username,
                ':email' => $email,
                ':full_name' => $full_name,
                ':is_active' => $is_active,
                ':user_id' => $user_id
            ];
            
            // Update password if provided
            if (!empty($password)) {
                $sql = "UPDATE users 
                        SET username = :username, email = :email, 
                            full_name = :full_name, is_active = :is_active,
                            password_hash = :password
                        WHERE id = :user_id";
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $db->executeQuery($sql, $params);
            
            // Update role-specific record
            switch ($user['role']) {
                case ROLE_STUDENT:
                    $sql = "UPDATE students 
                            SET student_id = :student_id, program = :program, 
                                year_of_study = :year_of_study, phone = :phone
                            WHERE user_id = :user_id";
                    $db->executeQuery($sql, [
                        ':student_id' => $student_id,
                        ':program' => $program,
                        ':year_of_study' => $year_of_study,
                        ':phone' => $phone,
                        ':user_id' => $user_id
                    ]);
                    break;
                    
                case ROLE_OFFICER:
                    $sql = "UPDATE officers 
                            SET employee_id = :employee_id, department_id = :department_id, 
                                position = :position
                            WHERE user_id = :user_id";
                    $db->executeQuery($sql, [
                        ':employee_id' => $employee_id,
                        ':department_id' => $department_id,
                        ':position' => $position,
                        ':user_id' => $user_id
                    ]);
                    break;
                    
                case ROLE_REGISTRAR:
                    $sql = "UPDATE registrars 
                            SET registrar_id = :registrar_id, is_super_admin = :is_super_admin
                            WHERE user_id = :user_id";
                    $db->executeQuery($sql, [
                        ':registrar_id' => $registrar_id,
                        ':is_super_admin' => $is_super_admin,
                        ':user_id' => $user_id
                    ]);
                    break;
                    
                case ROLE_AUDITOR:
                    $sql = "UPDATE auditors 
                            SET auditor_id = :auditor_id, access_level = :access_level
                            WHERE user_id = :user_id";
                    $db->executeQuery($sql, [
                        ':auditor_id' => $auditor_id,
                        ':access_level' => $access_level,
                        ':user_id' => $user_id
                    ]);
                    break;
            }
            
            $db->commit();
            
            setFlashMessage("User updated successfully", 'success');
            logAction($registrar->getId(), 'user_updated', "Updated user ID: $user_id");
            redirect('user_management.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Failed to update user: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-user-edit me-2"></i>Edit User: <?php echo htmlspecialchars($user['full_name']); ?>
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
                        <label for="role" class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="is_active" class="form-label">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Leave blank to keep current password">
                        <button class="btn btn-outline-secondary password-toggle" type="button" 
                                data-target="#password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Student Fields -->
                <?php if ($user['role'] === ROLE_STUDENT): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student ID *</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" 
                                   value="<?php echo htmlspecialchars($user['student_id']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="program" class="form-label">Program *</label>
                            <input type="text" class="form-control" id="program" name="program" 
                                   value="<?php echo htmlspecialchars($user['program']); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="year_of_study" class="form-label">Year of Study</label>
                            <select class="form-select" id="year_of_study" name="year_of_study">
                                <option value="">Select Year</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $user['year_of_study'] == $i ? 'selected' : ''; ?>>
                                        Year <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Officer Fields -->
                <?php if ($user['role'] === ROLE_OFFICER): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                   value="<?php echo htmlspecialchars($user['employee_id']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department *</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $user['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="position" class="form-label">Position *</label>
                        <input type="text" class="form-control" id="position" name="position" 
                               value="<?php echo htmlspecialchars($user['position']); ?>" required>
                    </div>
                <?php endif; ?>

                <!-- Registrar Fields -->
                <?php if ($user['role'] === ROLE_REGISTRAR): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="registrar_id" class="form-label">Registrar ID *</label>
                            <input type="text" class="form-control" id="registrar_id" name="registrar_id" 
                                   value="<?php echo htmlspecialchars($user['registrar_id']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="is_super_admin" class="form-label">Admin Privileges</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="is_super_admin" name="is_super_admin" value="1" 
                                       <?php echo $user['is_super_admin'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_super_admin">Super Admin</label>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Auditor Fields -->
                <?php if ($user['role'] === ROLE_AUDITOR): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="auditor_id" class="form-label">Auditor ID *</label>
                            <input type="text" class="form-control" id="auditor_id" name="auditor_id" 
                                   value="<?php echo htmlspecialchars($user['auditor_id']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="access_level" class="form-label">Access Level *</label>
                            <select class="form-select" id="access_level" name="access_level" required>
                                <option value="basic" <?php echo $user['access_level'] === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                <option value="admin" <?php echo $user['access_level'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="user_management.php" class="btn btn-outline-secondary me-md-2">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality
    document.querySelectorAll('.password-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            const input = document.querySelector(this.getAttribute('data-target'));
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
});
</script>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>