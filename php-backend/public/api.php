<?php
/**
 * CareerSage AI - API Router
 * Routes all API requests to appropriate controllers
 */

// Enable error reporting for development but disable HTML formatting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('html_errors', 0);    // Don't use HTML formatting for errors

// Start output buffering to catch any unexpected output
ob_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Career.php';
require_once __DIR__ . '/../models/MCQ.php';
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../controllers/StudentController.php';

// Parse request - try multiple methods to get the path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$pathInfo = $_SERVER['PATH_INFO'] ?? '';

// Debug log the original request
error_log("REQUEST_URI: " . $requestUri);
error_log("SCRIPT_NAME: " . $scriptName);
error_log("PATH_INFO: " . $pathInfo);

// Try to get the path info from different sources
if (!empty($pathInfo)) {
    $uri = $pathInfo;
} else {
    // Remove query string from URI
    $uri = parse_url($requestUri, PHP_URL_PATH);
    
    // If script name is present, take everything after it
    if (!empty($scriptName) && strpos($uri, $scriptName) !== false) {
        $uri = substr($uri, strlen($scriptName));
    } else {
        // Remove various possible base paths
        $uri = str_replace('/career-guidance-system/php-backend/public/api.php', '', $uri);
        $uri = str_replace('/php-backend/public/api.php', '', $uri);
        $uri = str_replace('/career-guidance-system/php-backend', '', $uri);
        $uri = str_replace('/php-backend', '', $uri);
        $uri = str_replace('/api.php', '', $uri);
    }
}

// Clean up the URI
$uri = trim($uri, '/');
if (empty($uri)) {
    $uri = '/';
} else {
    $uri = '/' . $uri;
}

// Debug log the final URI
error_log("Final URI: '" . $uri . "'");

// Route the request
try {
    switch (true) {
        // Student routes - accept both /api/student/* and /student/* formats
        case ($uri === '/api/student/create' || $uri === '/student/create') && $requestMethod === 'POST':
            $controller = new StudentController();
            $controller->create();
            break;
            
        case ($uri === '/api/student/profile' || $uri === '/student/profile') && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getProfile();
            break;
            
        case ($uri === '/api/student/update' || $uri === '/student/update') && $requestMethod === 'PUT':
            $controller = new StudentController();
            $controller->update();
            break;
            
        case ($uri === '/api/student/dashboard' || $uri === '/student/dashboard') && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getDashboard();
            break;
            
        case ($uri === '/api/student/reset' || $uri === '/student/reset') && $requestMethod === 'DELETE':
            $controller = new StudentController();
            $controller->resetData();
            break;
            
        case ($uri === '/api/student/login' || $uri === '/student/login') && $requestMethod === 'POST':
            $controller = new StudentController();
            $controller->login();
            break;
            
        case ($uri === '/api/student/logout' || $uri === '/student/logout') && $requestMethod === 'POST':
            $controller = new StudentController();
            $controller->logout();
            break;
            
        case ($uri === '/api/student/stats' || $uri === '/student/stats') && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getStats();
            break;
            
        case (preg_match('/^\/api\/student\/by-code\/([a-zA-Z0-9]+)$/', $uri, $matches) || preg_match('/^\/student\/by-code\/([a-zA-Z0-9]+)$/', $uri, $matches)) && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getByUniqueCode($matches[1]);
            break;
            
        // Default route
        case $uri === '/api' && $requestMethod === 'GET':
            echo json_encode([
                'message' => 'CareerSage AI API',
                'version' => '1.0.0',
                'status' => 'running',
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoints' => [
                    'POST /api/student/create' => 'Create new student',
                    'POST /api/student/login' => 'Login student',
                    'GET /api/student/profile' => 'Get student profile',
                    'GET /api/student/dashboard' => 'Get student dashboard',
                    'PUT /api/student/update' => 'Update student profile',
                    'DELETE /api/student/reset' => 'Reset student data',
                    'POST /api/student/logout' => 'Logout student',
                    'POST /api/student/academics' => 'Update academic scores',
                    'GET /api/student/stats' => 'Get system statistics',
                    'GET /api/mcq/questions/{grade}' => 'Get MCQ questions for grade',
                    'POST /api/mcq/submit' => 'Submit MCQ responses',
                    'GET /api/mcq/results/{student_id}' => 'Get MCQ results',
                    'GET /api/careers' => 'Get careers list'
                ]
            ]);
            break;
            
        case ($uri === '/api/student/academics' || $uri === '/student/academics') && $requestMethod === 'POST':
            $controller = new StudentController();
            $controller->updateAcademics();
            break;

        case ($uri === '/api/student/academics/status' || $uri === '/student/academics/status') && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getAcademicsStatus();
            break;
            
        case ($uri === '/api/aptitude/submit' || $uri === '/aptitude/submit') && $requestMethod === 'POST':
            $controller = new StudentController();
            $controller->submitAptitudeTest();
            break;

        case ($uri === '/api/aptitude/status' || $uri === '/aptitude/status') && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getAptitudeStatus();
            break;
            
        case ($uri === '/api/student/skills' || $uri === '/student/skills') && $requestMethod === 'POST':
            $controller = new StudentController();
            $controller->updateSkills();
            break;

        case ($uri === '/api/student/skills/status' || $uri === '/student/skills/status') && $requestMethod === 'GET':
            $controller = new StudentController();
            $controller->getSkillsStatus();
            break;

        // Careers
        case ($uri === '/api/careers' || $uri === '/careers') && $requestMethod === 'GET':
            $careerModel = new Career();
            $category = $_GET['category'] ?? '';
            $demand = $_GET['demand'] ?? '';
            $growth = $_GET['growth'] ?? '';
            $careers = $careerModel->getActiveCareers($category, $demand, $growth);
            echo json_encode([
                'success' => true,
                'careers' => $careers,
                'count' => count($careers)
            ]);
            break;

        // MCQ routes
        case (preg_match('/^\/api\/mcq\/questions\/(10|11|12)$/', $uri, $matches) || preg_match('/^\/mcq\/questions\/(10|11|12)$/', $uri, $matches)) && $requestMethod === 'GET':
            $mcqModel = new MCQ();
            $questions = $mcqModel->getQuestionsByGrade($matches[1]);
            echo json_encode([
                'success' => true,
                'data' => $questions,
                'count' => count($questions)
            ]);
            break;
            
        case ($uri === '/api/mcq/submit' || $uri === '/mcq/submit') && $requestMethod === 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['student_id']) || !isset($input['grade_level']) || !isset($input['responses'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: student_id, grade_level, responses'
                ]);
                break;
            }
            
            $mcqModel = new MCQ();
            
            // Save responses
            $saveResult = $mcqModel->saveResponses($input['student_id'], $input['responses']);
            
            if (!$saveResult['success']) {
                http_response_code(500);
                echo json_encode($saveResult);
                break;
            }
            
            // Calculate results
            $results = $mcqModel->calculateResults(
                $input['student_id'], 
                $input['grade_level'],
                $input['time_taken_minutes'] ?? null
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'MCQ responses saved successfully',
                'data' => $results
            ]);
            break;
            
        case (preg_match('/^\/api\/mcq\/results\/(\d+)$/', $uri, $matches) || preg_match('/^\/mcq\/results\/(\d+)$/', $uri, $matches)) && $requestMethod === 'GET':
            $mcqModel = new MCQ();
            $results = $mcqModel->getStudentResults($matches[1]);
            
            if ($results) {
                echo json_encode([
                    'success' => true,
                    'data' => $results
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'No MCQ results found for this student'
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found',
                'debug' => [
                    'original_uri' => $requestUri,
                    'cleaned_uri' => $uri,
                    'method' => $requestMethod
                ],
                'available_endpoints' => [
                    'POST /api/student/create',
                    'POST /api/student/login',
                    'GET /api/student/profile',
                    'GET /api/student/dashboard',
                    'PUT /api/student/update',
                    'DELETE /api/student/reset',
                    'POST /api/student/logout',
                    'GET /api/student/stats',
                    'GET /api/careers',
                    'POST /api/student/academics',
                    'POST /api/student/skills',
                    'POST /api/aptitude/submit',
                    'POST /api/mcq/submit'
                ]
            ]);
    }
} catch (Exception $e) {
    // Clean any output that might interfere with JSON
    ob_clean();
    
    error_log('API Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// End output buffering and send clean response
ob_end_flush();
?>
