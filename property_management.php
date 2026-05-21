<?php
$page_title = "Property Loan History";
$required_role = ROLE_OFFICER;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Officer.php');

$db = Database::getInstance()->getConnection();
$officer = new Officer($db);
$officer->loadOfficerData($_SESSION['user_id']);

$property_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;

// Verify property belongs to officer's department
$sql = "SELECT id, name FROM properties 
        WHERE id = :id AND department_id = :department_id";
$property = $db->fetchSingle($sql, [
    ':id' => $property_id,
    ':department_id' => $officer->getDepartmentId()
]);

if (!$property) {
    setFlashMessage('Invalid property', 'danger');
    redirect('property_management.php');
}

// Get loan history for this property
$sql = "SELECT pl.*, s.student_id, u.full_name AS student_name
        FROM property_loans pl
        JOIN students s ON pl.student_id = s.user_id
        JOIN users u ON s.user_id = u.id
        WHERE pl.property_id = :property_id
        ORDER BY pl.borrowed_date DESC";
$loans = $db->fetchAll($sql, [':property_id' => $property_id]);

// Process new loan form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_loan') {
    $student_id = sanitizeInput($_POST['student_id']);
    $due_date = sanitizeInput($_POST['due_date']);
    
    // Get student user_id from student_id
    $sql = "SELECT user_id FROM students WHERE student_id = :student_id";
    $student = $db->fetchSingle($sql, [':student_id' => $student_id]);
    
    if (!$student) {
        setFlashMessage('Student not found', 'danger');
    } else {
        $result = $officer->recordPropertyLoan($property_id, $student['user_id'], $due_date);
        
        if ($result) {
            setFlashMessage('Property loan recorded successfully', 'success');
            logAction($officer->getId(), 'property_loan', "Loaned property ID: $property_id to student $student_id");
            redirect("property_loans.php?property_id=$property_id");
        } else {
            setFlashMessage('Failed to record property loan', 'danger');
        }
    }
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-history me-2"></i>Loan History: <?php echo htmlspecialchars($property['name']); ?>
            </h5>
            <a href="property_management.php" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1"></i>Back to Properties
            </a>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Record New Loan</h6>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="record_loan">
                                <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
                                
                                <div class="mb-3">
                                    <label for="student_id" class="form-label">Student ID *</label>
                                    <input type="text" class="form-control" id="student_id" name="student_id" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="due_date" class="form-label">Due Date *</label>
                                    <input type="date" class="form-control datepicker" id="due_date" name="due_date" required>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Record Loan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="mb-3">Loan History</h6>
            <?php if (!empty($loans)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Returned Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                                <tr class="<?php 
                                    echo ($loan['status'] === 'overdue') ? 'table-warning' : '';
                                    echo ($loan['status'] === 'lost') ? 'table-danger' : '';
                                ?>">
                                    <td>
                                        <?php echo htmlspecialchars($loan['student_name']); ?>
                                        <small class="text-muted d-block"><?php echo $loan['student_id']; ?></small>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($loan['borrowed_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($loan['due_date'])); ?></td>
                                    <td><?php echo $loan['returned_date'] ? date('M j, Y', strtotime($loan['returned_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo ($loan['status'] === 'active') ? 'primary' : '';
                                            echo ($loan['status'] === 'returned') ? 'success' : '';
                                            echo ($loan['status'] === 'overdue') ? 'warning' : '';
                                            echo ($loan['status'] === 'lost') ? 'danger' : '';
                                        ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($loan['status'] === 'active' || $loan['status'] === 'overdue'): ?>
                                            <form method="POST" action="property_management.php" class="d-inline">
                                                <input type="hidden" name="action" value="record_return">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as Returned">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" title="Mark as Lost" 
                                                data-bs-toggle="modal" data-bs-target="#lostModal<?php echo $loan['id']; ?>">
                                            <i class="fas fa-question"></i>
                                        </button>
                                        
                                        <!-- Lost Item Modal -->
                                        <div class="modal fade" id="lostModal<?php echo $loan['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Mark Item as Lost</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to mark this item as lost?</p>
                                                        <p><strong><?php echo htmlspecialchars($property['name']); ?></strong></p>
                                                        <p>Borrowed by: <?php echo htmlspecialchars($loan['student_name']); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            <i class="fas fa-times me-1"></i>Cancel
                                                        </button>
                                                        <form method="POST" action="property_management.php" class="d-inline">
                                                            <input type="hidden" name="action" value="mark_lost">
                                                            <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>Mark as Lost
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No loan history found for this property.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date picker
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            minDate: 'today'
        });
    }
});
</script>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>