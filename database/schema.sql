-- CareerSage AI Clean Schema (Classes 10–12 only)
CREATE DATABASE IF NOT EXISTS careersage_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE careersage_ai;

-- Students (minimal)
CREATE TABLE IF NOT EXISTS students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  unique_code VARCHAR(50) UNIQUE NOT NULL,
  class_level ENUM('10','11','12') NOT NULL,
  board_type ENUM('CBSE','ICSE','State','Other') DEFAULT 'CBSE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active BOOLEAN DEFAULT TRUE
);

-- Academic scores
CREATE TABLE IF NOT EXISTS academic_scores (
  score_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  marks_obtained DECIMAL(5,2) NOT NULL,
  max_marks DECIMAL(5,2) NOT NULL,
  percentage DECIMAL(5,2) GENERATED ALWAYS AS (marks_obtained / max_marks * 100) STORED,
  exam_type ENUM('Unit','Midterm','Final','Board') DEFAULT 'Final',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Aptitude results (overall)
CREATE TABLE IF NOT EXISTS aptitude_results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  score_percentage DECIMAL(5,2) NOT NULL,
  domain_scores JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Skill ratings
CREATE TABLE IF NOT EXISTS skill_ratings (
  skill_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  skill_category VARCHAR(100) NOT NULL,
  skill_name VARCHAR(200) NOT NULL,
  self_rating INT CHECK (self_rating BETWEEN 1 AND 10),
  interest_level INT CHECK (interest_level BETWEEN 1 AND 5),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Career master
CREATE TABLE IF NOT EXISTS career_master (
  career_id INT AUTO_INCREMENT PRIMARY KEY,
  career_title VARCHAR(200) NOT NULL,
  career_category VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  required_skills JSON,
  average_salary_range VARCHAR(100),
  job_market_demand ENUM('Low','Medium','High','Very High') NOT NULL,
  future_growth ENUM('Declining','Stable','Growing','Rapidly Growing') NOT NULL,
  work_environment TEXT,
  key_responsibilities TEXT,
  indian_context TEXT,
  entrance_exams JSON,
  top_colleges JSON,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Education paths
CREATE TABLE IF NOT EXISTS education_paths (
  path_id INT AUTO_INCREMENT PRIMARY KEY,
  career_id INT NOT NULL,
  stream_after_10 VARCHAR(200) NOT NULL,
  stream_after_12 VARCHAR(200) NOT NULL,
  courses JSON,
  entrance_exams JSON,
  top_colleges JSON,
  FOREIGN KEY (career_id) REFERENCES career_master(career_id) ON DELETE CASCADE
);

-- Recommendations
CREATE TABLE IF NOT EXISTS career_recommendations (
  recommendation_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  career_id INT NOT NULL,
  match_percentage DECIMAL(5,2) NOT NULL,
  recommendation_score DECIMAL(6,2) NOT NULL,
  strengths TEXT,
  improvement_areas TEXT,
  recommended_stream VARCHAR(200),
  confidence_level ENUM('Low','Medium','High') NOT NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (career_id) REFERENCES career_master(career_id) ON DELETE CASCADE
);

-- System logs
CREATE TABLE IF NOT EXISTS system_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  log_level ENUM('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  module VARCHAR(100) NOT NULL,
  action VARCHAR(200) NOT NULL,
  student_id INT NULL,
  message TEXT NOT NULL,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL
);

-- MCQ questions (registration assessment)
CREATE TABLE IF NOT EXISTS mcq_questions (
  question_id INT AUTO_INCREMENT PRIMARY KEY,
  grade_level ENUM('10','11','12') NOT NULL,
  question_text TEXT NOT NULL,
  option_a VARCHAR(500) NOT NULL,
  option_b VARCHAR(500) NOT NULL,
  option_c VARCHAR(500) NOT NULL,
  option_d VARCHAR(500) NOT NULL,
  correct_answer ENUM('A','B','C','D') NOT NULL,
  domain VARCHAR(100) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  question_order INT NOT NULL,
  difficulty_level ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
  is_active BOOLEAN DEFAULT TRUE
);

-- MCQ responses
CREATE TABLE IF NOT EXISTS mcq_responses (
  response_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_answer ENUM('A','B','C','D') NOT NULL,
  is_correct BOOLEAN NOT NULL,
  time_taken_seconds INT DEFAULT NULL,
  answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_student_question (student_id, question_id),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES mcq_questions(question_id) ON DELETE CASCADE
);

-- MCQ results
CREATE TABLE IF NOT EXISTS mcq_results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  grade_level ENUM('10','11','12') NOT NULL,
  total_questions INT NOT NULL DEFAULT 30,
  correct_answers INT NOT NULL DEFAULT 0,
  incorrect_answers INT NOT NULL DEFAULT 0,
  unanswered INT NOT NULL DEFAULT 0,
  percentage_score DECIMAL(5,2) NOT NULL,
  time_taken_minutes INT DEFAULT NULL,
  domain_scores JSON DEFAULT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_student_grade (student_id, grade_level),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);
