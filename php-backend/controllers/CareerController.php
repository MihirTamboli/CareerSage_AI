<?php
/**
 * CareerSage AI - Career Controller
 * Handles career-related API endpoints
 */

require_once 'BaseController.php';
require_once '../models/Career.php';
require_once '../models/Student.php';

class CareerController extends BaseController {
    private $careerModel;
    private $studentModel;

    public function __construct() {
        parent::__construct();
        $this->careerModel = new Career();
        $this->studentModel = new Student();
        $this->handleCors();
    }

    /**
     * Get all careers with filters
     * GET /api/careers
     */
    public function getAll() {
        try {
            $this->validateRequestMethod('GET');
            
            $category = $this->request['category'] ?? '';
            $demand = $this->request['demand'] ?? '';
            $growth = $this->request['growth'] ?? '';
            $limit = min(100, max(1, (int)($this->request['limit'] ?? 50)));
            
            $careers = $this->careerModel->getActiveCareers($category, $demand, $growth);
            
            // Limit results
            if (count($careers) > $limit) {
                $careers = array_slice($careers, 0, $limit);
            }
            
            // Decode JSON fields for frontend
            foreach ($careers as &$career) {
                $jsonFields = ['required_skills', 'education_paths', 'entrance_exams', 'top_colleges'];
                foreach ($jsonFields as $field) {
                    if (!empty($career[$field])) {
                        $career[$field] = json_decode($career[$field], true);
                    }
                }
            }
            
            $this->logApiRequest('careers_get_all', [
                'category' => $category,
                'demand' => $demand,
                'growth' => $growth,
                'limit' => $limit
            ]);
            
            $this->sendJsonResponse([
                'careers' => $careers,
                'total' => count($careers),
                'filters' => [
                    'category' => $category,
                    'demand' => $demand,
                    'growth' => $growth
                ]
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get career details by ID
     * GET /api/careers/{id}
     */
    public function getDetails($careerId) {
        try {
            $this->validateRequestMethod('GET');
            
            $career = $this->careerModel->getCareerDetails($careerId);
            
            if (!$career) {
                $this->sendJsonResponse([
                    'message' => 'Career not found'
                ], 404);
            }
            
            // Get education paths
            $educationPaths = $this->careerModel->getEducationPaths($careerId);
            
            $career['education_paths'] = $educationPaths;
            
            $this->logApiRequest('career_get_details', ['career_id' => $careerId]);
            $this->sendJsonResponse($career);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Search careers
     * GET /api/careers/search?q={query}
     */
    public function search() {
        try {
            $this->validateRequestMethod('GET');
            
            $query = $this->request['q'] ?? '';
            if (empty($query)) {
                $this->sendJsonResponse([
                    'message' => 'Search query is required'
                ], 400);
            }
            
            $results = $this->careerModel->searchCareers($query);
            
            // Decode JSON fields
            foreach ($results as &$career) {
                $jsonFields = ['required_skills', 'education_paths', 'entrance_exams', 'top_colleges'];
                foreach ($jsonFields as $field) {
                    if (!empty($career[$field])) {
                        $career[$field] = json_decode($career[$field], true);
                    }
                }
            }
            
            $this->logApiRequest('careers_search', ['query' => $query]);
            $this->sendJsonResponse([
                'query' => $query,
                'results' => $results,
                'total' => count($results)
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get trending careers
     * GET /api/careers/trending
     */
    public function getTrending() {
        try {
            $this->validateRequestMethod('GET');
            
            $limit = min(20, max(1, (int)($this->request['limit'] ?? 10)));
            $trendingCareers = $this->careerModel->getTrendingCareers($limit);
            
            // Decode JSON fields
            foreach ($trendingCareers as &$career) {
                $jsonFields = ['required_skills', 'education_paths', 'entrance_exams', 'top_colleges'];
                foreach ($jsonFields as $field) {
                    if (!empty($career[$field])) {
                        $career[$field] = json_decode($career[$field], true);
                    }
                }
            }
            
            $this->logApiRequest('careers_get_trending', ['limit' => $limit]);
            $this->sendJsonResponse([
                'trending_careers' => $trendingCareers,
                'total' => count($trendingCareers)
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get career categories
     * GET /api/careers/categories
     */
    public function getCategories() {
        try {
            $this->validateRequestMethod('GET');
            
            $categories = $this->careerModel->getCareerCategories();
            
            $this->logApiRequest('careers_get_categories');
            $this->sendJsonResponse([
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get career insights and trends
     * GET /api/careers/insights
     */
    public function getInsights() {
        try {
            $this->validateRequestMethod('GET');
            
            $insights = $this->careerModel->getCareerInsights();
            $demandTrends = $this->careerModel->getDemandTrends();
            $growthTrends = $this->careerModel->getGrowthTrends();
            
            $this->logApiRequest('careers_get_insights');
            $this->sendJsonResponse([
                'insights' => $insights,
                'demand_trends' => $demandTrends,
                'growth_trends' => $growthTrends
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get education paths for student
     * GET /api/education-paths
     */
    public function getEducationPaths() {
        try {
            $this->validateRequestMethod('GET');
            $this->validateStudentSession();
            
            // Get student's recommended careers
            $recommendations = $this->careerModel->getRecommendedCareers($this->studentId, 5);
            
            $educationPaths = [];
            foreach ($recommendations as $rec) {
                $paths = $this->careerModel->getEducationPaths($rec['career_id']);
                $educationPaths[] = [
                    'career_id' => $rec['career_id'],
                    'career_title' => $rec['career_title'],
                    'match_percentage' => $rec['match_percentage'],
                    'education_paths' => $paths,
                    'recommended_stream' => $rec['recommended_stream']
                ];
            }
            
            $this->logApiRequest('education_paths_get');
            $this->sendJsonResponse([
                'education_paths' => $educationPaths,
                'total' => count($educationPaths)
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get market trends analysis
     * GET /api/market-trends
     */
    public function getMarketTrends() {
        try {
            $this->validateRequestMethod('GET');
            
            // Get comprehensive market data
            $insights = $this->careerModel->getCareerInsights();
            $demandTrends = $this->careerModel->getDemandTrends();
            $growthTrends = $this->careerModel->getGrowthTrends();
            $trendingCareers = $this->careerModel->getTrendingCareers(15);
            $categories = $this->careerModel->getCareerCategories();
            
            // Analyze trends
            $marketAnalysis = [
                'total_careers' => $insights['total_careers'],
                'high_demand_percentage' => ($insights['very_high_demand'] + $insights['high_demand']) / $insights['total_careers'] * 100,
                'growth_percentage' => ($insights['rapidly_growing'] + $insights['growing']) / $insights['total_careers'] * 100,
                'top_categories' => array_slice($categories, 0, 5),
                'emerging_fields' => array_filter($trendingCareers, function($career) {
                    return $career['future_growth'] === 'Rapidly Growing';
                }),
                'stable_careers' => array_filter($trendingCareers, function($career) {
                    return $career['job_market_demand'] === 'Very High' && $career['future_growth'] === 'Stable';
                }),
                'demand_distribution' => $demandTrends,
                'growth_distribution' => $growthTrends,
                'future_predictions' => $this->generateFuturePredictions($insights, $demandTrends, $growthTrends)
            ];
            
            $this->logApiRequest('market_trends_get');
            $this->sendJsonResponse($marketAnalysis);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Generate future predictions based on trends
     */
    private function generateFuturePredictions($insights, $demandTrends, $growthTrends) {
        $predictions = [];
        
        // Technology sector prediction
        $techDemand = array_filter($demandTrends, function($trend) {
            return strpos($trend['job_market_demand'], 'High') !== false;
        });
        $predictions['technology'] = [
            'outlook' => 'Excellent',
            'growth_rate' => '25-30%',
            'key_drivers' => ['Digital Transformation', 'AI/ML', 'Cloud Computing', 'Cybersecurity'],
            'time_horizon' => '5-10 years'
        ];
        
        // Healthcare sector prediction
        $predictions['healthcare'] = [
            'outlook' => 'Very Strong',
            'growth_rate' => '20-25%',
            'key_drivers' => ['Aging Population', 'Medical Technology', 'Healthcare Access', 'Preventive Care'],
            'time_horizon' => '5-10 years'
        ];
        
        // Emerging fields prediction
        $predictions['emerging_fields'] = [
            'AI/ML Engineering',
            'Data Science',
            'Cybersecurity',
            'Digital Marketing',
            'Renewable Energy',
            'Biotechnology'
        ];
        
        // Skills in demand prediction
        $predictions['in_demand_skills'] = [
            'Technical Skills' => ['AI/ML', 'Data Analysis', 'Cloud Computing', 'Cybersecurity'],
            'Soft Skills' => ['Communication', 'Problem Solving', 'Adaptability', 'Leadership'],
            'Future Skills' => ['Digital Literacy', 'Critical Thinking', 'Creativity', 'Emotional Intelligence']
        ];
        
        return $predictions;
    }

    /**
     * Get entrance exam information
     * GET /api/entrance-exams
     */
    public function getEntranceExams() {
        try {
            $this->validateRequestMethod('GET');
            
            $stream = $this->request['stream'] ?? '';
            
            // Indian entrance exams data
            $entranceExams = [
                'Science PCM' => [
                    [
                        'name' => 'JEE Main',
                        'full_name' => 'Joint Entrance Examination Main',
                        'purpose' => 'Engineering admissions in NITs, IIITs, and other central institutions',
                        'conducting_body' => 'NTA',
                        'frequency' => 'Twice a year',
                        'difficulty_level' => 'High',
                        'key_subjects' => ['Physics', 'Chemistry', 'Mathematics'],
                        'website' => 'https://jeemain.nta.nic.in/',
                        'preparation_tips' => 'Focus on NCERT books, practice previous year papers, take mock tests'
                    ],
                    [
                        'name' => 'JEE Advanced',
                        'full_name' => 'Joint Entrance Examination Advanced',
                        'purpose' => 'IIT admissions',
                        'conducting_body' => 'IITs',
                        'frequency' => 'Once a year',
                        'difficulty_level' => 'Very High',
                        'key_subjects' => ['Physics', 'Chemistry', 'Mathematics'],
                        'website' => 'https://jeeadv.ac.in/',
                        'preparation_tips' => 'Advanced problem solving, conceptual clarity, time management'
                    ]
                ],
                'Science PCB' => [
                    [
                        'name' => 'NEET',
                        'full_name' => 'National Eligibility cum Entrance Test',
                        'purpose' => 'Medical and dental college admissions',
                        'conducting_body' => 'NTA',
                        'frequency' => 'Once a year',
                        'difficulty_level' => 'High',
                        'key_subjects' => ['Physics', 'Chemistry', 'Biology'],
                        'website' => 'https://neet.nta.nic.in/',
                        'preparation_tips' => 'Strong biology foundation, practice diagrams, revision of NCERT'
                    ]
                ],
                'Commerce' => [
                    [
                        'name' => 'CA Foundation',
                        'full_name' => 'Chartered Accountant Foundation',
                        'purpose' => 'CA course entry',
                        'conducting_body' => 'ICAI',
                        'frequency' => 'Twice a year',
                        'difficulty_level' => 'Medium',
                        'key_subjects' => ['Accounting', 'Economics', 'Mathematics', 'Business Studies'],
                        'website' => 'https://icai.org/',
                        'preparation_tips' => 'Regular practice, conceptual clarity, time management'
                    ],
                    [
                        'name' => 'CUET',
                        'full_name' => 'Common University Entrance Test',
                        'purpose' => 'Central university admissions',
                        'conducting_body' => 'NTA',
                        'frequency' => 'Once a year',
                        'difficulty_level' => 'Medium',
                        'key_subjects' => 'Varies by chosen course',
                        'website' => 'https://cuet.samarth.ac.in/',
                        'preparation_tips' => 'Focus on chosen subjects, practice mock tests'
                    ]
                ],
                'Arts' => [
                    [
                        'name' => 'CUET',
                        'full_name' => 'Common University Entrance Test',
                        'purpose' => 'Central university admissions',
                        'conducting_body' => 'NTA',
                        'frequency' => 'Once a year',
                        'difficulty_level' => 'Medium',
                        'key_subjects' => 'Varies by chosen course',
                        'website' => 'https://cuet.samarth.ac.in/',
                        'preparation_tips' => 'Focus on language and domain subjects, general knowledge'
                    ]
                ]
            ];
            
            $filteredExams = $stream ? ($entranceExams[$stream] ?? []) : $entranceExams;
            
            $this->logApiRequest('entrance_exams_get', ['stream' => $stream]);
            $this->sendJsonResponse([
                'entrance_exams' => $filteredExams,
                'stream' => $stream
            ]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}
?>