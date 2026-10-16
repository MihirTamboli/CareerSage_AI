# CareerSage AI

AI-powered career guidance for Indian students in Classes 10–12.  
This rebuild delivers a clean UI, fresh schema, and working PHP + Flask integration.

## Tech Stack
- Frontend: HTML, CSS, JavaScript
- Backend: PHP (API), Python Flask (AI)
- Database: MySQL (phpMyAdmin)
- Server: Apache (XAMPP)

## Setup (Localhost)
1. Start Apache + MySQL in XAMPP
2. Import schema + seed data:
   - Open `http://localhost/career-guidance-system/database/setup_database.php`
3. Start Python AI server:
   - `python python-ai/app.py`
4. Open frontend:
   - `http://localhost/career-guidance-system/frontend/index.html`

## Folder Structure
```
career-guidance-system/
├── frontend/
├── php-backend/
├── python-ai/
├── database/
└── docs/
```

## Notes
- Camera/microphone are requested in the aptitude test (no media stored).
- Student data is minimal and can be wiped by recreating the database.
- MCQs are sourced from `docs/grade.js`.
"# CareerSage_AI" 
