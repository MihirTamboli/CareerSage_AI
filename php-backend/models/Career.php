<?php
/**
 * CareerSage AI - Career Model
 * Handles career-related database operations
 */

require_once 'BaseModel.php';

class Career extends BaseModel {
    protected $table = 'career_master';
    protected $primaryKey = 'career_id';
    protected $fillable = [
        'career_title',
        'career_category',
        'description',
        'required_skills',
        'education_paths',
        'average_salary_range',
        'job_market_demand',
        'future_growth',
        'work_environment',
        'key_responsibilities',
        'indian_context',
        'entrance_exams',
        'top_colleges',
        'is_active'
    ];
    protected $hidden = [];

    /**
     * Get active careers
     * @param string $category
     * @param string $demand
     * @param string $growth
     * @return array
     */
    public function getActiveCareers($category = '', $demand = '', $growth = '') {
        $conditions = ['is_active' => true];
        
        if (!empty($category)) {
            $conditions['career_category'] = $category;
        }
        
        if (!empty($demand)) {
            $conditions['job_market_demand'] = $demand;
        }
        
        if (!empty($growth)) {
            $conditions['future_growth'] = $growth;
        }
        
        return $this->getAll($conditions, 'career_title ASC');
    }

    /**
     * Get career by ID with full details
     * @param int $careerId
     * @return array|null
     */
    public function getCareerDetails($careerId) {
        $sql = "SELECT cm.*, 
                       COUNT(cr.recommendation_id) as recommendation_count,
                       AVG(cr.match_percentage) as average_match_percentage,
                       MAX(cr.generated_at) as last_recommended
                FROM career_master cm
                LEFT JOIN career_recommendations cr ON cm.career_id = cr.career_id
                WHERE cm.career_id = :career_id AND cm.is_active = TRUE
                GROUP BY cm.career_id";
        
        $stmt = $this->db->execute($sql, ['career_id' => $careerId]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Decode JSON fields
            $jsonFields = ['required_skills', 'education_paths', 'entrance_exams', 'top_colleges'];
            foreach ($jsonFields as $field) {
                if (!empty($result[$field])) {
                    $result[$field] = json_decode($result[$field], true);
                }
            }
        }
        
        return $result;
    }

    /**
     * Search careers by keyword
     * @param string $keyword
     * @return array
     */
    public function searchCareers($keyword) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = TRUE AND (
                    career_title LIKE :keyword OR 
                    career_category LIKE :keyword OR 
                    description LIKE :keyword OR
                    key_responsibilities LIKE :keyword
                )
                ORDER BY career_title ASC";
        
        $params = ['keyword' => '%' . $keyword . '%'];
        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get careers by category
     * @param string $category
     * @return array
     */
    public function getCareersByCategory($category) {
        return $this->getAll(['career_category' => $category, 'is_active' => true], 'career_title ASC');
    }

    /**
     * Get trending careers (high demand + rapid growth)
     * @param int $limit
     * @return array
     */
    public function getTrendingCareers($limit = 10) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = TRUE AND 
                      job_market_demand IN ('High', 'Very High') AND 
                      future_growth IN ('Growing', 'Rapidly Growing')
                ORDER BY 
                    CASE job_market_demand 
                        WHEN 'Very High' THEN 1 
                        WHEN 'High' THEN 2 
                        ELSE 3 
                    END,
                    CASE future_growth 
                        WHEN 'Rapidly Growing' THEN 1 
                        WHEN 'Growing' THEN 2 
                        ELSE 3 
                    END,
                    career_title ASC
                LIMIT :limit";
        
        $stmt = $this->db->execute($sql, ['limit' => $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get career categories
     * @return array
     */
    public function getCareerCategories() {
        $sql = "SELECT DISTINCT career_category, 
                       COUNT(*) as career_count
                FROM {$this->table} 
                WHERE is_active = TRUE
                GROUP BY career_category
                ORDER BY career_category ASC";
        
        $stmt = $this->db->execute($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get career insights and statistics
     * @return array
     */
    public function getCareerInsights() {
        $sql = "SELECT 
                    COUNT(*) as total_careers,
                    COUNT(DISTINCT career_category) as total_categories,
                    SUM(CASE WHEN job_market_demand = 'Very High' THEN 1 ELSE 0 END) as very_high_demand,
                    SUM(CASE WHEN job_market_demand = 'High' THEN 1 ELSE 0 END) as high_demand,
                    SUM(CASE WHEN future_growth = 'Rapidly Growing' THEN 1 ELSE 0 END) as rapidly_growing,
                    SUM(CASE WHEN future_growth = 'Growing' THEN 1 ELSE 0 END) as growing
                FROM {$this->table} 
                WHERE is_active = TRUE";
        
        $stmt = $this->db->execute($sql);
        return $stmt->fetch();
    }

    /**
     * Get recommended careers for student
     * @param int $studentId
     * @param int $limit
     * @return array
     */
    public function getRecommendedCareers($studentId, $limit = 10) {
        $sql = "SELECT cm.*, cr.match_percentage, cr.recommendation_score,
                       cr.strengths, cr.improvement_areas, cr.recommended_stream,
                       cr.generated_at
                FROM career_master cm
                JOIN career_recommendations cr ON cm.career_id = cr.career_id
                WHERE cr.student_id = :student_id AND 
                      cr.expires_at > CURRENT_TIMESTAMP AND
                      cm.is_active = TRUE
                ORDER BY cr.match_percentage DESC, cr.recommendation_score DESC
                LIMIT :limit";
        
        $stmt = $this->db->execute($sql, ['student_id' => $studentId, 'limit' => $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Create career recommendation
     * @param array $recommendationData
     * @return bool
     */
    public function createRecommendation($recommendationData) {
        try {
            $this->validateRequired($recommendationData, ['student_id', 'career_id', 'match_percentage']);
            
            $sql = "INSERT INTO career_recommendations 
                    (student_id, career_id, match_percentage, recommendation_score, 
                     strengths, improvement_areas, recommended_stream, confidence_level) 
                    VALUES (:student_id, :career_id, :match_percentage, :recommendation_score,
                           :strengths, :improvement_areas, :recommended_stream, :confidence_level)";
            
            $params = [
                'student_id' => $recommendationData['student_id'],
                'career_id' => $recommendationData['career_id'],
                'match_percentage' => $recommendationData['match_percentage'],
                'recommendation_score' => $recommendationData['recommendation_score'] ?? 0,
                'strengths' => $recommendationData['strengths'] ?? null,
                'improvement_areas' => $recommendationData['improvement_areas'] ?? null,
                'recommended_stream' => $recommendationData['recommended_stream'] ?? null,
                'confidence_level' => $recommendationData['confidence_level'] ?? 'Medium'
            ];
            
            $this->db->execute($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("Error creating career recommendation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get education paths for career
     * @param int $careerId
     * @return array
     */
    public function getEducationPaths($careerId) {
        $sql = "SELECT * FROM education_paths WHERE career_id = :career_id ORDER BY stream_after_10 ASC";
        $stmt = $this->db->execute($sql, ['career_id' => $careerId]);
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $jsonFields = ['courses', 'entrance_exams', 'top_colleges', 'alternative_paths'];
            foreach ($jsonFields as $field) {
                if (!empty($result[$field])) {
                    $result[$field] = json_decode($result[$field], true);
                }
            }
        }
        
        return $results;
    }

    /**
     * Get careers matching student profile
     * @param array $studentProfile
     * @param int $limit
     * @return array
     */
    public function getMatchingCareers($studentProfile, $limit = 20) {
        $classLevel = $studentProfile['class_level'] ?? '10';
        $skills = $studentProfile['skills'] ?? [];
        $interests = $studentProfile['interests'] ?? [];
        
        $sql = "SELECT cm.*, 
                       CASE 
                           WHEN cm.career_category LIKE :tech_category THEN 3
                           WHEN cm.career_category LIKE :commerce_category THEN 2
                           WHEN cm.career_category LIKE :science_category THEN 2
                           ELSE 1
                       END as category_match_score
                FROM career_master cm
                WHERE cm.is_active = TRUE";
        
        $params = [
            'tech_category' => '%Technology%',
            'commerce_category' => '%Commerce%',
            'science_category' => '%Science%'
        ];
        
        // Add skill matching if skills provided
        if (!empty($skills)) {
            $skillConditions = [];
            foreach ($skills as $index => $skill) {
                $skillConditions[] = "cm.required_skills LIKE :skill{$index}";
                $params["skill{$index}"] = "%{$skill}%";
            }
            $sql .= " AND (" . implode(' OR ', $skillConditions) . ")";
        }
        
        $sql .= " ORDER BY category_match_score DESC, cm.job_market_demand DESC, cm.career_title ASC LIMIT :limit";
        $params['limit'] = $limit;
        
        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Update career master data
     * @param int $careerId
     * @param array $careerData
     * @return bool
     */
    public function updateCareer($careerId, $careerData) {
        try {
            // Encode JSON fields
            $jsonFields = ['required_skills', 'education_paths', 'entrance_exams', 'top_colleges'];
            foreach ($jsonFields as $field) {
                if (isset($careerData[$field]) && is_array($careerData[$field])) {
                    $careerData[$field] = json_encode($careerData[$field]);
                }
            }
            
            return $this->update($careerId, $careerData);
        } catch (Exception $e) {
            error_log("Error updating career: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get career demand trends
     * @return array
     */
    public function getDemandTrends() {
        $sql = "SELECT job_market_demand, 
                       COUNT(*) as count,
                       ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$this->table} WHERE is_active = TRUE), 2) as percentage
                FROM {$this->table} 
                WHERE is_active = TRUE
                GROUP BY job_market_demand
                ORDER BY 
                    CASE job_market_demand 
                        WHEN 'Very High' THEN 1 
                        WHEN 'High' THEN 2 
                        WHEN 'Medium' THEN 3 
                        ELSE 4 
                    END";
        
        $stmt = $this->db->execute($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get growth trends
     * @return array
     */
    public function getGrowthTrends() {
        $sql = "SELECT future_growth, 
                       COUNT(*) as count,
                       ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$this->table} WHERE is_active = TRUE), 2) as percentage
                FROM {$this->table} 
                WHERE is_active = TRUE
                GROUP BY future_growth
                ORDER BY 
                    CASE future_growth 
                        WHEN 'Rapidly Growing' THEN 1 
                        WHEN 'Growing' THEN 2 
                        WHEN 'Stable' THEN 3 
                        ELSE 4 
                    END";
        
        $stmt = $this->db->execute($sql);
        return $stmt->fetchAll();
    }
}
?>