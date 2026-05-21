<?php
$page_title = "Clearance Request Form";
$required_role = ROLE_STUDENT;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Student.php');

$db = Database::getInstance()->getConnection();
$student = new Student($db);
$student->loadStudentData($_SESSION['user_id']);

// Check if student has pending clearance
$sql = "SELECT id FROM clearance_requests 
        WHERE student_id = :student_id AND overall_status = 'pending' 
        LIMIT 1";
$pending_request = $db->fetchSingle($sql, [':student_id' => $student->getId()]);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pending_request) {
    $academic_year = sanitizeInput($_POST['academic_year']);
    $semester = sanitizeInput($_POST['semester']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($academic_year)) {
        $errors[] = "Academic year is required";
    }
    
    if (empty($semester)) {
        $errors[] = "Semester is required";
    }
    
    if (empty($errors)) {
        // Submit clearance request
        $request_id = $student->submitClearanceRequest($academic_year, $semester);
        
        if ($request_id) {
            setFlashMessage('Clearance request submitted successfully!', 'success');
            logAction($student->getId(), 'clearance_request', "Submitted clearance request #$request_id");
            redirect('status.php');
        } else {
            $errors[] = "Failed to submit clearance request. Please try again.";
        }
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-alt me-2"></i>Clearance Request Form
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($pending_request): ?>
                        <div class="alert alert-warning">
                            <h5 class="alert-heading">Pending Clearance Request</h5>
                            <p>You already have a pending clearance request. Please wait for it to be processed or contact the relevant departments if it's taking too long.</p>
                            <hr>
                            <a href="status.php" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>View Status
                            </a>
                        </div>
                    <?php else: ?>
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
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" 
                                           value="<?php echo htmlspecialchars($student->getFullName()); ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="program" class="form-label">Program</label>
                                    <input type="text" class="form-control" id="program" 
                                           value="<?php echo htmlspecialchars($student->getProgram()); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="year_of_study" class="form-label">Year of Study</label>
                                    <input type="text" class="form-control" id="year_of_study" 
                                           value="<?php echo htmlspecialchars($student->getYearOfStudy()); ?>" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="academic_year" class="form-label">Academic Year *</label>
                                <select class="form-select" id="academic_year" name="academic_year" required>
                                    <option value="">Select Academic Year</option>
                                    <?php
                                    $current_year = date('Y');
                                    for ($i = $current_year - 2; $i <= $current_year + 1; $i++): 
                                        $year_range = $i . '/' . ($i + 1);
                                    ?>
                                        <option value="<?php echo $year_range; ?>" 
                                            <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] === $year_range) ? 'selected' : ''; ?>>
                                            <?php echo $year_range; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester *</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="Semester 1" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'Semester 1') ? 'selected' : ''; ?>>Semester 1</option>
                                    <option value="Semester 2" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'Semester 2') ? 'selected' : ''; ?>>Semester 2</option>
                                    <option value="Summer" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'Summer') ? 'selected' : ''; ?>>Summer</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="declaration" required>
                                    <label class="form-check-label" for="declaration">
                                        I declare that all the information provided is accurate and complete. I understand that providing false information may result in disciplinary action.
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Submit Request
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>