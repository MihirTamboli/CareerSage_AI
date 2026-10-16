# Run Project (Career Guidance System)

This guide covers how to start the full stack: database, PHP app, and Python AI service.

## 1) Start XAMPP Services
1. Open **XAMPP Control Panel**.
2. Start **Apache** and **MySQL**.

## 2) Build / Rebuild Database
Open in your browser:

- Normal setup:
  - `http://localhost/career-guidance-system/database/setup_database.php`
- Fresh rebuild (drops and recreates DB):
  - `http://localhost/career-guidance-system/database/setup_database.php?fresh=1`

This creates the `careersage_ai` database and all required tables.

## 3) Open the Web App
Open in your browser:

- `http://localhost/career-guidance-system/frontend/`

## 4) Start the Python AI Service
Open PowerShell in:

- `C:\xampp\htdocs\career-guidance-system\python-ai`

Then run:

```powershell
.\venv\Scripts\activate
python app.py
```

If you see: `Unknown database 'careersage_ai'`, repeat Step 2.

## 5) Quick Verification
1. Register a new user.
2. Login.
3. Start Aptitude test.
4. Submit test and view AI report.
5. Check phpMyAdmin tables: `mcq_responses`, `mcq_results` should populate.
