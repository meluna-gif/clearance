<?php
$page_title = "Pending Clearance Requests";
$required_role = ROLE_OFFICER;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Officer.php');

$db = Database::getInstance()->getConnection();
$officer = new Officer($db);
$officer->loadOfficerData($_SESSION['user_id']);

// Get pending clearance requests
$pending_requests = $officer->getPendingRequests();

// Process bulk action if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = sanitizeInput($_POST['bulk_action']);
    $selected_requests = $_POST['selected_requests'] ?? [];
    
    if (!empty($selected_requests)) {
        $success_count = 0;
        
        foreach ($selected_requests as $request_id) {
            $comments = sanitizeInput($_POST['comments'][$request_id] ?? '');
            
            if ($action === 'approve') {
                $result = $officer->processClearanceRequest($request_id, 'approved', $comments);
            } elseif ($action === 'reject') {
                $result = $officer->processClearanceRequest($request_id, 'rejected', $comments);
            }
            
            if ($result) $success_count++;
        }
        
        if ($success_count > 0) {
            setFlashMessage("Successfully processed $success_count request(s)", 'success');
            logAction($officer->getId(), 'bulk_clearance_action', "Processed $success_count requests as $action");
            redirect('pending_requests.php');
        } else {
            setFlashMessage("Failed to process requests", 'danger');
        }
    } else {
        setFlashMessage("No requests selected", 'warning');
    }
}
?>

<div class="container mt-4">
    <div class="card">
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
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="select-all" class="form-check-input">
                                    </th>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Program</th>
                                    <th>Year</th>
                                    <th>Request Date</th>
                                    <th>Comments</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_requests[]" 
                                                   value="<?php echo $request['id']; ?>" 
                                                   class="form-check-input request-checkbox">
                                        </td>
                                        <td><?php echo htmlspecialchars($request['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['program']); ?></td>
                                        <td><?php echo htmlspecialchars($request['year_of_study']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($request['request_date'])); ?></td>
                                        <td>
                                            <input type="text" name="comments[<?php echo $request['id']; ?>]" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="Optional comments">
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="process_request.php?request_id=<?php echo $request['id']; ?>&status=approve" 
                                                   class="btn btn-outline-success" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="process_request.php?request_id=<?php echo $request['id']; ?>&status=reject" 
                                                   class="btn btn-outline-danger" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                <a href="view_request.php?request_id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <select name="bulk_action" class="form-select form-select-sm">
                                <option value="">Bulk Action</option>
                                <option value="approve">Approve Selected</option>
                                <option value="reject">Reject Selected</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-play me-1"></i>Apply
                            </button>
                            <button type="reset" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    You don't have any pending clearance requests at the moment.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.request-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
});
</script>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>