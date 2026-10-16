<?php
/**
 * Resume Builder API
 * Generates PDF resumes using student profile and career recommendations
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/Database.php';

// Simple PDF generation (FPDF alternative - HTML to PDF)
class ResumeBuilder {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Get student data for resume
     */
    private function getStudentData($studentId) {
        // Get basic info
        $studentQuery = "SELECT * FROM students WHERE student_id = :student_id";
        $stmt = $this->conn->prepare($studentQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return null;
        }

        // Get academic scores
        $academicQuery = "SELECT subject, marks_obtained, max_marks, percentage, exam_type
                          FROM academic_scores WHERE student_id = :student_id
                          ORDER BY subject";
        $stmt = $this->conn->prepare($academicQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $academics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get skills
        $skillsQuery = "SELECT skill_category, skill_name, self_rating
                        FROM skill_ratings WHERE student_id = :student_id
                        ORDER BY self_rating DESC";
        $stmt = $this->conn->prepare($skillsQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get aptitude results (overall)
        $aptitudeQuery = "SELECT score_percentage, created_at
                          FROM aptitude_results
                          WHERE student_id = :student_id
                          ORDER BY created_at DESC
                          LIMIT 1";
        $stmt = $this->conn->prepare($aptitudeQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $aptitude = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get career recommendations
        $careerQuery = "SELECT cr.*, cm.career_title, cm.career_category, cm.description
                        FROM career_recommendations cr
                        JOIN career_master cm ON cr.career_id = cm.career_id
                        WHERE cr.student_id = :student_id
                        ORDER BY cr.match_percentage DESC
                        LIMIT 5";
        $stmt = $this->conn->prepare($careerQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $careers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'student' => $student,
            'academics' => $academics,
            'skills' => $skills,
            'aptitude' => $aptitude,
            'careers' => $careers
        ];
    }

    /**
     * Generate HTML resume
     */
    public function generateResume($studentId) {
        $data = $this->getStudentData($studentId);
        
        if (!$data) {
            return ['success' => false, 'error' => 'Student not found'];
        }

        $student = $data['student'];
        $academics = $data['academics'];
        $skills = $data['skills'];
        $aptitude = $data['aptitude'];
        $careers = $data['careers'];

        // Calculate overall academic percentage
        $avgPercentage = 0;
        if (count($academics) > 0) {
            $avgPercentage = round(array_sum(array_column($academics, 'percentage')) / count($academics), 1);
        }

        // Top skills (rating >= 7)
        $topSkills = array_filter($skills, fn($s) => $s['self_rating'] >= 7);
        $topSkillNames = array_column(array_slice($topSkills, 0, 6), 'skill_name');

        // Generate career objective
        $careerObjective = "A motivated Class " . $student['class_level'] . " student seeking guidance for career development.";
        if (count($careers) > 0) {
            $topCareer = $careers[0]['career_title'];
            $careerObjective = "A dedicated and motivated Class " . $student['class_level'] . " student with strong aptitude for " . $careers[0]['career_category'] . " seeking to pursue a career as a " . $topCareer . ". Committed to continuous learning and skill development.";
        }

        // AI summary + strengths (from recommendations if available)
        $aiSummary = "Generate your AI career report to unlock personalized insights.";
        $aiStrengths = $topSkillNames;
        if (count($careers) > 0) {
            $topCareer = $careers[0];
            $match = floatval($topCareer['match_percentage']);
            if ($match <= 1) {
                $match *= 100;
            }
            $match = round($match);
            $avgText = count($academics) > 0 ? "{$avgPercentage}%" : "N/A";
            $aptitudeText = $aptitude ? round($aptitude['score_percentage']) . "%" : "N/A";
            $aiSummary = "Based on an academic average of {$avgText} and aptitude score of {$aptitudeText}, your strongest match is {$topCareer['career_title']} ({$topCareer['career_category']}) at {$match}% fit.";
            $decodedStrengths = [];
            if (!empty($topCareer['strengths'])) {
                $decodedStrengths = json_decode($topCareer['strengths'], true) ?: [];
            }
            if (!empty($decodedStrengths)) {
                $aiStrengths = array_slice($decodedStrengths, 0, 5);
                $aiSummary .= " Key strengths include " . implode(', ', array_slice($aiStrengths, 0, 3)) . ".";
            }
        }

        $html = $this->buildResumeHTML($student, $academics, $skills, $aptitude, $careers, $avgPercentage, $topSkillNames, $careerObjective, $aiSummary, $aiStrengths);

        return [
            'success' => true,
            'html' => $html,
            'student_code' => $student['unique_code'],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Build resume HTML
     */
    private function buildResumeHTML($student, $academics, $skills, $aptitude, $careers, $avgPercentage, $topSkillNames, $careerObjective, $aiSummary, $aiStrengths) {
        $date = date('F Y');
        $skillsListItems = '';
        foreach ($topSkillNames as $skillName) {
            $skillsListItems .= "<li>{$skillName}</li>";
        }
        if (empty($skillsListItems)) {
            $skillsListItems = "<li>Skill ratings pending</li>";
        }

        $aiStrengthItems = '';
        foreach ($aiStrengths as $strength) {
            $aiStrengthItems .= "<li>{$strength}</li>";
        }
        if (empty($aiStrengthItems)) {
            $aiStrengthItems = "<li>AI strengths will appear after generating recommendations</li>";
        }
        
        // Top 3 career recommendations
        $careerRecommendations = '';
        foreach (array_slice($careers, 0, 3) as $career) {
            // Fix percentage display (handle 0.85 vs 85)
            $match = floatval($career['match_percentage']);
            if ($match <= 1) {
                $match *= 100;
            }
            $match = round($match);
            
            $careerRecommendations .= "
                <div class='career-item'>
                    <strong>{$career['career_title']}</strong> ({$career['career_category']})
                    <span class='match'>Match: {$match}%</span>
                </div>";
        }

        // Academic subjects
        $academicRows = '';
        foreach (array_slice($academics, 0, 8) as $acad) {
            $academicRows .= "<tr><td>{$acad['subject']}</td><td>{$acad['percentage']}%</td></tr>";
        }

        // Aptitude scores
        $aptitudeItems = '';
        if ($aptitude) {
            $aptitudeItems .= "<li>Overall aptitude score: {$aptitude['score_percentage']}%</li>";
        } else {
            $aptitudeItems .= "<li>No aptitude test submitted yet.</li>";
        }

        // Skills by category
        $skillsHTML = '';
        $groupedSkills = [];
        foreach ($skills as $skill) {
            $groupedSkills[$skill['skill_category']][] = $skill;
        }
        foreach ($groupedSkills as $category => $catSkills) {
            $skillNames = array_map(fn($s) => $s['skill_name'] . ' (' . $s['self_rating'] . '/10)', array_slice($catSkills, 0, 3));
            $skillsHTML .= "<p><strong>$category:</strong> " . implode(', ', $skillNames) . "</p>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Resume - {$student['unique_code']}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Source+Sans+3:wght@400;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --ink: #1f2937;
            --muted: #6b7280;
            --accent: #0f766e;
            --line: #e5e7eb;
            --panel: #f8fafc;
        }
        body {
            font-family: 'Source Sans 3', sans-serif;
            line-height: 1.6;
            color: var(--ink);
            background: white;
            padding: 36px;
            max-width: 900px;
            margin: 0 auto;
        }
        h1, h2, h3 { font-family: 'Merriweather', serif; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            border-bottom: 2px solid var(--line);
            padding-bottom: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .header h1 {
            font-size: 26px;
            margin-bottom: 4px;
        }
        .header .subtitle {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header .code {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
        }
        .section { margin-bottom: 22px; }
        .section-title {
            font-size: 13px;
            color: var(--accent);
            border-bottom: 1px solid var(--line);
            padding-bottom: 6px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .objective, .summary { 
            color: var(--muted);
            text-align: justify;
        }
        .career-item {
            background: var(--panel);
            padding: 10px 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            border: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .match {
            background: var(--accent);
            color: white;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th, .table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--line);
        }
        .table th { background: #f9fafb; color: #374151; font-weight: 600; }
        .list-pills {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0;
            margin: 0.5rem 0 0;
        }
        .list-pills li {
            border: 1px solid var(--line);
            background: var(--panel);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
        }
        .list-plain {
            padding-left: 18px;
            margin-top: 6px;
        }
        .list-plain li {
            margin-bottom: 6px;
        }
        .footer {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            text-align: center;
            font-size: 11px;
            color: var(--muted);
        }
        .two-column {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
        }
        @page {
            size: A4;
            margin: 18mm;
        }
        @media print {
            body { padding: 0; }
            .career-item { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>STUDENT CAREER PROFILE</h1>
        <div class="subtitle">CareerSage AI Career Assessment Report</div>
        <div class="code">Student ID: {$student['unique_code']} | Class: {$student['class_level']} | Board: {$student['board_type']}</div>
    </div>

    <div class="section">
        <h2 class="section-title">Career Objective</h2>
        <p class="objective">$careerObjective</p>
    </div>

    <div class="section">
        <h2 class="section-title">AI Summary</h2>
        <p class="summary">$aiSummary</p>
    </div>

    <div class="section">
        <h2 class="section-title">Top Career Recommendations</h2>
        $careerRecommendations
    </div>

    <div class="section">
        <h2 class="section-title">AI Strengths</h2>
        <ul class="list-pills">
            $aiStrengthItems
        </ul>
    </div>

    <div class="two-column">
        <div class="section">
            <h2 class="section-title">Academic Performance</h2>
            <table class="table">
                <tr><th>Subject</th><th>Score</th></tr>
                $academicRows
            </table>
            <p style="margin-top: 10px;"><strong>Average: $avgPercentage%</strong></p>
        </div>

        <div class="section">
            <h2 class="section-title">Aptitude Assessment</h2>
            <ul class="list-plain">
                $aptitudeItems
            </ul>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Skills & Competencies</h2>
        $skillsHTML
    </div>

    <div class="section">
        <h2 class="section-title">Top Skills</h2>
        <ul class="list-pills">
            $skillsListItems
        </ul>
    </div>

    <div class="footer">
        <p>Generated by CareerSage AI | $date</p>
        <p>This report is based on self-assessment data and AI analysis. Career recommendations are suggestions for exploration.</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Generate resume as downloadable HTML with print styles
     */
    public function downloadResume($studentId) {
        $result = $this->generateResume($studentId);
        
        if (!$result['success']) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        // Send as HTML for browser printing/PDF save
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="resume_' . $result['student_code'] . '.html"');
        echo $result['html'];
        exit();
    }
}

// Route handling
$builder = new ResumeBuilder();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$studentId = intval($_GET['student_id'] ?? 0);

if ($method === 'GET') {
    if ($action === 'download' && $studentId) {
        $builder->downloadResume($studentId);
    } elseif ($action === 'preview' && $studentId) {
        header('Content-Type: application/json');
        echo json_encode($builder->generateResume($studentId));
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'endpoints' => [
                'GET ?action=preview&student_id=X' => 'Get resume HTML as JSON',
                'GET ?action=download&student_id=X' => 'Download resume as HTML (print to PDF)'
            ]
        ]);
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
