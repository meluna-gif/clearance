<?php
require_once(__DIR__ . '/User.php');

class Student extends User {
    private $student_id;
    private $program;
    private $year_of_study;
    private $phone;

    public function __construct($db) {
        parent::__construct($db);
    }

    public function loadStudentData($user_id) {
        $sql = "SELECT s.*, u.username, u.email, u.full_name, u.role 
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = :user_id";
        $student = $this->db->fetchSingle($sql, [':user_id' => $user_id]);

        if ($student) {
            $this->id = $student['user_id'];
            $this->username = $student['username'];
            $this->email = $student['email'];
            $this->full_name = $student['full_name'];
            $this->role = $student['role'];
            $this->student_id = $student['student_id'];
            $this->program = $student['program'];
            $this->year_of_study = $student['year_of_study'];
            $this->phone = $student['phone'];
            return true;
        }
        return false;
    }

    public function submitClearanceRequest($academic_year, $semester) {
        $this->db->beginTransaction();
        
        try {
            // Create clearance request
            $sql = "INSERT INTO clearance_requests (student_id, academic_year, semester) 
                    VALUES (:student_id, :academic_year, :semester)";
            $this->db->executeQuery($sql, [
                ':student_id' => $this->id,
                ':academic_year' => $academic_year,
                ':semester' => $semester
            ]);
            
            $request_id = $this->db->lastInsertId();
            
            // Get all departments that require clearance
            $sql = "SELECT id FROM departments";
            $departments = $this->db->fetchAll($sql);
            
            // Create department clearance records
            foreach ($departments as $dept) {
                $sql = "INSERT INTO department_clearances (request_id, department_id) 
                        VALUES (:request_id, :department_id)";
                $this->db->executeQuery($sql, [
                    ':request_id' => $request_id,
                    ':department_id' => $dept['id']
                ]);
            }
            
            $this->db->commit();
            return $request_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error submitting clearance request: " . $e->getMessage());
            return false;
        }
    }

    public function getClearanceStatus() {
        $sql = "SELECT cr.id, cr.request_date, cr.academic_year, cr.semester, cr.overall_status,
                       dc.department_id, d.name AS department_name, dc.status AS dept_status,
                       dc.comments, dc.action_date
                FROM clearance_requests cr
                JOIN department_clearances dc ON cr.id = dc.request_id
                JOIN departments d ON dc.department_id = d.id
                WHERE cr.student_id = :student_id
                ORDER BY cr.request_date DESC, d.name";
        return $this->db->fetchAll($sql, [':student_id' => $this->id]);
    }

    public function getBorrowedProperties() {
        $sql = "SELECT p.name, p.description, pl.borrowed_date, pl.due_date, pl.status
                FROM property_loans pl
                JOIN properties p ON pl.property_id = p.id
                WHERE pl.student_id = :student_id AND pl.status != 'returned'
                ORDER BY pl.due_date";
        return $this->db->fetchAll($sql, [':student_id' => $this->id]);
    }

    // Getters for student-specific properties
    public function getStudentId() { return $this->student_id; }
    public function getProgram() { return $this->program; }
    public function getYearOfStudy() { return $this->year_of_study; }
    public function getPhone() { return $this->phone; }
}
?>