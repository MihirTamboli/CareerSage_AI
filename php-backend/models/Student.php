<?php
/**
 * CareerSage AI - Student Model
 * Handles student-related database operations
 */

require_once 'BaseModel.php';

class Student extends BaseModel {
    protected $table = 'students';
    protected $primaryKey = 'student_id';
    protected $fillable = [
        'unique_code',
        'class_level',
        'board_type',
        'is_active'
    ];
    protected $hidden = [];

    /**
     * Create new student
     * @param array $studentData
     * @return array
     */
    public function createStudent($studentData) {
        try {
            $this->validateRequired($studentData, ['class_level']);
            
            // Sanitize input
            $studentData = $this->sanitize($studentData);
            
            // Set default values
            $studentData['is_active'] = true;
            // Ensure unique_code is generated and stored with the student
            if (empty($studentData['unique_code'])) {
                $studentData['unique_code'] = $this->generateUniqueCode();
            }
            
            // Create student
            $studentId = $this->create($studentData);
            
            // Log the action
            $this->logAction('INFO', 'Student', 'Create', $studentId, "New student created with class level: {$studentData['class_level']}");
            
            return [
                'success' => true,
                'student_id' => $studentId,
                'unique_code' => $studentData['unique_code']
            ];
        } catch (Exception $e) {
            $this->logAction('ERROR', 'Student', 'Create', null, "Error creating student: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get student by unique code
     * @param string $uniqueCode
     * @return array|null
     */
    public function getByUniqueCode($uniqueCode) {
        return $this->findBy('unique_code', $uniqueCode);
    }

    /**
     * Get student summary with all related data
     * @param int $studentId
     * @return array|null
     */
    public function getStudentSummary($studentId) {
        $sql = "SELECT s.student_id, s.unique_code, s.class_level, s.board_type, s.last_active,
                       (SELECT COUNT(*) FROM aptitude_results ar WHERE ar.student_id = s.student_id) as tests_completed,
                       (SELECT AVG(score_percentage) FROM aptitude_results ar WHERE ar.student_id = s.student_id) as average_test_score,
                       (SELECT COUNT(DISTINCT skill_category) FROM skill_ratings sr WHERE sr.student_id = s.student_id) as skill_categories_rated,
                       (SELECT COUNT(*) FROM career_recommendations cr WHERE cr.student_id = s.student_id) as recommendations_count
                FROM students s
                WHERE s.student_id = :student_id AND s.is_active = TRUE";
        
        $stmt = $this->db->execute($sql, ['student_id' => $studentId]);
        $result = $stmt->fetch();
        
        return $this->hideFields($result);
    }

    /**
     * Update student last active timestamp
     * @param int $studentId
     * @return bool
     */
    public function updateLastActive($studentId) {
        $sql = "UPDATE students SET last_active = CURRENT_TIMESTAMP WHERE student_id = :student_id";
        $stmt = $this->db->execute($sql, ['student_id' => $studentId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deactivate student (soft delete)
     * @param int $studentId
     * @return bool
     */
    public function deactivateStudent($studentId) {
        $result = $this->update($studentId, ['is_active' => false]);
        
        if ($result) {
            $this->logAction('INFO', 'Student', 'Deactivate', $studentId, "Student deactivated");
        }
        
        return $result;
    }

    /**
     * Get active students count
     * @return int
     */
    public function getActiveStudentsCount() {
        return $this->count(['is_active' => true]);
    }

    /**
     * Get students by class level
     * @param string $classLevel
     * @return array
     */
    public function getByClassLevel($classLevel) {
        return $this->getAll(['class_level' => $classLevel, 'is_active' => true], 'created_at DESC');
    }

    /**
     * Generate unique code for student
     * @param int $studentId
     * @return string
     */
    private function generateUniqueCode() {
        return 'STU' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    /**
     * Log system action
     * @param string $level
     * @param string $module
     * @param string $action
     * @param int|null $studentId
     * @param string $message
     */
    private function logAction($level, $module, $action, $studentId, $message) {
        $sql = "INSERT INTO system_logs (log_level, module, action, student_id, message, ip_address, user_agent) 
                VALUES (:level, :module, :action, :student_id, :message, :ip_address, :user_agent)";
        
        $params = [
            'level' => $level,
            'module' => $module,
            'action' => $action,
            'student_id' => $studentId,
            'message' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        try {
            $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Failed to log action: " . $e->getMessage());
        }
    }

    /**
     * Get student academic performance
     * @param int $studentId
     * @return array
     */
    public function getAcademicPerformance($studentId) {
        $sql = "SELECT subject, 
                       AVG(percentage) as average_percentage,
                       MAX(percentage) as highest_percentage,
                       MIN(percentage) as lowest_percentage,
                       COUNT(*) as exam_count
                FROM academic_scores 
                WHERE student_id = :student_id
                GROUP BY subject
                ORDER BY average_percentage DESC";
        
        $stmt = $this->db->execute($sql, ['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    /**
     * Save/Update academic scores
     * @param int $studentId
     * @param array $scores
     * @return bool
     */
    public function saveAcademicScores($studentId, $scores) {
        try {
            $this->db->beginTransaction();
            
            // Delete existing scores (simple replacement strategy)
            $deleteSql = "DELETE FROM academic_scores WHERE student_id = :student_id";
            $this->db->execute($deleteSql, ['student_id' => $studentId]);
            
            // Insert new scores
            $insertSql = "INSERT INTO academic_scores (student_id, subject, marks_obtained, max_marks, exam_type) 
                          VALUES (:student_id, :subject, :marks, :max, 'Final')";
            
            foreach ($scores as $score) {
                $marks = floatval($score['marks_obtained']);
                $max = floatval($score['max_marks'] ?? 100);
                $this->db->execute($insertSql, [
                    'student_id' => $studentId,
                    'subject' => $score['subject'],
                    'marks' => $marks,
                    'max' => $max
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error saving scores: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get student test results
     * @param int $studentId
     * @return array
     */
    public function getTestResults($studentId) {
        $sql = "SELECT score_percentage, domain_scores, created_at 
                FROM aptitude_results 
                WHERE student_id = :student_id
                ORDER BY created_at DESC";
        $stmt = $this->db->execute($sql, ['student_id' => $studentId]);
        $results = $stmt->fetchAll();
        foreach ($results as &$result) {
            if (!empty($result['domain_scores'])) {
                $result['domain_scores'] = json_decode($result['domain_scores'], true);
            }
        }
        return $results;
    }

    /**
     * Reset all student data (for privacy/data cleanup)
     * @param int $studentId
     * @return bool
     */
    public function resetStudentData($studentId) {
        try {
            $this->db->beginTransaction();
            
            // Delete related records
            $tables = ['aptitude_results', 'skill_ratings', 'career_recommendations', 'academic_scores', 'mcq_responses', 'mcq_results'];
            
            foreach ($tables as $table) {
                $sql = "DELETE FROM {$table} WHERE student_id = :student_id";
                $this->db->execute($sql, ['student_id' => $studentId]);
            }
            
            // Log the action
            $this->logAction('WARNING', 'Student', 'Reset Data', $studentId, "All student data reset");
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logAction('ERROR', 'Student', 'Reset Data', $studentId, "Error resetting data: " . $e->getMessage());
            return false;
        }
    }
}
?>
