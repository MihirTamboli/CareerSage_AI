"""
CareerSage AI - Python Flask AI Recommendation Engine
AI-powered career guidance system for Indian students
"""

from flask import Flask, request, jsonify, render_template_string
from flask_cors import CORS
import mysql.connector
import json
import numpy as np
import datetime
import logging
import os
import time
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import pickle
import pandas as pd

# Anthropic Claude API
try:
    import anthropic
    ANTHROPIC_AVAILABLE = True
except ImportError:
    ANTHROPIC_AVAILABLE = False
    print("Warning: anthropic package not installed. Claude AI features disabled.")

# Initialize Flask app
from flask.json.provider import DefaultJSONProvider
import decimal

# Custom JSON Provider to handle all MySQL data types
class CustomJSONProvider(DefaultJSONProvider):
    def default(self, obj):
        if isinstance(obj, decimal.Decimal):
            return float(obj)
        if isinstance(obj, (datetime.date, datetime.datetime)):
            return obj.isoformat()
        if isinstance(obj, bytes):
            return obj.decode('utf-8')
        return super().default(obj)

app = Flask(__name__)
app.json = CustomJSONProvider(app)
CORS(app)

# Configuration
app.config['DEBUG'] = True
app.config['SECRET_KEY'] = 'careersage_ai_secret_key_2024'

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'careersage_ai',
    'charset': 'utf8mb4',
    'autocommit': True
}

# Simple in-memory rate limiter (per process)
RATE_LIMITS = {}

def check_rate_limit(ip, scope='default', max_requests=20, window_seconds=60):
    key = f"{scope}:{ip}"
    now = time.time()
    record = RATE_LIMITS.get(key, {'count': 0, 'start': now})
    if now - record['start'] > window_seconds:
        record = {'count': 0, 'start': now}
    if record['count'] >= max_requests:
        retry_after = max(1, int(window_seconds - (now - record['start'])))
        return False, retry_after
    record['count'] += 1
    RATE_LIMITS[key] = record
    return True, None

def parse_positive_int(value, default=None, min_value=1, max_value=None):
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        return default
    if parsed < min_value:
        return default
    if max_value is not None and parsed > max_value:
        return default
    return parsed

# Database helper
def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('careersage_ai.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class DatabaseManager:
    """Database connection manager"""
    
    def __init__(self):
        self.config = DB_CONFIG
        self.connection = None
    
    def connect(self):
        """Establish database connection"""
        try:
            if not self.connection or not self.connection.is_connected():
                self.connection = mysql.connector.connect(**self.config)
                logger.info("Database connection established")
            return self.connection
        except mysql.connector.Error as err:
            logger.error(f"Database connection error: {err}")
            raise
    
    def execute_query(self, query, params=None, fetch=True):
        """Execute database query"""
        cursor = None
        try:
            conn = self.connect()
            cursor = conn.cursor(dictionary=True)
            cursor.execute(query, params)
            
            if fetch:
                result = cursor.fetchall()
            else:
                result = cursor.lastrowid
            return result
        except mysql.connector.Error as err:
            logger.error(f"Query execution error: {err}")
            raise
        finally:
            if cursor is not None:
                cursor.close()
    
    def close(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("Database connection closed")

class AIRecommendationEngine:
    """AI-powered career recommendation engine"""
    
    def __init__(self):
        self.db = DatabaseManager()
        self.career_data = None
        self.skill_matrix = None
        self.vectorizer = None
        self.load_career_data()
    
    def load_career_data(self):
        """Load career data from database"""
        try:
            query = """
                SELECT career_id, career_title, career_category, description, 
                       required_skills, job_market_demand, future_growth,
                       indian_context
                FROM career_master 
                WHERE is_active = TRUE
            """
            self.career_data = self.db.execute_query(query)
            logger.info(f"Loaded {len(self.career_data)} careers from database")
        except Exception as e:
            logger.error(f"Error loading career data: {e}")
            raise
    
    def analyze_student_profile(self, student_id):
        """Analyze student's academic and aptitude profile"""
        try:
            # Get student info
            student_query = """
                SELECT class_level, board_type
                FROM students
                WHERE student_id = %s
            """
            student_info = self.db.execute_query(student_query, (student_id,))
            student_info = student_info[0] if student_info else {}

            # Get academic performance
            academic_query = """
                SELECT subject, AVG(percentage) as avg_percentage
                FROM academic_scores 
                WHERE student_id = %s
                GROUP BY subject
            """
            academic_data = self.db.execute_query(academic_query, (student_id,))
            
            # Get aptitude results (overall)
            aptitude_query = """
                SELECT AVG(score_percentage) as avg_score
                FROM aptitude_results
                WHERE student_id = %s
            """
            aptitude_data = self.db.execute_query(aptitude_query, (student_id,))
            
            # Get skill ratings
            skills_query = """
                SELECT skill_category, skill_name, self_rating, interest_level
                FROM skill_ratings 
                WHERE student_id = %s
            """
            skills_data = self.db.execute_query(skills_query, (student_id,))

            stream_profile = self.infer_stream_from_academics(academic_data, student_info.get('class_level'))
            
            return {
                'student_info': student_info,
                'academic_performance': academic_data,
                'aptitude_scores': aptitude_data,
                'skill_ratings': skills_data,
                'stream_profile': stream_profile
            }
        except Exception as e:
            logger.error(f"Error analyzing student profile: {e}")
            raise
    
    def calculate_career_match(self, student_profile, career):
        """Calculate match percentage between student and career"""
        try:
            match_score = 0.0
            factors = []
            
            # Academic performance factor (28%)
            academic_score = self.calculate_academic_match(student_profile.get('academic_performance', []), career)
            factors.append(('academic', academic_score, 0.28))
            
            # Aptitude test factor (37%)
            aptitude_score = self.calculate_aptitude_match(student_profile.get('aptitude_scores', []), career)
            factors.append(('aptitude', aptitude_score, 0.37))
            
            # Skills and interests factor (25%)
            skills_score = self.calculate_skills_match(student_profile.get('skill_ratings', []), career)
            factors.append(('skills', skills_score, 0.25))

            # Stream alignment factor (10%)
            stream_score = self.calculate_stream_fit(student_profile, career)
            factors.append(('stream', stream_score, 0.10))
            
            # Calculate weighted average
            total_score = sum(score * weight for factor, score, weight in factors)
            
            return {
                'match_percentage': min(100.0, max(0.0, total_score)),
                'factors': factors,
                'breakdown': {
                    'academic_score': academic_score,
                    'aptitude_score': aptitude_score,
                    'skills_score': skills_score,
                    'stream_score': stream_score
                },
                'gap_analysis': self.analyze_skill_interest_gap(student_profile.get('skill_ratings', []), career)
            }
        except Exception as e:
            logger.error(f"Error calculating career match: {e}")
            return {
                'match_percentage': 0.0,
                'factors': [],
                'breakdown': {
                    'academic_score': 0.0,
                    'aptitude_score': 0.0,
                    'skills_score': 0.0,
                    'stream_score': 0.0
                },
                'gap_analysis': {'gaps': [], 'strengths': []}
            }
    
    def calculate_academic_match(self, academic_data, career):
        """Calculate academic performance match"""
        if not academic_data:
            return 0.5  # Default score if no data
        
        # Define subject relevance for different career categories
        subject_relevance = {
            'Technology': {'Mathematics': 0.9, 'Physics': 0.8, 'Chemistry': 0.6, 'Computer Science': 1.0},
            'Healthcare': {'Biology': 1.0, 'Chemistry': 0.9, 'Physics': 0.7, 'Mathematics': 0.6},
            'Commerce': {'Mathematics': 0.8, 'Accountancy': 1.0, 'Economics': 0.9, 'Business Studies': 0.8},
            'Engineering': {'Mathematics': 0.9, 'Physics': 0.9, 'Chemistry': 0.7, 'Computer Science': 0.8},
            'Arts': {'English': 0.8, 'History': 0.7, 'Geography': 0.6, 'Political Science': 0.6},
            'Legal': {'English': 0.9, 'History': 0.7, 'Political Science': 0.8, 'Economics': 0.6}
        }
        
        career_category = career.get('career_category', '')
        relevance_map = subject_relevance.get(career_category, {})
        
        total_score = 0.0
        total_weight = 0.0
        
        for subject_data in academic_data:
            subject = subject_data['subject']
            percentage = float(subject_data['avg_percentage']) if subject_data['avg_percentage'] is not None else 0.0
            
            if subject in relevance_map:
                weight = relevance_map[subject]
                normalized_score = float(percentage) / 100.0
                total_score += normalized_score * weight
                total_weight += weight
        
        if total_weight > 0:
            return total_score / total_weight
        else:
            return 0.5  # Default score if no relevant subjects

    def infer_stream_from_academics(self, academic_data, class_level=None):
        """Infer most likely stream based on academic strengths"""
        if not academic_data:
            return {
                'inferred_stream': 'Undetermined',
                'confidence': 0.0,
                'scores': {}
            }

        def norm_subject(subject):
            return subject.strip().lower()

        stream_weights = {
            'Science PCM': {
                'mathematics': 1.0,
                'physics': 0.9,
                'chemistry': 0.8,
                'science': 0.8,
                'computer science': 0.7
            },
            'Science PCB': {
                'biology': 1.0,
                'chemistry': 0.9,
                'physics': 0.7,
                'science': 0.8
            },
            'Commerce': {
                'accountancy': 1.0,
                'economics': 0.9,
                'business studies': 0.9,
                'mathematics': 0.6
            },
            'Arts': {
                'english': 0.8,
                'history': 0.7,
                'geography': 0.7,
                'political science': 0.7,
                'social science': 0.8,
                'hindi': 0.5
            }
        }

        stream_scores = {stream: 0.0 for stream in stream_weights.keys()}
        stream_totals = {stream: 0.0 for stream in stream_weights.keys()}

        for entry in academic_data:
            subject = norm_subject(entry['subject'])
            percentage = float(entry['avg_percentage']) if entry['avg_percentage'] is not None else 0.0
            for stream, weights in stream_weights.items():
                if subject in weights:
                    weight = weights[subject]
                    stream_scores[stream] += (percentage / 100.0) * weight
                    stream_totals[stream] += weight

        normalized_scores = {}
        for stream in stream_scores:
            if stream_totals[stream] > 0:
                normalized_scores[stream] = stream_scores[stream] / stream_totals[stream]
            else:
                normalized_scores[stream] = 0.0

        sorted_streams = sorted(normalized_scores.items(), key=lambda x: x[1], reverse=True)
        top_stream, top_score = sorted_streams[0]
        second_score = sorted_streams[1][1] if len(sorted_streams) > 1 else 0.0

        confidence = max(0.0, min(1.0, (top_score - second_score) + 0.5))
        if top_score < 0.45:
            inferred_stream = 'Undetermined'
            confidence = 0.0
        else:
            inferred_stream = top_stream

        return {
            'inferred_stream': inferred_stream,
            'confidence': confidence,
            'scores': normalized_scores,
            'class_level': class_level
        }

    def calculate_stream_fit(self, student_profile, career):
        """Calculate stream alignment score for the career"""
        stream_profile = student_profile.get('stream_profile', {})
        inferred_stream = stream_profile.get('inferred_stream', 'Undetermined')

        category_to_stream = {
            'Technology': ['Science PCM'],
            'Engineering': ['Science PCM'],
            'Healthcare': ['Science PCB'],
            'Commerce': ['Commerce'],
            'Arts': ['Arts'],
            'Legal': ['Arts', 'Commerce']
        }

        preferred_streams = category_to_stream.get(career.get('career_category', ''), [])
        if not preferred_streams or inferred_stream == 'Undetermined':
            return 0.5
        if inferred_stream in preferred_streams:
            return 1.0
        if inferred_stream in ['Science PCM', 'Science PCB'] and 'Science PCM' in preferred_streams:
            return 0.7
        if inferred_stream in ['Science PCM', 'Science PCB'] and 'Science PCB' in preferred_streams:
            return 0.7
        return 0.4
    
    def calculate_aptitude_match(self, aptitude_data, career):
        """Calculate aptitude test match"""
        if not aptitude_data:
            return 0.5  # Default score if no data
        
        # Overall aptitude score (percentage) to 0-1 scale
        avg_score = aptitude_data[0].get('avg_score') if isinstance(aptitude_data, list) and aptitude_data else None
        if avg_score is None:
            return 0.5
        return max(0.0, min(1.0, float(avg_score) / 100.0))
    
    def calculate_skills_match(self, skills_data, career):
        """Calculate skills and interests match"""
        if not skills_data:
            return 0.5  # Default score if no data
        
        # Get required skills for career
        required_skills = []
        if career.get('required_skills'):
            try:
                required_skills = json.loads(career['required_skills'])
            except:
                required_skills = []
        
        if not required_skills:
            return 0.5  # Default score if no required skills specified
        
        # Calculate skill match
        skill_matches = []
        for skill_data in skills_data:
            skill_name = skill_data['skill_name']
            self_rating = float(skill_data.get('self_rating', 5))
            interest_level = float(skill_data.get('interest_level', 3))
            
            # Check if skill matches required skills
            skill_match = 0.0
            for req_skill in required_skills:
                if req_skill.lower() in skill_name.lower() or skill_name.lower() in req_skill.lower():
                    skill_match = max(skill_match, (self_rating / 10.0) * 0.6 + (interest_level / 5.0) * 0.4)
            
            if skill_match > 0:
                skill_matches.append(skill_match)
        
        if skill_matches:
            return sum(skill_matches) / len(skill_matches)
        else:
            return 0.3  # Low score if no skill matches

    def analyze_skill_interest_gap(self, skills_data, career):
        """Analyze interest-skill gaps for required career skills"""
        gaps = []
        strengths = []

        required_skills = []
        if career.get('required_skills'):
            try:
                required_skills = json.loads(career['required_skills'])
            except Exception:
                required_skills = []

        if not required_skills or not skills_data:
            return {'gaps': gaps, 'strengths': strengths}

        def find_best_match(req_skill):
            req = req_skill.lower()
            best = None
            for s in skills_data:
                name = s['skill_name'].lower()
                if req in name or name in req:
                    best = s
                    break
            return best

        for req_skill in required_skills:
            match = find_best_match(req_skill)
            if not match:
                gaps.append(f"Limited exposure to {req_skill}")
                continue

            self_rating = float(match.get('self_rating', 5))
            interest_level = float(match.get('interest_level', 3))

            if interest_level >= 4 and self_rating <= 5:
                gaps.append(f"High interest but low skill in {req_skill}")
            if self_rating >= 8 and interest_level <= 2:
                gaps.append(f"Strong skill but low interest in {req_skill}")
            if self_rating >= 7 and interest_level >= 4:
                strengths.append(f"Strong and interested in {req_skill}")

        return {'gaps': gaps, 'strengths': strengths}
    
    def generate_recommendations(self, student_id, limit=10):
        """Generate career recommendations for student"""
        try:
            # Analyze student profile
            student_profile = self.analyze_student_profile(student_id)
            
            # Calculate match scores for all careers
            career_matches = []
            for career in self.career_data:
                match_result = self.calculate_career_match(student_profile, career)
                
                career_matches.append({
                    'career_id': career['career_id'],
                    'career_title': career['career_title'],
                    'career_category': career['career_category'],
                    'match_percentage': match_result['match_percentage'],
                    'recommendation_score': match_result['match_percentage'] * 10,  # Scale to 0-1000
                    'factors': match_result['factors'],
                    'breakdown': match_result['breakdown'],
                    'gap_analysis': match_result.get('gap_analysis', {'gaps': [], 'strengths': []})
                })
            
            # Sort by match percentage
            career_matches.sort(key=lambda x: x['match_percentage'], reverse=True)
            
            # Get top recommendations
            top_recommendations = career_matches[:limit]
            
            # Generate insights for top recommendations
            for rec in top_recommendations:
                rec['strengths'] = self.generate_strengths_insights(student_profile, rec)
                rec['improvement_areas'] = self.generate_improvement_insights(student_profile, rec)
                rec['recommended_stream'] = self.recommend_stream(rec['career_category'], student_profile)
                rec['confidence_level'] = self.calculate_confidence_level(rec['match_percentage'])
                rec['score_explanation'] = self.build_score_explanation(student_profile, rec)
                rec['stream_profile'] = student_profile.get('stream_profile', {})
                rec['stream_fit'] = self.build_stream_fit(student_profile, rec)
                rec['why_suitable'] = self.generate_why_suitable(student_profile, rec)
            
            return top_recommendations
        except Exception as e:
            logger.error(f"Error generating recommendations: {e}")
            raise
    
    def generate_strengths_insights(self, student_profile, recommendation):
        """Generate strengths insights for recommendation"""
        strengths = []
        breakdown = recommendation['breakdown']
        gap_analysis = recommendation.get('gap_analysis', {})
        
        if breakdown['academic_score'] > 0.7:
            strengths.append("Strong academic performance in relevant subjects")
        
        if breakdown['aptitude_score'] > 0.7:
            strengths.append("High aptitude scores in key areas")
        
        if breakdown['skills_score'] > 0.7:
            strengths.append("Good alignment of skills and interests")

        if breakdown.get('stream_score', 0.5) >= 0.7:
            strengths.append("Academic stream aligns with this career path")

        for item in gap_analysis.get('strengths', [])[:2]:
            strengths.append(item)
        
        if not strengths:
            strengths.append("Potential for growth in this career path")
        
        return strengths
    
    def generate_improvement_insights(self, student_profile, recommendation):
        """Generate improvement areas insights for recommendation"""
        improvements = []
        breakdown = recommendation['breakdown']
        gap_analysis = recommendation.get('gap_analysis', {})
        stream_profile = student_profile.get('stream_profile', {})
        
        if breakdown['academic_score'] < 0.5:
            improvements.append("Focus on improving academic performance in key subjects")
        
        if breakdown['aptitude_score'] < 0.5:
            improvements.append("Consider additional aptitude development")
        
        if breakdown['skills_score'] < 0.5:
            improvements.append("Develop relevant skills through courses and practice")

        if breakdown.get('stream_score', 0.5) < 0.6 and stream_profile.get('inferred_stream') not in ['Undetermined', None]:
            improvements.append("Consider aligning subjects with this career's preferred stream")

        for item in gap_analysis.get('gaps', [])[:2]:
            improvements.append(item)
        
        if not improvements:
            improvements.append("Continue current learning path")
        
        return improvements
    
    def recommend_stream(self, career_category, student_profile):
        """Recommend academic stream based on career and student profile"""
        stream_recommendations = {
            'Technology': 'Science with Computer Science/PCM',
            'Healthcare': 'Science with Biology (PCB)',
            'Commerce': 'Commerce with Mathematics',
            'Engineering': 'Science with PCM',
            'Arts': 'Arts/Humanities',
            'Legal': 'Arts/Humanities or Commerce'
        }
        
        return stream_recommendations.get(career_category, 'Based on career requirements')
    
    def calculate_confidence_level(self, match_percentage):
        """Calculate confidence level for recommendation"""
        if match_percentage >= 80:
            return 'High'
        elif match_percentage >= 60:
            return 'Medium'
        else:
            return 'Low'

    def build_score_explanation(self, student_profile, recommendation):
        """Explain how the score was calculated"""
        breakdown = recommendation.get('breakdown', {})
        academic = breakdown.get('academic_score', 0.0) * 100
        aptitude = breakdown.get('aptitude_score', 0.0) * 100
        skills = breakdown.get('skills_score', 0.0) * 100
        stream = breakdown.get('stream_score', 0.0) * 100
        stream_profile = student_profile.get('stream_profile', {})
        inferred = stream_profile.get('inferred_stream', 'Undetermined')

        parts = [
            f"Academics {academic:.0f}%",
            f"Aptitude {aptitude:.0f}%",
            f"Skills {skills:.0f}%"
        ]
        if inferred != 'Undetermined':
            parts.append(f"Stream fit {stream:.0f}% ({inferred})")
        else:
            parts.append("Stream fit pending")

        return " | ".join(parts)

    def build_stream_fit(self, student_profile, recommendation):
        """Build stream alignment details for UI display"""
        breakdown = recommendation.get('breakdown', {})
        stream_score = round(breakdown.get('stream_score', 0.0) * 100)
        stream_profile = student_profile.get('stream_profile', {})
        inferred = stream_profile.get('inferred_stream', 'Undetermined')
        recommended = recommendation.get('recommended_stream', '')

        if inferred == 'Undetermined':
            status = 'Pending'
        elif stream_score >= 70:
            status = 'Aligned'
        elif stream_score >= 50:
            status = 'Partially Aligned'
        else:
            status = 'Needs Alignment'

        return {
            'score': stream_score,
            'inferred_stream': inferred,
            'recommended_stream': recommended,
            'status': status,
            'confidence': stream_profile.get('confidence', 0.0)
        }

    def generate_why_suitable(self, student_profile, recommendation):
        """Generate a concise explanation for why the career fits"""
        breakdown = recommendation.get('breakdown', {})
        stream_profile = student_profile.get('stream_profile', {})
        strengths = recommendation.get('strengths', [])

        reasons = []
        if breakdown.get('academic_score', 0.0) >= 0.65:
            reasons.append("strong academics")
        if breakdown.get('aptitude_score', 0.0) >= 0.65:
            reasons.append("solid aptitude")
        if breakdown.get('skills_score', 0.0) >= 0.65:
            reasons.append("skills that match this role")
        if breakdown.get('stream_score', 0.0) >= 0.7 and stream_profile.get('inferred_stream') not in ['Undetermined', None]:
            reasons.append(f"stream alignment with {stream_profile.get('inferred_stream')}")

        if not reasons:
            reasons.append("overall profile alignment")

        if len(reasons) > 1:
            reason_text = ", ".join(reasons[:-1]) + " and " + reasons[-1]
        else:
            reason_text = reasons[0]

        highlight = f" Key strength: {strengths[0]}." if strengths else ""
        return f"This career fits you due to {reason_text}.{highlight}"
    
    def save_recommendations(self, student_id, recommendations):
        """Save recommendations to database"""
        try:
            # Clear existing recommendations for student
            delete_query = "DELETE FROM career_recommendations WHERE student_id = %s"
            self.db.execute_query(delete_query, (student_id,), fetch=False)
            
            # Insert new recommendations
            for rec in recommendations:
                insert_query = """
                    INSERT INTO career_recommendations 
                    (student_id, career_id, match_percentage, recommendation_score,
                     strengths, improvement_areas, recommended_stream, confidence_level)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                params = (
                    student_id,
                    rec['career_id'],
                    rec['match_percentage'],
                    rec['recommendation_score'],
                    json.dumps(rec['strengths']),
                    json.dumps(rec['improvement_areas']),
                    rec['recommended_stream'],
                    rec['confidence_level']
                )
                
                self.db.execute_query(insert_query, params, fetch=False)
            
            logger.info(f"Saved {len(recommendations)} recommendations for student {student_id}")
        except Exception as e:
            logger.error(f"Error saving recommendations: {e}")
            raise

# Initialize AI engine
ai_engine = AIRecommendationEngine()

@app.route('/')
def index():
    """API root endpoint"""
    return jsonify({
        'name': 'CareerSage AI Recommendation Engine',
        'version': '1.0.0',
        'status': 'running',
        'timestamp': datetime.datetime.now().isoformat(),
        'endpoints': {
            'POST /api/recommendations/generate': 'Generate career recommendations',
            'GET /api/recommendations/<student_id>': 'Get existing recommendations',
            'GET /api/careers': 'Get all careers',
            'GET /api/health': 'Health check'
        }
    })

@app.route('/api/health', methods=['GET'])
def health_check():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM career_master")
        count = cursor.fetchone()[0]
        conn.close()
        return jsonify({
            'status': 'healthy',
            'database': 'connected',
            'careers_loaded': count,
            'timestamp': datetime.datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'status': 'unhealthy', 'error': str(e)}), 500

@app.route('/api/recommendations/generate', methods=['POST'])
def generate_recommendations():
    """Generate career recommendations for student"""
    try:
        data = request.get_json()
        
        if not data or 'student_id' not in data:
            return jsonify({'error': 'student_id is required'}), 400

        client_ip = request.headers.get('X-Forwarded-For', request.remote_addr) or 'unknown'
        allowed, retry_after = check_rate_limit(client_ip, 'recommendations_generate', 10, 60)
        if not allowed:
            return jsonify({
                'error': 'Rate limit exceeded. Please try again later.',
                'retry_after_seconds': retry_after
            }), 429

        student_id = parse_positive_int(data.get('student_id'), None, 1)
        if not student_id:
            return jsonify({'error': 'student_id must be a positive integer'}), 400

        limit = parse_positive_int(data.get('limit', 10), 10, 1, 20)
        
        logger.info(f"Generating recommendations for student {student_id}")
        
        # Generate recommendations
        recommendations = ai_engine.generate_recommendations(student_id, limit)
        
        # Save to database
        ai_engine.save_recommendations(student_id, recommendations)
        
        return jsonify({
            'success': True,
            'student_id': student_id,
            'recommendations': recommendations,
            'generated_at': datetime.datetime.now().isoformat(),
            'total_recommendations': len(recommendations)
        })
        
    except Exception as e:
        logger.error(f"Error generating recommendations: {e}")
        return jsonify({'error': 'Failed to generate recommendations'}), 500

@app.route('/api/recommendations/<int:student_id>')
@app.route('/api/recommend/<int:student_id>')  # Alias for frontend compatibility
def get_recommendations(student_id):
    """Get existing recommendations for student"""
    try:
        client_ip = request.headers.get('X-Forwarded-For', request.remote_addr) or 'unknown'
        allowed, retry_after = check_rate_limit(client_ip, 'recommendations_fetch', 60, 60)
        if not allowed:
            return jsonify({
                'error': 'Rate limit exceeded. Please try again later.',
                'retry_after_seconds': retry_after
            }), 429

        query = """
            SELECT cr.*, cm.career_title, cm.career_category, cm.description, cm.required_skills
            FROM career_recommendations cr
            JOIN career_master cm ON cr.career_id = cm.career_id
            WHERE cr.student_id = %s AND cr.expires_at > NOW()
            ORDER BY cr.match_percentage DESC
        """
        
        recommendations = ai_engine.db.execute_query(query, (student_id,))
        student_profile = ai_engine.analyze_student_profile(student_id)

        for rec in recommendations:
            if isinstance(rec.get('strengths'), str):
                try:
                    rec['strengths'] = json.loads(rec['strengths'])
                except Exception:
                    rec['strengths'] = []
            if isinstance(rec.get('improvement_areas'), str):
                try:
                    rec['improvement_areas'] = json.loads(rec['improvement_areas'])
                except Exception:
                    rec['improvement_areas'] = []

            career_stub = {
                'career_id': rec.get('career_id'),
                'career_title': rec.get('career_title'),
                'career_category': rec.get('career_category'),
                'description': rec.get('description', ''),
                'required_skills': rec.get('required_skills')
            }
            match_result = ai_engine.calculate_career_match(student_profile, career_stub)
            rec['breakdown'] = match_result.get('breakdown', {})
            rec['gap_analysis'] = match_result.get('gap_analysis', {'gaps': [], 'strengths': []})
            rec['score_explanation'] = ai_engine.build_score_explanation(student_profile, rec)
            rec['stream_profile'] = student_profile.get('stream_profile', {})
            rec['stream_fit'] = ai_engine.build_stream_fit(student_profile, rec)
            rec['why_suitable'] = ai_engine.generate_why_suitable(student_profile, rec)
        
        return jsonify({
            'success': True,
            'student_id': student_id,
            'recommendations': recommendations,
            'total_recommendations': len(recommendations)
        })
        
    except Exception as e:
        logger.error(f"Error getting recommendations: {e}")
        return jsonify({'error': 'Failed to get recommendations'}), 500

@app.route('/api/careers')
def get_careers():
    """Get all available careers"""
    try:
        category = request.args.get('category')
        demand = request.args.get('demand')
        growth = request.args.get('growth')
        
        query = "SELECT * FROM career_master WHERE is_active = TRUE"
        params = []
        
        if category:
            query += " AND career_category = %s"
            params.append(category)
        
        if demand:
            query += " AND job_market_demand = %s"
            params.append(demand)
        
        if growth:
            query += " AND future_growth = %s"
            params.append(growth)
        
        query += " ORDER BY career_title ASC"
        
        careers = ai_engine.db.execute_query(query, params) if params else ai_engine.db.execute_query(query)
        
        return jsonify({
            'success': True,
            'careers': careers,
            'total_careers': len(careers)
        })
        
    except Exception as e:
        logger.error(f"Error getting careers: {e}")
        return jsonify({'error': 'Failed to get careers'}), 500

@app.route('/api/student/<int:student_id>/profile')
def get_student_profile(student_id):
    """Get complete student profile analysis"""
    try:
        profile = ai_engine.analyze_student_profile(student_id)
        
        return jsonify({
            'success': True,
            'student_id': student_id,
            'profile': profile,
            'analyzed_at': datetime.datetime.now().isoformat()
        })
        
    except Exception as e:
        logger.error(f"Error getting student profile: {e}")
        return jsonify({'error': 'Failed to get student profile'}), 500

@app.route('/api/recommendations/claude', methods=['POST'])
def generate_claude_recommendations():
    """Generate AI career recommendations using Claude API"""
    try:
        data = request.get_json()
        
        if not data or 'student_id' not in data:
            return jsonify({'error': 'student_id is required'}), 400

        client_ip = request.headers.get('X-Forwarded-For', request.remote_addr) or 'unknown'
        allowed, retry_after = check_rate_limit(client_ip, 'recommendations_claude', 6, 60)
        if not allowed:
            return jsonify({
                'error': 'Rate limit exceeded. Please try again later.',
                'retry_after_seconds': retry_after
            }), 429

        student_id = parse_positive_int(data.get('student_id'), None, 1)
        if not student_id:
            return jsonify({'error': 'student_id must be a positive integer'}), 400

        limit = parse_positive_int(data.get('limit', 7), 7, 1, 15)
        
        logger.info(f"Generating Claude recommendations for student {student_id}")
        
        # Get student profile data
        student_profile = ai_engine.analyze_student_profile(student_id)
        
        # Get available careers
        careers_query = """
            SELECT career_id, career_title, career_category, description, 
                   required_skills, job_market_demand, future_growth, average_salary_range,
                   indian_context, entrance_exams, top_colleges
            FROM career_master WHERE is_active = TRUE
        """
        careers = ai_engine.db.execute_query(careers_query)
        
        # Build prompt for Claude
        prompt = build_claude_prompt(student_profile, careers, limit)
        
        # Try Claude API first
        if ANTHROPIC_AVAILABLE:
            api_key = os.environ.get('ANTHROPIC_API_KEY')
            if api_key:
                try:
                    claude_result = call_claude_api(api_key, prompt, careers, student_id)
                    if claude_result:
                        return jsonify({
                            'success': True,
                            'student_id': student_id,
                            'recommendations': claude_result,
                            'source': 'claude_ai',
                            'generated_at': datetime.datetime.now().isoformat()
                        })
                except Exception as e:
                    logger.warning(f"Claude API failed, falling back to ML: {e}")
        
        # Fallback to existing ML-based recommendations
        logger.info("Using ML-based recommendations (Claude unavailable)")
        recommendations = ai_engine.generate_recommendations(student_id, limit)
        ai_engine.save_recommendations(student_id, recommendations)
        
        return jsonify({
            'success': True,
            'student_id': student_id,
            'recommendations': recommendations,
            'source': 'ml_algorithm',
            'generated_at': datetime.datetime.now().isoformat()
        })
        
    except Exception as e:
        logger.error(f"Error generating Claude recommendations: {e}")
        return jsonify({'error': str(e)}), 500


def build_claude_prompt(student_profile, careers, limit):
    """Build a detailed prompt for Claude AI"""
    
    # Format academic performance
    academic_summary = "No academic data available"
    if student_profile.get('academic_performance'):
        subjects = [f"{s['subject']}: {s['avg_percentage']:.1f}%" 
                   for s in student_profile['academic_performance']]
        academic_summary = ", ".join(subjects) if subjects else "No academic data"
    
    # Format aptitude scores
    aptitude_summary = "No aptitude test completed"
    if student_profile.get('aptitude_scores'):
        scores = [f"{a['test_category']}: {a['avg_score']:.1f}/10" 
                 for a in student_profile['aptitude_scores']]
        aptitude_summary = ", ".join(scores) if scores else "No aptitude data"
    
    # Format skills
    skills_summary = "No skills rated"
    if student_profile.get('skill_ratings'):
        skills = [f"{s['skill_name']}: {s['self_rating']}/10" 
                 for s in student_profile['skill_ratings']]
        skills_summary = ", ".join(skills) if skills else "No skills data"
    
    # Format career options
    career_list = "\n".join([
        f"- {c['career_title']} ({c['career_category']}): {c['description'][:100]}..."
        for c in careers[:30]  # Limit to first 30 for context window
    ])
    
    prompt = f"""You are CareerSage AI, an expert career counselor for Indian students in Classes 10-12.

STUDENT PROFILE:
- Academic Performance: {academic_summary}
- Aptitude Test Scores: {aptitude_summary}
- Self-Rated Skills: {skills_summary}

AVAILABLE CAREER OPTIONS:
{career_list}

Based on this student's profile, recommend the top {limit} most suitable careers.

For each career, provide:
1. career_title (exact match from options above)
2. match_percentage (0-100, based on profile fit)
3. confidence_level (High/Medium/Low)
4. strengths (2-3 student strengths for this career)
5. improvement_areas (1-2 areas to work on)
6. recommended_stream (for Class 11-12: Science PCM/PCB/Commerce/Arts)
7. why_suitable (1-2 sentence explanation)

Consider Indian education context: JEE, NEET, CLAT, CA, UPSC pathways.

Respond in valid JSON format only:
{{
  "recommendations": [
    {{
      "career_title": "...",
      "match_percentage": 85,
      "confidence_level": "High",
      "strengths": ["...", "..."],
      "improvement_areas": ["..."],
      "recommended_stream": "...",
      "why_suitable": "..."
    }}
  ]
}}"""
    
    return prompt


def call_claude_api(api_key, prompt, careers, student_id):
    """Call Claude API and parse response"""
    try:
        client = anthropic.Anthropic(api_key=api_key)
        
        message = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=2048,
            messages=[
                {"role": "user", "content": prompt}
            ]
        )
        
        # Parse Claude's response
        response_text = message.content[0].text
        
        # Extract JSON from response
        import re
        json_match = re.search(r'\{[\s\S]*\}', response_text)
        if json_match:
            result = json.loads(json_match.group())
            recommendations = result.get('recommendations', [])
            
            # Enrich with career details from database
            career_lookup = {c['career_title']: c for c in careers}
            enriched = []
            
            for rec in recommendations:
                career_title = rec.get('career_title', '')
                if career_title in career_lookup:
                    career = career_lookup[career_title]
                    enriched.append({
                        'career_id': career['career_id'],
                        'career_title': career_title,
                        'career_category': career['career_category'],
                        'match_percentage': rec.get('match_percentage', 70),
                        'recommendation_score': rec.get('match_percentage', 70) * 10,
                        'confidence_level': rec.get('confidence_level', 'Medium'),
                        'strengths': rec.get('strengths', []),
                        'improvement_areas': rec.get('improvement_areas', []),
                        'recommended_stream': rec.get('recommended_stream', ''),
                        'why_suitable': rec.get('why_suitable', ''),
                        'job_market_demand': career.get('job_market_demand', 'Medium'),
                        'future_growth': career.get('future_growth', 'Growing'),
                        'average_salary_range': career.get('average_salary_range', 'Varies'),
                        'indian_context': career.get('indian_context', '')
                    })
            
            # Save to database
            if enriched:
                ai_engine.save_recommendations(student_id, enriched)
            
            return enriched
            
    except Exception as e:
        logger.error(f"Claude API error: {e}")
        raise
    
    return None


@app.route('/api/career-trends')
def get_career_trends():
    """Get trending careers and market insights"""
    try:
        query = """
            SELECT career_title, career_category, job_market_demand, 
                   future_growth, average_salary_range, indian_context
            FROM career_master 
            WHERE is_active = TRUE 
            AND (job_market_demand IN ('High', 'Very High') 
                 OR future_growth IN ('Growing', 'Rapidly Growing'))
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
                END
            LIMIT 15
        """
        
        trending = ai_engine.db.execute_query(query)
        
        # Group by demand level
        high_demand = [c for c in trending if c['job_market_demand'] == 'Very High']
        growing = [c for c in trending if c['future_growth'] == 'Rapidly Growing']
        
        return jsonify({
            'success': True,
            'trends': {
                'high_demand_careers': high_demand,
                'rapidly_growing_careers': growing,
                'all_trending': trending
            },
            'insights': {
                'top_sectors': ['Technology', 'Healthcare', 'Finance'],
                'emerging_fields': ['AI/ML', 'Data Science', 'Cybersecurity', 'Renewable Energy'],
                'stable_choices': ['Government Services', 'Healthcare', 'Education']
            }
        })
        
    except Exception as e:
        logger.error(f"Error getting career trends: {e}")
        return jsonify({'error': 'Failed to get career trends'}), 500


@app.route('/api/education-paths/<int:career_id>')
def get_education_paths(career_id):
    """Get education paths for a specific career"""
    try:
        query = """
            SELECT ep.*, cm.career_title, cm.career_category
            FROM education_paths ep
            JOIN career_master cm ON ep.career_id = cm.career_id
            WHERE ep.career_id = %s
        """
        
        paths = ai_engine.db.execute_query(query, (career_id,))
        
        if not paths:
            # Return career info with default path suggestions
            career_query = "SELECT * FROM career_master WHERE career_id = %s"
            career = ai_engine.db.execute_query(career_query, (career_id,))
            
            if career:
                return jsonify({
                    'success': True,
                    'career': career[0],
                    'paths': [],
                    'message': 'Specific education paths not yet available for this career'
                })
            else:
                return jsonify({'error': 'Career not found'}), 404
        
        return jsonify({
            'success': True,
            'career_id': career_id,
            'career_title': paths[0]['career_title'],
            'paths': paths
        })
        
    except Exception as e:
        logger.error(f"Error getting education paths: {e}")
        return jsonify({'error': 'Failed to get education paths'}), 500


@app.errorhandler(404)
def not_found(error):
    return jsonify({'error': 'Endpoint not found'}), 404

@app.errorhandler(500)
def internal_error(error):
    return jsonify({'error': 'Internal server error'}), 500

if __name__ == '__main__':
    logger.info("Starting CareerSage AI Recommendation Engine")
    app.run(host='0.0.0.0', port=5000, debug=True)
