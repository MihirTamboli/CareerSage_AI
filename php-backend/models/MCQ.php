<?php
/**
 * CareerSage AI - MCQ Model
 * Handles MCQ-related database operations
 */

require_once 'BaseModel.php';

class MCQ extends BaseModel {
    protected $table = 'mcq_questions';
    protected $primaryKey = 'question_id';
    protected $fillable = [
        'grade_level',
        'question_text',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'domain',
        'category',
        'question_order',
        'difficulty_level',
        'is_active'
    ];

    /**
     * Get all questions for a specific grade
     * @param string $grade Grade level (10, 11, or 12)
     * @return array
     */
    public function getQuestionsByGrade($grade) {
        try {
            $sql = "SELECT question_id, grade_level, question_text, 
                           option_a, option_b, option_c, option_d, 
                           domain, category, question_order, difficulty_level
                    FROM mcq_questions 
                    WHERE grade_level = :grade AND is_active = TRUE
                    ORDER BY question_order ASC";
            
            $stmt = $this->db->execute($sql, ['grade' => $grade]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching questions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save student responses to MCQ questions
     * @param int $studentId
     * @param array $responses Array of responses with question_id and selected_answer
     * @return array
     */
    public function saveResponses($studentId, $responses) {
        try {
            $this->db->beginTransaction();
            
            $savedCount = 0;
            $correctCount = 0;
            
            foreach ($responses as $response) {
                $questionId = $response['question_id'];
                $selectedAnswer = strtoupper($response['selected_answer']);
                
                // Get correct answer
                $question = $this->find($questionId);
                if (!$question) {
                    continue;
                }
                
                $isCorrect = ($selectedAnswer === $question['correct_answer']);
                if ($isCorrect) {
                    $correctCount++;
                }
                
                // Insert response
                $sql = "INSERT INTO mcq_responses 
                        (student_id, question_id, selected_answer, is_correct, time_taken_seconds) 
                        VALUES (:student_id, :question_id, :selected_answer, :is_correct, :time_taken)
                        ON DUPLICATE KEY UPDATE 
                        selected_answer = :selected_answer, 
                        is_correct = :is_correct,
                        time_taken_seconds = :time_taken,
                        answered_at = CURRENT_TIMESTAMP";
                
                $params = [
                    'student_id' => $studentId,
                    'question_id' => $questionId,
                    'selected_answer' => $selectedAnswer,
                    'is_correct' => $isCorrect,
                    'time_taken' => $response['time_taken_seconds'] ?? null
                ];
                
                $this->db->execute($sql, $params);
                $savedCount++;
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'saved_count' => $savedCount,
                'correct_count' => $correctCount
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error saving responses: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate and save MCQ results for a student
     * @param int $studentId
     * @param string $gradeLevel
     * @param int $timeTakenMinutes
     * @return array
     */
    public function calculateResults($studentId, $gradeLevel, $timeTakenMinutes = null) {
        try {
            // Get response statistics
            $sql = "SELECT 
                        COUNT(*) as total_answered,
                        SUM(CASE WHEN is_correct = TRUE THEN 1 ELSE 0 END) as correct_count,
                        SUM(CASE WHEN is_correct = FALSE THEN 1 ELSE 0 END) as incorrect_count
                    FROM mcq_responses r
                    JOIN mcq_questions q ON r.question_id = q.question_id
                    WHERE r.student_id = :student_id AND q.grade_level = :grade";
            
            $stmt = $this->db->execute($sql, [
                'student_id' => $studentId,
                'grade' => $gradeLevel
            ]);
            $stats = $stmt->fetch();
            
            $totalQuestions = 30;
            $correctAnswers = (int)$stats['correct_count'];
            $incorrectAnswers = (int)$stats['incorrect_count'];
            $totalAnswered = (int)$stats['total_answered'];
            $unanswered = $totalQuestions - $totalAnswered;
            $percentage = ($correctAnswers / $totalQuestions) * 100;
            
            // Get domain-wise scores for Grade 11 & 12
            $domainScores = null;
            if (in_array($gradeLevel, ['11', '12'])) {
                $domainScores = $this->getDomainScores($studentId, $gradeLevel);
            }
            
            // Save results
            $resultSql = "INSERT INTO mcq_results 
                         (student_id, grade_level, total_questions, correct_answers, 
                          incorrect_answers, unanswered, percentage_score, 
                          time_taken_minutes, domain_scores)
                         VALUES 
                         (:student_id, :grade, :total, :correct, :incorrect, 
                          :unanswered, :percentage, :time_taken, :domain_scores)
                         ON DUPLICATE KEY UPDATE
                         correct_answers = :correct,
                         incorrect_answers = :incorrect,
                         unanswered = :unanswered,
                         percentage_score = :percentage,
                         time_taken_minutes = :time_taken,
                         domain_scores = :domain_scores,
                         completed_at = CURRENT_TIMESTAMP";
            
            $this->db->execute($resultSql, [
                'student_id' => $studentId,
                'grade' => $gradeLevel,
                'total' => $totalQuestions,
                'correct' => $correctAnswers,
                'incorrect' => $incorrectAnswers,
                'unanswered' => $unanswered,
                'percentage' => round($percentage, 2),
                'time_taken' => $timeTakenMinutes,
                'domain_scores' => $domainScores ? json_encode($domainScores) : null
            ]);
            
            return [
                'success' => true,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => $incorrectAnswers,
                'unanswered' => $unanswered,
                'percentage_score' => round($percentage, 2),
                'domain_scores' => $domainScores
            ];
        } catch (Exception $e) {
            error_log("Error calculating results: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get domain-wise scores for Grade 11 & 12
     * @param int $studentId
     * @param string $gradeLevel
     * @return array
     */
    private function getDomainScores($studentId, $gradeLevel) {
        $sql = "SELECT 
                    q.domain,
                    COUNT(*) as total_questions,
                    SUM(CASE WHEN r.is_correct = TRUE THEN 1 ELSE 0 END) as correct_answers,
                    ROUND((SUM(CASE WHEN r.is_correct = TRUE THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                FROM mcq_responses r
                JOIN mcq_questions q ON r.question_id = q.question_id
                WHERE r.student_id = :student_id 
                AND q.grade_level = :grade
                AND q.domain IS NOT NULL
                GROUP BY q.domain";
        
        $stmt = $this->db->execute($sql, [
            'student_id' => $studentId,
            'grade' => $gradeLevel
        ]);
        
        $domains = [];
        while ($row = $stmt->fetch()) {
            $domains[$row['domain']] = [
                'total' => (int)$row['total_questions'],
                'correct' => (int)$row['correct_answers'],
                'percentage' => (float)$row['percentage']
            ];
        }
        
        return $domains;
    }

    /**
     * Get student's MCQ results
     * @param int $studentId
     * @return array|null
     */
    public function getStudentResults($studentId) {
        try {
            $sql = "SELECT * FROM mcq_results 
                    WHERE student_id = :student_id 
                    ORDER BY completed_at DESC 
                    LIMIT 1";
            
            $stmt = $this->db->execute($sql, ['student_id' => $studentId]);
            $result = $stmt->fetch();
            
            if ($result && $result['domain_scores']) {
                $result['domain_scores'] = json_decode($result['domain_scores'], true);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error fetching results: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get analytics for a specific question
     * @param int $questionId
     * @return array
     */
    public function getQuestionAnalytics($questionId) {
        try {
            $sql = "SELECT * FROM mcq_question_analytics 
                    WHERE question_id = :question_id";
            
            $stmt = $this->db->execute($sql, ['question_id' => $questionId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error fetching analytics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Bulk insert MCQ questions
     * @param array $questions Array of question data
     * @return array
     */
    public function bulkInsertQuestions($questions) {
        try {
            $this->db->beginTransaction();
            
            $insertedCount = 0;
            foreach ($questions as $question) {
                $this->create($question);
                $insertedCount++;
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'inserted_count' => $insertedCount
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error bulk inserting questions: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get student's answers for review
     * @param int $studentId
     * @param string $gradeLevel
     * @return array
     */
    public function getStudentAnswers($studentId, $gradeLevel) {
        try {
            $sql = "SELECT 
                        q.question_id,
                        q.question_text,
                        q.option_a,
                        q.option_b,
                        q.option_c,
                        q.option_d,
                        q.correct_answer,
                        r.selected_answer,
                        r.is_correct,
                        r.time_taken_seconds,
                        q.domain,
                        q.category
                    FROM mcq_questions q
                    LEFT JOIN mcq_responses r ON q.question_id = r.question_id 
                        AND r.student_id = :student_id
                    WHERE q.grade_level = :grade AND q.is_active = TRUE
                    ORDER BY q.question_order ASC";
            
            $stmt = $this->db->execute($sql, [
                'student_id' => $studentId,
                'grade' => $gradeLevel
            ]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching student answers: " . $e->getMessage());
            return [];
        }
    }
}
?>
