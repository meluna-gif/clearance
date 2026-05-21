<?php
$page_title = "Officer Dashboard";
$required_role = ROLE_OFFICER;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Officer.php');

$db = Database::getInstance()->getConnection();
$officer = new Officer($db);
$officer->loadOfficerData($_SESSION['user_id']);

// Get pending clearance requests
$pending_requests = $officer->getPendingRequests();

// Get department properties
$properties = $officer->getDepartmentProperties();

// Get notifications
$notifications = $officer->getNotifications(5);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Officer Profile Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-tie me-2"></i>Officer Profile
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <img src="<?php echo SITE_URL; ?>/assets/images/avatar.png" 
                             class="rounded-circle" width="100" alt="Officer Avatar">
                    </div>
                    <h5 class="card-title text-center"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Employee ID:</strong> <?php echo htmlspecialchars($officer->getEmployeeId()); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Department:</strong> <?php echo htmlspecialchars($officer->getDepartmentName()); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Position:</strong> <?php echo htmlspecialchars($officer->getPosition()); ?>
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
                            <h3 class="mb-0"><?php echo count($pending_requests); ?></h3>
                            <small class="text-muted">Pending Requests</small>
                        </div>
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($properties); ?></h3>
                            <small class="text-muted">Properties</small>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: 75%;" 
                             aria-valuenow="75" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            75% Completion
                        </div>
                    </div>
                    <small class="text-muted">Department clearance completion rate</small>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Pending Requests Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Pending Clearance Requests
                    </h5>
                    <span class="badge bg-white text-primary">
                        <?php echo count($pending_requests); ?> Pending
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_requests)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Program</th>
                                        <th>Year</th>
                                        <th>Request Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['program']); ?></td>
                                            <td><?php echo htmlspecialchars($request['year_of_study']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['request_date'])); ?></td>
                                            <td>
                                                <a href="process_request.php?request_id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Process
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
                            You don't have any pending clearance requests at the moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Department Properties Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-boxes me-2"></i>Department Properties
                    </h5>
                    <a href="property_management.php" class="btn btn-sm btn-light">
                        <i class="fas fa-cog"></i> Manage
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($properties)): ?>
                        <div class="row">
                            <?php foreach (array_slice($properties, 0, 4) as $property): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars($property['name']); ?>
                                                <span class="badge bg-<?php echo $property['is_available'] ? 'success' : 'danger'; ?> float-end">
                                                    <?php echo $property['is_available'] ? 'Available' : 'Checked Out'; ?>
                                                </span>
                                            </h6>
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars(truncate($property['description'], 60)); ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    Qty: <?php echo $property['quantity']; ?>
                                                </small>
                                                <a href="property_management.php?property_id=<?php echo $property['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($properties) > 4): ?>
                            <div class="text-center mt-2">
                                <a href="property_management.php" class="btn btn-sm btn-outline-primary">
                                    View All Properties (<?php echo count($properties); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No properties registered in your department yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>