<?php
require_once(__DIR__ . '/User.php');

class Registrar extends User {
    private $registrar_id;
    private $is_super_admin;

    public function __construct($db) {
        parent::__construct($db);
    }

    public function loadRegistrarData($user_id) {
        $sql = "SELECT r.*, u.username, u.email, u.full_name, u.role 
                FROM registrars r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.user_id = :user_id";
        $registrar = $this->db->fetchSingle($sql, [':user_id' => $user_id]);

        if ($registrar) {
            $this->id = $registrar['user_id'];
            $this->username = $registrar['username'];
            $this->email = $registrar['email'];
            $this->full_name = $registrar['full_name'];
            $this->role = $registrar['role'];
            $this->registrar_id = $registrar['registrar_id'];
            $this->is_super_admin = $registrar['is_super_admin'];
            return true;
        }
        return false;
    }

    public function getApprovedRequests() {
        $sql = "SELECT cr.id, cr.request_date, cr.academic_year, cr.semester,
                       s.student_id, u.full_name AS student_name, s.program,
                       COUNT(dc.id) AS department_count,
                       SUM(CASE WHEN dc.status = 'approved' THEN 1 ELSE 0 END) AS approved_count
                FROM clearance_requests cr
                JOIN students s ON cr.student_id = s.user_id
                JOIN users u ON s.user_id = u.id
                JOIN department_clearances dc ON cr.id = dc.request_id
                WHERE cr.overall_status = 'approved'
                GROUP BY cr.id
                HAVING department_count = approved_count
                ORDER BY cr.request_date";
        return $this->db->fetchAll($sql);
    }

    public function finalizeClearance($request_id) {
        $sql = "UPDATE clearance_requests 
                SET overall_status = 'completed' 
                WHERE id = :id AND overall_status = 'approved'";
        
        $result = $this->db->executeQuery($sql, [':id' => $request_id]);
        
        if ($result) {
            // Get student ID for notification
            $sql = "SELECT student_id FROM clearance_requests WHERE id = :id";
            $request = $this->db->fetchSingle($sql, [':id' => $request_id]);
            
            if ($request) {
                $this->sendNotification(
                    $request['student_id'],
                    "Clearance Completed",
                    "Your clearance request has been finalized. You can now download your clearance certificate."
                );
            }
            
            return true;
        }
        return false;
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

    public function generateCertificate($request_id) {
        // Get all request data
        $sql = "SELECT cr.*, s.student_id, s.program, u.full_name AS student_name
                FROM clearance_requests cr
                JOIN students s ON cr.student_id = s.user_id
                JOIN users u ON s.user_id = u.id
                WHERE cr.id = :request_id AND cr.overall_status = 'completed'";
        $request = $this->db->fetchSingle($sql, [':request_id' => $request_id]);
        
        if (!$request) return false;
        
        // Get department approvals
        $sql = "SELECT d.name AS department_name, dc.status, dc.action_date, 
                       u.full_name AS officer_name
                FROM department_clearances dc
                JOIN departments d ON dc.department_id = d.id
                LEFT JOIN officers o ON dc.officer_id = o.user_id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE dc.request_id = :request_id
                ORDER BY d.name";
        $departments = $this->db->fetchAll($sql, [':request_id' => $request_id]);
        
        return [
            'request' => $request,
            'departments' => $departments
        ];
    }

    // User management functions
    public function getAllStudents($search = '') {
        $sql = "SELECT s.*, u.username, u.email, u.full_name, u.role, u.is_active
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE u.full_name LIKE :search OR s.student_id LIKE :search
                ORDER BY u.full_name";
        return $this->db->fetchAll($sql, [':search' => "%$search%"]);
    }

    public function createStudent($data) {
        $this->db->beginTransaction();
        
        try {
            // Create user record
            $sql = "INSERT INTO users (username, password_hash, email, full_name, role)
                    VALUES (:username, :password, :email, :full_name, :role)";
            
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $this->db->executeQuery($sql, [
                ':username' => $data['student_id'],
                ':password' => $password_hash,
                ':email' => $data['email'],
                ':full_name' => $data['full_name'],
                ':role' => ROLE_STUDENT
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Create student record
            $sql = "INSERT INTO students (user_id, student_id, program, year_of_study, phone)
                    VALUES (:user_id, :student_id, :program, :year_of_study, :phone)";
            
            $this->db->executeQuery($sql, [
                ':user_id' => $user_id,
                ':student_id' => $data['student_id'],
                ':program' => $data['program'],
                ':year_of_study' => $data['year_of_study'],
                ':phone' => $data['phone']
            ]);
            
            $this->db->commit();
            return $user_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating student: " . $e->getMessage());
            return false;
        }
    }

    public function updateStudent($user_id, $data) {
        $this->db->beginTransaction();
        
        try {
            // Update user record
            $sql = "UPDATE users 
                    SET email = :email, full_name = :full_name, is_active = :is_active
                    WHERE id = :id AND role = :role";
            
            $this->db->executeQuery($sql, [
                ':email' => $data['email'],
                ':full_name' => $data['full_name'],
                ':is_active' => $data['is_active'],
                ':id' => $user_id,
                ':role' => ROLE_STUDENT
            ]);
            
            // Update student record
            $sql = "UPDATE students 
                    SET program = :program, year_of_study = :year_of_study, phone = :phone
                    WHERE user_id = :user_id";
            
            $this->db->executeQuery($sql, [
                ':program' => $data['program'],
                ':year_of_study' => $data['year_of_study'],
                ':phone' => $data['phone'],
                ':user_id' => $user_id
            ]);
            
            // Update password if provided
            if (!empty($data['password'])) {
                $sql = "UPDATE users 
                        SET password_hash = :password
                        WHERE id = :id";
                
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                
                $this->db->executeQuery($sql, [
                    ':password' => $password_hash,
                    ':id' => $user_id
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating student: " . $e->getMessage());
            return false;
        }
    }

    // Similar methods for officers, registrars, and auditors
    public function getAllOfficers($search = '') {
        $sql = "SELECT o.*, u.username, u.email, u.full_name, u.role, u.is_active, d.name AS department_name
                FROM officers o
                JOIN users u ON o.user_id = u.id
                JOIN departments d ON o.department_id = d.id
                WHERE u.full_name LIKE :search OR o.employee_id LIKE :search
                ORDER BY u.full_name";
        return $this->db->fetchAll($sql, [':search' => "%$search%"]);
    }

    public function createOfficer($data) {
        // Similar to createStudent but for officers
    }

    public function updateOfficer($user_id, $data) {
        // Similar to updateStudent but for officers
    }

    // Getters for registrar-specific properties
    public function getRegistrarId() { return $this->registrar_id; }
    public function isSuperAdmin() { return $this->is_super_admin; }
}
?>