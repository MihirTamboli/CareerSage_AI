<?php
/**
 * CareerSage AI - Base Controller
 * Abstract base class for all controllers
 */

abstract class BaseController {
    protected $request;
    protected $response;
    protected $studentId;
    protected $jsonInput;

    public function __construct() {
        $this->request = $_REQUEST;
        $this->response = [];
        $this->studentId = $this->getStudentId();
        $this->jsonInput = null;
    }

    /**
     * Get student ID from session or request
     * @return int|null
     */
    protected function getStudentId() {
        // Check session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['student_id'])) {
            return $_SESSION['student_id'];
        }
        
        // Check request parameters
        if (isset($this->request['student_id'])) {
            return (int)$this->request['student_id'];
        }
        
        if (isset($this->request['unique_code'])) {
            $studentModel = new Student();
            $student = $studentModel->getByUniqueCode($this->request['unique_code']);
            return $student ? $student['student_id'] : null;
        }
        
        return null;
    }

    /**
     * Send JSON response
     * @param array $data
     * @param int $statusCode
     */
    protected function sendJsonResponse($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        
        $response = [
            'success' => $statusCode < 400,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!$response['success']) {
            $message = $data['message'] ?? $data['error'] ?? 'Unknown error occurred';
            $response['message'] = $message;
            $response['error'] = $message;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Validate request method
     * @param string $expectedMethod
     */
    protected function validateRequestMethod($expectedMethod) {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($expectedMethod)) {
            $this->sendJsonResponse([
                'message' => 'Method not allowed. Expected: ' . strtoupper($expectedMethod)
            ], 405);
        }
    }

    /**
     * Validate required parameters
     * @param array $requiredParams
     */
    protected function validateRequiredParams($requiredParams) {
        $missing = [];
        
        // Get JSON input first (for POST/PUT requests)
        $jsonData = $this->getJsonInput();
        
        foreach ($requiredParams as $param) {
            // Check JSON data first, then fall back to $_REQUEST
            $value = $jsonData[$param] ?? $this->request[$param] ?? null;
            
            if ($value === null || $value === '') {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            $this->sendJsonResponse([
                'message' => 'Missing required parameters: ' . implode(', ', $missing)
            ], 400);
        }
    }

    /**
     * Sanitize input data
     * @param array $data
     * @return array
     */
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map(function($item) {
                return is_string($item) ? htmlspecialchars(trim($item), ENT_QUOTES, 'UTF-8') : $item;
            }, $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get JSON input from request body
     * @return array
     */
    protected function getJsonInput() {
        if ($this->jsonInput !== null) {
            return $this->jsonInput;
        }
        $input = file_get_contents('php://input');
        if ($input === '' || $input === false) {
            $this->jsonInput = [];
            return $this->jsonInput;
        }
        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendJsonResponse([
                'message' => 'Invalid JSON payload. Please check your request body.'
            ], 400);
        }
        $this->jsonInput = $decoded ?? [];
        return $this->jsonInput;
    }

    /**
     * Log API request
     * @param string $endpoint
     * @param array $data
     */
    protected function logApiRequest($endpoint, $data = []) {
        $logData = [
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'student_id' => $this->studentId,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        error_log('API Request: ' . json_encode($logData));
    }

    /**
     * Handle CORS
     */
    protected function handleCors() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Rate limiting check (simple implementation)
     * @param int $maxRequests
     * @param int $timeWindow
     * @return bool
     */
    protected function checkRateLimit($maxRequests = 100, $timeWindow = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ip = $this->getClientIp();
        $scope = $_SERVER['REQUEST_METHOD'] . ':' . ($_SERVER['REQUEST_URI'] ?? 'unknown');
        $scopeKey = preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $scope);
        $key = "rate_limit_{$scopeKey}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start_time' => time()];
        }
        
        $rateData = $_SESSION[$key];
        
        // Reset if time window expired
        if (time() - $rateData['start_time'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
            return true;
        }
        
        // Check limit
        if ($rateData['count'] >= $maxRequests) {
            $retryAfter = max(1, $timeWindow - (time() - $rateData['start_time']));
            header('Retry-After: ' . $retryAfter);
            $this->sendJsonResponse([
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after_seconds' => $retryAfter
            ], 429);
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }

    /**
     * Get client IP address
     * @return string
     */
    protected function getClientIp() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Validate integer input
     */
    protected function requireInt($value, $field, $min = null, $max = null) {
        $intVal = filter_var($value, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $this->sendJsonResponse(['message' => "{$field} must be a valid integer"], 400);
        }
        if ($min !== null && $intVal < $min) {
            $this->sendJsonResponse(['message' => "{$field} must be at least {$min}"], 400);
        }
        if ($max !== null && $intVal > $max) {
            $this->sendJsonResponse(['message' => "{$field} must be at most {$max}"], 400);
        }
        return $intVal;
    }

    /**
     * Validate numeric input
     */
    protected function requireNumber($value, $field, $min = null, $max = null) {
        if (!is_numeric($value)) {
            $this->sendJsonResponse(['message' => "{$field} must be a valid number"], 400);
        }
        $numVal = floatval($value);
        if ($min !== null && $numVal < $min) {
            $this->sendJsonResponse(['message' => "{$field} must be at least {$min}"], 400);
        }
        if ($max !== null && $numVal > $max) {
            $this->sendJsonResponse(['message' => "{$field} must be at most {$max}"], 400);
        }
        return $numVal;
    }

    /**
     * Validate string input
     */
    protected function requireString($value, $field, $minLen = 1, $maxLen = 255, $pattern = null) {
        if (!is_string($value)) {
            $this->sendJsonResponse(['message' => "{$field} must be a string"], 400);
        }
        $trimmed = trim($value);
        $length = strlen($trimmed);
        if ($length < $minLen) {
            $this->sendJsonResponse(['message' => "{$field} must be at least {$minLen} characters"], 400);
        }
        if ($length > $maxLen) {
            $this->sendJsonResponse(['message' => "{$field} must be at most {$maxLen} characters"], 400);
        }
        if ($pattern && !preg_match($pattern, $trimmed)) {
            $this->sendJsonResponse(['message' => "{$field} format is invalid"], 400);
        }
        return $trimmed;
    }

    /**
     * Validate array input
     */
    protected function requireArray($value, $field, $minLen = 0, $maxLen = null) {
        if (!is_array($value)) {
            $this->sendJsonResponse(['message' => "{$field} must be an array"], 400);
        }
        $count = count($value);
        if ($count < $minLen) {
            $this->sendJsonResponse(['message' => "{$field} must contain at least {$minLen} items"], 400);
        }
        if ($maxLen !== null && $count > $maxLen) {
            $this->sendJsonResponse(['message' => "{$field} must contain at most {$maxLen} items"], 400);
        }
        return $value;
    }

    /**
     * Validate student session
     */
    protected function validateStudentSession() {
        if (!$this->studentId) {
            $this->sendJsonResponse([
                'message' => 'Student session not found. Please login or provide valid student ID.'
            ], 401);
        }
    }

    /**
     * Get pagination parameters
     * @return array
     */
    protected function getPaginationParams() {
        $page = max(1, (int)($this->request['page'] ?? 1));
        $limit = min(100, max(1, (int)($this->request['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Build pagination response
     * @param int $total
     * @param int $page
     * @param int $limit
     * @param array $data
     * @return array
     */
    protected function buildPaginationResponse($total, $page, $limit, $data) {
        $totalPages = ceil($total / $limit);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $total,
                'records_per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1
            ]
        ];
    }

    /**
     * Handle file upload
     * @param string $fieldName
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array|null
     */
    protected function handleFileUpload($fieldName, $allowedTypes = [], $maxSize = 5242880) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        $file = $_FILES[$fieldName];
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $this->sendJsonResponse([
                'message' => 'File size exceeds maximum allowed size.'
            ], 400);
        }
        
        // Check file type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $this->sendJsonResponse([
                    'message' => 'File type not allowed.'
                ], 400);
            }
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = '../assets/uploads/' . $filename;
        
        // Move file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return [
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
                'path' => $uploadPath
            ];
        }
        
        return null;
    }

    /**
     * Common error handler
     * @param Exception $e
     */
    protected function handleError($e) {
        error_log('Controller Error: ' . $e->getMessage());
        
        $this->sendJsonResponse([
            'message' => 'An error occurred while processing your request.',
            'debug' => $this->isDebugMode() ? $e->getMessage() : null
        ], 500);
    }

    /**
     * Check if debug mode is enabled
     * @return bool
     */
    private function isDebugMode() {
        return (defined('DEBUG_MODE') && constant('DEBUG_MODE')) || (getenv('DEBUG_MODE') === 'true');
    }
}
?>
