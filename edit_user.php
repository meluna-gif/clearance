<?php
$page_title = "Registrar Dashboard";
$required_role = ROLE_REGISTRAR;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Registrar.php');

$db = Database::getInstance()->getConnection();
$registrar = new Registrar($db);
$registrar->loadRegistrarData($_SESSION['user_id']);

// Get approved clearance requests ready for final approval
$approved_requests = $registrar->getApprovedRequests();

// Get recent user activities
$sql = "SELECT al.*, u.full_name, u.role 
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 5";
$recent_activities = $db->fetchAll($sql);

// Get notifications
$notifications = $registrar->getNotifications(5);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Registrar Profile Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-shield me-2"></i>Registrar Profile
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <img src="<?php echo SITE_URL; ?>/assets/images/avatar.png" 
                             class="rounded-circle" width="100" alt="Registrar Avatar">
                    </div>
                    <h5 class="card-title text-center"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Registrar ID:</strong> <?php echo htmlspecialchars($registrar->getRegistrarId()); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Role:</strong> <?php echo $registrar->isSuperAdmin() ? 'Super Admin' : 'Registrar'; ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Last Login:</strong> <?php echo $_SESSION['last_login'] ? date('M j, Y g:i A', strtotime($_SESSION['last_login'])) : 'N/A'; ?>
                        </li>
                    </ul>
                    <div class="mt-3 text-center">
                        <a href="profile.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Quick Stats
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($approved_requests); ?></h3>
                            <small class="text-muted">Pending Final Approval</small>
                        </div>
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $sql = "SELECT COUNT(*) AS count FROM users";
                                $result = $db->fetchSingle($sql);
                                echo $result['count'];
                                ?>
                            </h3>
                            <small class="text-muted">System Users</small>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: 85%;" 
                             aria-valuenow="85" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            85% Completion
                        </div>
                    </div>
                    <small class="text-muted">Overall clearance completion rate</small>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Pending Final Approval Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check-circle me-2"></i>Pending Final Approval
                    </h5>
                    <span class="badge bg-white text-primary">
                        <?php echo count($approved_requests); ?> Pending
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($approved_requests)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Program</th>
                                        <th>Academic Year</th>
                                        <th>Semester</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_requests as $request): ?>
                                        <tr>
                                            <td><?php echo $request['id']; ?></td>
                                            <td><?php echo htmlspecialchars($request['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['program']); ?></td>
                                            <td><?php echo htmlspecialchars($request['academic_year']); ?></td>
                                            <td><?php echo htmlspecialchars($request['semester']); ?></td>
                                            <td>
                                                <a href="final_approval.php?request_id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            No clearance requests pending final approval at the moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Recent Activities
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-item-marker">
                                    <div class="timeline-item-marker-indicator bg-<?php 
                                        echo $activity['action'] === 'login' ? 'success' : 
                                             ($activity['action'] === 'logout' ? 'danger' : 'info');
                                    ?>"></div>
                                </div>
                                <div class="timeline-item-content">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">
                                            <?php echo $activity['user_id'] ? htmlspecialchars($activity['full_name']) : 'System'; ?>
                                            <small class="text-muted">(<?php echo $activity['role'] ?: 'system'; ?>)</small>
                                        </span>
                                        <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php echo ucfirst($activity['action']); ?>: 
                                        <?php echo htmlspecialchars(truncate($activity['description'], 80)); ?>
                                    </p>
                                    <small class="text-muted">IP: <?php echo $activity['ip_address']; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="../auditor/audit_logs.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>View All Activities
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 1rem;
    margin: 0 0 0 1rem;
    border-left: 1px solid #e9ecef;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-item-marker {
    position: absolute;
    left: -1.5rem;
    width: 1rem;
    height: 1rem;
    margin-top: 0.25rem;
}

.timeline-item-marker-indicator {
    width: 12px;
    height: 12px;
    border-radius: 100%;
    border: 3px solid #fff;
}

.timeline-item-content {
    padding-left: 0.5rem;
}
</style>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>