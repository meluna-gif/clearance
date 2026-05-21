<?php
$page_title = "Student Dashboard";
$required_role = ROLE_STUDENT;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Student.php');

$db = Database::getInstance()->getConnection();
$student = new Student($db);
$student->loadStudentData($_SESSION['user_id']);

// Get clearance status
$clearance_status = $student->getClearanceStatus();
$latest_request = !empty($clearance_status) ? $clearance_status[0] : null;

// Get borrowed properties
$borrowed_properties = $student->getBorrowedProperties();

// Get notifications
$notifications = $student->getNotifications(5);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Student Profile Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-graduate me-2"></i>Student Profile
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <img src="<?php echo SITE_URL; ?>/assets/images/avatar.png" 
                             class="rounded-circle" width="100" alt="Student Avatar">
                    </div>
                    <h5 class="card-title text-center"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Student ID:</strong> <?php echo htmlspecialchars($student->getStudentId()); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Program:</strong> <?php echo htmlspecialchars($student->getProgram()); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Year of Study:</strong> <?php echo htmlspecialchars($student->getYearOfStudy()); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </li>
                    </ul>
                    <div class="mt-3 text-center">
                        <a href="profile.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="clearance_form.php" class="btn btn-success">
                            <i class="fas fa-file-alt me-1"></i>New Clearance Request
                        </a>
                        <a href="status.php" class="btn btn-info">
                            <i class="fas fa-search me-1"></i>Check Clearance Status
                        </a>
                        <a href="certificate.php" class="btn btn-warning">
                            <i class="fas fa-certificate me-1"></i>View Certificates
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Clearance Status Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>Clearance Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($latest_request): ?>
                        <div class="alert alert-<?php echo getStatusAlertClass($latest_request['overall_status']); ?>">
                            <h5 class="alert-heading">
                                <?php echo ucfirst($latest_request['overall_status']); ?> Clearance
                            </h5>
                            <p class="mb-2">
                                <strong>Academic Year:</strong> <?php echo htmlspecialchars($latest_request['academic_year']); ?>
                                <br>
                                <strong>Semester:</strong> <?php echo htmlspecialchars($latest_request['semester']); ?>
                                <br>
                                <strong>Request Date:</strong> <?php echo date('M j, Y g:i A', strtotime($latest_request['request_date'])); ?>
                            </p>
                            <hr>
                            <a href="status.php" class="btn btn-sm btn-outline-primary">
                                View Full Details
                            </a>
                        </div>

                        <div class="progress mb-3" style="height: 25px;">
                            <?php 
                            $approved_count = 0;
                            $total_departments = 0;
                            foreach ($clearance_status as $status) {
                                if ($status['dept_status'] === 'approved') $approved_count++;
                                $total_departments++;
                            }
                            $progress = $total_departments > 0 ? ($approved_count / $total_departments) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $progress; ?>%" 
                                 aria-valuenow="<?php echo $progress; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?php echo round($progress); ?>% Complete
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Action Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clearance_status as $status): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($status['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($status['dept_status']); ?>">
                                                    <?php echo ucfirst($status['dept_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $status['action_date'] ? date('M j, Y', strtotime($status['action_date'])) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h5 class="alert-heading">No Clearance Requests Found</h5>
                            <p>You haven't submitted any clearance requests yet.</p>
                            <hr>
                            <a href="clearance_form.php" class="btn btn-primary">
                                <i class="fas fa-file-alt me-1"></i>Start New Clearance
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Borrowed Properties Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-box-open me-2"></i>Borrowed Properties
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($borrowed_properties)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Description</th>
                                        <th>Borrowed Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowed_properties as $property): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($property['name']); ?></td>
                                            <td><?php echo htmlspecialchars(truncate($property['description'], 30)); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($property['borrowed_date'])); ?></td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($property['due_date'])); ?>
                                                <?php if (strtotime($property['due_date']) < time()): ?>
                                                    <span class="badge bg-danger ms-1">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadgeClass($property['status']); ?>">
                                                    <?php echo ucfirst($property['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            You don't have any borrowed properties from the university.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to get badge class based on status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
        case 'returned':
        case 'completed':
            return 'success';
        case 'rejected':
        case 'lost':
            return 'danger';
        case 'pending':
            return 'warning';
        case 'overdue':
            return 'danger';
        case 'active':
            return 'primary';
        default:
            return 'secondary';
    }
}

// Helper function to get alert class based on status
function getStatusAlertClass($status) {
    switch ($status) {
        case 'approved':
        case 'completed':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'pending':
            return 'warning';
        default:
            return 'info';
    }
}

require_once(__DIR__ . '/../includes/footer.php');
?>