<?php
require_once(__DIR__ . '/functions.php');

// Check if user is logged in and has the required role
$required_role = isset($required_role) ? $required_role : null;
requireAuth($required_role);

// For pages that require specific roles, we can set $required_role at the top of the page
// Example: $required_role = ROLE_STUDENT;
?>