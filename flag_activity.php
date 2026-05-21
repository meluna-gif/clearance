<?php
$page_title = "Auditor Dashboard";
$required_role = ROLE_AUDITOR;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Auditor.php');

$db = Database::getInstance()->getConnection();
$auditor = new Auditor($db);
$auditor->loadAuditorData($_SESSION['user_id']);

// Get recent audit logs
$logs = $auditor->getAuditLogs(['limit' => 10]);

// Get system statistics
$sql = "SELECT COUNT(*) AS count FROM users";
$user_count = $db->fetchSingle($sql)['count'];

$sql = "SELECT COUNT(*) AS count FROM clearance_requests";
$request_count = $db->fetchSingle($sql)['count'];

$sql = "SELECT COUNT(*) AS count FROM department_clearances WHERE status = 'approved'";
$approval_count = $db->fetchSingle($sql)['count'];

// Get notifications
$notifications = $auditor->getNotifications(5);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Auditor Profile Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-shield me-2"></i>Auditor Profile
                    </h5>
                </div>
                <div class="card-body text-center">
                    <img src="<?php echo SITE_URL; ?>/assets/images/auditor.png" 
                         class="rounded-circle mb-3" width="120" alt="Auditor Avatar">
                    <h5><?php echo htmlspecialchars($auditor->getFullName()); ?></h5>
                    <p class="text-muted mb-1">
                        <i class="fas fa-id-badge me-1"></i>
                        <?php echo htmlspecialchars($auditor->getAuditorId()); ?>
                    </p>
                    <p class="text-muted">
                        <i class="fas fa-user-tag me-1"></i>
                        <?php echo ucfirst($auditor->getAccessLevel()); ?> Access
                    </p>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>System Stats
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="card bg-light p-2">
                                <h3 class="mb-0"><?php echo $user_count; ?></h3>
                                <small class="text-muted">Users</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card bg-light p-2">
                                <h3 class="mb-0"><?php echo $request_count; ?></h3>
                                <small class="text-muted">Requests</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-light p-2">
                                <h3 class="mb-0"><?php echo $approval_count; ?></h3>
                                <small class="text-muted">Approvals</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-light p-2">
                                <h3 class="mb-0"><?php echo count($logs); ?></h3>
                                <small class="text-muted">Today's Logs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Recent Activity Logs -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>Recent Activity Logs
                    </h5>
                    <a href="audit_logs.php" class="btn btn-sm btn-outline-light">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($logs)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('M j, H:i', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <span class="badge bg-info">User #<?php echo $log['user_id']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucfirst($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars(truncate($log['description'], 30)); ?></td>
                                            <td><?php echo $log['ip_address']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No activity logs found in the system.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="audit_logs.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search me-2"></i>Search Logs
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="audit_logs.php?export=1" class="btn btn-outline-success w-100">
                                <i class="fas fa-file-export me-2"></i>Export Logs
                            </a>
                        </div>
                        <?php if ($auditor->getAccessLevel() === 'admin'): ?>
                            <div class="col-md-6">
                                <a href="../registrar/user_management.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-users me-2"></i>User Management
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="../registrar/dashboard.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-tachometer-alt me-2"></i>Registrar Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>