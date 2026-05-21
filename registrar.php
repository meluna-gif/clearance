<?php
require_once(__DIR__ . '/User.php');

class Officer extends User {
    private $employee_id;
    private $department_id;
    private $position;
    private $department_name;

    public function __construct($db) {
        parent::__construct($db);
    }

    public function loadOfficerData($user_id) {
        $sql = "SELECT o.*, u.username, u.email, u.full_name, u.role, d.name AS department_name
                FROM officers o 
                JOIN users u ON o.user_id = u.id 
                JOIN departments d ON o.department_id = d.id
                WHERE o.user_id = :user_id";
        $officer = $this->db->fetchSingle($sql, [':user_id' => $user_id]);

        if ($officer) {
            $this->id = $officer['user_id'];
            $this->username = $officer['username'];
            $this->email = $officer['email'];
            $this->full_name = $officer['full_name'];
            $this->role = $officer['role'];
            $this->employee_id = $officer['employee_id'];
            $this->department_id = $officer['department_id'];
            $this->position = $officer['position'];
            $this->department_name = $officer['department_name'];
            return true;
        }
        return false;
    }

    public function getPendingRequests() {
        $sql = "SELECT dc.id, cr.id AS request_id, cr.request_date, 
                       s.student_id, s.user_id AS student_user_id, u.full_name AS student_name,
                       s.program, s.year_of_study, cr.academic_year, cr.semester
                FROM department_clearances dc
                JOIN clearance_requests cr ON dc.request_id = cr.id
                JOIN students s ON cr.student_id = s.user_id
                JOIN users u ON s.user_id = u.id
                WHERE dc.department_id = :department_id 
                AND dc.status = 'pending'
                ORDER BY cr.request_date";
        return $this->db->fetchAll($sql, [':department_id' => $this->department_id]);
    }

    public function processClearanceRequest($clearance_id, $status, $comments = '') {
        $sql = "UPDATE department_clearances 
                SET status = :status, 
                    comments = :comments, 
                    officer_id = :officer_id, 
                    action_date = NOW()
                WHERE id = :id AND department_id = :department_id";
        
        $result = $this->db->executeQuery($sql, [
            ':status' => $status,
            ':comments' => $comments,
            ':officer_id' => $this->id,
            ':id' => $clearance_id,
            ':department_id' => $this->department_id
        ]);

        if ($result) {
            // Get student ID for notification
            $sql = "SELECT cr.student_id 
                    FROM department_clearances dc
                    JOIN clearance_requests cr ON dc.request_id = cr.id
                    WHERE dc.id = :id";
            $request = $this->db->fetchSingle($sql, [':id' => $clearance_id]);
            
            if ($request) {
                $this->sendNotification(
                    $request['student_id'],
                    "Clearance Update",
                    "Your clearance request has been $status by {$this->department_name}. Comments: $comments"
                );
            }
            
            // Check if all departments have approved
            $this->checkFullApproval($clearance_id);
            
            return true;
        }
        return false;
    }

    private function checkFullApproval($department_clearance_id) {
        // Get the request ID
        $sql = "SELECT request_id FROM department_clearances WHERE id = :id";
        $dc = $this->db->fetchSingle($sql, [':id' => $department_clearance_id]);
        
        if (!$dc) return false;
        
        $request_id = $dc['request_id'];
        
        // Check if any departments are still pending
        $sql = "SELECT COUNT(*) AS pending_count 
                FROM department_clearances 
                WHERE request_id = :request_id AND status = 'pending'";
        $result = $this->db->fetchSingle($sql, [':request_id' => $request_id]);
        
        if ($result['pending_count'] == 0) {
            // All departments have approved, update overall status
            $sql = "UPDATE clearance_requests SET overall_status = 'approved' WHERE id = :id";
            $this->db->executeQuery($sql, [':id' => $request_id]);
            
            // Notify registrar
            $this->notifyRegistrar($request_id);
        }
    }

    private function notifyRegistrar($request_id) {
        // Get registrar IDs
        $sql = "SELECT user_id FROM registrars";
        $registrars = $this->db->fetchAll($sql);
        
        // Get student info for notification
        $sql = "SELECT u.full_name, s.student_id 
                FROM clearance_requests cr
                JOIN students s ON cr.student_id = s.user_id
                JOIN users u ON s.user_id = u.id
                WHERE cr.id = :request_id";
        $student = $this->db->fetchSingle($sql, [':request_id' => $request_id]);
        
        foreach ($registrars as $registrar) {
            $this->sendNotification(
                $registrar['user_id'],
                "Clearance Ready for Final Approval",
                "Student {$student['full_name']} ({$student['student_id']}) has been approved by all departments."
            );
        }
    }

    private function sendNotification($user_id, $title, $message, $link = '') {
        $sql = "INSERT INTO notifications (user_id, title, message, link) 
                VALUES (:user_id, :title, :message, :link)";
        return $this->db->executeQuery($sql, [
            ':user_id' => $user_id,
            ':title' => $title,
            ':message' => $message,
            ':link' => $link
        ]);
    }

    // Department property management
    public function getDepartmentProperties() {
        $sql = "SELECT * FROM properties 
                WHERE department_id = :department_id
                ORDER BY name";
        return $this->db->fetchAll($sql, [':department_id' => $this->department_id]);
    }

    public function recordPropertyLoan($property_id, $student_id, $due_date) {
        $sql = "INSERT INTO property_loans (property_id, student_id, borrowed_date, due_date)
                VALUES (:property_id, :student_id, CURDATE(), :due_date)";
        
        $this->db->beginTransaction();
        
        try {
            // Record the loan
            $this->db->executeQuery($sql, [
                ':property_id' => $property_id,
                ':student_id' => $student_id,
                ':due_date' => $due_date
            ]);
            
            // Update property availability
            $sql = "UPDATE properties SET is_available = FALSE WHERE id = :id";
            $this->db->executeQuery($sql, [':id' => $property_id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error recording property loan: " . $e->getMessage());
            return false;
        }
    }

    public function recordPropertyReturn($loan_id) {
        $sql = "UPDATE property_loans 
                SET returned_date = CURDATE(), status = 'returned'
                WHERE id = :id";
        
        $this->db->beginTransaction();
        
        try {
            // Get property ID first
            $sql_select = "SELECT property_id FROM property_loans WHERE id = :id";
            $loan = $this->db->fetchSingle($sql_select, [':id' => $loan_id]);
            
            if (!$loan) {
                throw new Exception("Loan record not found");
            }
            
            // Update loan record
            $this->db->executeQuery($sql, [':id' => $loan_id]);
            
            // Update property availability
            $sql = "UPDATE properties SET is_available = TRUE WHERE id = :id";
            $this->db->executeQuery($sql, [':id' => $loan['property_id']]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error recording property return: " . $e->getMessage());
            return false;
        }
    }

    // Getters for officer-specific properties
    public function getEmployeeId() { return $this->employee_id; }
    public function getDepartmentId() { return $this->department_id; }
    public function getDepartmentName() { return $this->department_name; }
    public function getPosition() { return $this->position; }
}
?>