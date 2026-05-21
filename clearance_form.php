<?php
$page_title = "Clearance Certificate";
$required_role = ROLE_STUDENT;
require_once(__DIR__ . '/../includes/header.php');
require_once(__DIR__ . '/../classes/Student.php');
require_once(__DIR__ . '/../classes/Registrar.php');

$db = Database::getInstance()->getConnection();
$student = new Student($db);
$student->loadStudentData($_SESSION['user_id']);

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

// Verify the request belongs to this student
$sql = "SELECT id FROM clearance_requests 
        WHERE id = :request_id AND student_id = :student_id AND overall_status = 'completed'";
$valid_request = $db->fetchSingle($sql, [
    ':request_id' => $request_id,
    ':student_id' => $student->getId()
]);

if (!$valid_request) {
    setFlashMessage('Invalid certificate request', 'danger');
    redirect('status.php');
}

// Get certificate data
$registrar = new Registrar($db);
$certificate_data = $registrar->generateCertificate($request_id);

// Handle PDF generation request
if (isset($_GET['download']) ){
    require_once(__DIR__ . '/../includes/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Unity University Clearance System');
    $pdf->SetAuthor('Unity University');
    $pdf->SetTitle('Clearance Certificate');
    $pdf->SetSubject('Student Clearance Certificate');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Add a page
    $pdf->AddPage();
    
    // University logo and header
    $pdf->Image(__DIR__ . '/../assets/images/university_logo.png', 15, 15, 30, '', 'PNG');
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'UNITY UNIVERSITY', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'OFFICE OF THE REGISTRAR', 0, 1, 'C');
    
    // Certificate title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 15, 'CLEARANCE CERTIFICATE', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Student information
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(40, 10, 'Student Name:', 0, 0);
    $pdf->Cell(0, 10, $certificate_data['request']['student_name'], 0, 1);
    $pdf->Cell(40, 10, 'Student ID:', 0, 0);
    $pdf->Cell(0, 10, $certificate_data['request']['student_id'], 0, 1);
    $pdf->Cell(40, 10, 'Program:', 0, 0);
    $pdf->Cell(0, 10, $certificate_data['request']['program'], 0, 1);
    $pdf->Cell(40, 10, 'Academic Year:', 0, 0);
    $pdf->Cell(0, 10, $certificate_data['request']['academic_year'], 0, 1);
    $pdf->Cell(40, 10, 'Semester:', 0, 0);
    $pdf->Cell(0, 10, $certificate_data['request']['semester'], 0, 1);
    $pdf->Ln(10);
    
    // Clearance details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DEPARTMENT CLEARANCES:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    // Table header
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(80, 10, 'Department', 1, 0, 'C', 1);
    $pdf->Cell(40, 10, 'Status', 1, 0, 'C', 1);
    $pdf->Cell(40, 10, 'Date Approved', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFillColor(255, 255, 255);
    foreach ($certificate_data['departments'] as $dept) {
        $pdf->Cell(80, 10, $dept['department_name'], 1, 0, 'L', 1);
        $pdf->Cell(40, 10, ucfirst($dept['status']), 1, 0, 'C', 1);
        $pdf->Cell(40, 10, $dept['action_date'] ? date('M j, Y', strtotime($dept['action_date'])) : 'N/A', 1, 1, 'C', 1);
    }
    
    $pdf->Ln(15);
    
    // Registrar signature
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'CERTIFIED BY:', 0, 1, 'L');
    $pdf->Ln(15);
    
    $pdf->Cell(0, 10, '_________________________________________', 0, 1, 'L');
    $pdf->Cell(0, 10, 'REGISTRAR', 0, 1, 'L');
    $pdf->Cell(0, 10, 'UNITY UNIVERSITY', 0, 1, 'L');
    
    // Date
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Date: ' . date('F j, Y'), 0, 1, 'R');
    
    // Certificate number
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Certificate ID: UUCC-' . str_pad($request_id, 6, '0', STR_PAD_LEFT), 0, 1, 'R');
    
    // Output PDF
    $pdf->Output('clearance_certificate_' . $certificate_data['request']['student_id'] . '.pdf', 'D');
    exit;
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-certificate me-2"></i>Clearance Certificate
            </h5>
        </div>
        <div class="card-body">
            <div class="certificate-preview border p-4 mb-4" style="background-color: #f8f9fa;">
                <div class="text-center mb-4">
                    <img src="<?php echo SITE_URL; ?>/assets/images/university_logo.png" alt="University Logo" style="height: 80px;">
                    <h2 class="mt-2">UNITY UNIVERSITY</h2>
                    <h4>OFFICE OF THE REGISTRAR</h4>
                </div>
                
                <h3 class="text-center mb-4 text-decoration-underline">CLEARANCE CERTIFICATE</h3>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Student Name:</strong> <?php echo htmlspecialchars($certificate_data['request']['student_name']); ?></p>
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($certificate_data['request']['student_id']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Program:</strong> <?php echo htmlspecialchars($certificate_data['request']['program']); ?></p>
                        <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($certificate_data['request']['academic_year']); ?></p>
                        <p><strong>Semester:</strong> <?php echo htmlspecialchars($certificate_data['request']['semester']); ?></p>
                    </div>
                </div>
                
                <h5 class="mt-4 mb-3">DEPARTMENT CLEARANCES:</h5>
                
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Date Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificate_data['departments'] as $dept): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $dept['status'] === 'approved' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($dept['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $dept['action_date'] ? date('M j, Y', strtotime($dept['action_date'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mt-5">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="mb-1">____________________________</p>
                            <p class="mb-0"><strong>REGISTRAR</strong></p>
                            <p class="mb-0">UNITY UNIVERSITY</p>
                        </div>
                        <div class="text-end">
                            <p class="mb-0"><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
                            <p class="mb-0"><small>Certificate ID: UUCC-<?php echo str_pad($request_id, 6, '0', STR_PAD_LEFT); ?></small></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <a href="certificate.php?request_id=<?php echo $request_id; ?>&download=1" class="btn btn-primary me-2">
                    <i class="fas fa-download me-1"></i>Download PDF
                </a>
                <a href="status.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Status
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../includes/footer.php');
?>