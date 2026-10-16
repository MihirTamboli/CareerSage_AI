# CareerSage AI - API Documentation

## PHP API Base URL
```
http://localhost/career-guidance-system/php-backend/public/api.php
```

## Student
POST `/student/create`
```json
{ "class_level": "10", "board_type": "CBSE" }
```

POST `/student/login`
```json
{ "unique_code": "STU20260212ABC123" }
```

GET `/student/profile?student_id=1`  
GET `/student/dashboard?student_id=1`

POST `/student/academics`
```json
{
  "student_id": 1,
  "scores": [
    { "subject": "Mathematics", "marks_obtained": 85, "max_marks": 100 }
  ]
}
```

POST `/student/skills`
```json
{
  "student_id": 1,
  "skills": [
    { "category": "General", "name": "Problem Solving", "rating": 4, "interest": 4 }
  ]
}
```

POST `/aptitude/submit`
```json
{ "student_id": 1, "score": 82 }
```

## Careers
GET `/careers`

## MCQ (Registration)
GET `/mcq/questions/10`  
POST `/mcq/submit`  
GET `/mcq/results/1`

## Python AI (Flask) Base URL
```
http://localhost:5000
```

POST `/api/recommendations/generate`
```json
{ "student_id": 1, "limit": 10 }
```

GET `/api/recommend/1`  
GET `/api/career-trends`  
GET `/api/education-paths/1`
