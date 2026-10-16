/**
 * CareerSage AI - MCQ Handler
 * Manages MCQ display, navigation, answer tracking, and submission
 */

class MCQManager {
    constructor() {
        this.currentGrade = null;
        this.questions = [];
        this.currentQuestionIndex = 0;
        this.answers = {}; // { questionId: selectedAnswer }
        this.startTime = null;
        this.questionStartTime = null;
        this.timeTaken = {}; // { questionId: seconds }
    }

    /**
     * Initialize MCQ for a specific grade
     * @param {string} grade - Grade level (10, 11, or 12)
     */
    init(grade) {
        this.currentGrade = grade;
        // Prefer MCQ_DATA from docs/grade.js, fall back to mcqData if present
        if (typeof MCQ_DATA !== 'undefined') {
            this.questions = this.mapGradeJsData(grade);
        } else if (typeof mcqData !== 'undefined') {
            this.questions = mcqData[grade] || [];
        } else {
            console.error('No MCQ dataset found. Load docs/grade.js or js/mcq-data.js.');
            return false;
        }
        this.currentQuestionIndex = 0;
        this.answers = {};
        this.startTime = Date.now();
        this.timeTaken = {};

        if (this.questions.length === 0) {
            console.error(`No questions found for grade ${grade}`);
            return false;
        }

        this.render();
        return true;
    }

    /**
     * Map docs/grade.js MCQ_DATA format into internal format
     * Ensures question_id matches DB (1-90) by applying grade offset
     */
    mapGradeJsData(grade) {
        const gradeKey = `Grade ${grade}`;
        const gradeData = MCQ_DATA[gradeKey] || [];
        const offset = grade === '10' ? 0 : grade === '11' ? 30 : 60;

        return gradeData.map(item => {
            const options = Array.isArray(item.options) ? item.options : [];
            const optionMap = {
                A: options[0] || '',
                B: options[1] || '',
                C: options[2] || '',
                D: options[3] || ''
            };

            const correctIndex = options.findIndex(opt => opt === item.answer);
            const correctLetter = ['A', 'B', 'C', 'D'][correctIndex] || '';

            return {
                id: offset + (item.id || 0),
                question: item.question,
                options: optionMap,
                correctAnswer: correctLetter,
                domain: item.category,
                category: item.category
            };
        });
    }

    /**
     * Render the current question
     */
    render() {
        const question = this.questions[this.currentQuestionIndex];
        if (!question) return;

        this.questionStartTime = Date.now();

        // Update question number
        const questionNumber = document.getElementById('mcq-question-number');
        if (questionNumber) {
            questionNumber.textContent = `Question ${this.currentQuestionIndex + 1} of ${this.questions.length}`;
        }

        // Update question text
        const questionText = document.getElementById('mcq-question-text');
        if (questionText) {
            questionText.textContent = question.question;
        }

        // Update domain/category badge
        const badge = document.getElementById('mcq-domain-badge');
        if (badge) {
            const label = question.domain || question.category || '';
            badge.textContent = label;
            badge.style.display = label ? 'inline-block' : 'none';
        }

        // Render options
        this.renderOptions(question);

        // Update progress bar
        this.updateProgressBar();

        // Update navigation buttons
        this.updateNavigationButtons();

        // Update question grid
        this.updateQuestionGrid();
    }

    /**
     * Render answer options
     */
    renderOptions(question) {
        const optionsContainer = document.getElementById('mcq-options');
        if (!optionsContainer) return;

        optionsContainer.innerHTML = '';

        const selectedAnswer = this.answers[question.id];

        ['A', 'B', 'C', 'D'].forEach(option => {
            const button = document.createElement('button');
            button.className = 'mcq-option';
            button.dataset.option = option;

            if (selectedAnswer === option) {
                button.classList.add('selected');
            }

            button.innerHTML = `
                <span class="option-letter">${option}</span>
                <span class="option-text">${question.options[option]}</span>
            `;

            button.addEventListener('click', () => this.selectAnswer(option));
            optionsContainer.appendChild(button);
        });
    }

    /**
     * Select an answer
     */
    selectAnswer(option) {
        const question = this.questions[this.currentQuestionIndex];

        // Record time taken for this question
        if (this.questionStartTime) {
            const timeTakenSeconds = Math.floor((Date.now() - this.questionStartTime) / 1000);
            this.timeTaken[question.id] = timeTakenSeconds;
        }

        // Save answer
        this.answers[question.id] = option;

        // Update UI
        const buttons = document.querySelectorAll('.mcq-option');
        buttons.forEach(btn => {
            btn.classList.remove('selected');
            if (btn.dataset.option === option) {
                btn.classList.add('selected');
            }
        });

        // Update question grid
        this.updateQuestionGrid();

        // Auto-advance after a short delay (optional)
        // setTimeout(() => this.nextQuestion(), 500);
    }

    /**
     * Navigate to next question
     */
    nextQuestion() {
        if (this.currentQuestionIndex < this.questions.length - 1) {
            this.currentQuestionIndex++;
            this.render();
        }
    }

    /**
     * Navigate to previous question
     */
    previousQuestion() {
        if (this.currentQuestionIndex > 0) {
            this.currentQuestionIndex--;
            this.render();
        }
    }

    /**
     * Jump to a specific question
     */
    jumpToQuestion(index) {
        if (index >= 0 && index < this.questions.length) {
            this.currentQuestionIndex = index;
            this.render();
        }
    }

    /**
     * Update progress bar
     */
    updateProgressBar() {
        const progressFill = document.getElementById('mcq-progress-fill');
        const progressText = document.getElementById('mcq-progress-text');

        const answeredCount = Object.keys(this.answers).length;
        const totalQuestions = this.questions.length;
        const percentage = (answeredCount / totalQuestions) * 100;

        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }

        if (progressText) {
            progressText.textContent = `${answeredCount} / ${totalQuestions} Answered`;
        }
    }

    /**
     * Update navigation buttons
     */
    updateNavigationButtons() {
        const prevBtn = document.getElementById('mcq-prev-btn');
        const nextBtn = document.getElementById('mcq-next-btn');

        if (prevBtn) {
            prevBtn.disabled = this.currentQuestionIndex === 0;
        }

        if (nextBtn) {
            if (this.currentQuestionIndex === this.questions.length - 1) {
                nextBtn.textContent = 'Review Answers';
            } else {
                nextBtn.textContent = 'Next →';
            }
        }
    }

    /**
     * Update question grid (visual indicator)
     */
    updateQuestionGrid() {
        const grid = document.getElementById('mcq-question-grid');
        if (!grid) return;

        grid.innerHTML = '';

        this.questions.forEach((question, index) => {
            const cell = document.createElement('button');
            cell.className = 'grid-cell';
            cell.textContent = index + 1;

            if (index === this.currentQuestionIndex) {
                cell.classList.add('current');
            }

            if (this.answers[question.id]) {
                cell.classList.add('answered');
            }

            cell.addEventListener('click', () => this.jumpToQuestion(index));
            grid.appendChild(cell);
        });
    }

    /**
     * Validate that all questions are answered
     */
    validateCompletion() {
        const answeredCount = Object.keys(this.answers).length;
        const totalQuestions = this.questions.length;

        if (answeredCount < totalQuestions) {
            const unanswered = totalQuestions - answeredCount;
            return {
                valid: false,
                message: `Please answer all questions. ${unanswered} question(s) remaining.`
            };
        }

        return { valid: true };
    }

    /**
     * Get responses in API format
     */
    getResponses() {
        return this.questions.map(question => ({
            question_id: question.id,
            selected_answer: this.answers[question.id] || '',
            time_taken_seconds: this.timeTaken[question.id] || 0
        }));
    }

    /**
     * Calculate total time taken
     */
    getTotalTimeTaken() {
        if (!this.startTime) return 0;
        return Math.floor((Date.now() - this.startTime) / 60000); // in minutes
    }

    /**
     * Calculate score (client-side preview)
     */
    calculateScore() {
        let correct = 0;
        this.questions.forEach(question => {
            if (this.answers[question.id] === question.correctAnswer) {
                correct++;
            }
        });

        return {
            total: this.questions.length,
            correct: correct,
            incorrect: this.questions.length - correct,
            percentage: ((correct / this.questions.length) * 100).toFixed(2)
        };
    }

    /**
     * Show review modal
     */
    showReview() {
        const validation = this.validateCompletion();

        if (!validation.valid) {
            if (typeof Utils !== 'undefined' && Utils.showAlert) {
                Utils.showAlert(validation.message, 'warning');
            } else {
                alert(validation.message);
            }
            return;
        }

        // Show confirmation modal
        const modal = document.getElementById('mcq-review-modal');
        if (modal) {
            const answeredCount = Object.keys(this.answers).length;
            document.getElementById('review-answered-count').textContent = answeredCount;
            document.getElementById('review-total-count').textContent = this.questions.length;
            modal.style.display = 'flex';
        }
    }

    /**
     * Submit MCQ responses
     */
    async submit(studentId) {
        const validation = this.validateCompletion();

        if (!validation.valid) {
            throw new Error(validation.message);
        }

        const payload = {
            student_id: studentId,
            grade_level: this.currentGrade,
            responses: this.getResponses(),
            time_taken_minutes: this.getTotalTimeTaken()
        };

        try {
            const response = await fetch('http://localhost/career-guidance-system/php-backend/public/api.php/mcq/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to submit MCQ');
            }

            return data;
        } catch (error) {
            console.error('MCQ submission error:', error);
            throw error;
        }
    }

    /**
     * Reset MCQ
     */
    reset() {
        this.currentQuestionIndex = 0;
        this.answers = {};
        this.startTime = null;
        this.questionStartTime = null;
        this.timeTaken = {};
        this.render();
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MCQManager;
}
