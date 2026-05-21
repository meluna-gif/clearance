<?php
// System constants
define('SITE_NAME', 'Unity University Clearance System');
define('SITE_URL', 'http://localhost/unity_clearance_system');

// User roles
define('ROLE_STUDENT', 'student');
define('ROLE_OFFICER', 'officer');
define('ROLE_REGISTRAR', 'registrar');
define('ROLE_AUDITOR', 'auditor');

// Clearance statuses
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_COMPLETED', 'completed');

// File upload settings
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
?>