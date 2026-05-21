<?php
require_once(__DIR__ . '/../config/constants.php');
require_once(__DIR__ . '/../classes/Database.php');
require_once(__DIR__ . '/../includes/functions.php');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        // Check if email exists
        $sql = "SELECT id, email, full_name FROM users WHERE email = :email";
        $user = $db->fetchSingle($sql, [':email' => $email]);
        
        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing tokens for this user
            $sql = "DELETE FROM password_reset_tokens WHERE user_id = :user_id";
            $db->executeQuery($sql, [':user_id' => $user['id']]);
            
            // Store the new token
            $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                    VALUES (:user_id, :token, :expires_at)";
            $db->executeQuery($sql, [
                ':user_id' => $user['id'],
                ':token' => $token,
                ':expires_at' => $expires_at
            ]);
            
            // Send email with reset link (in a real system)
            $reset_link = SITE_URL . "/auth/reset_password.php?token=$token";
            
            // In a real system, you would send an email here
            // For this example, we'll just display the link
            $message = "Password reset link (normally sent by email): <a href='$reset_link'>$reset_link</a>";
            
            // Log the action
            logAction($user['id'], 'password_reset_request', "Password reset requested");
        } else {
            $error = 'No account found with that email address';
        }
    }
}

// Include header
$page_title = "Forgot Password - " . SITE_NAME;
include(__DIR__ . '/../includes/header.php');
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4>Forgot Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php else: ?>
                        <p>Enter your email address and we'll send you a link to reset your password.</p>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php">Back to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include(__DIR__ . '/../includes/footer.php');
?>