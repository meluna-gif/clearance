<?php
require_once(__DIR__ . '/../config/constants.php');
require_once(__DIR__ . '/../classes/Database.php');
require_once(__DIR__ . '/../classes/User.php');
require_once(__DIR__ . '/../classes/Student.php');
require_once(__DIR__ . '/../classes/Officer.php');
require_once(__DIR__ . '/../classes/Registrar.php');
require_once(__DIR__ . '/../classes/Auditor.php');
require_once(__DIR__ . '/../includes/functions.php');

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    redirectBasedOnRole($_SESSION['role']);
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Create appropriate user object based on login attempt
        // We'll try to determine the user type based on the username format
        // (This is a simplified approach - in a real system you'd have a better way)
        
        $user = new User($db);
        
        if ($user->login($username, $password)) {
            // Login successful - determine user type and load specific data
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['username'] = $user->getUsername();
            $_SESSION['role'] = $user->getRole();
            $_SESSION['full_name'] = $user->getFullName();
            
            // Load role-specific data
            switch ($user->getRole()) {
                case ROLE_STUDENT:
                    $student = new Student($db);
                    if ($student->loadStudentData($user->getId())) {
                        $_SESSION['student_id'] = $student->getStudentId();
                        $_SESSION['program'] = $student->getProgram();
                    }
                    break;
                    
                case ROLE_OFFICER:
                    $officer = new Officer($db);
                    if ($officer->loadOfficerData($user->getId())) {
                        $_SESSION['employee_id'] = $officer->getEmployeeId();
                        $_SESSION['department_id'] = $officer->getDepartmentId();
                        $_SESSION['department_name'] = $officer->getDepartmentName();
                        $_SESSION['position'] = $officer->getPosition();
                    }
                    break;
                    
                case ROLE_REGISTRAR:
                    $registrar = new Registrar($db);
                    if ($registrar->loadRegistrarData($user->getId())) {
                        $_SESSION['registrar_id'] = $registrar->getRegistrarId();
                        $_SESSION['is_super_admin'] = $registrar->isSuperAdmin();
                    }
                    break;
                    
                case ROLE_AUDITOR:
                    $auditor = new Auditor($db);
                    if ($auditor->loadAuditorData($user->getId())) {
                        $_SESSION['auditor_id'] = $auditor->getAuditorId();
                        $_SESSION['access_level'] = $auditor->getAccessLevel();
                    }
                    break;
            }
            
            // Log the login action
            logAction($user->getId(), 'login', "User logged in from IP: {$_SERVER['REMOTE_ADDR']}");
            
            // Redirect to appropriate dashboard
            redirectBasedOnRole($user->getRole());
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Include header
$page_title = "Login - " . SITE_NAME;
include(__DIR__ . '/../includes/header.php');
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4><?php echo SITE_NAME; ?></h4>
                    <p class="mb-0">Please sign in</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required autofocus>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <small>Don't have an account? Contact the registrar's office</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include(__DIR__ . '/../includes/footer.php');
?>