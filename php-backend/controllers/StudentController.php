<?php
/**
 * CareerSage AI - Student Controller
 * Handles student-related API endpoints
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Career.php';

class StudentController extends BaseController {
    private $studentModel;

    public function __construct() {
        parent::__construct();
        $this->studentModel = new Student();
        $this->handleCors();
    }

    /**
     * Create new student
     * POST /api/student/create
     */
    public function create() {
        try {
            $this->validateRequestMethod('POST');
            $this->checkRateLimit(20, 3600);
            
            $data = $this->getJsonInput();
            $this->validateRequiredParams(['class_level']);
            $data['class_level'] = $this->requireString(
                $data['class_level'],
                'class_level',
                2,
                2,
                '/^(10|11|12)$/'
            );

            if (isset($data['board_type'])) {
                $data['board_type'] = $this->requireString(
                    $data['board_type'],
                    'board_type',
                    2,
                    20,
                    '/^(CBSE|ICSE|State|Other)$/'
                );
            }
            
            $result = $this->studentModel->createStudent($data);
            
            if ($result['success']) {
                // Set session
                $_SESSION['student_id'] = $result['student_id'];
                $_SESSION['unique_code'] = $result['unique_code'];
                
                $this->logApiRequest('student_create', $result);
                $this->sendJsonResponse($result);
            } else {
                $this->sendJsonResponse($result, 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get student profile
     * GET /api/student/profile
     */
    public function getProfile() {
        try {
            $this->validateRequestMethod('GET');
            
            // Try to get student ID from session first, then from query parameter
            $studentId = null;
            if (isset($_SESSION['student_id'])) {
                $studentId = $_SESSION['student_id'];
            } elseif (isset($_GET['student_id'])) {
                $studentId = intval($_GET['student_id']);
            } else {
                $this->sendJsonResponse([
                    'message' => 'Student ID required (either via session or student_id parameter)'
                ], 400);
                return;
            }
            
            $student = $this->studentModel->find($studentId);
            
            if (!$student) {
                $this->sendJsonResponse([
                    'message' => 'Student not found'
                ], 404);
                return;
            }
            
            // Get additional data
            $summary = $this->studentModel->getStudentSummary($studentId);
            $academicPerformance = $this->studentModel->getAcademicPerformance($studentId);
            $testResults = $this->studentModel->getTestResults($studentId);
            
            $profile = [
                'basic_info' => $student,
                'summary' => $summary,
                'academic_performance' => $academicPerformance,
                'test_results' => $testResults
            ];
            
            $this->logApiRequest('student_profile');
            $this->sendJsonResponse($profile);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Update student profile
     * PUT /api/student/update
     */
    public function update() {
        try {
            $this->validateRequestMethod('PUT');
            $this->validateStudentSession();
            
            $data = $this->getJsonInput();
            
            // Only allow updating certain fields
            $allowedFields = ['board_type'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (isset($updateData['board_type'])) {
                $updateData['board_type'] = $this->requireString(
                    $updateData['board_type'],
                    'board_type',
                    2,
                    20,
                    '/^(CBSE|ICSE|State|Other)$/'
                );
            }
            
            if (empty($updateData)) {
                $this->sendJsonResponse([
                    'message' => 'No valid fields to update'
                ], 400);
            }
            
            $result = $this->studentModel->update($this->studentId, $updateData);
            
            if ($result) {
                $this->logApiRequest('student_update', $updateData);
                $this->sendJsonResponse([
                    'message' => 'Profile updated successfully'
                ]);
            } else {
                $this->sendJsonResponse([
                    'message' => 'Failed to update profile'
                ], 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Update academic scores
     * POST /api/student/academics
     */
    public function updateAcademics() {
        try {
            $this->validateRequestMethod('POST');
            $this->checkRateLimit(30, 300);
            
            $data = $this->getJsonInput();
            
            // Try to get student ID from request body first, then from session
            $studentId = null;
            if (isset($data['student_id'])) {
                $studentId = $this->requireInt($data['student_id'], 'student_id', 1);
            } elseif (isset($_SESSION['student_id'])) {
                $studentId = $this->requireInt($_SESSION['student_id'], 'student_id', 1);
            } else {
                $this->sendJsonResponse([
                    'message' => 'Student ID required (either in request body or session)'
                ], 400);
                return;
            }

            $scores = $this->requireArray($data['scores'] ?? null, 'scores', 1, 50);
            $cleanScores = [];
            foreach ($scores as $score) {
                $subject = $this->requireString($score['subject'] ?? '', 'subject', 1, 100);
                $marks = $this->requireNumber($score['marks_obtained'] ?? null, 'marks_obtained', 0, 1000);
                $max = $this->requireNumber($score['max_marks'] ?? 100, 'max_marks', 1, 1000);
                if ($marks > $max) {
                    $this->sendJsonResponse([
                        'message' => "marks_obtained cannot exceed max_marks for {$subject}"
                    ], 400);
                }
                $cleanScores[] = [
                    'subject' => $subject,
                    'marks_obtained' => $marks,
                    'max_marks' => $max
                ];
            }

            // Prevent multiple submissions
            $db = new Database();
            $conn = $db->getConnection();
            $check = $conn->prepare("SELECT COUNT(*) as cnt FROM academic_scores WHERE student_id = :student_id");
            $check->bindParam(':student_id', $studentId);
            $check->execute();
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row && intval($row['cnt']) > 0) {
                $this->sendJsonResponse(['message' => 'Academic scores already submitted'], 409);
                return;
            }

            $result = $this->studentModel->saveAcademicScores($studentId, $cleanScores);
            
            if ($result) {
                $this->sendJsonResponse(['success' => true, 'message' => 'Scores updated']);
            } else {
                $this->sendJsonResponse(['message' => 'Failed to update scores'], 500);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get student dashboard data
     * GET /api/student/dashboard
     */
    public function getDashboard() {
        try {
            $this->validateRequestMethod('GET');
            
            // Try to get student ID from session first, then from query parameter
            $studentId = null;
            if (isset($_SESSION['student_id'])) {
                $studentId = $_SESSION['student_id'];
            } elseif (isset($_GET['student_id'])) {
                $studentId = intval($_GET['student_id']);
            } else {
                $this->sendJsonResponse([
                    'message' => 'Student ID required (either via session or student_id parameter)'
                ], 400);
                return;
            }
            
            // Update last active
            $this->studentModel->updateLastActive($studentId);
            
            $summary = $this->studentModel->getStudentSummary($studentId);
            $academicPerformance = $this->studentModel->getAcademicPerformance($studentId);
            $testResults = $this->studentModel->getTestResults($studentId);
            
            // Get recent recommendations
            $careerModel = new Career();
            $recommendations = $careerModel->getRecommendedCareers($studentId, 5);
            
            $dashboard = [
                'summary' => $summary,
                'academic_performance' => $academicPerformance,
                'recent_tests' => array_slice($testResults, 0, 3),
                'recent_recommendations' => $recommendations,
                'quick_stats' => [
                    'tests_completed' => count($testResults),
                    'subjects_rated' => count($academicPerformance),
                    'recommendations_count' => count($recommendations)
                ]
            ];
            
            $this->logApiRequest('student_dashboard');
            $this->sendJsonResponse($dashboard);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Delete/reset student data
     * DELETE /api/student/reset
     */
    public function resetData() {
        try {
            $this->validateRequestMethod('DELETE');
            $this->validateStudentSession();
            
            // Require confirmation
            $data = $this->getJsonInput();
            if (!isset($data['confirm']) || $data['confirm'] !== 'RESET_ALL_DATA') {
                $this->sendJsonResponse([
                    'message' => 'Confirmation required. Send {confirm: "RESET_ALL_DATA"} to proceed.'
                ], 400);
            }
            
            $result = $this->studentModel->resetStudentData($this->studentId);
            
            if ($result) {
                // Clear session
                unset($_SESSION['student_id']);
                unset($_SESSION['unique_code']);
                
                $this->logApiRequest('student_reset_data');
                $this->sendJsonResponse([
                    'message' => 'All student data has been reset successfully.'
                ]);
            } else {
                $this->sendJsonResponse([
                    'message' => 'Failed to reset student data'
                ], 500);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get student by unique code
     * GET /api/student/by-code/{unique_code}
     */
    public function getByUniqueCode($uniqueCode) {
        try {
            $this->validateRequestMethod('GET');
            
            $student = $this->studentModel->getByUniqueCode($uniqueCode);
            
            if (!$student) {
                $this->sendJsonResponse([
                    'message' => 'Student not found'
                ], 404);
            }
            
            // Only return basic info for privacy
            $basicInfo = [
                'student_id' => $student['student_id'],
                'unique_code' => $student['unique_code'],
                'class_level' => $student['class_level'],
                'board_type' => $student['board_type'],
                'created_at' => $student['created_at']
            ];
            
            $this->logApiRequest('student_by_code', ['unique_code' => $uniqueCode]);
            $this->sendJsonResponse($basicInfo);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Login student with unique code
     * POST /api/student/login
     */
    public function login() {
        try {
            $this->validateRequestMethod('POST');
            $this->checkRateLimit(20, 900);
            
            $data = $this->getJsonInput();
            $this->validateRequiredParams(['unique_code']);
            $data['unique_code'] = $this->requireString(
                $data['unique_code'],
                'unique_code',
                4,
                50,
                '/^[a-zA-Z0-9]+$/'
            );
            
            $student = $this->studentModel->getByUniqueCode($data['unique_code']);
            
            if (!$student || !$student['is_active']) {
                $this->sendJsonResponse([
                    'message' => 'Invalid unique code or student not active'
                ], 401);
            }
            
            // Set session
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['unique_code'] = $student['unique_code'];
            
            // Update last active
            $this->studentModel->updateLastActive($student['student_id']);
            
            $loginData = [
                'student_id' => $student['student_id'],
                'unique_code' => $student['unique_code'],
                'class_level' => $student['class_level'],
                'board_type' => $student['board_type']
            ];
            
            $this->logApiRequest('student_login', ['unique_code' => $data['unique_code']]);
            $this->sendJsonResponse([
                'message' => 'Login successful',
                'student' => $loginData
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Logout student
     * POST /api/student/logout
     */
    public function logout() {
        try {
            $this->validateRequestMethod('POST');
            
            // Clear session
            session_destroy();
            
            $this->logApiRequest('student_logout');
            $this->sendJsonResponse([
                'message' => 'Logout successful'
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get system statistics for admin
     * GET /api/student/stats
     */
    public function getStats() {
        try {
            $this->validateRequestMethod('GET');
            
            $stats = [
                'total_students' => $this->studentModel->getActiveStudentsCount(),
                'class_10_students' => count($this->studentModel->getByClassLevel('10')),
                'class_11_students' => count($this->studentModel->getByClassLevel('11')),
                'class_12_students' => count($this->studentModel->getByClassLevel('12'))
            ];
            
            $this->logApiRequest('student_stats');
            $this->sendJsonResponse($stats);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Submit aptitude test results
     * POST /api/aptitude/submit
     */
    public function submitAptitudeTest() {
        try {
            $this->validateRequestMethod('POST');
            $this->checkRateLimit(10, 900);
            
            $input = $this->getJsonInput();
            $this->validateRequiredParams(['student_id', 'score']);
            
            $studentId = $this->requireInt($input['student_id'], 'student_id', 1);
            $score = $this->requireNumber($input['score'], 'score', 0, 100);
            $domainScores = null;
            if (isset($input['domain_scores'])) {
                $domainScores = $this->normalizeDomainScores($input['domain_scores']);
            }

            // Prevent multiple submissions
            $db = new Database();
            $conn = $db->getConnection();
            $check = $conn->prepare("SELECT result_id FROM aptitude_results WHERE student_id = :student_id LIMIT 1");
            $check->bindParam(':student_id', $studentId);
            $check->execute();
            if ($check->fetch(PDO::FETCH_ASSOC)) {
                $this->sendJsonResponse([
                    'message' => 'Aptitude test already completed'
                ], 409);
                return;
            }

            // Save aptitude result
            $query = "INSERT INTO aptitude_results (student_id, score_percentage, domain_scores, created_at) 
                      VALUES (:student_id, :score, :domain_scores, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindParam(':score', $score);
            $domainJson = $domainScores ? json_encode($domainScores) : null;
            $stmt->bindValue(':domain_scores', $domainJson, $domainJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
            $testId = $conn->lastInsertId();
            
            $this->logApiRequest('aptitude_submit');
            $this->sendJsonResponse([
                'message' => 'Aptitude test results saved successfully',
                'test_id' => $testId,
                'score' => $score,
                'domain_scores' => $domainScores
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get aptitude test status
     * GET /api/aptitude/status
     */
    public function getAptitudeStatus() {
        try {
            $this->validateRequestMethod('GET');

            $studentId = null;
            if (isset($_GET['student_id'])) {
                $studentId = $this->requireInt($_GET['student_id'], 'student_id', 1);
            } elseif (isset($_SESSION['student_id'])) {
                $studentId = $this->requireInt($_SESSION['student_id'], 'student_id', 1);
            } else {
                $this->sendJsonResponse([
                    'message' => 'Student ID required'
                ], 400);
                return;
            }

            $db = new Database();
            $conn = $db->getConnection();
            $query = "SELECT score_percentage, domain_scores, created_at 
                      FROM aptitude_results 
                      WHERE student_id = :student_id
                      ORDER BY created_at DESC
                      LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $studentId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $domainScores = null;
            if ($result && !empty($result['domain_scores'])) {
                $domainScores = json_decode($result['domain_scores'], true);
            }

            $this->sendJsonResponse([
                'completed' => $result ? true : false,
                'latest_score' => $result ? floatval($result['score_percentage']) : null,
                'domain_scores' => $domainScores,
                'completed_at' => $result['created_at'] ?? null
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Update student skills assessment
     * POST /api/student/skills
     */
    public function updateSkills() {
        try {
            $this->validateRequestMethod('POST');
            $this->checkRateLimit(30, 300);
            
            $input = $this->getJsonInput();
            $this->validateRequiredParams(['student_id', 'skills']);
            
            $studentId = $this->requireInt($input['student_id'], 'student_id', 1);
            $skills = $this->requireArray($input['skills'], 'skills', 1, 100);
            $cleanSkills = [];
            foreach ($skills as $skill) {
                $category = $this->requireString($skill['category'] ?? 'General', 'category', 1, 100);
                $name = $this->requireString($skill['name'] ?? '', 'name', 1, 120);
                $rating = isset($skill['rating']) ? $this->requireInt($skill['rating'], 'rating', 1, 5) : 3;
                $interest = isset($skill['interest']) ? $this->requireInt($skill['interest'], 'interest', 1, 5) : 3;
                $cleanSkills[] = [
                    'category' => $category,
                    'name' => $name,
                    'rating' => $rating,
                    'interest' => $interest
                ];
            }
            
            $db = new Database();
            $conn = $db->getConnection();

            // Prevent multiple submissions
            $check = $conn->prepare("SELECT COUNT(*) as cnt FROM skill_ratings WHERE student_id = :student_id");
            $check->bindParam(':student_id', $studentId);
            $check->execute();
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row && intval($row['cnt']) > 0) {
                $this->sendJsonResponse([
                    'message' => 'Skills already submitted'
                ], 409);
                return;
            }
            
            // Delete existing skills for this student
            $deleteQuery = "DELETE FROM skill_ratings WHERE student_id = :student_id";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bindParam(':student_id', $studentId);
            $stmt->execute();
            
            // Insert new skills
            $insertQuery = "INSERT INTO skill_ratings 
                           (student_id, skill_category, skill_name, self_rating, interest_level) 
                           VALUES (:student_id, :category, :skill_name, :rating, :interest)";
            $stmt = $conn->prepare($insertQuery);
            
            $savedCount = 0;
            foreach ($cleanSkills as $skill) {
                $rating = isset($skill['rating']) ? intval($skill['rating']) * 2 : 6;
                $interest = isset($skill['interest']) ? intval($skill['interest']) : 3;
                $stmt->bindParam(':student_id', $studentId);
                $stmt->bindParam(':category', $skill['category']);
                $stmt->bindParam(':skill_name', $skill['name']);
                $stmt->bindParam(':rating', $rating);
                $stmt->bindParam(':interest', $interest);
                $stmt->execute();
                $savedCount++;
            }
            
            $this->logApiRequest('skills_update');
            $this->sendJsonResponse([
                'message' => 'Skills updated successfully',
                'skills_saved' => $savedCount
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get academic scores status
     * GET /api/student/academics/status
     */
    public function getAcademicsStatus() {
        try {
            $this->validateRequestMethod('GET');
            $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
            if (!$studentId) {
                $this->sendJsonResponse(['message' => 'Student ID required'], 400);
                return;
            }
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM academic_scores WHERE student_id = :student_id");
            $stmt->bindParam(':student_id', $studentId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->sendJsonResponse([
                'completed' => $row && intval($row['cnt']) > 0
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get skills status
     * GET /api/student/skills/status
     */
    public function getSkillsStatus() {
        try {
            $this->validateRequestMethod('GET');
            $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
            if (!$studentId) {
                $this->sendJsonResponse(['message' => 'Student ID required'], 400);
                return;
            }
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM skill_ratings WHERE student_id = :student_id");
            $stmt->bindParam(':student_id', $studentId);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->sendJsonResponse([
                'completed' => $row && intval($row['cnt']) > 0
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Normalize and validate domain scores payload
     * @param mixed $domainScores
     * @return array
     */
    private function normalizeDomainScores($domainScores) {
        if (!is_array($domainScores)) {
            $this->sendJsonResponse([
                'message' => 'domain_scores must be an object with logical, verbal, and quant values'
            ], 400);
        }

        $allowed = ['logical', 'verbal', 'quant'];
        $normalized = [];

        foreach ($allowed as $domain) {
            if (!isset($domainScores[$domain])) {
                continue;
            }

            $entry = $domainScores[$domain];
            if (is_array($entry)) {
                if (!isset($entry['percentage'])) {
                    $this->sendJsonResponse([
                        'message' => "domain_scores.{$domain}.percentage is required"
                    ], 400);
                }
                $percentage = $this->requireNumber($entry['percentage'], "domain_scores.{$domain}.percentage", 0, 100);
                $correct = isset($entry['correct']) ? $this->requireInt($entry['correct'], "domain_scores.{$domain}.correct", 0) : null;
                $total = isset($entry['total']) ? $this->requireInt($entry['total'], "domain_scores.{$domain}.total", 0) : null;
                if ($total !== null && $correct !== null && $correct > $total) {
                    $this->sendJsonResponse([
                        'message' => "domain_scores.{$domain}.correct cannot exceed total"
                    ], 400);
                }
                $normalized[$domain] = [
                    'percentage' => $percentage,
                    'correct' => $correct,
                    'total' => $total
                ];
            } else {
                $percentage = $this->requireNumber($entry, "domain_scores.{$domain}", 0, 100);
                $normalized[$domain] = [
                    'percentage' => $percentage
                ];
            }
        }

        if (empty($normalized)) {
            $this->sendJsonResponse([
                'message' => 'domain_scores must include at least one of: logical, verbal, quant'
            ], 400);
        }

        return $normalized;
    }
}
?>
