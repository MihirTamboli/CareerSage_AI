const APP = {
  API_BASE: 'http://localhost/career-guidance-system/php-backend/public/api.php',
  AI_BASE: 'http://localhost:5000'
};

const Utils = {
  getStudentId() {
    return localStorage.getItem('student_id');
  },
  getClassLevel() {
    return localStorage.getItem('class_level');
  },
  setSession(student) {
    localStorage.setItem('student_id', student.student_id);
    localStorage.setItem('unique_code', student.unique_code);
    localStorage.setItem('class_level', student.class_level);
    localStorage.setItem('board_type', student.board_type);
  },
  logout() {
    localStorage.clear();
    window.location.href = 'index.html';
  },
  showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = 'card';
    alert.style.borderColor = type === 'error' ? 'var(--danger)' : type === 'success' ? 'var(--success)' : 'var(--card-border)';
    alert.style.marginBottom = '1rem';
    alert.textContent = message;
    const container = document.querySelector('.container');
    if (container) {
      container.prepend(alert);
      setTimeout(() => alert.remove(), 4000);
    }
  }
};

const Api = {
  async request(path, options = {}) {
    const url = path.startsWith('http') ? path : `${APP.API_BASE}${path}`;
    const config = {
      headers: { 'Content-Type': 'application/json' },
      ...options
    };
    const res = await fetch(url, config);
    const data = await res.json();
    if (!res.ok || data.success === false) {
      const message = data.message || data.error || 'Request failed';
      throw new Error(message);
    }
    return data;
  },
  async aiRequest(url, options = {}) {
    const res = await fetch(url, options);
    const data = await res.json();
    if (!res.ok || data.success === false) {
      const message = data.message || data.error || 'Request failed';
      throw new Error(message);
    }
    return data;
  },
  register(payload) {
    return this.request('/student/create', { method: 'POST', body: JSON.stringify(payload) });
  },
  login(payload) {
    return this.request('/student/login', { method: 'POST', body: JSON.stringify(payload) });
  },
  submitAcademics(payload) {
    return this.request('/student/academics', { method: 'POST', body: JSON.stringify(payload) });
  },
  submitSkills(payload) {
    return this.request('/student/skills', { method: 'POST', body: JSON.stringify(payload) });
  },
  submitAptitude(payload) {
    return this.request('/aptitude/submit', { method: 'POST', body: JSON.stringify(payload) });
  },
  submitMcq(payload) {
    return this.request('/mcq/submit', { method: 'POST', body: JSON.stringify(payload) });
  },
  getDashboard(studentId) {
    return this.request(`/student/dashboard?student_id=${studentId}`, { method: 'GET' });
  },
  getProfile(studentId) {
    return this.request(`/student/profile?student_id=${studentId}`, { method: 'GET' });
  },
  getCareers() {
    return this.request('/careers', { method: 'GET' });
  },
  getRecommendations(studentId) {
    return this.aiRequest(`${APP.AI_BASE}/api/recommend/${studentId}`);
  },
  generateRecommendations(studentId) {
    return this.aiRequest(`${APP.AI_BASE}/api/recommendations/generate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ student_id: studentId })
    });
  },
  getTrends() {
    return this.aiRequest(`${APP.AI_BASE}/api/career-trends`);
  },
  getEducationPaths(careerId) {
    return this.aiRequest(`${APP.AI_BASE}/api/education-paths/${careerId}`);
  }
};

function renderNav(active = '') {
  const nav = document.querySelector('.navbar');
  if (!nav) return;
  const loggedIn = !!Utils.getStudentId();
  nav.innerHTML = `
    <div class="nav-inner">
      <a class="brand" href="index.html">
        <span class="brand-badge">CS</span>
        CareerSage AI
      </a>
      <div class="nav-links">
        <a href="index.html">Home</a>
        ${loggedIn ? `
          <a href="dashboard.html">Dashboard</a>
          <a href="aptitude-test.html">Aptitude</a>
          <a href="recommendations.html">AI Report</a>
          <a href="trends.html">Trends</a>
          <a href="resume.html">Resume</a>
          <a href="careers.html">Careers</a>
          <a href="#" onclick="Utils.logout()">Logout</a>
        ` : `
          <a href="register.html">Register</a>
          <a href="login.html">Login</a>
        `}
      </div>
    </div>
  `;
}

document.addEventListener('DOMContentLoaded', () => {
  renderNav();
});
