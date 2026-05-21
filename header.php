<?php
require_once(__DIR__ . '/../config/constants.php');
require_once(__DIR__ . '/../classes/Database.php');

// Initialize database connection
$db = Database::getInstance()->getConnection();

/**
 * Redirect to a specific URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Redirect based on user role
 */
function redirectBasedOnRole($role) {
    switch ($role) {
        case ROLE_STUDENT:
            redirect(SITE_URL . '/student/dashboard.php');
            break;
        case ROLE_OFFICER:
            redirect(SITE_URL . '/officer/dashboard.php');
            break;
        case ROLE_REGISTRAR:
            redirect(SITE_URL . '/registrar/dashboard.php');
            break;
        case ROLE_AUDITOR:
            redirect(SITE_URL . '/auditor/dashboard.php');
            break;
        default:
            redirect(SITE_URL . '/auth/login.php');
    }
}

/**
 * Get dashboard link based on user role
 */
function getDashboardLink() {
    if (!isset($_SESSION['role'])) {
        return SITE_URL . '/auth/login.php';
    }
    
    switch ($_SESSION['role']) {
        case ROLE_STUDENT:
            return SITE_URL . '/student/dashboard.php';
        case ROLE_OFFICER:
            return SITE_URL . '/officer/dashboard.php';
        case ROLE_REGISTRAR:
            return SITE_URL . '/registrar/dashboard.php';
        case ROLE_AUDITOR:
            return SITE_URL . '/auditor/dashboard.php';
        default:
            return SITE_URL . '/auth/login.php';
    }
}

/**
 * Get profile link based on user role
 */
function getProfileLink() {
    if (!isset($_SESSION['role'])) {
        return '#';
    }
    
    switch ($_SESSION['role']) {
        case ROLE_STUDENT:
            return SITE_URL . '/student/profile.php';
        case ROLE_OFFICER:
            return SITE_URL . '/officer/profile.php';
        case ROLE_REGISTRAR:
            return SITE_URL . '/registrar/profile.php';
        case ROLE_AUDITOR:
            return SITE_URL . '/auditor/profile.php';
        default:
            return '#';
    }
}

/**
 * Get notifications link based on user role
 */
function getNotificationsLink() {
    return SITE_URL . '/notifications.php';
}

/**
 * Check if user has unread notifications
 */
function hasUnreadNotifications() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $db;
    $sql = "SELECT COUNT(*) AS count FROM notifications 
            WHERE user_id = :user_id AND is_read = 0";
    $result = $db->fetchSingle($sql, [':user_id' => $_SESSION['user_id']]);
    return $result['count'] > 0;
}

/**
 * Get count of unread notifications
 */
function getUnreadNotificationCount() {
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }
    
    global $db;
    $sql = "SELECT COUNT(*) AS count FROM notifications 
            WHERE user_id = :user_id AND is_read = 0";
    $result = $db->fetchSingle($sql, [':user_id' => $_SESSION['user_id']]);
    return $result['count'];
}

/**
 * Get recent notifications
 */
function getRecentNotifications($limit = 5) {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    global $db;
    $sql = "SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit";
    return $db->fetchAll($sql, [':user_id' => $_SESSION['user_id'], ':limit' => $limit]);
}

/**
 * Log an action to the audit log
 */
function logAction($user_id, $action, $description) {
    global $db;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $sql = "INSERT INTO audit_logs (user_id, action, description, ip_address) 
            VALUES (:user_id, :action, :description, :ip_address)";
    return $db->executeQuery($sql, [
        ':user_id' => $user_id,
        ':action' => $action,
        ':description' => $description,
        ':ip_address' => $ip_address
    ]);
}

/**
 * Set a flash message
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $type;
}

/**
 * Format time as "time ago"
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time_diff = time() - $time;
    
    if ($time_diff < 60) {
        return "just now";
    } elseif ($time_diff < 3600) {
        $mins = floor($time_diff / 60);
        return "$mins min" . ($mins == 1 ? "" : "s") . " ago";
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return "$hours hour" . ($hours == 1 ? "" : "s") . " ago";
    } elseif ($time_diff < 2592000) {
        $days = floor($time_diff / 86400);
        return "$days day" . ($days == 1 ? "" : "s") . " ago";
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Truncate text with ellipsis
 */
function truncate($text, $length) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Check if user is logged in and has the required role
 */
function requireAuth($required_role = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        setFlashMessage('Please login to access that page', 'danger');
        redirect(SITE_URL . '/auth/login.php');
    }
    
    if ($required_role && $_SESSION['role'] !== $required_role) {
        setFlashMessage('You do not have permission to access that page', 'danger');
        redirect(getDashboardLink());
    }
}

/**
 * Generate a random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $max_size = MAX_FILE_SIZE, $allowed_types = ALLOWED_FILE_TYPES) {
    $errors = [];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error: " . $file['error'];
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = "File size exceeds maximum allowed size of " . ($max_size / 1024 / 1024) . "MB";
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>