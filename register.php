<?php
require_once(__DIR__ . '/../config/constants.php');
require_once(__DIR__ . '/../includes/functions.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action
if (isset($_SESSION['user_id'])) {
    require_once(__DIR__ . '/../classes/Database.php');
    $db = Database::getInstance()->getConnection();
    logAction($_SESSION['user_id'], 'logout', "User logged out");
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>